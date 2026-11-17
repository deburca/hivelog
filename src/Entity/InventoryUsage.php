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
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\hivelog\InventoryUsageAccessControlHandler;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Inventory Usage entity.
 *
 * An InventoryUsage is the "actual" half of inventory tracking, per
 * docs/project-management/decisions/0027-inventory-tracking-and-depreciation.md:
 * how much of a consumable InventoryItem was really used when a
 * HiveActionLog or ApiaryActionLog was reported `done`, as opposed to
 * CalendarActionItemRequirement's "recipe" estimate. Exactly one of
 * `hive_action_log` / `apiary_action_log` is set. Durable items are never
 * recorded here — their cost is fully accounted for via depreciation
 * regardless of usage frequency.
 *
 * There is no dedicated add/edit/delete UI for this entity — rows are
 * created, updated, and removed entirely as a side effect of saving a
 * HiveActionLogForm/ApiaryActionLogForm (see InventoryUsageFormTrait),
 * mirroring how a HiveInspection can be auto-created from a "done"
 * report (see HiveActionLogForm::createLinkedInspection()).
 */
#[ContentEntityType(
  id: 'inventory_usage',
  label: new TranslatableMarkup('Inventory Usage'),
  label_collection: new TranslatableMarkup('Inventory Usage'),
  label_singular: new TranslatableMarkup('inventory usage record'),
  label_plural: new TranslatableMarkup('inventory usage records'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'access' => InventoryUsageAccessControlHandler::class,
  ],
  base_table: 'hivelog_inventory_usage',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
)]
class InventoryUsage extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    $item = $this->get('item')->entity;
    return t('@item — @quantity @unit', [
      '@item' => $item ? $item->label() : t('Unknown item'),
      '@quantity' => $this->get('quantity')->value ?? '0',
      '@unit' => $item ? $item->get('unit')->value : '',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Defensive invariant: exactly one of hive_action_log/apiary_action_log
    // must be set — mirrors the parallel-siblings style HiveActionLog/
    // ApiaryActionLog already use instead of a polymorphic reference.
    $hive_log_id = $this->get('hive_action_log')->target_id;
    $apiary_log_id = $this->get('apiary_action_log')->target_id;
    if (($hive_log_id && $apiary_log_id) || (!$hive_log_id && !$apiary_log_id)) {
      throw new \InvalidArgumentException('Inventory usage must be linked to exactly one of a hive action log or an apiary action log.');
    }

    // Defensive invariant: only consumable items are ever consumed by a
    // usage record — durable items are checklist reminders on the
    // recipe, never a transaction (see ADR-0027).
    $item = $this->get('item')->entity;
    if (!$item || $item->get('item_type')->value !== 'consumable') {
      throw new \InvalidArgumentException('Inventory usage can only be recorded against a consumable inventory item.');
    }

    // Snapshot the weighted-average unit cost at creation time only —
    // once set, it stays fixed even if the quantity is edited later or
    // new purchases change the average, per ADR-0027's immutable-record
    // decision. This is what keeps a past cost report stable even after
    // a purchase price is corrected or a backdated purchase is added.
    if ($this->isNew() || $this->get('unit_cost_snapshot')->isEmpty()) {
      $this->set('unit_cost_snapshot', $item->getWeightedAverageUnitCost());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['item'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Item'))
      ->setDescription(t('The consumable inventory item that was used.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'inventory_item')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['quantity'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Quantity'))
      ->setDescription(t('How much of the item was actually used, in the item\'s own unit.'))
      ->setRequired(TRUE)
      ->setSetting('precision', 10)
      ->setSetting('scale', 3)
      ->setSetting('min', 0)
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'number_decimal', 'weight' => 1])
      ->setDisplayConfigurable('view', TRUE);

    $fields['hive_action_log'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Hive Action Log'))
      ->setDescription(t('The hive action log this usage was recorded against, if hive-scoped.'))
      ->setSetting('target_type', 'hive_action_log')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['apiary_action_log'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Apiary Action Log'))
      ->setDescription(t('The apiary action log this usage was recorded against, if apiary-scoped.'))
      ->setSetting('target_type', 'apiary_action_log')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['unit_cost_snapshot'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Unit Cost Snapshot'))
      ->setDescription(t('The weighted-average purchase cost per unit at the time this usage was recorded. Never recalculated after creation.'))
      ->setSetting('precision', 12)
      ->setSetting('scale', 4)
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'number_decimal', 'weight' => 4])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user who recorded this usage.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time this usage record was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time this usage record was last updated.'));

    return $fields;
  }

}
