<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the versioned prefix, the allow-list and the read-only handling.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiRoutingTest extends HivelogApiKernelTestBase {

  /**
   * Tests the discovery document is public and describes the API.
   */
  public function testDiscoveryDocumentIsPublic(): void {
    $response = $this->api('GET', '/hivelog/api/v1');
    $this->assertSame(200, $response['status']);

    $meta = $response['body']['meta']['hivelog_api'];
    $this->assertSame(1, $meta['api_version']);
    $this->assertSame('hivelog-ios', $meta['oauth']['client_id']);
    $this->assertSame('hivelog_field_app', $meta['oauth']['scope']);
    $this->assertStringEndsWith('/oauth/authorize', $meta['oauth']['authorize']);
    $this->assertStringEndsWith('/oauth/token', $meta['oauth']['token']);
    $this->assertStringEndsWith('/hivelog/api/v1/hive/hive', $response['body']['links']['hive--hive']['href']);
    $this->assertArrayNotHasKey('inventory_item--inventory_item', $response['body']['links']);
  }

  /**
   * Tests an allow-listed resource is served under the prefix, owner-scoped.
   */
  public function testAllowListedResourceIsServedUnderThePrefix(): void {
    $response = $this->api('GET', '/hivelog/api/v1/apiary/apiary', NULL, $this->me);
    $this->assertSame(200, $response['status'], $response['raw']);

    $names = array_column(array_column($response['body']['data'], 'attributes'), 'name');
    $this->assertSame(['my apiary'], $names, 'Another beekeeper\'s apiary is not listed');
    $this->assertStringContainsString('/hivelog/api/v1/apiary/apiary', $response['body']['links']['self']['href']);
  }

  /**
   * Tests another beekeeper's record is refused by id.
   */
  public function testForeignRecordIsRefused(): void {
    $uuid = $this->fixtures['their_hive']->uuid();
    $this->assertSame(403, $this->api('GET', "/hivelog/api/v1/hive/hive/$uuid", NULL, $this->me)['status']);
    $this->assertSame(200, $this->api('GET', "/hivelog/api/v1/hive/hive/$uuid", NULL, $this->them)['status']);
  }

  /**
   * Tests links follow whichever prefix the request used, in either order.
   */
  public function testLinksFollowTheRequestPrefixWithoutCacheLeakage(): void {
    $self = fn(array $response): string => $response['body']['links']['self']['href'];
    $plain = '/jsonapi/apiary/apiary';
    $versioned = '/hivelog/api/v1/apiary/apiary';

    foreach ([$plain, $versioned, $plain, $versioned] as $path) {
      $response = $this->api('GET', $path, NULL, $this->me);
      $this->assertSame(200, $response['status'], $path);
      $this->assertStringContainsString($path, $self($response), "Self link for $path");
    }
  }

  /**
   * Tests resource types outside the allow-list are not routed.
   */
  public function testNonAllowListedResourcesAreNotFound(): void {
    foreach ([
      'inventory_item/inventory_item', 'inventory_purchase/inventory_purchase', 'product/product',
      'hive_component/hive_component', 'calendar_action_item_requirement/calendar_action_item_requirement',
      'user/user', 'consumer/consumer', 'oauth2_token/access_token', 'nothing/nothing',
    ] as $resource) {
      $response = $this->api('GET', "/hivelog/api/v1/$resource", NULL, $this->me);
      $this->assertSame(404, $response['status'], $resource);
    }
    // The same resource is not hidden by being unreachable elsewhere: it is
    // the allow-list, not a missing route, that stops it.
    $this->assertSame(200, $this->api('GET', '/jsonapi/inventory_item/inventory_item', NULL, $this->me)['status']);
  }

  /**
   * Tests writes work on the prefix while JSON:API stays read-only elsewhere.
   */
  public function testWritesOnlyOnThePrefixWhenSiteIsReadOnly(): void {
    $this->assertTrue($this->config('jsonapi.settings')->get('read_only'), 'The site is read-only');

    $document = [
      'data' => [
        'type' => 'hive_inspection--hive_inspection',
        'attributes' => ['inspection_date' => '2026-07-01'],
        'relationships' => [
          'hive' => [
            'data' => [
              'type' => 'hive--hive',
              'id' => $this->fixtures['my_hive']->uuid(),
            ],
          ],
        ],
      ],
    ];

    $unprefixed = $this->api('POST', '/jsonapi/hive_inspection/hive_inspection', $document, $this->me);
    $this->assertSame(405, $unprefixed['status'], 'Plain JSON:API keeps the site\'s read-only mode');

    $prefixed = $this->api('POST', '/hivelog/api/v1/hive_inspection/hive_inspection', $document, $this->me);
    $this->assertSame(201, $prefixed['status'], $prefixed['raw']);
    $this->assertTrue($this->config('jsonapi.settings')->get('read_only'), 'The setting itself is untouched');
  }

}
