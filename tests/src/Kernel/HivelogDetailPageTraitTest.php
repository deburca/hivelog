<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\QueenController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\Queen;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests HivelogDetailPageTrait's shared builders (task 0125).
 *
 * Exercised through `QueenController`, one of the nine controllers that
 * use the trait — its own `buildActions()` / `buildSection()` /
 * `buildRows()` / `buildFieldValue()` were deleted in favour of the
 * trait, so this is really testing the trait, not anything
 * Queen-specific. `formatFieldValue()` itself (the per-type hook) is
 * already covered by each controller's own kernel test
 * (`QueenTest::testQueenViewRendersSectionedLayout()` and siblings).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogDetailPageTraitTest extends KernelTestBase {

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
   * A test queen, owned by an apiary owner with no beekeepers.
   */
  protected Queen $queen;

  /**
   * The apiary owner.
   */
  protected User $owner;

  /**
   * The test apiary, so tests can add/remove beekeeper members.
   *
   * `Queen`'s `update` access is member-scoped
   * (`ApiaryAccessTrait::checkApiaryEditAccess()`), so a permission alone
   * isn't enough — the user must also be an apiary member (owner or
   * beekeeper).
   */
  protected Apiary $apiary;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('queen');

    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('view own hive');
    $role->grantPermission('view own queen');
    $role->grantPermission('edit own queen');
    $role->grantPermission('delete own queen');
    $role->save();

    $this->owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $this->owner->addRole('beekeeper');
    $this->owner->save();

    $this->apiary = Apiary::create(['name' => 'Test Apiary', 'uid' => $this->owner->id(), 'visibility' => 'private']);
    $this->apiary->save();

    $hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
      'uid' => $this->owner->id(),
    ]);
    $hive->save();

    $this->queen = Queen::create([
      'name' => 'Q-test',
      'hive' => $hive->id(),
      'queen_year' => 2025,
      'status' => 'active',
      'uid' => $this->owner->id(),
    ]);
    $this->queen->save();
  }

  /**
   * Invokes the protected buildActions() method via reflection.
   */
  protected function buildActions(): array {
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(QueenController::class);
    $method = new \ReflectionMethod(QueenController::class, 'buildActions');
    $method->setAccessible(TRUE);
    return $method->invoke($controller, $this->queen);
  }

  /**
   * Extracts the button labels from a buildActions() render array.
   */
  protected function buttonLabels(array $build): array {
    if (empty($build)) {
      return [];
    }
    return array_column($build['#props']['buttons'], 'label');
  }

  /**
   * Tests both Edit and Delete are present for a user with both access.
   */
  public function testBothButtonsPresentWithFullAccess(): void {
    $this->setCurrentUser($this->owner);
    $build = $this->buildActions();
    $this->assertEquals(['Edit', 'Delete'], $this->buttonLabels($build));
    $this->assertEquals('component', $build['#type']);
    $this->assertEquals('hivelog:button-group', $build['#component']);
    $this->assertEquals('danger', $build['#props']['buttons'][1]['variant']);
  }

  /**
   * Tests only Edit is present for a user with update but not delete access.
   *
   * `QueenAccessControlHandler`'s `update` is member-scoped, so the
   * editor is added as a beekeeper on the fixture apiary — a permission
   * alone isn't enough.
   */
  public function testOnlyEditButtonWithUpdateAccessOnly(): void {
    $role = Role::create(['id' => 'edit_only', 'label' => 'Edit only']);
    $role->grantPermission('view own queen');
    $role->grantPermission('edit own queen');
    $role->save();
    $editor = User::create(['name' => 'editor', 'mail' => 'editor@example.com']);
    $editor->addRole('edit_only');
    $editor->save();
    $this->apiary->set('beekeepers', [$editor->id()]);
    $this->apiary->save();

    $this->setCurrentUser($editor);
    $build = $this->buildActions();
    $this->assertEquals(['Edit'], $this->buttonLabels($build));
  }

  /**
   * Tests only Delete is present for a user with delete but not update access.
   *
   * `QueenAccessControlHandler`'s delete is owner-scoped, so the owner
   * account with only a "delete own queen" permission (no "edit own
   * queen") is used here to isolate the case.
   */
  public function testOnlyDeleteButtonWithDeleteAccessOnly(): void {
    $role = Role::create(['id' => 'delete_only', 'label' => 'Delete only']);
    $role->grantPermission('view own queen');
    $role->grantPermission('delete own queen');
    $role->save();
    $this->owner->removeRole('beekeeper');
    $this->owner->addRole('delete_only');
    $this->owner->save();

    $this->setCurrentUser($this->owner);
    $build = $this->buildActions();
    $this->assertEquals(['Delete'], $this->buttonLabels($build));
  }

  /**
   * Tests no buttons (and an empty render array) with neither access.
   */
  public function testNoButtonsWithoutAccess(): void {
    $outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $outsider->addRole('beekeeper');
    $outsider->save();

    $this->setCurrentUser($outsider);
    $build = $this->buildActions();
    $this->assertEquals([], $build);
  }

}
