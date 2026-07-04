<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Hive;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Filter form for the seasonal calendar checklist on the hive view page.
 *
 * Submits via GET so filter state is reflected in the URL query string,
 * following the exact pattern established by
 * \Drupal\hivelog\Form\HivelogInspectionFilterForm. Defaults to
 * "Unreported" / the current year — the hive checklist's required default
 * view (see ADR-0025) — rather than an open-ended "- Any -" placeholder,
 * since there is always an effective status/year even when nothing is in
 * the query string.
 */
class HivelogCalendarFilterForm extends FormBase {

  /**
   * Constructs a HivelogCalendarFilterForm.
   *
   * $requestStack is an untyped property already declared on FormBase (it
   * backs FormBase::getRequest()); assign it here rather than redeclaring
   * it with a type, which PHP forbids for a property already declared by
   * a parent class. Mirrors HivelogInspectionFilterForm's constructor.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hivelog_calendar_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Hive $hive = NULL): array {
    $request = $this->requestStack->getCurrentRequest();
    $query = $request ? $request->query : NULL;
    $current_year = (int) date('Y');

    $form['#method'] = 'get';
    $form['#attributes']['class'][] = 'hivelog-filter-form';
    $form['#attached']['library'][] = 'hivelog/filter_form';
    $form['#cache']['contexts'][] = 'url.query_args';

    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-filter-form__filters']],
    ];

    $form['filters']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        'pending' => $this->t('Unreported'),
        'done' => $this->t('Done'),
        'ignored' => $this->t('Ignored'),
        'all' => $this->t('All'),
      ],
      '#default_value' => $query ? (string) $query->get('status', 'pending') : 'pending',
    ];

    $form['filters']['year'] = [
      '#type' => 'select',
      '#title' => $this->t('Year'),
      '#options' => [
        (string) ($current_year - 1) => (string) ($current_year - 1),
        (string) $current_year => (string) $current_year,
        (string) ($current_year + 1) => (string) ($current_year + 1),
      ],
      '#default_value' => $query ? (string) $query->get('year', (string) $current_year) : (string) $current_year,
    ];

    // Deliberately named 'filter_actions' (not 'actions') and typed as a
    // plain container — see HivelogInspectionFilterForm for why (Gin admin
    // theme compatibility).
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
    if ($hive) {
      $form['filter_actions']['reset'] = [
        '#type' => 'component',
        '#component' => 'hivelog:button',
        '#props' => [
          'label' => (string) $this->t('Reset'),
          'url' => Url::fromRoute('entity.hive.canonical', ['hive' => $hive->id()])->toString(),
        ],
      ];
    }

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

}
