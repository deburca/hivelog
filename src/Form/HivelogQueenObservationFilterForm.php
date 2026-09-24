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
 * Filter form for the Queen Observations table on the hive page and `/hivelog/queen-observations`.
 *
 * Submits via GET so filter state is reflected in the URL query string and
 * plays nicely with Drupal's pager. Filter keys are prefixed `obs_` because
 * on the hive page this form shares a page (and a query string) with
 * HivelogInspectionFilterForm — unprefixed names like `date_from` would
 * collide with that form's own filters; the prefix is kept on the full
 * `/hivelog/queen-observations` list too so a filtered URL means the same
 * thing on both pages (task 0132).
 *
 * Works with or without a parent hive — `HiveController::view()` passes
 * one for the embedded table; `QueenObservationListBuilder` (the full
 * list) passes none, and both the Reset target and the hive-scoped
 * `obs_queen` option list (only ever meaningful for one specific hive's
 * queens) adapt accordingly.
 *
 * `extract()` / `apply()` are static so `HiveController` and
 * `QueenObservationListBuilder` share one implementation instead of each
 * keeping its own copy.
 */
class HivelogQueenObservationFilterForm extends FormBase {

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
    return 'hivelog_queen_observation_filter_form';
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

    $observation_fields = $this->entityFieldManager->getBaseFieldDefinitions('queen_observation');

    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-filter-form__filters']],
    ];

    $form['filters']['obs_date_from'] = [
      '#type' => 'date',
      '#title' => $this->t('From'),
      '#default_value' => $filters['date_from'] ?? '',
    ];

    $form['filters']['obs_date_to'] = [
      '#type' => 'date',
      '#title' => $this->t('To'),
      '#default_value' => $filters['date_to'] ?? '',
    ];

    $form['filters']['obs_health'] = [
      '#type' => 'select',
      '#title' => $this->t('Health'),
      '#options' => ['' => $this->t('- Any -')] + $observation_fields['health']->getSetting('allowed_values'),
      '#default_value' => $filters['health'] ?? '',
    ];

    $form['filters']['obs_temperament'] = [
      '#type' => 'select',
      '#title' => $this->t('Temperament'),
      '#options' => ['' => $this->t('- Any -')] + $observation_fields['temperament']->getSetting('allowed_values'),
      '#default_value' => $filters['temperament'] ?? '',
    ];

    $form['filters']['obs_active'] = [
      '#type' => 'select',
      '#title' => $this->t('Active'),
      '#options' => [
        '' => $this->t('- Any -'),
        '1' => $this->t('Yes'),
        '0' => $this->t('No'),
      ],
      '#default_value' => $filters['active'] ?? '',
    ];

    if ($hive) {
      $queens = $hive->getQueens();
      if (count($queens) > 1) {
        // Only worth offering once a hive has had more than one queen —
        // otherwise every observation is already for the same queen. Only
        // possible at all with a parent hive: on the full cross-hive list
        // there is no single hive's queens to choose from.
        $queen_options = ['' => $this->t('- Any -')];
        foreach ($queens as $queen) {
          $queen_options[$queen->id()] = $queen->label();
        }
        $form['filters']['obs_queen'] = [
          '#type' => 'select',
          '#title' => $this->t('Queen'),
          '#options' => $queen_options,
          '#default_value' => $filters['queen'] ?? '',
        ];
      }
    }

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
    // page. Without one (the full /hivelog/queen-observations list),
    // Reset targets the current route with the query string cleared —
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
   * Extracts observation filter values from a request's query string.
   *
   * Keys are prefixed `obs_` in the query string (see class docblock);
   * the returned array has the prefix stripped.
   *
   * @return array<string, string>
   *   Associative array keyed by filter name (no `obs_` prefix). Only
   *   non-empty values are included.
   */
  public static function extract(Request $request): array {
    $filters = [];
    foreach (['date_from', 'date_to', 'health', 'temperament', 'active', 'queen'] as $key) {
      $value = trim((string) $request->query->get('obs_' . $key, ''));
      if ($value !== '') {
        $filters[$key] = $value;
      }
    }
    return $filters;
  }

  /**
   * Applies observation filter values (from `extract()`) to an entity query.
   */
  public static function apply(QueryInterface $query, array $filters): void {
    if (isset($filters['date_from'])) {
      $query->condition('observation_date', $filters['date_from'], '>=');
    }
    if (isset($filters['date_to'])) {
      $query->condition('observation_date', $filters['date_to'], '<=');
    }
    if (isset($filters['health'])) {
      $query->condition('health', $filters['health']);
    }
    if (isset($filters['temperament'])) {
      $query->condition('temperament', $filters['temperament']);
    }
    if (isset($filters['active']) && in_array($filters['active'], ['0', '1'], TRUE)) {
      $query->condition('active', (int) $filters['active']);
    }
    if (isset($filters['queen'])) {
      $query->condition('queen', $filters['queen']);
    }
  }

}
