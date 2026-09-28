<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Filter form for `/hivelog/inventory-items` (task 0156).
 *
 * Submits via GET so filter state is reflected in the URL query string
 * and plays nicely with Drupal's pager — same shape as
 * `HivelogHiveFilterForm` (task 0132), full-page-only (Inventory Items
 * has no embedded counterpart elsewhere to support).
 */
class HivelogInventoryItemFilterForm extends FormBase {

  /**
   * The entity field manager.
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  public function __construct(RequestStack $request_stack, EntityFieldManagerInterface $entity_field_manager) {
    $this->requestStack = $request_stack;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('request_stack'),
      $container->get('entity_field.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hivelog_inventory_item_filter_form';
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

    $item_fields = $this->entityFieldManager->getBaseFieldDefinitions('inventory_item');

    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-filter-form__filters']],
    ];

    $form['filters']['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#options' => ['' => $this->t('- Any -')] + $item_fields['category']->getSetting('allowed_values'),
      '#default_value' => $filters['category'] ?? '',
    ];

    $form['filters']['item_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => ['' => $this->t('- Any -')] + $item_fields['item_type']->getSetting('allowed_values'),
      '#default_value' => $filters['item_type'] ?? '',
    ];

    $form['filters']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => ['' => $this->t('- Any -')] + $item_fields['status']->getSetting('allowed_values'),
      '#default_value' => $filters['status'] ?? '',
    ];

    $form['filters']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name contains'),
      '#size' => 20,
      '#default_value' => $filters['name'] ?? '',
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
   * Extracts inventory item filter values from a request's query string.
   *
   * @return array<string, string>
   *   Associative array keyed by filter name. Only non-empty values are
   *   included.
   */
  public static function extract(Request $request): array {
    $filters = [];
    foreach (['category', 'item_type', 'status', 'name'] as $key) {
      $value = trim((string) $request->query->get($key, ''));
      if ($value !== '') {
        $filters[$key] = $value;
      }
    }
    return $filters;
  }

  /**
   * Applies inventory item filter values (from `extract()`) to an entity query.
   */
  public static function apply(QueryInterface $query, array $filters): void {
    if (isset($filters['category'])) {
      $query->condition('category', $filters['category']);
    }
    if (isset($filters['item_type'])) {
      $query->condition('item_type', $filters['item_type']);
    }
    if (isset($filters['status'])) {
      $query->condition('status', $filters['status']);
    }
    if (isset($filters['name'])) {
      $query->condition('name', '%' . static::escapeLike($filters['name']) . '%', 'LIKE');
    }
  }

  /**
   * Escapes LIKE wildcard characters for safe use inside a LIKE condition.
   */
  protected static function escapeLike(string $value): string {
    return addcslashes($value, '\\%_');
  }

}
