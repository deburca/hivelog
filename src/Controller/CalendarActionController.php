<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Utility\SimpleBulletText;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Calendar Action pages.
 */
class CalendarActionController extends ControllerBase {

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * Constructs a CalendarActionController.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder,
    RendererInterface $renderer,
  ) {
    // $entityTypeManager / $entityFormBuilder are untyped ControllerBase
    // properties; assign them rather than redeclaring them with types.
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('renderer'),
    );
  }

  /**
   * Provides the add form for a calendar action within an apiary context.
   */
  public function addForm(Apiary $apiary) {
    $calendar_action = $this->entityTypeManager->getStorage('calendar_action')->create([
      'apiary' => $apiary->id(),
    ]);
    return $this->entityFormBuilder->getForm($calendar_action, 'add');
  }

  /**
   * Displays a calendar action with its fields grouped into readable sections.
   */
  public function view(CalendarAction $calendar_action) {
    $build = [
      'actions' => $this->buildActions($calendar_action),
    ];

    $build += [
      'overview' => $this->buildSection($this->t('Overview'), $calendar_action, [
        'apiary',
        'title',
        'category',
        'enabled',
      ]),
      'schedule' => $this->buildSection($this->t('Schedule'), $calendar_action, [
        'week_start',
        'week_end',
        'recurring',
      ]),
      'description' => $this->buildSection($this->t('Description'), $calendar_action, [
        'description',
      ]),
    ];

    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($calendar_action);
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Title callback for the calendar action view page.
   */
  public function title(CalendarAction $calendar_action) {
    return $calendar_action->label();
  }

  /**
   * Builds Edit and Delete action links for the calendar action view.
   */
  protected function buildActions(CalendarAction $calendar_action): array {
    $buttons = [];
    if ($calendar_action->access('update')) {
      $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $calendar_action->toUrl('edit-form')->toString()];
    }
    if ($calendar_action->access('delete')) {
      $buttons[] = [
        'label' => (string) $this->t('Delete'),
        'url' => $calendar_action->toUrl('delete-form')->toString(),
        'variant' => 'danger',
      ];
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
   * Builds a consistently formatted calendar action section.
   */
  protected function buildSection($title, CalendarAction $calendar_action, array $fields): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['hivelog-calendar-action-section'],
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
        '#rows' => $this->buildRows($calendar_action, $fields),
        '#attributes' => [
          'class' => ['hivelog-calendar-action-table'],
        ],
        '#attached' => ['library' => ['hivelog/tables']],
      ],
    ];
  }

  /**
   * Builds rows for a section table.
   */
  protected function buildRows(CalendarAction $calendar_action, array $fields): array {
    $rows = [];

    foreach ($fields as $field_name) {
      $rows[] = [
        [
          'data' => [
            '#plain_text' => (string) $calendar_action->get($field_name)->getFieldDefinition()->getLabel(),
          ],
        ],
        [
          'data' => $this->buildFieldValue($calendar_action, $field_name),
        ],
      ];
    }

    return $rows;
  }

  /**
   * Builds the display value for a single calendar action field.
   */
  protected function buildFieldValue(CalendarAction $calendar_action, string $field_name): array {
    $field = $calendar_action->get($field_name);

    if ($field->isEmpty()) {
      return [
        '#plain_text' => (string) $this->t('—'),
      ];
    }

    switch ($field_name) {
      case 'apiary':
        return $field->entity ? $field->entity->toLink()->toRenderable() : [
          '#plain_text' => (string) $this->t('—'),
        ];

      case 'category':
        $allowed_values = $field->getSetting('allowed_values');
        return [
          '#plain_text' => (string) ($allowed_values[$field->value] ?? $field->value),
        ];

      case 'enabled':
      case 'recurring':
        return [
          '#plain_text' => $field->value ? (string) $this->t('Yes') : (string) $this->t('No'),
        ];

      case 'description':
        return [
          '#markup' => SimpleBulletText::render((string) $field->value),
        ];

      default:
        return [
          '#plain_text' => (string) $field->value,
        ];
    }
  }

}
