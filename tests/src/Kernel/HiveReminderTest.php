<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveReminder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the HiveReminder entity and its forms.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveReminderTest extends KernelTestBase {

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
   * A test apiary.
   *
   * @var \Drupal\hivelog\Entity\Apiary|null
   */
  protected $apiary = NULL;

  /**
   * A test hive.
   *
   * @var \Drupal\hivelog\Entity\Hive|null
   */
  protected $hive = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // A new apiary is given the starter calendar and its owner's membership,
    // so those tables are installed too, as in the other apiary tests.
    foreach ([
      'user', 'file', 'apiary', 'apiary_membership', 'hive', 'hive_reminder',
      'calendar_action', 'calendar_action_item_requirement', 'calendar_action_product_yield',
      'inventory_item', 'product',
    ] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);

    // Create an apiary.
    $this->apiary = Apiary::create([
      'name' => 'Test Apiary',
      'status' => 'active',
    ]);
    $this->apiary->save();

    // Create a hive.
    $this->hive = Hive::create([
      'name' => 'Test Hive',
      'type' => 'langstroth',
      'material' => 'wood',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hive->save();

    // Create a test user.
    User::create([
      'name' => 'test-user',
      'mail' => 'test@example.com',
    ])->save();
  }

  /**
   * Tests creating and saving a HiveReminder entity.
   */
  public function testCreateHiveReminder(): void {
    $reminder = HiveReminder::create([
      'title' => 'Check queen release',
      'notes' => 'Make sure she\'s out.',
      'hive' => $this->hive->id(),
      'due_date' => '2026-05-15',
    ]);

    // Test defaults.
    $this->assertEquals('pending', $reminder->get('status')->value);

    $reminder->save();
    $this->assertNotEmpty($reminder->id());

    // Reload and verify.
    $loaded = HiveReminder::load($reminder->id());
    $this->assertEquals('Check queen release', $loaded->get('title')->value);
    $this->assertEquals('2026-05-15', $loaded->get('due_date')->value);
  }

  /**
   * Tests updating a HiveReminder's status.
   */
  public function testUpdateHiveReminderStatus(): void {
    $reminder = HiveReminder::create([
      'title' => 'Check queen release',
      'hive' => $this->hive->id(),
      'due_date' => '2026-05-15',
    ]);
    $reminder->save();

    // Mark as done.
    $reminder->set('status', 'done')->save();
    $loaded = HiveReminder::load($reminder->id());
    $this->assertEquals('done', $loaded->get('status')->value);

    // Undo back to pending.
    $reminder->set('status', 'pending')->save();
    $loaded = HiveReminder::load($reminder->id());
    $this->assertEquals('pending', $loaded->get('status')->value);
  }

  /**
   * Tests the allowed values for status.
   */
  public function testStatusAllowedValues(): void {
    $definitions = \Drupal::service('entity_field.manager')->getBaseFieldDefinitions('hive_reminder');
    $allowed = $definitions['status']->getSetting('allowed_values');

    $this->assertArrayHasKey('pending', $allowed);
    $this->assertArrayHasKey('done', $allowed);
    $this->assertArrayHasKey('dismissed', $allowed);
  }

  /**
   * The entity type is declared in the namespace its file's path implies.
   *
   * Drupal finds a class by its namespace, so a class declared in
   * `src/Entity/` under another namespace cannot be autoloaded, and every
   * kernel test that touches the entity type manager then fails.
   */
  public function testEntityTypeAndHandlersLoad(): void {
    $this->assertSame('Drupal\\hivelog\\Entity\\HiveReminder', HiveReminder::class);

    $type = \Drupal::entityTypeManager()->getDefinition('hive_reminder');
    $this->assertSame(HiveReminder::class, $type->getClass());
    // Every handler it names is a class that exists and loads.
    foreach (['list_builder', 'access'] as $handler) {
      $this->assertTrue(class_exists($type->getHandlerClass($handler)), $handler);
    }
    foreach (['default', 'add', 'edit', 'delete'] as $operation) {
      $this->assertTrue(class_exists($type->getFormClass($operation)), $operation);
    }
    $this->assertInstanceOf(HiveReminder::class, \Drupal::entityTypeManager()->getStorage('hive_reminder')->create([]));

    // Edit and delete routes and a collection, but no canonical page: a
    // `canonical` template is read as a promise that its route exists.
    $this->assertFalse($type->hasLinkTemplate('canonical'));
    $this->assertTrue($type->hasLinkTemplate('edit-form'));
    $this->assertTrue($type->hasLinkTemplate('delete-form'));
  }

  /**
   * The due date is a date, stored as ISO text and read back unchanged.
   */
  public function testDueDateIsDateField(): void {
    $definitions = \Drupal::service('entity_field.manager')->getBaseFieldDefinitions('hive_reminder');
    $this->assertSame('datetime', $definitions['due_date']->getType());
    $this->assertSame('date', $definitions['due_date']->getSetting('datetime_type'));
  }

  /**
   * A reminder is judged against its hive's apiary.
   */
  public function testAccessFollowsTheHivesApiary(): void {
    $reminder = HiveReminder::create(['title' => 'Check', 'hive' => $this->hive->id(), 'due_date' => '2026-05-15']);
    $reminder->save();

    $outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $outsider->save();
    $this->assertFalse($reminder->access('update', $outsider));
    $this->assertFalse($reminder->access('delete', $outsider));

    $admin = $this->createUserWithPermission('administer hivelog');
    $this->assertTrue($reminder->access('update', $admin));
  }

  /**
   * Creates a user holding one permission.
   */
  protected function createUserWithPermission(string $permission): User {
    $role = Role::create(['id' => 'reminder_tester', 'label' => 'Reminder tester']);
    $role->grantPermission($permission);
    $role->save();
    $user = User::create(['name' => 'tester', 'mail' => 'tester@example.com']);
    $user->addRole('reminder_tester');
    $user->save();
    return $user;
  }

  /**
   * Row 0274-1: deleting a hive cascades its reminders.
   */
  public function testDeletingHiveCascadesReminders(): void {
    $reminder = HiveReminder::create([
      'hive' => $this->hive->id(),
      'title' => 'Check the feeder',
      'due_date' => date('Y-m-d', strtotime('+3 days')),
    ]);
    $reminder->save();
    $reminder_id = $reminder->id();

    $this->hive->delete();

    $this->assertNull(HiveReminder::load($reminder_id));
  }

}
