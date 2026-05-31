<?php

declare(strict_types=1);

namespace Drupal\hivelog\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog\Form\QueenDeleteForm;
use Drupal\hivelog\Form\QueenForm;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\hivelog\QueenAccessControlHandler;
use Drupal\hivelog\QueenListBuilder;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Queen entity.
 */
#[ContentEntityType(
  id: 'queen',
  label: new TranslatableMarkup('Queen'),
  label_collection: new TranslatableMarkup('Queens'),
  label_singular: new TranslatableMarkup('queen'),
  label_plural: new TranslatableMarkup('queens'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'list_builder' => QueenListBuilder::class,
    'form' => [
      'default' => QueenForm::class,
      'add' => QueenForm::class,
      'edit' => QueenForm::class,
      'delete' => QueenDeleteForm::class,
    ],
    'access' => QueenAccessControlHandler::class,
  ],
  base_table: 'hivelog_queen',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'label' => 'name',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'canonical' => '/hivelog/queen/{queen}',
    'add-form' => '/hivelog/queen/add',
    'edit-form' => '/hivelog/queen/{queen}/edit',
    'delete-form' => '/hivelog/queen/{queen}/delete',
    'collection' => '/hivelog/queens',
  ],
)]
class Queen extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {
  // ... rest of file is unchanged from current main
}
