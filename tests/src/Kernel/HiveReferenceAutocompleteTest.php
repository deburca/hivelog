<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\Queen;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that a hive autocomplete shows the hive's apiary (task 0241).
 *
 * Hive names repeat across apiaries ("VV-01" in two of them), so the dropdown
 * rows and the text in the field read "Hive · Apiary (id)".
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveReferenceAutocompleteTest extends KernelTestBase {

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
   * Two hives that share a name, in different apiaries.
   *
   * @var \Drupal\hivelog\Entity\Hive[]
   */
  protected array $hives = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('apiary_membership');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
    $this->installSchema('file', ['file_usage']);

    foreach (['North', 'South'] as $name) {
      $apiary = Apiary::create(['name' => $name]);
      $apiary->save();
      $hive = Hive::create(['name' => 'VV-01', 'apiary' => $apiary->id(), 'status' => 'active']);
      $hive->save();
      $this->hives[$name] = $hive;
    }
  }

  /**
   * Tests the dropdown offers each hive with its apiary, and the id resolves.
   */
  public function testDropdownRowsNameTheApiary(): void {
    $matches = \Drupal::service('entity.autocomplete_matcher')
      ->getMatches('hive', 'default:hivelog_hive_with_apiary', [], 'VV');

    $this->assertCount(2, $matches);
    $values = array_column($matches, 'value');
    $this->assertContains('VV-01 · North (' . $this->hives['North']->id() . ')', $values);
    $this->assertContains('VV-01 · South (' . $this->hives['South']->id() . ')', $values);
    $this->assertSame($this->hives['South']->id(), EntityAutocomplete::extractEntityIdFromAutocompleteInput('VV-01 · South (' . $this->hives['South']->id() . ')'));
  }

  /**
   * Tests a saved hive reads the same way in the field as in the dropdown.
   */
  public function testSavedHiveShowsItsApiaryInTheField(): void {
    $queen = Queen::create(['name' => 'Q', 'hive' => $this->hives['South']->id(), 'status' => 'active']);
    $form = \Drupal::service('entity.form_builder')->getForm($queen, 'add');
    $element = $form['hive']['widget'][0]['target_id'];

    $this->assertSame('default:hivelog_hive_with_apiary', $element['#selection_handler']);
    $this->assertSame('VV-01 · South (' . $this->hives['South']->id() . ')', $element['#value']);
  }

  /**
   * Tests a reference to anything but a hive is left alone.
   */
  public function testOtherReferencesAreUntouched(): void {
    $observation = \Drupal::entityTypeManager()->getStorage('queen_observation')->create([]);
    $form = \Drupal::service('entity.form_builder')->getForm($observation, 'add');

    $this->assertStringNotContainsString('hivelog_hive_with_apiary', (string) $form['queen']['widget'][0]['target_id']['#selection_handler']);
  }

}
