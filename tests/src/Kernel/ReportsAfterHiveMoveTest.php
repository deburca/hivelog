<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\hivelog\Controller\DashboardController;
use Drupal\hivelog\Controller\InventoryReportController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionItemRequirement;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the apiary reports keep their history after a hive moves (task 0231).
 *
 * A hive's logs and inspections belong to the apiary they were written under.
 * The cost and yield report of an apiary's year, and the dashboard's inspection
 * count, once found them through the hive's apiary, so a moved hive took its
 * history out of the old apiary's figures and into the new one's, though the
 * stock and prices were the old apiary's.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ReportsAfterHiveMoveTest extends KernelTestBase {

  use UserCreationTrait;

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
   * The apiary the hive was in when it was worked on.
   *
   * @var \Drupal\hivelog\Entity\Apiary
   */
  protected Apiary $oldApiary;

  /**
   * The apiary the hive moves to.
   *
   * @var \Drupal\hivelog\Entity\Apiary
   */
  protected Apiary $newApiary;

  /**
   * The hive.
   *
   * @var \Drupal\hivelog\Entity\Hive
   */
  protected Hive $hive;

  /**
   * An administrator, who may read every report.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $admin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach ([
      'user', 'file', 'apiary', 'apiary_membership', 'hive', 'hive_inspection', 'queen',
      'queen_observation', 'calendar_action', 'calendar_action_item_requirement',
      'calendar_action_product_yield', 'hive_action_log', 'apiary_action_log', 'inventory_item',
      'inventory_purchase', 'inventory_usage', 'product', 'harvest_yield', 'hive_component',
    ] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system']);
    // Skip uid 1, which bypasses every access check.
    $this->createUser();
    $this->admin = $this->createUser(['administer hivelog']);
    $this->setCurrentUser($this->admin);

    $this->oldApiary = Apiary::create(['name' => 'Old apiary', 'uid' => $this->admin->id()]);
    $this->oldApiary->save();
    $this->newApiary = Apiary::create(['name' => 'New apiary', 'uid' => $this->admin->id()]);
    $this->newApiary->save();
    $this->hive = Hive::create(['name' => 'H-1', 'apiary' => $this->oldApiary->id(), 'status' => 'active']);
    $this->hive->save();
  }

  /**
   * Reports 5 litres of sugar used on the hive in the old apiary, this year.
   */
  protected function reportSugarUsed(): void {
    $item = InventoryItem::create([
      'apiary' => $this->oldApiary->id(),
      'name' => 'Sugar Syrup',
      'unit' => 'litre',
      'item_type' => 'consumable',
    ]);
    $item->save();
    InventoryPurchase::create([
      'apiary' => $this->oldApiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 10,
      'unit_price' => 2,
    ])->save();
    $action = CalendarAction::create([
      'apiary' => $this->oldApiary->id(),
      'title' => 'Feeding',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $action->save();
    // The reporting form records usage only for items the action requires.
    CalendarActionItemRequirement::create(['calendar_action' => $action->id(), 'item' => $item->id(), 'quantity' => 3])->save();
    $log = HiveActionLog::create(['hive' => $this->hive->id(), 'calendar_action' => $action->id(), 'status' => 'done']);
    $form_object = \Drupal::entityTypeManager()->getFormObject('hive_action_log', 'add');
    $form_object->setEntity($log);
    $form_object->save([], (new FormState())->setValue('inventory_usage_' . $item->id(), 5));
  }

  /**
   * Renders an apiary's cost report for this year.
   */
  protected function costReport(Apiary $apiary): string {
    $request = Request::create('/hivelog/apiary/' . $apiary->id() . '/inventory/cost-report', 'GET', ['year' => (int) date('Y')]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(InventoryReportController::class);
    return (string) \Drupal::service('renderer')->renderInIsolation($controller->costReport($apiary));
  }

  /**
   * Tests the old apiary keeps the cost of what was done there, the new none.
   */
  public function testCostReportStaysWithTheApiaryTheWorkWasDoneUnder(): void {
    $this->reportSugarUsed();
    $this->assertStringContainsString('10.00', $this->costReport($this->oldApiary), 'Before the move.');

    \Drupal::service('hivelog.hive_mover')->move($this->hive, $this->newApiary, []);

    $old = $this->costReport($this->oldApiary);
    $this->assertStringContainsString('Sugar Syrup', $old, 'The old apiary keeps its history.');
    $this->assertStringContainsString('10.00', $old);

    $new = $this->costReport($this->newApiary);
    $this->assertStringNotContainsString('Sugar Syrup', $new, 'The new apiary does not take on the old one\'s cost.');
    $this->assertStringContainsString('No inventory cost, depreciation, or yield recorded', $new);
  }

  /**
   * Tests work done after the move counts for the new apiary.
   */
  public function testWorkAfterTheMoveCountsForTheNewApiary(): void {
    \Drupal::service('hivelog.hive_mover')->move($this->hive, $this->newApiary, []);
    $item = InventoryItem::create([
      'apiary' => $this->newApiary->id(),
      'name' => 'Fondant',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item->save();
    InventoryPurchase::create([
      'apiary' => $this->newApiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 10,
      'unit_price' => 3,
    ])->save();
    $action = CalendarAction::create([
      'apiary' => $this->newApiary->id(),
      'title' => 'Feed',
      'description' => 'D',
      'week_start' => 10,
    ]);
    $action->save();
    CalendarActionItemRequirement::create(['calendar_action' => $action->id(), 'item' => $item->id(), 'quantity' => 1])->save();
    $log = HiveActionLog::create(['hive' => $this->hive->id(), 'calendar_action' => $action->id(), 'status' => 'done']);
    $form_object = \Drupal::entityTypeManager()->getFormObject('hive_action_log', 'add');
    $form_object->setEntity($log);
    $form_object->save([], (new FormState())->setValue('inventory_usage_' . $item->id(), 2));

    $this->assertStringContainsString('Fondant', $this->costReport($this->newApiary));
    $this->assertStringNotContainsString('Fondant', $this->costReport($this->oldApiary));
  }

  /**
   * Tests the dashboard's inspection count stays with the apiary it was made in.
   */
  public function testDashboardInspectionCountFollowsTheStamp(): void {
    HiveInspection::create(['hive' => $this->hive->id(), 'inspection_date' => date('Y-m-d')])->save();

    $count_for = function (Apiary $apiary): int {
      $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(DashboardController::class);
      $method = new \ReflectionMethod($controller, 'inspectionsThisMonth');
      return $method->invoke($controller, [$apiary->id()]);
    };

    $this->assertSame(1, $count_for($this->oldApiary));
    \Drupal::service('hivelog.hive_mover')->move($this->hive, $this->newApiary, []);
    $this->assertSame(1, $count_for($this->oldApiary), 'The old apiary keeps the inspection it recorded.');
    $this->assertSame(0, $count_for($this->newApiary), 'The new apiary does not count it.');
  }

}
