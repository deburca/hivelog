<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\HivelogCalendarCopier;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests choosing and copying an apiary's seasonal calendar (task 0249).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class CalendarCopierTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('apiary_membership');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('product');
    $this->installEntitySchema('calendar_action_item_requirement');
    $this->installEntitySchema('calendar_action_product_yield');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * The copier service.
   */
  protected function copier(): HivelogCalendarCopier {
    return $this->container->get('hivelog.calendar_copier');
  }

  /**
   * Counts an apiary's calendar actions.
   */
  protected function actionCount(Apiary $apiary): int {
    return (int) \Drupal::entityTypeManager()->getStorage('calendar_action')
      ->getQuery()->accessCheck(FALSE)->condition('apiary', $apiary->id())->count()->execute();
  }

  /**
   * Finds an apiary's action by title.
   */
  protected function actionTitled(Apiary $apiary, string $title): ?CalendarAction {
    $found = \Drupal::entityTypeManager()->getStorage('calendar_action')
      ->loadByProperties(['apiary' => $apiary->id(), 'title' => $title]);
    return $found ? reset($found) : NULL;
  }

  /**
   * A new apiary can start from another apiary's edited calendar.
   */
  public function testNewApiaryCopiesAnotherApiarysCalendar(): void {
    $this->setUpCurrentUser(['uid' => 1], [], TRUE);
    $source = Apiary::create(['name' => 'North']);
    $source->save();
    $this->actionTitled($source, 'Midwinter Cluster Check')->set('week_start', 7)->set('week_end', 8)->save();
    CalendarAction::create([
      'apiary' => $source->id(),
      'title' => 'Heather Honey Move',
      'category' => 'harvest',
      'week_start' => 30,
      'scope' => 'apiary',
    ])->save();
    $this->actionTitled($source, 'Order & Prepare Equipment for the Season')->delete();

    $copy = Apiary::create(['name' => 'South']);
    $copy->setCalendarSource('apiary:' . $source->id());
    $copy->save();

    $this->assertSame($this->actionCount($source), $this->actionCount($copy));
    $this->assertSame('7', $this->actionTitled($copy, 'Midwinter Cluster Check')->get('week_start')->value);
    $this->assertNotNull($this->actionTitled($copy, 'Heather Honey Move'));
    $this->assertNull($this->actionTitled($copy, 'Order & Prepare Equipment for the Season'));
    $this->assertSame('apiary', $this->actionTitled($copy, 'Heather Honey Move')->get('scope')->value);

    // Editing the copy does not touch the source.
    $this->actionTitled($copy, 'Heather Honey Move')->set('week_start', 31)->save();
    $this->assertSame('30', $this->actionTitled($source, 'Heather Honey Move')->get('week_start')->value);
  }

  /**
   * Without a choice, a new apiary still gets the starter calendar.
   */
  public function testDefaultStaysTheStarterCalendar(): void {
    $this->setUpCurrentUser(['uid' => 1], [], TRUE);
    $apiary = Apiary::create(['name' => 'Plain']);
    $apiary->save();
    $this->assertSame(count(CalendarAction::DEFAULT_STARTER_CALENDAR), $this->actionCount($apiary));
  }

  /**
   * Copying onto an existing calendar adds the missing and skips the rest.
   */
  public function testCloneOntoExistingApiaryIsAdditiveAndRepeatable(): void {
    $this->setUpCurrentUser(['uid' => 1], [], TRUE);
    $source = Apiary::create(['name' => 'North']);
    $source->save();
    CalendarAction::create(['apiary' => $source->id(), 'title' => 'Heather Honey Move', 'week_start' => 30])->save();
    $target = Apiary::create(['name' => 'South']);
    $target->save();
    $before = $this->actionCount($target);
    $this->actionTitled($target, 'Midwinter Cluster Check')->set('description', 'My own words')->save();

    $first = $this->copier()->apply($target, 'apiary:' . $source->id());
    $this->assertSame(1, $first['created']);
    $this->assertSame($before, $first['skipped']);
    $this->assertSame($before + 1, $this->actionCount($target));
    $this->assertSame('My own words', $this->actionTitled($target, 'Midwinter Cluster Check')->get('description')->value);

    $second = $this->copier()->apply($target, 'apiary:' . $source->id());
    $this->assertSame(0, $second['created']);
    $this->assertSame($before + 1, $this->actionCount($target));
  }

  /**
   * Requirements and yields follow, matching or creating the catalogue entry.
   */
  public function testRequirementsAndYieldsFollowTheAction(): void {
    $this->setUpCurrentUser(['uid' => 1], [], TRUE);
    $source = Apiary::create(['name' => 'North']);
    $source->save();
    $target = Apiary::create(['name' => 'South']);
    $target->save();
    $storage = \Drupal::entityTypeManager();
    $action = CalendarAction::create([
      'apiary' => $source->id(),
      'title' => 'Feed',
      'week_start' => 38,
    ]);
    $action->save();
    $syrup = $storage->getStorage('inventory_item')->create([
      'apiary' => $source->id(),
      'name' => 'Syrup',
      'unit' => 'litre',
      'item_type' => 'consumable',
    ]);
    $syrup->save();
    $honey = $storage->getStorage('product')->create([
      'apiary' => $source->id(),
      'name' => 'Honey',
      'unit' => 'kg',
    ]);
    $honey->save();
    $storage->getStorage('calendar_action_item_requirement')->create([
      'calendar_action' => $action->id(),
      'item' => $syrup->id(),
      'quantity' => '2.500',
    ])->save();
    $storage->getStorage('calendar_action_product_yield')->create([
      'calendar_action' => $action->id(),
      'product' => $honey->id(),
      'quantity' => '1.000',
    ])->save();
    // The target already has the syrup, so it is matched and not duplicated.
    $existing = $storage->getStorage('inventory_item')->create([
      'apiary' => $target->id(),
      'name' => 'Syrup',
      'unit' => 'litre',
      'item_type' => 'consumable',
    ]);
    $existing->save();

    $result = $this->copier()->apply($target, 'apiary:' . $source->id());

    $this->assertSame(1, $result['requirements']);
    $this->assertSame(1, $result['yields']);
    $copied = $this->actionTitled($target, 'Feed');
    $requirement = reset($storage->getStorage('calendar_action_item_requirement')->loadByProperties(['calendar_action' => $copied->id()]));
    assert($requirement instanceof ContentEntityInterface);
    $this->assertSame($existing->id(), $requirement->get('item')->target_id);
    $syrups = $storage->getStorage('inventory_item')
      ->loadByProperties(['apiary' => $target->id(), 'name' => 'Syrup']);
    $this->assertCount(1, $syrups);
    $yield = reset($storage->getStorage('calendar_action_product_yield')->loadByProperties(['calendar_action' => $copied->id()]));
    assert($yield instanceof ContentEntityInterface);
    $product = $yield->get('product')->entity;
    assert($product instanceof ContentEntityInterface);
    $this->assertSame($target->id(), $product->get('apiary')->target_id);
  }

  /**
   * A user is offered, and may copy from, only apiaries they can view.
   */
  public function testSourcesAreLimitedToWhatTheUserMayView(): void {
    $this->setUpCurrentUser(['uid' => 1], [], TRUE);
    $private = Apiary::create(['name' => 'Private', 'visibility' => 'private']);
    $private->save();
    $target = Apiary::create(['name' => 'Mine']);
    $target->save();

    $stranger = $this->createUser(['view own apiary', 'view own calendar action']);
    $options = $this->copier()->sourceOptions($stranger, $target);
    $this->assertArrayHasKey(HivelogCalendarCopier::SOURCE_DEFAULT, $options);
    $this->assertArrayNotHasKey('apiary:' . $private->id(), $options);

    $this->expectException(\InvalidArgumentException::class);
    $this->copier()->apply($target, 'apiary:' . $private->id(), $stranger);
  }

  /**
   * An unknown source is refused rather than silently ignored.
   */
  public function testUnknownSourceIsRefused(): void {
    $this->setUpCurrentUser(['uid' => 1], [], TRUE);
    $apiary = Apiary::create(['name' => 'Mine']);
    $apiary->save();
    $this->expectException(\InvalidArgumentException::class);
    $this->copier()->apply($apiary, 'preset:nowhere');
  }

  /**
   * Loads the yields of an apiary's action, keyed by product name.
   *
   * @return array<string, string>
   *   Expected quantity by product name.
   */
  protected function yieldsOf(Apiary $apiary, string $title): array {
    $action = $this->actionTitled($apiary, $title);
    $yields = [];
    foreach (\Drupal::entityTypeManager()->getStorage('calendar_action_product_yield')->loadByProperties(['calendar_action' => $action->id()]) as $row) {
      assert($row instanceof ContentEntityInterface);
      $product = $row->get('product')->entity;
      assert($product instanceof ContentEntityInterface);
      $this->assertSame($apiary->id(), $product->get('apiary')->target_id);
      $yields[$product->label() . ' (' . $product->get('unit')->value . ')'] = (string) (float) $row->get('quantity')->value;
    }
    ksort($yields);
    return $yields;
  }

  /**
   * Counts an apiary's products.
   */
  protected function productCount(Apiary $apiary): int {
    return (int) \Drupal::entityTypeManager()->getStorage('product')
      ->getQuery()->accessCheck(FALSE)->condition('apiary', $apiary->id())->count()->execute();
  }

  /**
   * A new apiary can record honey and wax from its first harvest (task 0270).
   */
  public function testStarterHarvestsYieldHoneyAndBeeswax(): void {
    $this->setUpCurrentUser(['uid' => 1], [], TRUE);
    $apiary = Apiary::create(['name' => 'Plain']);
    $apiary->save();

    $expected = ['Beeswax (kg)' => '0', 'Honey (kg)' => '0'];
    $this->assertSame($expected, $this->yieldsOf($apiary, 'Harvest Spring Honey'));
    $this->assertSame($expected, $this->yieldsOf($apiary, 'Harvest Summer Honey'));
    // One Honey and one Beeswax, shared by both harvests, and nothing else.
    $this->assertSame(2, $this->productCount($apiary));
    $this->assertSame([], $this->yieldsOf($apiary, 'Midwinter Cluster Check'));

    // Applying the starter again adds nothing.
    $result = $this->copier()->apply($apiary, HivelogCalendarCopier::SOURCE_DEFAULT);
    $this->assertSame(0, $result['created']);
    $this->assertSame(0, $result['yields']);
    $this->assertSame(2, $this->productCount($apiary));
  }

  /**
   * An apiary made before the starter had yields gets them by copying it.
   */
  public function testExistingApiaryIsGivenTheStarterYields(): void {
    $this->setUpCurrentUser(['uid' => 1], [], TRUE);
    $apiary = Apiary::create(['name' => 'Old']);
    $apiary->save();
    // Put it back to how an apiary looked before: the actions, no yields,
    // and a Honey product of the beekeeper's own with a price.
    $etm = \Drupal::entityTypeManager();
    $etm->getStorage('calendar_action_product_yield')->delete($etm->getStorage('calendar_action_product_yield')->loadMultiple());
    $etm->getStorage('product')->delete($etm->getStorage('product')->loadByProperties(['name' => 'Beeswax']));
    $honeys = $etm->getStorage('product')->loadByProperties(['apiary' => $apiary->id(), 'name' => 'Honey']);
    $honey = reset($honeys);
    assert($honey instanceof ContentEntityInterface);
    $honey->set('expected_unit_price', '12.50')->save();
    $spring = $this->actionTitled($apiary, 'Harvest Spring Honey');
    $spring->set('description', 'My own words')->save();
    $before = $this->actionCount($apiary);

    $result = $this->copier()->apply($apiary, HivelogCalendarCopier::SOURCE_DEFAULT);

    $this->assertSame(0, $result['created']);
    $this->assertSame($before, $result['skipped']);
    $this->assertSame(4, $result['yields']);
    $this->assertSame(['Beeswax (kg)' => '0', 'Honey (kg)' => '0'], $this->yieldsOf($apiary, 'Harvest Spring Honey'));
    // The beekeeper's own Honey is used, not a second one, and is unchanged.
    $this->assertSame(2, $this->productCount($apiary));
    $honey = $etm->getStorage('product')->load($honey->id());
    assert($honey instanceof ContentEntityInterface);
    $this->assertSame('12.50', $honey->get('expected_unit_price')->value);
    // The action itself is not altered.
    $this->assertSame('My own words', $this->actionTitled($apiary, 'Harvest Spring Honey')->get('description')->value);
  }

  /**
   * A yield the apiary already has keeps the quantity the beekeeper set.
   */
  public function testAnExistingYieldIsLeftAlone(): void {
    $this->setUpCurrentUser(['uid' => 1], [], TRUE);
    $apiary = Apiary::create(['name' => 'Tuned']);
    $apiary->save();
    $etm = \Drupal::entityTypeManager();
    $spring = $this->actionTitled($apiary, 'Harvest Spring Honey');
    foreach ($etm->getStorage('calendar_action_product_yield')->loadByProperties(['calendar_action' => $spring->id()]) as $row) {
      assert($row instanceof ContentEntityInterface);
      $row->set('quantity', '18.000')->save();
    }

    $result = $this->copier()->apply($apiary, HivelogCalendarCopier::SOURCE_DEFAULT);

    $this->assertSame(0, $result['yields']);
    $this->assertSame(['Beeswax (kg)' => '18', 'Honey (kg)' => '18'], $this->yieldsOf($apiary, 'Harvest Spring Honey'));
  }

}
