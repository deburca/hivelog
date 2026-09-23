<?php

declare(strict_types=1);

namespace Drupal\nanoprobe\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\nanoprobe\Entity\SensorReading;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Filter form for the full-history sensor readings page (task 0110).
 *
 * Submits via GET, mirroring `HivelogInspectionFilterForm`'s own
 * established shape exactly — filter state lives in the URL query
 * string, not session state.
 */
class SensorReadingFilterForm extends FormBase {

  /**
   * Constructs a new SensorReadingFilterForm.
   *
   * `$requestStack` is an untyped property already declared on
   * `FormBase` — assigned here rather than redeclared with a type via
   * constructor promotion, which PHP forbids (mirrors
   * `HivelogInspectionFilterForm`'s own exact pattern).
   */
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
    return 'nanoprobe_sensor_reading_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SensorDevice $sensor_device = NULL): array {
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

    $metric_options = ['' => $this->t('- All metrics -')];
    if ($sensor_device) {
      foreach ($sensor_device->getConfigMetrics() as $metric) {
        $metric_options[$metric] = SensorReading::METRIC_TYPES[$metric] ?? $metric;
      }
    }
    $form['filters']['metric'] = [
      '#type' => 'select',
      '#title' => $this->t('Metric'),
      '#options' => $metric_options,
      '#default_value' => $query ? (string) $query->get('metric', '') : '',
    ];

    $form['filters']['date_from'] = [
      '#type' => 'date',
      '#title' => $this->t('From'),
      '#default_value' => $query ? (string) $query->get('date_from', '') : '',
    ];

    $form['filters']['date_to'] = [
      '#type' => 'date',
      '#title' => $this->t('To'),
      '#default_value' => $query ? (string) $query->get('date_to', '') : '',
    ];

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
    if ($sensor_device) {
      $form['filter_actions']['reset'] = [
        '#type' => 'component',
        '#component' => 'hivelog:button',
        '#props' => [
          'label' => (string) $this->t('Reset'),
          'url' => Url::fromRoute('entity.sensor_device.readings', ['sensor_device' => $sensor_device->id()])->toString(),
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
