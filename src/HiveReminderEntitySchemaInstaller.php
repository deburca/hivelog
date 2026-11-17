<?php

declare(strict_types=1);

namespace Drupal\hivelog;

/**
 * Installs the HiveReminder entity type and schema.
 *
 * Kernel tests enable modules via $modules but don't run update hooks, so the
 * hive_reminder entity type (registered by hivelog_update_10041) is missing
 * from the definition cache when kernel test setup tries to install its schema.
 * This class provides a programmatic way to register the entity type and
 * install its schema before modules are enabled.
 */
class HiveReminderEntitySchemaInstaller {

  /**
   * Register the hive_reminder entity type in the container.
   */
  public static function registerEntityType(): void {
    $container = \Drupal::getContainer();
    if ($container->hasDefinition('entity_type.manager')) {
      $manager = $container->get('entity_type.manager');
      // Already registered (e.g., via annotation processor).
      try {
        if ($manager->getDefinition('hive_reminder') !== NULL) {
          return;
        }
      }
      catch (\Drupal\Component\Plugin\Exception\PluginNotFoundException $e) {
        // Expected - not yet in the cache.
      }
    }

    // Manually build the entity type definition from the annotation metadata.
    // This mirrors src/Entity/HiveReminder.php #[ContentEntityType(...)] attributes.
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

    $prop = $reflection->getProperty('label');
    $prop->setAccessible(TRUE);
    $prop->setValue($entity_type, new \Drupal\Core\StringTranslation\TranslatableMarkup('Hive reminder'));

    $prop = $reflection->getProperty('label_collection');
    $prop->setAccessible(TRUE);
    $prop->setValue($entity_type, new \Drupal\Core\StringTranslation\TranslatableMarkup('Hive reminders'));

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
      'storage' => '\Drupal\Core\Entity\ContentEntityStorageInterface',
      'form' => [
        'default' => '\Drupal\hivelog\Form\HiveReminderForm',
        'add' => '\Drupal\hivelog\Form\HiveReminderForm',
        'edit' => '\Drupal\hivelog\Form\HiveReminderForm',
        'delete' => '\Drupal\hivelog\Form\HivelogEntityDeleteForm',
      ],
      'access' => '\Drupal\hivelog\HiveReminderAccessControlHandler',
    ]);

    $prop = $reflection->getProperty('list_builder_class');
    $prop->setAccessible(TRUE);
    $prop->setValue($entity_type, '\Drupal\hivelog\HiveReminderListBuilder');

    if ($container->hasDefinition('entity_type.manager')) {
      $container->get('entity_type.manager')->addDefinition($entity_type);
    }
  }

}
