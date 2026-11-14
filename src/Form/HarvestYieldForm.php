<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hivelog\HivelogEntityHierarchy;

/**
 * Form for a harvest recorded directly, with no calendar action (task 0271).
 *
 * Reached from "Record harvest" on a hive or apiary page, which pre-set the
 * hive and apiary. A yield reported with a planned action is not edited
 * here: it belongs to its action log's form (HarvestYieldFormTrait).
 */
class HarvestYieldForm extends ContentEntityForm {

  use HivelogEntityFormTrait;
  use ApiaryScopedAutocompleteTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    /** @var \Drupal\hivelog\Entity\HarvestYield $harvest */
    $harvest = $this->entity;

    // Only this apiary's products, and none that are discontinued.
    $this->scopeAutocompleteToApiary($form, 'product', $harvest->get('apiary')->target_id);

    // Say where the harvest is being recorded: the hive and apiary are set
    // by the page the form was opened from and are not editable.
    $hive = $harvest->get('hive')->entity;
    $apiary = $harvest->get('apiary')->entity;
    if ($hive || $apiary) {
      $form['harvest_source'] = [
        '#type' => 'item',
        '#title' => $hive ? $this->t('Hive') : $this->t('Apiary'),
        '#markup' => $hive
          ? $this->t('@hive · @apiary', ['@hive' => $hive->label(), '@apiary' => $apiary ? $apiary->label() : ''])
          : $apiary->label(),
        '#weight' => -10,
      ];
    }

    if ($harvest->isNew() && isset($form['harvest_date']['widget'][0]['value']) && $harvest->get('harvest_date')->isEmpty()) {
      $form['harvest_date']['widget'][0]['value']['#default_value'] = new \DateTime('today');
    }
    if (isset($form['harvest_date']['widget'][0]['value'])) {
      $form['harvest_date']['widget'][0]['value']['#required'] = TRUE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $harvest = $this->entity;
    $status = $harvest->save();

    $this->messenger()->addStatus($status === SAVED_NEW
      ? $this->t('Harvest recorded: %label.', ['%label' => $harvest->label()])
      : $this->t('Harvest updated: %label.', ['%label' => $harvest->label()]));

    $form_state->setRedirectUrl(HivelogEntityHierarchy::parentOrCollectionUrl($harvest));
    return $status;
  }

}
