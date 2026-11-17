<?php

declare(strict_types=1);

namespace Drupal\nexus\ProviderCaller;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\nexus\Entity\AiProviderConfig;
use Drupal\nexus\NexusProviderException;

/**
 * Calls the Drupal AI contrib module, if installed and configured.
 *
 * Per docs/project-management/decisions/0100-nexus-in-process-ai-synthesis.md
 * §Context point 2: a genuinely optional, soft integration — `nexus`
 * does not depend on the `ai` module ([[0006-contrib-dependency-policy]]),
 * so this class never type-hints any of that module's own classes, only
 * calls its `ai.provider` service dynamically once `moduleHandler()`
 * confirms the module is actually enabled. When it isn't, or no default
 * chat provider is configured within it, this throws
 * `NexusProviderException` rather than crashing `nexus_cron()`.
 *
 * Credential handling is intentionally NOT this class's job — a
 * configured Drupal AI provider plugin manages its own credentials
 * (typically via `Key`, exactly as `AiProviderConfig` does for the other
 * two modes), so `AiProviderConfig.key` is not required, and not used
 * here, when mode is `ai_module`.
 */
class AiModuleProviderCaller implements ProviderCallerInterface {

  /**
   * Extra tags attached to every chat call this class makes.
   *
   * Lets anything watching the AI module's own logging/observability
   * tooling (e.g. `ai_logging`, if installed) distinguish nexus's calls
   * from anything else using the same site-wide default provider.
   */
  protected const CALL_TAGS = ['nexus'];

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function call(array $context, string $systemPrompt, AiProviderConfig $config): string {
    if (!$this->moduleHandler->moduleExists('ai')) {
      throw new NexusProviderException('The Drupal AI module is not installed/enabled — this AiProviderConfig cannot use mode "ai_module" until it is.');
    }

    /** @var \Drupal\ai\AiProviderPluginManager $provider_manager */
    $provider_manager = \Drupal::service('ai.provider');
    $provider_info = $provider_manager->getDefaultProviderForOperationType('chat');
    if (!$provider_info) {
      throw new NexusProviderException('No default chat provider is configured in the Drupal AI module — set one at admin/config/ai/settings.');
    }

    try {
      $provider = $provider_manager->createInstance($provider_info['provider_id']);
      $output = $provider->chat(
        $systemPrompt . "\n\n" . json_encode($context),
        $provider_info['model_id'],
        self::CALL_TAGS
      );
      return $output->getNormalized()->getText();
    }
    catch (\Throwable $e) {
      throw new NexusProviderException('Drupal AI module chat call failed: ' . $e->getMessage(), 0, $e);
    }
  }

}
