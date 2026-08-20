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
use Drupal\hivelog\HarvestYieldAccessControlHandler;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Harvest Yield entity.
 *
 * A HarvestYield is the "actual" half of yield tracking, per
 * docs/project-management/decisions/0034-honey-wax-propolis-yield-and-potential-income.md:
 * how much of a Product was really produced when a HiveActionLog or
 * ApiaryActionLog was reported `done`, as opposed to
 * CalendarActionProductYield's "recipe" estimate. Exactly one of
 * `hive_action_log` / `apiary_action_log` is set. Mirrors InventoryUsage
 * one level removed (outputs instead of inputs) — see that entity's
 * docblock for the identical shape this one follows.
 *
 * There is no dedicated add/edit/delete UI for this entity — rows are
 * created, updated, and removed entirely as a side effect of saving a
 * HiveActionLogForm/ApiaryActionLogForm (see HarvestYieldFormTrait),
 * mirroring InventoryUsage's own system-managed rows.
 */
#[ContentEntityType(
  id: 'harvest_yield',
  label: new TranslatableMarkup('Harvest Yield'),
  label_collection: new TranslatableMarkup('Harvest Yields'),
  label_singular: new TranslatableMarkup('harvest yield record'),
  label_plural: new TranslatableMarkup('harvest yield records'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'access' => HarvestYieldAccessControlHandler::class,
  ],
  base_table: 'hivelog_harvest_yield',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
)]
class HarvestYield extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    $product = $this->get('product')->entity;
    return t('@product — @quantity @unit', [
      '@product' => $product ? $product->label() : t('Unknown product'),
      '@quantity' => $this->get('quantity')->value ?? '0',
      '@unit' => $product ? $product->get('unit')->value : '',
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
      throw new \InvalidArgumentException('Harvest yield must be linked to exactly one of a hive action log or an apiary action log.');
    }

    // Snapshot the product's expected unit price at creation time only —
    // once set, it stays fixed even if the quantity is edited later or
    // the product's expected price changes, per ADR-0034's immutable-
    // snapshot decision. This is what keeps a past cost report's
    // potential-income figure stable even after the expected price is
    // revised.
    if ($this->isNew() || $this->get('unit_price_snapshot')->isEmpty()) {
      $product = $this->get('product')->entity;
      $this->set('unit_price_snapshot', $product ? $product->get('expected_unit_price')->value : 0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['product'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Product'))
      ->setDescription(t('The product that was actually produced.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'product')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['quantity'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Quantity'))
      ->setDescription(t('How much of the product was actually produced, in the product\'s own unit.'))
      ->setRequired(TRUE)
      ->setSetting('precision', 10)
      ->setSetting('scale', 3)
      ->setSetting('min', 0)
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'number_decimal', 'weight' => 1])
      ->setDisplayConfigurable('view', TRUE);

    $fields['hive_action_log'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Hive Action Log'))
      ->setDescription(t('The hive action log this yield was recorded against, if hive-scoped.'))
      ->setSetting('target_type', 'hive_action_log')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['apiary_action_log'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Apiary Action Log'))
      ->setDescription(t('The apiary action log this yield was recorded against, if apiary-scoped.'))
      ->setSetting('target_type', 'apiary_action_log')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['unit_price_snapshot'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Unit Price Snapshot'))
      ->setDescription(t('The product\'s expected unit price at the time this yield was recorded. Never recalculated after creation.'))
      ->setSetting('precision', 12)
      ->setSetting('scale', 4)
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'number_decimal', 'weight' => 4])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user who recorded this yield.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time this yield record was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time this yield record was last updated.'));

    return $fields;
  }

}
