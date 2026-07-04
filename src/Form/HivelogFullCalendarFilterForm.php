<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Filter form for the Full Calendar page (task 0027 follow-up).
 *
 * Submits via GET so filter state is reflected in the URL query string and
 * plays nicely with Drupal's pager, mirroring HivelogHiveFilterForm's
 * established pattern exactly.
 */
class HivelogFullCalendarFilterForm extends FormBase {

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
    return 'hivelog_full_calendar_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Apiary $apiary = NULL): array {
    $request = $this->requestStack->getCurrentRequest();
    $query = $request ? $request->query : NULL;

    $form['#method'] = 'get';
    $form['#attributes']['class'][] = 'hivelog-filter-form';
    $form['#attached']['library'][] = 'hivelog/filter_form';
    $form['#cache']['contexts'][] = 'url.query_args';

    $calendar_action_fields = $this->entityFieldManager->getBaseFieldDefinitions('calendar_action');

    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-filter-form__filters']],
    ];

    $form['filters']['scope'] = [
      '#type' => 'select',
      '#title' => $this->t('Scope'),
      '#options' => ['' => $this->t('- Any -')] + $calendar_action_fields['scope']->getSetting('allowed_values'),
      '#default_value' => $query ? (string) $query->get('scope', '') : '',
    ];

    $form['filters']['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#options' => ['' => $this->t('- Any -')] + $calendar_action_fields['category']->getSetting('allowed_values'),
      '#default_value' => $query ? (string) $query->get('category', '') : '',
    ];

    // Defaults to "Enabled only" so the page's default view matches its
    // original (task 0027) behaviour of hiding disabled actions; "Disabled
    // only" and "- Any -" let a beekeeper find and manage a disabled
    // action directly from this page.
    $form['filters']['enabled'] = [
      '#type' => 'select',
      '#title' => $this->t('Enabled'),
      '#options' => [
        '1' => $this->t('Enabled only'),
        '0' => $this->t('Disabled only'),
        '' => $this->t('- Any -'),
      ],
      '#default_value' => $query ? (string) $query->get('enabled', '1') : '1',
    ];

    $form['filters']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title contains'),
      '#size' => 20,
      '#default_value' => $query ? (string) $query->get('title', '') : '',
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
    if ($apiary) {
      $form['filter_actions']['reset'] = [
        '#type' => 'component',
        '#component' => 'hivelog:button',
        '#props' => [
          'label' => (string) $this->t('Reset'),
          'url' => Url::fromRoute('hivelog.apiary.calendar_action.collection', ['apiary' => $apiary->id()])->toString(),
        ],
      ];
    }

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
