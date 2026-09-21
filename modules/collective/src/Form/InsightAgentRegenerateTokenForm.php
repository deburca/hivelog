<?php

declare(strict_types=1);

namespace Drupal\collective\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\collective\Entity\InsightAgent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Regenerate Token action for an Insight Agent.
 *
 * Implemented as a real Drupal form (POST, CSRF-protected by the Form
 * API) rather than a bare GET link, since this action mutates the
 * agent's token — the same [[0018-csrf-and-safe-http-methods]] reasoning
 * `\Drupal\nanoprobe\Form\SensorDeviceConfigDownloadForm` already
 * documents for its own regenerate action. Unlike that form, there is no
 * config descriptor to download here — the new plaintext is shown once,
 * via a status message, then never recoverable again.
 */
class InsightAgentRegenerateTokenForm extends FormBase {

  /**
   * The agent this form regenerates a token for.
   */
  protected InsightAgent $insightAgent;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'collective_insight_agent_regenerate_token_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?InsightAgent $insight_agent = NULL): array {
    if ($insight_agent === NULL || !$insight_agent->access('update')) {
      throw new AccessDeniedHttpException();
    }
    $this->insightAgent = $insight_agent;

    $form['warning'] = [
      '#type' => 'container',
      '#markup' => '<p>' . $this->t('Regenerating this agent\'s token <strong>immediately invalidates</strong> the previous one — any external agent process still using it will start getting 401 responses from both API endpoints. The new token is shown once, here, after you confirm; it cannot be recovered afterward.') . '</p>',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Regenerate Token'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $plaintext = $this->insightAgent->generateToken();
    $this->insightAgent->save();

    $this->messenger()->addWarning($this->t('Token regenerated (copy this now — it will not be shown again): %token', [
      '%token' => $plaintext,
    ]));

    $form_state->setRedirectUrl($this->insightAgent->toUrl());
  }

}
