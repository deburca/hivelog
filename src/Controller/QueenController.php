<?php

namespace Drupal\hivelog\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\HivelogDetailPageTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Queen pages.
 */
class QueenController extends ControllerBase {

  use HivelogDetailPageTrait;

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

  /**
   * The time service (for "n months in hive").
   */
  protected TimeInterface $time;

  /**
   * Constructs a QueenController.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder,
    DateFormatterInterface $date_formatter,
    RendererInterface $renderer,
    TimeInterface $time,
  ) {
    // $entityTypeManager and $entityFormBuilder are untyped properties
    // inherited from ControllerBase; assign them rather than redeclaring
    // them with types.
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->dateFormatter = $date_formatter;
    $this->renderer = $renderer;
    $this->time = $time;
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
      $container->get('datetime.time'),
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

    // The queen's short facts are one strip of stat tiles (task 0258); only
    // origin and notes, which are free text, stay as a table. The queen's name
    // is the page title, so no tile repeats it.
    $marking = implode(' · ', array_filter([
      $queen->get('queen_year')->getString(),
      $queen->get('queen_colour')->getString() !== '' ? $queen->getColourName() : '',
    ]));
    $cost = $queen->get('purchase_cost')->isEmpty()
      ? ''
      : number_format((float) $queen->get('purchase_cost')->getString(), 2);
    $facts = $this->buildTileStrip($queen, [
      'status' => [],
      'hive' => $queen->get('hive')->isEmpty() ? ['value' => (string) $this->t('Unassigned')] : [],
      'marking' => ['label' => $this->t('Year · Colour'), 'value' => $marking, 'omit_empty' => TRUE],
      'breed' => ['omit_empty' => TRUE],
      'temperament' => ['omit_empty' => TRUE],
      'introduction_date' => [
        'label' => $this->t('Introduced'),
        'sublabel' => $this->timeInHive($queen),
        'omit_empty' => TRUE,
      ],
      'purchase_cost' => [
        'label' => $this->t('Purchase cost'),
        'value' => $cost,
        'sublabel' => $queen->get('purchase_date')->isEmpty()
          ? ''
          : $this->t('Bought @date', ['@date' => $queen->get('purchase_date')->getString()]),
        'omit_empty' => TRUE,
      ],
    ]);
    if ($facts) {
      $build['facts'] = $facts;
    }

    $build['details'] = $this->buildSection($this->t('Details'), $queen, ['origin', 'notes']);
    if ($queen->get('origin')->isEmpty() && $queen->get('notes')->isEmpty()) {
      unset($build['details']);
    }

    // Observations list: rendered at the end of the page in the same style
    // as the inspections table on the hive page. 'observations' is the
    // anchor task 0141's delete-blocked page links to (ADR-0103 row #14,
    // queen → queen_observation, BLOCK).
    $build['observations_heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading'], 'id' => 'observations'],
      '#weight' => 20,
      'title' => [
        '#type' => 'html_tag',
        // H2 (task 0128) — a top-level section, sibling to buildSection()'s
        // own Overview/Identity/etc. headings.
        '#tag' => 'h2',
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
            ['label' => (string) $this->t('Edit'), 'url' => $observation->toUrl('edit-form')->toString()],
            [
              'label' => (string) $this->t('Delete'),
              'url' => $observation->toUrl('delete-form')->toString(),
              'variant' => 'danger',
            ],
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
   * How long an active queen has been in the hive, for the Introduced tile.
   *
   * Empty unless the queen is active, installed and has an introduction date
   * in the past.
   */
  protected function timeInHive(Queen $queen): string {
    if ($queen->get('status')->getString() !== 'active' || $queen->get('hive')->isEmpty()
      || $queen->get('introduction_date')->isEmpty()) {
      return '';
    }
    try {
      $introduced = new \DateTimeImmutable($queen->get('introduction_date')->getString() . ' 00:00:00 UTC');
    }
    catch (\Exception) {
      return '';
    }
    $now = (new \DateTimeImmutable('@' . $this->time->getRequestTime()));
    if ($now < $introduced) {
      return '';
    }
    $diff = $introduced->diff($now);
    $months = $diff->y * 12 + $diff->m;
    if ($months < 1) {
      return (string) $this->t('Less than a month in hive');
    }
    if ($months < 24) {
      return (string) $this->formatPlural($months, '1 month in hive', '@count months in hive');
    }
    return (string) $this->t('@count years in hive', ['@count' => intdiv($months, 12)]);
  }

  /**
   * Title callback for the queen view page.
   */
  public function title(Queen $queen) {
    return $queen->label();
  }

  /**
   * {@inheritdoc}
   */
  protected function formatFieldValue(FieldableEntityInterface $entity, string $field_name, FieldItemListInterface $field): array {
    /** @var \Drupal\hivelog\Entity\Queen $entity */
    switch ($field_name) {
      case 'hive':
      case 'uid':
        return $this->buildReference($field);

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

      case 'queen_colour':
        /** @var \Drupal\hivelog\Entity\Queen $entity */
        return ['#plain_text' => $entity->getColourName() ?: (string) $this->t('—')];

      case 'status':
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
