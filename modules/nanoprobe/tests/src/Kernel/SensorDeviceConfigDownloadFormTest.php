<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\nanoprobe\Controller\SensorIngestController;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\nanoprobe\Entity\SensorReading;
use Drupal\nanoprobe\Form\SensorDeviceConfigDownloadForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests the Sensor Device config descriptor download / token regeneration.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class SensorDeviceConfigDownloadFormTest extends KernelTestBase {

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
   * A test apiary, owned by `$owner`.
   */
  protected Apiary $apiary;

  /**
   * A test hive, belonging to `$apiary`.
   */
  protected Hive $hive;

  /**
   * A test sensor device, belonging to `$hive`.
   */
  protected SensorDevice $device;

  /**
   * The apiary owner user (has update access to `$device`).
   */
  protected User $owner;

  /**
   * A user with no relationship to `$apiary` (no access to `$device`).
   */
  protected User $outsider;

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
    $this->installEntitySchema('sensor_reading');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system']);
    \Drupal::service('router.builder')->rebuild();

    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('edit own apiary');
    $role->grantPermission('view own sensor device');
    $role->grantPermission('edit own sensor device');
    $role->save();

    $this->owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $this->owner->addRole('beekeeper');
    $this->owner->save();

    $this->outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $this->outsider->addRole('beekeeper');
    $this->outsider->save();

    $this->apiary = Apiary::create([
      'name' => 'Test Apiary',
      'uid' => $this->owner->id(),
      'visibility' => 'private',
    ]);
    $this->apiary->save();

    $this->hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
      'uid' => $this->owner->id(),
    ]);
    $this->hive->save();

    $this->device = SensorDevice::create([
      'label' => 'VV-01 Scale',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'uid' => $this->owner->id(),
    ]);
    $this->device->save();
  }

  /**
   * Tests the descriptor's exact shape and a weight device's metric list.
   */
  public function testDescriptorShape(): void {
    $this->setCurrentUser($this->owner);

    $form_object = new SensorDeviceConfigDownloadForm();
    $form_state = new FormState();
    $form = $form_object->buildForm([], $form_state, $this->device);
    $form_object->submitForm($form, $form_state);

    $response = $form_state->getResponse();
    $this->assertNotNull($response);
    $descriptor = json_decode($response->getContent(), TRUE);

    $this->assertEquals(1, $descriptor['config_version']);
    $this->assertEquals((int) $this->device->id(), $descriptor['device']['id']);
    $this->assertEquals('VV-01 Scale', $descriptor['device']['label']);
    $this->assertStringContainsString('/hivelog/api/sensor-readings', $descriptor['endpoint']['url']);
    $this->assertEquals('POST', $descriptor['endpoint']['method']);
    $this->assertEquals('application/json', $descriptor['endpoint']['content_type']);
    $this->assertEquals('bearer', $descriptor['auth']['type']);
    $this->assertNotEmpty($descriptor['auth']['token']);
    $this->assertTrue($descriptor['transport']['batch']);
    $this->assertEquals(SensorDevice::DEFAULT_SUGGESTED_INTERVAL_SECONDS, $descriptor['transport']['suggested_interval_seconds']);
    $this->assertEquals(['weight_kg', 'battery_voltage', 'signal_rssi'], $descriptor['metrics']);
  }

  /**
   * Tests each mapped `device_type` gets its own metric list.
   */
  public function testMetricsPerDeviceType(): void {
    $this->device->set('device_type', 'temperature_humidity');
    $this->assertEquals(
      [
        'temp_internal_c',
        'temp_external_c',
        'humidity_internal_pct',
        'humidity_external_pct',
        'battery_voltage',
        'signal_rssi',
      ],
      $this->device->getConfigMetrics()
    );

    $this->device->set('device_type', 'acoustic');
    $this->assertEquals(['battery_voltage', 'signal_rssi'], $this->device->getConfigMetrics());
  }

  /**
   * Tests `multi`/`other`/unset device_type fall back to the full taxonomy.
   */
  public function testUnmappedDeviceTypeFallsBackToFullMetricList(): void {
    $this->device->set('device_type', 'multi');
    $this->assertEquals(array_keys(SensorReading::METRIC_TYPES), $this->device->getConfigMetrics());

    $this->device->set('device_type', NULL);
    $this->assertEquals(array_keys(SensorReading::METRIC_TYPES), $this->device->getConfigMetrics());
  }

  /**
   * Tests a user without update access to the device cannot build the form.
   */
  public function testAccessDeniedForUserWithoutUpdateRights(): void {
    $this->setCurrentUser($this->outsider);

    $form_object = new SensorDeviceConfigDownloadForm();
    $this->expectException(AccessDeniedHttpException::class);
    $form_object->buildForm([], new FormState(), $this->device);
  }

  /**
   * Tests regenerating the token via the form invalidates the previous one.
   *
   * A follow-up ingest request using the stale (pre-regeneration) token
   * must be rejected with 401.
   */
  public function testRegenerationInvalidatesPreviousToken(): void {
    $this->setCurrentUser($this->owner);
    $stale_token = $this->device->getPlainTextToken();

    $form_object = new SensorDeviceConfigDownloadForm();
    $form_state = new FormState();
    $form = $form_object->buildForm([], $form_state, $this->device);
    $form_object->submitForm($form, $form_state);

    $ingest = new SensorIngestController();
    $request = Request::create('/hivelog/api/sensor-readings', 'POST', [], [], [], [], json_encode([
      'metric' => 'weight_kg',
      'value' => 1,
      'recorded' => time(),
    ]));
    $request->headers->set('Authorization', 'Bearer ' . $stale_token);

    $response = $ingest->ingest($request);
    $this->assertEquals(401, $response->getStatusCode());
  }

  /**
   * Tests the new token from the form works against the ingestion endpoint.
   */
  public function testRegeneratedTokenWorksAgainstIngestEndpoint(): void {
    $this->setCurrentUser($this->owner);

    $form_object = new SensorDeviceConfigDownloadForm();
    $form_state = new FormState();
    $form = $form_object->buildForm([], $form_state, $this->device);
    $form_object->submitForm($form, $form_state);

    $descriptor = json_decode($form_state->getResponse()->getContent(), TRUE);
    $new_token = $descriptor['auth']['token'];

    $ingest = new SensorIngestController();
    $request = Request::create('/hivelog/api/sensor-readings', 'POST', [], [], [], [], json_encode([
      'metric' => 'weight_kg',
      'value' => 1,
      'recorded' => time(),
    ]));
    $request->headers->set('Authorization', 'Bearer ' . $new_token);

    $response = $ingest->ingest($request);
    $this->assertEquals(201, $response->getStatusCode());
  }

  /**
   * Tests the config route is registered gated by edit-level permission.
   */
  public function testConfigRouteRequiresEditPermission(): void {
    /** @var \Symfony\Component\Routing\RouteProviderInterface $route_provider */
    $route_provider = \Drupal::service('router.route_provider');
    $route = $route_provider->getRouteByName('nanoprobe.sensor_device.config');

    $this->assertEquals('/hivelog/sensor-device/{sensor_device}/config', $route->getPath());
    $this->assertStringContainsString('edit own sensor device', $route->getRequirement('_permission'));
  }

}
