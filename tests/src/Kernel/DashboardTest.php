<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\DashboardController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the dashboard landing page controller shell (task 0056, ADR-0057).
 *
 * Covers criterion 2: the header strip (ISO-week badge + the CBR summary
 * line moved off ApiaryListBuilder), the first-run welcome state, and the
 * render's cache metadata.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class DashboardTest extends KernelTestBase {

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
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_inspection');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * Resolves the dashboard controller from the container.
   */
  private function controller(): DashboardController {
    return \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(DashboardController::class);
  }

  /**
   * Renders a build array to an HTML string.
   */
  private function renderBuild(array $build): string {
    return (string) \Drupal::service('renderer')->renderInIsolation($build);
  }

  /**
   * With no visible apiaries the dashboard shows the first-run welcome card.
   */
  public function testFirstRunWelcomeState(): void {
    $user = User::create(['name' => 'newbie', 'mail' => 'newbie@example.com']);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('Welcome to HiveLog', $html);
    $this->assertStringContainsString('31-entry seasonal calendar', $html);
    $this->assertStringContainsString('/hivelog/apiary/add', $html);
    $this->assertStringNotContainsString('Go to Apiaries', $html);
  }

  /**
   * With at least one visible apiary the interim body replaces the welcome.
   */
  public function testInterimBodyWhenApiariesExist(): void {
    $user = User::create(['name' => 'keeper', 'mail' => 'keeper@example.com']);
    $user->save();
    \Drupal::currentUser()->setAccount($user);
    Apiary::create(['name' => 'Home Apiary', 'uid' => $user->id()])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringNotContainsString('Welcome to HiveLog', $html);
    $this->assertStringContainsString('Go to Apiaries', $html);
  }

  /**
   * The header strip prints the current ISO week and year.
   */
  public function testHeaderShowsCurrentWeek(): void {
    $user = User::create(['name' => 'weeker', 'mail' => 'weeker@example.com']);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('<strong>' . ((int) date('W')) . '</strong>', $html);
    $this->assertStringContainsString((string) ((int) date('Y')), $html);
  }

  /**
   * The CBR summary line renders its three states from the dashboard.
   */
  public function testCbrSummaryStates(): void {
    $with = User::create([
      'name' => 'cbr-with',
      'mail' => 'cbr-with@example.com',
      'cbr_number' => 'IE-4242',
    ]);
    $with->save();
    $without = User::create([
      'name' => 'cbr-without',
      'mail' => 'cbr-without@example.com',
    ]);
    $without->save();

    \Drupal::currentUser()->setAccount($with);
    $this->assertStringContainsString(
      'Your CBR number: IE-4242',
      $this->renderBuild($this->controller()->view())
    );

    \Drupal::currentUser()->setAccount($without);
    $html = $this->renderBuild($this->controller()->view());
    $this->assertStringContainsString('have not set a CBR number yet', $html);
    $this->assertStringContainsString('Update your profile', $html);
  }

  /**
   * The stat-tile SDC renders its value, label, link and sub-line variant.
   */
  public function testStatTileComponentRenders(): void {
    $build = [
      '#type' => 'component',
      '#component' => 'hivelog:stat-tile',
      '#props' => [
        'value' => '7',
        'label' => 'Active hives',
        'url' => '/hivelog/hives',
        'sublabel' => '2 overdue',
        'sublabel_variant' => 'critical',
      ],
    ];

    $html = $this->renderBuild($build);

    $this->assertStringContainsString('class="hivelog-stat-tile"', $html);
    $this->assertStringContainsString('href="/hivelog/hives"', $html);
    $this->assertStringContainsString('Active hives', $html);
    $this->assertStringContainsString('>7<', $html);
    $this->assertStringContainsString('hivelog-stat-tile__sub--critical', $html);
    $this->assertStringContainsString('2 overdue', $html);
  }

  /**
   * The render declares the user cache context, the apiary list cache tag,
   * and a max-age bounded by the ISO week boundary.
   */
  public function testCacheMetadata(): void {
    $user = User::create(['name' => 'cache', 'mail' => 'cache@example.com']);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $build = $this->controller()->view();

    $this->assertContains('user', $build['#cache']['contexts']);
    $this->assertContains('apiary_list', $build['#cache']['tags']);
    $this->assertGreaterThan(0, $build['#cache']['max-age']);
    $this->assertLessThanOrEqual(7 * 24 * 3600, $build['#cache']['max-age']);
  }

}
