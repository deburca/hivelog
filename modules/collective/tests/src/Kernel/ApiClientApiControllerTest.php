<?php

declare(strict_types=1);

namespace Drupal\Tests\collective\Kernel;

use Drupal\collective\Controller\ApiClientApiController;
use Drupal\collective\Entity\ApiClient;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\Queen;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the API Client context-read endpoint (task 0090, rescoped 0101).
 *
 * The insight write-back endpoint this test class originally also
 * covered was retired in task 0101 per
 * [[0100-nexus-in-process-ai-synthesis]] — those assertions were removed
 * outright, not adapted, since the route no longer exists.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ApiClientApiControllerTest extends KernelTestBase {

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
    'collective',
  ];

  /**
   * An apiary with AI insights enabled.
   */
  protected Apiary $optedInApiary;

  /**
   * A hive under `$optedInApiary`.
   */
  protected Hive $optedInHive;

  /**
   * An apiary WITHOUT AI insights enabled.
   */
  protected Apiary $optedOutApiary;

  /**
   * A hive under `$optedOutApiary`.
   */
  protected Hive $optedOutHive;

  /**
   * A test API client.
   */
  protected ApiClient $client;

  /**
   * The plaintext token for `$client`.
   */
  protected string $token;

  /**
   * The controller under test.
   */
  protected ApiClientApiController $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_inspection');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('api_client');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system']);
    \Drupal::service('router.builder')->rebuild();

    $this->optedInApiary = Apiary::create(['name' => 'Opted In', 'ai_insights_enabled' => TRUE]);
    $this->optedInApiary->save();
    $this->optedInHive = Hive::create([
      'name' => 'Opted In Hive',
      'apiary' => $this->optedInApiary->id(),
      'status' => 'active',
    ]);
    $this->optedInHive->save();

    $this->optedOutApiary = Apiary::create(['name' => 'Opted Out', 'ai_insights_enabled' => FALSE]);
    $this->optedOutApiary->save();
    $this->optedOutHive = Hive::create([
      'name' => 'Opted Out Hive',
      'apiary' => $this->optedOutApiary->id(),
      'status' => 'active',
    ]);
    $this->optedOutHive->save();

    $this->client = ApiClient::create(['label' => 'Test Client']);
    $this->client->save();
    $this->token = $this->client->getPlainTextToken();

    $this->controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiClientApiController::class);
  }

  /**
   * Builds a GET request with an optional Authorization header.
   */
  protected function buildGetRequest(?string $token, array $query = []): Request {
    $request = Request::create('/hivelog/api/collective/context', 'GET', $query);
    if ($token !== NULL) {
      $request->headers->set('Authorization', 'Bearer ' . $token);
    }
    return $request;
  }

  /**
   * Tests only hives under an opted-in apiary appear in contexts().
   */
  public function testContextsReturnsOnlyOptedInHives(): void {
    $response = $this->controller->contexts($this->buildGetRequest($this->token));
    $this->assertEquals(200, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $hive_ids = array_column($data, 'hive_id');

    $this->assertContains((int) $this->optedInHive->id(), $hive_ids);
    $this->assertNotContains((int) $this->optedOutHive->id(), $hive_ids);
  }

  /**
   * Tests the context object's exact minimised shape.
   *
   * Asserts absent fields genuinely stay absent (no hive/apiary name, no
   * location, no free-text notes), not just that present fields are
   * correct.
   */
  public function testContextShapeIsExactlyMinimised(): void {
    Queen::create([
      'name' => 'Should Never Appear',
      'hive' => $this->optedInHive->id(),
      'status' => 'active',
      'queen_year' => 2025,
    ])->save();

    HiveInspection::create([
      'hive' => $this->optedInHive->id(),
      'inspection_date' => date('Y-m-d\TH:i:s'),
      'population' => 'strong',
      'brood_pattern' => 'good',
      'honey_stores' => 'abundant',
      'pollen_stores' => 'adequate',
      'queen_seen' => TRUE,
      'queen_cells' => FALSE,
      'varroa_check' => FALSE,
      'supers' => 1,
      'weight' => 41.2,
      'notes' => 'This free text must never leave the building.',
      'action_taken' => 'This free text must never leave the building either.',
    ])->save();

    $response = $this->controller->contexts($this->buildGetRequest($this->token, ['hive' => $this->optedInHive->id()]));
    $data = json_decode($response->getContent(), TRUE);
    $this->assertCount(1, $data);
    $context = $data[0];

    // Exactly the top-level keys ADR-0088 §2 specifies — nothing extra.
    $this->assertEqualsCanonicalizing(
      ['hive_id', 'current_week', 'calendar_status', 'inspections', 'queen'],
      array_keys($context)
    );

    $this->assertEquals((int) $this->optedInHive->id(), $context['hive_id']);
    $this->assertIsInt($context['current_week']);

    $inspection = $context['inspections'][0];
    $this->assertEqualsCanonicalizing(
      [
        'week', 'population', 'brood_pattern', 'honey_stores', 'pollen_stores',
        'queen_seen', 'queen_cells', 'varroa_check', 'varroa_count',
        'disease_signs', 'supers', 'weight_kg',
      ],
      array_keys($inspection)
    );
    $this->assertEquals('strong', $inspection['population']);
    $this->assertEquals(41.2, $inspection['weight_kg']);
    // The free-text fields must not appear anywhere in the payload.
    $this->assertStringNotContainsString('never leave the building', json_encode($context));
    // Neither must the hive's own name, or the queen's.
    $this->assertStringNotContainsString('Opted In Hive', json_encode($context));
    $this->assertStringNotContainsString('Should Never Appear', json_encode($context));

    $this->assertEqualsCanonicalizing(['queen_year', 'status'], array_keys($context['queen']));
    $this->assertEquals(2025, $context['queen']['queen_year']);
    $this->assertEquals('active', $context['queen']['status']);
  }

  /**
   * Tests `?hive=` narrows the response to that one hive.
   */
  public function testHiveFilterBehaves(): void {
    $second_hive = Hive::create(['name' => 'Second Hive', 'apiary' => $this->optedInApiary->id(), 'status' => 'active']);
    $second_hive->save();

    $response = $this->controller->contexts($this->buildGetRequest($this->token, ['hive' => $this->optedInHive->id()]));
    $data = json_decode($response->getContent(), TRUE);

    $this->assertCount(1, $data);
    $this->assertEquals((int) $this->optedInHive->id(), $data[0]['hive_id']);
  }

  /**
   * Tests filtering to a hive under a non-opted-in apiary yields nothing.
   *
   * Not an error — the response shape must not let a caller distinguish
   * "wrong id" from "not opted in".
   */
  public function testHiveFilterForNonOptedInHiveReturnsEmpty(): void {
    $response = $this->controller->contexts($this->buildGetRequest($this->token, ['hive' => $this->optedOutHive->id()]));
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertSame([], json_decode($response->getContent(), TRUE));
  }

  /**
   * Tests calendar_status correctly buckets due/overdue/recently-done actions.
   */
  public function testCalendarStatusBucketsActions(): void {
    $current_week = (int) date('W');
    $year = (int) date('Y');

    $due_action = CalendarAction::create([
      'apiary' => $this->optedInApiary->id(),
      'title' => 'Weekly Swarm Checks',
      'description' => 'Check weekly.',
      'week_start' => $current_week,
      'scope' => 'hive',
    ]);
    $due_action->save();

    $overdue_action = CalendarAction::create([
      'apiary' => $this->optedInApiary->id(),
      'title' => 'Overdue Task',
      'description' => 'Should have happened.',
      'week_start' => max($current_week - 3, 1),
      'week_end' => max($current_week - 2, 1),
      'scope' => 'hive',
    ]);
    $overdue_action->save();

    $done_action = CalendarAction::create([
      'apiary' => $this->optedInApiary->id(),
      'title' => 'Recently Completed Task',
      'description' => 'Already done.',
      'week_start' => max($current_week - 1, 1),
      'scope' => 'hive',
    ]);
    $done_action->save();
    HiveActionLog::create([
      'hive' => $this->optedInHive->id(),
      'calendar_action' => $done_action->id(),
      'year' => $year,
      'status' => 'done',
    ])->save();

    $response = $this->controller->contexts($this->buildGetRequest($this->token, ['hive' => $this->optedInHive->id()]));
    $data = json_decode($response->getContent(), TRUE);
    $calendar_status = $data[0]['calendar_status'];

    $this->assertContains('Weekly Swarm Checks', $calendar_status['due_this_week']);
    $this->assertContains('Overdue Task', $calendar_status['overdue']);
    $this->assertContains('Recently Completed Task', $calendar_status['recently_done']);
  }

  /**
   * Tests a successful read updates `ApiClient.last_run`.
   *
   * The write-back endpoint used to be the thing that updated this;
   * with that endpoint gone, a successful context read is now the only
   * signal that a credential is actually in use.
   */
  public function testSuccessfulReadUpdatesLastRun(): void {
    $this->assertTrue($this->client->get('last_run')->isEmpty());

    $this->controller->contexts($this->buildGetRequest($this->token));

    $reloaded_client = ApiClient::load($this->client->id());
    $this->assertNotEmpty($reloaded_client->get('last_run')->value);
  }

  /**
   * Tests a missing token is rejected with 401.
   */
  public function testMissingTokenRejected(): void {
    $this->assertEquals(401, $this->controller->contexts($this->buildGetRequest(NULL))->getStatusCode());
  }

  /**
   * Tests an unrecognised token is rejected with 401.
   */
  public function testUnrecognisedTokenRejected(): void {
    $this->assertEquals(401, $this->controller->contexts($this->buildGetRequest('not-a-real-token'))->getStatusCode());
  }

  /**
   * Tests a disabled client is rejected with 403.
   */
  public function testDisabledClientRejected(): void {
    $this->client->set('enabled', FALSE);
    $this->client->save();

    $this->assertEquals(403, $this->controller->contexts($this->buildGetRequest($this->token))->getStatusCode());
  }

  /**
   * Tests the context route is registered with no permission requirement.
   */
  public function testRouteIsRegisteredWithNoPermissionGate(): void {
    /** @var \Symfony\Component\Routing\RouteProviderInterface $route_provider */
    $route_provider = \Drupal::service('router.route_provider');

    $contexts_route = $route_provider->getRouteByName('collective.api_client.context');
    $this->assertEquals('/hivelog/api/collective/context', $contexts_route->getPath());
    $this->assertEquals(['GET'], $contexts_route->getMethods());
    $this->assertEquals('TRUE', $contexts_route->getRequirement('_access'));
  }

}
