<?php

namespace Drupal\hivelog\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\Queen;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Queen pages.
 */
class QueenController extends ControllerBase {

  /**
   * Default number of observations shown per page in the embedded list.
   */
  public const OBSERVATIONS_PER_PAGE = 20;

  /**
   * Pager element id for the embedded observation table.
   */
  protected const OBSERVATIONS_PAGER_ELEMENT = 0;

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder,
    DateFormatterInterface $date_formatter,
    RendererInterface $renderer,
  ) {
    // $entityTypeManager and $entityFormBuilder are untyped properties
    // inherited from ControllerBase; assign them rather than redeclaring
    // them with types.
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->dateFormatter = $date_formatter;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('date.formatter'),
      $container->get('renderer'),
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

    // Observations list: rendered at the end of the page in the same style
    // as the inspections table on the hive page.
    $build['observations_heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading']],
      '#weight' => 20,
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Observations'),
        '#attributes' => ['class' => ['hivelog-list-heading__title']],
      ],
      'add' => [
        '#type' => 'component',
        '#component' => 'hivelog:button',
        '#props' => [
          'label' => (string) $this->t('Add Observation'),
          'url' => Url::fromRoute('hivelog.queen_observation.add', ['queen' => $queen->id()])->toString(),
          'variant' => 'primary',
          'extra_classes' => 'hivelog-list-heading__action',
        ],
      ],
    ];

    $query = $this->entityTypeManager
      ->getStorage('queen_observation')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('queen', $queen->id())
      ->sort('observation_date', 'DESC')
      ->pager(static::OBSERVATIONS_PER_PAGE, static::OBSERVATIONS_PAGER_ELEMENT);
    $observation_ids = $query->execute();

    $observations = $observation_ids
      ? $this->entityTypeManager->getStorage('queen_observation')->loadMultiple($observation_ids)
      : [];

    $header = [
      $this->t('Date'),
      $this->t('Health'),
      $this->t('Temperament'),
      $this->t('Active'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($observations as $observation) {
      $health = $observation->get('health')->value;
      $temperament = $observation->get('temperament')->value;
      $actions = [
        '#type' => 'component',
        '#component' => 'hivelog:button-group',
        '#props' => [
          'buttons' => [
            ['label' => (string) $this->t('View'), 'url' => $observation->toUrl('canonical')->toString()],
            ['label' => (string) $this->t('Edit'), 'url' => $observation->toUrl('edit-form')->toString()],
            ['label' => (string) $this->t('Delete'), 'url' => $observation->toUrl('delete-form')->toString(), 'variant' => 'danger'],
          ],
        ],
      ];
      $rows[] = [
        'cells' => [
          $observation->toLink($observation->get('observation_date')->value ?: $this->t('N/A'))->toString(),
          $health ? ($observation->get('health')->getSetting('allowed_values')[$health] ?? $health) : '',
          $temperament ? ($observation->get('temperament')->getSetting('allowed_values')[$temperament] ?? $temperament) : '',
          (string) ($observation->get('active')->value ? $this->t('Yes') : $this->t('No')),
          $this->renderer->renderInIsolation($actions),
        ],
      ];
    }

    $build['observations_table'] = [
      '#type' => 'component',
      '#component' => 'hivelog:entity-table',
      '#props' => [
        'headers' => array_map('strval', $header),
        'rows' => $rows,
        'empty_message' => (string) $this->t('No observations have been recorded for this queen yet.'),
      ],
      '#weight' => 21,
    ];

    $build['observations_pager'] = [
      '#type' => 'pager',
      '#element' => static::OBSERVATIONS_PAGER_ELEMENT,
      '#weight' => 22,
    ];

    // Explicit cache metadata.
    // - url.query_args: pager state travels in the query string.
    // - user.permissions: action button visibility depends on the current
    //   user's update/delete access on the queen and observations.
    // - Queen's own cache tags: invalidate on any update/delete.
    // - Observation list cache tag + each rendered observation's tags:
    //   invalidate on any observation change.
    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['url.query_args', 'user.permissions'])
      ->addCacheableDependency($queen)
      ->addCacheTags($this->entityTypeManager->getDefinition('queen_observation')->getListCacheTags());
    foreach ($observations as $observation) {
      $cache->addCacheableDependency($observation);
    }
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
    $buttons = [];
    if ($queen->access('update')) {
      $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $queen->toUrl('edit-form')->toString(), 'variant' => 'primary'];
    }
    if ($queen->access('delete')) {
      $buttons[] = ['label' => (string) $this->t('Delete'), 'url' => $queen->toUrl('delete-form')->toString(), 'variant' => 'danger'];
    }
    if (empty($buttons)) {
      return [];
    }
    return [
      '#type' => 'component',
      '#component' => 'hivelog:button-group',
      '#props' => ['buttons' => $buttons],
      '#weight' => -10,
    ];
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
        '#attached' => ['library' => ['hivelog/tables']],
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
