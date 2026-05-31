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
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Form\HiveDeleteForm;
use Drupal\hivelog\Form\HiveForm;
use Drupal\hivelog\HiveAccessControlHandler;
use Drupal\hivelog\HiveListBuilder;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Hive entity.
 */
#[ContentEntityType(
  id: 'hive',
  label: new TranslatableMarkup('Hive'),
  label_collection: new TranslatableMarkup('Hives'),
  label_singular: new TranslatableMarkup('hive'),
  label_plural: new TranslatableMarkup('hives'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'list_builder' => HiveListBuilder::class,
    'form' => [
      'default' => HiveForm::class,
      'add' => HiveForm::class,
      'edit' => HiveForm::class,
      'delete' => HiveDeleteForm::class,
    ],
    'access' => HiveAccessControlHandler::class,
  ],
  base_table: 'hivelog_hive',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'label' => 'name',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'canonical' => '/hivelog/hive/{hive}',
    'edit-form' => '/hivelog/hive/{hive}/edit',
    'delete-form' => '/hivelog/hive/{hive}/delete',
  ],
)]
class Hive extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * Returns the currently active queen for this hive, if any.
   *
   * Queens are tracked on the `queen` entity; only one may be active per
   * hive at a time (enforced in Queen::preSave). This helper wraps the
   * query used by the hive view page and by tests.
   *
   * @return \Drupal\hivelog\Entity\Queen|null
   *   The active queen entity, or NULL if the hive has no active queen.
   */
  public function getActiveQueen(): ?Queen {
    if ($this->isNew()) {
      return NULL;
    }
    $storage = $this->entityTypeManager()->getStorage('queen');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive', $this->id())
      ->condition('status', 'active')
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    /** @var \Drupal\hivelog\Entity\Queen $queen */
    $queen = $storage->load(reset($ids));
    return $queen ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Hive Name'))
      ->setDescription(t('A name or identifier for the hive.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['apiary'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Apiary'))
      ->setDescription(t('The apiary this hive belongs to.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'apiary')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['hive_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Hive Type'))
      ->setDescription(t('The type of hive.'))
      ->setSetting('allowed_values', [
        '10x12' => '10x12',
        'norwegian' => 'Norwegian',
        'langstroth' => 'Langstroth',
        'trugstad' => 'Trugstad',
        'normal' => 'Normal',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['hive_material'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Hive Material'))
      ->setDescription(t('The material the hive is made from.'))
      ->setSetting('allowed_values', [
        'wood' => 'Wood',
        'styrofoam' => 'Styrofoam',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['bee_breed'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Bee Breed'))
      ->setDescription(t('The breed of bees in this hive.'))
      ->setSetting('allowed_values', [
        'buckfast' => 'Buckfast',
        'carniolan' => 'Apis mellifera carnica (Carniolan)',
        'italian' => 'Apis mellifera ligustica (Italian)',
        'caucasian' => 'Apis mellifera caucasica (Caucasian)',
        // 'russian' => 'Russian',
        'amm' => 'Apis mellifera mellifera (Dark European)',
        'other' => 'Other',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['temperament'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Temperament'))
      ->setDescription(t('The general temperament of the colony.'))
      ->setSetting('allowed_values', [
        'calm' => 'Calm',
        'moderate' => 'Moderate',
        'aggressive' => 'Aggressive',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The current status of the hive.'))
      ->setRequired(TRUE)
      ->setDefaultValue('active')
      ->setSetting('allowed_values', [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'dead' => 'Dead',
        'sold' => 'Sold',
        'merged' => 'Merged',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['images'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Pictures'))
      ->setDescription(t('One or more pictures of the hive. The first picture is shown as a letterbox hero on the hive view page.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('file_directory', 'hivelog/hive')
      ->setSetting('file_extensions', 'png gif jpg jpeg webp')
      ->setSetting('alt_field', TRUE)
      ->setSetting('alt_field_required', FALSE)
      ->setDisplayOptions('form', [
        'type' => 'image_image',
        'weight' => 5,
        'settings' => [
          'progress_indicator' => 'throbber',
          'preview_image_style' => 'thumbnail',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('General notes about the hive.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 6,
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user who owns this hive.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the hive was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the hive was last updated.'));

    return $fields;
  }

}
