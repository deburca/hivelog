<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Product add/edit forms.
 */
class ProductForm extends ContentEntityForm {

  use HivelogEntityFormTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    // Suggest what bees usually give (task 0270), as native datalists: a
    // suggestion only, nothing is created unasked and any other name or unit
    // can still be typed. No JavaScript, so choosing a name does not fill in
    // the unit; the unit field has its own short list.
    $this->suggest($form, 'name', array_map(fn($name) => (string) $name, $this->commonProducts()));
    $this->suggest($form, 'unit', ['kg', 'g', 'jar', 'each']);

    return $form;
  }

  /**
   * The products offered as suggestions on the Name field.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   Product names, in the order shown.
   */
  protected function commonProducts(): array {
    return [
      $this->t('Honey'),
      $this->t('Comb honey'),
      $this->t('Beeswax'),
      $this->t('Propolis'),
      $this->t('Pollen'),
      $this->t('Royal jelly'),
      $this->t('Nucleus colony'),
      $this->t('Queen'),
    ];
  }

  /**
   * Attaches a datalist of suggestions to a single-value text widget.
   *
   * @param array $form
   *   The form, altered by reference.
   * @param string $field
   *   The field name.
   * @param string[] $suggestions
   *   The values to offer.
   */
  protected function suggest(array &$form, string $field, array $suggestions): void {
    if (!isset($form[$field]['widget'][0]['value'])) {
      return;
    }
    $id = 'hivelog-product-' . $field . '-suggestions';
    $form[$field]['widget'][0]['value']['#attributes']['list'] = $id;
    $form[$field]['widget'][0]['value']['#attributes']['autocomplete'] = 'off';
    $form[$field . '_suggestions'] = [
      '#type' => 'html_tag',
      '#tag' => 'datalist',
      '#attributes' => ['id' => $id],
      '#weight' => 1000,
    ];
    foreach ($suggestions as $delta => $suggestion) {
      $form[$field . '_suggestions'][$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'option',
        '#attributes' => ['value' => $suggestion],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Product %name has been created.', [
        '%name' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Product %name has been updated.', [
        '%name' => $entity->label(),
      ]));
    }

    // Redirect to the parent apiary — the embedded Products table there
    // is where the saved product is actually visible, matching Hive/
    // InventoryItem's redirect-to-embedding-parent choice.
    $apiary = $entity->get('apiary')->entity;
    if ($apiary) {
      $form_state->setRedirectUrl($apiary->toUrl());
    }
    else {
      $form_state->setRedirect('entity.product.collection');
    }
  }

}
