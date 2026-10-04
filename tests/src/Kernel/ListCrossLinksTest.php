<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\CalendarActionController;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the heading cross-links for the calendar, log and report pages.
 *
 * Task 0172. The Calendar Actions page, the two action-log lists, Products and
 * Inventory Purchases link to one another and to the financial report. Each
 * link is offered only when the viewer can open its target, so nobody is
 * sent into a 403.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ListCrossLinksTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('inventory_usage');
    $this->installEntitySchema('product');
    $this->installSchema('file', ['file_usage']);

    // Consumes uid 1 (which bypasses every access check), so the users made
    // below are genuinely restricted.
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();
  }

  /**
   * Makes a user with the given permissions and makes them the current user.
   *
   * @param string[] $permissions
   *   Permissions to grant.
   */
  protected function actAs(array $permissions): void {
    $role_id = 'role_' . substr(md5(implode(',', $permissions) . random_int(0, PHP_INT_MAX)), 0, 8);
    $role = Role::create(['id' => $role_id, 'label' => $role_id]);
    foreach ($permissions as $permission) {
      $role->grantPermission($permission);
    }
    $role->save();
    $user = User::create(['name' => $role_id, 'mail' => $role_id . '@example.com']);
    $user->addRole($role_id);
    $user->save();
    \Drupal::currentUser()->setAccount($user);
  }

  /**
   * Pushes a request onto the stack, routed through the real router.
   */
  protected function pushRoutedRequest(string $path): void {
    $request = Request::create($path, 'GET');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->attributes->add(\Drupal::service('router')->matchRequest($request));
    \Drupal::service('request_stack')->push($request);
  }

  /**
   * The heading's button labels for a list builder's page, or [] if none.
   *
   * @return string[]
   *   Button labels, in order.
   */
  protected function listHeadingLabels(string $entity_type, string $path): array {
    $this->pushRoutedRequest($path);
    $build = \Drupal::entityTypeManager()->getListBuilder($entity_type)->render();
    return $this->labelsOf($build);
  }

  /**
   * The button labels in a build's heading, or [] if it has none.
   *
   * @return string[]
   *   Button labels, in order.
   */
  protected function labelsOf(array $build): array {
    return array_column($build['heading']['actions']['buttons']['#props']['buttons'] ?? [], 'label');
  }

  /**
   * The Calendar Actions page's build for the current user.
   */
  protected function calendarActionsPage(): array {
    $this->pushRoutedRequest('/hivelog/calendar-actions');
    return \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(CalendarActionController::class)
      ->collection();
  }

  /**
   * The two log lists link to the Calendar Actions and to each other.
   */
  public function testLogListsLinkToCalendarActionsAndSiblingLogs(): void {
    $this->actAs(['administer hivelog']);

    $this->assertSame(
      ['View Calendar Actions', 'View Apiary Logs'],
      $this->listHeadingLabels('hive_action_log', '/hivelog/hive-action-logs')
    );
    $this->assertSame(
      ['View Calendar Actions', 'View Hive Logs'],
      $this->listHeadingLabels('apiary_action_log', '/hivelog/apiary-action-logs')
    );
  }

  /**
   * The log-list links point at the right collections.
   */
  public function testLogListLinksTargetTheRightPages(): void {
    $this->actAs(['administer hivelog']);
    $this->pushRoutedRequest('/hivelog/hive-action-logs');
    $build = \Drupal::entityTypeManager()->getListBuilder('hive_action_log')->render();

    $buttons = $build['heading']['actions']['buttons']['#props']['buttons'];
    $this->assertSame('/hivelog/calendar-actions', $buttons[0]['url']);
    $this->assertSame('/hivelog/apiary-action-logs', $buttons[1]['url']);
  }

  /**
   * A viewer who can see only the log list is offered no dead-end links.
   */
  public function testLogListLinksAreGatedByPermission(): void {
    $this->actAs(['view any hive action log']);
    $this->assertSame([], $this->listHeadingLabels('hive_action_log', '/hivelog/hive-action-logs'));

    // Add one target's permission: only that link appears.
    $this->actAs(['view any hive action log', 'view any calendar action']);
    $this->assertSame(['View Calendar Actions'], $this->listHeadingLabels('hive_action_log', '/hivelog/hive-action-logs'));

    $this->actAs(['view any hive action log', 'view any apiary action log']);
    $this->assertSame(['View Apiary Logs'], $this->listHeadingLabels('hive_action_log', '/hivelog/hive-action-logs'));
  }

  /**
   * The Calendar Actions page links to both log lists, each only if allowed.
   */
  public function testCalendarActionsPageLinksToTheLogs(): void {
    $this->actAs(['administer hivelog']);
    $this->assertSame(['View Hive Logs', 'View Apiary Logs'], $this->labelsOf($this->calendarActionsPage()));

    $this->actAs(['view any calendar action']);
    $build = $this->calendarActionsPage();
    $this->assertArrayNotHasKey('heading', $build, 'No heading at all when no target is reachable.');

    $this->actAs(['view any calendar action', 'view any hive action log']);
    $this->assertSame(['View Hive Logs'], $this->labelsOf($this->calendarActionsPage()));
  }

  /**
   * Products and Purchases offer the financial report only if it is openable.
   *
   * The report needs inventory-item view access, which is a different
   * permission from viewing products or purchases.
   */
  public function testFinancialReportLinkIsGatedByItsOwnPermission(): void {
    $this->actAs(['administer hivelog']);
    $this->assertSame(
      ['Add Product', 'View Financial Report'],
      $this->listHeadingLabels('product', '/hivelog/products')
    );
    $this->assertSame(
      ['Add Purchase', 'View Inventory Items', 'View Financial Report'],
      $this->listHeadingLabels('inventory_purchase', '/hivelog/inventory-purchases')
    );

    $this->actAs(['view any product', 'view any inventory purchase']);
    $this->assertSame(['Add Product'], $this->listHeadingLabels('product', '/hivelog/products'));
    $this->assertSame(
      ['Add Purchase', 'View Inventory Items'],
      $this->listHeadingLabels('inventory_purchase', '/hivelog/inventory-purchases')
    );

    $this->actAs(['view any product', 'view any inventory item']);
    $this->assertSame(
      ['Add Product', 'View Financial Report'],
      $this->listHeadingLabels('product', '/hivelog/products')
    );
  }

  /**
   * The heading varies by permissions.
   *
   * Otherwise one viewer's links could be cached and served to another.
   */
  public function testHeadingVariesByPermissions(): void {
    $this->actAs(['administer hivelog']);
    $this->pushRoutedRequest('/hivelog/hive-action-logs');
    $build = \Drupal::entityTypeManager()->getListBuilder('hive_action_log')->render();
    $this->assertContains('user.permissions', $build['heading']['#cache']['contexts']);

    $page = $this->calendarActionsPage();
    $this->assertContains('user.permissions', $page['heading']['#cache']['contexts']);
  }

}
