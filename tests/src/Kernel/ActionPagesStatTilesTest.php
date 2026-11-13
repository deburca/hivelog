<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\hivelog\Controller\ApiaryActionLogController;
use Drupal\hivelog\Controller\CalendarActionController;
use Drupal\hivelog\Controller\HiveActionLogController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the calendar action and action log pages' stat-tile strips (0262).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ActionPagesStatTilesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'datetime', 'options', 'file', 'image', 'geofield', 'hivelog',
  ];

  /**
   * The apiary.
   */
  protected Apiary $apiary;

  /**
   * The hive.
   */
  protected Hive $hive;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    foreach ([
      'user', 'file', 'apiary', 'apiary_membership', 'hive', 'hive_inspection',
      'calendar_action', 'calendar_action_item_requirement', 'calendar_action_product_yield',
      'inventory_item', 'inventory_purchase', 'inventory_usage', 'product', 'harvest_yield',
      'hive_action_log', 'apiary_action_log',
    ] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);
    User::create(['name' => 'root', 'mail' => 'root@example.com'])->save();
    \Drupal::currentUser()->setAccount(User::load(1));

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();
    $this->hive = Hive::create(['name' => 'H', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $this->hive->save();
  }

  /**
   * Creates a calendar action.
   */
  protected function action(array $values = []): CalendarAction {
    $action = CalendarAction::create($values + [
      'apiary' => $this->apiary->id(),
      'title' => 'Varroa treatment',
      'category' => 'other',
      'week_start' => 14,
      'week_end' => 18,
      'recurring' => TRUE,
      'enabled' => TRUE,
    ]);
    $action->save();
    return $action;
  }

  /**
   * Returns a controller instance.
   */
  protected function controller(string $class): object {
    return \Drupal::service('class_resolver')->getInstanceFromDefinition($class);
  }

  /**
   * Tests the calendar action tiles: window, recurring, enabled.
   */
  public function testCalendarActionTiles(): void {
    $build = $this->controller(CalendarActionController::class)->view($this->action());
    $this->assertArrayHasKey('facts', $build);
    $this->assertArrayNotHasKey('overview', $build);
    $this->assertArrayNotHasKey('schedule', $build);
    $window = $build['facts']['window']['#props'];
    $this->assertSame('W14–W18', $window['value']);
    $this->assertSame('Recurring', $window['sublabel']);
    $status = $build['facts']['enabled']['#props'];
    $this->assertSame('Enabled', $status['value']);
    $this->assertSame('default', $status['sublabel_variant']);
  }

  /**
   * Tests the description is a note block with its bullets, and absent when empty.
   */
  public function testCalendarActionDescriptionIsANoteBlock(): void {
    $action = $this->action(['description' => "Harvest supers.\n- Check capping\n- Leave stores"]);
    $build = $this->controller(CalendarActionController::class)->view($action);
    $this->assertArrayHasKey('description', $build);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build['description']);
    $this->assertStringContainsString('hivelog-prose', $html);
    $this->assertStringContainsString('<li>Check capping</li>', $html);

    $bare = $this->controller(CalendarActionController::class)->view($this->action(['description' => '', 'title' => 'Bare']));
    $this->assertArrayNotHasKey('description', $bare);
  }

  /**
   * Tests a disabled, one-off, single-week action.
   */
  public function testDisabledCalendarAction(): void {
    $action = $this->action(['enabled' => FALSE, 'recurring' => FALSE, 'week_end' => 14]);
    $build = $this->controller(CalendarActionController::class)->view($action);
    $this->assertSame('W14', $build['facts']['window']['#props']['value']);
    $this->assertSame('', $build['facts']['window']['#props']['sublabel']);
    $this->assertSame('Disabled', $build['facts']['enabled']['#props']['value']);
    $this->assertSame('warning', $build['facts']['enabled']['#props']['sublabel_variant']);
  }

  /**
   * Tests the hive log tiles, including the status tints.
   */
  public function testHiveActionLogTiles(): void {
    $action = $this->action();
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $action->id(),
      'year' => 2026,
      'week_completed' => 15,
      'status' => 'done',
      'notes' => 'All good',
    ]);
    $log->save();
    $build = $this->controller(HiveActionLogController::class)->view($log);
    $this->assertArrayNotHasKey('overview', $build);
    $this->assertSame('Done', $build['facts']['status']['#props']['value']);
    $this->assertSame('ok', $build['facts']['status']['#props']['sublabel_variant']);
    $this->assertSame('2026 · W15', $build['facts']['when']['#props']['value']);
    $this->assertSame('Action', $build['facts']['calendar_action']['#props']['label']);
    $this->assertArrayNotHasKey('inspection', $build['facts']);
    $this->assertArrayHasKey('details', $build);

    $log->set('status', 'pending')->save();
    $build = $this->controller(HiveActionLogController::class)->view($log);
    $this->assertSame('warning', $build['facts']['status']['#props']['sublabel_variant']);

    $log->set('status', 'ignored')->save();
    $build = $this->controller(HiveActionLogController::class)->view($log);
    $this->assertSame('default', $build['facts']['status']['#props']['sublabel_variant']);
  }

  /**
   * Tests the apiary log has no inspection tile.
   */
  public function testApiaryActionLogTiles(): void {
    $action = $this->action(['scope' => 'apiary']);
    $log = ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $action->id(),
      'year' => 2026,
      'status' => 'done',
    ]);
    $log->save();
    $build = $this->controller(ApiaryActionLogController::class)->view($log);
    $this->assertSame('Done', $build['facts']['status']['#props']['value']);
    $this->assertSame('2026', $build['facts']['when']['#props']['value']);
    $this->assertArrayNotHasKey('inspection', $build['facts']);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('Varroa treatment', $html);
  }

}
