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
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog\ApiaryAccessControlHandler;
use Drupal\hivelog\ApiaryListBuilder;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\hivelog\Form\ApiaryDeleteForm;
use Drupal\hivelog\Form\ApiaryForm;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Apiary entity.
 */
#[ContentEntityType(
  id: 'apiary',
  label: new TranslatableMarkup('Apiary'),
  label_collection: new TranslatableMarkup('Apiaries'),
  label_singular: new TranslatableMarkup('apiary'),
  label_plural: new TranslatableMarkup('apiaries'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'list_builder' => ApiaryListBuilder::class,
    'form' => [
      'default' => ApiaryForm::class,
      'add' => ApiaryForm::class,
      'edit' => ApiaryForm::class,
      'delete' => ApiaryDeleteForm::class,
    ],
    'access' => ApiaryAccessControlHandler::class,
  ],
  base_table: 'hivelog_apiary',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'label' => 'name',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'canonical' => '/hivelog/apiary/{apiary}',
    'add-form' => '/hivelog/apiary/add',
    'edit-form' => '/hivelog/apiary/{apiary}/edit',
    'delete-form' => '/hivelog/apiary/{apiary}/delete',
    'collection' => '/hivelog',
  ],
)]
class Apiary extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * Checks whether the given account is a member of this apiary.
   *
   * A member is either the apiary owner or a user listed in the
   * beekeepers field.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check.
   *
   * @return bool
   *   TRUE if the account is the apiary owner or an approved beekeeper.
   */
  public function isApiaryMember(AccountInterface $account): bool {
    if ((int) $this->getOwnerId() === (int) $account->id()) {
      return TRUE;
    }
    foreach ($this->get('beekeepers') as $item) {
      if ((int) $item->target_id === (int) $account->id()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks whether this apiary is publicly visible.
   *
   * @return bool
   *   TRUE if the visibility field is set to 'public'.
   */
  public function isPublic(): bool {
    return $this->get('visibility')->value === 'public';
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the apiary.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['location'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Location'))
      ->setDescription(t('Address or description of the apiary location.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 1,
        'settings' => [
          'rows' => 3,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['geolocation'] = BaseFieldDefinition::create('geofield')
      ->setLabel(t('Geolocation'))
      ->setDescription(t('GPS coordinates of the apiary. Click or drag the marker on the map to set the location.'))
      ->setDisplayOptions('form', [
        'type' => 'leaflet_widget_default',
        'weight' => 2,
        'settings' => [
          'map' => [
            'leaflet_map' => 'OSM Mapnik',
            'height' => 300,
            'center' => ['lat' => 55.0, 'lon' => 10.0],
            'auto_center' => TRUE,
            'zoom' => 6,
          ],
          'input' => [
            'show' => FALSE,
          ],
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'leaflet_formatter_default',
        'weight' => 2,
        'settings' => [
          'leaflet_map' => 'OSM Mapnik',
          'height' => 200,
          'height_unit' => 'px',
          'hide_empty_map' => TRUE,
          'disable_wheel' => TRUE,
          'map_position' => [
            'force' => FALSE,
            'center' => ['lat' => 0, 'lon' => 0],
            'zoom' => 14,
            'minZoom' => 1,
            'maxZoom' => 18,
            'zoomFiner' => 0,
          ],
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('General notes about the apiary.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 4,
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['beekeepers'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Beekeepers'))
      ->setDescription(t('Users who are approved to manage hives and record inspections in this apiary. The apiary owner always has full access and does not need to be listed here.'))
      ->setSetting('target_type', 'user')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['visibility'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Visibility'))
      ->setDescription(t('Controls whether non-members can view this apiary and its hives. Private apiaries are only visible to the owner and approved beekeepers.'))
      ->setRequired(TRUE)
      ->setDefaultValue('private')
      ->setSetting('allowed_values', [
        'private' => 'Private',
        'public' => 'Public',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user who owns this apiary.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 7,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the apiary was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the apiary was last updated.'));

    return $fields;
  }

}
