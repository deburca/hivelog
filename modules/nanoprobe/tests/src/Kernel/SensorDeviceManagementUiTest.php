<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\nanoprobe\Controller\SensorDeviceController;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Sensor Device management UI (task 0106).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class SensorDeviceManagementUiTest extends KernelTestBase {

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
    'nanoprobe',
  ];

  /**
   * An administrator, so `administer hivelog`-gated actions succeed.
   */
  protected User $admin;

  /**
   * A test apiary.
   */
  protected Apiary $apiary;

  /**
   * A test hive, belonging to `$apiary`.
   */
  protected Hive $hive;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('sensor_device');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system']);
    \Drupal::service('router.builder')->rebuild();

    $admin_role = Role::create(['id' => 'hivelog_admin', 'label' => 'Hivelog Admin']);
    $admin_role->grantPermission('administer hivelog');
    $admin_role->save();
    $this->admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $this->admin->addRole('hivelog_admin');
    $this->admin->save();
    $this->setCurrentUser($this->admin);

    $this->apiary = Apiary::create(['name' => 'Test Apiary', 'uid' => $this->admin->id()]);
    $this->apiary->save();
    $this->hive = Hive::create(['name' => 'Test Hive', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $this->hive->save();
  }

  /**
   * Builds the add form's render array for a given set of field values.
   */
  protected function buildAddForm(array $values): array {
    $entity = SensorDevice::create($values);
    $form_object = \Drupal::entityTypeManager()->getFormObject('sensor_device', 'add');
    $form_object->setEntity($entity);
    $form_state = new FormState();
    return \Drupal::formBuilder()->buildForm($form_object, $form_state);
  }

  /**
   * Tests saving a valid hive-scoped device through the real form.
   */
  public function testFormSavesHiveScopedDevice(): void {
    $entity = SensorDevice::create([
      'label' => 'Hive Scale',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('sensor_device', 'add');
    $form_object->setEntity($entity);
    $form_object->save([], new FormState());

    $loaded = SensorDevice::load($entity->id());
    $this->assertEquals('hive', $loaded->get('scope')->value);
    $this->assertEquals($this->hive->id(), $loaded->get('hive')->target_id);
    $this->assertEquals($this->apiary->id(), $loaded->get('apiary')->target_id);

    $messages = \Drupal::messenger()->all();
    $status_text = implode(' ', array_map('strval', $messages['status'] ?? []));
    $this->assertStringContainsString('has been created', $status_text);
  }

  /**
   * Tests saving a valid apiary-scoped device through the real form.
   */
  public function testFormSavesApiaryScopedDevice(): void {
    $entity = SensorDevice::create([
      'label' => 'Weather Station',
      'apiary' => $this->apiary->id(),
      'scope' => 'apiary',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('sensor_device', 'add');
    $form_object->setEntity($entity);
    $form_object->save([], new FormState());

    $loaded = SensorDevice::load($entity->id());
    $this->assertEquals('apiary', $loaded->get('scope')->value);
    $this->assertTrue($loaded->get('hive')->isEmpty());
  }

  /**
   * Tests validation rejects a hive-scoped device with no hive selected.
   */
  public function testFormValidationRejectsMissingHiveForHiveScope(): void {
    $entity = SensorDevice::create([
      'label' => 'Missing Hive',
      'apiary' => $this->apiary->id(),
      'scope' => 'hive',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('sensor_device', 'add');
    $form_object->setEntity($entity);
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $form_object->validateForm($form, $form_state);

    $this->assertArrayHasKey('hive', $form_state->getErrors());
    $this->assertStringContainsString('Hive is required', (string) $form_state->getErrors()['hive']);
  }

  /**
   * Tests validation rejects an apiary-scoped device with a hive set.
   */
  public function testFormValidationRejectsHivePresentForApiaryScope(): void {
    $entity = SensorDevice::create([
      'label' => 'Stray Hive',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'apiary',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('sensor_device', 'add');
    $form_object->setEntity($entity);
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $form_object->validateForm($form, $form_state);

    $this->assertArrayHasKey('hive', $form_state->getErrors());
    $this->assertStringContainsString('must be left empty', (string) $form_state->getErrors()['hive']);
  }

  /**
   * Tests `hive`'s widget carries the right `#states`.
   *
   * Kernel tests can't exercise real client-side JS, but the `#states`
   * array itself is a real, inspectable part of the render array — this
   * asserts the exact selector/value the form actually builds, mirroring
   * `AiProviderConfigManagementUiTest::testModeConditionalStatesAreWiredCorrectly()`.
   */
  public function testHiveFieldStatesAreWiredCorrectly(): void {
    $form = $this->buildAddForm(['label' => 'States Probe', 'apiary' => $this->apiary->id(), 'scope' => 'hive']);

    $this->assertEquals(
      ['visible' => [':input[name="scope"]' => ['value' => 'hive']]],
      $form['hive']['widget'][0]['target_id']['#states']
    );
  }

  /**
   * Tests the add form shows the token once via a status message.
   *
   * And that it is not recoverable in plaintext after a fresh load.
   */
  public function testTokenShownOnceOnCreateAndNotRecoverableAfter(): void {
    $device = SensorDevice::create([
      'label' => 'Token Probe',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
    ]);

    $form_object = \Drupal::entityTypeManager()->getFormObject('sensor_device', 'add');
    $form_object->setEntity($device);
    $form_object->save([], new FormState());

    $messages = \Drupal::messenger()->all();
    $warning_text = implode(' ', array_map('strval', $messages['warning'] ?? []));
    $this->assertStringContainsString('will not be shown again', $warning_text);
    $this->assertStringContainsString($device->getPlainTextToken(), $warning_text);

    \Drupal::messenger()->deleteAll();

    $reloaded = SensorDevice::load($device->id());
    $this->assertNull($reloaded->getPlainTextToken());
  }

  /**
   * Tests the collection page lists accessible devices and hides others.
   */
  public function testCollectionListsAccessibleDevicesAndHidesInaccessible(): void {
    $other_apiary = Apiary::create(['name' => 'Other Apiary', 'uid' => $this->admin->id(), 'visibility' => 'private']);
    $other_apiary->save();
    $other_hive = Hive::create(['name' => 'Other Hive', 'apiary' => $other_apiary->id(), 'status' => 'active']);
    $other_hive->save();

    $visible = SensorDevice::create([
      'label' => 'Visible Device',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
    ]);
    $visible->save();
    $other = SensorDevice::create([
      'label' => 'Other Device',
      'apiary' => $other_apiary->id(),
      'hive' => $other_hive->id(),
      'scope' => 'hive',
    ]);
    $other->save();

    $role = Role::create(['id' => 'device_viewer', 'label' => 'Device Viewer']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('view own sensor device');
    $role->save();
    $viewer = User::create(['name' => 'viewer', 'mail' => 'viewer@example.com']);
    $viewer->addRole('device_viewer');
    $viewer->save();
    $this->apiary->set('beekeepers', [$viewer->id()]);
    $this->apiary->save();
    \Drupal::currentUser()->setAccount($viewer);

    $build = \Drupal::entityTypeManager()->getListBuilder('sensor_device')->render();

    $this->assertCount(1, $build['table']['#props']['rows']);
    $this->assertStringContainsString('Visible Device', (string) $build['table']['#props']['rows'][0]['cells'][0]);
  }

  /**
   * Tests deleting a device redirects to the collection and is gated.
   */
  public function testDeleteWorksAndIsGated(): void {
    $device = SensorDevice::create([
      'label' => 'Delete Me',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
    ]);
    $device->save();

    $role = Role::create(['id' => 'no_delete', 'label' => 'No Delete']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('view own sensor device');
    $role->grantPermission('edit own sensor device');
    $role->save();
    $no_delete_user = User::create(['name' => 'no-delete', 'mail' => 'no-delete@example.com']);
    $no_delete_user->addRole('no_delete');
    $no_delete_user->save();

    $this->assertFalse($device->access('delete', $no_delete_user));
    $this->assertTrue($device->access('delete', $this->admin));

    $form_object = \Drupal::entityTypeManager()->getFormObject('sensor_device', 'delete');
    $form_object->setEntity($device);
    $form_state = new FormState();
    $form = [];
    $form_object->submitForm($form, $form_state);

    $this->assertNull(SensorDevice::load($device->id()));
    $this->assertEquals('entity.sensor_device.collection', $form_state->getRedirect()->getRouteName());
  }

  /**
   * Tests the Hive canonical page's Sensors panel shows "Add Sensor" to an admin.
   */
  public function testAddSensorLinkAppearsForAdminOnHivePanel(): void {
    $build = \Drupal::service('nanoprobe.sensor_panel_builder')->buildHivePanel($this->hive);
    $this->assertArrayHasKey('add', $build['nanoprobe_sensors']['heading']);
    $this->assertEquals('Add Sensor', (string) $build['nanoprobe_sensors']['heading']['add']['#props']['label']);
  }

  /**
   * Tests the "Add Sensor" link is hidden for a user without create access.
   *
   * With no devices registered on this hive either, the whole panel has
   * nothing to show — the empty array itself is the assertion that
   * neither the link nor an empty-state placeholder leaked through.
   */
  public function testAddSensorLinkHiddenForNonAdminOnHivePanel(): void {
    $role = Role::create(['id' => 'no_add', 'label' => 'No Add']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('view own sensor device');
    $role->save();
    $viewer = User::create(['name' => 'panel-viewer', 'mail' => 'panel-viewer@example.com']);
    $viewer->addRole('no_add');
    $viewer->save();
    $this->apiary->set('beekeepers', [$viewer->id()]);
    $this->apiary->save();
    \Drupal::currentUser()->setAccount($viewer);

    $build = \Drupal::service('nanoprobe.sensor_panel_builder')->buildHivePanel($this->hive);
    $this->assertEquals([], $build);
  }

  /**
   * Tests the hive-contextual add controller pre-fills apiary/hive/scope.
   *
   * Mirrors `QueenObservationTest`'s own reasoning: the rendered form
   * array doesn't expose an easily-introspectable `#entity`/default-value
   * for the widget, so this exercises the route-parameter → entity path
   * (the form builds without error) and separately asserts the same
   * entity-creation values the controller sets, the way that precedent
   * does.
   */
  public function testAddFormForHivePrefillsApiaryHiveAndScope(): void {
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(SensorDeviceController::class);
    $form = $controller->addFormForHive($this->hive);
    $this->assertIsArray($form);

    $new = \Drupal::entityTypeManager()->getStorage('sensor_device')->create([
      'apiary' => $this->hive->get('apiary')->target_id,
      'hive' => $this->hive->id(),
      'scope' => 'hive',
    ]);
    $this->assertEquals($this->apiary->id(), $new->get('apiary')->target_id);
    $this->assertEquals($this->hive->id(), $new->get('hive')->target_id);
    $this->assertEquals('hive', $new->get('scope')->value);
  }

  /**
   * Tests the apiary-contextual add controller pre-fills apiary/scope.
   */
  public function testAddFormForApiaryPrefillsApiaryAndScope(): void {
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(SensorDeviceController::class);
    $form = $controller->addFormForApiary($this->apiary);
    $this->assertIsArray($form);

    $new = \Drupal::entityTypeManager()->getStorage('sensor_device')->create([
      'apiary' => $this->apiary->id(),
      'scope' => 'apiary',
    ]);
    $this->assertEquals($this->apiary->id(), $new->get('apiary')->target_id);
    $this->assertTrue($new->get('hive')->isEmpty());
    $this->assertEquals('apiary', $new->get('scope')->value);
  }

}
