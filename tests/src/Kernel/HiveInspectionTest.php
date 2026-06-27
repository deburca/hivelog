<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormState;
use Drupal\file\Entity\File;
use Drupal\hivelog\Controller\HiveInspectionController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Form\HiveInspectionForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Hive Inspection entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveInspectionTest extends KernelTestBase {

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
   * A test hive.
   *
   * @var \Drupal\hivelog\Entity\Hive
   */
  protected Hive $hive;

  /**
   * A test apiary.
   *
   * @var \Drupal\hivelog\Entity\Apiary
   */
  protected Apiary $apiary;

  /**
   * A test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $user;

  /**
   * Validates dependent inspection fields using form-level rule logic.
   */
  protected function validateDependentInspectionFields(array $values): FormState {
    $defaults = [
      'fed' => [['value' => 0]],
      'feed_type' => [['value' => '']],
      'varroa_check' => [['value' => 0]],
      'varroa_count' => [['value' => '']],
    ];

    $form_state = new FormState();
    $form_state->setValues(array_replace($defaults, $values));

    /** @var \Drupal\hivelog\Form\HiveInspectionForm $form_object */
    $form_object = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveInspectionForm::class);
    $method = new \ReflectionMethod(HiveInspectionForm::class, 'validateDependentFields');
    $method->setAccessible(TRUE);
    $method->invoke($form_object, $form_state);

    return $form_state;
  }

  /**
   * Runs the full normalise-then-validate sequence the form uses on submit.
   */
  protected function normaliseThenValidateDependentInspectionFields(array $values): FormState {
    $defaults = [
      'fed' => [['value' => 0]],
      'feed_type' => [['value' => '']],
      'varroa_check' => [['value' => 0]],
      'varroa_count' => [['value' => '']],
    ];

    $form_state = new FormState();
    $form_state->setValues(array_replace($defaults, $values));

    /** @var \Drupal\hivelog\Form\HiveInspectionForm $form_object */
    $form_object = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveInspectionForm::class);

    $normalise = new \ReflectionMethod(HiveInspectionForm::class, 'normaliseDependentFields');
    $normalise->setAccessible(TRUE);
    $normalise->invoke($form_object, $form_state);

    $validate = new \ReflectionMethod(HiveInspectionForm::class, 'validateDependentFields');
    $validate->setAccessible(TRUE);
    $validate->invoke($form_object, $form_state);

    return $form_state;
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_inspection');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
    $this->installSchema('file', ['file_usage']);

    $this->user = User::create([
      'name' => 'inspector',
      'mail' => 'inspector@example.com',
    ]);
    $this->user->save();

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();

    $this->hive = Hive::create([
      'name' => 'Hive Alpha',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hive->save();
  }

  /**
   * Tests creating an inspection with all fields populated.
   */
  public function testCreateFullInspection(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-06-15',
      'queen_seen' => TRUE,
      'queen_cells' => FALSE,
      'eggs_seen' => TRUE,
      'brood_pattern' => 'good',
      'honey_stores' => 'abundant',
      'pollen_stores' => 'adequate',
      'temperament' => 'calm',
      'population' => 'strong',
      'varroa_check' => TRUE,
      'varroa_count' => 3,
      'disease_signs' => 'none',
      'weight' => 32.5,
      'fed' => FALSE,
      'feed_type' => '',
      'supers' => 2,
      'action_taken' => 'Added super, checked for swarm cells.',
      'notes' => 'Excellent colony. No issues.',
      'uid' => $this->user->id(),
    ]);
    $inspection->save();

    $loaded = HiveInspection::load($inspection->id());

    // Verify all fields.
    $this->assertEquals($this->hive->id(), $loaded->get('hive')->target_id);
    $this->assertEquals('2024-06-15', $loaded->get('inspection_date')->value);
    $this->assertEquals(TRUE, (bool) $loaded->get('queen_seen')->value);
    $this->assertEquals(FALSE, (bool) $loaded->get('queen_cells')->value);
    $this->assertEquals(TRUE, (bool) $loaded->get('eggs_seen')->value);
    $this->assertEquals('good', $loaded->get('brood_pattern')->value);
    $this->assertEquals('abundant', $loaded->get('honey_stores')->value);
    $this->assertEquals('adequate', $loaded->get('pollen_stores')->value);
    $this->assertEquals('calm', $loaded->get('temperament')->value);
    $this->assertEquals('strong', $loaded->get('population')->value);
    $this->assertEquals(TRUE, (bool) $loaded->get('varroa_check')->value);
    $this->assertEquals(3, $loaded->get('varroa_count')->value);
    $this->assertEquals('none', $loaded->get('disease_signs')->value);
    $this->assertEquals(32.5, (float) $loaded->get('weight')->value);
    $this->assertEquals(FALSE, (bool) $loaded->get('fed')->value);
    $this->assertEquals(2, $loaded->get('supers')->value);
    $this->assertEquals('Added super, checked for swarm cells.', $loaded->get('action_taken')->value);
    $this->assertEquals('Excellent colony. No issues.', $loaded->get('notes')->value);
    $this->assertEquals($this->user->id(), $loaded->getOwnerId());
  }

  /**
   * Tests the hive entity reference relationship.
   */
  public function testHiveRelationship(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-07-01',
    ]);
    $inspection->save();

    $loaded = HiveInspection::load($inspection->id());
    $referenced_hive = $loaded->get('hive')->entity;
    $this->assertNotNull($referenced_hive);
    $this->assertEquals('Hive Alpha', $referenced_hive->label());

    // Verify the full chain: inspection → hive → apiary.
    $referenced_apiary = $referenced_hive->get('apiary')->entity;
    $this->assertNotNull($referenced_apiary);
    $this->assertEquals('Test Apiary', $referenced_apiary->label());
  }

  /**
   * Tests the dynamic label generation.
   */
  public function testInspectionLabel(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-08-20',
    ]);
    $inspection->save();

    $loaded = HiveInspection::load($inspection->id());
    $label = (string) $loaded->label();
    $this->assertStringContainsString('Hive Alpha', $label);
    $this->assertStringContainsString('2024-08-20', $label);
  }

  /**
   * Tests that multiple inspections can reference the same hive.
   */
  public function testMultipleInspectionsPerHive(): void {
    $dates = ['2024-04-01', '2024-05-01', '2024-06-01'];
    $ids = [];

    foreach ($dates as $date) {
      $inspection = HiveInspection::create([
        'hive' => $this->hive->id(),
        'inspection_date' => $date,
      ]);
      $inspection->save();
      $ids[] = $inspection->id();
    }

    // Verify all three inspections exist and reference the same hive.
    foreach ($ids as $id) {
      $loaded = HiveInspection::load($id);
      $this->assertNotNull($loaded);
      $this->assertEquals($this->hive->id(), $loaded->get('hive')->target_id);
    }

    // Verify we can query inspections by hive.
    $results = \Drupal::entityTypeManager()
      ->getStorage('hive_inspection')
      ->loadByProperties(['hive' => $this->hive->id()]);
    $this->assertCount(3, $results);
  }

  /**
   * Tests all brood pattern options.
   */
  public function testBroodPatternOptions(): void {
    $patterns = ['good', 'fair', 'poor', 'none'];
    foreach ($patterns as $pattern) {
      $inspection = HiveInspection::create([
        'hive' => $this->hive->id(),
        'inspection_date' => '2024-06-01',
        'brood_pattern' => $pattern,
      ]);
      $inspection->save();

      $loaded = HiveInspection::load($inspection->id());
      $this->assertEquals($pattern, $loaded->get('brood_pattern')->value);
    }
  }

  /**
   * Tests all disease sign options.
   */
  public function testDiseaseSignOptions(): void {
    $diseases = ['none', 'nosema', 'chalkbrood', 'efb', 'afb', 'sacbrood', 'other'];
    foreach ($diseases as $disease) {
      $inspection = HiveInspection::create([
        'hive' => $this->hive->id(),
        'inspection_date' => '2024-06-01',
        'disease_signs' => $disease,
      ]);
      $inspection->save();

      $loaded = HiveInspection::load($inspection->id());
      $this->assertEquals($disease, $loaded->get('disease_signs')->value);
    }
  }

  /**
   * Tests feeding fields together.
   */
  public function testFeedingFields(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-10-01',
      'fed' => TRUE,
      'feed_type' => 'Sugar syrup 2:1',
    ]);
    $inspection->save();

    $loaded = HiveInspection::load($inspection->id());
    $this->assertEquals(TRUE, (bool) $loaded->get('fed')->value);
    $this->assertEquals('Sugar syrup 2:1', $loaded->get('feed_type')->value);
  }

  /**
   * Tests updating an inspection.
   */
  public function testUpdateInspection(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-06-15',
      'queen_seen' => FALSE,
      'honey_stores' => 'low',
    ]);
    $inspection->save();

    $inspection->set('queen_seen', TRUE);
    $inspection->set('honey_stores', 'adequate');
    $inspection->set('notes', 'Found queen on frame 4.');
    $inspection->save();

    $loaded = HiveInspection::load($inspection->id());
    $this->assertEquals(TRUE, (bool) $loaded->get('queen_seen')->value);
    $this->assertEquals('adequate', $loaded->get('honey_stores')->value);
    $this->assertEquals('Found queen on frame 4.', $loaded->get('notes')->value);
  }

  /**
   * Tests deleting an inspection.
   */
  public function testDeleteInspection(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-06-15',
    ]);
    $inspection->save();
    $id = $inspection->id();

    $inspection->delete();
    $this->assertNull(HiveInspection::load($id));
  }

  /**
   * Tests that the inspection form is grouped into structured sections.
   */
  public function testInspectionFormIsGroupedIntoSections(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-06-15',
    ]);

    $form = \Drupal::service('entity.form_builder')->getForm($inspection, 'add');

    $this->assertEquals('vertical_tabs', $form['inspection_sections']['#type']);
    $this->assertArrayHasKey('overview', $form);
    $this->assertArrayHasKey('external_check_section', $form);
    $this->assertArrayHasKey('queen_status', $form);
    $this->assertArrayHasKey('brood_and_stores', $form);
    $this->assertArrayHasKey('colony_condition', $form);
    $this->assertArrayHasKey('health', $form);
    $this->assertArrayHasKey('management', $form);
    $this->assertArrayHasKey('notes_section', $form);
    $this->assertArrayHasKey('photos_section', $form);

    $this->assertEquals('overview', $form['hive']['#group']);
    $this->assertEquals('overview', $form['inspection_date']['#group']);
    // queen_brood has been retired; ensure no residual form element remains.
    $this->assertArrayNotHasKey('queen_brood', $form);
    $this->assertEquals('queen_status', $form['queen_seen']['#group']);
    $this->assertEquals('brood_and_stores', $form['honey_stores']['#group']);
    $this->assertEquals('health', $form['disease_signs']['#group']);
    $this->assertEquals('management', $form['weight']['#group']);
    $this->assertEquals('management', $form['action_taken']['#group']);
    $this->assertEquals('notes_section', $form['notes']['#group']);
    $this->assertEquals('photos_section', $form['images']['#group']);
  }

  /**
   * Tests the grouped inspection view layout.
   */
  public function testInspectionViewIsGroupedIntoSections(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-06-15',
      'external_check' => 'Strong flight activity with pollen coming in.',
      'queen_seen' => TRUE,
      'queen_cells' => FALSE,
      'eggs_seen' => TRUE,
      'brood_pattern' => 'good',
      'honey_stores' => 'adequate',
      'pollen_stores' => 'abundant',
      'temperament' => 'calm',
      'population' => 'strong',
      'varroa_check' => TRUE,
      'varroa_count' => 2,
      'disease_signs' => 'none',
      'weight' => 28.75,
      'fed' => FALSE,
      'feed_type' => '',
      'supers' => 1,
      'action_taken' => 'Added a super.',
      'notes' => 'Colony developing well.',
      'uid' => $this->user->id(),
    ]);
    $inspection->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveInspectionController::class);
    $build = $controller->view($inspection);

    $this->assertArrayHasKey('overview', $build);
    $this->assertArrayHasKey('external_check', $build);
    $this->assertArrayHasKey('queen_status', $build);
    $this->assertArrayHasKey('brood_and_stores', $build);
    $this->assertArrayHasKey('colony_condition', $build);
    $this->assertArrayHasKey('health', $build);
    $this->assertArrayHasKey('management', $build);
    $this->assertArrayHasKey('notes', $build);

    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('Overview', $html);
    $this->assertStringContainsString('External check', $html);
    $this->assertStringContainsString('Queen status', $html);
    $this->assertStringContainsString('Brood, honey and pollen', $html);
    $this->assertStringContainsString('Colony condition', $html);
    $this->assertStringContainsString('Varroa and disease', $html);
    $this->assertStringContainsString('Management', $html);
    $this->assertStringContainsString('Field', $html);
    $this->assertStringContainsString('Value', $html);
    $this->assertStringNotContainsString('Queen Brood', $html);
    $this->assertStringContainsString('Strong flight activity with pollen coming in.', $html);
    $this->assertStringContainsString('28.75 kg', $html);
    $this->assertStringContainsString('Added a super.', $html);
    $this->assertStringContainsString('Colony developing well.', $html);
  }

  /**
   * Tests that the inspection view page renders Edit and Delete action links.
   */
  public function testInspectionViewHasEditAndDeleteActions(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-06-15',
      'uid' => $this->user->id(),
    ]);
    $inspection->save();

    // Grant the current user edit and delete permissions on inspections.
    $role = Role::create([
      'id' => 'inspection_editor',
      'label' => 'Inspection editor',
    ]);
    $role->grantPermission('view any hive inspection');
    $role->grantPermission('edit any hive inspection');
    $role->grantPermission('delete any hive inspection');
    $role->save();
    $this->user->addRole('inspection_editor');
    $this->user->save();
    \Drupal::currentUser()->setAccount($this->user);

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveInspectionController::class);
    $build = $controller->view($inspection);

    $this->assertArrayHasKey('actions', $build);
    $this->assertEquals('component', $build['actions']['#type']);
    $this->assertEquals('hivelog:button-group', $build['actions']['#component']);
    $this->assertCount(2, $build['actions']['#props']['buttons']);

    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('Edit', $html);
    $this->assertStringContainsString('Delete', $html);
    $this->assertStringContainsString('hivelog-button-group', $html);
  }

  /**
   * Tests that inspection photos are rendered as a grid linking to the file.
   */
  public function testInspectionViewRendersPhotosGrid(): void {
    $directory = 'public://hivelog-test-inspection';
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAfbLI3wAAAABJRU5ErkJggg==');

    $files = [];
    foreach (['photo-1.png', 'photo-2.png'] as $filename) {
      $uri = $directory . '/' . $filename;
      file_put_contents($uri, $png);
      $file = File::create([
        'uri' => $uri,
        'filename' => $filename,
        'status' => 1,
      ]);
      $file->save();
      $files[] = $file;
    }

    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-06-15',
      'uid' => $this->user->id(),
      'images' => [
        ['target_id' => $files[0]->id(), 'alt' => 'Photo one'],
        ['target_id' => $files[1]->id(), 'alt' => 'Photo two'],
      ],
    ]);
    $inspection->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveInspectionController::class);
    $build = $controller->view($inspection);

    $this->assertArrayHasKey('photos', $build);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('hivelog-photos-grid', $html);
    $this->assertStringContainsString('hivelog-photos-grid__item', $html);
    $this->assertStringContainsString('photo-1.png', $html);
    $this->assertStringContainsString('photo-2.png', $html);
    $this->assertStringContainsString('alt="Photo one"', $html);
    $this->assertStringContainsString('alt="Photo two"', $html);
    // Photos section heading should appear.
    $this->assertStringContainsString('>Photos<', $html);
  }

  /**
   * Tests that the Photos section is absent when no images are attached.
   */
  public function testInspectionViewOmitsPhotosSectionWhenEmpty(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-06-15',
    ]);
    $inspection->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveInspectionController::class);
    $build = $controller->view($inspection);

    $this->assertArrayNotHasKey('photos', $build);
  }

  /**
   * Tests the weight field stores and retrieves floating-point values.
   */
  public function testWeightField(): void {
    // Test with a decimal value.
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-07-15',
      'weight' => 45.3,
    ]);
    $inspection->save();

    $loaded = HiveInspection::load($inspection->id());
    $this->assertEquals(45.3, (float) $loaded->get('weight')->value);

    // Test that weight is optional (nullable).
    $inspection2 = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-07-16',
    ]);
    $inspection2->save();

    $loaded2 = HiveInspection::load($inspection2->id());
    $this->assertTrue($loaded2->get('weight')->isEmpty());
  }

  /**
   * Tests feed type is required when fed is checked.
   */
  public function testValidationRequiresFeedTypeWhenFed(): void {
    $form_state = $this->validateDependentInspectionFields([
      'fed' => [['value' => 1]],
      'feed_type' => [['value' => '']],
    ]);

    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertArrayHasKey('feed_type', $form_state->getErrors());
  }

  /**
   * Tests feed type is silently cleared when fed is not checked.
   */
  public function testValidationClearsFeedTypeWhenNotFed(): void {
    $form_state = $this->normaliseThenValidateDependentInspectionFields([
      'fed' => [['value' => 0]],
      'feed_type' => [['value' => 'Fondant']],
    ]);

    $this->assertFalse($form_state->hasAnyErrors());
    $feed_type = $form_state->getValue('feed_type');
    $this->assertSame('', $feed_type[0]['value'] ?? NULL);
  }

  /**
   * Tests varroa count is required when varroa check is performed.
   */
  public function testValidationRequiresVarroaCountWhenChecked(): void {
    $form_state = $this->validateDependentInspectionFields([
      'varroa_check' => [['value' => 1]],
      'varroa_count' => [['value' => '']],
    ]);

    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertArrayHasKey('varroa_count', $form_state->getErrors());
  }

  /**
   * Tests varroa count is silently cleared when varroa check is not performed.
   */
  public function testValidationClearsVarroaCountWhenNotChecked(): void {
    $form_state = $this->normaliseThenValidateDependentInspectionFields([
      'varroa_check' => [['value' => 0]],
      'varroa_count' => [['value' => 3]],
    ]);

    $this->assertFalse($form_state->hasAnyErrors());
    $varroa_count = $form_state->getValue('varroa_count');
    $this->assertNull($varroa_count[0]['value'] ?? NULL);
  }

  /**
   * Tests consistent dependent field combinations pass validation.
   */
  public function testValidationAcceptsConsistentDependentFields(): void {
    $form_state = $this->validateDependentInspectionFields([
      'fed' => [['value' => 1]],
      'feed_type' => [['value' => 'Sugar syrup 1:1']],
      'varroa_check' => [['value' => 1]],
      'varroa_count' => [['value' => 2]],
    ]);

    $this->assertFalse($form_state->hasAnyErrors());
  }

  /**
   * Tests that dependent fields declare #states visibility tied to their controlling boolean.
   */
  public function testInspectionFormHasConditionalStatesOnDependentFields(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
    ]);

    $form = \Drupal::service('entity.form_builder')->getForm($inspection, 'add');

    $this->assertArrayHasKey('feed_type', $form);
    $this->assertArrayHasKey('#states', $form['feed_type']);
    $this->assertEquals(
      [['checked' => TRUE]],
      array_values($form['feed_type']['#states']['visible'] ?? [])
    );
    $this->assertArrayHasKey(':input[name="fed[value]"]', $form['feed_type']['#states']['visible']);

    $this->assertArrayHasKey('varroa_count', $form);
    $this->assertArrayHasKey('#states', $form['varroa_count']);
    $this->assertArrayHasKey(
      ':input[name="varroa_check[value]"]',
      $form['varroa_count']['#states']['visible']
    );
    $this->assertEquals(
      ['checked' => TRUE],
      $form['varroa_count']['#states']['visible'][':input[name="varroa_check[value]"]']
    );
  }

  /**
   * Tests that a new inspection form defaults inspection_date to today.
   */
  public function testInspectionFormDefaultsInspectionDateToToday(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
    ]);

    $form = \Drupal::service('entity.form_builder')->getForm($inspection, 'add');

    $today = date('Y-m-d');
    $default = $form['inspection_date']['widget'][0]['value']['#default_value'] ?? NULL;
    $this->assertNotNull($default, 'inspection_date should have a default date set on a new form.');
    $this->assertEquals($today, $default->format('Y-m-d'));
  }

  /**
   * Tests that a new inspection form defaults disease_signs to "none".
   */
  public function testInspectionFormDefaultsDiseaseSignsToNone(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
    ]);

    $form = \Drupal::service('entity.form_builder')->getForm($inspection, 'add');

    $this->assertEquals('none', $form['disease_signs']['widget']['#default_value'][0] ?? NULL);
  }

  /**
   * Tests that the dormant queen_brood field has been fully retired.
   */
  public function testQueenBroodFieldIsRetired(): void {
    $base_fields = \Drupal::service('entity_field.manager')
      ->getBaseFieldDefinitions('hive_inspection');
    $this->assertArrayNotHasKey('queen_brood', $base_fields);

    $table_mapping = \Drupal::entityTypeManager()
      ->getStorage('hive_inspection')
      ->getTableMapping();
    $this->assertNotContains(
      'queen_brood',
      $table_mapping->getFieldNames('hivelog_hive_inspection')
    );

    $schema = \Drupal::database()->schema();
    $this->assertFalse($schema->fieldExists('hivelog_hive_inspection', 'queen_brood'));
    $this->assertFalse($schema->fieldExists('hivelog_hive_inspection', 'queen_brood__value'));
  }

  /**
   * Tests that editing an existing inspection does NOT override saved values.
   */
  public function testInspectionFormDoesNotOverrideExistingDefaults(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2023-05-15',
      'disease_signs' => 'chalkbrood',
    ]);
    $inspection->save();

    $form = \Drupal::service('entity.form_builder')->getForm($inspection, 'edit');

    $default_date = $form['inspection_date']['widget'][0]['value']['#default_value'] ?? NULL;
    $this->assertNotNull($default_date);
    $this->assertEquals('2023-05-15', $default_date->format('Y-m-d'));
    $this->assertEquals('chalkbrood', $form['disease_signs']['widget']['#default_value'][0] ?? NULL);
  }

}
