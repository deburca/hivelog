<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveReminder;

use Drupal\KernelTests\KernelTestBase;
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
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_reminder');

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
    $field_definition = \Drupal::entityTypeManager()->getStorage('hive_reminder')
      ->getFieldDefinition('status');
    $allowed = $field_definition->getSetting('allowed_values');

    $this->assertArrayHasKey('pending', $allowed);
    $this->assertArrayHasKey('done', $allowed);
    $this->assertArrayHasKey('dismissed', $allowed);
  }

}
