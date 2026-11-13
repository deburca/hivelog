<?php

namespace Drupal\hivelog\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\HivelogDetailPageTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Hive Inspection pages.
 */
class HiveInspectionController extends ControllerBase {

  use HivelogDetailPageTrait;

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

  /**
   * Constructs a HiveInspectionController.
   */
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

    // The short facts are strips of stat tiles under their existing headings
    // (task 0259); only free text stays as tables. An unrecorded optional fact
    // is left out, and a section with none disappears.
    $inspection = $hive_inspection;
    $facts = $this->buildTileStrip($inspection, [
      'hive' => [],
      'inspection_date' => ['label' => $this->t('Date')],
      'uid' => ['label' => $this->t('Inspector'), 'omit_empty' => TRUE],
    ]);
    if ($facts) {
      $build['facts'] = $facts;
    }

    // External check happens before the hive is opened, so it comes first,
    // under the field's own label (the same one the edit form shows).
    $external = $this->buildFieldProseBlock(
      $inspection->get('external_check')->getFieldDefinition()->getLabel(),
      $inspection,
      'external_check',
    );
    if ($external) {
      $build['external_check'] = $external;
    }

    $weight = $inspection->get('weight')->isEmpty() ? '' : $inspection->get('weight')->getString() . ' kg';
    $fed_with = $inspection->get('fed')->getString() === '1' ? $inspection->get('feed_type')->getString() : '';
    $sections = [
      'queen_status' => [
        'title' => $this->t('Queen status'),
        'tiles' => [
          'queen_seen' => ['omit_empty' => TRUE],
          'queen_cells' => ['omit_empty' => TRUE],
          'eggs_seen' => ['omit_empty' => TRUE],
        ],
      ],
      'brood_and_stores' => [
        'title' => $this->t('Brood, honey and pollen'),
        'tiles' => [
          'brood_pattern' => ['omit_empty' => TRUE] + $this->severity($inspection, 'brood_pattern'),
          'honey_stores' => ['omit_empty' => TRUE] + $this->severity($inspection, 'honey_stores'),
          'pollen_stores' => ['omit_empty' => TRUE],
        ],
      ],
      'colony_condition' => [
        'title' => $this->t('Colony condition'),
        'tiles' => [
          'temperament' => ['omit_empty' => TRUE],
          'population' => ['omit_empty' => TRUE] + $this->severity($inspection, 'population'),
        ],
      ],
      'health' => [
        'title' => $this->t('Varroa and disease'),
        'tiles' => [
          'varroa_check' => ['omit_empty' => TRUE],
          'varroa_count' => ['omit_empty' => TRUE],
          'disease_signs' => ['omit_empty' => TRUE] + $this->severity($inspection, 'disease_signs'),
        ],
      ],
      'management' => [
        'title' => $this->t('Management'),
        'tiles' => [
          'weight' => ['value' => $weight, 'omit_empty' => TRUE],
          'fed' => ['sublabel' => $fed_with, 'omit_empty' => TRUE],
          'supers' => ['omit_empty' => TRUE],
        ],
      ],
    ];
    foreach ($sections as $key => $section) {
      $built = $this->buildTileSection($section['title'], $inspection, $section['tiles']);
      if ($built) {
        $build[$key] = $built;
      }
    }

    // Free text goes in note blocks (task 0266), each shown only when something
    // was written: Action taken and Notes after the tiles.
    foreach (['action_taken' => $this->t('Action taken'), 'notes' => $this->t('Notes')] as $field => $title) {
      $block = $this->buildFieldProseBlock($title, $inspection, $field);
      if ($block) {
        $build[$field] = $block;
      }
    }

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
   * A tile's severity sub-line for the values that clearly call for one.
   *
   * Only where a value is plainly good or bad on its own (task 0259): low or
   * no honey stores, a poor brood pattern, a weak colony, any disease sign
   * (serious for foulbrood), and "None" for disease as an `ok` "Clear". Varroa
   * count, queen cells, temperament and pollen carry none: what counts as high
   * depends on the counting method and the season, which are not recorded.
   *
   * @return array
   *   `sublabel` and `variant` for the tile, or an empty array.
   */
  protected function severity(HiveInspection $inspection, string $field): array {
    $value = $inspection->get($field)->getString();
    $disease = [$this->t('Disease sign'), 'warning'];
    $serious = [$this->t('Serious disease'), 'critical'];
    $map = [
      'brood_pattern' => ['poor' => [$this->t('Poor brood'), 'warning']],
      'honey_stores' => [
        'low' => [$this->t('Low stores'), 'warning'],
        'none' => [$this->t('No stores'), 'critical'],
      ],
      'population' => ['weak' => [$this->t('Weak colony'), 'warning']],
      'disease_signs' => [
        'none' => [$this->t('Clear'), 'ok'],
        'afb' => $serious,
        'efb' => $serious,
        'nosema' => $disease,
        'chalkbrood' => $disease,
        'sacbrood' => $disease,
        'other' => $disease,
      ],
    ];
    if (!isset($map[$field][$value])) {
      return [];
    }
    [$text, $variant] = $map[$field][$value];
    return ['sublabel' => $text, 'variant' => $variant];
  }

  /**
   * Title callback for the inspection view page.
   */
  public function title(HiveInspection $hive_inspection) {
    return $hive_inspection->label();
  }

  /**
   * {@inheritdoc}
   */
  protected function formatFieldValue(FieldableEntityInterface $entity, string $field_name, FieldItemListInterface $field): array {
    /** @var \Drupal\hivelog\Entity\HiveInspection $entity */
    switch ($field_name) {
      case 'hive':
      case 'uid':
        return $this->buildReference($field);

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
