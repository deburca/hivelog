<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityOwnerInterface;
use Drupal\Core\Entity\EntityOwnerTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog\Form\HivelogEntityDeleteForm;
use Drupal\hivelog\Form\HiveReminderForm;

/**
 * A one-off, date-specific reminder for a single hive.
 *
 * Unlike `CalendarAction` (recurring, week-scheduled, apiary-wide), a
 * reminder is about one hive and one date, non-repeating until dismissed or
 * completed. It can be created from the hive page or at the end of an
 * inspection form ("Next visit").
 */
#[ContentEntityType(
  id: 'hive_reminder',
  label: new TranslatableMarkup('Hive reminder'),
  label_collection: new TranslatableMarkup('Hive reminders'),
  module: 'hivelog',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'label' => 'title',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'canonical' => '/hivelog/hive-reminder/{hive_reminder}',
    'edit-form' => '/hivelog/hive-reminder/{hive_reminder}/edit',
    'delete-form' => '/hivelog/hive-reminder/{hive_reminder}/delete',
    'collection' => '/hivelog/hive-reminders',
  ],
  list_builder_class: '\Drupal\hivelog\HiveReminderListBuilder',
  handlers: [
    'storage' => ContentEntityStorageInterface::class,
    'form' => [
      'default' => HiveReminderForm::class,
      'add' => HiveReminderForm::class,
      'edit' => HiveReminderForm::class,
      'delete' => HivelogEntityDeleteForm::class,
    ],
    'access' => HiveReminderAccessControlHandler::class,
  ],
  base_table: 'hivelog_hive_reminder',
)]
class HiveReminder extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['hive'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Hive'))
      ->setDescription(t('The hive this reminder is about.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'hive')
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

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('A short description of what to do.'))
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

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('Optional longer notes about this reminder.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['due_date'] = BaseFieldDefinition::create('date')
      ->setLabel(t('Due date'))
      ->setDescription(t('The date this reminder is due.'))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'date')
      ->setDisplayOptions('form', [
        'type' => 'date',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'datetime_default',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('Whether the reminder is pending, done or dismissed.'))
      ->setRequired(TRUE)
      ->setDefaultValue('pending')
      ->setSetting('allowed_values', [
        'pending' => 'Pending',
        'done' => 'Done',
        'dismissed' => 'Dismissed',
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

    $fields['uid']
      ->setLabel(t('Author'))
      ->setDescription(t('The author of this reminder.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the reminder was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the reminder was last updated.'));

    // Data-integrity constraints (task 0200).
    $fields['hive']->addConstraint('HivelogParentAccess');

    return $fields;
  }

}
