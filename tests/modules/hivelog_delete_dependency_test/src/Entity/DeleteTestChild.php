<?php

declare(strict_types=1);

namespace Drupal\hivelog_delete_dependency_test\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog_delete_dependency_test\DeleteTestChildAccessControlHandler;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * A throwaway "child" entity for the delete-dependency framework tests.
 *
 * The "child" in the test-only relationship
 * `hivelog_delete_dependency_test` registers via
 * `hook_hivelog_delete_dependencies()`. Exists only for
 * `HivelogDeleteDependencyRegistry` / `HivelogDeleteDependencyCounter`
 * kernel tests (task 0134) — an `apiary` is the "parent", this is the
 * "child", `delete own` / `delete any` permissions give a real,
 * testable split between children the test's current user can and
 * cannot delete themselves (ADR-0103's "children the current user
 * can't delete" case).
 */
#[ContentEntityType(
  id: 'hivelog_delete_test_child',
  label: new TranslatableMarkup('Delete Test Child'),
  label_singular: new TranslatableMarkup('delete test child'),
  label_plural: new TranslatableMarkup('delete test children'),
  handlers: [
    'access' => DeleteTestChildAccessControlHandler::class,
  ],
  base_table: 'hivelog_delete_test_child',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
)]
class DeleteTestChild extends ContentEntityBase implements EntityOwnerInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['parent'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Parent apiary'))
      ->setSetting('target_type', 'apiary')
      ->setRequired(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner');

    return $fields;
  }

}
