<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use Drupal\hivelog_api\HivelogApiResources;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the field schema the app builds its forms from (task 0205).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiSchemaTest extends HivelogApiKernelTestBase {

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
    'serialization',
    'jsonapi',
    'basic_auth',
    'consumers',
    'simple_oauth',
    'hivelog',
    'hivelog_api',
    'hivelog_api_test',
  ];

  /**
   * Fetches the schema as the signed-in beekeeper.
   *
   * @return array<string, array{fields: array<string, array<string, mixed>>}>
   *   The `data` member.
   */
  protected function schema(): array {
    $response = $this->api('GET', '/hivelog/api/v1/schema', NULL, $this->me);
    $this->assertSame(200, $response['status'], $response['raw']);
    return $response['body']['data'];
  }

  /**
   * Tests the schema needs a sign-in, like the rest of the API.
   */
  public function testItNeedsCredentials(): void {
    $this->assertSame(401, $this->api('GET', '/hivelog/api/v1/schema')['status']);
  }

  /**
   * Tests it describes exactly the exposed types, and not the file type.
   */
  public function testItCoversTheExposedTypes(): void {
    $this->assertEqualsCanonicalizing(HivelogApiResources::narrowedTypes(), array_keys($this->schema()));
  }

  /**
   * Tests a choice field offers the entity's own values and labels, in order.
   */
  public function testChoicesAreTheEntitysOwnAllowedValues(): void {
    $field = $this->schema()['hive_inspection']['fields']['brood_pattern'];
    $this->assertSame('choice', $field['kind']);
    $this->assertSame('Brood Pattern', $field['label']);

    $definition = \Drupal::service('entity_field.manager')->getFieldDefinitions('hive_inspection', 'hive_inspection')['brood_pattern'];
    $expected = [];
    foreach ($definition->getFieldStorageDefinition()->getSetting('allowed_values') as $value => $label) {
      $expected[] = ['value' => (string) $value, 'label' => (string) $label];
    }
    $this->assertSame($expected, $field['options']);
    $this->assertNotEmpty($expected);
  }

  /**
   * Tests the kinds, limits and references a form needs for an inspection.
   */
  public function testInspectionFieldsCarryWhatFormNeeds(): void {
    $fields = $this->schema()['hive_inspection']['fields'];

    $this->assertSame('date', $fields['inspection_date']['kind']);
    $this->assertTrue($fields['inspection_date']['required']);
    $this->assertSame('reference', $fields['hive']['kind']);
    $this->assertSame('hive--hive', $fields['hive']['target']);
    $this->assertSame('boolean', $fields['queen_seen']['kind']);
    $this->assertSame('integer', $fields['varroa_count']['kind']);
    $this->assertSame(0, $fields['varroa_count']['min']);
    $this->assertSame('number', $fields['weight']['kind']);
    $this->assertSame('long_text', $fields['notes']['kind']);
    $this->assertSame('text', $fields['feed_type']['kind']);
    $this->assertSame(255, $fields['feed_type']['max_length']);
    $this->assertSame('images', $fields['images']['kind']);
    $this->assertTrue($fields['images']['multiple']);
    $this->assertFalse($fields['disease_signs']['multiple'], 'A single choice says so');
  }

  /**
   * Tests nothing the server owns is offered for editing.
   */
  public function testServerOwnedFieldsAreLeftOut(): void {
    foreach ($this->schema() as $type => $description) {
      foreach (['id', 'uuid', 'uid', 'created', 'changed'] as $name) {
        $this->assertArrayNotHasKey($name, $description['fields'], "$type does not offer $name");
      }
    }
  }

  /**
   * Tests every field named is one the API actually exposes under that name.
   */
  public function testEveryFieldIsPartOfTheV1Contract(): void {
    $contract = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/fixtures/api-v1-contract.json'), TRUE);
    foreach ($this->schema() as $type => $description) {
      foreach (array_keys($description['fields']) as $name) {
        $this->assertArrayHasKey($name, $contract["$type--$type"], "$type.$name is in the contract");
      }
    }
  }

  /**
   * Tests every option offered is one the server accepts.
   *
   * The reason the app reads this instead of hard-coding the lists: an option
   * it shows must never be refused.
   */
  public function testEveryOfferedOptionIsAccepted(): void {
    $checked = 0;
    foreach ($this->schema() as $type => $description) {
      foreach ($description['fields'] as $name => $field) {
        if ($field['kind'] !== 'choice') {
          continue;
        }
        foreach ($field['options'] as $option) {
          /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
          $entity = \Drupal::entityTypeManager()->getStorage($type)->create([]);
          $entity->set($name, $field['multiple'] ? [$option['value']] : $option['value']);
          $this->assertCount(0, $entity->validate()->getByField($name), "$type.$name accepts {$option['value']}");
          $checked++;
        }
      }
    }
    $this->assertGreaterThan(20, $checked, 'There were options to check');
  }

}
