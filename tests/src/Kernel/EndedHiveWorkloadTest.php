<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\hivelog\Controller\ApiaryController;
use Drupal\hivelog\Controller\HiveController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Form\HivelogHiveFilterForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests that a hive without a colony leaves the seasonal workload.
 *
 * Task 0269: only an active hive raises seasonal alerts, counts as open work
 * and is listed by default; a dead, sold or merged hive also retires its
 * queen.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class EndedHiveWorkloadTest extends KernelTestBase {

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
   * A test hive.
   */
  protected Hive $hive;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('apiary_membership');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_inspection');
    // Task 0163: HiveController::view() now unconditionally queries
    // hive_component for every hive page render.
    $this->installEntitySchema('hive_component');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('calendar_action_item_requirement');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('inventory_usage');
    $this->installEntitySchema('product');
    $this->installEntitySchema('calendar_action_product_yield');
    $this->installSchema('file', ['file_usage']);

    $user = User::create([
      'name' => 'tester',
      'mail' => 'tester@example.com',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $this->apiary = Apiary::create(['name' => 'Workload Apiary']);
    $this->apiary->save();

    $this->hive = Hive::create([
      'name' => 'Living Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hive->save();

    // A new apiary is given the starter calendar; these tests count actions,
    // so they start from none.
    $actions = \Drupal::entityTypeManager()->getStorage('calendar_action');
    $actions->delete($actions->loadMultiple());
  }

  /**
   * Pushes a request with the given query parameters onto the request stack.
   */
  protected function pushRequestWithQuery(array $query, string $path = '/hivelog/hive/1'): void {
    $request = Request::create($path, 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
  }

  /**
   * Creates a second hive in the test apiary.
   */
  protected function createHive(string $name, string $status): Hive {
    $hive = Hive::create([
      'name' => $name,
      'apiary' => $this->apiary->id(),
      'status' => $status,
    ]);
    $hive->save();
    return $hive;
  }

  /**
   * Creates an enabled hive-scoped action that was due in week 1.
   */
  protected function createHiveAction(string $title = 'Winter check'): CalendarAction {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => $title,
      'description' => 'Desc.',
      'week_start' => 1,
      'scope' => 'hive',
    ]);
    $action->save();
    return $action;
  }

  /**
   * Seasonal alerts and the open counts are for active hives only.
   */
  public function testOnlyActiveHivesRaiseSeasonalAlerts(): void {
    $this->createHiveAction();
    foreach (['inactive', 'dead', 'sold', 'merged'] as $status) {
      $this->createHive('Gone ' . $status, $status);
    }

    $result = \Drupal::service('hivelog.alert_collector')->collectSeasonalAlerts(
      [$this->apiary->id() => $this->apiary],
      (int) date('Y'),
      30,
      new CacheableMetadata(),
    );

    $this->assertSame(1, $result['open_total']);
    $this->assertSame(1, $result['open_overdue']);
    $this->assertCount(1, $result['alerts']);
    $this->assertSame($this->hive->id(), $result['alerts'][0]['subject']['hive']->id());
  }

  /**
   * A hive that is brought back into use is counted again.
   */
  public function testReactivatedHiveIsCountedAgain(): void {
    $this->createHiveAction();
    $hive = $this->createHive('Stored box', 'inactive');
    $collect = fn() => \Drupal::service('hivelog.alert_collector')->collectSeasonalAlerts(
      [$this->apiary->id() => $this->apiary], (int) date('Y'), 30, new CacheableMetadata(),
    )['open_total'];

    $this->assertSame(1, $collect());
    $hive->set('status', 'active')->save();
    $this->assertSame(2, $collect());
  }

  /**
   * Ending a hive retires its queen, who stays in the hive's history.
   */
  public function testEndedHiveRetiresItsQueen(): void {
    foreach (Hive::ENDED_STATUSES as $status) {
      $hive = $this->createHive('Hive to be ' . $status, 'active');
      $queen = Queen::create(['name' => 'Queen of ' . $status, 'hive' => $hive->id(), 'status' => 'active']);
      $queen->save();
      $this->assertNotNull($hive->getActiveQueen());

      $hive->set('status', $status)->save();

      $queen = Queen::load($queen->id());
      $this->assertSame('inactive', $queen->get('status')->value, $status);
      $this->assertEquals($hive->id(), $queen->get('hive')->target_id, 'The retired queen stays linked to the hive.');
      $this->assertNull($hive->getActiveQueen());
      $this->assertCount(1, $hive->getQueens());
    }
  }

  /**
   * An inactive hive is set aside, not ended: its queen is left alone.
   */
  public function testInactiveHiveKeepsItsQueen(): void {
    $queen = Queen::create(['name' => 'Resting queen', 'hive' => $this->hive->id(), 'status' => 'active']);
    $queen->save();

    $this->hive->set('status', 'inactive')->save();

    $this->assertFalse($this->hive->isActive());
    $this->assertFalse($this->hive->hasEnded());
    $this->assertSame('active', Queen::load($queen->id())->get('status')->value);
  }

  /**
   * The checklist of a hive that is not active is its reported rows only.
   */
  public function testChecklistOfEndedHiveIsHistoryOnly(): void {
    $done = $this->createHiveAction('Reported action');
    $this->createHiveAction('Never reported action');
    HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $done->id(),
      'year' => (int) date('Y'),
      'status' => 'done',
    ])->save();

    $builder = \Drupal::service('hivelog.calendar_checklist_builder');
    $this->assertCount(2, $builder->buildForHive($this->hive, (int) date('Y'), 'all')['rows']);

    $this->hive->set('status', 'dead')->save();

    $all = $builder->buildForHive($this->hive, (int) date('Y'), 'all');
    $this->assertSame([(int) $done->id()], array_map('intval', array_keys($all['rows'])));
    $this->assertSame([], $builder->buildForHive($this->hive, (int) date('Y'), 'pending')['rows']);
  }

  /**
   * The hive page of an ended hive shows its history and says why.
   */
  public function testHivePageOfEndedHive(): void {
    $done = $this->createHiveAction('Reported action');
    $this->createHiveAction('Never reported action');
    HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $done->id(),
      'year' => (int) date('Y'),
      'status' => 'done',
    ])->save();
    $this->hive->set('status', 'sold')->save();

    $this->pushRequestWithQuery([]);
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($this->hive);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('This hive is not active', $html);
    $this->assertStringContainsString('Reported action', $html);
    $this->assertStringNotContainsString('Never reported action', $html);
  }

  /**
   * The hive filter lists active hives unless a status is asked for.
   */
  public function testHiveFilterDefaultsToActive(): void {
    $dead = $this->createHive('Dead hive', 'dead');
    $ids = function (array $query): array {
      $filters = HivelogHiveFilterForm::extract(Request::create('/hivelog/hives', 'GET', $query));
      $entity_query = \Drupal::entityTypeManager()->getStorage('hive')->getQuery()->accessCheck(FALSE)->sort('id');
      HivelogHiveFilterForm::apply($entity_query, $filters);
      return array_map('intval', array_values($entity_query->execute()));
    };

    $this->assertSame([(int) $this->hive->id()], $ids([]));
    $this->assertSame([(int) $this->hive->id()], $ids(['status' => 'active']));
    $this->assertSame([(int) $dead->id()], $ids(['status' => 'dead']));
    $this->assertSame([(int) $this->hive->id(), (int) $dead->id()], $ids(['status' => HivelogHiveFilterForm::STATUS_ANY]));
    // The default is not a filter the user set.
    $this->assertSame([], HivelogHiveFilterForm::extract(Request::create('/hivelog/hives', 'GET', ['status' => 'active'])));
  }

  /**
   * An apiary whose hives have all ended does not claim to have none.
   */
  public function testApiaryPageSaysWhenNoHiveIsActive(): void {
    $this->hive->set('status', 'dead')->save();

    $this->pushRequestWithQuery([], '/hivelog/apiary/1');
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($this->apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('This apiary has no active hives', $html);
    $this->assertStringNotContainsString('Living Hive', $html);

    $this->pushRequestWithQuery(['status' => HivelogHiveFilterForm::STATUS_ANY], '/hivelog/apiary/1');
    $build = $controller->view($this->apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('Living Hive', $html);
  }

}
