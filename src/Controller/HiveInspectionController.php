<?php

namespace Drupal\hivelog\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;

/**
 * Controller for Hive Inspection pages.
 */
class HiveInspectionController extends ControllerBase {

  /**
   * Provides the add form for an inspection within a hive context.
   */
  public function addForm(Hive $hive) {
    $inspection = $this->entityTypeManager()->getStorage('hive_inspection')->create([
      'hive' => $hive->id(),
    ]);
    return $this->entityFormBuilder()->getForm($inspection, 'add');
  }

  /**
   * Displays a hive inspection.
   */
  public function view(HiveInspection $hive_inspection) {
    return [
      'overview' => $this->buildSection($this->t('Overview'), $hive_inspection, [
        'hive',
        'inspection_date',
        'uid',
      ]),
      'external_check' => $this->buildSection($this->t('External check'), $hive_inspection, [
        'external_check',
      ]),
      'queen_status' => $this->buildSection($this->t('Queen status'), $hive_inspection, [
        'queen_seen',
        'queen_cells',
        'eggs_seen',
      ]),
      'brood_and_stores' => $this->buildSection($this->t('Brood, honey and pollen'), $hive_inspection, [
        'brood_pattern',
        'honey_stores',
        'pollen_stores',
      ]),
      'colony_condition' => $this->buildSection($this->t('Colony condition'), $hive_inspection, [
        'temperament',
        'population',
      ]),
      'health' => $this->buildSection($this->t('Varroa and disease'), $hive_inspection, [
        'varroa_check',
        'varroa_count',
        'disease_signs',
      ]),
      'management' => $this->buildSection($this->t('Management'), $hive_inspection, [
        'weight',
        'fed',
        'feed_type',
        'supers',
        'action_taken',
      ]),
      'notes' => $this->buildSection($this->t('Notes'), $hive_inspection, [
        'notes',
      ]),
    ];
  }

  /**
   * Title callback for the inspection view page.
   */
  public function title(HiveInspection $hive_inspection) {
    return $hive_inspection->label();
  }

  /**
   * Builds a consistently formatted inspection section.
   */
  protected function buildSection($title, HiveInspection $hive_inspection, array $fields): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['hivelog-inspection-section'],
      ],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $title,
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Field'),
          $this->t('Value'),
        ],
        '#rows' => $this->buildRows($hive_inspection, $fields),
        '#attributes' => [
          'class' => ['hivelog-inspection-table'],
        ],
      ],
    ];
  }

  /**
   * Builds rows for a section table.
   */
  protected function buildRows(HiveInspection $hive_inspection, array $fields): array {
    $rows = [];

    foreach ($fields as $field_name) {
      $rows[] = [
        [
          'data' => [
            '#plain_text' => (string) $hive_inspection->get($field_name)->getFieldDefinition()->getLabel(),
          ],
        ],
        [
          'data' => $this->buildFieldValue($hive_inspection, $field_name),
        ],
      ];
    }

    return $rows;
  }

  /**
   * Builds the display value for a single inspection field.
   */
  protected function buildFieldValue(HiveInspection $hive_inspection, string $field_name): array {
    $field = $hive_inspection->get($field_name);

    if ($field->isEmpty()) {
      return [
        '#plain_text' => (string) $this->t('—'),
      ];
    }

    switch ($field_name) {
      case 'hive':
      case 'uid':
        return $field->entity ? $field->entity->toLink()->toRenderable() : [
          '#plain_text' => (string) $this->t('—'),
        ];

      case 'inspection_date':
        $timestamp = strtotime($field->value . ' 00:00:00 UTC');
        return [
          '#plain_text' => $timestamp !== FALSE ? \Drupal::service('date.formatter')->format($timestamp, 'custom', 'Y-m-d') : (string) $field->value,
        ];

      case 'weight':
        return [
          '#plain_text' => $field->value . ' kg',
        ];

      case 'queen_seen':
      case 'queen_cells':
      case 'eggs_seen':
      case 'varroa_check':
      case 'fed':
        return [
          '#plain_text' => $field->value ? (string) $this->t('Yes') : (string) $this->t('No'),
        ];

      case 'brood_pattern':
      case 'honey_stores':
      case 'pollen_stores':
      case 'temperament':
      case 'population':
      case 'disease_signs':
        $allowed_values = $field->getSetting('allowed_values');
        return [
          '#plain_text' => (string) ($allowed_values[$field->value] ?? $field->value),
        ];

      case 'external_check':
      case 'action_taken':
      case 'notes':
        return [
          '#markup' => nl2br(Html::escape((string) $field->value)),
        ];

      default:
        return [
          '#plain_text' => (string) $field->value,
        ];
    }
  }

}
