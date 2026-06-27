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
 *
 * A queen observation captures queen-specific notes (health, temperament,
 * laying activity, photos, free text) taken at a point in time, separately
 * from hive-level inspections. Observations reference a queen, which in
 * turn references the hive the queen is installed in.
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

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    $date = $this->get('observation_date')->value;
    $queen = $this->get('queen')->entity;
    $queen_name = $queen ? $queen->label() : t('Unknown');
    return t('Observation of @queen on @date', [
      '@queen' => $queen_name,
      '@date' => $date ?: t('unknown date'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['queen'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Queen'))
      ->setDescription(t('The queen being observed.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'queen')
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 0])
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'entity_reference_label', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['observation_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Observation Date'))
      ->setDescription(t('The date of the observation.'))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'date')
      ->setDisplayOptions('form', ['type' => 'datetime_default', 'weight' => 1])
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'datetime_default', 'weight' => 1])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['health'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Health'))
      ->setDescription(t("The queen's apparent health at the time of this observation."))
      ->setSetting('allowed_values', [
        'excellent' => 'Excellent',
        'good' => 'Good',
        'fair' => 'Fair',
        'poor' => 'Poor',
      ])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 2])
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'list_default', 'weight' => 2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['temperament'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Temperament'))
      ->setDescription(t("The queen's temperament as observed."))
      ->setSetting('allowed_values', [
        'calm' => 'Calm',
        'moderate' => 'Moderate',
        'aggressive' => 'Aggressive',
      ])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 3])
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'list_default', 'weight' => 3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['active'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active'))
      ->setDescription(t('Was the queen observed actively laying / moving on the comb?'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', ['type' => 'boolean_checkbox', 'weight' => 4])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => 4,
        'settings' => ['format' => 'yes-no'],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('Free-text observations about the queen.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 5,
        'settings' => ['rows' => 4],
      ])
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'basic_string', 'weight' => 5])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['images'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Pictures'))
      ->setDescription(t('Photos taken during this queen observation.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('file_directory', 'hivelog/queen-observation')
      ->setSetting('file_extensions', 'png gif jpg jpeg webp')
      ->setSetting('alt_field', TRUE)
      ->setSetting('alt_field_required', FALSE)
      ->setDisplayOptions('form', [
        'type' => 'image_image',
        'weight' => 6,
        'settings' => ['progress_indicator' => 'throbber', 'preview_image_style' => 'thumbnail'],
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'image',
        'weight' => 6,
        'settings' => ['image_style' => 'medium', 'image_link' => 'file'],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['uid']
      ->setLabel(t('Observer'))
      ->setDescription(t('The user who made this observation.'))
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 7])
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'entity_reference_label', 'weight' => 7])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the observation was recorded.'));
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the observation was last updated.'));

    return $fields;
  }

}
