<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Installs the HiveReminder entity type and schema for kernel test environments.
 */
class HiveReminderEntitySchemaInstaller {

  /**
   * Register the hive_reminder entity type in the container.
   */
  public static function registerEntityType(): void {
    try {
      $manager = \Drupal::service('entity_type.manager');
    }
    catch (\Throwable) {
      return;
    }
    // Already registered.
    try {
      if ($manager->getDefinition('hive_reminder') !== NULL) {
        return;
      }
    }
    catch (PluginNotFoundException) {
      // Expected - not yet in the cache.
    }

    // Manually build the entity type definition from annotation metadata.
    $entity_type = ContentEntityType::buildEntityType(
      'hive_reminder',
      'hivelog_hive_reminder',
      '\Drupal\Core\Entity\ContentEntityStorageInterface',
      '\Drupal\hivelog\HiveReminder',
      NULL,
      ['install' => TRUE],
      '/hivelog/hive-reminder/{hive_reminder}',
    );

    // Set properties that buildEntityType doesn't populate.
    $reflection = new \ReflectionClass($entity_type);

    self::setStringProperty($reflection, $entity_type, 'label', 'Hive reminder');
    self::setStringProperty($reflection, $entity_type, 'label_collection', 'Hive reminders');
    self::setStringProperty($reflection, $entity_type, 'admin_permission', 'administer hivelog');
    self::setStringProperty($reflection, $entity_type, 'module', 'hivelog');

    // Set entity_keys.
    $prop = $reflection->getProperty('entity_keys');
    $prop->setAccessible(TRUE);
    $prop->setValue($entity_type, [
      'id' => 'id',
      'label' => 'title',
      'uuid' => 'uuid',
      'owner' => 'uid',
    ]);

    // Set links.
    $prop = $reflection->getProperty('links');
    $prop->setAccessible(TRUE);
    $prop->setValue($entity_type, [
      'canonical' => '/hivelog/hive-reminder/{hive_reminder}',
      'edit-form' => '/hivelog/hive-reminder/{hive_reminder}/edit',
      'delete-form' => '/hivelog/hive-reminder/{hive_reminder}/delete',
      'collection' => '/hivelog/hive-reminders',
    ]);

    // Set handlers.
    $prop = $reflection->getProperty('handlers');
    $prop->setAccessible(TRUE);
    $prop->setValue($entity_type, [
      'storage' => '\Drupal\Core\Entity\ContentEntityStorageInterface',
      'form' => [
        'default' => '\Drupal\hivelog\Form\HiveReminderForm',
        'add' => '\Drupal\hivelog\Form\HiveReminderForm',
        'edit' => '\Drupal\hivelog\Form\HiveReminderForm',
        'delete' => '\Drupal\hivelog\Form\HivelogEntityDeleteForm',
      ],
      'access' => '\Drupal\hivelog\HiveReminderAccessControlHandler',
    ]);

    // Set list_builder_class.
    $prop = $reflection->getProperty('list_builder_class');
    $prop->setAccessible(TRUE);
    $prop->setValue($entity_type, '\Drupal\hivelog\HiveReminderListBuilder');

    $manager->addDefinition($entity_type);
  }

  /**
   * Helper: set a TranslatableMarkup property on the entity type.
   */
  private static function setStringProperty(\ReflectionClass $reflection, object $entity_type, string $property, string $text): void {
    $prop = $reflection->getProperty($property);
    $prop->setAccessible(TRUE);
    $prop->setValue($entity_type, new TranslatableMarkup($text));
  }

}
