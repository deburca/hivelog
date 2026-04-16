<?php

namespace Drupal\hivelog\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;

/**
 * Controller for Apiary pages.
 */
class ApiaryController extends ControllerBase {

  /**
   * Displays an apiary with its hives.
   */
  public function view(Apiary $apiary) {
    $build = [];

    // Render the apiary entity fields.
    $view_builder = $this->entityTypeManager()->getViewBuilder('apiary');
    $build['apiary'] = $view_builder->view($apiary);

    // Add hive heading and action link.
    $build['hives_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $this->t('Hives'),
      '#weight' => 10,
    ];

    $build['add_hive'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Hive'),
      '#url' => Url::fromRoute('hivelog.hive.add', ['apiary' => $apiary->id()]),
      '#attributes' => ['class' => ['button', 'button--primary']],
      '#weight' => 11,
    ];

    // Load hives for this apiary.
    $hives = $this->entityTypeManager()
      ->getStorage('hive')
      ->loadByProperties(['apiary' => $apiary->id()]);

    $header = [
      $this->t('Name'),
      $this->t('Breed'),
      $this->t('Temperament'),
      $this->t('Status'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($hives as $hive) {
      $rows[] = [
        $hive->toLink()->toString(),
        $hive->get('bee_breed')->value ? $hive->get('bee_breed')->getSetting('allowed_values')[$hive->get('bee_breed')->value] ?? $hive->get('bee_breed')->value : '',
        $hive->get('temperament')->value ? $hive->get('temperament')->getSetting('allowed_values')[$hive->get('temperament')->value] ?? $hive->get('temperament')->value : '',
        $hive->get('status')->getSetting('allowed_values')[$hive->get('status')->value] ?? $hive->get('status')->value,
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'edit' => [
                'title' => $this->t('Edit'),
                'url' => $hive->toUrl('edit-form'),
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => $hive->toUrl('delete-form'),
              ],
            ],
          ],
        ],
      ];
    }

    $build['hives_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No hives have been added to this apiary yet.'),
      '#weight' => 12,
    ];

    return $build;
  }

  /**
   * Title callback for the apiary view page.
   */
  public function title(Apiary $apiary) {
    return $apiary->label();
  }

}
