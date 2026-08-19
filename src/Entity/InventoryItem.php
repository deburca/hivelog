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
use Drupal\hivelog\Form\InventoryItemDeleteForm;
use Drupal\hivelog\Form\InventoryItemForm;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\hivelog\InventoryItemListBuilder;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Inventory Item entity.
 *
 * An InventoryItem is one catalog entry for something a beekeeper buys and
 * uses — sugar, varroa strips, jars, frames — scoped to a single apiary.
 * `item_type` is the key branch: `consumable` items are tracked via
 * purchases (`InventoryPurchase`) and usage (`InventoryUsage`); `durable`
 * items are purchased once and depreciate over `useful_life_years` instead
 * of being consumed.
 *
 * See docs/project-management/decisions/0027-inventory-tracking-and-depreciation.md
 * for the full design.
 */
#[ContentEntityType(
  id: 'inventory_item',
  label: new TranslatableMarkup('Inventory Item'),
  label_collection: new TranslatableMarkup('Inventory Items'),
  label_singular: new TranslatableMarkup('inventory item'),
  label_plural: new TranslatableMarkup('inventory items'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'list_builder' => InventoryItemListBuilder::class,
    'form' => [
      'default' => InventoryItemForm::class,
      'add' => InventoryItemForm::class,
      'edit' => InventoryItemForm::class,
      'delete' => InventoryItemDeleteForm::class,
    ],
  ],
  base_table: 'hivelog_inventory_item',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'label' => 'name',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'canonical' => '/hivelog/inventory-item/{inventory_item}',
    'edit-form' => '/hivelog/inventory-item/{inventory_item}/edit',
    'delete-form' => '/hivelog/inventory-item/{inventory_item}/delete',
  ],
)]
class InventoryItem extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Defensive invariant: a durable item must declare a useful life, since
    // depreciation cannot be computed without it. InventoryItemForm::
    // validateForm() already blocks this at the UI layer; this guards
    // programmatic creation too, matching CalendarAction::preSave()'s
    // week_end >= week_start guard style.
    $item_type = $this->get('item_type')->value;
    $useful_life_years = $this->get('useful_life_years')->value;
    if ($item_type === 'durable' && empty($useful_life_years)) {
      throw new \InvalidArgumentException('A durable inventory item must have a useful life (in years) set.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['apiary'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Apiary'))
      ->setDescription(t('The apiary this inventory item belongs to.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'apiary')
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

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('A short name for this item, e.g. "Granulated Sugar" or "Apivar Strips".'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['category'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Category'))
      ->setDescription(t('An optional classification for this item.'))
      ->setSetting('allowed_values', [
        'feed' => 'Feed',
        'treatment' => 'Treatment',
        'packaging' => 'Packaging',
        'equipment' => 'Equipment',
        'other' => 'Other',
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

    $fields['unit'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Unit'))
      ->setDescription(t('The unit this item is measured in, e.g. "kg", "L", "strip", "jar", "frame". Pick one unit and use it consistently for this item — there is no unit conversion.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 32)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['item_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Type'))
      ->setDescription(t('Consumable items (e.g. sugar) are used up and tracked via purchases and usage. Durable items (e.g. frames) are purchased once and depreciate over their useful life instead.'))
      ->setRequired(TRUE)
      ->setDefaultValue('consumable')
      ->setSetting('allowed_values', [
        'consumable' => 'Consumable',
        'durable' => 'Durable',
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

    $fields['useful_life_years'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Useful Life (years)'))
      ->setDescription(t('Required for durable items: how many years this item is depreciated over.'))
      ->setSetting('min', 1)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 5,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('Discontinued items are hidden from new purchase/requirement selection, but remain listed here for management.'))
      ->setRequired(TRUE)
      ->setDefaultValue('active')
      ->setSetting('allowed_values', [
        'active' => 'Active',
        'discontinued' => 'Discontinued',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user who created this inventory item.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the inventory item was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the inventory item was last updated.'));

    return $fields;
  }

}
