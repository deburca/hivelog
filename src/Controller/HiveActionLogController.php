<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\HivelogDetailPageTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for Hive Action Log pages.
 */
class HiveActionLogController extends ControllerBase {

  use HivelogDetailPageTrait;

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * Constructs a HiveActionLogController.
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
   * Provides the add form for a log within a hive + calendar action context.
   *
   * Pre-populates `hive` and `calendar_action`. An optional `?status=` query
   * parameter (`done` / `ignored`) pre-fills the `status` field so
   * the "Done" / "Ignored" report links can jump straight into the
   * relevant state — the link itself is a safe GET navigation to this form;
   * the actual write only happens through the form's own CSRF-protected
   * POST submission, per ADR-0018. Unknown/invalid status values are
   * ignored rather than trusted.
   */
  public function addForm(Hive $hive, CalendarAction $calendar_action) {
    // Defensive check: a calendar action from a different apiary should
    // never be reachable via this hive's URL, even if both entity IDs
    // happen to be individually accessible to the current user.
    if ((int) $calendar_action->get('apiary')->target_id !== (int) $hive->get('apiary')->target_id) {
      throw new NotFoundHttpException();
    }

    $log = $this->entityTypeManager->getStorage('hive_action_log')->create([
      'hive' => $hive->id(),
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
   * Displays a hive action log with its fields grouped into readable sections.
   */
  public function view(HiveActionLog $hive_action_log) {
    $build = [
      'actions' => $this->buildActions($hive_action_log),
    ];

    $build += [
      'overview' => $this->buildSection($this->t('Overview'), $hive_action_log, [
        'hive',
        'calendar_action',
        'year',
        'status',
      ]),
      'details' => $this->buildSection($this->t('Details'), $hive_action_log, [
        'week_completed',
        'notes',
        'inspection',
        'uid',
      ]),
    ];

    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($hive_action_log);
    $linked_inspection = $hive_action_log->get('inspection')->entity;
    if ($linked_inspection) {
      $cache->addCacheableDependency($linked_inspection);
    }
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Title callback for the hive action log view page.
   */
  public function title(HiveActionLog $hive_action_log) {
    return $hive_action_log->label();
  }

  /**
   * {@inheritdoc}
   */
  protected function formatFieldValue(FieldableEntityInterface $entity, string $field_name, FieldItemListInterface $field): array {
    /** @var \Drupal\hivelog\Entity\HiveActionLog $entity */
    switch ($field_name) {
      case 'hive':
      case 'calendar_action':
      case 'inspection':
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
