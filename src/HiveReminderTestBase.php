<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog;

use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\KernelTestBase;
use Drupal\hivelog\Form\HiveReminderForm;
use Drupal\hivelog\Form\HivelogEntityDeleteForm;
use Drupal\hivelog\HiveReminderAccessControlHandler;
use Drupal\hivelog\HiveReminderListBuilder;

/**
 * Base class for HiveReminder kernel tests.
 *
 * Registers the hive_reminder entity type definition in the container before
 * modules are enabled, so that routes, access checks and schema installation
 * all work correctly in test environments where update hooks do not run.
 */
abstract class HiveReminderTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->registerHiveReminderEntityType();
    parent::setUp();
  }

  /**
   * Manually register the hive_reminder entity type.
   */
  protected function registerHiveReminderEntityType(): void {
    $entity_type = ContentEntityType::buildEntityType(
      'hive_reminder',
      'hivelog_hive_reminder',
      '\Drupal\hivelog\HiveReminderStorage',
      '\Drupal\hivelog\HiveReminder',
      NULL,
      ['install' => TRUE],
      '/hivelog/hive-reminder/{hive_reminder}',
      'list_builder_class' => HiveReminderListBuilder::class,
    );

    $reflection = new \ReflectionClass($entity_type);

    $prop = $reflection->getProperty('label');
    $prop->setAccessible(TRUE);
    $prop->setValue($entity_type, new TranslatableMarkup('Hive reminder'));

    $prop = $reflection->getProperty('label_collection');
    $prop->setAccessible(TRUE);
    $prop->setValue($entity_type, new TranslatableMarkup('Hive reminders'));

    $prop = $reflection->getProperty('admin_permission');
    $prop->setAccessible(TRUE);
    $prop->setValue($entity_type, 'administer hivelog');

    $prop = $reflection->getProperty('module');
    $prop->setAccessible(TRUE);
    $prop->setValue($entity_type, 'hivelog');

    $prop = $reflection->getProperty('entity_keys');
    $prop->setAccessible(TRUE);
    $prop->setValue($entity_type, [
      'id' => 'id',
      'label' => 'title',
      'uuid' => 'uuid',
      'owner' => 'uid',
    ]);

    $prop = $reflection->getProperty('links');
    $prop->setAccessible(TRUE);
    $prop->setValue($entity_type, [
      'canonical' => '/hivelog/hive-reminder/{hive_reminder}',
      'edit-form' => '/hivelog/hive-reminder/{hive_reminder}/edit',
      'delete-form' => '/hivelog/hive-reminder/{hive_reminder}/delete',
      'collection' => '/hivelog/hive-reminders',
    ]);

    $prop = $reflection->getProperty('handlers');
    $prop->setAccessible(TRUE);
    $prop->setValue($entity_type, [
      'storage' => ContentEntityStorageInterface::class,
      'form' => [
        'default' => HiveReminderForm::class,
        'add' => HiveReminderForm::class,
        'edit' => HiveReminderForm::class,
        'delete' => HivelogEntityDeleteForm::class,
      ],
      'access' => HiveReminderAccessControlHandler::class,
    ]);

    \Drupal::service('entity_type.manager')->addDefinition($entity_type);
  }

}
