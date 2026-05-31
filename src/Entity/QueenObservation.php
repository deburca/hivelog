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
use Drupal\hivelog\Form\QueenObservationDeleteForm;
use Drupal\hivelog\Form\QueenObservationForm;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\hivelog\QueenObservationAccessControlHandler;
use Drupal\hivelog\QueenObservationListBuilder;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Queen Observation entity.
 */
#[ContentEntityType(
  id: 'queen_observation',
  label: new TranslatableMarkup('Queen Observation'),
  label_collection: new TranslatableMarkup('Queen Observations'),
  label_singular: new TranslatableMarkup('queen observation'),
  label_plural: new TranslatableMarkup('queen observations'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'list_builder' => QueenObservationListBuilder::class,
    'form' => [
      'default' => QueenObservationForm::class,
      'add' => QueenObservationForm::class,
      'edit' => QueenObservationForm::class,
      'delete' => QueenObservationDeleteForm::class,
    ],
    'access' => QueenObservationAccessControlHandler::class,
  ],
  base_table: 'hivelog_queen_observation',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'canonical' => '/hivelog/queen-observation/{queen_observation}',
    'edit-form' => '/hivelog/queen-observation/{queen_observation}/edit',
    'delete-form' => '/hivelog/queen-observation/{queen_observation}/delete',
    'collection' => '/hivelog/queen-observations',
  ],
)]
class QueenObservation extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {
  // ... rest of file is unchanged from current main
}
