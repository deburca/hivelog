<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\HivelogOwnerWarning;
use Drupal\hivelog\Controller\ApiaryController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryMembership;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the primary owner, and the CBR number an apiary carries (task 0232).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ApiaryPrimaryOwnerTest extends KernelTestBase {

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
   * The creator of the apiary, its first owner, CBR 111.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $first;

  /**
   * The owner it is handed over to, CBR 222.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $second;

  /**
   * The apiary.
   *
   * @var \Drupal\hivelog\Entity\Apiary
   */
  protected Apiary $apiary;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach ([
      'user', 'file', 'apiary', 'apiary_membership', 'hive', 'hive_inspection', 'queen',
      'queen_observation', 'calendar_action', 'hive_action_log', 'apiary_action_log',
      'inventory_item', 'inventory_purchase', 'inventory_usage', 'product', 'hive_component',
    ] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);
    // Skip uid 1, which bypasses every access check.
    $this->createUser();

    $permissions = ['view own apiary', 'edit own apiary', 'delete own apiary'];
    $this->first = $this->createUser($permissions);
    $this->first->set('cbr_number', ' 111 ')->save();
    $this->second = $this->createUser($permissions);
    $this->second->set('cbr_number', '222')->save();
    $this->apiary = Apiary::create(['name' => 'Home', 'uid' => $this->first->id()]);
    $this->apiary->save();
  }

  /**
   * Adds a membership with a role, with the owner role held since a time.
   */
  protected function addMember($user, string $role, ?int $owner_since = NULL): ApiaryMembership {
    $membership = ApiaryMembership::create([
      'apiary' => $this->apiary->id(),
      'account' => $user->id(),
      'role' => $role,
    ]);
    if ($owner_since !== NULL) {
      $membership->set('owner_since', $owner_since);
    }
    $membership->save();
    return $membership;
  }

  /**
   * Tests the creator is the primary owner and the apiary carries their CBR.
   */
  public function testCreatorIsThePrimaryOwner(): void {
    $this->assertSame((string) $this->first->id(), (string) $this->apiary->getPrimaryOwner()->id());
    $this->assertSame('111', $this->apiary->getCbrNumber(), 'The number is trimmed.');
  }

  /**
   * Tests the CBR number follows a hand-over from the old owner to the new.
   */
  public function testCbrNumberFollowsHandOver(): void {
    $this->addMember($this->second, 'owner');

    // During the hand-over, the owner of longest standing still counts.
    $this->assertSame('111', $this->apiary->getCbrNumber());

    // When the current owner is removed, the next owner's number is the apiary's.
    $memberships = \Drupal::entityTypeManager()->getStorage('apiary_membership')
      ->loadByProperties(['apiary' => $this->apiary->id(), 'account' => $this->first->id()]);
    reset($memberships)->delete();
    $this->assertSame('222', $this->apiary->getCbrNumber());
    $this->assertSame((string) $this->second->id(), (string) $this->apiary->getPrimaryOwner()->id());
  }

  /**
   * Tests that an owner with no number gives the apiary none, not another's.
   */
  public function testNoNumberIsNoNumber(): void {
    $this->first->set('cbr_number', '')->save();
    $this->addMember($this->second, 'owner');

    $this->assertSame('', $this->apiary->getCbrNumber(), 'The primary owner has set none.');
  }

  /**
   * Tests that a member promoted later does not jump the queue.
   */
  public function testPromotedMemberIsNotAheadOfAnEarlierOwner(): void {
    $third = $this->createUser();
    $third->set('cbr_number', '333')->save();
    // Added first (the oldest membership id), but made an owner last.
    $base = (int) $this->apiary->getOwnerMemberships()[0]->get('owner_since')->value;
    $promoted = $this->addMember($third, 'keeper');
    $this->addMember($this->second, 'owner', $base + 10);
    $promoted->set('role', 'owner')->save();
    $this->assertNotEmpty($promoted->get('owner_since')->value, 'Stamped when it becomes an owner.');
    $promoted->set('owner_since', $base + 20)->save();

    $order = array_map(
      fn(ApiaryMembership $m): string => (string) $m->get('account')->target_id,
      $this->apiary->getOwnerMemberships()
    );
    $this->assertSame((string) $this->first->id(), $order[0]);
    $this->assertSame(
      [(string) $this->second->id(), (string) $third->id()],
      array_slice($order, 1),
      'The promoted member comes after the owner who was already one.'
    );
  }

  /**
   * Tests that owner_since is cleared when an owner is made a beekeeper.
   */
  public function testOwnerSinceIsClearedWhenNoLongerAnOwner(): void {
    $second = $this->addMember($this->second, 'owner');
    $this->assertNotEmpty($second->get('owner_since')->value);

    $second->set('role', 'keeper')->save();
    $this->assertEmpty($second->get('owner_since')->value);
  }

  /**
   * Tests the warning appears for owners only, and only above two owners.
   */
  public function testWarningWhenMoreThanTwoOwners(): void {
    $viewer = $this->first;
    $this->addMember($this->second, 'owner');
    $warning = fn() => HivelogOwnerWarning::build($this->apiary, $viewer);

    $this->assertArrayNotHasKey('text', $warning(), 'Two owners is a normal hand-over.');

    $third = $this->createUser();
    $this->addMember($third, 'owner');
    $built = $warning();
    $this->assertArrayHasKey('text', $built);
    $text = (string) \Drupal::service('renderer')->renderInIsolation($built);
    $this->assertStringContainsString('3 owners', $text);
    $this->assertStringContainsString($this->first->getDisplayName(), $text, 'It names the owner whose CBR number is used.');
    $this->assertContains('messages--warning', $built['#attributes']['class']);

    // Someone who cannot manage the apiary does not see it.
    $keeper = $this->createUser(['view own apiary', 'edit own apiary']);
    $this->addMember($keeper, 'keeper');
    $this->assertArrayNotHasKey('text', HivelogOwnerWarning::build($this->apiary, $keeper));
  }

  /**
   * Tests the apiary page shows the CBR number and the warning.
   */
  public function testApiaryPageShowsTheCbrNumberAndWarning(): void {
    $this->setCurrentUser($this->first);
    $this->addMember($this->second, 'owner');
    $this->addMember($this->createUser(), 'owner');

    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($this->apiary);

    $this->assertArrayHasKey('text', $build['owner_warning']);
    $summary = (string) \Drupal::service('renderer')->renderInIsolation($build['summary']);
    $this->assertStringContainsString('CBR number', $summary);
    $this->assertStringContainsString('111', $summary);
  }

  /**
   * Tests the apiary list shows the primary owner's CBR and every owner.
   */
  public function testApiaryListShowsTheCbrNumberAndOwners(): void {
    $this->setCurrentUser($this->first);
    $this->addMember($this->second, 'owner');

    $build = \Drupal::entityTypeManager()->getListBuilder('apiary')->render();
    $rows = $build['table']['#props']['rows'];

    $this->assertCount(1, $rows);
    $cells = $rows[0]['cells'];
    $this->assertSame('111', $cells[1]);
    $this->assertSame($this->first->getDisplayName() . ', ' . $this->second->getDisplayName(), $cells[3]);
    $this->assertContains('apiary_membership_list', $build['table']['#cache']['tags']);
  }

  /**
   * Tests the update hook fills owner_since for current owners.
   */
  public function testUpdateHookFillsOwnerSince(): void {
    \Drupal::moduleHandler()->loadInclude('hivelog', 'install');
    \Drupal::database()->update('hivelog_apiary_membership')->fields(['owner_since' => NULL])->execute();
    \Drupal::entityTypeManager()->getStorage('apiary_membership')->resetCache();

    $sandbox = [];
    $update = 'hivelog_update_' . $this->updateNumber;
    $update($sandbox);
    $update($sandbox);

    $owners = $this->apiary->getOwnerMemberships();
    $this->assertCount(1, $owners);
    $this->assertNotEmpty($owners[0]->get('owner_since')->value);
  }

  /**
   * The update hook number the backfill test runs.
   */
  protected int $updateNumber = 10038;

}
