<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests a rule broken through the API is a 422 naming the field, never a 5xx.
 *
 * Before task 0200 an invariant that lived in a form answered a non-form
 * caller with an HTTP 500 from `preSave()`. Each rule is now a constraint, so
 * the API reports it like any other validation error. This sweeps every
 * exposed type with a missing required field, a bad value, and (where one
 * exists) a HiveLog-specific rule.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiValidationTest extends HivelogApiKernelTestBase {

  /**
   * An administrator, so a failure is the data's and never a permission's.
   */
  protected User $admin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $role = Role::create(['id' => 'validation_admin', 'label' => 'Validation admin']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $this->admin = User::create([
      'name' => 'validator',
      'mail' => 'v@example.com',
      'pass' => 'pw-validator',
      'status' => 1,
    ]);
    $this->admin->addRole('validation_admin');
    $this->admin->save();
  }

  /**
   * Builds a relationship entry.
   */
  protected function rel(string $type, string $uuid): array {
    return ['data' => ['type' => "$type--$type", 'id' => $uuid]];
  }

  /**
   * Posts a document and returns the response.
   */
  protected function post(string $type, array $attributes, array $relationships): array {
    return $this->api('POST', "/hivelog/api/v1/$type/$type", [
      'data' => [
        'type' => "$type--$type",
        'attributes' => $attributes,
        'relationships' => $relationships,
      ],
    ], $this->admin);
  }

  /**
   * Asserts a response is a 422 pointing at a field.
   */
  protected function assertRefused(array $response, string $field, string $message): void {
    $this->assertSame(422, $response['status'], "$message: " . $response['raw']);
    $pointers = array_column(array_column($response['body']['errors'], 'source'), 'pointer');
    // The pointer may carry a delta, e.g. /data/attributes/status/0.
    $on_field = array_filter($pointers, fn($p) => (bool) preg_match('#/data/(attributes|relationships)/' . $field . '(/|$)#', (string) $p));
    $this->assertNotEmpty($on_field, "$message: no error on $field in " . $response['raw']);
  }

  /**
   * Tests a missing required field is a 422 on that field, for every type.
   */
  public function testMissingRequiredFieldsAre422(): void {
    $f = $this->fixtures;
    $hive = $this->rel('hive', $f['my_hive']->uuid());
    $apiary = $this->rel('apiary', $f['my_apiary']->uuid());
    $action = CalendarAction::create([
      'apiary' => $f['my_apiary']->id(),
      'title' => 'T',
      'description' => 'D',
      'week_start' => 10,
    ]);
    $action->save();
    $action = $this->rel('calendar_action', $action->uuid());

    $this->assertRefused($this->post('apiary', [], []), 'name', 'apiary without a name');
    $this->assertRefused($this->post('hive', [], ['apiary' => $apiary]), 'name', 'hive without a name');
    $this->assertRefused($this->post('hive_inspection', [], ['hive' => $hive]), 'inspection_date', 'inspection without a date');
    $this->assertRefused($this->post('queen', [], ['hive' => $hive]), 'name', 'queen without a name');
    $queen_uuid = $this->post('queen', ['name' => 'Q', 'queen_year' => 2025], ['hive' => $hive])['body']['data']['id'];
    $this->assertRefused(
      $this->post('queen_observation', [], ['queen' => $this->rel('queen', $queen_uuid)]),
      'observation_date',
      'observation without a date'
    );
    $this->assertRefused($this->post('calendar_action', [], ['apiary' => $apiary]), 'title', 'calendar action without a title');
    $this->assertRefused($this->post('hive_action_log', [], ['hive' => $hive]), 'calendar_action', 'hive log without an action');
    $this->assertRefused($this->post('apiary_action_log', [], ['apiary' => $apiary]), 'calendar_action', 'apiary log without an action');
  }

  /**
   * Tests a value outside an allowed list is a 422 on that field.
   */
  public function testBadEnumValuesAre422(): void {
    $f = $this->fixtures;
    $queen_uuid = $this->post('queen', ['name' => 'Q', 'queen_year' => 2025], ['hive' => $this->rel('hive', $f['my_hive']->uuid())])['body']['data']['id'];

    $this->assertRefused(
      $this->post('hive', ['name' => 'H', 'status' => 'bogus'], ['apiary' => $this->rel('apiary', $f['my_apiary']->uuid())]),
      'status',
      'hive with a bad status'
    );
    $this->assertRefused(
      $this->post(
        'queen_observation',
        ['observation_date' => '2026-07-01', 'health' => 'bogus'],
        ['queen' => $this->rel('queen', $queen_uuid)]
      ),
      'health',
      'observation with a bad health'
    );
  }

  /**
   * Tests the HiveLog-specific rules (task 0200) are 422s, not the old 500s.
   */
  public function testHiveLogRulesAre422(): void {
    $f = $this->fixtures;
    $hive = $this->rel('hive', $f['my_hive']->uuid());
    $apiary = $this->rel('apiary', $f['my_apiary']->uuid());

    $this->assertRefused(
      $this->post('hive_inspection', ['inspection_date' => '2026-07-01', 'fed' => TRUE], ['hive' => $hive]),
      'feed_type',
      'fed without a feed type'
    );
    $this->assertRefused(
      $this->post('hive_inspection', ['inspection_date' => '2026-07-01', 'varroa_check' => TRUE], ['hive' => $hive]),
      'varroa_count',
      'varroa check without a count'
    );
    $this->assertRefused(
      $this->post('calendar_action', ['title' => 'T', 'description' => 'D', 'week_start' => 20, 'week_end' => 5], ['apiary' => $apiary]),
      'week_end',
      'an end week before the start week'
    );

    // A calendar action belonging to another apiary cannot be logged here.
    $foreign = CalendarAction::create([
      'apiary' => $f['their_apiary']->id(),
      'title' => 'T',
      'description' => 'D',
      'week_start' => 10,
    ]);
    $foreign->save();
    $foreign_ref = $this->rel('calendar_action', $foreign->uuid());
    $this->assertRefused($this->post('hive_action_log', [], ['hive' => $hive, 'calendar_action' => $foreign_ref]), 'calendar_action', 'a hive log for another apiary\'s action');
    $this->assertRefused($this->post('apiary_action_log', [], ['apiary' => $apiary, 'calendar_action' => $foreign_ref]), 'calendar_action', 'an apiary log for another apiary\'s action');
  }

  /**
   * Tests a valid create still works for the administrator (the control).
   */
  public function testValidCreateSucceeds(): void {
    $f = $this->fixtures;
    $response = $this->post(
      'hive',
      ['name' => 'Good', 'status' => 'active'],
      ['apiary' => $this->rel('apiary', $f['my_apiary']->uuid())]
    );
    $this->assertSame(201, $response['status'], $response['raw']);
    $this->assertNotNull(Apiary::load($f['my_apiary']->id()));
  }

}
