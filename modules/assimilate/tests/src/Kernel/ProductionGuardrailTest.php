<?php

declare(strict_types=1);

namespace Drupal\Tests\assimilate\Kernel;

use Drupal\assimilate\DemoDataProvisioner;
use Drupal\hivelog\Entity\Apiary;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests `ProductionGuardrail` — task 0107's real guard against production use.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ProductionGuardrailTest extends KernelTestBase {

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
    'nanoprobe',
    'assimilate',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * Tests a site with no real apiaries at all is safe.
   */
  public function testNoApiariesIsSafe(): void {
    $guardrail = \Drupal::service('assimilate.production_guardrail');
    $this->assertNull($guardrail->blockingReason());
  }

  /**
   * Tests a real apiary with AI insights enabled blocks.
   */
  public function testRealApiaryWithAiInsightsBlocks(): void {
    Apiary::create(['name' => 'Real Apiary', 'ai_insights_enabled' => TRUE])->save();

    $guardrail = \Drupal::service('assimilate.production_guardrail');
    $reason = $guardrail->blockingReason();
    $this->assertNotNull($reason);
    $this->assertStringContainsString('AI insights enabled', (string) $reason);
  }

  /**
   * Tests a real apiary without AI insights enabled does not block.
   */
  public function testRealApiaryWithoutAiInsightsIsSafe(): void {
    Apiary::create(['name' => 'Real Apiary', 'ai_insights_enabled' => FALSE])->save();

    $guardrail = \Drupal::service('assimilate.production_guardrail');
    $this->assertNull($guardrail->blockingReason());
  }

  /**
   * Tests assimilate's own demo apiary is excluded from the check.
   *
   * Without this, assimilate would permanently lock itself out the
   * moment `DemoDataProvisioner` enables `ai_insights_enabled` on its
   * own demo apiary (so `nexus_cron()` has something to process).
   */
  public function testOwnDemoApiaryIsExcluded(): void {
    $demo_apiary = Apiary::create(['name' => 'Assimilate Demo Apiary', 'ai_insights_enabled' => TRUE]);
    $demo_apiary->save();
    \Drupal::state()->set(DemoDataProvisioner::DEMO_APIARY_ID_STATE_KEY, $demo_apiary->id());

    $guardrail = \Drupal::service('assimilate.production_guardrail');
    $this->assertNull($guardrail->blockingReason());
  }

  /**
   * Tests a real apiary still blocks even once the demo apiary exists.
   */
  public function testRealApiaryStillBlocksAlongsideDemoApiary(): void {
    $demo_apiary = Apiary::create(['name' => 'Assimilate Demo Apiary', 'ai_insights_enabled' => TRUE]);
    $demo_apiary->save();
    \Drupal::state()->set(DemoDataProvisioner::DEMO_APIARY_ID_STATE_KEY, $demo_apiary->id());

    Apiary::create(['name' => 'Real Apiary', 'ai_insights_enabled' => TRUE])->save();

    $guardrail = \Drupal::service('assimilate.production_guardrail');
    $this->assertNotNull($guardrail->blockingReason());
  }

  /**
   * Tests `assimilate_requirements()`'s `runtime` phase reflects the guard.
   */
  public function testRuntimeRequirementsReflectGuardrailState(): void {
    \Drupal::moduleHandler()->loadInclude('assimilate', 'install');

    $requirements = assimilate_requirements('runtime');
    $this->assertArrayHasKey('assimilate_dev_only', $requirements);
    $this->assertEquals(REQUIREMENT_WARNING, $requirements['assimilate_dev_only']['severity']);

    Apiary::create(['name' => 'Real Apiary', 'ai_insights_enabled' => TRUE])->save();

    $requirements = assimilate_requirements('runtime');
    $this->assertEquals(REQUIREMENT_ERROR, $requirements['assimilate_dev_only']['severity']);
  }

  /**
   * Tests `assimilate_requirements()`'s `install` phase blocks on a real apiary.
   */
  public function testInstallRequirementsBlockOnRealApiary(): void {
    \Drupal::moduleHandler()->loadInclude('assimilate', 'install');

    $this->assertEmpty(assimilate_requirements('install'));

    Apiary::create(['name' => 'Real Apiary', 'ai_insights_enabled' => TRUE])->save();

    $requirements = assimilate_requirements('install');
    $this->assertArrayHasKey('assimilate_production_guardrail', $requirements);
    $this->assertEquals(REQUIREMENT_ERROR, $requirements['assimilate_production_guardrail']['severity']);
  }

}
