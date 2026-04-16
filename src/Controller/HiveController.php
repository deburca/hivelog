<?php

namespace Drupal\hivelog\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;

/**
 * Controller for Hive pages.
 */
class HiveController extends ControllerBase {

  /**
   * Provides the add form for a hive within an apiary context.
   */
  public function addForm(Apiary $apiary) {
    $hive = $this->entityTypeManager()->getStorage('hive')->create([
      'apiary' => $apiary->id(),
    ]);
    return $this->entityFormBuilder()->getForm($hive, 'add');
  }

  /**
   * Displays a hive with its inspections.
   */
  public function view(Hive $hive) {
    $build = [];

    // Render the hive entity fields.
    $view_builder = $this->entityTypeManager()->getViewBuilder('hive');
    $build['hive'] = $view_builder->view($hive);

    // Add inspections heading and action link.
    $build['inspections_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $this->t('Inspections'),
      '#weight' => 10,
    ];

    $build['add_inspection'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Inspection'),
      '#url' => Url::fromRoute('hivelog.inspection.add', ['hive' => $hive->id()]),
      '#attributes' => ['class' => ['button', 'button--primary']],
      '#weight' => 11,
    ];

    // Load inspections for this hive, ordered by date descending.
    $inspection_ids = $this->entityTypeManager()
      ->getStorage('hive_inspection')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('hive', $hive->id())
      ->sort('inspection_date', 'DESC')
      ->execute();

    $inspections = $this->entityTypeManager()
      ->getStorage('hive_inspection')
      ->loadMultiple($inspection_ids);

    $header = [
      $this->t('Date'),
      $this->t('Queen'),
      $this->t('Brood'),
      $this->t('Honey'),
      $this->t('Temperament'),
      $this->t('Population'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($inspections as $inspection) {
      $rows[] = [
        $inspection->get('inspection_date')->value ?: $this->t('N/A'),
        $inspection->get('queen_seen')->value ? $this->t('Yes') : $this->t('No'),
        $inspection->get('brood_pattern')->value ? $inspection->get('brood_pattern')->getSetting('allowed_values')[$inspection->get('brood_pattern')->value] ?? '' : '',
        $inspection->get('honey_stores')->value ? $inspection->get('honey_stores')->getSetting('allowed_values')[$inspection->get('honey_stores')->value] ?? '' : '',
        $inspection->get('temperament')->value ? $inspection->get('temperament')->getSetting('allowed_values')[$inspection->get('temperament')->value] ?? '' : '',
        $inspection->get('population')->value ? $inspection->get('population')->getSetting('allowed_values')[$inspection->get('population')->value] ?? '' : '',
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'view' => [
                'title' => $this->t('View'),
                'url' => $inspection->toUrl('canonical'),
              ],
              'edit' => [
                'title' => $this->t('Edit'),
                'url' => $inspection->toUrl('edit-form'),
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => $inspection->toUrl('delete-form'),
              ],
            ],
          ],
        ],
      ];
    }

    $build['inspections_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No inspections have been recorded for this hive yet.'),
      '#weight' => 12,
    ];

    return $build;
  }

  /**
   * Title callback for the hive view page.
   */
  public function title(Hive $hive) {
    return $hive->label();
  }

}
