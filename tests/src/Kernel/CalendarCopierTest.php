<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

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
    $this->assertSame($existing->id(), $requirement->get('item')->target_id);
    $syrups = $storage->getStorage('inventory_item')
      ->loadByProperties(['apiary' => $target->id(), 'name' => 'Syrup']);
    $this->assertCount(1, $syrups);
    $yield = reset($storage->getStorage('calendar_action_product_yield')->loadByProperties(['calendar_action' => $copied->id()]));
    $this->assertSame($target->id(), $yield->get('product')->entity->get('apiary')->target_id);
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

}
