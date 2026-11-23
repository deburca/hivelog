<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the quick mode of the inspection form (task 0279).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveInspectionQuickModeTest extends KernelTestBase {

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
   * A hive in a test apiary.
   */
  protected Hive $hive;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    foreach ([
      'user', 'file', 'apiary', 'apiary_membership', 'hive', 'hive_reminder', 'queen_observation', 'hive_inspection',
      'queen', 'calendar_action', 'calendar_action_item_requirement', 'calendar_action_product_yield',
      'inventory_item', 'product',
    ] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);

    // The first user is uid 1, who passes every access check.
    User::create(['name' => 'root', 'mail' => 'root@example.com'])->save();
    \Drupal::currentUser()->setAccount(User::load(1));

    $apiary = Apiary::create(['name' => 'Home Apiary']);
    $apiary->save();
    $this->hive = Hive::create(['name' => 'Hive One', 'apiary' => $apiary->id(), 'status' => 'active']);
    $this->hive->save();
  }

  /**
   * Builds the add form for a new inspection, optionally in quick mode.
   */
  protected function buildAddForm(bool $quick): array {
    $query = $quick ? ['mode' => 'quick'] : [];
    $request = Request::create('/hivelog/hive/1/inspection/add', 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::requestStack()->push($request);
    $inspection = HiveInspection::create(['hive' => $this->hive->id()]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('hive_inspection', 'add');
    $form_object->setEntity($inspection);
    return \Drupal::formBuilder()->buildForm($form_object, new FormState());
  }

  /**
   * Whether a form element is shown to the user.
   */
  protected function isShown(array $form, string $name): bool {
    return isset($form[$name]) && ($form[$name]['#access'] ?? TRUE) !== FALSE;
  }

  /**
   * Quick mode shows only its own fields and no tabs.
   */
  public function testQuickFormShowsOnlyTheQuickFields(): void {
    $form = $this->buildAddForm(TRUE);
    foreach (['inspection_date', 'queen_seen', 'eggs_seen', 'honey_stores', 'population', 'temperament', 'notes'] as $field) {
      $this->assertTrue($this->isShown($form, $field), "$field is in the quick form.");
    }
    foreach (['varroa_count', 'disease_signs', 'fed', 'supers', 'frames_of_bees', 'pollen_stores', 'images'] as $field) {
      $this->assertFalse($this->isShown($form, $field), "$field is left out of the quick form.");
    }
    $this->assertArrayNotHasKey('inspection_sections', $form);
    $this->assertArrayHasKey('mode_switch', $form);
  }

  /**
   * The full form is still the default and keeps its tabs.
   */
  public function testFullFormIsTheDefault(): void {
    $form = $this->buildAddForm(FALSE);
    $this->assertArrayHasKey('inspection_sections', $form);
    $this->assertTrue($this->isShown($form, 'varroa_count'));
    $this->assertTrue($this->isShown($form, 'frames_of_bees'));
  }

  /**
   * Stores and population start from the last inspection; facts never do.
   */
  public function testQuickFormOffersOnlySafeDefaults(): void {
    HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2026-05-01',
      'honey_stores' => 'low',
      'population' => 'weak',
      'queen_seen' => TRUE,
      'eggs_seen' => TRUE,
      'varroa_check' => TRUE,
      'varroa_count' => 4,
      'disease_signs' => 'nosema',
    ])->save();
    // An older one must not win over the newer one above.
    HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2026-04-01',
      'honey_stores' => 'abundant',
      'population' => 'strong',
    ])->save();

    $form = $this->buildAddForm(TRUE);
    $this->assertSame('low', $form['honey_stores']['widget']['#default_value'][0] ?? NULL);
    $this->assertSame('weak', $form['population']['widget']['#default_value'][0] ?? NULL);
    $this->assertEmpty($form['queen_seen']['widget']['value']['#default_value'] ?? FALSE);
    $this->assertEmpty($form['eggs_seen']['widget']['value']['#default_value'] ?? FALSE);
    // The full form carries nothing over.
    $full = $this->buildAddForm(FALSE);
    $this->assertEmpty($full['honey_stores']['widget']['#default_value'] ?? []);
  }

  /**
   * A quick save stores the same entity, with the hidden fields at defaults.
   */
  public function testQuickSaveWritesAnOrdinaryInspection(): void {
    $this->buildAddForm(TRUE);
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2026-06-01',
      'queen_seen' => TRUE,
      'honey_stores' => 'adequate',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('hive_inspection', 'add');
    $form_object->setEntity($inspection);
    $form_object->save([], new FormState());

    $saved = HiveInspection::load($inspection->id());
    $this->assertNotNull($saved);
    $this->assertTrue((bool) $saved->get('queen_seen')->value);
    $this->assertTrue($saved->get('varroa_check')->isEmpty() || !$saved->get('varroa_check')->value);
    $this->assertSame('adequate', $saved->get('honey_stores')->value);
  }

}
