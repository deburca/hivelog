<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Hive;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Filter form for the inspection table on the hive page and `/hivelog/inspections`.
 *
 * Submits via GET so filter state is reflected in the URL query string and
 * plays nicely with Drupal's pager. Works with or without a parent hive
 * (task 0132) — `HiveController::view()` passes one for the embedded
 * table; `HiveInspectionListBuilder` (the full `/hivelog/inspections`
 * list) passes none, and Reset then targets the current route instead of
 * the hive page.
 *
 * `extract()` / `apply()` are static so `HiveController` and
 * `HiveInspectionListBuilder` share one implementation instead of each
 * keeping its own copy.
 */
class HivelogInspectionFilterForm extends FormBase {

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
    return 'hivelog_inspection_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Hive $hive = NULL): array {
    $request = $this->requestStack->getCurrentRequest();
    $filters = $request ? static::extract($request) : [];

    $form['#method'] = 'get';
    $form['#attributes']['class'][] = 'hivelog-filter-form';
    $form['#attached']['library'][] = 'hivelog/filter_form';
    $form['#cache']['contexts'][] = 'url.query_args';

    $inspection_fields = $this->entityFieldManager->getBaseFieldDefinitions('hive_inspection');

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

    $form['filters']['queen_seen'] = [
      '#type' => 'select',
      '#title' => $this->t('Queen seen'),
      '#options' => [
        '' => $this->t('- Any -'),
        '1' => $this->t('Yes'),
        '0' => $this->t('No'),
      ],
      '#default_value' => $filters['queen_seen'] ?? '',
    ];

    $form['filters']['brood_pattern'] = [
      '#type' => 'select',
      '#title' => $this->t('Brood pattern'),
      '#options' => ['' => $this->t('- Any -')] + $inspection_fields['brood_pattern']->getSetting('allowed_values'),
      '#default_value' => $filters['brood_pattern'] ?? '',
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
    // With a parent hive (the embedded table), Reset returns to the hive
    // page. Without one (the full /hivelog/inspections list), Reset
    // targets the current route with the query string cleared —
    // '<current>' resolves to this request's own route match, no route
    // name needed.
    $reset_url = $hive
      ? Url::fromRoute('entity.hive.canonical', ['hive' => $hive->id()])
      : Url::fromRoute('<current>');
    $form['filter_actions']['reset'] = [
      '#type' => 'component',
      '#component' => 'hivelog:button',
      '#props' => [
        'label' => (string) $this->t('Reset'),
        'url' => $reset_url->toString(),
      ],
    ];

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
   * Extracts inspection filter values from a request's query string.
   *
   * @return array<string, string>
   *   Associative array keyed by filter name. Only non-empty values are
   *   included.
   */
  public static function extract(Request $request): array {
    $filters = [];
    foreach (['date_from', 'date_to', 'queen_seen', 'brood_pattern'] as $key) {
      $value = trim((string) $request->query->get($key, ''));
      if ($value !== '') {
        $filters[$key] = $value;
      }
    }
    return $filters;
  }

  /**
   * Applies inspection filter values (from `extract()`) to an entity query.
   */
  public static function apply(QueryInterface $query, array $filters): void {
    if (isset($filters['date_from'])) {
      $query->condition('inspection_date', $filters['date_from'], '>=');
    }
    if (isset($filters['date_to'])) {
      $query->condition('inspection_date', $filters['date_to'], '<=');
    }
    if (isset($filters['queen_seen']) && in_array($filters['queen_seen'], ['0', '1'], TRUE)) {
      $query->condition('queen_seen', (int) $filters['queen_seen']);
    }
    if (isset($filters['brood_pattern'])) {
      $query->condition('brood_pattern', $filters['brood_pattern']);
    }
  }

}
