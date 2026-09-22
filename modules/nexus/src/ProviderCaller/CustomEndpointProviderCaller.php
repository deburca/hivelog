<?php

declare(strict_types=1);

namespace Drupal\nexus\ProviderCaller;

use Drupal\key\KeyRepositoryInterface;
use Drupal\nexus\Entity\AiProviderConfig;
use Drupal\nexus\NexusProviderException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * POSTs to a beekeeper's own custom endpoint.
 *
 * Per docs/project-management/decisions/0100-nexus-in-process-ai-synthesis.md
 * Decision §3: a real, documented contract, not a vague "bring your own
 * integration" — nexus POSTs `{"system": ..., "context": ...}` and
 * expects the response body to already be the structured JSON object
 * `NexusResponseParser` validates (`verdict`/`recommendation`/`signals`/
 * `confidence`) — unlike the other two modes, there is no LLM free-text
 * wrapper to extract JSON out of; the endpoint is expected to return
 * that shape directly.
 */
class CustomEndpointProviderCaller implements ProviderCallerInterface {

  public function __construct(
    protected ClientInterface $httpClient,
    protected KeyRepositoryInterface $keyRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function call(array $context, string $systemPrompt, AiProviderConfig $config): string {
    $url = $config->get('endpoint_url')->value;
    if (!$url) {
      throw new NexusProviderException('AiProviderConfig has no endpoint_url configured.');
    }

    $headers = ['content-type' => 'application/json'];
    $key_id = $config->get('key')->value;
    if ($key_id) {
      $key = $this->keyRepository->getKey($key_id);
      $secret = $key?->getKeyValue();
      if (!$secret) {
        throw new NexusProviderException("AiProviderConfig's key '$key_id' could not be resolved to a credential.");
      }
      $headers['Authorization'] = 'Bearer ' . $secret;
    }

    try {
      $response = $this->httpClient->request('POST', $url, [
        'headers' => $headers,
        'json' => [
          'system' => $systemPrompt,
          'context' => $context,
        ],
      ]);
    }
    catch (GuzzleException $e) {
      throw new NexusProviderException('Custom endpoint request failed: ' . $e->getMessage(), 0, $e);
    }

    return (string) $response->getBody();
  }

}
