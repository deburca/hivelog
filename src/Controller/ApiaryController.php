<?php

namespace Drupal\hivelog\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Form\HivelogHiveFilterForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for Apiary pages.
 */
class ApiaryController extends ControllerBase {

  /**
   * Default number of hives shown per page in the embedded list.
   */
  public const HIVES_PER_PAGE = 20;

  /**
   * Pager element id for the embedded hive table.
   */
  protected const HIVES_PAGER_ELEMENT = 0;

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FormBuilderInterface $form_builder,
    AccountInterface $current_user,
    RequestStack $request_stack,
    RendererInterface $renderer,
  ) {
    // $entityTypeManager / $formBuilder / $currentUser are untyped properties
    // inherited from ControllerBase; assign rather than redeclare them.
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('renderer'),
    );
  }

  /**
   * Displays an apiary with its hives.
   */
  public function view(Apiary $apiary) {
    $build = [];

    // Render the apiary entity fields.
    $view_builder = $this->entityTypeManager->getViewBuilder('apiary');
    $build['apiary'] = $view_builder->view($apiary);

    // Heading row: the "Hives" title on the left, the Add Hive action on
    // the right. Placing the action here (rather than inline with the
    // filter form below) keeps it at the top-right of the list section
    // where it logically belongs.
    $build['hives_heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading']],
      '#weight' => 10,
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Hives'),
        '#attributes' => ['class' => ['hivelog-list-heading__title']],
      ],
      'add' => [
        '#type' => 'link',
        '#title' => $this->t('Add Hive'),
        '#url' => Url::fromRoute('hivelog.hive.add', ['apiary' => $apiary->id()]),
        '#attributes' => [
          'class' => ['button', 'button--primary', 'hivelog-list-heading__action'],
        ],
      ],
    ];

    // Filter form sits on its own row below the heading. The form's own
    // CSS pushes the Filter / Reset buttons to the right-hand side of the
    // filter row.
    $build['hives_filter'] = $this->formBuilder->getForm(HivelogHiveFilterForm::class, $apiary);
    $build['hives_filter']['#weight'] = 11;

    // Build the query with filters and pagination applied.
    $filters = $this->extractHiveFilters();
    $query = $this->entityTypeManager
      ->getStorage('hive')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary->id())
      ->sort('name', 'ASC')
      ->pager(static::HIVES_PER_PAGE, static::HIVES_PAGER_ELEMENT);
    $this->applyHiveFilters($query, $filters);
    $hive_ids = $query->execute();

    $hives = $hive_ids
      ? $this->entityTypeManager->getStorage('hive')->loadMultiple($hive_ids)
      : [];
    $hives = array_filter(
      $hives,
      fn($hive) => $hive->access('view', $this->currentUser)
    );

    $header = [
      $this->t('Name'),
      $this->t('Breed'),
      $this->t('Temperament'),
      $this->t('Status'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($hives as $hive) {
      $actions = [
        '#type' => 'component',
        '#component' => 'hivelog:button-group',
        '#props' => [
          'buttons' => $this->renderButtons([
            ['title' => $this->t('Edit'), 'url' => $hive->toUrl('edit-form'), 'class' => ['button']],
            ['title' => $this->t('Delete'), 'url' => $hive->toUrl('delete-form'), 'class' => ['button', 'button--danger']],
          ]),
        ],
      ];
      $rows[] = [
        'cells' => [
          $hive->toLink()->toString(),
          $hive->get('bee_breed')->value ? $hive->get('bee_breed')->getSetting('allowed_values')[$hive->get('bee_breed')->value] ?? $hive->get('bee_breed')->value : '',
          $hive->get('temperament')->value ? $hive->get('temperament')->getSetting('allowed_values')[$hive->get('temperament')->value] ?? $hive->get('temperament')->value : '',
          $hive->get('status')->getSetting('allowed_values')[$hive->get('status')->value] ?? $hive->get('status')->value,
          $this->renderer->renderInIsolation($actions),
        ],
      ];
    }

    $build['hives_table'] = [
      '#type' => 'component',
      '#component' => 'hivelog:entity-table',
      '#props' => [
        'headers' => array_map('strval', $header),
        'rows' => $rows,
        'empty_message' => (string) (!empty($filters)
          ? $this->t('No hives match the current filters.')
          : $this->t('No hives have been added to this apiary yet.')),
      ],
      '#weight' => 12,
    ];

    $build['hives_pager'] = [
      '#type' => 'pager',
      '#element' => static::HIVES_PAGER_ELEMENT,
      '#weight' => 13,
    ];

    // Explicit cache metadata.
    // - url.query_args: pager + filter state travels in the query string.
    // - user.permissions: hive rows are post-filtered by per-entity access,
    //   so two users with different permissions must not share a cache entry.
    // - Apiary entity tags: invalidate on apiary update/delete.
    // - Hive list cache tag + each rendered hive's own tags: invalidate on
    //   any hive change so the embedded table is never stale.
    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['url.query_args', 'user.permissions'])
      ->addCacheableDependency($apiary)
      ->addCacheTags($this->entityTypeManager->getDefinition('hive')->getListCacheTags());
    foreach ($hives as $hive) {
      $cache->addCacheableDependency($hive);
    }
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Title callback for the apiary view page.
   */
  public function title(Apiary $apiary) {
    return $apiary->label();
  }

  /**
   * Extracts hive filter values from the current request.
   *
   * @return array<string, string>
   *   Associative array keyed by filter name. Only non-empty values are
   *   included.
   */
  protected function extractHiveFilters(): array {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return [];
    }
    $filters = [];
    foreach (['status', 'bee_breed', 'temperament', 'name'] as $key) {
      $value = trim((string) $request->query->get($key, ''));
      if ($value !== '') {
        $filters[$key] = $value;
      }
    }
    return $filters;
  }

  /**
   * Applies hive filters to an entity query.
   */
  protected function applyHiveFilters(QueryInterface $query, array $filters): void {
    if (isset($filters['status'])) {
      $query->condition('status', $filters['status']);
    }
    if (isset($filters['bee_breed'])) {
      $query->condition('bee_breed', $filters['bee_breed']);
    }
    if (isset($filters['temperament'])) {
      $query->condition('temperament', $filters['temperament']);
    }
    if (isset($filters['name'])) {
      $query->condition('name', '%' . $this->escapeLike($filters['name']) . '%', 'LIKE');
    }
  }

  /**
   * Escapes LIKE wildcard characters for safe use inside a LIKE condition.
   */
  protected function escapeLike(string $value): string {
    return addcslashes($value, '\\%_');
  }

  /**
   * Pre-renders an array of button definitions to HTML strings.
   *
   * @param array<int, array{title: mixed, url: \Drupal\Core\Url, class: string[]}> $definitions
   *
   * @return string[]
   */
  protected function renderButtons(array $definitions): array {
    $buttons = [];
    foreach ($definitions as $def) {
      $link = [
        '#type' => 'link',
        '#title' => $def['title'],
        '#url' => $def['url'],
        '#attributes' => ['class' => $def['class']],
      ];
      $buttons[] = (string) $this->renderer->renderInIsolation($link);
    }
    return $buttons;
  }

}
