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
use Drupal\hivelog\Form\HarvestYieldForm;
use Drupal\hivelog\Form\HivelogEntityDeleteForm;
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
 * A yield reported with a planned action has no form of its own: its row is
 * created, updated and removed as a side effect of saving a
 * HiveActionLogForm/ApiaryActionLogForm (see HarvestYieldFormTrait),
 * mirroring InventoryUsage's own system-managed rows.
 *
 * A harvest can also be recorded **directly** (task 0271), with no calendar
 * action behind it: taking supers off outside the planned weeks, cut comb,
 * a little propolis. Such a row has no log; it carries its own `apiary`
 * (always), `hive` (when it came from one hive) and `harvest_date`, and has
 * add, edit and delete forms (HarvestYieldForm). `isDirect()` tells the two
 * kinds apart. Its `apiary` is a stamp like a hive action log's: set when
 * the row is created and never changed, so a hive that moves later leaves
 * the harvest with the apiary it was taken in.
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
    'form' => [
      'add' => HarvestYieldForm::class,
      'edit' => HarvestYieldForm::class,
      'delete' => HivelogEntityDeleteForm::class,
    ],
  ],
  base_table: 'hivelog_harvest_yield',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'edit-form' => '/hivelog/harvest/{harvest_yield}/edit',
    'delete-form' => '/hivelog/harvest/{harvest_yield}/delete',
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
      // "7.5", not the stored "7.500": the label is read in breadcrumbs,
      // messages and the delete form's title.
      '@quantity' => static::trimQuantity((string) ($this->get('quantity')->value ?? '0')),
      '@unit' => $product ? $product->get('unit')->value : '',
    ]);
  }

  /**
   * Drops a decimal quantity's trailing zeros: "7.500" reads "7.5".
   */
  public static function trimQuantity(string $quantity): string {
    return str_contains($quantity, '.') ? rtrim(rtrim($quantity, '0'), '.') : $quantity;
  }

  /**
   * Whether this harvest was recorded directly, not with an action log.
   */
  public function isDirect(): bool {
    return !$this->get('hive_action_log')->target_id && !$this->get('apiary_action_log')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Defensive invariant: a yield has exactly one source. Either one of
    // hive_action_log/apiary_action_log (mirroring the parallel-siblings
    // style HiveActionLog/ApiaryActionLog use instead of a polymorphic
    // reference), or neither and an apiary of its own: a harvest recorded
    // directly (task 0271).
    $hive_log_id = $this->get('hive_action_log')->target_id;
    $apiary_log_id = $this->get('apiary_action_log')->target_id;
    if ($hive_log_id && $apiary_log_id) {
      throw new \InvalidArgumentException('Harvest yield must be linked to exactly one of a hive action log or an apiary action log.');
    }
    if ($this->isDirect()) {
      $this->prepareDirectHarvest();
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
   * The stored version of this harvest, while it is being saved.
   *
   * `getOriginal()` exists from Drupal 11.2, where reading the `original`
   * property is deprecated; the module supports 11.0 and 11.1 too.
   */
  protected function originalHarvest(): ?self {
    $original = method_exists($this, 'getOriginal') ? $this->getOriginal() : ($this->original ?? NULL);
    return $original instanceof self ? $original : NULL;
  }

  /**
   * Completes and checks a harvest recorded directly (task 0271).
   *
   * The friendly errors are the field constraints'; these throws are the
   * backstop for a caller that never validates.
   */
  protected function prepareDirectHarvest(): void {
    $hive = $this->get('hive')->entity;
    $hive = $hive instanceof Hive ? $hive : NULL;
    if ($this->isNew()) {
      // The apiary is a stamp: taken from the hive when the harvest is
      // recorded, and not followed if the hive moves later.
      if ($hive && $this->get('apiary')->isEmpty()) {
        $this->set('apiary', $hive->get('apiary')->target_id);
      }
      if ($hive && (int) $hive->get('apiary')->target_id !== (int) $this->get('apiary')->target_id) {
        throw new \InvalidArgumentException('A harvest\'s hive must belong to the apiary the harvest is recorded in.');
      }
      if ($this->get('harvest_date')->isEmpty()) {
        $this->set('harvest_date', date('Y-m-d'));
      }
    }
    elseif (($original = $this->originalHarvest()) && (int) $original->get('apiary')->target_id !== (int) $this->get('apiary')->target_id) {
      throw new \InvalidArgumentException('A harvest\'s apiary cannot be changed.');
    }
    if ($this->get('apiary')->isEmpty()) {
      throw new \InvalidArgumentException('Harvest yield must be linked to a hive action log, an apiary action log or, when recorded directly, an apiary.');
    }
    $product = $this->get('product')->entity;
    if ($product instanceof Product && (int) $product->get('apiary')->target_id !== (int) $this->get('apiary')->target_id) {
      throw new \InvalidArgumentException('A harvest\'s product must belong to the apiary the harvest is recorded in.');
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
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 0])
      ->setDisplayConfigurable('view', TRUE);

    $fields['quantity'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Quantity'))
      ->setDescription(t('How much of the product was actually produced, in the product\'s own unit.'))
      ->setRequired(TRUE)
      ->setSetting('precision', 10)
      ->setSetting('scale', 3)
      ->setSetting('min', 0)
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'number_decimal', 'weight' => 1])
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => 1])
      ->setDisplayConfigurable('view', TRUE);

    // The next four are for a harvest recorded directly (task 0271); a
    // yield reported with an action log leaves them empty and takes its
    // apiary, hive and year from the log.
    $fields['harvest_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Date'))
      ->setDescription(t('The day of the harvest.'))
      ->setSetting('datetime_type', 'date')
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'datetime_default', 'weight' => 2])
      ->setDisplayOptions('form', ['type' => 'datetime_default', 'weight' => 2])
      ->setDisplayConfigurable('view', TRUE);

    $fields['apiary'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Apiary'))
      ->setDescription(t('The apiary a directly recorded harvest was taken in. Set when it is recorded and never changed.'))
      ->setSetting('target_type', 'apiary')
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'entity_reference_label', 'weight' => 5])
      ->setDisplayConfigurable('view', TRUE);

    $fields['hive'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Hive'))
      ->setDescription(t('The hive a directly recorded harvest came from, if it was one hive.'))
      ->setSetting('target_type', 'hive')
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'entity_reference_label', 'weight' => 6])
      ->setDisplayConfigurable('view', TRUE);

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'))
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'basic_string', 'weight' => 7])
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 3, 'settings' => ['rows' => 3]])
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

    // Create access is a global permission and cannot see which hive or
    // apiary a new harvest points at; these apply the scoped add routes'
    // own rule to every caller (tasks 0198/0200, see AGENTS.md).
    $fields['hive']->addConstraint('HivelogParentAccess');
    $fields['apiary']->addConstraint('HivelogParentAccess');
    $fields['product']->addConstraint('HivelogSameApiary', [
      'against' => 'apiary',
      'message' => 'The selected product must belong to the apiary this harvest is recorded in.',
    ]);

    return $fields;
  }

}
