<?php

namespace Drupal\hivelog\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\Query\QueryInterface;
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

  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('request_stack'));
  }

  /**
   * Displays an apiary with its hives.
   */
  public function view(Apiary $apiary) {
    $build = [];

    // Render the apiary entity fields.
    $view_builder = $this->entityTypeManager()->getViewBuilder('apiary');
    $build['apiary'] = $view_builder->view($apiary);

    // Add hive heading and action link.
    $build['hives_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $this->t('Hives'),
      '#weight' => 10,
    ];

    $build['add_hive'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Hive'),
      '#url' => Url::fromRoute('hivelog.hive.add', ['apiary' => $apiary->id()]),
      '#attributes' => ['class' => ['button', 'button--primary']],
      '#weight' => 11,
    ];

    // Filter form.
    $build['hives_filter'] = $this->formBuilder()->getForm(HivelogHiveFilterForm::class, $apiary);
    $build['hives_filter']['#weight'] = 11.5;

    // Build the query with filters and pagination applied.
    $filters = $this->extractHiveFilters();
    $query = $this->entityTypeManager()
      ->getStorage('hive')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary->id())
      ->sort('name', 'ASC')
      ->pager(static::HIVES_PER_PAGE, static::HIVES_PAGER_ELEMENT);
    $this->applyHiveFilters($query, $filters);
    $hive_ids = $query->execute();

    $hives = $hive_ids
      ? $this->entityTypeManager()->getStorage('hive')->loadMultiple($hive_ids)
      : [];
    $account = $this->currentUser();
    $hives = array_filter(
      $hives,
      static fn($hive) => $hive->access('view', $account)
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
      $rows[] = [
        $hive->toLink()->toString(),
        $hive->get('bee_breed')->value ? $hive->get('bee_breed')->getSetting('allowed_values')[$hive->get('bee_breed')->value] ?? $hive->get('bee_breed')->value : '',
        $hive->get('temperament')->value ? $hive->get('temperament')->getSetting('allowed_values')[$hive->get('temperament')->value] ?? $hive->get('temperament')->value : '',
        $hive->get('status')->getSetting('allowed_values')[$hive->get('status')->value] ?? $hive->get('status')->value,
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'edit' => [
                'title' => $this->t('Edit'),
                'url' => $hive->toUrl('edit-form'),
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => $hive->toUrl('delete-form'),
              ],
            ],
          ],
        ],
      ];
    }

    $build['hives_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => !empty($filters)
        ? $this->t('No hives match the current filters.')
        : $this->t('No hives have been added to this apiary yet.'),
      '#weight' => 12,
    ];

    $build['hives_pager'] = [
      '#type' => 'pager',
      '#element' => static::HIVES_PAGER_ELEMENT,
      '#weight' => 13,
    ];

    // Cache this render array per URL query string so pager + filter state
    // produce distinct cache entries.
    $build['#cache']['contexts'][] = 'url.query_args';

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

}
