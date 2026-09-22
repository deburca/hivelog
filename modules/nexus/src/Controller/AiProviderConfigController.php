<?php

declare(strict_types=1);

namespace Drupal\nexus\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\nexus\Entity\AiProviderConfig;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for the AI Provider Config canonical page.
 *
 * Beyond the standard field summary, states which mode is active and —
 * for `ai_module` mode specifically — whether the Drupal AI module is
 * actually, currently installed and has a default chat provider
 * configured, per task 0104's own explicit requirement: "a config
 * pointing at an uninstalled/misconfigured AI module should be visibly
 * wrong, not silently inert." A real, live check performed on every
 * page view, not a cached assumption from whenever the config was last
 * saved — the AI module's own installed/configured state can change
 * independently of this entity at any time.
 */
class AiProviderConfigController extends ControllerBase {

  /**
   * Builds the AI Provider Config canonical page.
   *
   * @param \Drupal\nexus\Entity\AiProviderConfig $ai_provider_config
   *   The config to display.
   *
   * @return array
   *   A render array.
   */
  public function view(AiProviderConfig $ai_provider_config): array {
    if (!$ai_provider_config->access('view')) {
      throw new AccessDeniedHttpException();
    }

    $mode = $ai_provider_config->get('mode')->value;
    $rows = [
      [$this->t('Label'), $ai_provider_config->label()],
      [$this->t('Mode'), AiProviderConfig::MODES[$mode] ?? $mode],
      [$this->t('Enabled'), $ai_provider_config->get('enabled')->value ? $this->t('Yes') : $this->t('No')],
    ];

    if ($mode === 'direct_api' && !$ai_provider_config->get('provider')->isEmpty()) {
      $rows[] = [$this->t('Provider'), $ai_provider_config->get('provider')->value];
    }
    if ($mode === 'custom_endpoint' && !$ai_provider_config->get('endpoint_url')->isEmpty()) {
      $rows[] = [$this->t('Endpoint URL'), $ai_provider_config->get('endpoint_url')->value];
    }
    if ($mode !== 'ai_module' && !$ai_provider_config->get('key')->isEmpty()) {
      $rows[] = [$this->t('Key'), $ai_provider_config->get('key')->value];
    }

    if (!$ai_provider_config->get('last_run')->isEmpty()) {
      $rows[] = [
        $this->t('Last run'),
        $this->dateFormatter()->format((int) $ai_provider_config->get('last_run')->value),
      ];
    }
    else {
      $rows[] = [$this->t('Last run'), $this->t('Never')];
    }

    $build['summary'] = [
      '#type' => 'table',
      '#header' => [$this->t('Field'), $this->t('Value')],
      '#attributes' => ['class' => ['hivelog-ai-provider-config-table']],
      '#attached' => ['library' => ['hivelog/tables']],
      '#rows' => $rows,
    ];

    if ($mode === 'ai_module') {
      $build['ai_module_status'] = $this->buildAiModuleStatus();
    }

    return $build;
  }

  /**
   * Builds the live Drupal-AI-module status block for `ai_module` mode.
   *
   * @return array
   *   A render array — a positive confirmation when the module is
   *   installed and has a default chat provider configured, or a clear
   *   warning naming exactly what's missing when it doesn't.
   */
  protected function buildAiModuleStatus(): array {
    if (!$this->moduleHandler()->moduleExists('ai')) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        '#value' => $this->t('The Drupal AI module is not installed/enabled — nexus_cron() cannot use this config until it is.'),
      ];
    }

    /** @var \Drupal\ai\AiProviderPluginManager $provider_manager */
    $provider_manager = \Drupal::service('ai.provider');
    $provider_info = $provider_manager->getDefaultProviderForOperationType('chat');

    if (!$provider_info) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        '#value' => $this->t('The Drupal AI module is installed, but no default chat provider is configured — set one at <code>admin/config/ai/settings</code> before nexus_cron() can use this config.'),
      ];
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => ['class' => ['messages', 'messages--status']],
      '#value' => $this->t('The Drupal AI module is installed and configured — default chat provider: @provider (@model).', [
        '@provider' => $provider_info['provider_id'],
        '@model' => $provider_info['model_id'],
      ]),
    ];
  }

  /**
   * Title callback for the canonical page.
   *
   * @param \Drupal\nexus\Entity\AiProviderConfig $ai_provider_config
   *   The config being displayed.
   *
   * @return string
   *   The page title.
   */
  public function title(AiProviderConfig $ai_provider_config): string {
    return $ai_provider_config->label();
  }

  /**
   * The date formatter service.
   *
   * @return \Drupal\Core\Datetime\DateFormatterInterface
   *   The date formatter.
   */
  protected function dateFormatter() {
    return \Drupal::service('date.formatter');
  }

}
