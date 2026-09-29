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
use Drupal\hivelog\Form\HiveComponentForm;
use Drupal\hivelog\Form\HivelogEntityDeleteForm;
use Drupal\hivelog\HiveComponentAccessControlHandler;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Hive Component entity.
 *
 * A HiveComponent is one "this hive is built from N of this catalog item"
 * line — e.g. "1 × Base Plate (Wood)", "2 × 10×12 Brood Chamber (Wood,
 * empty)" — mirroring CalendarActionItemRequirement's "recipe" shape
 * (hive + item + quantity) almost exactly. `Hive::getEmptyWeightKg()`
 * sums `quantity × item.weight_kg` across a hive's components.
 *
 * Unlike CalendarActionItemRequirement.quantity (decimal — a consumable
 * amount can be fractional), `quantity` here is an **integer**: hive
 * components are discrete, countable equipment, not a fractional amount
 * ("1.5 brood chambers" is not a real quantity).
 *
 * A component can never be assigned past how many units of that catalog
 * item actually exist — see `InventoryItem::getAvailableForHiveAssignmentQuantity()`
 * and `preSave()` below.
 *
 * See docs/project-management/decisions/0106-hive-component-weight-tracking.md
 * for the full design.
 */
#[ContentEntityType(
  id: 'hive_component',
  label: new TranslatableMarkup('Hive Component'),
  label_collection: new TranslatableMarkup('Hive Components'),
  label_singular: new TranslatableMarkup('hive component'),
  label_plural: new TranslatableMarkup('hive components'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'form' => [
      'default' => HiveComponentForm::class,
      'add' => HiveComponentForm::class,
      'edit' => HiveComponentForm::class,
      'delete' => HivelogEntityDeleteForm::class,
    ],
    'access' => HiveComponentAccessControlHandler::class,
  ],
  base_table: 'hivelog_hive_component',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'edit-form' => '/hivelog/hive-component/{hive_component}/edit',
    'delete-form' => '/hivelog/hive-component/{hive_component}/delete',
  ],
)]
class HiveComponent extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    $item = $this->get('item')->entity;
    return t('@quantity × @item', [
      '@quantity' => $this->get('quantity')->value ?? '0',
      '@item' => $item ? $item->label() : t('Unknown item'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    /** @var \Drupal\hivelog\Entity\InventoryItem|null $item */
    $item = $this->get('item')->entity;
    $hive = $this->get('hive')->entity;

    // Defensive invariant: a component's item must belong to the same
    // apiary as its hive. HiveComponentForm::validateForm() already
    // blocks this at the UI layer; this guards programmatic creation
    // too, matching CalendarActionItemRequirement::preSave()'s identical
    // same-apiary guard.
    if ($item && $hive && (int) $item->get('apiary')->target_id !== (int) $hive->get('apiary')->target_id) {
      throw new \InvalidArgumentException('A hive component\'s item must belong to the same apiary as its hive.');
    }

    // Defensive invariant (ADR-0106 §2 Amendment): a component can never
    // be assigned past how many units of that catalog item actually
    // exist. HiveComponentForm::validateForm() already blocks this at
    // the UI layer; this guards programmatic creation too. Excludes this
    // row's own prior quantity from the "already assigned" count on an
    // edit, so re-saving an unchanged (or reduced) quantity never
    // spuriously fails against itself.
    if ($item) {
      $quantity = (float) ($this->get('quantity')->value ?? 0);
      $exclude_id = $this->isNew() ? NULL : (int) $this->id();
      $available = $item->getAvailableForHiveAssignmentQuantity($exclude_id);
      if ($quantity > $available) {
        throw new \InvalidArgumentException(sprintf(
          'Cannot assign %s of "%s" — only %s available.',
          rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.'),
          $item->label(),
          rtrim(rtrim(number_format($available, 3, '.', ''), '0'), '.')
        ));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['hive'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Hive'))
      ->setDescription(t('The hive this component belongs to.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'hive')
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
      ->setDescription(t('The inventory item this hive is built from.'))
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

    $fields['quantity'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Quantity'))
      ->setDescription(t('How many of this item this hive is built from — a discrete count (e.g. 2 brood chambers), not a fractional amount.'))
      ->setRequired(TRUE)
      ->setDefaultValue(1)
      ->setSetting('min', 1)
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => 2])
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'number_integer', 'weight' => 2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user who added this component.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time this component was added.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time this component was last updated.'));

    return $fields;
  }

}
