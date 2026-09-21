<?php

declare(strict_types=1);

namespace Drupal\Tests\collective\Kernel;

use Drupal\collective\Entity\HiveInsight;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Hive Insight entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveInsightTest extends KernelTestBase {

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
    'collective',
  ];

  /**
   * A test apiary.
   */
  protected Apiary $apiary;

  /**
   * A test hive, belonging to `$apiary`.
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
    $this->installEntitySchema('hive_insight');
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
   * Tests creating, updating and deleting a hive-scoped insight.
   */
  public function testCrud(): void {
    $insight = HiveInsight::create([
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'verdict' => 'inspect_soon',
      'recommendation' => 'Possible swarm risk — inspect within 2 days',
      'signals' => "- Weight dropped 2.1 kg overnight\n- Last inspection 9 days ago showed queen cells starting",
      'confidence' => 'high',
      'generated' => \Drupal::time()->getRequestTime(),
    ]);
    $insight->save();

    $loaded = HiveInsight::load($insight->id());
    $this->assertEquals($this->apiary->id(), $loaded->get('apiary')->target_id);
    $this->assertEquals($this->hive->id(), $loaded->get('hive')->target_id);
    $this->assertEquals('hive', $loaded->get('scope')->value);
    $this->assertEquals('inspect_soon', $loaded->get('verdict')->value);
    $this->assertEquals('Possible swarm risk — inspect within 2 days', $loaded->get('recommendation')->value);
    $this->assertStringContainsString('Weight dropped', $loaded->get('signals')->value);
    $this->assertEquals('high', $loaded->get('confidence')->value);
    $this->assertNotEmpty($loaded->get('generated')->value);
    $this->assertNotEmpty($loaded->get('created')->value);
    $this->assertStringContainsString('Inspect soon', (string) $loaded->label());
    $this->assertStringContainsString('Test Hive', (string) $loaded->label());

    // Update.
    $insight->set('confidence', 'medium');
    $insight->save();
    $this->assertEquals('medium', HiveInsight::load($insight->id())->get('confidence')->value);

    // Delete.
    $id = $insight->id();
    $insight->delete();
    $this->assertNull(HiveInsight::load($id));
  }

  /**
   * Tests `scope` defaults to `hive`.
   */
  public function testFieldDefaults(): void {
    $insight = HiveInsight::create([
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'verdict' => 'all_clear',
      'recommendation' => 'No action needed',
      'signals' => '- Nothing unusual observed',
      'generated' => \Drupal::time()->getRequestTime(),
    ]);
    $this->assertEquals('hive', $insight->get('scope')->value);
  }

  /**
   * Tests that an apiary-scoped insight may omit hive.
   */
  public function testApiaryScopedInsightWithoutHive(): void {
    $insight = HiveInsight::create([
      'apiary' => $this->apiary->id(),
      'scope' => 'apiary',
      'verdict' => 'all_clear',
      'recommendation' => 'Apiary looks healthy overall',
      'signals' => '- All monitored hives reporting normally',
      'generated' => \Drupal::time()->getRequestTime(),
    ]);
    $insight->save();

    $loaded = HiveInsight::load($insight->id());
    $this->assertTrue($loaded->get('hive')->isEmpty());
  }

  /**
   * Tests that hive is required when scope = hive.
   */
  public function testHiveRequiredWhenScopeIsHive(): void {
    $insight = HiveInsight::create([
      'apiary' => $this->apiary->id(),
      'scope' => 'hive',
      'verdict' => 'all_clear',
      'recommendation' => 'No action needed',
      'signals' => '- Nothing unusual',
      'generated' => \Drupal::time()->getRequestTime(),
    ]);
    $this->expectException(\Exception::class);
    $insight->save();
  }

  /**
   * Tests that hive must be unset when scope = apiary.
   */
  public function testHiveRejectedWhenScopeIsApiary(): void {
    $insight = HiveInsight::create([
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'apiary',
      'verdict' => 'all_clear',
      'recommendation' => 'No action needed',
      'signals' => '- Nothing unusual',
      'generated' => \Drupal::time()->getRequestTime(),
    ]);
    $this->expectException(\Exception::class);
    $insight->save();
  }

  /**
   * Tests that an unrecognised verdict is rejected.
   */
  public function testUnrecognisedVerdictRejected(): void {
    $insight = HiveInsight::create([
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'verdict' => 'not_a_real_verdict',
      'recommendation' => 'No action needed',
      'signals' => '- Nothing unusual',
      'generated' => \Drupal::time()->getRequestTime(),
    ]);
    $this->expectException(\Exception::class);
    $insight->save();
  }

  /**
   * Tests every VERDICTS constant is a valid, savable verdict.
   */
  public function testAllVerdictsAreValid(): void {
    foreach (array_keys(HiveInsight::VERDICTS) as $verdict) {
      $insight = HiveInsight::create([
        'apiary' => $this->apiary->id(),
        'hive' => $this->hive->id(),
        'scope' => 'hive',
        'verdict' => $verdict,
        'recommendation' => 'Test',
        'signals' => '- Test signal',
        'generated' => \Drupal::time()->getRequestTime(),
      ]);
      $insight->save();
      $this->assertNotNull($insight->id(), "Verdict '$verdict' should save successfully.");
    }
  }

  /**
   * Tests apiary-scoped access control parity.
   */
  public function testApiaryScopedAccess(): void {
    // The first user created in a kernel test is uid 1, which bypasses
    // permission checks entirely — a throwaway user here ensures the
    // real assertions below actually exercise the access-control logic.
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('view own hive insight');
    $role->grantPermission('delete own hive insight');
    $role->save();

    $owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $owner->addRole('beekeeper');
    $owner->save();

    $beekeeper = User::create(['name' => 'beekeeper', 'mail' => 'beekeeper@example.com']);
    $beekeeper->addRole('beekeeper');
    $beekeeper->save();

    $outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $outsider->addRole('beekeeper');
    $outsider->save();

    $apiary = Apiary::create([
      'name' => 'Access Test Apiary',
      'uid' => $owner->id(),
      'visibility' => 'private',
      'beekeepers' => [$beekeeper->id()],
    ]);
    $apiary->save();

    $hive = Hive::create([
      'name' => 'Access Test Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
      'uid' => $owner->id(),
    ]);
    $hive->save();

    $insight = HiveInsight::create([
      'apiary' => $apiary->id(),
      'hive' => $hive->id(),
      'scope' => 'hive',
      'verdict' => 'act_now',
      'recommendation' => 'Add a super',
      'signals' => '- Weight trending up steadily, brood nest full',
      'generated' => \Drupal::time()->getRequestTime(),
    ]);
    $insight->save();

    $this->assertTrue($insight->access('view', $owner));
    $this->assertTrue($insight->access('view', $beekeeper));
    $this->assertFalse($insight->access('view', $outsider));

    // Delete is owner-only.
    $this->assertTrue($insight->access('delete', $owner));
    $this->assertFalse($insight->access('delete', $beekeeper));

    // Public apiary: an outsider with the "own" permission can view.
    // The insight/hive objects above already resolved and cached their
    // reference fields during the earlier access() calls, and those
    // objects are still sitting in their storages' static caches too —
    // so the entity storages and the access handler all need resetting
    // before a reload picks up the new visibility.
    $apiary->set('visibility', 'public');
    $apiary->save();
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_type_manager->getStorage('hive')->resetCache();
    $entity_type_manager->getStorage('hive_insight')->resetCache();
    $entity_type_manager->getAccessControlHandler('hive_insight')->resetCache();
    $reloaded_insight = HiveInsight::load($insight->id());
    $this->assertTrue($reloaded_insight->access('view', $outsider));
  }

}
