<?php

declare(strict_types=1);

namespace Drupal\Tests\nexus\Kernel\Fixture;

use Drupal\nexus\Entity\AiProviderConfig;
use Drupal\nexus\NexusProviderException;
use Drupal\nexus\ProviderCaller\ProviderCallerInterface;

/**
 * A ProviderCallerInterface test double — no real outbound HTTP, ever.
 *
 * Either returns a fixed raw response string, or throws
 * NexusProviderException with a fixed message — never both, per call
 * site — covering everything NexusInsightGeneratorTest needs to exercise
 * both the success and the provider-failure paths.
 */
class FakeProviderCaller implements ProviderCallerInterface {

  private function __construct(
    protected ?string $response,
    protected ?string $failureMessage,
  ) {}

  /**
   * A fake that returns $response from every call().
   */
  public static function returning(string $response): self {
    return new self($response, NULL);
  }

  /**
   * A fake that throws NexusProviderException($message) from every call().
   */
  public static function throwing(string $message): self {
    return new self(NULL, $message);
  }

  /**
   * {@inheritdoc}
   */
  public function call(array $context, string $systemPrompt, AiProviderConfig $config): string {
    if ($this->failureMessage !== NULL) {
      throw new NexusProviderException($this->failureMessage);
    }
    return $this->response;
  }

}
