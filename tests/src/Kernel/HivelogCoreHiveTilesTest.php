<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveComponent;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\Queen;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests core's own Hive page stat tiles (task 0257).
 *
 * `HivelogStatTileBuilder::coreTilesForHive()`: status, type, temperament, the
 * active queen and the empty weight.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogCoreHiveTilesTest extends KernelTestBase {

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
   * The apiary.
   */
  protected Apiary $apiary;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $types = [
      'user', 'file', 'apiary', 'apiary_membership', 'hive', 'queen',
      'inventory_item', 'inventory_purchase', 'hive_component',
    ];
    foreach ($types as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);
    // The first user (uid 1) is the superuser, so entity and route access pass.
    User::create(['name' => 'root', 'mail' => 'root@example.com'])->save();
    \Drupal::currentUser()->setAccount(User::load(1));

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();
  }

  /**
   * Builds a hive and returns its core tile descriptors.
   */
  protected function tiles(array $values = []): array {
    $hive = Hive::create($values + ['name' => 'H', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $hive->save();
    $this->hive = $hive;
    return \Drupal::service('hivelog.stat_tile_builder')->coreTilesForHive($hive);
  }

  /**
   * The hive built by tiles().
   */
  protected Hive $hive;

  /**
   * Adds a durable item (optionally weighing $kg) and puts one on the hive.
   */
  protected function addComponent(?float $kg): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Part ' . $this->randomMachineName(4),
      'category' => 'equipment',
      'unit' => 'each',
      'item_type' => 'durable',
      'useful_life_years' => 10,
    ] + ($kg !== NULL ? ['weight_kg' => $kg] : []));
    $item->save();
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 2,
      'unit_price' => 10,
    ])->save();
    HiveComponent::create(['hive' => $this->hive->id(), 'item' => $item->id(), 'quantity' => 2])->save();
  }

  /**
   * Tests status, type and temperament; unset ones are left out.
   */
  public function testFactTiles(): void {
    $tiles = $this->tiles(['hive_type' => 'langstroth', 'hive_material' => 'wood', 'temperament' => 'calm']);
    $this->assertSame('Active', $tiles['hivelog_status']['value']);
    $this->assertSame('Langstroth · Wood', $tiles['hivelog_construction']['value']);
    $this->assertSame('Calm', $tiles['hivelog_temperament']['value']);

    $bare = $this->tiles();
    $this->assertArrayNotHasKey('hivelog_construction', $bare);
    $this->assertArrayNotHasKey('hivelog_temperament', $bare);
  }

  /**
   * Tests every core tile sorts before a submodule tile (weight 0 and up).
   */
  public function testCoreTilesComeFirst(): void {
    $weights = array_column($this->tiles(['hive_type' => 'langstroth', 'temperament' => 'calm']), 'weight');
    $this->assertNotEmpty($weights);
    $this->assertLessThan(0, max($weights));
  }

  /**
   * Tests a hive with no queen invites adding one.
   */
  public function testNoQueenTile(): void {
    $tile = $this->tiles()['hivelog_queen'];
    $this->assertSame('No queen', $tile['value']);
    $this->assertSame('warning', $tile['sublabel_variant']);
    $this->assertStringContainsString('/queen/add', $tile['url']->toString());
  }

  /**
   * Tests the active queen's tile: year and colour, name and breed, link to the queen.
   */
  public function testActiveQueenTile(): void {
    $hive = Hive::create(['name' => 'H', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $hive->save();
    $queen = Queen::create([
      'name' => 'Q',
      'hive' => $hive->id(),
      'queen_year' => 2025,
      'breed' => 'buckfast',
      'status' => 'active',
    ]);
    $queen->save();

    $tile = \Drupal::service('hivelog.stat_tile_builder')->coreTilesForHive($hive)['hivelog_queen'];
    $this->assertSame('2025 · Blue', $tile['value']);
    $this->assertSame('Q · Buckfast', $tile['sublabel']);
    $this->assertSame($queen->toUrl()->toString(), $tile['url']->toString());
  }

  /**
   * Tests the three empty-weight states.
   */
  public function testEmptyWeightTile(): void {
    $tile = $this->tiles()['hivelog_empty_weight'];
    $this->assertSame('Unknown', $tile['value']);
    $this->assertEquals('Not yet composed', (string) $tile['sublabel']);
    $this->assertSame('warning', $tile['sublabel_variant']);
    $this->assertStringContainsString('#components', $tile['url']->toString());

    $this->addComponent(NULL);
    $tile = \Drupal::service('hivelog.stat_tile_builder')->coreTilesForHive($this->hive)['hivelog_empty_weight'];
    $this->assertSame('Unknown', $tile['value']);
    $this->assertEquals('Incomplete', (string) $tile['sublabel']);

    $complete = $this->tiles();
    $this->addComponent(4.25);
    $tile = \Drupal::service('hivelog.stat_tile_builder')->coreTilesForHive($this->hive)['hivelog_empty_weight'];
    $this->assertSame('8.5 kg', $tile['value']);
    $this->assertArrayNotHasKey('sublabel', $tile);
    $this->assertNotEmpty($complete);
  }

  /**
   * Tests the page row is core's tiles; the API's list is not.
   */
  public function testPageRowHasCoreTilesButApiListDoesNot(): void {
    $this->tiles();
    $builder = \Drupal::service('hivelog.stat_tile_builder');
    $this->assertSame([], $builder->tilesForHive($this->hive));
    $row = $builder->buildForHive($this->hive);
    $this->assertArrayHasKey('hivelog_status', $row);
    $this->assertArrayHasKey('hivelog_queen', $row);
    $this->assertArrayHasKey('hivelog_empty_weight', $row);
  }

}
