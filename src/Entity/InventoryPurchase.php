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
use Drupal\hivelog\Form\InventoryPurchaseDeleteForm;
use Drupal\hivelog\Form\InventoryPurchaseForm;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\hivelog\InventoryPurchaseAccessControlHandler;
use Drupal\hivelog\InventoryPurchaseListBuilder;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Inventory Purchase entity.
 *
 * An InventoryPurchase is one acquisition record for an InventoryItem — the
 * single source of truth for "amount bought and unit price". Stock on hand
 * and depreciation are always computed from these records (and
 * InventoryUsage, for consumables), never stored as a running balance.
 *
 * See docs/project-management/decisions/0027-inventory-tracking-and-depreciation.md
 * for the full design.
 */
#[ContentEntityType(
  id: 'inventory_purchase',
  label: new TranslatableMarkup('Inventory Purchase'),
  label_collection: new TranslatableMarkup('Inventory Purchases'),
  label_singular: new TranslatableMarkup('inventory purchase'),
  label_plural: new TranslatableMarkup('inventory purchases'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'list_builder' => InventoryPurchaseListBuilder::class,
    'form' => [
      'default' => InventoryPurchaseForm::class,
      'add' => InventoryPurchaseForm::class,
      'edit' => InventoryPurchaseForm::class,
      'delete' => InventoryPurchaseDeleteForm::class,
    ],
    'access' => InventoryPurchaseAccessControlHandler::class,
  ],
  base_table: 'hivelog_inventory_purchase',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'canonical' => '/hivelog/inventory-purchase/{inventory_purchase}',
    'add-form' => '/hivelog/inventory-purchase/add',
    'edit-form' => '/hivelog/inventory-purchase/{inventory_purchase}/edit',
    'delete-form' => '/hivelog/inventory-purchase/{inventory_purchase}/delete',
    'collection' => '/hivelog/inventory-purchases',
  ],
)]
class InventoryPurchase extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    $item = $this->get('item')->entity;
    return t('@item — @quantity @unit (@date)', [
      '@item' => $item ? $item->label() : t('Unknown item'),
      '@quantity' => $this->get('quantity')->value ?? '0',
      '@unit' => $item ? $item->get('unit')->value : '',
      '@date' => $this->get('purchase_date')->value ?: t('unknown date'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Auto-derive total_cost so it never has to be entered or kept in sync
    // by hand — same pattern as Queen::preSave() deriving queen_colour.
    $quantity = $this->get('quantity')->value;
    $unit_price = $this->get('unit_price')->value;
    if ($quantity !== NULL && $unit_price !== NULL) {
      $this->set('total_cost', (string) ((float) $quantity * (float) $unit_price));
    }

    // Defensive invariant: a purchase's item must belong to the same
    // apiary as the purchase itself. InventoryPurchaseForm::validateForm()
    // already blocks this at the UI layer; this guards programmatic
    // creation too, matching CalendarAction::preSave()'s
    // week_end >= week_start guard style.
    $item = $this->get('item')->entity;
    $apiary_id = $this->get('apiary')->target_id;
    if ($item && $apiary_id && (int) $item->get('apiary')->target_id !== (int) $apiary_id) {
      throw new \InvalidArgumentException('An inventory purchase\'s item must belong to the same apiary as the purchase.');
    }

    // Defensive invariants for the disposal event (task 0049): only
    // meaningful for a durable item — depreciation is what disposal
    // actually affects, and consumables aren't depreciated — and can't
    // predate the purchase itself. InventoryPurchaseForm::validateForm()
    // already blocks both at the UI layer; this guards programmatic
    // creation too.
    $disposal_date = $this->get('disposal_date')->value;
    if ($disposal_date) {
      if ($item && $item->get('item_type')->value !== 'durable') {
        throw new \InvalidArgumentException('A disposal date can only be recorded for a purchase of a durable item.');
      }
      $purchase_date = $this->get('purchase_date')->value;
      if ($purchase_date && $disposal_date < $purchase_date) {
        throw new \InvalidArgumentException('A purchase\'s disposal date cannot be before its purchase date.');
      }
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
      ->setDescription(t('The apiary this purchase was made for.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'apiary')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['item'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Item'))
      ->setDescription(t('The inventory item that was purchased.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'inventory_item')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['purchase_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Purchase Date'))
      ->setDescription(t('The date this purchase was made.'))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'date')
      ->setDisplayOptions('form', ['type' => 'datetime_default', 'weight' => 2])
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'datetime_default', 'weight' => 2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['quantity'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Quantity'))
      ->setDescription(t('How much was bought, in the item\'s unit.'))
      ->setRequired(TRUE)
      ->setSetting('precision', 10)
      ->setSetting('scale', 3)
      ->setSetting('min', 0)
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => 3])
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'number_decimal', 'weight' => 3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['unit_price'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Unit Price'))
      ->setDescription(t('Cost per unit at the time of this purchase.'))
      ->setRequired(TRUE)
      ->setSetting('precision', 10)
      ->setSetting('scale', 2)
      ->setSetting('min', 0)
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => 4])
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'number_decimal', 'weight' => 4])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['total_cost'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Total Cost'))
      ->setDescription(t('Automatically calculated as quantity × unit price.'))
      ->setSetting('precision', 12)
      ->setSetting('scale', 2)
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'number_decimal', 'weight' => 5])
      ->setDisplayConfigurable('view', TRUE);

    $fields['supplier'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Supplier'))
      ->setDescription(t('Optional: who this was bought from.'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 6])
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'string', 'weight' => 6])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('General notes about this purchase.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 7,
        'settings' => ['rows' => 4],
      ])
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'basic_string', 'weight' => 7])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['disposal_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Disposal Date'))
      ->setDescription(t('Optional, durable items only: the date this purchase was disposed of/retired/written off. Once set, it stops counting toward depreciation for years after it, even if still inside the useful-life window.'))
      ->setSetting('datetime_type', 'date')
      ->setDisplayOptions('form', ['type' => 'datetime_default', 'weight' => 8])
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'datetime_default', 'weight' => 8])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['disposal_reason'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Disposal Reason'))
      ->setDescription(t('Optional: why/how this purchase was disposed of, e.g. broken, lost, retired early.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 9,
        'settings' => ['rows' => 3],
      ])
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'basic_string', 'weight' => 9])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user who recorded this purchase.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the purchase record was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the purchase record was last updated.'));

    return $fields;
  }

}
