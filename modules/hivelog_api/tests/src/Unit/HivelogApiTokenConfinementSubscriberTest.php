<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Unit;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\hivelog_api\EventSubscriber\HivelogApiTokenConfinementSubscriber;
use Drupal\simple_oauth\Authentication\TokenAuthUserInterface;
use Drupal\simple_oauth\Entity\Oauth2TokenInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Route;

/**
 * Tests a field-app token is confined to the versioned API.
 */
#[Group('hivelog')]
class HivelogApiTokenConfinementSubscriberTest extends UnitTestCase {

  /**
   * Builds the event for a request to a path on a JSON:API (or other) route.
   */
  protected function event(string $path, bool $jsonapi_route): RequestEvent {
    $request = Request::create('http://localhost' . $path);
    $route = new Route($path);
    if ($jsonapi_route) {
      $route->setDefault('_is_jsonapi', TRUE);
    }
    $request->attributes->set('_route_object', $route);
    return new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
  }

  /**
   * Builds the subscriber for an account.
   */
  protected function subscriber(AccountInterface $account): HivelogApiTokenConfinementSubscriber {
    $proxy = $this->createMock(AccountProxyInterface::class);
    $proxy->method('getAccount')->willReturn($account);
    return new HivelogApiTokenConfinementSubscriber($proxy);
  }

  /**
   * Builds a token user whose token carries the given scope ids.
   */
  protected function tokenUser(array $scope_ids): TokenAuthUserInterface {
    $field = new class($scope_ids) {

      /**
       * Holds the scope ids the fake token carries.
       */
      public function __construct(protected array $ids) {}

      /**
       * Mimics the scope reference field's raw values.
       */
      public function getValue(): array {
        return array_map(fn($id) => ['scope_id' => $id], $this->ids);
      }

    };
    $token = $this->createMock(Oauth2TokenInterface::class);
    $token->method('get')->with('scopes')->willReturn($field);
    $user = $this->createMock(TokenAuthUserInterface::class);
    $user->method('getToken')->willReturn($token);
    return $user;
  }

  /**
   * Tests the app scope is refused on plain JSON:API but not elsewhere.
   */
  #[DataProvider('cases')]
  public function testConfinement(string $path, bool $jsonapi_route, array $scopes, bool $refused): void {
    $subscriber = $this->subscriber($this->tokenUser($scopes));
    if ($refused) {
      $this->expectException(AccessDeniedHttpException::class);
    }
    $subscriber->onRequest($this->event($path, $jsonapi_route));
    $this->addToAssertionCount(1);
  }

  /**
   * Cases: path, is a JSON:API route, token scopes, whether it is refused.
   */
  public static function cases(): array {
    return [
      'app token on plain JSON:API' => ['/jsonapi/user/user', TRUE, ['hivelog_field_app'], TRUE],
      'app token on the versioned API' => ['/hivelog/api/v1/hive/hive', TRUE, ['hivelog_field_app'], FALSE],
      'app token on a non-JSON:API route' => ['/oauth/userinfo', FALSE, ['hivelog_field_app'], FALSE],
      'another client\'s token on plain JSON:API' => ['/jsonapi/node/page', TRUE, ['some_other_scope'], FALSE],
      'app scope among others' => ['/jsonapi/user/user', TRUE, ['x', 'hivelog_field_app'], TRUE],
    ];
  }

  /**
   * Tests a session user (not a token) is untouched.
   */
  public function testSessionUsersAreUntouched(): void {
    $subscriber = $this->subscriber($this->createMock(AccountInterface::class));
    $subscriber->onRequest($this->event('/jsonapi/user/user', TRUE));
    $this->addToAssertionCount(1);
  }

}
