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
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Entity\QueenObservation;
use Drupal\hivelog\HivelogDetailPageTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Queen Observation pages.
 */
class QueenObservationController extends ControllerBase {

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
   * Constructs a QueenObservationController.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder,
    FileUrlGeneratorInterface $file_url_generator,
    DateFormatterInterface $date_formatter,
    RendererInterface $renderer,
  ) {
    // $entityTypeManager / $entityFormBuilder are untyped ControllerBase
    // properties; assign them rather than redeclaring them with types.
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
   * {@inheritdoc}
   */
  protected function formatFieldValue(FieldableEntityInterface $entity, string $field_name, FieldItemListInterface $field): array {
    /** @var \Drupal\hivelog\Entity\QueenObservation $entity */
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
