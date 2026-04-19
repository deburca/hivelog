<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\QueenController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\Queen;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Queen entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class QueenTest extends KernelTestBase {

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
   * A test apiary.
   */
  protected Apiary $apiary;

  /**
   * A test hive.
   */
  protected Hive $hive;

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

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();

    $this->hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hive->save();
  }

  /**
   * Tests basic queen creation and persisted field values.
   */
  public function testCreateQueen(): void {
    $queen = Queen::create([
      'name' => 'Q-2024-001',
      'origin' => 'Local breeder',
      'queen_year' => 2024,
      'breed' => 'buckfast',
      'temperament' => 'calm',
      'purchase_cost' => '35.50',
      'purchase_date' => '2024-04-01',
      'hive' => $this->hive->id(),
      'introduction_date' => '2024-05-10',
      'status' => 'active',
      'notes' => 'Mated locally.',
    ]);
    $queen->save();

    $loaded = Queen::load($queen->id());
    $this->assertEquals('Q-2024-001', $loaded->label());
    $this->assertEquals('Local breeder', $loaded->get('origin')->value);
    $this->assertEquals(2024, $loaded->get('queen_year')->value);
    $this->assertEquals('buckfast', $loaded->get('breed')->value);
    $this->assertEquals('calm', $loaded->get('temperament')->value);
    $this->assertEquals('35.50', $loaded->get('purchase_cost')->value);
    $this->assertEquals('2024-04-01', $loaded->get('purchase_date')->value);
    $this->assertEquals($this->hive->id(), $loaded->get('hive')->target_id);
    $this->assertEquals('2024-05-10', $loaded->get('introduction_date')->value);
    $this->assertEquals('active', $loaded->get('status')->value);
    $this->assertEquals('Mated locally.', $loaded->get('notes')->value);
  }

  /**
   * Tests queen colour auto-calculation for every year ending.
   */
  public function testQueenColourAutoCalculation(): void {
    $expected = [
      2020 => 'blue',
      2021 => 'white',
      2022 => 'yellow',
      2023 => 'red',
      2024 => 'green',
      2025 => 'blue',
      2026 => 'white',
      2027 => 'yellow',
      2028 => 'red',
      2029 => 'green',
    ];

    foreach ($expected as $year => $colour) {
      $queen = Queen::create([
        'name' => "Q-$year",
        'queen_year' => $year,
        'status' => 'active',
      ]);
      $queen->save();

      $loaded = Queen::load($queen->id());
      $this->assertEquals(
        $colour,
        $loaded->get('queen_colour')->value,
        "Year $year should produce colour '$colour'.",
      );
    }
  }

  /**
   * Tests that queen colour is empty when no year is set.
   */
  public function testQueenColourWithoutYear(): void {
    $queen = Queen::create([
      'name' => 'Q-unknown',
      'status' => 'active',
    ]);
    $queen->save();

    $this->assertEmpty(Queen::load($queen->id())->get('queen_colour')->value);
  }

  /**
   * Tests queen colour updates when the year changes.
   */
  public function testQueenColourUpdatesOnYearChange(): void {
    $queen = Queen::create([
      'name' => 'Q-renewed',
      'queen_year' => 2023,
      'status' => 'active',
    ]);
    $queen->save();
    $this->assertEquals('red', Queen::load($queen->id())->get('queen_colour')->value);

    $queen->set('queen_year', 2025);
    $queen->save();
    $this->assertEquals('blue', Queen::load($queen->id())->get('queen_colour')->value);
  }

  /**
   * Saving a second active queen on a hive marks the first one inactive.
   */
  public function testOneActiveQueenPerHiveInvariant(): void {
    $first = Queen::create([
      'name' => 'Q-first',
      'hive' => $this->hive->id(),
      'queen_year' => 2024,
      'status' => 'active',
    ]);
    $first->save();

    $second = Queen::create([
      'name' => 'Q-second',
      'hive' => $this->hive->id(),
      'queen_year' => 2025,
      'status' => 'active',
    ]);
    $second->save();

    $reloaded_first = Queen::load($first->id());
    $reloaded_second = Queen::load($second->id());

    $this->assertEquals('inactive', $reloaded_first->get('status')->value);
    $this->assertEmpty(
      $reloaded_first->get('hive')->target_id,
      'Previous queen should be detached from the hive when a new active queen takes over.'
    );
    $this->assertEquals('active', $reloaded_second->get('status')->value);
    $this->assertEquals($this->hive->id(), $reloaded_second->get('hive')->target_id);
  }

  /**
   * Marking a queen inactive does not bump other queens on the same hive.
   */
  public function testInactiveSaveDoesNotArchiveOtherActiveQueens(): void {
    $active = Queen::create([
      'name' => 'Q-active',
      'hive' => $this->hive->id(),
      'queen_year' => 2024,
      'status' => 'active',
    ]);
    $active->save();

    // Another queen saved as inactive with the same hive should not touch
    // the existing active queen.
    $inactive = Queen::create([
      'name' => 'Q-spare',
      'hive' => $this->hive->id(),
      'queen_year' => 2023,
      'status' => 'inactive',
    ]);
    $inactive->save();

    $this->assertEquals('active', Queen::load($active->id())->get('status')->value);
  }

  /**
   * Hive::getActiveQueen() returns the single active queen for the hive.
   */
  public function testHiveGetActiveQueen(): void {
    // No queen yet.
    $this->assertNull($this->hive->getActiveQueen());

    $queen = Queen::create([
      'name' => 'Q-current',
      'hive' => $this->hive->id(),
      'queen_year' => 2024,
      'status' => 'active',
    ]);
    $queen->save();

    // Reload hive so any cached references are refreshed.
    $hive = Hive::load($this->hive->id());
    $active = $hive->getActiveQueen();
    $this->assertNotNull($active);
    $this->assertEquals($queen->id(), $active->id());

    // Archive the queen and detach it; the hive should now report no active
    // queen even though a queen record still exists.
    $queen->set('status', 'inactive');
    $queen->set('hive', NULL);
    $queen->save();
    $this->assertNull(Hive::load($this->hive->id())->getActiveQueen());
  }

  /**
   * Tests updating and deleting a queen.
   */
  public function testUpdateAndDeleteQueen(): void {
    $queen = Queen::create([
      'name' => 'Q-lifecycle',
      'queen_year' => 2024,
      'status' => 'active',
    ]);
    $queen->save();
    $id = $queen->id();

    $queen->set('status', 'inactive');
    $queen->save();
    $this->assertEquals('inactive', Queen::load($id)->get('status')->value);

    $queen->delete();
    $this->assertNull(Queen::load($id));
  }

  /**
   * Renders the queen canonical view and asserts the sectioned layout,
   * formatted values, and Edit / Delete action buttons are present.
   */
  public function testQueenViewRendersSectionedLayout(): void {
    $this->installConfig(['system']);

    $user = User::create([
      'name' => 'queen-view-tester',
      'mail' => 'queen-view-tester@example.com',
    ]);
    $user->save();

    // Grant edit + delete on queens so the action buttons render.
    $role = Role::create([
      'id' => 'queen_editor',
      'label' => 'Queen editor',
    ]);
    $role->grantPermission('view queen');
    $role->grantPermission('edit queen');
    $role->grantPermission('delete queen');
    $role->save();
    $user->addRole('queen_editor');
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $queen = Queen::create([
      'name' => 'Q-2024-007',
      'origin' => 'Highlands Apiaries',
      'queen_year' => 2024,
      'breed' => 'buckfast',
      'temperament' => 'calm',
      'purchase_cost' => '42.50',
      'purchase_date' => '2024-04-01',
      'hive' => $this->hive->id(),
      'introduction_date' => '2024-05-10',
      'status' => 'active',
      'notes' => "Line 1.\nLine 2.",
      'uid' => $user->id(),
    ]);
    $queen->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(QueenController::class);
    $build = $controller->view($queen);

    // Section render arrays are keyed in the expected order.
    $this->assertArrayHasKey('actions', $build);
    $this->assertArrayHasKey('overview', $build);
    $this->assertArrayHasKey('identity', $build);
    $this->assertArrayHasKey('acquisition', $build);
    $this->assertArrayHasKey('notes', $build);
    $this->assertArrayHasKey('edit', $build['actions']);
    $this->assertArrayHasKey('delete', $build['actions']);

    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    // Section headings.
    $this->assertStringContainsString('Overview', $html);
    $this->assertStringContainsString('Identity', $html);
    $this->assertStringContainsString('Acquisition', $html);
    $this->assertStringContainsString('Notes', $html);
    // Field / Value column headers from the shared section tables.
    $this->assertStringContainsString('>Field<', $html);
    $this->assertStringContainsString('>Value<', $html);

    // Formatted values.
    $this->assertStringContainsString('Q-2024-007', $html);
    $this->assertStringContainsString('Highlands Apiaries', $html);
    // Status and breed are shown using their human-readable labels, not
    // the raw machine names.
    $this->assertStringContainsString('Active', $html);
    $this->assertStringContainsString('Buckfast', $html);
    // Queen colour derived from the 2024 year → green.
    $this->assertStringContainsString('Green', $html);
    // Purchase cost renders with two decimals via number_format.
    $this->assertStringContainsString('42.50', $html);
    $this->assertStringContainsString('2024-04-01', $html);
    $this->assertStringContainsString('2024-05-10', $html);
    // Notes preserve line breaks as <br />.
    $this->assertStringContainsString('Line 1.<br', $html);

    // Action buttons.
    $this->assertStringContainsString('button--primary', $html);
    $this->assertStringContainsString('button--danger', $html);
    // Edit button appears before the Delete button.
    $edit_pos = strpos($html, '>Edit<');
    $delete_pos = strpos($html, '>Delete<');
    $this->assertNotFalse($edit_pos);
    $this->assertNotFalse($delete_pos);
    $this->assertLessThan($delete_pos, $edit_pos);
  }

  /**
   * Empty fields render as an em-dash rather than being hidden or echoing
   * raw empty values.
   */
  public function testQueenViewShowsEmDashForEmptyFields(): void {
    $this->installConfig(['system']);

    $user = User::create([
      'name' => 'sparse-queen-tester',
      'mail' => 'sparse-queen-tester@example.com',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $queen = Queen::create([
      'name' => 'Q-minimal',
      'status' => 'active',
    ]);
    $queen->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(QueenController::class);
    $build = $controller->view($queen);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    // At least one em-dash should appear for the empty origin / breed /
    // purchase_date / etc. rows.
    $this->assertStringContainsString('—', $html);
  }

}
