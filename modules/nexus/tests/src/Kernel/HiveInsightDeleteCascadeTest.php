<?php

declare(strict_types=1);

namespace Drupal\Tests\nexus\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\KernelTests\KernelTestBase;
use Drupal\nexus\Entity\HiveInsight;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ADR-0103 rows #8/#13 (apiary/hive -> hive_insight, CASCADE) — task 0142.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveInsightDeleteCascadeTest extends KernelTestBase {

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
    'key',
    'hivelog',
    'collective',
    'nexus',
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
    $this->installEntitySchema('hive_insight');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * An apiary-scoped hive insight.
   */
  protected function createApiaryInsight(Apiary $apiary): HiveInsight {
    $insight = HiveInsight::create([
      'apiary' => $apiary->id(),
      'scope' => 'apiary',
      'verdict' => 'all_clear',
      'recommendation' => 'Apiary looks healthy overall',
      'signals' => '- All monitored hives reporting normally',
      'generated' => \Drupal::time()->getRequestTime(),
    ]);
    $insight->save();
    return $insight;
  }

  /**
   * Deleting an apiary cascades its own apiary-scoped hive insights.
   */
  public function testApiaryDeleteCascadesHiveInsights(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $insight = $this->createApiaryInsight($apiary);

    $apiary->delete();

    $this->assertNull(HiveInsight::load($insight->id()));
  }

  /**
   * Deleting a hive cascades its own hive-scoped hive insights.
   */
  public function testHiveDeleteCascadesHiveInsights(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    $insight = HiveInsight::create([
      'apiary' => $apiary->id(),
      'hive' => $hive->id(),
      'scope' => 'hive',
      'verdict' => 'all_clear',
      'recommendation' => 'Hive looks healthy',
      'signals' => '- Weight stable',
      'generated' => \Drupal::time()->getRequestTime(),
    ]);
    $insight->save();

    $hive->delete();

    $this->assertNull(HiveInsight::load($insight->id()));
  }

  /**
   * Deleting one apiary leaves another apiary's hive insight alone.
   */
  public function testCascadeLeavesUnrelatedInsightsUntouched(): void {
    $apiary_a = Apiary::create(['name' => 'Apiary A']);
    $apiary_a->save();
    $apiary_b = Apiary::create(['name' => 'Apiary B']);
    $apiary_b->save();
    $insight_a = $this->createApiaryInsight($apiary_a);
    $insight_b = $this->createApiaryInsight($apiary_b);

    $apiary_a->delete();

    $this->assertNull(HiveInsight::load($insight_a->id()));
    $this->assertNotNull(HiveInsight::load($insight_b->id()), "Another apiary's hive insight must survive.");
  }

}
