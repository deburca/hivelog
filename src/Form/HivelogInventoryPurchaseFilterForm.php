<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Filter form for `/hivelog/inventory-purchases` (task 0156).
 *
 * Submits via GET so filter state is reflected in the URL query string
 * and plays nicely with Drupal's pager — same date-range shape as
 * `HivelogInspectionFilterForm` (task 0132), full-page-only (Inventory
 * Purchases has no embedded counterpart elsewhere to support). A
 * purchase ledger's primary axis is *when*, not a status enum, so this
 * has no `entity_field.manager` dependency — neither filter field
 * reads an `allowed_values` list.
 */
class HivelogInventoryPurchaseFilterForm extends FormBase {

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
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hivelog_inventory_purchase_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $request = $this->requestStack->getCurrentRequest();
    $filters = $request ? static::extract($request) : [];

    $form['#method'] = 'get';
    $form['#attributes']['class'][] = 'hivelog-filter-form';
    $form['#attached']['library'][] = 'hivelog/filter_form';
    $form['#cache']['contexts'][] = 'url.query_args';

    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-filter-form__filters']],
    ];

    $form['filters']['date_from'] = [
      '#type' => 'date',
      '#title' => $this->t('From'),
      '#default_value' => $filters['date_from'] ?? '',
    ];

    $form['filters']['date_to'] = [
      '#type' => 'date',
      '#title' => $this->t('To'),
      '#default_value' => $filters['date_to'] ?? '',
    ];

    $form['filters']['supplier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Supplier contains'),
      '#size' => 20,
      '#default_value' => $filters['supplier'] ?? '',
    ];

    // Deliberately named 'filter_actions' (not 'actions') and typed as a
    // plain container so admin themes such as Gin do not treat these as
    // form-level actions and relocate them into their sticky top bar
    // (top-bar__actions). Gin's form alter keys off $form['actions'] to
    // decide whether to hoist buttons; using a different key keeps the
    // Filter and Reset controls inline with the filter fields.
    // @see \Drupal\gin\GinContentFormHelper::formAlter()
    $form['filter_actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-filter-form__actions']],
    ];
    $form['filter_actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['hivelog-filter-form__submit']],
    ];
    $form['filter_actions']['reset'] = [
      '#type' => 'component',
      '#component' => 'hivelog:button',
      '#props' => [
        'label' => (string) $this->t('Reset'),
        'url' => Url::fromRoute('<current>')->toString(),
      ],
    ];

    // GET forms don't need CSRF / build id tokens; hide them so they don't
    // end up as query string noise.
    foreach (['form_build_id', 'form_token', 'form_id'] as $element) {
      if (isset($form[$element])) {
        $form[$element]['#access'] = FALSE;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Intentionally empty: the form uses GET, so submission simply reloads
    // the current URL with the filter values as query string parameters.
  }

  /**
   * Extracts inventory purchase filter values from a request's query string.
   *
   * @return array<string, string>
   *   Associative array keyed by filter name. Only non-empty values are
   *   included.
   */
  public static function extract(Request $request): array {
    $filters = [];
    foreach (['date_from', 'date_to', 'supplier'] as $key) {
      $value = trim((string) $request->query->get($key, ''));
      if ($value !== '') {
        $filters[$key] = $value;
      }
    }
    return $filters;
  }

  /**
   * Applies inventory purchase filter values (from `extract()`) to a query.
   */
  public static function apply(QueryInterface $query, array $filters): void {
    if (isset($filters['date_from'])) {
      $query->condition('purchase_date', $filters['date_from'], '>=');
    }
    if (isset($filters['date_to'])) {
      $query->condition('purchase_date', $filters['date_to'], '<=');
    }
    if (isset($filters['supplier'])) {
      $query->condition('supplier', '%' . static::escapeLike($filters['supplier']) . '%', 'LIKE');
    }
  }

  /**
   * Escapes LIKE wildcard characters for safe use inside a LIKE condition.
   */
  protected static function escapeLike(string $value): string {
    return addcslashes($value, '\\%_');
  }

}
