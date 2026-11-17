<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests turning the pre-2.14 `beekeepers` field into memberships.
 *
 * `hivelog_update_10032()` ran against a site that still has the field's own
 * table, while the Apiary entity no longer defines the field, so the values
 * have to be read from that table. This sets up that legacy state.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class LegacyBeekeepersMigrationTest extends KernelTestBase {

  use UserCreationTrait;

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
   * The apiary's owner.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $owner;

  /**
   * The first former beekeeper.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $first;

  /**
   * The second former beekeeper.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $second;

  /**
   * An apiary.
   *
   * @var \Drupal\hivelog\Entity\Apiary
   */
  protected Apiary $apiary;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['user', 'file', 'apiary', 'apiary_membership'] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);
    // Skip uid 1, which bypasses every access check.
    $this->createUser();
    $this->owner = $this->createUser();
    $this->first = $this->createUser();
    $this->second = $this->createUser();
    $this->apiary = Apiary::create(['name' => 'Legacy', 'uid' => $this->owner->id()]);
    $this->apiary->save();
    $this->installLegacyBeekeepersField([
      [$this->apiary->id(), 0, $this->owner->id()],
      [$this->apiary->id(), 1, $this->first->id()],
      [$this->apiary->id(), 2, $this->second->id()],
      // A user that no longer exists, and an apiary that no longer exists.
      [$this->apiary->id(), 3, 9999],
      [8888, 0, $this->first->id()],
    ]);
    // Make the legacy state real: no memberships yet but the owner's, which
    // `Apiary::postSave()` gives a new apiary.
    \Drupal::database()->delete('hivelog_apiary_membership')->execute();
    \Drupal::entityTypeManager()->getStorage('apiary_membership')->resetCache();
    Apiary::resetRoleCache();
  }

  /**
   * Creates the old field's table and storage definition, with some values.
   *
   * @param array $rows
   *   Rows of [apiary id, delta, user id].
   */
  protected function installLegacyBeekeepersField(array $rows): void {
    $definition = BaseFieldDefinition::create('entity_reference')
      ->setName('beekeepers')
      ->setTargetEntityTypeId('apiary')
      ->setProvider('hivelog')
      ->setLabel('Beekeepers')
      ->setSetting('target_type', 'user')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    \Drupal::service('entity.last_installed_schema.repository')
      ->setLastInstalledFieldStorageDefinition($definition);

    $schema = \Drupal::database()->schema();
    $schema->createTable('apiary__beekeepers', [
      'fields' => [
        'bundle' => ['type' => 'varchar_ascii', 'length' => 128, 'not null' => TRUE, 'default' => ''],
        'deleted' => ['type' => 'int', 'size' => 'tiny', 'not null' => TRUE, 'default' => 0],
        'entity_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'revision_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'langcode' => ['type' => 'varchar_ascii', 'length' => 32, 'not null' => TRUE, 'default' => ''],
        'delta' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'beekeepers_target_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
      ],
      'primary key' => ['entity_id', 'deleted', 'delta', 'langcode'],
    ]);
    foreach ($rows as [$apiary_id, $delta, $uid]) {
      \Drupal::database()->insert('apiary__beekeepers')->fields([
        'bundle' => 'apiary',
        'deleted' => 0,
        'entity_id' => $apiary_id,
        'revision_id' => $apiary_id,
        'langcode' => 'en',
        'delta' => $delta,
        'beekeepers_target_id' => $uid,
      ])->execute();
    }
  }

  /**
   * Calls a helper from hivelog.install by a name built at run time.
   *
   * Whether static analysis can see an .install file's functions depends on
   * where it is run from, so they are not named statically.
   */
  protected function installFunction(string $name): mixed {
    \Drupal::moduleHandler()->loadInclude('hivelog', 'install');
    $function = '_hivelog_' . $name;
    return $function();
  }

  /**
   * Tests each former beekeeper becomes a novice, and the field goes.
   */
  public function testBeekeepersBecomeNovices(): void {
    $migrate = fn(): int => $this->installFunction('migrate_beekeepers_to_memberships');

    $this->assertSame(2, $migrate());

    $this->assertSame('novice', $this->apiary->getRoleOf($this->first));
    $this->assertSame('novice', $this->apiary->getRoleOf($this->second));
    $this->assertNull($this->apiary->getRoleOf($this->owner), 'The owner gets an owner membership in a later hook.');
    $this->assertFalse(\Drupal::database()->schema()->tableExists('apiary__beekeepers'), 'The old field table is gone.');
    $this->assertNull(\Drupal::entityDefinitionUpdateManager()->getFieldStorageDefinition('beekeepers', 'apiary'));
  }

  /**
   * Tests that running it twice adds nothing.
   */
  public function testMigrationIsSafeToRunTwice(): void {
    $migrate = fn(): int => $this->installFunction('migrate_beekeepers_to_memberships');

    $this->assertSame(2, $migrate());
    $this->assertSame(0, $migrate());
    $this->assertCount(2, $this->apiary->getApiaryMembers());
  }

  /**
   * Tests that the unique key is added once and refuses a duplicate pair.
   */
  public function testUniqueKeyIsAddedOnce(): void {
    $add = fn() => $this->installFunction('add_membership_unique_key');
    $schema = \Drupal::database()->schema();

    $add();
    $add();
    $this->assertTrue($schema->indexExists('hivelog_apiary_membership', 'apiary_account_unique'));

    $values = [
      'apiary' => $this->apiary->id(),
      'account' => $this->first->id(),
      'role' => 'novice',
      'uuid' => 'a',
      'uid' => $this->owner->id(),
    ];
    \Drupal::database()->insert('hivelog_apiary_membership')->fields($values)->execute();
    $this->expectException(\Exception::class);
    \Drupal::database()->insert('hivelog_apiary_membership')->fields(['uuid' => 'b'] + $values)->execute();
  }

}
