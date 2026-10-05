<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\Queen;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the JSON:API filter, sort and include parameters on the versioned API.
 *
 * The app finds a hive's active queen and its recent inspections with these
 * (task 0205), so they must work together with the query-level access
 * narrowing (task 0203), not only without it.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiFilterTest extends HivelogApiKernelTestBase {

  /**
   * Tests a filter on a plain field narrows the user's own records.
   */
  public function testFilterByFieldNarrowsTheUsersRecords(): void {
    $second = Hive::create([
      'name' => 'second hive',
      'apiary' => $this->fixtures['my_apiary']->id(),
      'status' => 'active',
      'uid' => $this->me->id(),
    ]);
    $second->save();

    $response = $this->api('GET', '/hivelog/api/v1/hive/hive?filter[name]=second%20hive', NULL, $this->me);
    $this->assertSame(200, $response['status'], $response['raw']);
    $this->assertSame([$second->uuid()], array_column($response['body']['data'], 'id'), $response['raw']);
  }

  /**
   * Tests a filter never reaches another user's record.
   */
  public function testFilterDoesNotWidenAccess(): void {
    $response = $this->api('GET', '/hivelog/api/v1/hive/hive?filter[name]=their%20hive', NULL, $this->me);
    $this->assertSame(200, $response['status']);
    $this->assertSame([], $response['body']['data']);
  }

  /**
   * Tests the hive's inspections and its active queen can be looked up.
   */
  public function testFilterByParentAndSort(): void {
    $hive = $this->fixtures['my_hive'];
    HiveInspection::create(['hive' => $hive->id(), 'inspection_date' => '2026-07-01', 'uid' => $this->me->id()])->save();
    HiveInspection::create(['hive' => $hive->id(), 'inspection_date' => '2026-08-01', 'uid' => $this->me->id()])->save();
    $queen = Queen::create(['name' => 'Q', 'hive' => $hive->id(), 'status' => 'active', 'uid' => $this->me->id()]);
    $queen->save();

    $response = $this->api('GET', '/hivelog/api/v1/hive_inspection/hive_inspection?filter[hive.id]=' . $hive->uuid() . '&sort=-inspection_date&page[limit]=2', NULL, $this->me);
    $this->assertSame(200, $response['status'], $response['raw']);
    $this->assertSame(['2026-08-01', '2026-07-01'], array_column(array_column($response['body']['data'], 'attributes'), 'inspection_date'), $response['raw']);

    $response = $this->api('GET', '/hivelog/api/v1/queen/queen?filter[hive.id]=' . $hive->uuid() . '&filter[status]=active', NULL, $this->me);
    $this->assertSame([$queen->uuid()], array_column($response['body']['data'], 'id'), $response['raw']);
  }

  /**
   * Tests filtering through a relationship still reaches only viewable records.
   *
   * A filter on the parent's field is how the app asks "this hive's
   * inspections"; it must not become a way to probe another user's records.
   */
  public function testFilterThroughRelationshipCannotProbeOthers(): void {
    $path = '/hivelog/api/v1/hive_inspection/hive_inspection';
    $mine = $this->api('GET', $path . '?filter[hive.name]=my%20hive', NULL, $this->me);
    $this->assertSame([$this->fixtures['my_inspection']->uuid()], array_column($mine['body']['data'], 'id'), $mine['raw']);

    $theirs = $this->api('GET', $path . '?filter[hive.name]=their%20hive', NULL, $this->me);
    $this->assertSame(200, $theirs['status']);
    $this->assertSame([], $theirs['body']['data']);
    $this->assertArrayNotHasKey('meta', $theirs['body'], 'No omitted records are named');

    $deep = $this->api('GET', $path . '?filter[hive.apiary.name]=their%20apiary', NULL, $this->me);
    $this->assertSame([], $deep['body']['data']);
  }

  /**
   * Tests a filter on the owner can match only the user themself.
   *
   * The users are not part of the API, and JSON:API only lets a request filter
   * through a user relationship as far as that user's own record, so a filter
   * is not a way to find out who else exists.
   */
  public function testFilterByOwnerMatchesOnlyTheCurrentUser(): void {
    $mine = $this->api('GET', '/hivelog/api/v1/hive/hive?filter[uid.name]=me', NULL, $this->me);
    $this->assertSame([$this->fixtures['my_hive']->uuid()], array_column($mine['body']['data'], 'id'), $mine['raw']);

    $theirs = $this->api('GET', '/hivelog/api/v1/hive/hive?filter[uid.name]=them', NULL, $this->me);
    $this->assertSame([], $theirs['body']['data'] ?? NULL, $theirs['raw']);
    $nobody = $this->api('GET', '/hivelog/api/v1/hive/hive?filter[uid.name]=nobody', NULL, $this->me);
    $this->assertSame($theirs['body']['data'], $nobody['body']['data'], 'A user who exists answers like one who does not');
  }

  /**
   * Tests plain JSON:API keeps its default; only the versioned API opens up.
   */
  public function testThePlainJsonApiKeepsItsDefault(): void {
    $response = $this->api('GET', '/jsonapi/hive/hive?filter[name]=my%20hive', NULL, $this->me);
    $this->assertSame([], $response['body']['data'] ?? [], 'Not opened up off the versioned prefix: ' . $response['raw']);
  }

}
