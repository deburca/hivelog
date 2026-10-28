<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\hivelog\Controller\ErrorPageController;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the friendly page-not-found and access-denied pages (task 0238).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ErrorPageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'datetime',
    'options',
    'file',
    'image',
    'geofield',
    'sdc',
    'hivelog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('apiary_membership');
    $this->installSchema('file', ['file_usage']);
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Tests the page renders the scene, the sign and a link to the home page.
   */
  public function testNotFoundPageRenders(): void {
    $controller = ErrorPageController::create(\Drupal::getContainer());
    $build = $controller->notFound();
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('<svg class="hivelog-error-scene"', $html);
    $this->assertStringContainsString('aria-hidden="true"', $html);
    $this->assertStringContainsString('>Vacant</text>', $html);
    $this->assertStringContainsString('These bees may have fled.', $html);
    $this->assertMatchesRegularExpression('#<a href="/"[^>]*>HiveLog home page</a>#', $html);
    $this->assertSame('Oops! This hive is empty.', (string) $controller->notFoundTitle());
  }

  /**
   * Tests anyone, anonymous included, may open the page.
   */
  public function testRouteIsOpenToAnonymous(): void {
    $this->assertTrue(\Drupal::service('access_manager')->checkNamedRoute(
      'hivelog.page_not_found',
      [],
      \Drupal::service('current_user'),
    ));
  }

  /**
   * Tests a signed-out visitor is offered a sign-in link as well as the way home.
   */
  public function testAccessDeniedOffersSignInToAnonymous(): void {
    $controller = ErrorPageController::create(\Drupal::getContainer());
    $html = (string) \Drupal::service('renderer')->renderInIsolation($controller->accessDenied());

    $this->assertStringContainsString('>Residents only</text>', $html);
    $this->assertStringContainsString('Only authorised bees are allowed in.', $html);
    $this->assertMatchesRegularExpression('#<a href="/user/login[^"]*"[^>]*>Sign in</a>#', $html);
    $this->assertMatchesRegularExpression('#<a href="/"[^>]*>HiveLog home page</a>#', $html);
    $this->assertSame("Hold on, this hive isn't yours.", (string) $controller->accessDeniedTitle());
  }

  /**
   * Tests a signed-in visitor is offered only the way home.
   */
  public function testAccessDeniedOffersOnlyHomeToSignedInUser(): void {
    $user = User::create(['name' => 'forager', 'status' => 1]);
    $user->save();
    $this->container->get('current_user')->setAccount($user);

    $controller = ErrorPageController::create(\Drupal::getContainer());
    $html = (string) \Drupal::service('renderer')->renderInIsolation($controller->accessDenied());

    $this->assertStringContainsString('Only authorised bees are allowed in.', $html);
    $this->assertStringNotContainsString('Sign in', $html);
    $this->assertMatchesRegularExpression('#<a href="/"[^>]*>HiveLog home page</a>#', $html);
  }

  /**
   * Tests both error routes are open to anonymous visitors.
   */
  public function testAccessDeniedRouteIsOpenToAnonymous(): void {
    $this->assertTrue(\Drupal::service('access_manager')->checkNamedRoute(
      'hivelog.access_denied',
      [],
      \Drupal::service('current_user'),
    ));
  }

}
