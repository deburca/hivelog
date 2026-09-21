<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\nanoprobe\Controller\SensorIngestController;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\nanoprobe\Entity\SensorReading;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the sensor reading ingestion endpoint.
 *
 * Calls `SensorIngestController::ingest()` directly with a manually built
 * `Request`, rather than dispatching through the full HTTP kernel/router —
 * this exercises the controller's auth/validation/persistence logic
 * exactly as the route invokes it, without the added complexity of
 * installing routing/path-alias state in a kernel test. Route
 * registration itself (path, method, controller, `_access`) is checked
 * separately in testRouteIsRegisteredWithNoPermissionGate().
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class SensorIngestControllerTest extends KernelTestBase {

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
   * A test apiary.
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
   * The plaintext token for `$device`.
   */
  protected string $token;

  /**
   * The controller under test.
   */
  protected SensorIngestController $controller;

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

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();

    $this->hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hive->save();

    $this->device = SensorDevice::create([
      'label' => 'VV-01 Scale',
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
    ]);
    $this->device->save();
    $this->token = $this->device->getPlainTextToken();

    $this->controller = new SensorIngestController();
  }

  /**
   * Builds a JSON POST request with the given body and optional token.
   */
  protected function buildRequest(mixed $body, ?string $token): Request {
    $request = Request::create(
      '/hivelog/api/sensor-readings',
      'POST',
      [],
      [],
      [],
      [],
      is_string($body) ? $body : json_encode($body)
    );
    if ($token !== NULL) {
      $request->headers->set('Authorization', 'Bearer ' . $token);
    }
    return $request;
  }

  /**
   * Tests a single valid reading is accepted and persisted.
   */
  public function testSingleReadingAccepted(): void {
    $request = $this->buildRequest([
      'metric' => 'weight_kg',
      'value' => 42.7,
      'recorded' => '2026-09-21T10:15:00Z',
    ], $this->token);

    $response = $this->controller->ingest($request);
    $this->assertEquals(201, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertCount(1, $data['created']);

    $reading = SensorReading::load($data['created'][0]);
    $this->assertEquals('weight_kg', $reading->get('metric')->value);
    $this->assertEquals(42.7, (float) $reading->get('value')->value);
    $this->assertEquals($this->device->id(), $reading->get('sensor_device')->target_id);
  }

  /**
   * Tests a valid batch (JSON array) is accepted in full.
   */
  public function testBatchAccepted(): void {
    $request = $this->buildRequest([
      ['metric' => 'weight_kg', 'value' => 42.7, 'recorded' => '2026-09-21T10:15:00Z'],
      ['metric' => 'temp_internal_c', 'value' => 34.8, 'recorded' => '2026-09-21T10:15:00Z'],
    ], $this->token);

    $response = $this->controller->ingest($request);
    $this->assertEquals(201, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertCount(2, $data['created']);
  }

  /**
   * Tests a request with no Authorization header is rejected.
   */
  public function testMissingTokenRejected(): void {
    $request = $this->buildRequest(['metric' => 'weight_kg', 'value' => 1, 'recorded' => time()], NULL);
    $response = $this->controller->ingest($request);
    $this->assertEquals(401, $response->getStatusCode());
  }

  /**
   * Tests a request with a token matching no device is rejected.
   */
  public function testUnrecognisedTokenRejected(): void {
    $request = $this->buildRequest(['metric' => 'weight_kg', 'value' => 1, 'recorded' => time()], 'not-a-real-token');
    $response = $this->controller->ingest($request);
    $this->assertEquals(401, $response->getStatusCode());
  }

  /**
   * Tests a token matching a disabled device is rejected with 403, not 401.
   */
  public function testDisabledDeviceRejected(): void {
    $this->device->set('enabled', FALSE);
    $this->device->save();

    $request = $this->buildRequest(['metric' => 'weight_kg', 'value' => 1, 'recorded' => time()], $this->token);
    $response = $this->controller->ingest($request);
    $this->assertEquals(403, $response->getStatusCode());
  }

  /**
   * Tests an unknown metric is rejected with 422, nothing written.
   */
  public function testUnknownMetricRejected(): void {
    $request = $this->buildRequest([
      'metric' => 'not_a_real_metric',
      'value' => 1,
      'recorded' => time(),
    ], $this->token);

    $response = $this->controller->ingest($request);
    $this->assertEquals(422, $response->getStatusCode());
    $this->assertEmpty($this->loadAllReadings());
  }

  /**
   * Tests a non-numeric value is rejected with 422.
   *
   * True NaN/Infinity cannot be transmitted as valid JSON at all (they are
   * not legal JSON literals), so the realistic "non-finite" case reaching
   * this controller is a value that isn't a number to begin with.
   */
  public function testNonNumericValueRejected(): void {
    $request = $this->buildRequest([
      'metric' => 'weight_kg',
      'value' => 'not-a-number',
      'recorded' => time(),
    ], $this->token);

    $response = $this->controller->ingest($request);
    $this->assertEquals(422, $response->getStatusCode());
    $this->assertEmpty($this->loadAllReadings());
  }

  /**
   * Tests an unparseable `recorded` value is rejected with 422.
   */
  public function testUnparseableRecordedRejected(): void {
    $request = $this->buildRequest([
      'metric' => 'weight_kg',
      'value' => 1,
      'recorded' => 'not-a-date',
    ], $this->token);

    $response = $this->controller->ingest($request);
    $this->assertEquals(422, $response->getStatusCode());
    $this->assertEmpty($this->loadAllReadings());
  }

  /**
   * Tests malformed JSON is rejected with 422.
   */
  public function testMalformedJsonRejected(): void {
    $request = $this->buildRequest('{not valid json', $this->token);
    $response = $this->controller->ingest($request);
    $this->assertEquals(422, $response->getStatusCode());
  }

  /**
   * Tests an empty batch array is rejected with 422.
   */
  public function testEmptyBatchRejected(): void {
    $request = $this->buildRequest([], $this->token);
    $response = $this->controller->ingest($request);
    $this->assertEquals(422, $response->getStatusCode());
  }

  /**
   * Tests one bad item in a batch fails the whole batch (all-or-nothing).
   */
  public function testOneBadItemFailsWholeBatch(): void {
    $request = $this->buildRequest([
      ['metric' => 'weight_kg', 'value' => 42.7, 'recorded' => '2026-09-21T10:15:00Z'],
      ['metric' => 'not_a_real_metric', 'value' => 1, 'recorded' => '2026-09-21T10:15:00Z'],
    ], $this->token);

    $response = $this->controller->ingest($request);
    $this->assertEquals(422, $response->getStatusCode());
    // Confirms all-or-nothing: the first (valid) item was NOT persisted
    // just because it appeared before the bad one.
    $this->assertEmpty($this->loadAllReadings());
  }

  /**
   * Tests `SensorDevice.last_seen` is updated only on a successful request.
   */
  public function testLastSeenUpdatedOnSuccess(): void {
    $this->assertTrue($this->device->get('last_seen')->isEmpty());

    $request = $this->buildRequest([
      'metric' => 'weight_kg',
      'value' => 42.7,
      'recorded' => time(),
    ], $this->token);
    $this->controller->ingest($request);

    $reloaded = SensorDevice::load($this->device->id());
    $this->assertNotEmpty($reloaded->get('last_seen')->value);
  }

  /**
   * Tests a created reading resolves to the right apiary/hive for access.
   *
   * Confirms the reading is genuinely readable through the normal
   * apiary-scoped access model afterward, not just successfully written.
   */
  public function testCreatedReadingResolvesToCorrectApiaryAccess(): void {
    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('view own sensor reading');
    $role->save();

    $beekeeper = User::create(['name' => 'beekeeper', 'mail' => 'beekeeper@example.com']);
    $beekeeper->addRole('beekeeper');
    $beekeeper->save();
    $this->apiary->set('uid', $beekeeper->id());
    $this->apiary->save();

    $outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $outsider->addRole('beekeeper');
    $outsider->save();

    $request = $this->buildRequest([
      'metric' => 'weight_kg',
      'value' => 42.7,
      'recorded' => time(),
    ], $this->token);
    $response = $this->controller->ingest($request);
    $data = json_decode($response->getContent(), TRUE);

    $reading = SensorReading::load($data['created'][0]);
    $this->assertTrue($reading->access('view', $beekeeper));
    $this->assertFalse($reading->access('view', $outsider));
  }

  /**
   * Tests the route is registered with no permission requirement.
   *
   * The controller performs its own bearer-token auth (see class docs),
   * so the route must carry `_access: 'TRUE'`, not a `_permission`.
   */
  public function testRouteIsRegisteredWithNoPermissionGate(): void {
    /** @var \Symfony\Component\Routing\RouteProviderInterface $route_provider */
    $route_provider = \Drupal::service('router.route_provider');
    $route = $route_provider->getRouteByName('nanoprobe.sensor_reading.ingest');

    $this->assertEquals('/hivelog/api/sensor-readings', $route->getPath());
    $this->assertEquals(['POST'], $route->getMethods());
    $this->assertEquals('TRUE', $route->getRequirement('_access'));
    $this->assertNull($route->getRequirement('_permission'));
  }

  /**
   * Loads every persisted SensorReading, for asserting none were written.
   */
  protected function loadAllReadings(): array {
    $storage = \Drupal::entityTypeManager()->getStorage('sensor_reading');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    return $storage->loadMultiple($ids);
  }

}
