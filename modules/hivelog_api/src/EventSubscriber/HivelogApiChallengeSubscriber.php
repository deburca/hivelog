<?php

declare(strict_types=1);

namespace Drupal\hivelog_api\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\hivelog_api\HivelogApiResources;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Answers a request with no credentials on the versioned API with a 401.
 *
 * Drupal answers "no credentials" with a 403, or with an empty 200 collection,
 * and a 401 only for a bad bearer token. A client has to tell "sign in" from
 * "you may not", so everything under the prefix except the public discovery
 * document gets a 401 with a Bearer challenge when nobody is signed in. A
 * missing, expired or invalid token therefore all mean the same thing to the
 * app: refresh or sign in again.
 */
class HivelogApiChallengeSubscriber implements EventSubscriberInterface {

  public function __construct(protected AccountProxyInterface $currentUser) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // After authentication (300) and routing (32), before access checks.
    return [KernelEvents::REQUEST => ['onRequest', 20]];
  }

  /**
   * Answers an anonymous request to a matched route on the prefix with a 401.
   */
  public function onRequest(RequestEvent $event): void {
    // Drupal renders its own 401/403 page as a sub-request that carries the
    // original server variables, so it looks like the versioned path again.
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    if (!HivelogApiResources::isVersionedRequest($request)) {
      return;
    }
    $route = $request->attributes->get('_route');
    if ($route === NULL || $route === 'hivelog_api.discovery') {
      return;
    }
    if ($this->currentUser->isAnonymous()) {
      // Answered here, as JSON:API's error shape, rather than thrown: a thrown
      // 401 is rendered by Drupal as an HTML page.
      $event->setResponse(new JsonResponse(
        [
          'errors' => [[
            'status' => '401',
            'title' => 'Unauthorized',
            'detail' => 'Sign in to use the HiveLog API.',
          ],
          ],
        ],
        401,
        ['WWW-Authenticate' => 'Bearer realm="HiveLog API"', 'Content-Type' => 'application/vnd.api+json']
      ));
    }
  }

}
