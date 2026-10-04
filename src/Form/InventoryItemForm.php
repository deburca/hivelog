<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Inventory Item add/edit forms.
 */
class InventoryItemForm extends ContentEntityForm {

  use HivelogEntityFormTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    $form['inventory_item_sections'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Inventory item details'),
      '#weight' => 10,
    ];

    $sections = [
      'inventory_item_overview' => [
        'title' => $this->t('Overview'),
        'weight' => 0,
        'open' => TRUE,
        'fields' => ['apiary', 'name', 'category', 'unit', 'weight_kg', 'low_stock_threshold', 'status'],
      ],
      'inventory_item_depreciation' => [
        'title' => $this->t('Type & Depreciation'),
        'weight' => 1,
        'open' => FALSE,
        'fields' => ['item_type', 'useful_life_years', 'uid'],
      ],
    ];

    foreach ($sections as $section_key => $section) {
      $form[$section_key] = [
        '#type' => 'details',
        '#title' => $section['title'],
        '#group' => 'inventory_item_sections',
        '#weight' => $section['weight'],
        '#open' => $section['open'],
      ];
      foreach ($section['fields'] as $field_name) {
        if (isset($form[$field_name])) {
          $form[$field_name]['#group'] = $section_key;
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Inventory item %name has been created.', [
        '%name' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Inventory item %name has been updated.', [
        '%name' => $entity->label(),
      ]));
    }

    // Redirect to the item collection, not the parent apiary — unlike
    // Hive/CalendarAction, the apiary canonical page doesn't embed an
    // inventory items table, only a link out to this collection, so
    // that's where the saved item is actually visible.
    $form_state->setRedirect('entity.inventory_item.collection');
  }

}
