<?php

declare(strict_types=1);

namespace Drupal\nanoprobe\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Filter form for `/hivelog/sensor-devices` (task 0157).
 *
 * Submits via GET so filter state is reflected in the URL query string
 * and plays nicely with Drupal's pager — same shape as
 * `HivelogHiveFilterForm` (task 0132), full-page-only. Not to be
 * confused with `SensorReadingFilterForm` (task 0110), which filters
 * one device's own reading history, not the device collection.
 */
class SensorDeviceFilterForm extends FormBase {

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
    return 'nanoprobe_sensor_device_filter_form';
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

    $device_fields = $this->entityFieldManager->getBaseFieldDefinitions('sensor_device');

    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-filter-form__filters']],
    ];

    $form['filters']['scope'] = [
      '#type' => 'select',
      '#title' => $this->t('Scope'),
      '#options' => ['' => $this->t('- Any -')] + $device_fields['scope']->getSetting('allowed_values'),
      '#default_value' => $filters['scope'] ?? '',
    ];

    $form['filters']['device_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Device Type'),
      '#options' => ['' => $this->t('- Any -')] + $device_fields['device_type']->getSetting('allowed_values'),
      '#default_value' => $filters['device_type'] ?? '',
    ];

    $form['filters']['transport'] = [
      '#type' => 'select',
      '#title' => $this->t('Transport'),
      '#options' => ['' => $this->t('- Any -')] + $device_fields['transport']->getSetting('allowed_values'),
      '#default_value' => $filters['transport'] ?? '',
    ];

    $form['filters']['enabled'] = [
      '#type' => 'select',
      '#title' => $this->t('Enabled'),
      '#options' => [
        '' => $this->t('- Any -'),
        '1' => $this->t('Yes'),
        '0' => $this->t('No'),
      ],
      '#default_value' => $filters['enabled'] ?? '',
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
   * Extracts sensor device filter values from a request's query string.
   *
   * @return array<string, string>
   *   Associative array keyed by filter name. Only non-empty values are
   *   included.
   */
  public static function extract(Request $request): array {
    $filters = [];
    foreach (['scope', 'device_type', 'transport', 'enabled'] as $key) {
      $value = trim((string) $request->query->get($key, ''));
      if ($value !== '') {
        $filters[$key] = $value;
      }
    }
    return $filters;
  }

  /**
   * Applies sensor device filter values (from `extract()`) to an entity query.
   */
  public static function apply(QueryInterface $query, array $filters): void {
    if (isset($filters['scope'])) {
      $query->condition('scope', $filters['scope']);
    }
    if (isset($filters['device_type'])) {
      $query->condition('device_type', $filters['device_type']);
    }
    if (isset($filters['transport'])) {
      $query->condition('transport', $filters['transport']);
    }
    if (isset($filters['enabled'])) {
      $query->condition('enabled', $filters['enabled']);
    }
  }

}
