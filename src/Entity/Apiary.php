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
use Drupal\hivelog\ApiaryAccessControlHandler;
use Drupal\hivelog\ApiaryListBuilder;
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
    'canonical' => '/admin/hivelog/apiary/{apiary}',
    'add-form' => '/admin/hivelog/apiary/add',
    'edit-form' => '/admin/hivelog/apiary/{apiary}/edit',
    'delete-form' => '/admin/hivelog/apiary/{apiary}/delete',
    'collection' => '/admin/hivelog',
  ],
)]
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
