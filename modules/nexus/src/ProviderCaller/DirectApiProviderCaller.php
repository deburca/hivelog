<?php

declare(strict_types=1);

namespace Drupal\nexus\ProviderCaller;

use Drupal\key\KeyRepositoryInterface;
use Drupal\nexus\Entity\AiProviderConfig;
use Drupal\nexus\NexusProviderException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Calls a provider's own API directly — nexus's own minimal HTTP client.
 *
 * Per docs/project-management/decisions/0100-nexus-in-process-ai-synthesis.md
 * Decision §3: Anthropic first — the model tier
 * [[0087-ai-insights-hosting-and-privacy-model]] already priced out and
 * recommended starting at; OpenAI/Gemini are documented as
 * straightforward follow-ups against the same `ProviderCallerInterface`,
 * not required for Phase 1. `AiProviderConfig.provider` values other
 * than `anthropic` are rejected with a clear error, not silently
 * mishandled.
 *
 * The model id is a fixed Phase 1 default
 * (`ANTHROPIC_MODEL`), not yet a configurable field on
 * `AiProviderConfig` — matches the Haiku-tier pricing
 * [[0087-ai-insights-hosting-and-privacy-model]] costed this feature at.
 * A configurable model field is a natural follow-up once a second
 * provider makes "which model" a real choice, not before.
 */
class DirectApiProviderCaller implements ProviderCallerInterface {

  /**
   * The Anthropic Messages API endpoint.
   */
  protected const ANTHROPIC_ENDPOINT = 'https://api.anthropic.com/v1/messages';

  /**
   * The Anthropic API version header value this class was built against.
   */
  protected const ANTHROPIC_API_VERSION = '2023-06-01';

  /**
   * The Phase 1 default Anthropic model — see this class's own docblock.
   */
  protected const ANTHROPIC_MODEL = 'claude-3-5-haiku-latest';

  /**
   * The maximum tokens requested per call.
   *
   * Generous for this task's short JSON output shape, not a real limit
   * in practice.
   */
  protected const MAX_TOKENS = 1024;

  public function __construct(
    protected ClientInterface $httpClient,
    protected KeyRepositoryInterface $keyRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function call(array $context, string $systemPrompt, AiProviderConfig $config): string {
    $provider = $config->get('provider')->value;
    if ($provider !== 'anthropic') {
      throw new NexusProviderException("Direct-API provider '$provider' is not yet supported — only anthropic is implemented for Phase 1, per ADR-0100 Decision §3.");
    }

    $secret = $this->resolveSecret($config);

    try {
      $response = $this->httpClient->request('POST', self::ANTHROPIC_ENDPOINT, [
        'headers' => [
          'x-api-key' => $secret,
          'anthropic-version' => self::ANTHROPIC_API_VERSION,
          'content-type' => 'application/json',
        ],
        'json' => [
          'model' => self::ANTHROPIC_MODEL,
          'max_tokens' => self::MAX_TOKENS,
          'system' => $systemPrompt,
          'messages' => [
            ['role' => 'user', 'content' => json_encode($context)],
          ],
        ],
      ]);
    }
    catch (GuzzleException $e) {
      throw new NexusProviderException('Anthropic API request failed: ' . $e->getMessage(), 0, $e);
    }

    $body = json_decode((string) $response->getBody(), TRUE);
    return $body['content'][0]['text'] ?? '';
  }

  /**
   * Resolves `$config`'s `key` field to the actual provider credential.
   *
   * Called once per request, immediately before making it — never
   * cached, logged, or persisted, per ADR-0100's Implementation notes.
   */
  protected function resolveSecret(AiProviderConfig $config): string {
    $key_id = $config->get('key')->value;
    $key = $key_id ? $this->keyRepository->getKey($key_id) : NULL;
    $secret = $key?->getKeyValue();
    if (!$secret) {
      throw new NexusProviderException("AiProviderConfig's key '$key_id' could not be resolved to a credential.");
    }
    return $secret;
  }

}
