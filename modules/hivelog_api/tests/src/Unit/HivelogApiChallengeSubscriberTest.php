<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Unit;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\hivelog_api\EventSubscriber\HivelogApiChallengeSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests no credentials on the versioned API is a 401 with a Bearer challenge.
 */
#[Group('hivelog')]
class HivelogApiChallengeSubscriberTest extends UnitTestCase {

  /**
   * Runs the subscriber for a request.
   */
  protected function dispatch(string $path, ?string $route, bool $anonymous, bool $main = TRUE): RequestEvent {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('isAnonymous')->willReturn($anonymous);
    $request = Request::create('http://localhost' . $path);
    if ($route !== NULL) {
      $request->attributes->set('_route', $route);
    }
    $event = new RequestEvent(
      $this->createMock(HttpKernelInterface::class),
      $request,
      $main ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST
    );
    (new HivelogApiChallengeSubscriber($account))->onRequest($event);
    return $event;
  }

  /**
   * Tests when an anonymous request is challenged.
   */
  #[DataProvider('cases')]
  public function testChallenge(string $path, ?string $route, bool $anonymous, bool $challenged): void {
    $event = $this->dispatch($path, $route, $anonymous);
    $this->assertSame($challenged, $event->hasResponse());
    if ($challenged) {
      $this->assertSame(401, $event->getResponse()->getStatusCode());
    }
  }

  /**
   * Cases: path, matched route, anonymous, whether a 401 is thrown.
   */
  public static function cases(): array {
    return [
      'anonymous on a resource' => ['/hivelog/api/v1/hive/hive', 'jsonapi.hive--hive.collection', TRUE, TRUE],
      'anonymous on a computed view' => ['/hivelog/api/v1/computed/alerts', 'hivelog_api.computed_alerts', TRUE, TRUE],
      'anonymous on the public discovery document' => ['/hivelog/api/v1', 'hivelog_api.discovery', TRUE, FALSE],
      'signed in on a resource' => ['/hivelog/api/v1/hive/hive', 'jsonapi.hive--hive.collection', FALSE, FALSE],
      'anonymous outside the prefix' => ['/jsonapi/hive/hive', 'jsonapi.hive--hive.collection', TRUE, FALSE],
      'anonymous on an unmatched path' => ['/hivelog/api/v1/nothing/nothing', NULL, TRUE, FALSE],
    ];
  }

  /**
   * Tests the challenge names the Bearer scheme and is JSON:API shaped.
   */
  public function testChallengeNamesBearerAndIsJson(): void {
    $event = $this->dispatch('/hivelog/api/v1/hive/hive', 'jsonapi.hive--hive.collection', TRUE);
    $response = $event->getResponse();
    $this->assertStringStartsWith('Bearer', $response->headers->get('WWW-Authenticate'));
    $this->assertSame('application/vnd.api+json', $response->headers->get('Content-Type'));
    $this->assertSame('401', json_decode($response->getContent(), TRUE)['errors'][0]['status']);
  }

  /**
   * Tests a sub-request (Drupal's own 401 page) is never challenged again.
   *
   * It carries the original path, so without this the 401 page would be
   * challenged too, forever.
   */
  public function testSubRequestsAreLeftAlone(): void {
    $event = $this->dispatch('/hivelog/api/v1/hive/hive', 'jsonapi.hive--hive.collection', TRUE, FALSE);
    $this->assertFalse($event->hasResponse());
  }

}
