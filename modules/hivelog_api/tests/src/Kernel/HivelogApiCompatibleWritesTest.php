<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog_api\EventSubscriber\HivelogApiWriteCompatibilitySubscriber;
use Drupal\hivelog_api\HivelogApiResources;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Tests writes sent as the content types a shared host's firewall allows.
 *
 * A host firewall on the live site refused `application/vnd.api+json`,
 * `application/octet-stream`, `PATCH` and `PUT` before Drupal saw the request
 * (task 0212). The API accepts `application/json` for a record and
 * `multipart/form-data` for a photo instead, for a request with an
 * `Authorization` header, on the versioned prefix only. An edit or a delete,
 * which the same firewall refuses as `PATCH` and `DELETE`, is sent as a `POST`
 * with `X-HTTP-Method-Override` (task 0220).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiCompatibleWritesTest extends HivelogApiKernelTestBase {

  protected const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

  /**
   * Sends a request with chosen content type and body, through the kernel.
   *
   * @param string $path
   *   The path.
   * @param string $content_type
   *   The Content-Type header, or '' for none.
   * @param string|null $body
   *   The raw body.
   * @param \Drupal\user\Entity\User|null $user
   *   Who to authenticate as (Basic auth stands in for the bearer token), or
   *   NULL to send no credentials.
   * @param \Symfony\Component\HttpFoundation\File\UploadedFile[] $files
   *   Uploaded files, as PHP would have parsed a multipart body.
   * @param array<string, string> $headers
   *   Extra request headers, by name (`X-HTTP-Method-Override`).
   *
   * @return array{status: int, body: array|null, raw: string}
   *   The response.
   */
  protected function send(string $path, string $content_type, ?string $body, $user = NULL, array $files = [], array $headers = []): array {
    $server = ['HTTP_ACCEPT' => 'application/vnd.api+json'];
    foreach ($headers as $name => $value) {
      $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
    }
    if ($content_type !== '') {
      $server['CONTENT_TYPE'] = $content_type;
    }
    if ($user) {
      $server['PHP_AUTH_USER'] = $user->getAccountName();
      $server['PHP_AUTH_PW'] = 'pw-' . $user->getAccountName();
    }
    $request = Request::create('http://localhost' . $path, 'POST', [], [], $files, $server, $body);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $response = $this->container->get('http_kernel')->handle($request);
    $raw = (string) $response->getContent();
    $this->container->get('request_stack')->pop();
    return ['status' => $response->getStatusCode(), 'body' => json_decode($raw, TRUE), 'raw' => $raw];
  }

  /**
   * An inspection document for a hive.
   */
  protected function inspectionJson(string $hive_uuid, ?string $id = NULL): string {
    $data = [
      'type' => 'hive_inspection--hive_inspection',
      'attributes' => ['inspection_date' => '2026-07-01'],
      'relationships' => ['hive' => ['data' => ['type' => 'hive--hive', 'id' => $hive_uuid]]],
    ];
    if ($id) {
      $data['id'] = $id;
    }
    return json_encode(['data' => $data]);
  }

  /**
   * A record sent as plain JSON is created, the same as the standard type.
   */
  public function testRecordSentAsApplicationJsonIsCreated(): void {
    $uuid = \Drupal::service('uuid')->generate();
    $path = '/hivelog/api/v1/hive_inspection/hive_inspection';

    $response = $this->send($path, 'application/json', $this->inspectionJson($this->fixtures['my_hive']->uuid(), $uuid), $this->me);
    $this->assertSame(201, $response['status'], $response['raw']);
    $this->assertSame($uuid, $response['body']['data']['id']);

    // The server's rules still apply: a retry is a conflict, a foreign hive is refused.
    $retry = $this->send($path, 'application/json; charset=utf-8', $this->inspectionJson($this->fixtures['my_hive']->uuid(), $uuid), $this->me);
    $this->assertSame(409, $retry['status'], $retry['raw']);
    $foreign = $this->send($path, 'application/json', $this->inspectionJson($this->fixtures['their_hive']->uuid()), $this->me);
    $this->assertSame(422, $foreign['status'], $foreign['raw']);
  }

  /**
   * Without the leniency the standard route still refuses application/json.
   */
  public function testPlainJsonStaysRefusedOffTheVersionedApi(): void {
    $response = $this->send('/jsonapi/hive_inspection/hive_inspection', 'application/json', $this->inspectionJson($this->fixtures['my_hive']->uuid()), $this->me);
    // Refused either way (405 while JSON:API is read-only, as it is by default,
    // 415 otherwise): the leniency does not reach plain /jsonapi.
    $this->assertContains($response['status'], [405, 415], $response['raw']);
  }

  /**
   * A request with no Authorization header is not rewritten.
   */
  public function testRequestWithoutCredentialsIsLeftAlone(): void {
    $request = Request::create('http://localhost/hivelog/api/v1/hive_inspection/hive_inspection', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
    $this->assertFalse(HivelogApiWriteCompatibilitySubscriber::applies($request));
    $request->headers->set('Authorization', 'Bearer abc');
    $this->assertTrue(HivelogApiWriteCompatibilitySubscriber::applies($request));
  }

  /**
   * Only POSTs on JSON:API resources are touched, not the plain-JSON routes.
   */
  public function testOnlyJsonApiResourceWritesApply(): void {
    $make = function (string $method, string $path): Request {
      $request = Request::create('http://localhost' . $path, $method);
      $request->headers->set('Authorization', 'Bearer abc');
      return $request;
    };
    $applies = fn(string $method, string $path): bool => HivelogApiWriteCompatibilitySubscriber::applies($make($method, $path));
    $this->assertTrue($applies('POST', '/hivelog/api/v1/hive_inspection/hive_inspection'));
    $this->assertTrue($applies('POST', '/hivelog/api/v1/hive_inspection/hive_inspection/abc/images'));
    $this->assertFalse($applies('GET', '/hivelog/api/v1/hive_inspection/hive_inspection'));
    $this->assertFalse($applies('POST', '/hivelog/api/v1/sign-out'));
    $this->assertFalse($applies('POST', '/hivelog/api/v1/schema'));
    $this->assertFalse($applies('POST', '/hivelog/api/v1/computed/alerts'));
    $this->assertFalse($applies('POST', '/jsonapi/hive_inspection/hive_inspection'));
  }

  /**
   * A photo sent as multipart/form-data is attached like a raw upload.
   */
  public function testMultipartPhotoIsAttachedToTheInspection(): void {
    $inspection = $this->fixtures['my_inspection'];
    $path = '/hivelog/api/v1/hive_inspection/hive_inspection/' . $inspection->uuid() . '/images';
    $upload = $this->uploadedPng('hive photo.png');

    $response = $this->send($path, 'multipart/form-data; boundary=x', NULL, $this->me, ['file' => $upload]);
    $this->assertSame(200, $response['status'], $response['raw']);

    /** @var \Drupal\hivelog\Entity\HiveInspection $reloaded */
    $reloaded = \Drupal::entityTypeManager()->getStorage('hive_inspection')->load($inspection->id());
    $this->assertCount(1, $reloaded->get('images'));
    /** @var \Drupal\file\FileInterface $file */
    $file = $reloaded->get('images')->entity;
    $this->assertSame('hive photo.png', $file->getFilename(), 'The upload\'s file name becomes the stored file name.');
    $this->assertSame(base64_decode(self::PNG), file_get_contents($file->getFileUri()), 'The stored bytes are the uploaded bytes.');
  }

  /**
   * A multipart request with no file, or an empty one, is a clean 400.
   */
  public function testMultipartWithNoFileIsRefused(): void {
    $path = '/hivelog/api/v1/hive_inspection/hive_inspection/' . $this->fixtures['my_inspection']->uuid() . '/images';
    $none = $this->send($path, 'multipart/form-data; boundary=x', NULL, $this->me);
    $this->assertSame(400, $none['status'], $none['raw']);
    $this->assertSame('400', $none['body']['errors'][0]['status']);

    $empty_path = tempnam(sys_get_temp_dir(), 'hl');
    $empty = $this->send($path, 'multipart/form-data; boundary=x', NULL, $this->me, ['file' => new UploadedFile($empty_path, 'a.png', 'image/png', NULL, TRUE)]);
    $this->assertSame(400, $empty['status'], $empty['raw']);
  }

  /**
   * Someone else's inspection still cannot be given a photo.
   */
  public function testMultipartCannotAttachToAnotherBeekeepersRecord(): void {
    $path = '/hivelog/api/v1/hive_inspection/hive_inspection/' . $this->fixtures['their_inspection']->uuid() . '/images';
    $response = $this->send($path, 'multipart/form-data; boundary=x', NULL, $this->me, ['file' => $this->uploadedPng('x.png')]);
    $this->assertContains($response['status'], [403, 404], $response['raw']);
    /** @var \Drupal\hivelog\Entity\HiveInspection $reloaded */
    $reloaded = \Drupal::entityTypeManager()->getStorage('hive_inspection')->load($this->fixtures['their_inspection']->id());
    $this->assertCount(0, $reloaded->get('images'));
  }

  /**
   * An inspection's item path.
   */
  protected function inspectionPath(string $uuid): string {
    return '/hivelog/api/v1/hive_inspection/hive_inspection/' . $uuid;
  }

  /**
   * A PATCH document that sets an inspection's notes.
   */
  protected function notesJson(string $uuid, string $notes): string {
    return json_encode([
      'data' => [
        'type' => 'hive_inspection--hive_inspection',
        'id' => $uuid,
        'attributes' => ['notes' => $notes],
      ],
    ]);
  }

  /**
   * An inspection as stored now, not as the entity cache remembers it.
   */
  protected function storedInspection(int|string $id): ?HiveInspection {
    \Drupal::entityTypeManager()->getStorage('hive_inspection')->resetCache([$id]);
    return HiveInspection::load($id);
  }

  /**
   * The stored notes of an inspection, read fresh.
   */
  protected function storedNotes(int|string $id): ?string {
    return $this->storedInspection($id)?->get('notes')->value;
  }

  /**
   * A beekeeper who may also delete their own inspections, with a side of records.
   *
   * The field-app role cannot delete, on purpose; this stands in for the
   * permission a later phase may grant, to prove the override carries a DELETE
   * all the way to JSON:API and its access checks.
   *
   * @return array{user: \Drupal\user\Entity\User, inspection: \Drupal\Core\Entity\ContentEntityInterface}
   *   The user and their inspection.
   */
  protected function deleter(): array {
    Role::create(['id' => 'inspection_deleter', 'label' => 'Inspection deleter'])
      ->grantPermission('delete own hive inspection')
      ->save();
    $user = $this->beekeeper('deleter');
    $user->addRole('inspection_deleter');
    $user->save();
    $side = $this->buildSide($user, 'deleter');
    return ['user' => $user, 'inspection' => $side['deleter_inspection']];
  }

  /**
   * A POST with the override header is an edit, as a PATCH would be.
   */
  public function testPatchViaOverrideEditsTheRecord(): void {
    $inspection = $this->fixtures['my_inspection'];
    $path = $this->inspectionPath($inspection->uuid());

    // As the standard content type, and as the plain JSON a firewall allows.
    $types = [
      'application/vnd.api+json' => 'Edited A',
      'application/json' => 'Edited B',
      'application/json; charset=utf-8' => 'Edited C',
    ];
    foreach ($types as $type => $notes) {
      $response = $this->send($path, $type, $this->notesJson($inspection->uuid(), $notes), $this->me, [], ['X-HTTP-Method-Override' => 'PATCH']);
      $this->assertSame(200, $response['status'], $type . ': ' . $response['raw']);
      $this->assertSame($notes, $this->storedNotes($inspection->id()));
    }

    // The method name is not case sensitive.
    $lower = $this->send($path, 'application/json', $this->notesJson($inspection->uuid(), 'Edited D'), $this->me, [], ['X-HTTP-Method-Override' => 'patch']);
    $this->assertSame(200, $lower['status'], $lower['raw']);
    $this->assertSame('Edited D', $this->storedNotes($inspection->id()));
  }

  /**
   * An override edit meets the same rules as a real PATCH.
   */
  public function testPatchViaOverrideKeepsAccessAndValidation(): void {
    $theirs = $this->fixtures['their_inspection'];
    $before = $this->storedNotes($theirs->id());
    $foreign = $this->send($this->inspectionPath($theirs->uuid()), 'application/json', $this->notesJson($theirs->uuid(), 'Hijacked'), $this->me, [], ['X-HTTP-Method-Override' => 'PATCH']);
    $this->assertContains($foreign['status'], [403, 404], $foreign['raw']);
    $this->assertSame($before, $this->storedNotes($theirs->id()), 'Another beekeeper\'s record is not changed.');

    // A rule the server enforces is a 422 with the field pointer, as for a real PATCH.
    $mine = $this->fixtures['my_inspection'];
    $bad = json_encode([
      'data' => [
        'type' => 'hive_inspection--hive_inspection',
        'id' => $mine->uuid(),
        'attributes' => ['inspection_date' => 'not a date'],
      ],
    ]);
    $response = $this->send($this->inspectionPath($mine->uuid()), 'application/json', $bad, $this->me, [], ['X-HTTP-Method-Override' => 'PATCH']);
    $this->assertContains($response['status'], [400, 422], $response['raw']);
    $this->assertSame('2026-06-01', $this->storedInspection($mine->id())->get('inspection_date')->value);
  }

  /**
   * A POST with the override header is a delete, and the role's ceiling still holds.
   */
  public function testDeleteViaOverrideObeysTheRole(): void {
    // The field-app role cannot delete: a 403 (a DELETE refused), not a 405 (a POST nobody routes).
    $mine = $this->fixtures['my_inspection'];
    $refused = $this->send($this->inspectionPath($mine->uuid()), '', NULL, $this->me, [], ['X-HTTP-Method-Override' => 'DELETE']);
    $this->assertSame(403, $refused['status'], $refused['raw']);
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('hive_inspection')->load($mine->id()));

    // With the permission it is deleted, with no body and no content type.
    $own = $this->deleter();
    $response = $this->send($this->inspectionPath($own['inspection']->uuid()), '', NULL, $own['user'], [], ['X-HTTP-Method-Override' => 'DELETE']);
    $this->assertSame(204, $response['status'], $response['raw']);
    $this->assertNull(\Drupal::entityTypeManager()->getStorage('hive_inspection')->load($own['inspection']->id()));

    // And never another beekeeper's, even with the permission.
    $theirs = $this->fixtures['their_inspection'];
    $foreign = $this->send($this->inspectionPath($theirs->uuid()), '', NULL, $own['user'], [], ['X-HTTP-Method-Override' => 'DELETE']);
    $this->assertContains($foreign['status'], [403, 404], $foreign['raw']);
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('hive_inspection')->load($theirs->id()));
  }

  /**
   * The override stands for PATCH and DELETE only, never for another method.
   *
   * A safe method (GET, HEAD, OPTIONS, TRACE) is refused by Drupal's own request
   * sanitiser with a 400 before routing; any other name is reset to the plain
   * POST by the subscriber, which no route answers. Either way nothing is edited.
   */
  public function testOverrideCannotStandForAnythingElse(): void {
    $mine = $this->fixtures['my_inspection'];
    $path = $this->inspectionPath($mine->uuid());
    foreach (['GET', 'HEAD', 'OPTIONS', 'TRACE', 'PUT', 'POST', 'NONSENSE', ''] as $method) {
      try {
        $response = $this->send($path, 'application/json', $this->notesJson($mine->uuid(), 'Sneaky'), $this->me, [], ['X-HTTP-Method-Override' => $method]);
        $this->assertContains($response['status'], [400, 404, 405, 415], "Override '$method': " . $response['raw']);
      }
      catch (BadRequestHttpException) {
        // Drupal's sanitiser refused it, which is a 400 on a real site.
      }
      $this->assertNotSame('Sneaky', $this->storedNotes($mine->id()), "Override '$method' did not edit.");
    }
  }

  /**
   * Which requests the override applies to, and what it names.
   */
  public function testOverrideMethodRules(): void {
    $make = function (string $path, ?string $override, bool $credentials = TRUE, string $method = 'POST'): Request {
      $request = Request::create('http://localhost' . $path, $method);
      if ($credentials) {
        $request->headers->set('Authorization', 'Bearer abc');
      }
      if ($override !== NULL) {
        $request->headers->set('X-HTTP-Method-Override', $override);
      }
      return $request;
    };
    $item = '/hivelog/api/v1/hive_inspection/hive_inspection/abc';
    $named = fn(Request $r): ?string => HivelogApiWriteCompatibilitySubscriber::overrideMethod($r);

    $this->assertSame('PATCH', $named($make($item, 'PATCH')));
    $this->assertSame('DELETE', $named($make($item, 'DELETE')));
    $this->assertSame('PATCH', $named($make($item, ' patch ')));
    $this->assertNull($named($make($item, NULL)), 'No header, no override.');
    $this->assertNull($named($make($item, 'PUT')));
    $this->assertNull($named($make($item, 'GET')));
    $this->assertNull($named($make($item, 'DELETE', FALSE)), 'No credentials in a header: no override.');
    $this->assertNull($named($make('/jsonapi/hive_inspection/hive_inspection/abc', 'DELETE')), 'Not off the versioned prefix.');
    $this->assertNull($named($make('/hivelog/api/v1/sign-out', 'DELETE')));
    $this->assertNull($named($make('/hivelog/api/v1/schema', 'PATCH')));
    $this->assertNull($named($make('/hivelog/api/v1/computed/alerts', 'DELETE')));
    $this->assertNull($named($make($item, 'DELETE', TRUE, 'GET')), 'Only a real POST can be an override.');
  }

  /**
   * The discovery document lists what the server accepts.
   */
  public function testDiscoveryListsTheFeatures(): void {
    $response = $this->api('GET', '/hivelog/api/v1');
    $this->assertSame(200, $response['status']);
    $this->assertSame(['json_write', 'multipart_upload', 'method_override'], $response['body']['meta']['hivelog_api']['features']);
    $this->assertSame(HivelogApiResources::FEATURES, $response['body']['meta']['hivelog_api']['features']);
    $this->assertSame(1, $response['body']['meta']['hivelog_api']['api_version'], 'The contract version is unchanged.');
  }

  /**
   * Writes a small PNG to a temporary file as an uploaded file.
   */
  protected function uploadedPng(string $client_name): UploadedFile {
    $path = tempnam(sys_get_temp_dir(), 'hl');
    file_put_contents($path, base64_decode(self::PNG));
    return new UploadedFile($path, $client_name, 'image/png', NULL, TRUE);
  }

}
