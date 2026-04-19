<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Guards the declared module dependency set.
 *
 * The geocoder module was removed after an audit confirmed nothing in the
 * hivelog codebase consumes it: the apiary map widget is
 * leaflet_widget_default, which does not require geocoder, and no
 * geocoder service is invoked anywhere in the module. This test keeps that
 * decision from silently regressing.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ModuleDependencyAuditTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'datetime',
    'options',
    'file',
    'image',
    'geofield',
    'hivelog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_inspection');
    $this->installEntitySchema('queen');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * Asserts hivelog.info.yml does not declare the geocoder dependency.
   */
  public function testGeocoderIsNotDeclaredAsDependency(): void {
    $info = \Drupal::service('extension.list.module')->getExtensionInfo('hivelog');
    $dependencies = $info['dependencies'] ?? [];
    foreach ($dependencies as $dependency) {
      $this->assertStringNotContainsString(
        'geocoder',
        $dependency,
        'hivelog must not depend on geocoder unless a concrete use is added.'
      );
    }
  }

  /**
   * Asserts the module keeps functioning with geocoder absent.
   *
   * The test explicitly does not list 'geocoder' in $modules, so installing
   * hivelog and the entity schemas without it exercises the removed
   * dependency. A failure here most likely means something reintroduced a
   * geocoder service call or plugin expectation.
   */
  public function testInstallsWithoutGeocoder(): void {
    $module_handler = \Drupal::moduleHandler();
    $this->assertTrue($module_handler->moduleExists('hivelog'));
    $this->assertFalse($module_handler->moduleExists('geocoder'));

    $entity_type_manager = \Drupal::entityTypeManager();
    $this->assertNotNull($entity_type_manager->getDefinition('apiary'));
    $this->assertNotNull($entity_type_manager->getDefinition('hive'));
    $this->assertNotNull($entity_type_manager->getDefinition('hive_inspection'));
    $this->assertNotNull($entity_type_manager->getDefinition('queen'));
  }

}
