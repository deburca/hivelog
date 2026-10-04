<?php

declare(strict_types=1);

namespace Drupal\hivelog_api\Controller;

use Defuse\Crypto\Crypto;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\hivelog_api\HivelogApiResources;
use Drupal\simple_oauth\Authentication\TokenAuthUserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs the app out by revoking the tokens it holds (task 0204).
 *
 * The simple_oauth module has no revocation endpoint, so without this "sign
 * out" in the app would only forget the tokens on the device, leaving them
 * valid on the server (a lost phone's refresh token would keep working for
 * two weeks).
 *
 * The bearer token that makes the request is revoked. If the body also carries
 * the app's refresh token (`{"refresh_token": "..."}`), that refresh token and
 * the access token it was issued with are revoked too, so only this device's
 * session ends and a second device stays signed in. The refresh token is the
 * opaque blob the client holds: it is decrypted exactly as the OAuth server
 * does, and honoured only if it belongs to the same user and client as the
 * bearer token, so nobody can revoke someone else's.
 */
class SignOutController extends ControllerBase {

  public function __construct(
    protected object $accessTokens,
    protected object $refreshTokens,
    protected AccountProxyInterface $accountProxy,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('simple_oauth.repositories.access_token'),
      $container->get('simple_oauth.repositories.refresh_token'),
      $container->get('current_user'),
    );
  }

  /**
   * Revokes the caller's tokens.
   */
  public function signOut(Request $request): Response {
    $account = $this->accountProxy->getAccount();
    if (!$account instanceof TokenAuthUserInterface) {
      $error = [
        'status' => '400',
        'title' => 'Bad Request',
        'detail' => 'Sign out needs the app\'s bearer token.',
      ];
      return new JsonResponse(['errors' => [$error]], 400, ['Content-Type' => 'application/vnd.api+json']);
    }

    $token = $account->getToken();
    $this->accessTokens->revokeAccessToken($token->get('value')->value);

    $body = json_decode($request->getContent() ?: '[]', TRUE);
    $refresh = is_array($body) ? ($body['refresh_token'] ?? NULL) : NULL;
    if (is_string($refresh) && $refresh !== '') {
      $payload = $this->decryptRefreshToken($refresh);
      if ($payload
        && (string) ($payload['user_id'] ?? '') === (string) $account->getSubject()->id()
        && ($payload['client_id'] ?? NULL) === $account->getConsumer()->get('client_id')->value) {
        $this->refreshTokens->revokeRefreshToken($payload['refresh_token_id']);
        $this->accessTokens->revokeAccessToken($payload['access_token_id']);
      }
    }

    return new Response('', 204);
  }

  /**
   * Decrypts a refresh token the way the OAuth server does.
   *
   * @return array|null
   *   The payload, or NULL if it is not a refresh token this server issued.
   */
  protected function decryptRefreshToken(string $refresh): ?array {
    try {
      $json = Crypto::decryptWithPassword($refresh, substr(Settings::getHashSalt(), 0, 32));
    }
    catch (\Throwable) {
      return NULL;
    }
    $payload = json_decode($json, TRUE);
    foreach (['refresh_token_id', 'access_token_id', 'user_id', 'client_id'] as $key) {
      if (!is_array($payload) || !isset($payload[$key])) {
        return NULL;
      }
    }
    return $payload;
  }

}
