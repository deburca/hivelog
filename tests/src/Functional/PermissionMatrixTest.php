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
      '/admin/hivelog',
      '/admin/hivelog/hives',
      '/admin/hivelog/inspections',
      '/admin/hivelog/apiary/add',
      '/admin/hivelog/apiary/' . $this->apiary->id(),
      '/admin/hivelog/apiary/' . $this->apiary->id() . '/edit',
      '/admin/hivelog/apiary/' . $this->apiary->id() . '/delete',
      '/admin/hivelog/apiary/' . $this->apiary->id() . '/hive/add',
      '/admin/hivelog/hive/' . $this->hive->id(),
      '/admin/hivelog/hive/' . $this->hive->id() . '/edit',
      '/admin/hivelog/hive/' . $this->hive->id() . '/delete',
      '/admin/hivelog/hive/' . $this->hive->id() . '/inspection/add',
      '/admin/hivelog/inspection/' . $this->inspection->id(),
      '/admin/hivelog/inspection/' . $this->inspection->id() . '/edit',
      '/admin/hivelog/inspection/' . $this->inspection->id() . '/delete',
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
      'view apiary',
      'view hive',
      'view hive inspection',
    ]);
    $this->drupalLogin($viewer);

    // View is allowed on collections and canonicals.
    $this->drupalGet('/admin/hivelog');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('/admin/hivelog/apiary/' . $this->apiary->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('/admin/hivelog/hive/' . $this->hive->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('/admin/hivelog/inspection/' . $this->inspection->id());
    $this->assertSession()->statusCodeEquals(200);

    // Add/edit/delete routes are denied.
    foreach ([
      '/admin/hivelog/apiary/add',
      '/admin/hivelog/apiary/' . $this->apiary->id() . '/edit',
      '/admin/hivelog/apiary/' . $this->apiary->id() . '/delete',
      '/admin/hivelog/apiary/' . $this->apiary->id() . '/hive/add',
      '/admin/hivelog/hive/' . $this->hive->id() . '/edit',
      '/admin/hivelog/hive/' . $this->hive->id() . '/delete',
      '/admin/hivelog/hive/' . $this->hive->id() . '/inspection/add',
      '/admin/hivelog/inspection/' . $this->inspection->id() . '/edit',
      '/admin/hivelog/inspection/' . $this->inspection->id() . '/delete',
    ] as $denied_path) {
      $this->drupalGet($denied_path);
      $this->assertSession()->statusCodeEquals(403);
    }

    // On canonical pages the Edit and Delete tabs must NOT be visible.
    $this->drupalGet('/admin/hivelog/apiary/' . $this->apiary->id());
    $this->assertSession()->linkByHrefNotExists('/admin/hivelog/apiary/' . $this->apiary->id() . '/edit');
    $this->assertSession()->linkByHrefNotExists('/admin/hivelog/apiary/' . $this->apiary->id() . '/delete');
  }

  /**
   * Tests that administer hivelog bypasses every per-operation permission.
   */
  public function testAdministerHivelogBypassesGranularChecks(): void {
    $admin = $this->drupalCreateUser(['administer hivelog']);
    $this->drupalLogin($admin);

    $routes = [
      '/admin/hivelog',
      '/admin/hivelog/hives',
      '/admin/hivelog/inspections',
      '/admin/hivelog/apiary/add',
      '/admin/hivelog/apiary/' . $this->apiary->id(),
      '/admin/hivelog/apiary/' . $this->apiary->id() . '/edit',
      '/admin/hivelog/apiary/' . $this->apiary->id() . '/delete',
      '/admin/hivelog/apiary/' . $this->apiary->id() . '/hive/add',
      '/admin/hivelog/hive/' . $this->hive->id(),
      '/admin/hivelog/hive/' . $this->hive->id() . '/edit',
      '/admin/hivelog/hive/' . $this->hive->id() . '/delete',
      '/admin/hivelog/hive/' . $this->hive->id() . '/inspection/add',
      '/admin/hivelog/inspection/' . $this->inspection->id(),
      '/admin/hivelog/inspection/' . $this->inspection->id() . '/edit',
      '/admin/hivelog/inspection/' . $this->inspection->id() . '/delete',
    ];

    foreach ($routes as $path) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(200);
    }

    // Canonical apiary page exposes Edit and Delete tabs for admins.
    $this->drupalGet('/admin/hivelog/apiary/' . $this->apiary->id());
    $this->assertSession()->linkByHrefExists('/admin/hivelog/apiary/' . $this->apiary->id() . '/edit');
    $this->assertSession()->linkByHrefExists('/admin/hivelog/apiary/' . $this->apiary->id() . '/delete');
  }

  /**
   * Tests that an editor can edit but not delete without delete permission.
   */
  public function testEditOnlyPermission(): void {
    $editor = $this->drupalCreateUser([
      'view hive',
      'edit hive',
    ]);
    $this->drupalLogin($editor);

    $this->drupalGet('/admin/hivelog/hive/' . $this->hive->id());
    $this->assertSession()->statusCodeEquals(200);

    // Edit tab is visible; Delete tab is not.
    $this->assertSession()->linkByHrefExists('/admin/hivelog/hive/' . $this->hive->id() . '/edit');
    $this->assertSession()->linkByHrefNotExists('/admin/hivelog/hive/' . $this->hive->id() . '/delete');

    $this->drupalGet('/admin/hivelog/hive/' . $this->hive->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('/admin/hivelog/hive/' . $this->hive->id() . '/delete');
    $this->assertSession()->statusCodeEquals(403);
  }

}
