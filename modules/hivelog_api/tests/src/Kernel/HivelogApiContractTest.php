<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use Drupal\hivelog_api\HivelogApiResources;
use Drupal\jsonapi\ResourceType\ResourceTypeRelationship;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Pins the v1 API contract: the exposed resources and their field names.
 *
 * Every field name comes straight from an entity's `baseFieldDefinitions()`,
 * so renaming or removing one silently breaks every shipped copy of the app.
 * This test fails when the exposed surface differs from the committed
 * fixture. A change that only ADDS a field is safe: regenerate the fixture.
 * A rename or removal needs either an alias that keeps the old name, or a new
 * API version (`HivelogApiResources::API_VERSION` and `PATH_PREFIX`) with the
 * old one kept running while the app updates.
 *
 * Regenerate with:
 * HIVELOG_API_WRITE_CONTRACT=1 phpunit … HivelogApiContractTest.php
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiContractTest extends HivelogApiKernelTestBase {

  /**
   * Describes the allow-listed resources as {type: {field: kind}}.
   *
   * The file resource is pinned by name only: its fields are core's, not ours.
   */
  protected function currentContract(): array {
    $repository = \Drupal::service('jsonapi.resource_type.repository');
    $contract = [];
    foreach (HivelogApiResources::RESOURCES as $resource) {
      [$entity_type_id, $bundle] = explode('/', $resource);
      $type = $repository->get($entity_type_id, $bundle);
      $this->assertNotNull($type, "$resource has a JSON:API resource type");
      $fields = [];
      if ($entity_type_id !== 'file') {
        foreach ($type->getFields() as $field) {
          if ($field->isFieldEnabled()) {
            $fields[$field->getPublicName()] = $field instanceof ResourceTypeRelationship ? 'relationship' : 'attribute';
          }
        }
        ksort($fields);
      }
      $contract[$type->getTypeName()] = $fields;
    }
    ksort($contract);
    return $contract;
  }

  /**
   * Tests the exposed surface still matches the committed v1 contract.
   */
  public function testTheExposedSurfaceMatchesTheV1Contract(): void {
    $path = dirname(__DIR__, 2) . '/fixtures/api-v1-contract.json';
    $current = $this->currentContract();

    if (getenv('HIVELOG_API_WRITE_CONTRACT')) {
      file_put_contents($path, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
    $this->assertFileExists($path);
    $pinned = json_decode((string) file_get_contents($path), TRUE);

    $this->assertSame(array_keys($pinned), array_keys($current), 'The set of exposed resource types changed.');
    foreach ($pinned as $type => $fields) {
      $removed = array_diff_key($fields, $current[$type]);
      $this->assertSame([], $removed, "$type lost fields the app may rely on: a rename or removal is a breaking change. See this test's docblock.");
      $changed = array_diff_assoc(array_intersect_key($current[$type], $fields), $fields);
      $this->assertSame([], $changed, "$type changed a field between attribute and relationship.");
    }
    $this->assertSame($pinned, $current, 'Fields were added: regenerate the fixture (see this test\'s docblock) and add them to the app deliberately.');
    $this->assertSame(1, HivelogApiResources::API_VERSION, 'The fixture is the v1 contract; a v2 needs its own fixture.');
  }

}
