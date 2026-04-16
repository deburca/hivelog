<?php

declare(strict_types=1);

namespace Drupal\hivelog\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog\Form\HiveDeleteForm;
use Drupal\hivelog\Form\HiveForm;
use Drupal\hivelog\HiveAccessControlHandler;
use Drupal\hivelog\HiveListBuilder;
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
    'canonical' => '/admin/hivelog/hive/{hive}',
    'edit-form' => '/admin/hivelog/hive/{hive}/edit',
    'delete-form' => '/admin/hivelog/hive/{hive}/delete',
  ],
)]
class Hive extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * Maps the last digit of a year to the international queen marking colour.
   */
  const QUEEN_COLOUR_MAP = [
    0 => 'blue',
    1 => 'white',
    2 => 'yellow',
    3 => 'red',
    4 => 'green',
    5 => 'blue',
    6 => 'white',
    7 => 'yellow',
    8 => 'red',
    9 => 'green',
  ];

  /**
   * {@inheritdoc}
   */
  public function preSave(\Drupal\Core\Entity\EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Auto-calculate queen colour from queen year.
    $queen_year = $this->get('queen_year')->value;
    if ($queen_year) {
      $last_digit = (int) $queen_year % 10;
      $this->set('queen_colour', self::QUEEN_COLOUR_MAP[$last_digit]);
    }
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
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
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
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
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
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
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
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['queen_year'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Queen Year'))
      ->setDescription(t('The year the queen was introduced. The queen colour is set automatically based on this value.'))
      ->setSetting('min', 2000)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['queen_colour'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Queen Colour'))
      ->setDescription(t('International queen marking colour. Auto-calculated from Queen Year: White (1,6), Yellow (2,7), Red (3,8), Green (4,9), Blue (0,5).'))
      ->setSetting('allowed_values', [
        'white' => 'White (years ending 1, 6)',
        'yellow' => 'Yellow (years ending 2, 7)',
        'red' => 'Red (years ending 3, 8)',
        'green' => 'Green (years ending 4, 9)',
        'blue' => 'Blue (years ending 0, 5)',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['bee_breed'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Bee Breed'))
      ->setDescription(t('The breed of bees in this hive.'))
      ->setSetting('allowed_values', [
        'buckfast' => 'Buckfast',
        'carniolan' => 'Carniolan',
        'italian' => 'Italian',
        'caucasian' => 'Caucasian',
        'russian' => 'Russian',
        'amm' => 'AMM (Dark European)',
        'other' => 'Other',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
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
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
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
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

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
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => 6,
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
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
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
