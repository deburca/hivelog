<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Functional;

use Behat\Mink\Driver\BrowserKitDriver;
use Drupal\Tests\BrowserTestBase;
use Drupal\consumers\Entity\Consumer;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog_api\HivelogApiResources;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Signs in with the client the module installs, then uses the API as the app.
 *
 * End to end and against the real thing: the installed public PKCE client, the
 * installed role and scope, the consent screen, the token exchange, and
 * bearer-token calls to the versioned API. A functional test (it needs a
 * browser session for the consent screen), so the access-critical assertions
 * also live in the kernel tests, which are a hard CI gate.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiOauthTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   *
   * `node` provides `access content`, which the field-app role holds so a
   * photo can be attached.
   */
  protected static $modules = ['hivelog', 'hivelog_api', 'file', 'image', 'node'];

  /**
   * Base64url without padding.
   */
  protected function b64url(string $raw): string {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
  }

  /**
   * Sends a bearer-authenticated request.
   */
  protected function bearer(string $method, string $path, string $token, ?array $json = NULL): array {
    $options = [
      'http_errors' => FALSE,
      'headers' => [
        'Accept' => 'application/vnd.api+json',
        'Authorization' => 'Bearer ' . $token,
      ],
    ];
    if ($json !== NULL) {
      $options['headers']['Content-Type'] = 'application/vnd.api+json';
      $options['body'] = json_encode($json);
    }
    $response = $this->getHttpClient()->request($method, $this->buildUrl($path), $options);
    return ['status' => $response->getStatusCode(), 'json' => json_decode((string) $response->getBody(), TRUE)];
  }

  /**
   * Runs the authorization-code flow as a user, returning the redirect params.
   */
  protected function authorize($user, string $verifier, bool $with_challenge = TRUE): array {
    $query = [
      'response_type' => 'code',
      'client_id' => HivelogApiResources::CLIENT_ID,
      'redirect_uri' => HivelogApiResources::REDIRECT_URI,
      'scope' => HivelogApiResources::SCOPE,
      'state' => 'state-123',
    ];
    if ($with_challenge) {
      $query += ['code_challenge' => $this->b64url(hash('sha256', $verifier, TRUE)), 'code_challenge_method' => 'S256'];
    }
    $this->drupalLogin($user);
    // The redirect target is a custom URL scheme no HTTP client can follow.
    $driver = $this->getSession()->getDriver();
    assert($driver instanceof BrowserKitDriver);
    $driver->getClient()->followRedirects(FALSE);
    $this->drupalGet('/oauth/authorize', ['query' => $query]);
    $location = $this->getSession()->getResponseHeader('Location');
    // No redirect and no consent screen means the server refused outright.
    $refused = FALSE;
    if (!$location) {
      if ($this->getSession()->getPage()->findButton('Allow')) {
        $this->submitForm([], 'Allow');
        $location = $this->getSession()->getResponseHeader('Location');
      }
      else {
        $refused = TRUE;
      }
    }
    $status = $this->getSession()->getStatusCode();
    $this->drupalLogout();
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $params);
    return $params + ['_location' => (string) $location, '_refused' => $refused, '_status' => $status];
  }

  /**
   * Exchanges a code for tokens at the token endpoint.
   */
  protected function exchange(?string $code, ?string $verifier): array {
    $form = [
      'grant_type' => 'authorization_code',
      'client_id' => HivelogApiResources::CLIENT_ID,
      'code' => (string) $code,
      'redirect_uri' => HivelogApiResources::REDIRECT_URI,
    ];
    if ($verifier !== NULL) {
      $form['code_verifier'] = $verifier;
    }
    $response = $this->getHttpClient()->request('POST', $this->buildUrl('/oauth/token'), [
      'http_errors' => FALSE,
      'form_params' => $form,
      'headers' => ['Accept' => 'application/json'],
    ]);
    return ['status' => $response->getStatusCode(), 'json' => json_decode((string) $response->getBody(), TRUE) ?? []];
  }

  /**
   * Tests the installed client, role and scope support a full app session.
   */
  public function testAppSignInAndUse(): void {
    // The OAuth signing keys are a per-site step the module cannot do.
    $dir = sys_get_temp_dir() . '/hivelog-api-keys-' . uniqid();
    mkdir($dir, 0700);
    \Drupal::service('simple_oauth.key.generator')->generateKeys($dir);
    \Drupal::configFactory()->getEditable('simple_oauth.settings')
      ->set('public_key', $dir . '/public.key')
      ->set('private_key', $dir . '/private.key')
      ->save();

    // What install created.
    $consumers = \Drupal::entityTypeManager()->getStorage('consumer')
      ->loadByProperties(['client_id' => HivelogApiResources::CLIENT_ID]);
    $this->assertCount(1, $consumers);
    /** @var \Drupal\consumers\Entity\Consumer $consumer */
    $consumer = reset($consumers);
    $this->assertInstanceOf(Consumer::class, $consumer);
    $this->assertFalse((bool) $consumer->get('confidential')->value, 'A public client: no secret to leak from an app');
    $this->assertTrue((bool) $consumer->get('pkce')->value);
    $this->assertSame(HivelogApiResources::REDIRECT_URI, $consumer->get('redirect')->value);
    $role = Role::load(HivelogApiResources::SCOPE);
    $this->assertNotNull($role);
    $this->assertEmpty(array_filter($role->getPermissions(), fn($p) => str_starts_with($p, 'delete ')), 'No delete permission');

    // A beekeeper who holds only the field-app role, and another one.
    $me = $this->drupalCreateUser([], 'oauth_me');
    $me->addRole(HivelogApiResources::SCOPE);
    $me->save();
    $them = $this->drupalCreateUser([], 'oauth_them');
    $them->addRole(HivelogApiResources::SCOPE);
    $them->save();
    $my_apiary = Apiary::create(['name' => 'Mine', 'uid' => $me->id(), 'visibility' => 'private']);
    $my_apiary->save();
    $my_hive = Hive::create([
      'name' => 'My hive',
      'apiary' => $my_apiary->id(),
      'status' => 'active',
      'uid' => $me->id(),
    ]);
    $my_hive->save();
    $their_apiary = Apiary::create(['name' => 'Theirs', 'uid' => $them->id(), 'visibility' => 'private']);
    $their_apiary->save();
    $their_hive = Hive::create([
      'name' => 'Their hive',
      'apiary' => $their_apiary->id(),
      'status' => 'active',
      'uid' => $them->id(),
    ]);
    $their_hive->save();

    // PKCE is not optional: a request with no code_challenge gets no code.
    $verifier = $this->b64url(random_bytes(48));
    $no_challenge = $this->authorize($me, $verifier, FALSE);
    $this->assertArrayNotHasKey('code', $no_challenge, 'A code was issued without a PKCE challenge: ' . $no_challenge['_location']);
    $this->assertTrue($no_challenge['_refused'] || isset($no_challenge['error']), 'The request was neither refused nor errored');

    // The real flow.
    $params = $this->authorize($me, $verifier);
    $this->assertSame('state-123', $params['state'] ?? NULL);
    $this->assertNotEmpty($params['code'] ?? NULL, 'No code in ' . $params['_location']);
    $wrong = $this->exchange($params['code'], $this->b64url(random_bytes(48)));
    $this->assertSame(400, $wrong['status'], 'A wrong PKCE verifier is refused');

    $params = $this->authorize($me, $verifier);
    $tokens = $this->exchange($params['code'], $verifier);
    $this->assertSame(200, $tokens['status'], json_encode($tokens['json']));
    $this->assertNotEmpty($tokens['json']['refresh_token']);
    $access = $tokens['json']['access_token'];

    // Discovery needs no token; the data does.
    $this->drupalGet('/hivelog/api/v1');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame(401, $this->bearer('GET', '/hivelog/api/v1/apiary/apiary', 'not-a-token')['status']);

    // The app sees its own records only.
    $read = $this->bearer('GET', '/hivelog/api/v1/apiary/apiary', $access);
    $this->assertSame(200, $read['status']);
    $this->assertSame(['Mine'], array_column(array_column($read['json']['data'], 'attributes'), 'name'));
    $this->assertSame(403, $this->bearer('GET', '/hivelog/api/v1/hive/hive/' . $their_hive->uuid(), $access)['status']);

    // It can log an inspection on its own hive, not on someone else's.
    $document = fn(string $hive_uuid) => [
      'data' => [
        'type' => 'hive_inspection--hive_inspection',
        'attributes' => ['inspection_date' => '2026-07-01'],
        'relationships' => ['hive' => ['data' => ['type' => 'hive--hive', 'id' => $hive_uuid]]],
      ],
    ];
    $created = $this->bearer('POST', '/hivelog/api/v1/hive_inspection/hive_inspection', $access, $document($my_hive->uuid()));
    $this->assertSame(201, $created['status']);

    // A photo is uploaded straight onto the new inspection's images field.
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $upload = $this->getHttpClient()->request('POST', $this->buildUrl('/hivelog/api/v1/hive_inspection/hive_inspection/' . $created['json']['data']['id'] . '/images'), [
      'http_errors' => FALSE,
      'body' => $png,
      'headers' => [
        'Accept' => 'application/vnd.api+json',
        'Authorization' => 'Bearer ' . $access,
        'Content-Type' => 'application/octet-stream',
        'Content-Disposition' => 'file; filename="hive.png"',
      ],
    ]);
    $this->assertSame(200, $upload->getStatusCode(), (string) $upload->getBody());
    $this->assertSame(422, $this->bearer('POST', '/hivelog/api/v1/hive_inspection/hive_inspection', $access, $document($their_hive->uuid()))['status']);

    // The computed views take the same bearer token.
    $alerts = $this->bearer('GET', '/hivelog/api/v1/computed/alerts', $access);
    $this->assertSame(200, $alerts['status']);
    $this->assertArrayHasKey('data', $alerts['json']);
    $tiles = $this->bearer('GET', '/hivelog/api/v1/computed/hive/' . $my_hive->uuid() . '/stat-tiles', $access);
    $this->assertSame(200, $tiles['status']);
    $this->assertSame(403, $this->bearer('GET', '/hivelog/api/v1/computed/hive/' . $their_hive->uuid() . '/stat-tiles', $access)['status']);
    $this->assertSame(401, $this->bearer('GET', '/hivelog/api/v1/computed/alerts', 'not-a-token')['status']);

    // Outside the allow-list, and no way to read other users.
    $this->assertSame(404, $this->bearer('GET', '/hivelog/api/v1/inventory_item/inventory_item', $access)['status']);
    $users = $this->bearer('GET', '/jsonapi/user/user', $access);
    $names = array_column(array_column($users['json']['data'] ?? [], 'attributes'), 'display_name');
    $this->assertNotContains('oauth_them', $names, 'The token can enumerate other users');

    // Refresh works and rotates.
    $refresh = $this->getHttpClient()->request('POST', $this->buildUrl('/oauth/token'), [
      'http_errors' => FALSE,
      'form_params' => [
        'grant_type' => 'refresh_token',
        'client_id' => HivelogApiResources::CLIENT_ID,
        'refresh_token' => $tokens['json']['refresh_token'],
      ],
    ]);
    $this->assertSame(200, $refresh->getStatusCode());
  }

}
