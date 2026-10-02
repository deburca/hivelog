<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Shared filter form for the Hive and Apiary Action Log collections.
 *
 * Task 0168. `HiveActionLog` and `ApiaryActionLog` have the same shape
 * (a parent, a `calendar_action`, `year`, `status`, `week_completed`), so
 * the two filter forms differ only in which parent they name — a subclass
 * supplies `ENTITY_TYPE`, `PARENT_FIELD`, `FORM_ID` and
 * `parentFilterLabel()`, nothing else.
 *
 * Submits via GET so filter state is reflected in the URL query string
 * and plays nicely with Drupal's pager. The calendar-action and parent
 * filters are deliberately "name contains" text fields, not selects: a
 * select would list every calendar action / hive / apiary in the site to
 * a user who may only be allowed to see some of them, whereas a text
 * match only ever narrows rows `HivelogListBuilder::load()` has already
 * access-filtered per row.
 */
abstract class HivelogActionLogFilterFormBase extends FormBase {

  /**
   * The log entity type ID this form filters.
   */
  protected const ENTITY_TYPE = '';

  /**
   * The log's parent reference field (`hive` or `apiary`).
   */
  protected const PARENT_FIELD = '';

  /**
   * The form ID.
   */
  protected const FORM_ID = '';

  /**
   * The query-string keys, in display order.
   */
  protected const KEYS = ['status', 'year', 'action', 'parent'];

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
    return static::FORM_ID;
  }

  /**
   * The label for the parent-name text filter.
   */
  abstract protected function parentFilterLabel(): TranslatableMarkup;

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

    $log_fields = $this->entityFieldManager->getBaseFieldDefinitions(static::ENTITY_TYPE);

    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-filter-form__filters']],
    ];

    $form['filters']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => ['' => $this->t('- Any -')] + $log_fields['status']->getSetting('allowed_values'),
      '#default_value' => $filters['status'] ?? '',
    ];

    $form['filters']['year'] = [
      '#type' => 'number',
      '#title' => $this->t('Year'),
      '#min' => 1900,
      '#max' => 2200,
      '#size' => 6,
      '#default_value' => $filters['year'] ?? '',
    ];

    $form['filters']['action'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Calendar action contains'),
      '#size' => 20,
      '#default_value' => $filters['action'] ?? '',
    ];

    $form['filters']['parent'] = [
      '#type' => 'textfield',
      '#title' => $this->parentFilterLabel(),
      '#size' => 20,
      '#default_value' => $filters['parent'] ?? '',
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
   * Extracts action log filter values from a request's query string.
   *
   * @return array<string, string>
   *   Associative array keyed by filter name. Only non-empty values are
   *   included. `year` is kept only if it is all digits — anything else
   *   (including a negative or decimal value) is ignored rather than
   *   passed to the query.
   */
  public static function extract(Request $request): array {
    $filters = [];
    foreach (static::KEYS as $key) {
      $value = trim((string) $request->query->get($key, ''));
      if ($value === '') {
        continue;
      }
      if ($key === 'year' && !ctype_digit($value)) {
        continue;
      }
      $filters[$key] = $value;
    }
    return $filters;
  }

  /**
   * Applies action log filter values (from `extract()`) to an entity query.
   */
  public static function apply(QueryInterface $query, array $filters): void {
    if (isset($filters['status'])) {
      $query->condition('status', $filters['status']);
    }
    if (isset($filters['year'])) {
      $query->condition('year', (int) $filters['year']);
    }
    if (isset($filters['action'])) {
      $query->condition('calendar_action.entity.title', '%' . static::escapeLike($filters['action']) . '%', 'LIKE');
    }
    if (isset($filters['parent'])) {
      $query->condition(static::PARENT_FIELD . '.entity.name', '%' . static::escapeLike($filters['parent']) . '%', 'LIKE');
    }
  }

  /**
   * Escapes LIKE wildcard characters for safe use inside a LIKE condition.
   */
  protected static function escapeLike(string $value): string {
    return addcslashes($value, '\\%_');
  }

}
