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
use Drupal\hivelog\CalendarActionProductYieldAccessControlHandler;
use Drupal\hivelog\Form\CalendarActionProductYieldDeleteForm;
use Drupal\hivelog\Form\CalendarActionProductYieldForm;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Calendar Action Product Yield entity.
 *
 * A CalendarActionProductYield is one "recipe" line: it declares that a
 * CalendarAction (e.g. "Harvest Summer Honey") typically produces a given
 * quantity of a Product — per hive occurrence for a hive-scoped action,
 * per apiary occurrence for an apiary-scoped one (scope is read from the
 * parent CalendarAction, not duplicated here). This is the "plan" half of
 * yield tracking, mirroring CalendarActionItemRequirement one level
 * removed (outputs instead of inputs); HarvestYield (task 0037) is the
 * "actual" half recorded when an action is reported done.
 *
 * See docs/project-management/decisions/0034-honey-wax-propolis-yield-and-potential-income.md
 * for the full design.
 */
#[ContentEntityType(
  id: 'calendar_action_product_yield',
  label: new TranslatableMarkup('Calendar Action Product Yield'),
  label_collection: new TranslatableMarkup('Calendar Action Product Yields'),
  label_singular: new TranslatableMarkup('calendar action product yield'),
  label_plural: new TranslatableMarkup('calendar action product yields'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'form' => [
      'default' => CalendarActionProductYieldForm::class,
      'add' => CalendarActionProductYieldForm::class,
      'edit' => CalendarActionProductYieldForm::class,
      'delete' => CalendarActionProductYieldDeleteForm::class,
    ],
    'access' => CalendarActionProductYieldAccessControlHandler::class,
  ],
  base_table: 'hivelog_calendar_action_product_yield',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'edit-form' => '/hivelog/calendar-action-yield/{calendar_action_product_yield}/edit',
    'delete-form' => '/hivelog/calendar-action-yield/{calendar_action_product_yield}/delete',
  ],
)]
class CalendarActionProductYield extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    $product = $this->get('product')->entity;
    return t('@product × @quantity @unit', [
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

    // Defensive invariant: a yield's product must belong to the same
    // apiary as its calendar action. CalendarActionProductYieldForm::
    // validateForm() already blocks this at the UI layer; this guards
    // programmatic creation too, matching
    // CalendarActionItemRequirement::preSave()'s identical guard.
    $product = $this->get('product')->entity;
    $calendar_action = $this->get('calendar_action')->entity;
    if ($product && $calendar_action && (int) $product->get('apiary')->target_id !== (int) $calendar_action->get('apiary')->target_id) {
      throw new \InvalidArgumentException('A calendar action product yield\'s product must belong to the same apiary as its calendar action.');
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
      ->setDescription(t('The calendar action this yield belongs to.'))
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

    $fields['product'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Product'))
      ->setDescription(t('The product typically produced.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'product')
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
      ->setDescription(t('How much of the product is typically produced — per hive for a hive-scoped action, per apiary for an apiary-scoped one — in the product\'s own unit.'))
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
      ->setDescription(t('The user who added this yield.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the yield was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the yield was last updated.'));

    return $fields;
  }

}
