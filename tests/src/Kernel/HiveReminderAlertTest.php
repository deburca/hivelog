<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveReminder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the "Needs attention" alerts for hive reminders (task 0274).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveReminderAlertTest extends KernelTestBase {

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
   * An apiary the tests may view.
   */
  protected Apiary $apiary;

  /**
   * A hive in that apiary.
   */
  protected Hive $hive;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach ([
      'user', 'file', 'apiary', 'apiary_membership', 'hive', 'hive_reminder',
      'calendar_action', 'calendar_action_item_requirement', 'calendar_action_product_yield',
      'inventory_item', 'product',
    ] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);
    \Drupal::service('router.builder')->rebuild();

    // The first user is uid 1, who bypasses every access check: burn it.
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();
    $role = Role::create(['id' => 'administrator_like', 'label' => 'Administrator like']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $admin->addRole('administrator_like');
    $admin->save();
    \Drupal::currentUser()->setAccount($admin);

    $this->apiary = Apiary::create(['name' => 'Home Apiary']);
    $this->apiary->save();
    $this->hive = $this->hiveIn($this->apiary, 'Hive One');
  }

  /**
   * Creates a hive in an apiary.
   */
  protected function hiveIn(Apiary $apiary, string $name): Hive {
    $hive = Hive::create(['name' => $name, 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    return $hive;
  }

  /**
   * Creates a reminder due a number of days from today.
   */
  protected function reminder(Hive $hive, string $title, int $days, string $status = 'pending'): HiveReminder {
    $reminder = HiveReminder::create([
      'hive' => $hive->id(),
      'title' => $title,
      'status' => $status,
      'due_date' => date('Y-m-d', strtotime(sprintf('%+d days', $days))),
    ]);
    $reminder->save();
    return $reminder;
  }

  /**
   * Collects the reminder alerts for apiaries, keyed by title.
   *
   * @param \Drupal\hivelog\Entity\Apiary[] $apiaries
   *   The apiaries the user may view.
   *
   * @return array<string, array>
   *   Rows by title.
   */
  protected function collect(array $apiaries): array {
    $keyed = [];
    foreach ($apiaries as $apiary) {
      $keyed[(int) $apiary->id()] = $apiary;
    }
    $rows = [];
    foreach (\Drupal::service('hivelog.alert_collector')->collectReminderAlerts($keyed, new CacheableMetadata()) as $row) {
      $rows[$row['title']] = $row;
    }
    return $rows;
  }

  /**
   * A pending reminder becomes a row by how soon it is due.
   */
  public function testRowsFollowTheDueDate(): void {
    $this->reminder($this->hive, 'Overdue', -2);
    $this->reminder($this->hive, 'Today', 0);
    $this->reminder($this->hive, 'Soon', 2);
    $this->reminder($this->hive, 'This week', 5);
    $this->reminder($this->hive, 'Later', 10);
    $this->reminder($this->hive, 'Finished', -4, 'done');
    $this->reminder($this->hive, 'Dropped', -4, 'dismissed');

    $rows = $this->collect([$this->apiary]);

    $this->assertSame(['Overdue', 'Today', 'Soon', 'This week'], array_keys($rows), 'Later, done and dismissed ones raise nothing.');
    // Title => severity, chip and the action's label.
    $expected = [
      'Overdue' => ['critical', 'Overdue 2 day(s)', 'Mark done'],
      'Today' => ['critical', 'Today', 'Mark done'],
      'Soon' => ['critical', 'Due in 2 day(s)', 'Mark done'],
      'This week' => ['warning', 'Due in 5 day(s)', 'Dismiss'],
    ];
    foreach ($expected as $title => $values) {
      $row = $rows[$title];
      $this->assertSame($values, [$row['severity'], (string) $row['chip'], $row['action_label']], $title);
    }
  }

  /**
   * A row has the shape the dashboard and the app's alerts feed read.
   */
  public function testRowShapeMatchesTheOtherPasses(): void {
    $reminder = $this->reminder($this->hive, 'Check queen release', -1);
    $row = $this->collect([$this->apiary])['Check queen release'];

    $this->assertSame('reminder', $row['kind']);
    // `subject` is an array, as the API's serialiser expects: a string left
    // the app with an alert that named no apiary and no hive.
    $this->assertIsArray($row['subject']);
    $this->assertSame($this->apiary->id(), $row['subject']['apiary']->id());
    $this->assertSame($this->hive->id(), $row['subject']['hive']->id());
    $this->assertStringContainsString((string) $reminder->get('due_date')->value, $row['subject']['detail']);
    $this->assertArrayHasKey('context', $row);
    $this->assertStringContainsString('/hive-reminder/' . $reminder->id() . '/edit', $row['action_url']);
    $this->assertStringContainsString('status=done', $row['action_url']);
  }

  /**
   * Only the apiaries the caller may view are searched.
   */
  public function testOnlyTheGivenApiariesReminders(): void {
    $other = Apiary::create(['name' => 'Someone Else']);
    $other->save();
    $this->reminder($this->hive, 'Mine', 0);
    $this->reminder($this->hiveIn($other, 'Their hive'), 'Theirs', 0);

    $this->assertSame(['Mine'], array_keys($this->collect([$this->apiary])));
    $this->assertSame(['Theirs'], array_keys($this->collect([$other])));
    $this->assertSame(['Mine', 'Theirs'], array_keys($this->collect([$this->apiary, $other])));
    $this->assertSame([], $this->collect([]));
  }

  /**
   * A user without the reminder permissions sees no reminder alert.
   */
  public function testUserWithoutThePermissionSeesNone(): void {
    $this->reminder($this->hive, 'Mine', 0);
    $nobody = User::create(['name' => 'nobody', 'mail' => 'nobody@example.com']);
    $nobody->save();
    \Drupal::currentUser()->setAccount($nobody);

    $this->assertSame([], $this->collect([$this->apiary]));
  }

}
