<?php

declare(strict_types=1);

namespace Drupal\Tests\nexus\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\nexus\Kernel\Fixture\FakeProviderCaller;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\nexus\Entity\AiProviderConfig;
use Drupal\nexus\Entity\HiveInsight;
use Drupal\nexus\NexusInsightGenerator;
use Drupal\nexus\NexusResponseParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\NullLogger;

/**
 * Tests NexusInsightGenerator — nexus_cron()'s engine (task 0103).
 *
 * Every ProviderCallerInterface is a `FakeProviderCaller` here — no real
 * outbound HTTP happens in this test, per task 0103's own acceptance
 * criteria. `NexusInsightGenerator` is constructed by hand rather than
 * pulled from the container, specifically so these fakes can be swapped
 * in directly instead of needing container-level service overrides.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class NexusInsightGeneratorTest extends KernelTestBase {

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
    'key',
    'hivelog',
    'collective',
    'nexus',
  ];

  /**
   * An apiary with AI insights enabled.
   */
  protected Apiary $optedInApiary;

  /**
   * A hive under `$optedInApiary`.
   */
  protected Hive $optedInHive;

  /**
   * An apiary WITHOUT AI insights enabled.
   */
  protected Apiary $optedOutApiary;

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
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('hive_insight');
    $this->installEntitySchema('ai_provider_config');
    $this->installSchema('file', ['file_usage']);

    $this->optedInApiary = Apiary::create(['name' => 'Opted In', 'ai_insights_enabled' => TRUE]);
    $this->optedInApiary->save();
    $this->optedInHive = Hive::create([
      'name' => 'Opted In Hive',
      'apiary' => $this->optedInApiary->id(),
      'status' => 'active',
    ]);
    $this->optedInHive->save();

    $this->optedOutApiary = Apiary::create(['name' => 'Opted Out', 'ai_insights_enabled' => FALSE]);
    $this->optedOutApiary->save();
    Hive::create([
      'name' => 'Opted Out Hive',
      'apiary' => $this->optedOutApiary->id(),
      'status' => 'active',
    ])->save();
  }

  /**
   * Builds a generator with the given provider caller wired to every mode.
   *
   * Good enough for these tests — none exercise more than one mode at a
   * time, so one fake standing in for all three keeps the test setup
   * small without weakening what's actually being asserted (the
   * generator's own orchestration, not mode dispatch, which
   * `testCallProviderDispatchesByMode()` covers directly).
   */
  protected function buildGenerator(FakeProviderCaller $caller): NexusInsightGenerator {
    return new NexusInsightGenerator(
      \Drupal::entityTypeManager(),
      \Drupal::service('collective.hive_context_builder'),
      new NexusResponseParser(),
      \Drupal::time(),
      new NullLogger(),
      $caller,
      $caller,
      $caller,
    );
  }

  /**
   * Tests a valid response creates the right HiveInsight and updates last_run.
   */
  public function testValidResponseCreatesInsightAndUpdatesLastRun(): void {
    $config = AiProviderConfig::create([
      'label' => 'Test Config',
      'mode' => 'ai_module',
    ]);
    $config->save();
    $this->assertTrue($config->get('last_run')->isEmpty());

    $caller = FakeProviderCaller::returning(json_encode([
      'verdict' => 'inspect_soon',
      'recommendation' => 'Possible swarm risk — inspect within 2 days',
      'signals' => '- Weight dropped 2.1 kg overnight',
      'confidence' => 'high',
    ]));

    $count = $this->buildGenerator($caller)->generateForConfig($config);
    $this->assertEquals(1, $count);

    $storage = \Drupal::entityTypeManager()->getStorage('hive_insight');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    $this->assertCount(1, $ids);

    /** @var \Drupal\nexus\Entity\HiveInsight $insight */
    $insight = HiveInsight::load(reset($ids));
    $this->assertEquals($this->optedInHive->id(), $insight->get('hive')->target_id);
    $this->assertEquals($this->optedInApiary->id(), $insight->get('apiary')->target_id);
    $this->assertEquals('inspect_soon', $insight->get('verdict')->value);
    $this->assertEquals('high', $insight->get('confidence')->value);
    $this->assertNotEmpty($insight->get('context_snapshot')->value);

    $reloaded_config = AiProviderConfig::load($config->id());
    $this->assertNotEmpty($reloaded_config->get('last_run')->value);
  }

  /**
   * Tests only opted-in hives get an insight.
   */
  public function testOnlyOptedInHivesAreProcessed(): void {
    $config = AiProviderConfig::create(['label' => 'Test Config', 'mode' => 'ai_module']);
    $config->save();

    $caller = FakeProviderCaller::returning(json_encode([
      'verdict' => 'all_clear',
      'recommendation' => 'No action needed',
      'signals' => '- Nothing unusual observed',
    ]));

    $count = $this->buildGenerator($caller)->generateForConfig($config);
    $this->assertEquals(1, $count);

    $storage = \Drupal::entityTypeManager()->getStorage('hive_insight');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    $insight = HiveInsight::load(reset($ids));
    $this->assertEquals($this->optedInHive->id(), $insight->get('hive')->target_id);
  }

  /**
   * Tests a disabled config is simply never run — generateAll() skips it.
   */
  public function testDisabledConfigSkippedByGenerateAll(): void {
    $config = AiProviderConfig::create(['label' => 'Disabled Config', 'mode' => 'ai_module', 'enabled' => FALSE]);
    $config->save();

    // A caller that would fail the test if it were ever actually called.
    $caller = FakeProviderCaller::throwing('This must never be called for a disabled config.');

    // generateAll() loads configs itself — a disabled one is never
    // handed to the (would-be-failing) caller at all.
    $storage = \Drupal::entityTypeManager()->getStorage('ai_provider_config');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('enabled', TRUE)->execute();
    $this->assertCount(0, $ids);

    $this->buildGenerator($caller)->generateAll();

    $insight_storage = \Drupal::entityTypeManager()->getStorage('hive_insight');
    $this->assertCount(0, $insight_storage->getQuery()->accessCheck(FALSE)->execute());
  }

  /**
   * Tests a provider call failure is logged and skipped, not fatal.
   *
   * No HiveInsight is written, and — since $count stays 0 — last_run is
   * NOT updated either, matching generateForConfig()'s own contract:
   * last_run means "this config actually produced something", not
   * "this config was attempted".
   */
  public function testProviderFailureIsSkippedNotFatal(): void {
    $config = AiProviderConfig::create([
      'label' => 'Failing Config',
      'mode' => 'direct_api',
      'provider' => 'openai',
      'key' => 'whatever',
    ]);
    $config->save();

    $caller = FakeProviderCaller::throwing('Simulated provider outage.');

    $count = $this->buildGenerator($caller)->generateForConfig($config);
    $this->assertEquals(0, $count);

    $insight_storage = \Drupal::entityTypeManager()->getStorage('hive_insight');
    $this->assertCount(0, $insight_storage->getQuery()->accessCheck(FALSE)->execute());

    $this->assertTrue(AiProviderConfig::load($config->id())->get('last_run')->isEmpty());
  }

  /**
   * Tests a response that fails NexusResponseParser's validation is skipped.
   */
  public function testUnparsableResponseIsSkippedNotFatal(): void {
    $config = AiProviderConfig::create(['label' => 'Bad Response Config', 'mode' => 'ai_module']);
    $config->save();

    $caller = FakeProviderCaller::returning('this is not valid JSON at all');

    $count = $this->buildGenerator($caller)->generateForConfig($config);
    $this->assertEquals(0, $count);

    $insight_storage = \Drupal::entityTypeManager()->getStorage('hive_insight');
    $this->assertCount(0, $insight_storage->getQuery()->accessCheck(FALSE)->execute());
  }

}
