<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Routing\RouteMatch;
use Drupal\hivelog\Controller\ApiaryController;
use Drupal\hivelog\Controller\HiveController;
use Drupal\hivelog\Controller\InventoryReportController;
use Drupal\hivelog\Delete\HivelogDeleteDependencyCounter;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\HarvestYield;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\Product;
use Drupal\hivelog\HivelogEntityHierarchy;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Route;

/**
 * Tests a harvest recorded directly, with no calendar action (task 0271).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class DirectHarvestTest extends KernelTestBase {

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
   * A test apiary.
   */
  protected Apiary $apiary;

  /**
   * A hive in the test apiary.
   */
  protected Hive $hive;

  /**
   * A product of the test apiary, priced at 10 a kg.
   */
  protected Product $honey;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    foreach ([
      'user', 'file', 'apiary', 'apiary_membership', 'hive', 'hive_inspection', 'hive_component',
      'queen', 'queen_observation', 'calendar_action', 'calendar_action_item_requirement',
      'calendar_action_product_yield', 'hive_action_log', 'apiary_action_log', 'inventory_item',
      'inventory_purchase', 'inventory_usage', 'product', 'harvest_yield',
    ] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);
    \Drupal::service('router.builder')->rebuild();

    // The first user is uid 1, so access passes.
    User::create(['name' => 'root', 'mail' => 'root@example.com'])->save();
    \Drupal::currentUser()->setAccount(User::load(1));

    $this->apiary = Apiary::create(['name' => 'Home Apiary']);
    $this->apiary->save();
    $this->hive = Hive::create(['name' => 'Hive One', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $this->hive->save();
    $this->honey = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Heather honey',
      'unit' => 'kg',
      'expected_unit_price' => 10,
    ]);
    $this->honey->save();
  }

  /**
   * Pushes a request so controllers and forms can read the query string.
   */
  protected function pushRequest(string $path): void {
    $request = Request::create($path);
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
  }

  /**
   * Creates a direct harvest of the test product.
   */
  protected function harvest(array $values = []): HarvestYield {
    $harvest = HarvestYield::create($values + [
      'hive' => $this->hive->id(),
      'product' => $this->honey->id(),
      'quantity' => 12,
      'harvest_date' => date('Y') . '-06-20',
    ]);
    $harvest->save();
    return $harvest;
  }

  /**
   * A harvest from a hive is stamped with the hive's apiary and a date.
   */
  public function testHiveHarvestIsStampedWithApiaryAndDate(): void {
    $harvest = HarvestYield::create([
      'hive' => $this->hive->id(),
      'product' => $this->honey->id(),
      'quantity' => 3,
    ]);
    $harvest->save();

    $this->assertTrue($harvest->isDirect());
    // Reloaded, the stored "3.000" still reads "3" in the label.
    $reloaded = HarvestYield::load($harvest->id());
    $this->assertSame('Heather honey — 3 kg', (string) $reloaded->label());
    $this->assertEquals($this->apiary->id(), $harvest->get('apiary')->target_id);
    $this->assertSame(date('Y-m-d'), $harvest->get('harvest_date')->value);
    // The price is snapshotted as for a yield reported with a log.
    $this->assertEquals(10, (float) $harvest->get('unit_price_snapshot')->value);
  }

  /**
   * A harvest needs a log, or an apiary of its own.
   */
  public function testHarvestWithNoSourceIsRefused(): void {
    $this->expectException(EntityStorageException::class);
    HarvestYield::create(['product' => $this->honey->id(), 'quantity' => 1])->save();
  }

  /**
   * The product must be one of the apiary the harvest is recorded in.
   */
  public function testProductOfAnotherApiaryIsRefused(): void {
    $other = Apiary::create(['name' => 'Other Apiary']);
    $other->save();
    $foreign = Product::create(['apiary' => $other->id(), 'name' => 'Their honey', 'unit' => 'kg']);
    $foreign->save();

    $harvest = HarvestYield::create([
      'hive' => $this->hive->id(),
      'apiary' => $this->apiary->id(),
      'product' => $foreign->id(),
      'quantity' => 1,
    ]);
    // The friendly error, for a form or an API.
    $messages = [];
    foreach ($harvest->validate() as $violation) {
      $messages[] = (string) $violation->getMessage();
    }
    $this->assertContains('The selected product must belong to the apiary this harvest is recorded in.', $messages);

    // The backstop, for a caller that never validates.
    $this->expectException(EntityStorageException::class);
    $harvest->save();
  }

  /**
   * A harvest stays with the apiary it was recorded in.
   */
  public function testHarvestApiaryCannotBeChanged(): void {
    $other = Apiary::create(['name' => 'Other Apiary']);
    $other->save();
    $harvest = $this->harvest();

    $this->expectException(EntityStorageException::class);
    $harvest->set('apiary', $other->id())->save();
  }

  /**
   * Direct harvests count in the apiary's yield and income for their year.
   */
  public function testDirectHarvestsCountInTheReport(): void {
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(InventoryReportController::class);
    $year = (int) date('Y');
    $before = $controller->computeApiaryYearTotals($this->apiary, $year)['income_total'];

    // From the hive, for the apiary as a whole, and one from last year.
    $this->harvest(['quantity' => 12]);
    $this->harvest(['hive' => NULL, 'apiary' => $this->apiary->id(), 'quantity' => 3]);
    $this->harvest(['quantity' => 100, 'harvest_date' => ($year - 1) . '-08-01']);

    $this->assertEqualsWithDelta($before + 150.0, $controller->computeApiaryYearTotals($this->apiary, $year)['income_total'], 0.001);
    $this->assertEqualsWithDelta(1000.0, $controller->computeApiaryYearTotals($this->apiary, $year - 1)['income_total'], 0.001);

    // Another apiary's year holds none of it.
    $other = Apiary::create(['name' => 'Other Apiary']);
    $other->save();
    $this->assertEqualsWithDelta(0.0, $controller->computeApiaryYearTotals($other, $year)['income_total'], 0.001);
  }

  /**
   * A hive or apiary with a recorded harvest cannot be deleted.
   */
  public function testHarvestBlocksDeletingItsHiveAndApiary(): void {
    $harvest = $this->harvest();
    $counter = \Drupal::service('hivelog.delete_dependency_counter');
    $hive_result = $counter->blockingAccessResult($this->hive);
    $this->assertInstanceOf(AccessResultForbidden::class, $hive_result);
    $this->assertStringContainsString('1 harvest yield record', (string) $hive_result->getReason());
    $apiary_result = $counter->blockingAccessResult($this->apiary);
    $this->assertInstanceOf(AccessResultForbidden::class, $apiary_result);
    $this->assertStringContainsString('1 harvest yield record', (string) $apiary_result->getReason());

    // Once the harvest is gone, the hive is no longer blocked by it.
    $harvest->delete();
    $fresh = new HivelogDeleteDependencyCounter(
      \Drupal::entityTypeManager(),
      \Drupal::service('entity.last_installed_schema.repository'),
    );
    $this->assertNull($fresh->blockingAccessResult($this->hive));
  }

  /**
   * The hive page lists its harvests and offers to record one.
   */
  public function testHivePageHasHarvestSection(): void {
    $this->harvest(['notes' => 'Two supers']);
    $this->pushRequest('/hivelog/hive/' . $this->hive->id());
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($this->hive);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('id="harvests"', $html);
    $this->assertStringContainsString('Record harvest', $html);
    $this->assertStringContainsString('/hivelog/hive/' . $this->hive->id() . '/harvest/add', $html);
    $this->assertStringContainsString('Heather honey', $html);
    $this->assertStringContainsString('12 kg', $html);
    $this->assertStringContainsString('Two supers', $html);
    $this->assertStringContainsString('/hivelog/harvest/1/edit', $html);
  }

  /**
   * The apiary page lists its own harvests and its hives', naming the hive.
   */
  public function testApiaryPageHasHarvestSection(): void {
    $this->harvest();
    $this->harvest(['hive' => NULL, 'apiary' => $this->apiary->id(), 'quantity' => 3]);
    $this->pushRequest('/hivelog/apiary/' . $this->apiary->id());
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($this->apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('id="harvests"', $html);
    $this->assertStringContainsString('/hivelog/apiary/' . $this->apiary->id() . '/harvest/add', $html);
    $this->assertStringContainsString('12 kg', $html);
    $this->assertStringContainsString('3 kg', $html);
    $this->assertStringContainsString('Hive One', $html);
  }

  /**
   * The add form is scoped to the hive's apiary and asks for a date.
   */
  public function testAddFormForHive(): void {
    $this->pushRequest('/hivelog/hive/' . $this->hive->id() . '/harvest/add');
    $harvest = HarvestYield::create([
      'hive' => $this->hive->id(),
      'apiary' => $this->apiary->id(),
      'harvest_date' => date('Y-m-d'),
    ]);
    $form = \Drupal::service('entity.form_builder')->getForm($harvest, 'add');

    $this->assertSame('default:hivelog_apiary_scoped', $form['product']['widget'][0]['target_id']['#selection_handler']);
    $this->assertEquals($this->apiary->id(), $form['product']['widget'][0]['target_id']['#selection_settings']['apiary_id']);
    $this->assertArrayHasKey('quantity', $form);
    $this->assertArrayHasKey('harvest_date', $form);
    $this->assertArrayHasKey('notes', $form);
    $this->assertStringContainsString('Hive One', (string) $form['harvest_source']['#markup']);
    // The parents are set by the page, not typed.
    $this->assertArrayNotHasKey('hive', $form);
    $this->assertArrayNotHasKey('apiary', $form);
  }

  /**
   * A harvest's edit and delete pages sit under its hive, or its apiary.
   *
   * The breadcrumb and the Cancel link both read this.
   */
  public function testHarvestPagesBelongUnderHiveOrApiary(): void {
    $from_hive = $this->harvest();
    $for_apiary = $this->harvest(['hive' => NULL, 'apiary' => $this->apiary->id()]);

    $route_match = new RouteMatch('entity.harvest_yield.edit_form', new Route('/hivelog/harvest/{harvest_yield}/edit'), ['harvest_yield' => $from_hive]);
    $subject = HivelogEntityHierarchy::resolveSubject($route_match, 'entity.harvest_yield.edit_form');
    $this->assertSame($from_hive, $subject);

    $this->assertSame('hive', HivelogEntityHierarchy::resolveParent($from_hive)->getEntityTypeId());
    $this->assertSame('apiary', HivelogEntityHierarchy::resolveParent($for_apiary)->getEntityTypeId());
  }

}
