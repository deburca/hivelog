<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests how the apiary edit form lays out its fields and Members section.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ApiaryFormLayoutTest extends KernelTestBase {

  use UserCreationTrait;

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
    $this->installEntitySchema('apiary_membership');
    // Skip uid 1, which bypasses every access check.
    $this->createUser();
  }

  /**
   * Builds the edit form of a saved apiary as a user who may edit it.
   */
  protected function editForm(): array {
    $owner = $this->createUser(['edit own apiary', 'view own apiary']);
    $this->setCurrentUser($owner);
    $apiary = Apiary::create(['name' => 'Test Apiary', 'uid' => $owner->id()]);
    $apiary->save();
    return \Drupal::service('entity.form_builder')->getForm($apiary, 'edit');
  }

  /**
   * Tests that the fields are grouped into vertical tabs.
   */
  public function testFieldsAreGroupedIntoTabs(): void {
    $form = $this->editForm();

    $this->assertSame('vertical_tabs', $form['hivelog_tabs']['#type']);
    foreach (['hivelog_details', 'hivelog_map', 'hivelog_access', 'hivelog_ai'] as $tab) {
      $this->assertSame('details', $form[$tab]['#type'], "$tab is a details element.");
      $this->assertSame('hivelog_tabs', $form[$tab]['#group'], "$tab is a vertical tab.");
    }
    $expected = [
      'name' => 'hivelog_details',
      'location' => 'hivelog_details',
      'notes' => 'hivelog_details',
      'geolocation' => 'hivelog_map',
      'visibility' => 'hivelog_access',
      'uid' => 'hivelog_access',
      'members' => 'hivelog_access',
      'ai_insights_enabled' => 'hivelog_ai',
    ];
    foreach ($expected as $field => $tab) {
      $this->assertSame($tab, $form[$field]['#group'], "$field sits in $tab.");
    }
  }

  /**
   * Tests that visibility, owner and members follow one another.
   */
  public function testAccessSettingsAreInOrder(): void {
    $form = $this->editForm();

    $this->assertLessThan($form['uid']['#weight'], $form['visibility']['#weight']);
    $this->assertLessThan($form['members']['#weight'], $form['uid']['#weight']);
    $this->assertSame('Members', (string) $form['members']['heading']['title']['#value']);
  }

  /**
   * Tests that a new apiary has no Members section yet.
   */
  public function testNewApiaryHasNoMembersSection(): void {
    $this->setCurrentUser($this->createUser(['add apiary']));
    $form = \Drupal::service('entity.form_builder')->getForm(Apiary::create([]), 'add');

    $this->assertArrayNotHasKey('members', $form);
    $this->assertSame('hivelog_details', $form['name']['#group']);
    $this->assertSame('hivelog_access', $form['visibility']['#group']);
  }

  /**
   * Tests the map's start position is a Denmark default.
   */
  public function testMapStartsOnDenmark(): void {
    $settings = Apiary::baseFieldDefinitions(\Drupal::entityTypeManager()->getDefinition('apiary'))['geolocation']
      ->getDisplayOptions('form')['settings']['map'];

    $this->assertEqualsWithDelta(55.9, $settings['map_position']['center']['lat'], 1.0);
    $this->assertEqualsWithDelta(10.5, $settings['map_position']['center']['lon'], 2.0);
    $this->assertArrayNotHasKey('center', $settings, 'The widget ignores a bare center key.');
  }

}
