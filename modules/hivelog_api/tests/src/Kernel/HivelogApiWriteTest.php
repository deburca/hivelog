<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests writes through the API: client UUIDs and shared validation.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiWriteTest extends HivelogApiKernelTestBase {

  /**
   * Builds an inspection document for a hive.
   */
  protected function inspectionDocument(string $hive_uuid, array $attributes = [], ?string $id = NULL): array {
    $data = [
      'type' => 'hive_inspection--hive_inspection',
      'attributes' => $attributes + ['inspection_date' => '2026-07-01'],
      'relationships' => ['hive' => ['data' => ['type' => 'hive--hive', 'id' => $hive_uuid]]],
    ];
    if ($id) {
      $data['id'] = $id;
    }
    return ['data' => $data];
  }

  /**
   * Tests a client-supplied UUID is kept, and a retry is a clean 409.
   */
  public function testClientUuidIsKeptAndRetryConflicts(): void {
    $uuid = \Drupal::service('uuid')->generate();
    $path = '/hivelog/api/v1/hive_inspection/hive_inspection';
    $document = $this->inspectionDocument($this->fixtures['my_hive']->uuid(), [], $uuid);

    $first = $this->api('POST', $path, $document, $this->me);
    $this->assertSame(201, $first['status'], $first['raw']);
    $this->assertSame($uuid, $first['body']['data']['id']);

    $retry = $this->api('POST', $path, $document, $this->me);
    $this->assertSame(409, $retry['status'], $retry['raw']);

    $count = \Drupal::entityTypeManager()->getStorage('hive_inspection')->getQuery()->accessCheck(FALSE)
      ->condition('uuid', $uuid)->count()->execute();
    $this->assertSame(1, (int) $count);
  }

  /**
   * Tests a constraint violation is a 422 naming the field, not a 500.
   */
  public function testConstraintViolationIsA422WithTheFieldPointer(): void {
    $path = '/hivelog/api/v1/hive_inspection/hive_inspection';
    $document = $this->inspectionDocument($this->fixtures['my_hive']->uuid(), ['fed' => TRUE]);

    $response = $this->api('POST', $path, $document, $this->me);
    $this->assertSame(422, $response['status'], $response['raw']);
    $this->assertSame('/data/attributes/feed_type', $response['body']['errors'][0]['source']['pointer']);
    $this->assertCount(1, $response['body']['errors']);
    $this->assertSame('feed_type: Feed type is required when the colony was fed.', $response['body']['errors'][0]['detail']);
  }

  /**
   * Tests a record cannot be created under another beekeeper's hive.
   */
  public function testCreatingUnderForeignHiveIsRefused(): void {
    $path = '/hivelog/api/v1/hive_inspection/hive_inspection';
    $document = $this->inspectionDocument($this->fixtures['their_hive']->uuid());

    $response = $this->api('POST', $path, $document, $this->me);
    $this->assertSame(422, $response['status'], $response['raw']);
    $this->assertStringContainsString('You do not have permission', $response['body']['errors'][0]['detail']);
    $this->assertSame('/data/attributes/hive', $response['body']['errors'][0]['source']['pointer']);
  }

  /**
   * Tests an inspection cannot be moved onto another beekeeper's hive.
   */
  public function testReparentingToForeignHiveIsRefused(): void {
    $uuid = $this->fixtures['my_inspection']->uuid();
    $path = "/hivelog/api/v1/hive_inspection/hive_inspection/$uuid/relationships/hive";
    $body = ['data' => ['type' => 'hive--hive', 'id' => $this->fixtures['their_hive']->uuid()]];

    $this->assertSame(422, $this->api('PATCH', $path, $body, $this->me)['status']);
    /** @var \Drupal\hivelog\Entity\HiveInspection $stored */
    $stored = \Drupal::entityTypeManager()->getStorage('hive_inspection')
      ->loadUnchanged($this->fixtures['my_inspection']->id());
    $this->assertEquals($this->fixtures['my_hive']->id(), $stored->get('hive')->target_id);
  }

  /**
   * Tests the app role cannot delete: the app deletes nothing in v1.
   */
  public function testTheAppRoleCannotDelete(): void {
    $uuid = $this->fixtures['my_inspection']->uuid();
    $response = $this->api('DELETE', "/hivelog/api/v1/hive_inspection/hive_inspection/$uuid", NULL, $this->me);
    $this->assertSame(403, $response['status']);
  }

  /**
   * Tests an unauthenticated request cannot write, and is told to sign in.
   */
  public function testUnauthenticatedCannotWrite(): void {
    $document = $this->inspectionDocument($this->fixtures['my_hive']->uuid());
    $response = $this->api('POST', '/hivelog/api/v1/hive_inspection/hive_inspection', $document);
    $this->assertSame(401, $response['status']);
  }

}
