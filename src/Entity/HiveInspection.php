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
use Drupal\hivelog\Form\HiveInspectionDeleteForm;
use Drupal\hivelog\Form\HiveInspectionForm;
use Drupal\hivelog\HiveInspectionAccessControlHandler;
use Drupal\hivelog\HiveInspectionListBuilder;
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
    'canonical' => '/admin/hivelog/inspection/{hive_inspection}',
    'edit-form' => '/admin/hivelog/inspection/{hive_inspection}/edit',
    'delete-form' => '/admin/hivelog/inspection/{hive_inspection}/delete',
  ],
)]
class HiveInspection extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    $date = $this->get('inspection_date')->value;
    $hive = $this->get('hive')->entity;
    $hive_name = $hive ? $hive->label() : t('Unknown');
    return t('Inspection of @hive on @date', [
      '@hive' => $hive_name,
      '@date' => $date ?: t('unknown date'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['hive'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Hive'))
      ->setDescription(t('The hive being inspected.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'hive')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['inspection_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Inspection Date'))
      ->setDescription(t('The date of the inspection.'))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'date')
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'datetime_default',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Queen status fields.
    $fields['queen_seen'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Queen Seen'))
      ->setDescription(t('Was the queen spotted during this inspection?'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => 2,
        'settings' => [
          'format' => 'yes-no',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['queen_cells'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Queen Cells'))
      ->setDescription(t('Were queen cells present?'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => 3,
        'settings' => [
          'format' => 'yes-no',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['eggs_seen'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Eggs Seen'))
      ->setDescription(t('Were eggs visible (indicates queen is laying)?'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 4,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => 4,
        'settings' => [
          'format' => 'yes-no',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Brood fields.
    $fields['brood_pattern'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Brood Pattern'))
      ->setDescription(t('Quality of the brood pattern.'))
      ->setSetting('allowed_values', [
        'good' => 'Good',
        'fair' => 'Fair',
        'poor' => 'Poor',
        'none' => 'None',
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

    $fields['queen_brood'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Queen Brood'))
      ->setDescription(t('Is capped queen brood present?'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => 6,
        'settings' => [
          'format' => 'yes-no',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Stores fields.
    $fields['honey_stores'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Honey Stores'))
      ->setDescription(t('Level of honey stores.'))
      ->setSetting('allowed_values', [
        'abundant' => 'Abundant',
        'adequate' => 'Adequate',
        'low' => 'Low',
        'none' => 'None',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 7,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['pollen_stores'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Pollen Stores'))
      ->setDescription(t('Level of pollen stores.'))
      ->setSetting('allowed_values', [
        'abundant' => 'Abundant',
        'adequate' => 'Adequate',
        'low' => 'Low',
        'none' => 'None',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 8,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 8,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Colony condition fields.
    $fields['temperament'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Temperament'))
      ->setDescription(t('Temperament observed during this inspection.'))
      ->setSetting('allowed_values', [
        'calm' => 'Calm',
        'moderate' => 'Moderate',
        'aggressive' => 'Aggressive',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 9,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 9,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['population'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Population'))
      ->setDescription(t('Strength of the colony population.'))
      ->setSetting('allowed_values', [
        'strong' => 'Strong',
        'moderate' => 'Moderate',
        'weak' => 'Weak',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 10,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Health fields.
    $fields['varroa_check'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Varroa Check'))
      ->setDescription(t('Was a varroa mite check performed?'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 11,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => 11,
        'settings' => [
          'format' => 'yes-no',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['varroa_count'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Varroa Count'))
      ->setDescription(t('Number of varroa mites found (if check was performed).'))
      ->setSetting('min', 0)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 12,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => 12,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['disease_signs'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Disease Signs'))
      ->setDescription(t('Any signs of disease observed.'))
      ->setSetting('allowed_values', [
        'none' => 'None',
        'nosema' => 'Nosema',
        'chalkbrood' => 'Chalkbrood',
        'efb' => 'European Foulbrood (EFB)',
        'afb' => 'American Foulbrood (AFB)',
        'sacbrood' => 'Sacbrood',
        'other' => 'Other',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 13,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 13,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Management fields.
    $fields['fed'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Fed'))
      ->setDescription(t('Was the colony fed during this inspection?'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 14,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => 14,
        'settings' => [
          'format' => 'yes-no',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['feed_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Feed Type'))
      ->setDescription(t('Type of feed given (e.g. sugar syrup, fondant).'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 15,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['supers'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Supers'))
      ->setDescription(t('Number of supers on the hive.'))
      ->setSetting('min', 0)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 16,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => 16,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['action_taken'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Action Taken'))
      ->setDescription(t('Actions performed during the inspection.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 17,
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => 17,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('General observations and notes.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 18,
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => 18,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Inspector'))
      ->setDescription(t('The user who performed this inspection.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 19,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 19,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the inspection was recorded.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the inspection was last updated.'));

    return $fields;
  }

}
