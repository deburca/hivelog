<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\HiveController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveReminder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the Reminders section of the hive page (task 0274).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveReminderPageTest extends KernelTestBase {

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
   */
  protected Apiary $apiary;

  /**
   * A hive in the test apiary.
   */
  protected Hive $hive;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    // The tables the hive page reads, as in the other tests that render it.
    foreach ([
      'user', 'file', 'apiary', 'apiary_membership', 'hive', 'hive_inspection', 'hive_component',
      'hive_reminder', 'queen', 'queen_observation', 'calendar_action', 'calendar_action_item_requirement',
      'calendar_action_product_yield', 'hive_action_log', 'apiary_action_log', 'inventory_item',
      'inventory_purchase', 'inventory_usage', 'product', 'harvest_yield',
    ] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);
    \Drupal::service('router.builder')->rebuild();

    // The first user is uid 1, so access passes.
    User::create(['name' => 'root', 'mail' => 'root@example.com'])->save();
    \Drupal::currentUser()->setAccount(User::load(1));

    $this->apiary = Apiary::create(['name' => 'Home Apiary']);
    $this->apiary->save();
    $this->hive = Hive::create(['name' => 'Hive One', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $this->hive->save();
  }

  /**
   * Renders the hive page and returns its build.
   */
  protected function page(): array {
    $request = Request::create('/hivelog/hive/' . $this->hive->id());
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
    return \Drupal::service('class_resolver')->getInstanceFromDefinition(HiveController::class)->view($this->hive);
  }

  /**
   * Creates a reminder due a number of days from today.
   */
  protected function reminder(string $title, int $days): HiveReminder {
    $reminder = HiveReminder::create([
      'hive' => $this->hive->id(),
      'title' => $title,
      'due_date' => date('Y-m-d', strtotime(sprintf('%+d days', $days))),
    ]);
    $reminder->save();
    return $reminder;
  }

  /**
   * The page renders with no reminder, and with due and overdue ones.
   *
   * A reminder due today or earlier used to make the page throw: the section
   * passed an array literal to a renderer method that takes its argument by
   * reference.
   */
  public function testPageRendersWithDueAndOverdueReminders(): void {
    $html = (string) \Drupal::service('renderer')->renderInIsolation($this->page());
    $this->assertStringContainsString('id="reminders"', $html);
    $this->assertStringContainsString('No pending reminders for this hive.', $html);
    $this->assertStringContainsString('Add Reminder', $html);

    $this->reminder('Overdue one', -3);
    $this->reminder('Due today', 0);
    $this->reminder('Next week', 6);
    $build = $this->page();

    $this->assertArrayHasKey('reminders', $build);
    $rows = $build['reminders']['table']['#props']['rows'] ?? [];
    $this->assertCount(3, $rows);
    // Due and overdue ones carry the chip, the one a week away does not.
    $this->assertStringContainsString('hivelog-stat-tile--critical', $rows[0]['cells'][1]);
    $this->assertStringContainsString('hivelog-stat-tile--critical', $rows[1]['cells'][1]);
    $this->assertStringNotContainsString('hivelog-stat-tile--critical', $rows[2]['cells'][1]);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    // The section shows its heading and the reminders, not just a container.
    $this->assertStringContainsString('Reminders', $html);
    $this->assertStringContainsString('Overdue one', $html);
    $this->assertStringContainsString('Next week', $html);
  }

  /**
   * A reminder's title is escaped: the table prints its cells as raw HTML.
   */
  public function testTitleIsEscaped(): void {
    $this->reminder('<script>alert(1)</script>', 1);
    $rows = $this->page()['reminders']['table']['#props']['rows'] ?? [];

    $this->assertNotEmpty($rows);
    $this->assertStringNotContainsString('<script>', $rows[0]['cells'][0]);
    $this->assertStringContainsString('&lt;script&gt;', $rows[0]['cells'][0]);
  }

}
