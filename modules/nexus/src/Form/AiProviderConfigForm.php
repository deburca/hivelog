<?php

declare(strict_types=1);

namespace Drupal\nexus\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for AI Provider Config add/edit forms.
 *
 * Two responsibilities beyond the standard entity form: conditionally
 * showing/hiding `provider`, `endpoint_url`, and `key` based on the
 * selected `mode` (task 0104's own explicit requirement), and swapping
 * the `key` field's plain textfield widget for Key module's own
 * `key_select` render element — so a beekeeper picks an already-defined
 * Key entity, or creates one through Key's own UI, without this form
 * ever building its own credential-entry UI.
 */
class AiProviderConfigForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    $this->swapKeyWidgetForKeySelect($form);
    $this->addModeConditionalStates($form);

    return $form;
  }

  /**
   * Replaces `key`'s plain textfield widget with Key's `key_select`.
   *
   * The field itself stays a plain `string` (holding a Key config-entity
   * id) — only the form widget changes, per this class's own docblock.
   */
  protected function swapKeyWidgetForKeySelect(array &$form): void {
    if (!isset($form['key']['widget'][0]['value'])) {
      return;
    }

    $current = $form['key']['widget'][0]['value'];
    $form['key']['widget'][0]['value'] = [
      '#type' => 'key_select',
      '#title' => $current['#title'] ?? $this->t('Key'),
      '#description' => $current['#description'] ?? NULL,
      '#default_value' => $current['#default_value'] ?? NULL,
      '#empty_option' => $this->t('- Select -'),
    ];
  }

  /**
   * Shows/hides `provider`, `endpoint_url`, and `key` based on `mode`.
   *
   * `mode`'s own field description already states which mode each field
   * applies to (kernel tests can't exercise real client-side JS, so that
   * text is the substantive, always-testable version of this
   * information — see `\Drupal\collective\collective.module`'s own
   * disclosure text for the same reasoning); `#states` is the
   * progressive-enhancement layer on top, for a beekeeper actually using
   * the form in a browser.
   */
  protected function addModeConditionalStates(array &$form): void {
    $mode_selector = ':input[name="mode"]';

    if (isset($form['provider']['widget'][0]['value'])) {
      $form['provider']['widget'][0]['value']['#states'] = [
        'visible' => [$mode_selector => ['value' => 'direct_api']],
      ];
    }

    if (isset($form['endpoint_url']['widget'][0]['value'])) {
      $form['endpoint_url']['widget'][0]['value']['#states'] = [
        'visible' => [$mode_selector => ['value' => 'custom_endpoint']],
      ];
    }

    if (isset($form['key']['widget'][0]['value'])) {
      $form['key']['widget'][0]['value']['#states'] = [
        'visible' => [
          $mode_selector => [
            ['value' => 'direct_api'],
            ['value' => 'custom_endpoint'],
          ],
        ],
      ];
    }
  }

  /**
   * {@inheritdoc}
   *
   * `AiProviderConfig::preSave()` enforces the same mode-conditional
   * requirements, but throwing `\InvalidArgumentException` from there
   * surfaces as a raw, uncaught "unexpected error" page, not a normal
   * inline form error — confirmed by actually submitting this form
   * without a key selected. Duplicating the checks here, against a
   * `buildEntity()`'d clone, turns that into the ordinary
   * `setErrorByName()` experience every other hivelog form gives; the
   * entity-level check stays too, as the real invariant guard for any
   * non-form caller (e.g. a future import, or direct API use).
   *
   * Deliberately does NOT skip these checks when `parent::validateForm()`
   * already recorded some other, unrelated field error — `buildEntity()`
   * only needs a form/form_state to read submitted values from, not an
   * already-valid entity, and a beekeeper fixing one error at a time
   * shouldn't have this one silently withheld until the other is fixed
   * first.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $entity = $this->buildEntity($form, $form_state);
    $mode = $entity->get('mode')->value;

    if ($mode !== 'ai_module' && $entity->get('key')->isEmpty()) {
      $form_state->setErrorByName('key', $this->t('Key is required unless mode is "Drupal AI module".'));
    }
    if ($mode === 'direct_api' && $entity->get('provider')->isEmpty()) {
      $form_state->setErrorByName('provider', $this->t('Provider is required when mode is "Direct provider API".'));
    }
    if ($mode === 'custom_endpoint' && $entity->get('endpoint_url')->isEmpty()) {
      $form_state->setErrorByName('endpoint_url', $this->t('Custom Endpoint URL is required when mode is "Custom endpoint".'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = $entity->save();

    $message = $status === SAVED_NEW
      ? $this->t('AI provider config %label has been created.', ['%label' => $entity->label()])
      : $this->t('AI provider config %label has been updated.', ['%label' => $entity->label()]);
    $this->messenger()->addStatus($message);

    $form_state->setRedirectUrl($entity->toUrl());
    return $status;
  }

}
