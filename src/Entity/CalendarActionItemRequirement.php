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
use Drupal\hivelog\CalendarActionItemRequirementAccessControlHandler;
use Drupal\hivelog\Form\CalendarActionItemRequirementDeleteForm;
use Drupal\hivelog\Form\CalendarActionItemRequirementForm;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Calendar Action Item Requirement entity.
 *
 * A CalendarActionItemRequirement is one "recipe" line: it declares that a
 * CalendarAction (e.g. "Varroa Treatment (Spring)") typically requires a
 * given quantity of an InventoryItem — per hive occurrence for a
 * hive-scoped action, per apiary occurrence for an apiary-scoped one
 * (scope is read from the parent CalendarAction, not duplicated here).
 * This is the "plan" half of inventory usage; InventoryUsage (task 0031)
 * is the "actual" half recorded when an action is reported done.
 *
 * See docs/project-management/decisions/0027-inventory-tracking-and-depreciation.md
 * for the full design.
 */
#[ContentEntityType(
  id: 'calendar_action_item_requirement',
  label: new TranslatableMarkup('Calendar Action Item Requirement'),
  label_collection: new TranslatableMarkup('Calendar Action Item Requirements'),
  label_singular: new TranslatableMarkup('calendar action item requirement'),
  label_plural: new TranslatableMarkup('calendar action item requirements'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'form' => [
      'default' => CalendarActionItemRequirementForm::class,
      'add' => CalendarActionItemRequirementForm::class,
      'edit' => CalendarActionItemRequirementForm::class,
      'delete' => CalendarActionItemRequirementDeleteForm::class,
    ],
    'access' => CalendarActionItemRequirementAccessControlHandler::class,
  ],
  base_table: 'hivelog_calendar_action_item_requirement',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'edit-form' => '/hivelog/calendar-action-requirement/{calendar_action_item_requirement}/edit',
    'delete-form' => '/hivelog/calendar-action-requirement/{calendar_action_item_requirement}/delete',
  ],
)]
class CalendarActionItemRequirement extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    $item = $this->get('item')->entity;
    return t('@item × @quantity @unit', [
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

    // Defensive invariant: a requirement's item must belong to the same
    // apiary as its calendar action. CalendarActionItemRequirementForm::
    // validateForm() already blocks this at the UI layer; this guards
    // programmatic creation too, matching CalendarAction::preSave()'s
    // week_end >= week_start guard style.
    $item = $this->get('item')->entity;
    $calendar_action = $this->get('calendar_action')->entity;
    if ($item && $calendar_action && (int) $item->get('apiary')->target_id !== (int) $calendar_action->get('apiary')->target_id) {
      throw new \InvalidArgumentException('A calendar action item requirement\'s item must belong to the same apiary as its calendar action.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['calendar_action'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Calendar Action'))
      ->setDescription(t('The calendar action this requirement belongs to.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'calendar_action')
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
      ->setDescription(t('The inventory item required.'))
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

    $fields['quantity'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Quantity'))
      ->setDescription(t('How much of the item is typically needed — per hive for a hive-scoped action, per apiary for an apiary-scoped one — in the item\'s own unit.'))
      ->setRequired(TRUE)
      ->setSetting('precision', 10)
      ->setSetting('scale', 3)
      ->setSetting('min', 0)
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => 2])
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'number_decimal', 'weight' => 2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user who added this requirement.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the requirement was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the requirement was last updated.'));

    return $fields;
  }

}
