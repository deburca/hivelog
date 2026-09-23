<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests `HivelogStatTileBuilder` — the Hive/Apiary page stat-tile row (task 0110).
 *
 * Mirrors `HivelogAppNavBuilderTest`'s own shape — hivelog core
 * contributes no tiles of its own here, so the merging/sorting tests
 * call the `protected` `build()` method directly via reflection with
 * hand-built descriptors, rather than needing a real submodule
 * implementation (nexus/nanoprobe's own hooks are covered by their own
 * test suites).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogStatTileBuilderTest extends KernelTestBase {

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
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * Tests the row is empty when no implementation contributes a tile.
   */
  public function testEmptyWhenNoTilesContributed(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();

    $builder = \Drupal::service('hivelog.stat_tile_builder');
    $this->assertSame([], $builder->buildForHive($hive));
    $this->assertSame([], $builder->buildForApiary($apiary));
  }

  /**
   * Tests the hooks are actually dispatched by the module handler.
   *
   * With no submodule implementing them in this test's $modules list, an
   * empty array is the correct, expected result — this only confirms
   * the invocation itself doesn't error, matching
   * `HivelogAppNavBuilderTest::testHookIsDispatchedByModuleHandler()`'s
   * own reasoning.
   */
  public function testHooksAreDispatchedByModuleHandler(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();

    $hive_tiles = \Drupal::moduleHandler()->invokeAll('hivelog_hive_stat_tiles', [$hive]);
    $apiary_tiles = \Drupal::moduleHandler()->invokeAll('hivelog_apiary_stat_tiles', [$apiary]);
    $this->assertIsArray($hive_tiles);
    $this->assertIsArray($apiary_tiles);
  }

  /**
   * Tests a single contributed tile builds the right component props.
   *
   * Exercises `HivelogStatTileBuilder::build()` directly (its
   * `protected` visibility means this reaches it the same way
   * `buildForHive()`/`buildForApiary()` do) rather than needing a real
   * hook implementation, since hivelog core itself contributes none.
   */
  public function testSingleTileBuildsExpectedComponentProps(): void {
    $builder = \Drupal::service('hivelog.stat_tile_builder');
    $method = new \ReflectionMethod($builder, 'build');
    $build = $method->invoke($builder, [
      'my_module_thing' => [
        'value' => '3',
        'label' => t('My Things'),
        'url' => Url::fromRoute('<front>'),
        'sublabel' => t('2 need attention'),
        'sublabel_variant' => 'warning',
        'weight' => 0,
      ],
    ]);

    $this->assertEquals('hivelog-stat-tiles', $build['#attributes']['class'][0]);
    $this->assertArrayHasKey('my_module_thing', $build);
    $tile = $build['my_module_thing'];
    $this->assertEquals('hivelog:stat-tile', $tile['#component']);
    $this->assertEquals('3', $tile['#props']['value']);
    $this->assertEquals('My Things', $tile['#props']['label']);
    $this->assertEquals('2 need attention', $tile['#props']['sublabel']);
    $this->assertEquals('warning', $tile['#props']['sublabel_variant']);
  }

  /**
   * Tests tiles are sorted by weight, and a missing weight defaults to 0.
   */
  public function testTilesAreSortedByWeight(): void {
    $url = Url::fromRoute('<front>');
    $builder = \Drupal::service('hivelog.stat_tile_builder');
    $method = new \ReflectionMethod($builder, 'build');
    $build = $method->invoke($builder, [
      'weight_five' => ['value' => '1', 'label' => t('Five'), 'url' => $url, 'weight' => 5],
      'weight_neg_five' => ['value' => '2', 'label' => t('Neg five'), 'url' => $url, 'weight' => -5],
      'weight_unset' => ['value' => '3', 'label' => t('Unset'), 'url' => $url],
    ]);

    $keys = array_keys(array_filter($build, fn($k) => is_string($k) && !str_starts_with((string) $k, '#'), ARRAY_FILTER_USE_KEY));
    $this->assertSame(['weight_neg_five', 'weight_unset', 'weight_five'], $keys);
  }

}
