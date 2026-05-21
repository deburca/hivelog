<?php

namespace Drupal\hivelog\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Entity\QueenObservation;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Queen Observation pages.
 */
class QueenObservationController extends ControllerBase {

  /**
   * The file URL generator.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder,
    FileUrlGeneratorInterface $file_url_generator,
    DateFormatterInterface $date_formatter,
  ) {
    // $entityTypeManager / $entityFormBuilder are untyped ControllerBase
    // properties; assign them rather than redeclaring them with types.
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->fileUrlGenerator = $file_url_generator;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('file_url_generator'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Provides the add form for an observation within a queen context.
   */
  public function addForm(Queen $queen) {
    $observation = $this->entityTypeManager->getStorage('queen_observation')->create([
      'queen' => $queen->id(),
    ]);
    return $this->entityFormBuilder->getForm($observation, 'add');
  }

  /**
   * Displays a queen observation.
   */
  public function view(QueenObservation $queen_observation) {
    $build = [
      'actions' => $this->buildActions($queen_observation),
    ];

    $build += [
      'overview' => $this->buildSection($this->t('Overview'), $queen_observation, [
        'queen',
        'observation_date',
        'uid',
      ]),
      'observations' => $this->buildSection($this->t('Observations'), $queen_observation, [
        'health',
        'temperament',
        'active',
      ]),
      'notes' => $this->buildSection($this->t('Notes'), $queen_observation, [
        'notes',
      ]),
    ];

    // Photos grid (displayed after notes when images are present).
    $photos = $this->buildPhotosGrid($queen_observation);
    if (!empty($photos)) {
      $build['photos'] = $photos;
    }

    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($queen_observation);
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Title callback for the observation view page.
   */
  public function title(QueenObservation $queen_observation) {
    return $queen_observation->label();
  }

  /**
   * Builds Edit and Delete action links for the observation view.
   */
  protected function buildActions(QueenObservation $queen_observation): array {
    $links = [];

    if ($queen_observation->access('update')) {
      $links['edit'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit'),
        '#url' => $queen_observation->toUrl('edit-form'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
    }

    if ($queen_observation->access('delete')) {
      $links['delete'] = [
        '#type' => 'link',
        '#title' => $this->t('Delete'),
        '#url' => $queen_observation->toUrl('delete-form'),
        '#attributes' => ['class' => ['button', 'button--danger']],
      ];
    }

    if (empty($links)) {
      return [];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-queen-observation-actions']],
      '#weight' => -10,
    ] + $links;
  }

  /**
   * Builds a grid of observation photos with links to the full-size image.
   */
  protected function buildPhotosGrid(QueenObservation $queen_observation): array {
    if ($queen_observation->get('images')->isEmpty()) {
      return [];
    }

    $image_style = $this->entityTypeManager
      ->getStorage('image_style')
      ->load('thumbnail');

    $items = [];
    foreach ($queen_observation->get('images') as $delta => $item) {
      /** @var \Drupal\file\FileInterface|null $file */
      $file = $item->entity;
      if (!$file) {
        continue;
      }

      $full_url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
      $thumb_url = $image_style ? $image_style->buildUrl($file->getFileUri()) : $full_url;
      $alt = (string) ($item->alt ?? '');

      $items[] = [
        'full_url' => $full_url,
        'thumb_url' => $thumb_url,
        'alt' => $alt,
      ];
    }

    if (empty($items)) {
      return [];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['hivelog-queen-observation-section', 'hivelog-queen-observation-photos'],
      ],
      '#attached' => [
        'library' => ['hivelog/images'],
      ],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Photos'),
      ],
      'grid' => [
        '#type' => 'inline_template',
        '#template' => '<div class="hivelog-photos-grid">{% for item in items %}<a class="hivelog-photos-grid__item" href="{{ item.full_url }}" target="_blank" rel="noopener"><img src="{{ item.thumb_url }}" alt="{{ item.alt }}" loading="lazy" /></a>{% endfor %}</div>',
        '#context' => [
          'items' => $items,
        ],
      ],
    ];
  }

  /**
   * Builds a consistently formatted observation section.
   */
  protected function buildSection($title, QueenObservation $queen_observation, array $fields): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['hivelog-queen-observation-section'],
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
        '#rows' => $this->buildRows($queen_observation, $fields),
        '#attributes' => [
          'class' => ['hivelog-queen-observation-table'],
        ],
        '#attached' => ['library' => ['hivelog/tables']],
      ],
    ];
  }

  /**
   * Builds rows for a section table.
   */
  protected function buildRows(QueenObservation $queen_observation, array $fields): array {
    $rows = [];

    foreach ($fields as $field_name) {
      $rows[] = [
        [
          'data' => [
            '#plain_text' => (string) $queen_observation->get($field_name)->getFieldDefinition()->getLabel(),
          ],
        ],
        [
          'data' => $this->buildFieldValue($queen_observation, $field_name),
        ],
      ];
    }

    return $rows;
  }

  /**
   * Builds the display value for a single observation field.
   */
  protected function buildFieldValue(QueenObservation $queen_observation, string $field_name): array {
    $field = $queen_observation->get($field_name);

    if ($field->isEmpty()) {
      return [
        '#plain_text' => (string) $this->t('—'),
      ];
    }

    switch ($field_name) {
      case 'queen':
      case 'uid':
        return $field->entity ? $field->entity->toLink()->toRenderable() : [
          '#plain_text' => (string) $this->t('—'),
        ];

      case 'observation_date':
        $timestamp = strtotime($field->value . ' 00:00:00 UTC');
        return [
          '#plain_text' => $timestamp !== FALSE
            ? $this->dateFormatter->format($timestamp, 'custom', 'Y-m-d')
            : (string) $field->value,
        ];

      case 'active':
        return [
          '#plain_text' => $field->value ? (string) $this->t('Yes') : (string) $this->t('No'),
        ];

      case 'health':
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
