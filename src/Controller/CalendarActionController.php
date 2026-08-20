<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
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
   * Provides the add form for a requirement within a calendar action context.
   */
  public function addRequirementForm(CalendarAction $calendar_action) {
    $requirement = $this->entityTypeManager->getStorage('calendar_action_item_requirement')->create([
      'calendar_action' => $calendar_action->id(),
    ]);
    return $this->entityFormBuilder->getForm($requirement, 'add');
  }

  /**
   * Provides the add form for a yield within a calendar action context.
   */
  public function addYieldForm(CalendarAction $calendar_action) {
    $yield = $this->entityTypeManager->getStorage('calendar_action_product_yield')->create([
      'calendar_action' => $calendar_action->id(),
    ]);
    return $this->entityFormBuilder->getForm($yield, 'add');
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
        'scope',
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

    [$requirements_section, $requirements] = $this->buildRequirementsSection($calendar_action);
    $build['requirements'] = $requirements_section;

    [$yield_section, $yields] = $this->buildYieldSection($calendar_action);
    $build['yields'] = $yield_section;

    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($calendar_action)
      ->addCacheTags($this->entityTypeManager->getDefinition('calendar_action_item_requirement')->getListCacheTags())
      ->addCacheTags($this->entityTypeManager->getDefinition('calendar_action_product_yield')->getListCacheTags());
    foreach ($requirements as $requirement) {
      $cache->addCacheableDependency($requirement);
    }
    foreach ($yields as $yield) {
      $cache->addCacheableDependency($yield);
    }
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
   * Builds the embedded "Required Items" section of the calendar action view.
   *
   * This is the recipe that a "done" report's inventory usage form pre-fills
   * from. Mirrors HiveController::buildInspectionsColumn()'s heading+table
   * shape, without pagination — recipes are short lists by nature.
   *
   * @param \Drupal\hivelog\Entity\CalendarAction $calendar_action
   *   The calendar action being rendered.
   *
   * @return array{0: array, 1: \Drupal\hivelog\Entity\CalendarActionItemRequirement[]}
   *   Tuple of [render array, loaded requirement entities for cache deps].
   */
  protected function buildRequirementsSection(CalendarAction $calendar_action): array {
    $requirement_ids = $this->entityTypeManager
      ->getStorage('calendar_action_item_requirement')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('calendar_action', $calendar_action->id())
      ->sort('id', 'ASC')
      ->execute();
    $requirements = $requirement_ids
      ? $this->entityTypeManager->getStorage('calendar_action_item_requirement')->loadMultiple($requirement_ids)
      : [];

    $header = [
      $this->t('Item'),
      $this->t('Quantity'),
      $this->t('Unit'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($requirements as $requirement) {
      $item = $requirement->get('item')->entity;
      $buttons = [];
      if ($requirement->access('update')) {
        $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $requirement->toUrl('edit-form')->toString()];
      }
      if ($requirement->access('delete')) {
        $buttons[] = [
          'label' => (string) $this->t('Delete'),
          'url' => $requirement->toUrl('delete-form')->toString(),
          'variant' => 'danger',
        ];
      }
      $actions = [
        '#type' => 'component',
        '#component' => 'hivelog:button-group',
        '#props' => ['buttons' => $buttons],
      ];
      $rows[] = [
        'cells' => [
          $item ? $item->toLink()->toString() : (string) $this->t('Unknown item'),
          rtrim(rtrim(number_format((float) $requirement->get('quantity')->value, 3, '.', ''), '0'), '.'),
          $item ? $item->get('unit')->value : '',
          $this->renderer->renderInIsolation($actions),
        ],
      ];
    }

    $section = [
      '#type' => 'container',
      'heading' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-list-heading']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Required Items'),
          '#attributes' => ['class' => ['hivelog-list-heading__title']],
        ],
        'add' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Add Required Item'),
            'url' => Url::fromRoute('hivelog.calendar_action_item_requirement.add', ['calendar_action' => $calendar_action->id()])->toString(),
            'variant' => 'primary',
            'extra_classes' => 'hivelog-list-heading__action',
          ],
        ],
      ],
      'table' => [
        '#type' => 'component',
        '#component' => 'hivelog:entity-table',
        '#props' => [
          'headers' => array_map('strval', $header),
          'rows' => $rows,
          'empty_message' => (string) $this->t('No required items have been recorded for this calendar action yet.'),
        ],
      ],
    ];

    return [$section, $requirements];
  }

  /**
   * Builds the embedded "Expected Yield" section of the calendar action view.
   *
   * This is the recipe that a "done" report's yield form pre-fills from.
   * Placed alongside (not replacing) buildRequirementsSection() — a
   * calendar action can need items (jars) and yield products (honey) at
   * once. Mirrors buildRequirementsSection() exactly, one level removed
   * (outputs instead of inputs).
   *
   * @param \Drupal\hivelog\Entity\CalendarAction $calendar_action
   *   The calendar action being rendered.
   *
   * @return array{0: array, 1: \Drupal\hivelog\Entity\CalendarActionProductYield[]}
   *   Tuple of [render array, loaded yield entities for cache deps].
   */
  protected function buildYieldSection(CalendarAction $calendar_action): array {
    $yield_ids = $this->entityTypeManager
      ->getStorage('calendar_action_product_yield')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('calendar_action', $calendar_action->id())
      ->sort('id', 'ASC')
      ->execute();
    $yields = $yield_ids
      ? $this->entityTypeManager->getStorage('calendar_action_product_yield')->loadMultiple($yield_ids)
      : [];

    $header = [
      $this->t('Product'),
      $this->t('Quantity'),
      $this->t('Unit'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($yields as $yield) {
      $product = $yield->get('product')->entity;
      $buttons = [];
      if ($yield->access('update')) {
        $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $yield->toUrl('edit-form')->toString()];
      }
      if ($yield->access('delete')) {
        $buttons[] = [
          'label' => (string) $this->t('Delete'),
          'url' => $yield->toUrl('delete-form')->toString(),
          'variant' => 'danger',
        ];
      }
      $actions = [
        '#type' => 'component',
        '#component' => 'hivelog:button-group',
        '#props' => ['buttons' => $buttons],
      ];
      $rows[] = [
        'cells' => [
          $product ? $product->toLink()->toString() : (string) $this->t('Unknown product'),
          rtrim(rtrim(number_format((float) $yield->get('quantity')->value, 3, '.', ''), '0'), '.'),
          $product ? $product->get('unit')->value : '',
          $this->renderer->renderInIsolation($actions),
        ],
      ];
    }

    $section = [
      '#type' => 'container',
      'heading' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-list-heading']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Expected Yield'),
          '#attributes' => ['class' => ['hivelog-list-heading__title']],
        ],
        'add' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Add Expected Yield'),
            'url' => Url::fromRoute('hivelog.calendar_action_product_yield.add', ['calendar_action' => $calendar_action->id()])->toString(),
            'variant' => 'primary',
            'extra_classes' => 'hivelog-list-heading__action',
          ],
        ],
      ],
      'table' => [
        '#type' => 'component',
        '#component' => 'hivelog:entity-table',
        '#props' => [
          'headers' => array_map('strval', $header),
          'rows' => $rows,
          'empty_message' => (string) $this->t('No expected yield has been recorded for this calendar action yet.'),
        ],
      ],
    ];

    return [$section, $yields];
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
      case 'scope':
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
