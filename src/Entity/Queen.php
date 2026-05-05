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
use Drupal\hivelog\QueenAccessControlHandler;
use Drupal\hivelog\QueenListBuilder;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Queen entity.
 *
 * Queens are tracked separately from hives because hives outlive queens and
 * a single hive may house different queens over its lifetime. A queen has
 * its own identifier, provenance and lifecycle, and carries an optional
 * reference to the hive it is currently installed in. When the queen is no
 * longer in service (superseded, died, sold) the record is archived.
 */
#[ContentEntityType(
  id: 'queen',
  label: new TranslatableMarkup('Queen'),
  label_collection: new TranslatableMarkup('Queens'),
  label_singular: new TranslatableMarkup('queen'),
  label_plural: new TranslatableMarkup('queens'),
  handlers: [
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
   *
   * Derives the marking colour from the hatch year and enforces the
   * "one active queen per hive" invariant by deactivating any previously
   * active queen on the same hive when this one is saved as active.
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Auto-calculate queen colour from queen year.
    $queen_year = $this->get('queen_year')->value;
    if ($queen_year) {
      $last_digit = (int) $queen_year % 10;
      $this->set('queen_colour', self::QUEEN_COLOUR_MAP[$last_digit]);
    }

    // Enforce at most one active queen per hive: if this queen is being
    // saved as active with a hive assigned, mark any other active queen
    // currently attached to the same hive inactive and detach it.
    $status = $this->get('status')->value;
    $hive_id = $this->get('hive')->target_id;
    if ($status === 'active' && $hive_id) {
      $query = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('hive', $hive_id)
        ->condition('status', 'active');
      if (!$this->isNew()) {
        $query->condition('id', $this->id(), '<>');
      }
      $conflict_ids = $query->execute();
      if ($conflict_ids) {
        foreach ($storage->loadMultiple($conflict_ids) as $conflict) {
          $conflict->set('status', 'inactive');
          $conflict->set('hive', NULL);
          $conflict->save();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Queen ID'))
      ->setDescription(t('A human readable identifier for this queen, e.g. Q-2026-001.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['origin'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Origin'))
      ->setDescription(t('Where this queen came from (breeder, swarm, supplier, ...).'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['queen_year'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Queen Year'))
      ->setDescription(t('The year the queen hatched. The queen colour is set automatically based on this value.'))
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

    $fields['breed'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Breed'))
      ->setDescription(t('The breed of this queen.'))
      ->setSetting('allowed_values', [
        'buckfast' => 'Buckfast',
        'carniolan' => 'Apis mellifera carnica (Carniolan)',
        'italian' => 'Apis mellifera ligustica (Italian)',
        'caucasian' => 'Apis mellifera caucasica (Caucasian)',
        'amm' => 'Apis mellifera mellifera (Dark European)',
        'other' => 'Other',
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

    $fields['temperament'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Temperament'))
      ->setDescription(t('The general temperament associated with this queen.'))
      ->setSetting('allowed_values', [
        'calm' => 'Calm',
        'moderate' => 'Moderate',
        'aggressive' => 'Aggressive',
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

    $fields['purchase_cost'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Purchase Cost'))
      ->setDescription(t('What this queen cost, if purchased.'))
      ->setSetting('precision', 10)
      ->setSetting('scale', 2)
      ->setSetting('min', 0)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['purchase_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Purchase Date'))
      ->setDescription(t('The date this queen was purchased, if applicable.'))
      ->setSetting('datetime_type', 'date')
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 7,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'datetime_default',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['hive'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Hive'))
      ->setDescription(t('The hive this queen is currently installed in, if any. Leave empty for queens that are not yet assigned or that are inactive.'))
      ->setSetting('target_type', 'hive')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 8,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 8,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['introduction_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Introduction Date'))
      ->setDescription(t('The date this queen was introduced to the hive.'))
      ->setSetting('datetime_type', 'date')
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 9,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'datetime_default',
        'weight' => 9,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('Whether this queen is currently in service (active) or retired from service (inactive).'))
      ->setRequired(TRUE)
      ->setDefaultValue('active')
      ->setSetting('allowed_values', [
        'active' => 'Active',
        'inactive' => 'Inactive',
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

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('General notes about this queen.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 11,
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => 11,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user who owns this queen record.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 12,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the queen record was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the queen record was last updated.'));

    return $fields;
  }

}
