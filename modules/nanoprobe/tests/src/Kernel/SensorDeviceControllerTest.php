  }

  /**
   * Tests the page renders and includes a download button for an owner.
   *
   * The button uses a `danger` variant (task 0112 follow-up) since
   * downloading a fresh configuration regenerates the device's token,
   * invalidating any previously downloaded one.
   */
  public function testViewRendersForAuthorizedUser(): void {
    $this->setCurrentUser($this->owner);

    $controller = $this->createController();
    $build = $controller->view($this->device);

    $this->assertArrayHasKey('summary', $build);
    // Task 0131: the generic detail-table class is kept alongside the
    // per-entity class.
    $this->assertContains('hivelog-detail-table', $build['summary']['#attributes']['class']);
    $this->assertContains('hivelog-sensor-device-table', $build['summary']['#attributes']['class']);
    $this->assertArrayHasKey('config', $build);
    $this->assertEquals('hivelog:button', $build['config']['download']['#component']);
    $this->assertEquals('danger', $build['config']['download']['#props']['variant']);
    $this->assertStringContainsString(
      '/sensor-device/' . $this->device->id() . '/config',
      $build['config']['download']['#props']['url']
    );
  }

  /**
   * Tests the download action is hidden for a viewer without update access.
   */
  public function testDownloadActionHiddenWithoutUpdateAccess(): void {
    $this->setCurrentUser($this->viewOnlyMember);
    $controller = $this->createController();
    $build = $controller->view($this->device);

    $this->assertArrayNotHasKey('config', $build);
  }

  /**
   * Tests the page-owned Edit/Delete button group appears with access.
   *
   * Task 0118 — this page previously had no page-owned actions and no
   * local task tabs; it was only editable from the collection row.
   * `checkApiaryOwnerDeleteAccess()` requires the apiary owner AND the
   * `delete own sensor device` permission — `$this->owner`'s
   * `beekeeper` role grants `edit own` but not `delete own`, so this
   * needs its own device owned by a user with both.
   */
  public function testActionsAppearWithUpdateAndDeleteAccess(): void {
    $role = Role::create(['id' => 'beekeeper_full', 'label' => 'Beekeeper (full)']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('view own sensor device');
    $role->grantPermission('edit own sensor device');
    $role->grantPermission('delete own sensor device');
    $role->save();

    $full_owner = User::create(['name' => 'full_owner', 'mail' => 'full_owner@example.com']);
    $full_owner->addRole('beekeeper_full');
    $full_owner->save();

    $apiary = Apiary::create(['name' => 'Full Access Apiary', 'uid' => $full_owner->id(), 'visibility' => 'private']);
    $apiary->save();
    $device = SensorDevice::create([
      'label' => 'Full Access Device',
      'apiary' => $apiary->id(),
      'scope' => 'apiary',
    ]);
    $device->save();

    $this->setCurrentUser($full_owner);
    $controller = $this->createController();
    $build = $controller->view($device);

    $this->assertEquals('hivelog:button-group', $build['actions']['#component']);
    $labels = array_column($build['actions']['#props']['buttons'], 'label');
    $this->assertContains('Edit', $labels);
    $this->assertContains('Delete', $labels);
  }

  /**
   * Tests the page-owned Edit/Delete button group is absent without access.
   */
  public function testActionsAbsentWithoutUpdateOrDeleteAccess(): void {
    $this->setCurrentUser($this->viewOnlyMember);
    $controller = $this->createController();
    $build = $controller->view($this->device);

    $this->assertSame([], $build['actions']);
  }

  /**
   * Tests an outsider cannot view the page at all.
   */
  public function testViewDeniedForOutsider(): void {
    $this->setCurrentUser($this->outsider);

    $controller = $this->createController();
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

    // The `view()` page uses $this->dateFormatter() to format dates.
    $this->mockChartBuilder = $this->createStub(
      SensorTrendChartBuilder::class
    );
  }

  /**
   * Creates a SensorDeviceController with mock dependencies.
   */
  protected function createController(): SensorDeviceController {
    $time = $this->getMockBuilder(TimeInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $request_stack = new RequestStack();
    $date_formatter = $this->getMockBuilder(DateFormatterInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    return new SensorDeviceController(
      $time,
      $request_stack,
      $this->mockChartBuilder,
      $date_formatter,
    );
  }
