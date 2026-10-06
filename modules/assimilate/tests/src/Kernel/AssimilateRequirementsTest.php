<?php

declare(strict_types=1);

namespace Drupal\Tests\assimilate\Kernel;

use Drupal\assimilate\DemoDataProvisioner;
use Drupal\hivelog\Entity\Apiary;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests assimilate's requirements the way Drupal itself asks for them.
 *
 * Drupal 11.3 and later run install requirements from a class and runtime
 * requirements from an object-oriented hook, and skip the procedural
 * hook_requirements() of a module that marks it as legacy; Drupal 12 removes it
 * altogether. These tests go through `drupal_check_module()` (what the module
 * install page and Drush use) and the status report's own list, not through
 * `assimilate_requirements()`, so they fail if the guardrail stops being
 * asked for. The older tests in ProductionGuardrailTest call the procedural
 * function directly, which would keep passing after Drupal stopped calling it.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class AssimilateRequirementsTest extends KernelTestBase {

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
    require_once $this->root . '/core/includes/install.inc';
  }

  /**
   * Gets one entry of the status report, its severity as 0, 1 or 2.
   *
   * @return array<string, mixed>
   *   The requirement; severity 0 is OK, 1 a warning, 2 an error.
   */
  protected function runtimeRequirement(string $key): array {
    $all = \Drupal::service('system.manager')->listRequirements();
    $this->assertArrayHasKey($key, $all, "The status report has no $key entry.");
    $requirement = $all[$key];
    $severity = $requirement['severity'];
    $requirement['severity'] = $severity instanceof \BackedEnum ? $severity->value : (int) $severity;
    return $requirement;
  }

  /**
   * The install check passes on a site with no real AI-insight data.
   */
  public function testInstallCheckPassesOnAnEmptySite(): void {
    $this->assertTrue(drupal_check_module('assimilate'));
    $this->assertSame([], \Drupal::messenger()->messagesByType('error'));
  }

  /**
   * The install check refuses on a site where a real apiary has insights on.
   */
  public function testInstallCheckRefusesWhenRealApiaryHasInsightsOn(): void {
    Apiary::create(['name' => 'Real Apiary', 'ai_insights_enabled' => TRUE])->save();

    $this->assertFalse(drupal_check_module('assimilate'));
    $errors = \Drupal::messenger()->messagesByType('error');
    $this->assertNotEmpty($errors, 'The refusal says why.');
    $this->assertStringContainsString('never run on a site with real AI-insight data', (string) $errors[0]);
  }

  /**
   * The install check ignores a real apiary that has not opted in.
   */
  public function testInstallCheckIgnoresAnApiaryWithInsightsOff(): void {
    Apiary::create(['name' => 'Real Apiary', 'ai_insights_enabled' => FALSE])->save();
    $this->assertTrue(drupal_check_module('assimilate'));
  }

  /**
   * The install check ignores assimilate's own demo apiary.
   */
  public function testInstallCheckIgnoresTheDemoApiary(): void {
    $demo = Apiary::create(['name' => 'Assimilate Demo Apiary', 'ai_insights_enabled' => TRUE]);
    $demo->save();
    \Drupal::state()->set(DemoDataProvisioner::DEMO_APIARY_ID_STATE_KEY, $demo->id());
    $this->assertTrue(drupal_check_module('assimilate'));
  }

  /**
   * The status report always carries the standing reminder, as a warning.
   */
  public function testStatusReportShowsTheReminder(): void {
    $requirement = $this->runtimeRequirement('assimilate_dev_only');
    $this->assertSame(1, $requirement['severity']);
    $this->assertStringContainsString('No real apiary', (string) $requirement['value']);
  }

  /**
   * The status report turns to an error once a real apiary has opted in.
   */
  public function testStatusReportIsErrorOnceRealApiaryOptsIn(): void {
    Apiary::create(['name' => 'Real Apiary', 'ai_insights_enabled' => TRUE])->save();
    $requirement = $this->runtimeRequirement('assimilate_dev_only');
    $this->assertSame(2, $requirement['severity']);
    $this->assertStringContainsString('1 real apiary', (string) $requirement['value']);
  }

}
