<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Filter form for the global Calendar Actions collection page (task 0073).
 *
 * Submits via GET so filter state is reflected in the URL query string and
 * plays nicely with Drupal's pager, mirroring HivelogFullCalendarFilterForm's
 * established pattern. The dashboard's "Open seasonal tasks" stat tile links
 * here with week_from/week_to pre-filled to the rest of the year.
 */
class HivelogCalendarActionsFilterForm extends FormBase {

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
    return 'hivelog_calendar_actions_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $request = $this->requestStack->getCurrentRequest();
    $query = $request ? $request->query : NULL;

    $form['#method'] = 'get';
    $form['#attributes']['class'][] = 'hivelog-filter-form';
    $form['#attached']['library'][] = 'hivelog/filter_form';
    $form['#cache']['contexts'][] = 'url.query_args';

    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-filter-form__filters']],
    ];

    $form['filters']['week_from'] = [
      '#type' => 'number',
      '#title' => $this->t('From week'),
      '#min' => 1,
      '#max' => 53,
      '#size' => 4,
      '#default_value' => $query ? $query->get('week_from', '') : '',
    ];

    $form['filters']['week_to'] = [
      '#type' => 'number',
      '#title' => $this->t('To week'),
      '#min' => 1,
      '#max' => 53,
      '#size' => 4,
      '#default_value' => $query ? $query->get('week_to', '') : '',
    ];

    // Deliberately named 'filter_actions' (not 'actions') and typed as a
    // plain container — see HivelogHiveFilterForm for why (Gin admin theme
    // compatibility).
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
        'url' => Url::fromRoute('entity.calendar_action.collection')->toString(),
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

}
