<?php

declare(strict_types=1);

namespace Drupal\hivelog_api\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\hivelog_api\HivelogApiResources;
use Drupal\jsonapi\Routing\Routes;
use Drupal\simple_oauth\Authentication\TokenAuthUserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Confines a field-app token to the versioned API.
 *
 * Core JSON:API also serves every other resource type the site exposes under
 * `/jsonapi`, and some of those are readable by any signed-in user (the user
 * collection lists every display name). The field app's token needs none of
 * that, so a token carrying the app's scope is refused on any JSON:API route
 * outside `/hivelog/api/v1`, whose allow-list is the intended surface. Tokens
 * with other scopes, and sessions, are untouched.
 */
class HivelogApiTokenConfinementSubscriber implements EventSubscriberInterface {

  public function __construct(protected AccountProxyInterface $currentUser) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // After authentication (300) and routing (32), before access checks.
    return [KernelEvents::REQUEST => ['onRequest', 20]];
  }

  /**
   * Refuses a field-app token on a JSON:API route off the versioned prefix.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    if (!$this->isJsonApiRoute($request) || HivelogApiResources::isVersionedRequest($request)) {
      return;
    }
    $account = $this->currentUser->getAccount();
    if (!$account instanceof TokenAuthUserInterface) {
      return;
    }
    $scopes = array_column($account->getToken()->get('scopes')->getValue(), 'scope_id');
    if (in_array(HivelogApiResources::SCOPE, $scopes, TRUE)) {
      throw new AccessDeniedHttpException(sprintf('This token can only be used with the HiveLog API at %s.', HivelogApiResources::PATH_PREFIX));
    }
  }

  /**
   * Whether the matched route is a JSON:API route.
   */
  protected function isJsonApiRoute(Request $request): bool {
    $route = $request->attributes->get('_route_object');
    return $route && $route->hasDefault(Routes::JSON_API_ROUTE_FLAG_KEY);
  }

}
