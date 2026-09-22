<?php

declare(strict_types=1);

namespace Drupal\nanoprobe\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Sensor Device add/edit forms.
 *
 * Two responsibilities beyond the standard entity form, both mirroring
 * `\Drupal\nexus\Form\AiProviderConfigForm`'s established shape: hiding
 * `hive` via `#states` unless `scope = hive` (task 0106's own explicit
 * requirement, adapted from that class's `mode`-conditional pattern), and
 * showing the auto-generated token exactly once via a status message on
 * creation, per `\Drupal\collective\Form\ApiClientForm`'s one-time-plaintext
 * UX (task 0091) — `SensorDevice::preSave()` generates it, this form's
 * only extra job is surfacing it.
 */
class SensorDeviceForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    $this->addScopeConditionalStates($form);

    return $form;
  }

  /**
   * Shows/hides `hive` based on `scope`.
   *
   * `hive`'s own field description already states when it's required
   * (kernel tests can't exercise real client-side JS, so that text is the
   * substantive, always-testable version of this information); `#states`
   * is the progressive-enhancement layer on top, for a beekeeper actually
   * using the form in a browser.
   */
  protected function addScopeConditionalStates(array &$form): void {
    if (isset($form['hive']['widget'][0]['target_id'])) {
      $form['hive']['widget'][0]['target_id']['#states'] = [
        'visible' => [':input[name="scope"]' => ['value' => 'hive']],
      ];
    }
  }

  /**
   * {@inheritdoc}
   *
   * `SensorDevice::preSave()` enforces the same scope-conditional
   * requirement, but throwing `\InvalidArgumentException` from there
   * surfaces as a raw, uncaught "unexpected error" page, not a normal
   * inline form error — mirrors `AiProviderConfigForm::validateForm()`'s
   * own reasoning exactly (task 0104). Duplicating the check here, against
   * a `buildEntity()`'d clone, turns that into the ordinary
   * `setErrorByName()` experience every other hivelog form gives; the
   * entity-level check stays too, as the real invariant guard for any
   * non-form caller (e.g. a future import, or direct API use).
   *
   * Checks `hive`'s `target_id` directly rather than calling the field's
   * own `isEmpty()` — an unselected `entity_reference_autocomplete`
   * widget submits `target_id => ''`, which `EntityReferenceItem::
   * isEmpty()` doesn't treat as empty (it only checks for `NULL`).
   * `SensorDevice::preSave()`'s own invariant check already reads
   * `target_id` directly for exactly this reason; mirrored here instead
   * of duplicating the `isEmpty()` pitfall.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $entity = $this->buildEntity($form, $form_state);
    $scope = $entity->get('scope')->value;
    $has_hive = (bool) $entity->get('hive')->target_id;

    if ($scope === 'hive' && !$has_hive) {
      $form_state->setErrorByName('hive', $this->t('Hive is required when scope is "Hive".'));
    }
    if ($scope === 'apiary' && $has_hive) {
      $form_state->setErrorByName('hive', $this->t('Hive must be left empty when scope is "Apiary".'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $plaintext = $entity->getPlainTextToken();
      $this->messenger()->addStatus($this->t('Sensor device %label has been created.', [
        '%label' => $entity->label(),
      ]));
      if ($plaintext) {
        $this->messenger()->addWarning($this->t('Token (copy this now — it will not be shown again): %token', [
          '%token' => $plaintext,
        ]));
      }
    }
    else {
      $this->messenger()->addStatus($this->t('Sensor device %label has been updated.', [
        '%label' => $entity->label(),
      ]));
    }

    $form_state->setRedirectUrl($entity->toUrl());
    return $status;
  }

}
