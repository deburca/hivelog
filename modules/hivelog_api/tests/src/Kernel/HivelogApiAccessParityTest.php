<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Entity\QueenObservation;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The API must grant nothing the web UI does not (task 0203).
 *
 * `RouteEntityAccessTest` is the web matrix: an owner is allowed, an unrelated
 * user is denied, an administrator is allowed, a beekeeper member is allowed
 * except on apiary-owner-only routes, and anonymous is denied. Here the same
 * question is asked of every exposed resource through the API, and the
 * oracle is the web's own route access (`access_manager->checkNamedRoute()`),
 * not a second hand-written table: if the web UI would let a user do
 * something, the API must, and if not, it must not. Read, create, update and
 * delete are each compared. Collections must never list a record the user
 * could not open.
 *
 * Denied means 403 (or, for a create whose parent the user cannot use, 422
 * from the parent-access constraint). It never means a 5xx.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiAccessParityTest extends HivelogApiKernelTestBase {

  /**
   * The exposed hivelog types, with the web routes and a PATCH attribute.
   *
   * 'add' names the scoped add route and the route parameters it needs, as
   * fixture keys.
   */
  protected const TYPES = [
    'apiary' => ['patch' => 'name', 'add' => ['entity.apiary.add_form', []]],
    'hive' => ['patch' => 'name', 'add' => ['hivelog.hive.add', ['apiary' => 'apiary']]],
    'hive_inspection' => ['patch' => 'notes', 'add' => ['hivelog.inspection.add', ['hive' => 'hive']]],
    'queen' => ['patch' => 'name', 'add' => ['hivelog.queen.add', ['hive' => 'hive']]],
    'queen_observation' => [
      'patch' => 'notes',
      'add' => ['hivelog.queen_observation.add', ['queen' => 'queen']],
    ],
    'calendar_action' => ['patch' => 'title', 'add' => ['hivelog.calendar_action.add', ['apiary' => 'apiary']]],
    'hive_action_log' => [
      'patch' => 'notes',
      'add' => ['hivelog.hive_action_log.add', ['hive' => 'hive', 'calendar_action' => 'calendar_action']],
    ],
    'apiary_action_log' => [
      'patch' => 'notes',
      'add' => ['hivelog.apiary_action_log.add', ['apiary' => 'apiary', 'calendar_action' => 'calendar_action']],
    ],
  ];

  /**
   * Types that can be deleted without a BLOCK child in the way.
   */
  protected const LEAF_TYPES = ['hive_inspection', 'queen_observation', 'hive_action_log', 'apiary_action_log'];

  /**
   * Users keyed by label: owner, outsider, member, any, admin.
   *
   * @var \Drupal\user\Entity\User[]
   */
  protected array $actors = [];

  /**
   * The owner's and the outsider's record trees, keyed by label then type.
   *
   * @var array<string, \Drupal\Core\Entity\ContentEntityInterface[]>
   */
  protected array $trees = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $own = $any = [];
    foreach (array_keys(self::TYPES) as $type) {
      $phrase = str_replace('_', ' ', $type);
      array_push($own, "view own $phrase", "edit own $phrase", "delete own $phrase", "add $phrase");
      array_push($any, "view any $phrase", "edit any $phrase", "delete any $phrase", "add $phrase");
    }
    foreach (['beekeeper' => $own, 'any_viewer' => $any, 'api_admin' => ['administer hivelog']] as $id => $permissions) {
      $role = Role::create(['id' => $id, 'label' => $id]);
      foreach ($permissions as $permission) {
        $role->grantPermission($permission);
      }
      $role->save();
    }

    $actor_roles = [
      'owner' => 'beekeeper',
      'outsider' => 'beekeeper',
      'member' => 'beekeeper',
      'any' => 'any_viewer',
      'admin' => 'api_admin',
    ];
    foreach ($actor_roles as $label => $role) {
      $user = User::create([
        'name' => "actor_$label",
        'mail' => "$label@example.com",
        'pass' => "pw-actor_$label",
        'status' => 1,
      ]);
      $user->addRole($role);
      $user->save();
      $this->actors[$label] = $user;
    }

    $this->trees['owner'] = $this->tree($this->actors['owner']);
    $this->trees['outsider'] = $this->tree($this->actors['outsider']);

    // The member belongs to the owner's apiary but does not own it.
    $this->trees['owner']['apiary']->set('beekeepers', [$this->actors['member']->id()])->save();
    foreach (array_keys(self::TYPES) as $type) {
      \Drupal::entityTypeManager()->getStorage($type)->resetCache();
      \Drupal::entityTypeManager()->getAccessControlHandler($type)->resetCache();
    }
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Builds one user's record tree: one record of every exposed type.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   Keyed by entity type ID.
   */
  protected function tree(User $owner): array {
    $uid = $owner->id();
    $apiary = Apiary::create(['name' => 'Apiary', 'uid' => $uid, 'visibility' => 'private']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Hive', 'apiary' => $apiary->id(), 'status' => 'active', 'uid' => $uid]);
    $hive->save();
    $queen = Queen::create(['name' => 'Queen', 'hive' => $hive->id(), 'queen_year' => 2025, 'uid' => $uid]);
    $queen->save();
    $action = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Action',
      'description' => 'D',
      'week_start' => 20,
      'uid' => $uid,
    ]);
    $action->save();
    $records = [
      'apiary' => $apiary,
      'hive' => $hive,
      'queen' => $queen,
      'calendar_action' => $action,
      'hive_inspection' => HiveInspection::create([
        'hive' => $hive->id(),
        'inspection_date' => '2026-06-01',
        'uid' => $uid,
      ]),
      'queen_observation' => QueenObservation::create([
        'queen' => $queen->id(),
        'observation_date' => '2026-06-02',
        'health' => 'good',
        'uid' => $uid,
      ]),
      'hive_action_log' => HiveActionLog::create([
        'hive' => $hive->id(),
        'calendar_action' => $action->id(),
        'uid' => $uid,
      ]),
      'apiary_action_log' => ApiaryActionLog::create([
        'apiary' => $apiary->id(),
        'calendar_action' => $action->id(),
        'uid' => $uid,
      ]),
    ];
    foreach ($records as $record) {
      $record->save();
    }
    return $records;
  }

  /**
   * Whether the web UI would let the user reach a route.
   */
  protected function webAllows($user, string $route, array $params): bool {
    return (bool) \Drupal::service('access_manager')->checkNamedRoute($route, $params, $user);
  }

  /**
   * The versioned collection or item path of a type.
   */
  protected function path(string $type, ?ContentEntityInterface $entity = NULL): string {
    return "/hivelog/api/v1/$type/$type" . ($entity ? '/' . $entity->uuid() : '');
  }

  /**
   * The actors that have a session (everyone but anonymous).
   *
   * @return array<string, \Drupal\user\Entity\User>
   *   Keyed by label.
   */
  protected function signedIn(): array {
    return $this->actors;
  }

  /**
   * Tests reading a record by id matches the web UI, for every actor.
   */
  public function testReadMatchesTheWeb(): void {
    foreach ($this->signedIn() as $label => $user) {
      foreach (array_keys(self::TYPES) as $type) {
        foreach (['owner', 'outsider'] as $tree) {
          $entity = $this->trees[$tree][$type];
          $expected = $this->webAllows($user, "entity.$type.canonical", [$type => $entity->id()]);
          $status = $this->api('GET', $this->path($type, $entity), NULL, $user)['status'];
          $this->assertLessThan(500, $status, "$label reading $tree's $type");
          $this->assertSame($expected ? 200 : 403, $status, "$label reading $tree's $type");
        }
      }
    }
  }

  /**
   * Tests no collection ever lists a record the user could not open.
   */
  public function testCollectionsNeverLeakAnotherUsersRows(): void {
    foreach ($this->signedIn() as $label => $user) {
      foreach (array_keys(self::TYPES) as $type) {
        // Every record of the type on the site (creating an apiary also seeds
        // its calendar), not only the fixtures: what the web would open.
        $expected = [];
        foreach (\Drupal::entityTypeManager()->getStorage($type)->loadMultiple() as $entity) {
          if ($this->webAllows($user, "entity.$type.canonical", [$type => $entity->id()])) {
            $expected[] = $entity->uuid();
          }
        }
        // Walk every page, as a client would.
        $listed = [];
        $path = $this->path($type);
        for ($page = 0; $path && $page < 10; $page++) {
          $response = $this->api('GET', $path, NULL, $user);
          $this->assertSame(200, $response['status'], "$label listing $type");
          $listed = array_merge($listed, array_column($response['body']['data'], 'id'));
          // Nothing is dropped after the fact, so nothing is named as omitted:
          // JSON:API would otherwise list the ids of records the user may not see.
          $this->assertArrayNotHasKey('meta', $response['body'], "$label listing $type names omitted records: " . $response['raw']);
          $next = $response['body']['links']['next']['href'] ?? NULL;
          $path = $next ? (parse_url($next, PHP_URL_PATH) . '?' . parse_url($next, PHP_URL_QUERY)) : NULL;
        }
        sort($listed);
        sort($expected);
        $this->assertSame($expected, $listed, "$label listing $type");
      }
    }
  }

  /**
   * Tests a user's records are on the first page however many others precede.
   *
   * JSON:API paginates before it filters by access, so without the query-level
   * narrowing a user whose records come after 50 others sees an empty page.
   */
  public function testFirstPageHoldsTheUsersRecordsAmongManyOthers(): void {
    $uid = $this->actors['outsider']->id();
    for ($i = 0; $i < 60; $i++) {
      Apiary::create(['name' => "Filler $i", 'uid' => $uid, 'visibility' => 'private'])->save();
    }
    // The owner's apiary was created first, so it sorts after all of them.
    $response = $this->api('GET', $this->path('apiary') . '?page[limit]=5', NULL, $this->actors['owner']);
    $this->assertSame(200, $response['status']);
    $this->assertSame([$this->trees['owner']['apiary']->uuid()], array_column($response['body']['data'], 'id'));
    $this->assertArrayNotHasKey('next', $response['body']['links'], 'No empty pages to walk');

    // And the same user sees all of their own, page by page.
    $page = $this->api('GET', $this->path('apiary') . '?page[limit]=20', NULL, $this->actors['outsider']);
    $this->assertCount(20, $page['body']['data']);
    $this->assertArrayHasKey('next', $page['body']['links']);
  }

  /**
   * Tests the file collection is not served, only items and uploads.
   */
  public function testTheFileCollectionIsNotServed(): void {
    $this->assertSame(404, $this->api('GET', '/hivelog/api/v1/file/file', NULL, $this->actors['owner'])['status']);
  }

  /**
   * Tests updating a record matches the web UI, for every actor.
   */
  public function testUpdateMatchesTheWeb(): void {
    foreach ($this->signedIn() as $label => $user) {
      foreach (self::TYPES as $type => $info) {
        foreach (['owner', 'outsider'] as $tree) {
          $entity = $this->trees[$tree][$type];
          $expected = $this->webAllows($user, "entity.$type.edit_form", [$type => $entity->id()]);
          $document = [
            'data' => [
              'type' => "$type--$type",
              'id' => $entity->uuid(),
              'attributes' => [$info['patch'] => "Edited by $label"],
            ],
          ];
          $status = $this->api('PATCH', $this->path($type, $entity), $document, $user)['status'];
          $this->assertLessThan(500, $status, "$label updating $tree's $type");
          $this->assertSame($expected ? 200 : 403, $status, "$label updating $tree's $type");
        }
      }
    }
  }

  /**
   * Builds a create document for a type under a tree's parents.
   */
  protected function createDocument(string $type, array $tree): array {
    $attributes = match ($type) {
      'apiary' => ['name' => 'New', 'visibility' => 'private'],
      'hive' => ['name' => 'New', 'status' => 'active'],
      'hive_inspection' => ['inspection_date' => '2026-07-01'],
      'queen' => ['name' => 'New', 'queen_year' => 2025],
      'queen_observation' => ['observation_date' => '2026-07-01', 'health' => 'good'],
      'calendar_action' => ['title' => 'New', 'description' => 'D', 'week_start' => 20],
      default => [],
    };
    $parents = match ($type) {
      'hive' => ['apiary'],
      'hive_inspection', 'queen' => ['hive'],
      'queen_observation' => ['queen'],
      'calendar_action', 'apiary_action_log' => ['apiary'],
      default => [],
    };
    if ($type === 'hive_action_log') {
      $parents = ['hive', 'calendar_action'];
    }
    if ($type === 'apiary_action_log') {
      $parents = ['apiary', 'calendar_action'];
    }
    $relationships = [];
    foreach ($parents as $parent) {
      $relationships[$parent] = ['data' => ['type' => "$parent--$parent", 'id' => $tree[$parent]->uuid()]];
    }
    return [
      'data' => [
        'type' => "$type--$type",
        'attributes' => $attributes,
        'relationships' => $relationships,
      ],
    ];
  }

  /**
   * Tests creating a record matches the web's scoped add routes.
   *
   * Under the owner's parents and under the outsider's parents, so each actor
   * meets both a parent they may use and one they may not. A refusal is a
   * 403 (no permission) or a 422 (the parent-access constraint).
   */
  public function testCreateMatchesTheWeb(): void {
    foreach ($this->signedIn() as $label => $user) {
      foreach (self::TYPES as $type => $info) {
        foreach (['owner', 'outsider'] as $tree) {
          [$route, $params] = $info['add'];
          $route_params = [];
          foreach ($params as $name => $fixture) {
            $route_params[$name] = $this->trees[$tree][$fixture]->id();
          }
          $expected = $this->webAllows($user, $route, $route_params);

          $response = $this->api('POST', $this->path($type), $this->createDocument($type, $this->trees[$tree]), $user);
          $this->assertLessThan(500, $response['status'], "$label creating $type under $tree: " . $response['raw']);
          if ($expected) {
            $this->assertSame(201, $response['status'], "$label creating $type under $tree: " . $response['raw']);
          }
          else {
            $this->assertContains($response['status'], [403, 422], "$label creating $type under $tree");
          }
        }
      }
    }
  }

  /**
   * Tests deleting a leaf record matches the web UI, for every actor.
   */
  public function testDeleteMatchesTheWeb(): void {
    foreach ($this->signedIn() as $label => $user) {
      foreach (self::LEAF_TYPES as $type) {
        foreach (['owner', 'outsider'] as $tree) {
          // A fresh leaf each time: a delete that works consumes it.
          $fresh = $this->freshLeaf($type, $tree);
          $expected = $this->webAllows($user, "entity.$type.delete_form", [$type => $fresh->id()]);
          $status = $this->api('DELETE', $this->path($type, $fresh), NULL, $user)['status'];
          $this->assertLessThan(500, $status, "$label deleting $tree's $type");
          $this->assertSame($expected ? 204 : 403, $status, "$label deleting $tree's $type");
        }
      }
    }
  }

  /**
   * Creates a new leaf record in a tree.
   */
  protected function freshLeaf(string $type, string $tree): ContentEntityInterface {
    $parents = $this->trees[$tree];
    $uid = $parents['apiary']->get('uid')->target_id;
    $leaf = match ($type) {
      'hive_inspection' => HiveInspection::create([
        'hive' => $parents['hive']->id(),
        'inspection_date' => '2026-06-03',
        'uid' => $uid,
      ]),
      'queen_observation' => QueenObservation::create([
        'queen' => $parents['queen']->id(),
        'observation_date' => '2026-06-03',
        'health' => 'good',
        'uid' => $uid,
      ]),
      'hive_action_log' => HiveActionLog::create([
        'hive' => $parents['hive']->id(),
        'calendar_action' => $parents['calendar_action']->id(),
        'uid' => $uid,
      ]),
      'apiary_action_log' => ApiaryActionLog::create([
        'apiary' => $parents['apiary']->id(),
        'calendar_action' => $parents['calendar_action']->id(),
        'uid' => $uid,
      ]),
    };
    $leaf->save();
    return $leaf;
  }

  /**
   * Tests a record with children cannot be deleted, even by its owner or admin.
   *
   * ADR-0103: BLOCK is a data-integrity rule, not a permission, so the API
   * honours it exactly as the web does; the owner's apiary has hives and a
   * hive has inspections.
   */
  public function testRecordsWithBlockingChildrenAreNotDeletable(): void {
    foreach (['owner', 'admin'] as $label) {
      foreach (['apiary', 'hive'] as $type) {
        $status = $this->api('DELETE', $this->path($type, $this->trees['owner'][$type]), NULL, $this->actors[$label])['status'];
        $this->assertSame(403, $status, "$label deleting an owner's $type that has children");
      }
    }
  }

  /**
   * Tests the web and the API agree for the members the oracle covers.
   *
   * A beekeeper member of the owner's apiary can read and edit the member-scoped
   * records but not the apiary itself, and cannot add a hive or a calendar
   * action: the owner-only routes.
   */
  public function testBeekeeperMemberSharesTheWebsLimits(): void {
    $member = $this->actors['member'];
    $owner_tree = $this->trees['owner'];

    $inspection = $owner_tree['hive_inspection'];
    $apiary = $owner_tree['apiary'];
    $patch = fn(string $type, $entity, array $attributes) => $this->api('PATCH', $this->path($type, $entity), [
      'data' => ['type' => "$type--$type", 'id' => $entity->uuid(), 'attributes' => $attributes],
    ], $member)['status'];
    $create = fn(string $type) => $this->api('POST', $this->path($type), $this->createDocument($type, $owner_tree), $member)['status'];

    $this->assertSame(200, $this->api('GET', $this->path('hive', $owner_tree['hive']), NULL, $member)['status']);
    $this->assertSame(200, $patch('hive_inspection', $inspection, ['notes' => 'By a member']));
    $this->assertSame(403, $patch('apiary', $apiary, ['name' => 'Renamed']), 'A member cannot rename the apiary');
    $this->assertSame(201, $create('hive_inspection'));
    $this->assertContains($create('hive'), [403, 422]);
    $this->assertContains($create('calendar_action'), [403, 422]);
  }

  /**
   * Tests every request with no credentials is a 401, never data.
   */
  public function testNoCredentialsIsAlways401(): void {
    foreach (array_keys(self::TYPES) as $type) {
      $this->assertSame(401, $this->api('GET', $this->path($type))['status'], "Listing $type");
      $this->assertSame(401, $this->api('GET', $this->path($type, $this->trees['owner'][$type]))['status'], "Reading $type");
      $this->assertSame(401, $this->api('POST', $this->path($type), $this->createDocument($type, $this->trees['owner']))['status'], "Creating $type");
      $this->assertSame(401, $this->api('DELETE', $this->path($type, $this->trees['owner'][$type]))['status'], "Deleting $type");
    }
  }

  /**
   * Tests what the field-app role itself can and cannot do (its ceiling).
   *
   * Independent of the web matrix: this is the role the app's token carries.
   * It creates inspections, observations and action reports; it cannot create
   * an apiary, hive, queen or calendar action, and deletes nothing. It can
   * edit the parents it adds children under (the documented over-grant).
   */
  public function testTheFieldAppRoleCeiling(): void {
    $tree = $this->fixturesForAppRole();
    $app = $this->me;

    foreach (['hive_inspection', 'queen_observation', 'hive_action_log', 'apiary_action_log'] as $type) {
      $this->assertSame(201, $this->api('POST', $this->path($type), $this->createDocument($type, $tree), $app)['status'], "App creates $type");
    }
    foreach (['apiary', 'hive', 'queen', 'calendar_action'] as $type) {
      $this->assertContains(
        $this->api('POST', $this->path($type), $this->createDocument($type, $tree), $app)['status'],
        [403, 422],
        "App cannot create $type"
      );
    }
    foreach (self::LEAF_TYPES as $type) {
      $leaf = $tree[$type];
      $this->assertSame(403, $this->api('DELETE', $this->path($type, $leaf), NULL, $app)['status'], "App cannot delete $type");
    }
  }

  /**
   * Builds a full tree owned by the app-role user, for the ceiling test.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   Keyed by entity type ID.
   */
  protected function fixturesForAppRole(): array {
    return $this->tree($this->me);
  }

}
