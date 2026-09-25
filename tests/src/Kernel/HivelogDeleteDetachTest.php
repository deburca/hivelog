<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\Queen;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests hivelog core's DETACH rows (task 0143).
 *
 * Row #12 (submodule) is covered by nanoprobe's own kernel test instead —
 * this class covers only the rows `HivelogDeleteDependencyRegistry`
 * declares itself: #11 (Hive → Queen) and #21 (HiveInspection →
 * HiveActionLog).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogDeleteDetachTest extends KernelTestBase {

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
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('hive_action_log');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * A fresh apiary + hive pair.
   *
   * @return array{0: \Drupal\hivelog\Entity\Apiary, 1: \Drupal\hivelog\Entity\Hive}
   *   The apiary and its hive.
   */
  protected function createApiaryAndHive(): array {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    return [$apiary, $hive];
  }

  // -------------------------------------------------------------------------
  // Row #11: Hive -> Queen.
  // -------------------------------------------------------------------------

  /**
   * Deleting a hive detaches its active queen: hive cleared, set inactive.
   */
  public function testHiveDeleteDetachesActiveQueen(): void {
    [, $hive] = $this->createApiaryAndHive();
    $queen = Queen::create([
      'name' => 'Q-2026-001',
      'hive' => $hive->id(),
      'status' => 'active',
      'origin' => 'Bred',
    ]);
    $queen->save();

    $hive->delete();

    $reloaded = Queen::load($queen->id());
    $this->assertNotNull($reloaded, 'The queen must survive the hive delete.');
    $this->assertTrue($reloaded->get('hive')->isEmpty(), "The queen's hive reference must be cleared.");
    $this->assertEquals('inactive', $reloaded->get('status')->value);
    // No other field touched.
    $this->assertEquals('Q-2026-001', $reloaded->get('name')->value);
    $this->assertEquals('Bred', $reloaded->get('origin')->value);
  }

  /**
   * Deleting a hive detaches every queen linked to it, active or retired.
   *
   * `Hive::getQueens()` (AGENTS.md) resolves every queen the hive has
   * ever had, not just the active one — a retired queen still
   * references `hive` and must be detached too.
   */
  public function testHiveDeleteDetachesRetiredQueenToo(): void {
    [, $hive] = $this->createApiaryAndHive();
    $retired = Queen::create([
      'name' => 'Q-2025-001',
      'hive' => $hive->id(),
      'status' => 'inactive',
    ]);
    $retired->save();
    $active = Queen::create([
      'name' => 'Q-2026-001',
      'hive' => $hive->id(),
      'status' => 'active',
    ]);
    $active->save();

    $hive->delete();

    foreach ([$retired, $active] as $queen) {
      $reloaded = Queen::load($queen->id());
      $this->assertNotNull($reloaded);
      $this->assertTrue($reloaded->get('hive')->isEmpty());
      $this->assertEquals('inactive', $reloaded->get('status')->value);
    }
  }

  /**
   * Deleting one hive leaves another hive's queen alone.
   */
  public function testHiveDeleteLeavesOtherHivesQueenAlone(): void {
    [$apiary, $hive_a] = $this->createApiaryAndHive();
    $hive_b = Hive::create(['name' => 'Other Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive_b->save();
    $queen_b = Queen::create(['name' => 'Q-Other', 'hive' => $hive_b->id(), 'status' => 'active']);
    $queen_b->save();

    $hive_a->delete();

    $reloaded = Queen::load($queen_b->id());
    $this->assertNotNull($reloaded);
    $this->assertEquals($hive_b->id(), $reloaded->get('hive')->target_id, "Another hive's queen must be untouched.");
    $this->assertEquals('active', $reloaded->get('status')->value);
  }

  // -------------------------------------------------------------------------
  // Row #21: HiveInspection -> HiveActionLog.
  // -------------------------------------------------------------------------

  /**
   * Deleting an inspection detaches the action log that references it.
   */
  public function testHiveInspectionDeleteDetachesLinkedActionLog(): void {
    [$apiary, $hive] = $this->createApiaryAndHive();
    $calendar_action = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Test Calendar Action',
      'description' => 'Desc.',
      'week_start' => 15,
    ]);
    $calendar_action->save();
    $inspection = HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2026-06-15',
    ]);
    $inspection->save();
    $log = HiveActionLog::create([
      'hive' => $hive->id(),
      'calendar_action' => $calendar_action->id(),
      'inspection' => $inspection->id(),
    ]);
    $log->save();

    $inspection->delete();

    $reloaded = HiveActionLog::load($log->id());
    $this->assertNotNull($reloaded, 'The action log must survive the inspection delete.');
    $this->assertTrue($reloaded->get('inspection')->isEmpty(), "The log's inspection reference must be cleared.");
    // No other field touched — it keeps its own record of the action.
    $this->assertEquals($hive->id(), $reloaded->get('hive')->target_id);
    $this->assertEquals($calendar_action->id(), $reloaded->get('calendar_action')->target_id);
  }

}
