<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\hivelog\Entity\Apiary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional coverage for the dashboard landing page (task 0056, ADR-0057).
 *
 * The route move: /hivelog is the dashboard; the apiary collection is at
 * /hivelog/apiaries; the breadcrumb "HiveLog" crumb points at the
 * dashboard.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class DashboardTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'hivelog'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('system_breadcrumb_block');

    Apiary::create(['name' => 'Ravnholt Home'])->save();

    $this->drupalLogin($this->drupalCreateUser([
      'view any apiary',
      'view any hive',
      'view any hive inspection',
      'view any calendar action',
    ]));
  }

  /**
   * The /hivelog root serves the dashboard, not the apiary list.
   */
  public function testHivelogRootServesTheDashboard(): void {
    $this->drupalGet('/hivelog');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '.hivelog-dashboard');
    $this->assertSession()->pageTextContains('Needs attention');
    // "View all Queens" is an apiary-list affordance, absent from the dashboard.
    $this->assertSession()->pageTextNotContains('View all Queens');
  }

  /**
   * The apiary collection now lives at `/hivelog/apiaries`.
   */
  public function testApiaryCollectionMoved(): void {
    $this->drupalGet('/hivelog/apiaries');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Ravnholt Home');
    $this->assertSession()->pageTextContains('View all Queens');
    $this->assertSession()->linkByHrefExists('/hivelog/apiary/add');
  }

  /**
   * The breadcrumb "HiveLog" crumb links to the dashboard on both pages.
   */
  public function testBreadcrumbRootIsTheDashboard(): void {
    $this->drupalGet('/hivelog/apiaries');
    $this->assertSession()->elementExists('css', 'nav.breadcrumb a[href="/hivelog"]');
    $this->assertSession()->elementTextContains('css', 'nav.breadcrumb', 'HiveLog');
    $this->assertSession()->elementTextContains('css', 'nav.breadcrumb', 'Apiaries');

    $this->drupalGet('/hivelog');
    $this->assertSession()->elementExists('css', 'nav.breadcrumb');
    $this->assertSession()->elementTextContains('css', 'nav.breadcrumb', 'HiveLog');
  }

}
