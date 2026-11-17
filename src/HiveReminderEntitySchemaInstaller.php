<?php

declare(strict_types=1);

namespace Drupal\hivelog;

/**
 * Installs the HiveReminder entity type and schema for environments where
 * the annotation processor has not yet run (kernel test module_enable).
 */
class HiveReminderEntitySchemaInstaller {

  /**
   * Register the hive_reminder entity type in the container.
   */
  public static function registerEntityType(): void {
    $container = \Drupal::getContainer();
    if (!$container->hasDefinition('entity_type.manager')) {
      return;
    }

    $manager = $container->get('entity_type.manager');
    // Already registered.
    try {
      if ($manager->getDefinition('hive_reminder') !== NULL) {
        return;
      }
    }
    catch (\Drupal\Component\Plugin\Exception\PluginNotFoundException) {
      // Expected - not yet in the cache.
    }

    // Manually build the entity type definition from annotation metadata.
    $entity_type = \Drupal\Core\Entity\ContentEntityType::buildEntityType(
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
    $prop->setValue($entity_type, new \Drupal\Core\StringTranslation\TranslatableMarkup($text));
  }

}
