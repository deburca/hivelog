<?php

declare(strict_types=1);

namespace Drupal\nexus\ProviderCaller;

use Drupal\nexus\Entity\AiProviderConfig;

/**
 * One of the three provider integration modes ADR-0100 Decision §3 defines.
 *
 * Each implementation sends $context (already minimised by
 * `HiveContextBuilder`) and $systemPrompt to one kind of provider and
 * returns its raw response body as a string — expected to be a JSON
 * object with `verdict`/`recommendation`/`signals`/`confidence` keys,
 * parsed and validated afterward by `NexusResponseParser`, uniformly
 * across all three modes.
 */
interface ProviderCallerInterface {

  /**
   * Calls the provider and returns its raw response text.
   *
   * @param array $context
   *   The minimised hive/apiary context, as `HiveContextBuilder` built
   *   it — ready for `json_encode()`.
   * @param string $systemPrompt
   *   The instruction telling the provider what shape to respond in.
   * @param \Drupal\nexus\Entity\AiProviderConfig $config
   *   The config to call with — supplies the provider identifier,
   *   endpoint URL, and/or `key` reference, depending on mode.
   *
   * @return string
   *   The provider's raw response body — expected to be a JSON object
   *   matching `NexusResponseParser::parse()`'s contract.
   *
   * @throws \Drupal\nexus\NexusProviderException
   *   If the call cannot be completed at all (misconfiguration, a
   *   missing soft dependency, or a network/API failure). A response
   *   that came back but doesn't parse/validate is NOT this exception —
   *   that's `NexusResponseParser`'s job, downstream of a successful
   *   call.
   */
  public function call(array $context, string $systemPrompt, AiProviderConfig $config): string;

}
