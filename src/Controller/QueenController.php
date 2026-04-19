<?php

namespace Drupal\hivelog\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\Queen;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Queen pages.
 */
class QueenController extends ControllerBase {

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder,
    DateFormatterInterface $date_formatter,
  ) {
    // $entityTypeManager and $entityFormBuilder are untyped properties
    // inherited from ControllerBase; assign them rather than redeclaring
    // them with types.
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Provides the add form for a queen within a hive context.
   *
   * Pre-populates the queen's `hive` reference so the user does not have to
   * pick the hive manually when adding from the hive page.
   */
  public function addForm(Hive $hive) {
    $queen = $this->entityTypeManager->getStorage('queen')->create([
      'hive' => $hive->id(),
      'status' => 'active',
    ]);
    return $this->entityFormBuilder->getForm($queen, 'add');
  }

  /**
   * Displays a queen with its fields grouped into readable sections.
   *
   * Mirrors the layout used by HiveInspectionController::view so the queen
   * page looks consistent with the rest of the module rather than dumping
   * every field inline.
   */
  public function view(Queen $queen) {
    $build = [
      'actions' => $this->buildActions($queen),
    ];

    $build += [
      'overview' => $this->buildSection($this->t('Overview'), $queen, [
        'name',
        'status',
        'hive',
        'uid',
      ]),
      'identity' => $this->buildSection($this->t('Identity'), $queen, [
        'origin',
        'queen_year',
        'queen_colour',
        'breed',
        'temperament',
      ]),
      'acquisition' => $this->buildSection($this->t('Acquisition'), $queen, [
        'purchase_cost',
        'purchase_date',
        'introduction_date',
      ]),
      'notes' => $this->buildSection($this->t('Notes'), $queen, [
        'notes',
      ]),
    ];

    // Explicit cache metadata.
    // - user.permissions: action button visibility depends on the current
    //   user's update/delete access on the queen.
    // - Queen's own cache tags: invalidate on any update/delete.
    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($queen);
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Title callback for the queen view page.
   */
  public function title(Queen $queen) {
    return $queen->label();
  }

  /**
   * Builds Edit and Delete action links for the queen view.
   */
  protected function buildActions(Queen $queen): array {
    $links = [];

    if ($queen->access('update')) {
      $links['edit'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit'),
        '#url' => $queen->toUrl('edit-form'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
    }

    if ($queen->access('delete')) {
      $links['delete'] = [
        '#type' => 'link',
        '#title' => $this->t('Delete'),
        '#url' => $queen->toUrl('delete-form'),
        '#attributes' => ['class' => ['button', 'button--danger']],
      ];
    }

    if (empty($links)) {
      return [];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-queen-actions']],
      '#weight' => -10,
    ] + $links;
  }

  /**
   * Builds a consistently formatted queen section.
   */
  protected function buildSection($title, Queen $queen, array $fields): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['hivelog-queen-section'],
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
        '#rows' => $this->buildRows($queen, $fields),
        '#attributes' => [
          'class' => ['hivelog-queen-table'],
        ],
      ],
    ];
  }

  /**
   * Builds rows for a section table.
   */
  protected function buildRows(Queen $queen, array $fields): array {
    $rows = [];

    foreach ($fields as $field_name) {
      $rows[] = [
        [
          'data' => [
            '#plain_text' => (string) $queen->get($field_name)->getFieldDefinition()->getLabel(),
          ],
        ],
        [
          'data' => $this->buildFieldValue($queen, $field_name),
        ],
      ];
    }

    return $rows;
  }

  /**
   * Builds the display value for a single queen field.
   */
  protected function buildFieldValue(Queen $queen, string $field_name): array {
    $field = $queen->get($field_name);

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

      case 'purchase_date':
      case 'introduction_date':
        $timestamp = strtotime($field->value . ' 00:00:00 UTC');
        return [
          '#plain_text' => $timestamp !== FALSE
            ? $this->dateFormatter->format($timestamp, 'custom', 'Y-m-d')
            : (string) $field->value,
        ];

      case 'purchase_cost':
        return [
          '#plain_text' => number_format((float) $field->value, 2),
        ];

      case 'status':
      case 'queen_colour':
      case 'breed':
      case 'temperament':
        $allowed_values = $field->getSetting('allowed_values');
        return [
          '#plain_text' => (string) ($allowed_values[$field->value] ?? $field->value),
        ];

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
