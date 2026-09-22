<?php

declare(strict_types=1);

namespace Drupal\collective\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\collective\Entity\ApiClient;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Regenerate Token action for an API Client.
 *
 * Implemented as a real Drupal form (POST, CSRF-protected by the Form
 * API) rather than a bare GET link, since this action mutates the
 * client's token — the same [[0018-csrf-and-safe-http-methods]] reasoning
 * `\Drupal\nanoprobe\Form\SensorDeviceConfigDownloadForm` already
 * documents for its own regenerate action. Unlike that form, there is no
 * config descriptor to download here — the new plaintext is shown once,
 * via a status message, then never recoverable again.
 */
class ApiClientRegenerateTokenForm extends FormBase {

  /**
   * The client this form regenerates a token for.
   */
  protected ApiClient $apiClient;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'collective_api_client_regenerate_token_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ApiClient $api_client = NULL): array {
    if ($api_client === NULL || !$api_client->access('update')) {
      throw new AccessDeniedHttpException();
    }
    $this->apiClient = $api_client;

    $form['warning'] = [
      '#type' => 'container',
      '#markup' => '<p>' . $this->t('Regenerating this client\'s token <strong>immediately invalidates</strong> the previous one — any process still using it will start getting 401 responses from the API endpoint. The new token is shown once, here, after you confirm; it cannot be recovered afterward.') . '</p>',
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
    $plaintext = $this->apiClient->generateToken();
    $this->apiClient->save();

    $this->messenger()->addWarning($this->t('Token regenerated (copy this now — it will not be shown again): %token', [
      '%token' => $plaintext,
    ]));

    $form_state->setRedirectUrl($this->apiClient->toUrl());
  }

}
