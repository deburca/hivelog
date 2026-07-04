<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\ApiaryActionLogController;
use Drupal\hivelog\Controller\ApiaryController;
use Drupal\hivelog\Controller\CalendarActionController;
use Drupal\hivelog\Controller\HiveActionLogController;
use Drupal\hivelog\Controller\HiveController;
use Drupal\hivelog\Controller\HiveInspectionController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests explicit cache metadata on HiveLog controller views.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ControllerCacheMetadataTest extends KernelTestBase {

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
    $this->installEntitySchema('hive_inspection');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installSchema('file', ['file_usage']);

    $user = User::create([
      'name' => 'tester',
      'mail' => 'tester@example.com',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    // FormBuilder requires a session on the current request; ensure one is
    // present since the controllers build forms.
    $request = Request::create('/hivelog/apiary/1', 'GET');
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
  }

  /**
   * Tests that the apiary view declares expected cache contexts and tags.
   */
  public function testApiaryViewCacheMetadata(): void {
    $apiary = Apiary::create(['name' => 'Cache Apiary']);
    $apiary->save();

    $hive = Hive::create([
      'name' => 'Cache Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    // The apiary view now also surfaces its apiary-scoped Seasonal Calendar
    // checklist, so its list cache tag and this row's own tag must both be
    // declared. Since task 0027, only `scope = apiary` actions are included
    // in the apiary view's checklist/cache metadata.
    $calendarAction = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Cache Calendar Action',
      'description' => 'Desc.',
      'week_start' => 10,
      'scope' => 'apiary',
    ]);
    $calendarAction->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($apiary);

    $this->assertContains('url.query_args', $build['#cache']['contexts']);
    $this->assertContains('user.permissions', $build['#cache']['contexts']);

    $hive_list_tags = \Drupal::entityTypeManager()
      ->getDefinition('hive')
      ->getListCacheTags();
    foreach ($hive_list_tags as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }

    $calendar_action_list_tags = \Drupal::entityTypeManager()
      ->getDefinition('calendar_action')
      ->getListCacheTags();
    foreach ($calendar_action_list_tags as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }

    foreach ($apiary->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
    foreach ($hive->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
    foreach ($calendarAction->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
  }

  /**
   * Tests that the hive view declares expected cache contexts and tags.
   */
  public function testHiveViewCacheMetadata(): void {
    $apiary = Apiary::create(['name' => 'Cache Apiary']);
    $apiary->save();

    $hive = Hive::create([
      'name' => 'Cache Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    $inspection = HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2024-06-15',
    ]);
    $inspection->save();

    // The hive view now also surfaces the Seasonal Calendar checklist, so
    // both entity types' list cache tags and this row's own tags must be
    // declared.
    $calendarAction = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Cache Calendar Action',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $calendarAction->save();

    // Left at the default "pending" status deliberately, so this row
    // appears in the hive checklist's default (unreported) view and its
    // cache tags are genuinely exercised via that render path, rather
    // than being silently filtered out and never actually added.
    $log = HiveActionLog::create([
      'hive' => $hive->id(),
      'calendar_action' => $calendarAction->id(),
    ]);
    $log->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);

    $this->assertContains('url.query_args', $build['#cache']['contexts']);
    $this->assertContains('user.permissions', $build['#cache']['contexts']);

    $inspection_list_tags = \Drupal::entityTypeManager()
      ->getDefinition('hive_inspection')
      ->getListCacheTags();
    foreach ($inspection_list_tags as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }

    // The hive view now surfaces the active queen, so its list cache tag
    // must be declared so the page invalidates whenever a queen changes.
    $queen_list_tags = \Drupal::entityTypeManager()
      ->getDefinition('queen')
      ->getListCacheTags();
    foreach ($queen_list_tags as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }

    $calendar_action_list_tags = \Drupal::entityTypeManager()
      ->getDefinition('calendar_action')
      ->getListCacheTags();
    foreach ($calendar_action_list_tags as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }

    $hive_action_log_list_tags = \Drupal::entityTypeManager()
      ->getDefinition('hive_action_log')
      ->getListCacheTags();
    foreach ($hive_action_log_list_tags as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }

    foreach ($hive->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
    foreach ($inspection->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
    foreach ($calendarAction->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
    foreach ($log->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
  }

  /**
   * Tests that the hive inspection view declares expected cache metadata.
   */
  public function testHiveInspectionViewCacheMetadata(): void {
    $apiary = Apiary::create(['name' => 'Cache Apiary']);
    $apiary->save();

    $hive = Hive::create([
      'name' => 'Cache Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    $inspection = HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2024-06-15',
    ]);
    $inspection->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveInspectionController::class);
    $build = $controller->view($inspection);

    $this->assertContains('user.permissions', $build['#cache']['contexts']);

    foreach ($inspection->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
  }

  /**
   * Tests that the calendar action view declares expected cache metadata.
   */
  public function testCalendarActionViewCacheMetadata(): void {
    $apiary = Apiary::create(['name' => 'Cache Apiary']);
    $apiary->save();

    $calendarAction = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Cache Calendar Action',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $calendarAction->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(CalendarActionController::class);
    $build = $controller->view($calendarAction);

    $this->assertContains('user.permissions', $build['#cache']['contexts']);

    foreach ($calendarAction->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
  }

  /**
   * Tests that the Full Calendar page declares expected cache metadata.
   *
   * Unlike the plain calendar action view, this page has a GET filter
   * form and pager, so it must also vary by `url.query_args`.
   */
  public function testApiaryFullCalendarCacheMetadata(): void {
    $apiary = Apiary::create(['name' => 'Cache Apiary']);
    $apiary->save();

    $calendarAction = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Cache Full Calendar Action',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $calendarAction->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->fullCalendar($apiary);

    $this->assertContains('url.query_args', $build['#cache']['contexts']);
    $this->assertContains('user.permissions', $build['#cache']['contexts']);

    foreach ($apiary->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
    foreach ($calendarAction->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
  }

  /**
   * Tests that the hive action log view declares expected cache metadata.
   *
   * Including the linked inspection's cache tags when one is set.
   */
  public function testHiveActionLogViewCacheMetadata(): void {
    $apiary = Apiary::create(['name' => 'Cache Apiary']);
    $apiary->save();

    $hive = Hive::create([
      'name' => 'Cache Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    $calendarAction = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Cache Calendar Action',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $calendarAction->save();

    $inspection = HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2024-06-15',
    ]);
    $inspection->save();

    $log = HiveActionLog::create([
      'hive' => $hive->id(),
      'calendar_action' => $calendarAction->id(),
      'status' => 'done',
      'inspection' => $inspection->id(),
    ]);
    $log->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveActionLogController::class);
    $build = $controller->view($log);

    $this->assertContains('user.permissions', $build['#cache']['contexts']);

    foreach ($log->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
    foreach ($inspection->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
  }

  /**
   * Tests that the apiary action log view declares expected cache metadata.
   *
   * Unlike HiveActionLogController, there is no linked inspection to check
   * for — ApiaryActionLog deliberately has no `inspection` field (task
   * 0027).
   */
  public function testApiaryActionLogViewCacheMetadata(): void {
    $apiary = Apiary::create(['name' => 'Cache Apiary']);
    $apiary->save();

    $calendarAction = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Cache Calendar Action',
      'description' => 'Desc.',
      'week_start' => 10,
      'scope' => 'apiary',
    ]);
    $calendarAction->save();

    $log = ApiaryActionLog::create([
      'apiary' => $apiary->id(),
      'calendar_action' => $calendarAction->id(),
      'status' => 'done',
    ]);
    $log->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryActionLogController::class);
    $build = $controller->view($log);

    $this->assertContains('user.permissions', $build['#cache']['contexts']);

    foreach ($log->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
  }

  /**
   * Tests that each controller can be instantiated via the class resolver.
   */
  public function testControllersUseDependencyInjection(): void {
    $class_resolver = \Drupal::service('class_resolver');

    $apiary_controller = $class_resolver->getInstanceFromDefinition(ApiaryController::class);
    $this->assertInstanceOf(ApiaryController::class, $apiary_controller);

    $hive_controller = $class_resolver->getInstanceFromDefinition(HiveController::class);
    $this->assertInstanceOf(HiveController::class, $hive_controller);

    $inspection_controller = $class_resolver->getInstanceFromDefinition(HiveInspectionController::class);
    $this->assertInstanceOf(HiveInspectionController::class, $inspection_controller);

    $calendar_action_controller = $class_resolver->getInstanceFromDefinition(CalendarActionController::class);
    $this->assertInstanceOf(CalendarActionController::class, $calendar_action_controller);

    $hive_action_log_controller = $class_resolver->getInstanceFromDefinition(HiveActionLogController::class);
    $this->assertInstanceOf(HiveActionLogController::class, $hive_action_log_controller);

    $apiary_action_log_controller = $class_resolver->getInstanceFromDefinition(ApiaryActionLogController::class);
    $this->assertInstanceOf(ApiaryActionLogController::class, $apiary_action_log_controller);
  }

}
