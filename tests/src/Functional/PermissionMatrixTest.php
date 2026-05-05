<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional coverage for the HiveLog permission matrix and tab visibility.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class PermissionMatrixTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'hivelog'];

  /**
   * An apiary used as canonical route fixture.
   */
  protected Apiary $apiary;

  /**
   * A hive used as canonical route fixture.
   */
  protected Hive $hive;

  /**
   * An inspection used as canonical route fixture.
   */
  protected HiveInspection $inspection;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Render local task tabs so tab visibility can be asserted.
    $this->drupalPlaceBlock('local_tasks_block');

    $this->apiary = Apiary::create(['name' => 'Matrix Apiary']);
    $this->apiary->save();

    $this->hive = Hive::create([
      'name' => 'Matrix Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hive->save();

    $this->inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-06-15',
    ]);
    $this->inspection->save();
  }

  /**
   * Tests that anonymous users are denied every HiveLog route.
   */
  public function testAnonymousHasNoAccess(): void {
    $paths = [
      '/hivelog',
      '/hivelog/hives',
      '/hivelog/inspections',
      '/hivelog/apiary/add',
      '/hivelog/apiary/' . $this->apiary->id(),
      '/hivelog/apiary/' . $this->apiary->id() . '/edit',
      '/hivelog/apiary/' . $this->apiary->id() . '/delete',
      '/hivelog/apiary/' . $this->apiary->id() . '/hive/add',
      '/hivelog/hive/' . $this->hive->id(),
      '/hivelog/hive/' . $this->hive->id() . '/edit',
      '/hivelog/hive/' . $this->hive->id() . '/delete',
      '/hivelog/hive/' . $this->hive->id() . '/inspection/add',
      '/hivelog/inspection/' . $this->inspection->id(),
      '/hivelog/inspection/' . $this->inspection->id() . '/edit',
      '/hivelog/inspection/' . $this->inspection->id() . '/delete',
    ];

    foreach ($paths as $path) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(403);
    }
  }

  /**
   * Tests a view-only user can view but not add/edit/delete entities.
   */
  public function testViewOnlyPermissions(): void {
    $viewer = $this->drupalCreateUser([
      'view any apiary',
      'view any hive',
      'view any hive inspection',
    ]);
    $this->drupalLogin($viewer);

    // View is allowed on collections and canonicals.
    $this->drupalGet('/hivelog');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('/hivelog/apiary/' . $this->apiary->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('/hivelog/hive/' . $this->hive->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('/hivelog/inspection/' . $this->inspection->id());
    $this->assertSession()->statusCodeEquals(200);

    // Add/edit/delete routes are denied.
    foreach ([
      '/hivelog/apiary/add',
      '/hivelog/apiary/' . $this->apiary->id() . '/edit',
      '/hivelog/apiary/' . $this->apiary->id() . '/delete',
      '/hivelog/apiary/' . $this->apiary->id() . '/hive/add',
      '/hivelog/hive/' . $this->hive->id() . '/edit',
      '/hivelog/hive/' . $this->hive->id() . '/delete',
      '/hivelog/hive/' . $this->hive->id() . '/inspection/add',
      '/hivelog/inspection/' . $this->inspection->id() . '/edit',
      '/hivelog/inspection/' . $this->inspection->id() . '/delete',
    ] as $denied_path) {
      $this->drupalGet($denied_path);
      $this->assertSession()->statusCodeEquals(403);
    }

    // On canonical pages the Edit and Delete tabs must NOT be visible.
    $this->drupalGet('/hivelog/apiary/' . $this->apiary->id());
    $this->assertSession()->linkByHrefNotExists('/hivelog/apiary/' . $this->apiary->id() . '/edit');
    $this->assertSession()->linkByHrefNotExists('/hivelog/apiary/' . $this->apiary->id() . '/delete');
  }

  /**
   * Tests that administer hivelog bypasses every per-operation permission.
   */
  public function testAdministerHivelogBypassesGranularChecks(): void {
    $admin = $this->drupalCreateUser(['administer hivelog']);
    $this->drupalLogin($admin);

    $routes = [
      '/hivelog',
      '/hivelog/hives',
      '/hivelog/inspections',
      '/hivelog/apiary/add',
      '/hivelog/apiary/' . $this->apiary->id(),
      '/hivelog/apiary/' . $this->apiary->id() . '/edit',
      '/hivelog/apiary/' . $this->apiary->id() . '/delete',
      '/hivelog/apiary/' . $this->apiary->id() . '/hive/add',
      '/hivelog/hive/' . $this->hive->id(),
      '/hivelog/hive/' . $this->hive->id() . '/edit',
      '/hivelog/hive/' . $this->hive->id() . '/delete',
      '/hivelog/hive/' . $this->hive->id() . '/inspection/add',
      '/hivelog/inspection/' . $this->inspection->id(),
      '/hivelog/inspection/' . $this->inspection->id() . '/edit',
      '/hivelog/inspection/' . $this->inspection->id() . '/delete',
    ];

    foreach ($routes as $path) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(200);
    }

    // Canonical apiary page exposes Edit and Delete tabs for admins.
    $this->drupalGet('/hivelog/apiary/' . $this->apiary->id());
    $this->assertSession()->linkByHrefExists('/hivelog/apiary/' . $this->apiary->id() . '/edit');
    $this->assertSession()->linkByHrefExists('/hivelog/apiary/' . $this->apiary->id() . '/delete');
  }

  /**
   * Tests that an editor can edit but not delete without delete permission.
   */
  public function testEditOnlyPermission(): void {
    $editor = $this->drupalCreateUser([
      'view any hive',
      'edit any hive',
    ]);
    $this->drupalLogin($editor);

    $this->drupalGet('/hivelog/hive/' . $this->hive->id());
    $this->assertSession()->statusCodeEquals(200);

    // Edit tab is visible; Delete tab is not.
    $this->assertSession()->linkByHrefExists('/hivelog/hive/' . $this->hive->id() . '/edit');
    $this->assertSession()->linkByHrefNotExists('/hivelog/hive/' . $this->hive->id() . '/delete');

    $this->drupalGet('/hivelog/hive/' . $this->hive->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('/hivelog/hive/' . $this->hive->id() . '/delete');
    $this->assertSession()->statusCodeEquals(403);
  }

}
