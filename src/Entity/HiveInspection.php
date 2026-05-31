<?php

declare(strict_types=1);

namespace Drupal\hivelog\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog\Form\HiveInspectionDeleteForm;
use Drupal\hivelog\Form\HiveInspectionForm;
use Drupal\hivelog\HiveInspectionAccessControlHandler;
use Drupal\hivelog\HiveInspectionListBuilder;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Hive Inspection entity.
 */
#[ContentEntityType(
  id: 'hive_inspection',
  label: new TranslatableMarkup('Hive Inspection'),
  label_collection: new TranslatableMarkup('Hive Inspections'),
  label_singular: new TranslatableMarkup('hive inspection'),
  label_plural: new TranslatableMarkup('hive inspections'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'list_builder' => HiveInspectionListBuilder::class,
    'form' => [
      'default' => HiveInspectionForm::class,
      'add' => HiveInspectionForm::class,
      'edit' => HiveInspectionForm::class,
      'delete' => HiveInspectionDeleteForm::class,
    ],
    'access' => HiveInspectionAccessControlHandler::class,
  ],
  base_table: 'hivelog_hive_inspection',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'canonical' => '/hivelog/inspection/{hive_inspection}',
    'edit-form' => '/hivelog/inspection/{hive_inspection}/edit',
    'delete-form' => '/hivelog/inspection/{hive_inspection}/delete',
  ],
)]
class HiveInspection extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {
  // ... rest of file is unchanged from current main
}
