<?php

declare(strict_types=1);

namespace Drupal\nexus;

use Drupal\nexus\Entity\HiveInsight;

/**
 * Parses and validates a provider's raw response text.
 *
 * The shared downstream step for all three `ProviderCallerInterface`
 * modes — adapted from
 * `\Drupal\collective\Controller\InsightAgentApiController::validateAndResolve()`
 * (deleted in task 0101), which validated the same
 * verdict/recommendation/signals/confidence shape for an external
 * agent's write-back body. Simpler here: `NexusInsightGenerator` already
 * knows which hive/apiary it's generating for (it's iterating them
 * itself), so unlike the old write-back body, a provider's response
 * carries no `hive`/`apiary`/`scope` fields to resolve at all.
 */
class NexusResponseParser {

  /**
   * Parses and validates $raw into HiveInsight-ready field values.
   *
   * @param string $raw
   *   The provider's raw response text — expected to be a single JSON
   *   object.
   *
   * @return array
   *   `['verdict' => string, 'recommendation' => string, 'signals' =>
   *   string, 'confidence' => string|null]`.
   *
   * @throws \UnexpectedValueException
   *   If $raw isn't valid JSON, isn't a JSON object, or fails
   *   validation — the message lists every problem found, not just the
   *   first.
   */
  public function parse(string $raw): array {
    try {
      $decoded = json_decode($raw, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      throw new \UnexpectedValueException('Provider response is not valid JSON: ' . substr($raw, 0, 200));
    }

    if (!is_array($decoded) || array_is_list($decoded)) {
      throw new \UnexpectedValueException('Provider response must be a single JSON object.');
    }

    $errors = [];

    $verdict = $decoded['verdict'] ?? NULL;
    if (!is_string($verdict) || !isset(HiveInsight::VERDICTS[$verdict])) {
      $errors[] = 'verdict must be one of: ' . implode(', ', array_keys(HiveInsight::VERDICTS));
    }

    $recommendation = $decoded['recommendation'] ?? NULL;
    if (!is_string($recommendation) || trim($recommendation) === '') {
      $errors[] = 'recommendation is required.';
    }

    $signals = $decoded['signals'] ?? NULL;
    if (!is_string($signals) || trim($signals) === '') {
      $errors[] = 'signals is required.';
    }

    $confidence = $decoded['confidence'] ?? NULL;
    $confidence_valid = $confidence === NULL || (is_string($confidence) && isset(HiveInsight::CONFIDENCE_LEVELS[$confidence]));
    if (!$confidence_valid) {
      $errors[] = 'confidence, if given, must be one of: ' . implode(', ', array_keys(HiveInsight::CONFIDENCE_LEVELS));
    }

    if (!empty($errors)) {
      throw new \UnexpectedValueException('Provider response validation failed: ' . implode(' ', $errors));
    }

    return [
      'verdict' => $verdict,
      'recommendation' => $recommendation,
      'signals' => $signals,
      'confidence' => is_string($confidence) ? $confidence : NULL,
    ];
  }

}
