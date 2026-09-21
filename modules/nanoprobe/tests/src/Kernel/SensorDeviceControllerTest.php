<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests the Sensor Device canonical page controller.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class SensorDeviceControllerTest extends KernelTestBase {

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
   * A test sensor device, belonging to the apiary's hive.
   */
  protected SensorDevice $device;

  /**
   * The apiary owner (has view + update access to `$device`).
   */
  protected User $owner;

  /**
   * A user with no relationship to the apiary (no access to `$device`).
   */
  protected User $outsider;

  /**
   * An apiary member with view-only access (no edit permission at all).
   */
  protected User $viewOnlyMember;

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

    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('view own sensor device');
    $role->grantPermission('edit own sensor device');
    $role->save();

    $view_only_role = Role::create(['id' => 'beekeeper_view_only', 'label' => 'Beekeeper (view only)']);
    $view_only_role->grantPermission('view own apiary');
    $view_only_role->grantPermission('view own sensor device');
    $view_only_role->save();

    $this->owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $this->owner->addRole('beekeeper');
    $this->owner->save();

    $this->outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $this->outsider->addRole('beekeeper');
    $this->outsider->save();

    $this->viewOnlyMember = User::create(['name' => 'view_only', 'mail' => 'view_only@example.com']);
    $this->viewOnlyMember->addRole('beekeeper_view_only');
    $this->viewOnlyMember->save();

    $this->apiary = Apiary::create([
      'name' => 'Test Apiary',
      'uid' => $this->owner->id(),
      'visibility' => 'private',
      'beekeepers' => [$this->viewOnlyMember->id()],
    ]);
    $this->apiary->save();

    $hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
      'uid' => $this->owner->id(),
    ]);
    $hive->save();

    $this->device = SensorDevice::create([
      'label' => 'VV-01 Scale',
      'apiary' => $this->apiary->id(),
      'hive' => $hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'uid' => $this->owner->id(),
    ]);
    $this->device->save();
  }

  /**
   * Tests the page renders and includes a download link for an owner.
   */
  public function testViewRendersForAuthorizedUser(): void {
    $this->setCurrentUser($this->owner);

    $controller = new SensorDeviceController();
    $build = $controller->view($this->device);

    $this->assertArrayHasKey('summary', $build);
    $this->assertArrayHasKey('config', $build);
    $this->assertEquals('nanoprobe.sensor_device.config', $build['config']['download']['#url']->getRouteName());
  }

  /**
   * Tests the download action is hidden for a viewer without update access.
   */
  public function testDownloadActionHiddenWithoutUpdateAccess(): void {
    $this->setCurrentUser($this->viewOnlyMember);
    $controller = new SensorDeviceController();
    $build = $controller->view($this->device);

    $this->assertArrayNotHasKey('config', $build);
  }

  /**
   * Tests an outsider cannot view the page at all.
   */
  public function testViewDeniedForOutsider(): void {
    $this->setCurrentUser($this->outsider);

    $controller = new SensorDeviceController();
    $this->expectException(AccessDeniedHttpException::class);
    $controller->view($this->device);
  }

  /**
   * Tests the canonical route is registered and resolvable.
   */
  public function testCanonicalRouteIsRegistered(): void {
    /** @var \Symfony\Component\Routing\RouteProviderInterface $route_provider */
    $route_provider = \Drupal::service('router.route_provider');
    $route = $route_provider->getRouteByName('entity.sensor_device.canonical');

    $this->assertEquals('/hivelog/sensor-device/{sensor_device}', $route->getPath());
  }

}
