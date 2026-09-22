<?php

declare(strict_types=1);

namespace Drupal\nexus;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\collective\HiveContextBuilder;
use Drupal\hivelog\Entity\Hive;
use Drupal\nexus\Entity\AiProviderConfig;
use Drupal\nexus\Entity\HiveInsight;
use Drupal\nexus\ProviderCaller\ProviderCallerInterface;
use Psr\Log\LoggerInterface;

/**
 * Runs the whole in-process AI synthesis cycle — `nexus_cron()`'s engine.
 *
 * Per docs/project-management/decisions/0100-nexus-in-process-ai-synthesis.md
 * Decision §2: for each enabled `AiProviderConfig`, for each
 * `ai_insights_enabled` hive, builds context via `collective`'s
 * `HiveContextBuilder` (a direct PHP service call, not an HTTP round
 * trip), calls the configured provider, validates the result, and
 * writes `HiveInsight` directly — no bearer token, no write-back API.
 *
 * A single hive's failure (a bad provider response, a network error, a
 * misconfigured credential) is logged and skipped, never allowed to
 * abort the rest of a run — mirrors
 * `\Drupal\nanoprobe\SensorReadingRetentionService`'s own
 * "keep going, one bad row shouldn't sink the batch" spirit, though that
 * class has no equivalent per-item failure mode to guard against.
 *
 * Multiple simultaneously-enabled `AiProviderConfig` rows are not
 * deduplicated against each other — each independently generates its
 * own `HiveInsight` per opted-in hive. `AiProviderConfig`, like
 * `ApiClient`/`InsightAgent` before it, is normally exactly one row;
 * running more than one enabled at once is a configuration choice, not
 * a case this class needs to arbitrate.
 */
class NexusInsightGenerator {

  /**
   * The system prompt every chat-based provider call sends.
   *
   * Custom-endpoint mode also receives this (per ADR-0100 Decision §3's
   * "system prompt + context object" contract) even though it's talking
   * to a fixed API contract, not an LLM — the endpoint is free to ignore
   * it, but it documents the same expected response shape either way.
   */
  protected const SYSTEM_PROMPT = <<<'PROMPT'
You are an apiary assistant. You will be given a JSON object describing one hive's recent inspections, calendar status, and queen status. Respond with a single JSON object, and nothing else, with exactly these keys:

- "verdict": one of "act_now", "inspect_soon", or "all_clear".
- "recommendation": a short imperative sentence, e.g. "Add a super" or "Possible swarm risk — inspect within 2 days".
- "signals": the specific evidence behind your verdict, as a string with each point on its own line starting with "- ". Never give a bare verdict without citing the signals that led to it.
- "confidence" (optional): one of "high", "medium", or "low".

Respond with ONLY that JSON object — no other text, no markdown code fences.
PROMPT;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected HiveContextBuilder $contextBuilder,
    protected NexusResponseParser $responseParser,
    protected TimeInterface $time,
    protected LoggerInterface $logger,
    protected ProviderCallerInterface $aiModuleCaller,
    protected ProviderCallerInterface $directApiCaller,
    protected ProviderCallerInterface $customEndpointCaller,
  ) {}

  /**
   * Runs the full cycle for every enabled AiProviderConfig.
   *
   * Called from `nexus_cron()`.
   */
  public function generateAll(): void {
    $storage = $this->entityTypeManager->getStorage('ai_provider_config');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('enabled', TRUE)
      ->execute();
    if (empty($ids)) {
      return;
    }

    /** @var \Drupal\nexus\Entity\AiProviderConfig $config */
    foreach ($storage->loadMultiple($ids) as $config) {
      $this->generateForConfig($config);
    }
  }

  /**
   * Runs one AiProviderConfig against every opted-in hive.
   *
   * @param \Drupal\nexus\Entity\AiProviderConfig $config
   *   The config to run.
   *
   * @return int
   *   How many hives got a new HiveInsight written.
   */
  public function generateForConfig(AiProviderConfig $config): int {
    $count = 0;

    foreach ($this->loadOptedInHives() as $hive) {
      try {
        $this->generateForHive($hive, $config);
        $count++;
      }
      catch (\Throwable $e) {
        $this->logger->error('nexus failed to generate an insight for hive @hive using config @config: @message', [
          '@hive' => $hive->id(),
          '@config' => $config->label(),
          '@message' => $e->getMessage(),
        ]);
      }
    }

    if ($count > 0) {
      $config->set('last_run', $this->time->getRequestTime());
      $config->save();
    }

    return $count;
  }

  /**
   * Builds context, calls the provider, and writes one HiveInsight.
   *
   * @throws \Drupal\nexus\NexusProviderException
   *   If the provider call itself fails.
   * @throws \UnexpectedValueException
   *   If the provider's response doesn't parse/validate.
   */
  protected function generateForHive(Hive $hive, AiProviderConfig $config): void {
    $context = $this->contextBuilder->buildContext($hive);
    $raw = $this->callProvider($context, $config);
    $values = $this->responseParser->parse($raw);

    /** @var \Drupal\hivelog\Entity\Apiary $apiary */
    $apiary = $hive->get('apiary')->entity;

    HiveInsight::create([
      'apiary' => $apiary->id(),
      'hive' => $hive->id(),
      'scope' => 'hive',
      'verdict' => $values['verdict'],
      'recommendation' => $values['recommendation'],
      'signals' => $values['signals'],
      'confidence' => $values['confidence'],
      'context_snapshot' => json_encode($context),
      'generated' => $this->time->getRequestTime(),
    ])->save();
  }

  /**
   * Dispatches to the right ProviderCallerInterface for $config's mode.
   */
  protected function callProvider(array $context, AiProviderConfig $config): string {
    $mode = $config->get('mode')->value;
    $caller = match ($mode) {
      'ai_module' => $this->aiModuleCaller,
      'direct_api' => $this->directApiCaller,
      'custom_endpoint' => $this->customEndpointCaller,
      default => throw new NexusProviderException("AiProviderConfig mode '$mode' is not a recognised mode."),
    };

    return $caller->call($context, self::SYSTEM_PROMPT, $config);
  }

  /**
   * Loads every hive whose apiary has AI insights enabled.
   *
   * @return \Drupal\hivelog\Entity\Hive[]
   *   The opted-in hives.
   */
  protected function loadOptedInHives(): array {
    $apiary_storage = $this->entityTypeManager->getStorage('apiary');
    $apiary_ids = $apiary_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('ai_insights_enabled', TRUE)
      ->execute();
    if (empty($apiary_ids)) {
      return [];
    }

    $hive_storage = $this->entityTypeManager->getStorage('hive');
    $hive_ids = $hive_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('apiary', $apiary_ids, 'IN')
      ->execute();

    return $hive_ids ? array_values($hive_storage->loadMultiple($hive_ids)) : [];
  }

}
