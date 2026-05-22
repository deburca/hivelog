<?php

namespace Drupal\hivelog\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Hive Inspection pages.
 */
class HiveInspectionController extends ControllerBase {

  /**
   * The file URL generator.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

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
    FileUrlGeneratorInterface $file_url_generator,
    DateFormatterInterface $date_formatter,
    RendererInterface $renderer,
  ) {
    // $entityTypeManager and $entityFormBuilder are untyped properties
    // inherited from ControllerBase; assign them rather than redeclaring
    // them with types.
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->fileUrlGenerator = $file_url_generator;
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
      $container->get('file_url_generator'),
      $container->get('date.formatter'),
      $container->get('renderer'),
    );
  }

  /**
   * Provides the add form for an inspection within a hive context.
   */
  public function addForm(Hive $hive) {
    $inspection = $this->entityTypeManager->getStorage('hive_inspection')->create([
      'hive' => $hive->id(),
    ]);
    return $this->entityFormBuilder->getForm($inspection, 'add');
  }

  /**
   * Displays a hive inspection.
   */
  public function view(HiveInspection $hive_inspection) {
    $build = [
      'actions' => $this->buildActions($hive_inspection),
    ];

    $build += [
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

    // Photos grid (displayed after the notes section when images are present).
    $photos = $this->buildPhotosGrid($hive_inspection);
    if (!empty($photos)) {
      $build['photos'] = $photos;
    }

    // Explicit cache metadata.
    // - user.permissions: the action buttons depend on the current user's
    //   update/delete access on the inspection.
    // - Inspection's own cache tags: invalidate on any update/delete.
    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($hive_inspection);
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Builds a grid of inspection photos with links to the full-size image.
   */
  protected function buildPhotosGrid(HiveInspection $hive_inspection): array {
    if ($hive_inspection->get('images')->isEmpty()) {
      return [];
    }

    $image_style = $this->entityTypeManager
      ->getStorage('image_style')
      ->load('thumbnail');

    $items = [];
    foreach ($hive_inspection->get('images') as $delta => $item) {
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
        'class' => ['hivelog-inspection-section', 'hivelog-inspection-photos'],
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
   * Builds Edit and Delete action links for the inspection view.
   */
  protected function buildActions(HiveInspection $hive_inspection): array {
    $buttons = [];
    if ($hive_inspection->access('update')) {
      $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $hive_inspection->toUrl('edit-form')->toString(), 'variant' => 'primary'];
    }
    if ($hive_inspection->access('delete')) {
      $buttons[] = ['label' => (string) $this->t('Delete'), 'url' => $hive_inspection->toUrl('delete-form')->toString(), 'variant' => 'danger'];
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
        '#attached' => ['library' => ['hivelog/tables']],
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
          '#plain_text' => $timestamp !== FALSE
            ? $this->dateFormatter->format($timestamp, 'custom', 'Y-m-d')
            : (string) $field->value,
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
