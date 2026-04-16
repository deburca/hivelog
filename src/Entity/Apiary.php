<?php

namespace Drupal\hivelog\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Apiary entity.
 *
 * @ContentEntityType(
 *   id = "apiary",
 *   label = @Translation("Apiary"),
 *   label_collection = @Translation("Apiaries"),
 *   label_singular = @Translation("apiary"),
 *   label_plural = @Translation("apiaries"),
 *   handlers = {
 *     "list_builder" = "Drupal\hivelog\ApiaryListBuilder",
 *     "form" = {
 *       "default" = "Drupal\hivelog\Form\ApiaryForm",
 *       "add" = "Drupal\hivelog\Form\ApiaryForm",
 *       "edit" = "Drupal\hivelog\Form\ApiaryForm",
 *       "delete" = "Drupal\hivelog\Form\ApiaryDeleteForm",
 *     },
 *     "access" = "Drupal\hivelog\ApiaryAccessControlHandler",
 *   },
 *   base_table = "hivelog_apiary",
 *   admin_permission = "administer hivelog",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "canonical" = "/admin/hivelog/apiary/{apiary}",
 *     "add-form" = "/admin/hivelog/apiary/add",
 *     "edit-form" = "/admin/hivelog/apiary/{apiary}/edit",
 *     "delete-form" = "/admin/hivelog/apiary/{apiary}/delete",
 *     "collection" = "/admin/hivelog",
 *   },
 * )
 */
class Apiary extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

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
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
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
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['geolocation'] = BaseFieldDefinition::create('geofield')
      ->setLabel(t('Geolocation'))
      ->setDescription(t('GPS coordinates of the apiary.'))
      ->setDisplayOptions('form', [
        'type' => 'geofield_latlon',
        'weight' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
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
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user who owns this apiary.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 5,
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
