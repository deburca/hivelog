<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for Apiary Action Log pages.
 *
 * Mirrors HiveActionLogController minus the inspection-linking pieces
 * (there is no apiary-level equivalent of a hive inspection).
 */
class ApiaryActionLogController extends ControllerBase {

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * Constructs an ApiaryActionLogController.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder,
    RequestStack $request_stack,
    RendererInterface $renderer,
  ) {
    // $entityTypeManager / $entityFormBuilder are untyped ControllerBase
    // properties; assign them rather than redeclaring them with types.
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->requestStack = $request_stack;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('request_stack'),
      $container->get('renderer'),
    );
  }

  /**
   * Provides the add form for a log within an apiary + calendar action context.
   *
   * Pre-populates `apiary` and `calendar_action`. An optional `?status=`
   * query parameter (`done` / `ignored`) pre-fills the `status` field so
   * "Report Done" / "Report Ignored" links can jump straight into the
   * relevant state — the link itself is a safe GET navigation to this form;
   * the actual write only happens through the form's own CSRF-protected
   * POST submission, per ADR-0018. Unknown/invalid status values are
   * ignored rather than trusted.
   */
  public function addForm(Apiary $apiary, CalendarAction $calendar_action) {
    // Defensive check: a calendar action from a different apiary should
    // never be reachable via this apiary's URL, even if both entity IDs
    // happen to be individually accessible to the current user.
    if ((int) $calendar_action->get('apiary')->target_id !== (int) $apiary->id()) {
      throw new NotFoundHttpException();
    }

    $log = $this->entityTypeManager->getStorage('apiary_action_log')->create([
      'apiary' => $apiary->id(),
      'calendar_action' => $calendar_action->id(),
    ]);

    $request = $this->requestStack->getCurrentRequest();
    $status = $request?->query->get('status');
    if (is_string($status)) {
      $allowed_values = $log->get('status')->getSetting('allowed_values');
      if (isset($allowed_values[$status])) {
        $log->set('status', $status);
      }
    }

    return $this->entityFormBuilder->getForm($log, 'add');
  }

  /**
   * Displays an apiary action log with its fields grouped into readable sections.
   */
  public function view(ApiaryActionLog $apiary_action_log) {
    $build = [
      'actions' => $this->buildActions($apiary_action_log),
    ];

    $build += [
      'overview' => $this->buildSection($this->t('Overview'), $apiary_action_log, [
        'apiary',
        'calendar_action',
        'year',
        'status',
      ]),
      'details' => $this->buildSection($this->t('Details'), $apiary_action_log, [
        'week_completed',
        'notes',
        'uid',
      ]),
    ];

    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($apiary_action_log);
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Title callback for the apiary action log view page.
   */
  public function title(ApiaryActionLog $apiary_action_log) {
    return $apiary_action_log->label();
  }

  /**
   * Builds Edit and Delete action links for the apiary action log view.
   */
  protected function buildActions(ApiaryActionLog $apiary_action_log): array {
    $buttons = [];
    if ($apiary_action_log->access('update')) {
      $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $apiary_action_log->toUrl('edit-form')->toString()];
    }
    if ($apiary_action_log->access('delete')) {
      $buttons[] = [
        'label' => (string) $this->t('Delete'),
        'url' => $apiary_action_log->toUrl('delete-form')->toString(),
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
   * Builds a consistently formatted apiary action log section.
   */
  protected function buildSection($title, ApiaryActionLog $apiary_action_log, array $fields): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['hivelog-apiary-action-log-section'],
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
        '#rows' => $this->buildRows($apiary_action_log, $fields),
        '#attributes' => [
          'class' => ['hivelog-apiary-action-log-table'],
        ],
        '#attached' => ['library' => ['hivelog/tables']],
      ],
    ];
  }

  /**
   * Builds rows for a section table.
   */
  protected function buildRows(ApiaryActionLog $apiary_action_log, array $fields): array {
    $rows = [];

    foreach ($fields as $field_name) {
      $rows[] = [
        [
          'data' => [
            '#plain_text' => (string) $apiary_action_log->get($field_name)->getFieldDefinition()->getLabel(),
          ],
        ],
        [
          'data' => $this->buildFieldValue($apiary_action_log, $field_name),
        ],
      ];
    }

    return $rows;
  }

  /**
   * Builds the display value for a single apiary action log field.
   */
  protected function buildFieldValue(ApiaryActionLog $apiary_action_log, string $field_name): array {
    $field = $apiary_action_log->get($field_name);

    if ($field->isEmpty()) {
      return [
        '#plain_text' => (string) $this->t('—'),
      ];
    }

    switch ($field_name) {
      case 'apiary':
      case 'calendar_action':
      case 'uid':
        return $field->entity ? $field->entity->toLink()->toRenderable() : [
          '#plain_text' => (string) $this->t('—'),
        ];

      case 'status':
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
