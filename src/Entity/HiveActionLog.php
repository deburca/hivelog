<?php

declare(strict_types=1);

namespace Drupal\hivelog\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog\Form\HiveActionLogDeleteForm;
use Drupal\hivelog\Form\HiveActionLogForm;
use Drupal\hivelog\HiveActionLogAccessControlHandler;
use Drupal\hivelog\HiveActionLogListBuilder;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Hive Action Log entity.
 *
 * A HiveActionLog is the per-hive execution record against a
 * CalendarAction, for a given year — the "log" half of the seasonal
 * calendar. It starts unreported: absence of a row for a given
 * (hive, calendar_action, year), or a row with status = pending, both mean
 * "not yet reported". The beekeeper later reports it as `done` or
 * `ignored`. Multiple logs per (hive, calendar_action, year) are valid by
 * design — no uniqueness invariant is enforced.
 *
 * See docs/project-management/decisions/0025-seasonal-calendar-and-hive-action-tracking.md
 * for the full design.
 */
#[ContentEntityType(
  id: 'hive_action_log',
  label: new TranslatableMarkup('Hive Action Log'),
  label_collection: new TranslatableMarkup('Hive Action Logs'),
  label_singular: new TranslatableMarkup('hive action log'),
  label_plural: new TranslatableMarkup('hive action logs'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'list_builder' => HiveActionLogListBuilder::class,
    'form' => [
      'default' => HiveActionLogForm::class,
      'add' => HiveActionLogForm::class,
      'edit' => HiveActionLogForm::class,
      'delete' => HiveActionLogDeleteForm::class,
    ],
    'access' => HiveActionLogAccessControlHandler::class,
  ],
  base_table: 'hivelog_hive_action_log',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'canonical' => '/hivelog/hive-action-log/{hive_action_log}',
    'edit-form' => '/hivelog/hive-action-log/{hive_action_log}/edit',
    'delete-form' => '/hivelog/hive-action-log/{hive_action_log}/delete',
  ],
)]
class HiveActionLog extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    $calendar_action = $this->get('calendar_action')->entity;
    $hive = $this->get('hive')->entity;
    return t('@action for @hive (@year)', [
      '@action' => $calendar_action ? $calendar_action->label() : t('Unknown action'),
      '@hive' => $hive ? $hive->label() : t('Unknown hive'),
      '@year' => $this->get('year')->value ?: t('unknown year'),
    ]);
  }

  /**
   * Default value callback for the `year` field: the current year.
   *
   * @return int
   *   The current calendar year.
   */
  public static function getDefaultYear(FieldableEntityInterface $entity, FieldDefinitionInterface $definition): int {
    return (int) date('Y');
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['hive'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Hive'))
      ->setDescription(t('The hive this action was reported for.'))
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

    $fields['calendar_action'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Calendar Action'))
      ->setDescription(t('Which seasonal calendar action this log reports on.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'calendar_action')
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

    $fields['year'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Year'))
      ->setDescription(t('Which annual occurrence of the calendar action this log is for.'))
      ->setRequired(TRUE)
      ->setDefaultValueCallback(static::class . '::getDefaultYear')
      ->setSetting('min', 2000)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('Pending means not yet reported. Done and ignored are the two reporting outcomes.'))
      ->setRequired(TRUE)
      ->setDefaultValue('pending')
      ->setSetting('allowed_values', [
        'pending' => 'Pending',
        'done' => 'Done',
        'ignored' => 'Ignored',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['week_completed'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Week Completed'))
      ->setDescription(t('Optional: the ISO-8601 week (1-53) this action was actually carried out. Typically left empty for "ignored".'))
      ->setSetting('min', 1)
      ->setSetting('max', 53)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 4,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('Free text: product used, dose, why an item was ignored, observations.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 5,
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['inspection'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Linked Inspection'))
      ->setDescription(t('Set automatically when a "done" report also creates a hive inspection record. Never edited directly on this form.'))
      ->setSetting('target_type', 'hive_inspection')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Reported by'))
      ->setDescription(t('The user who reported this action.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the log was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the log was last updated.'));

    return $fields;
  }

}
