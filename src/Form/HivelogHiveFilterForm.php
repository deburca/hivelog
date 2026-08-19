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
 * Filter form for the hive child table on the apiary view page.
 *
 * Submits via GET so filter state is reflected in the URL query string and
 * plays nicely with Drupal's pager.
 */
class HivelogHiveFilterForm extends FormBase {

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
    return 'hivelog_hive_filter_form';
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

    $hive_fields = $this->entityFieldManager->getBaseFieldDefinitions('hive');
    $queen_fields = $this->entityFieldManager->getBaseFieldDefinitions('queen');

    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-filter-form__filters']],
    ];

    $form['filters']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => ['' => $this->t('- Any -')] + $hive_fields['status']->getSetting('allowed_values'),
      '#default_value' => $query ? (string) $query->get('status', '') : '',
    ];

    // Breed lives on the active queen, not the hive (a hive's breed
    // identity comes from whichever queen currently occupies it) — see
    // ApiaryController::applyHiveFilters().
    $form['filters']['breed'] = [
      '#type' => 'select',
      '#title' => $this->t('Breed'),
      '#options' => ['' => $this->t('- Any -')] + $queen_fields['breed']->getSetting('allowed_values'),
      '#default_value' => $query ? (string) $query->get('breed', '') : '',
    ];

    $form['filters']['temperament'] = [
      '#type' => 'select',
      '#title' => $this->t('Temperament'),
      '#options' => ['' => $this->t('- Any -')] + $hive_fields['temperament']->getSetting('allowed_values'),
      '#default_value' => $query ? (string) $query->get('temperament', '') : '',
    ];

    $form['filters']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name contains'),
      '#size' => 20,
      '#default_value' => $query ? (string) $query->get('name', '') : '',
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
    if ($apiary) {
      $form['filter_actions']['reset'] = [
        '#type' => 'component',
        '#component' => 'hivelog:button',
        '#props' => [
          'label' => (string) $this->t('Reset'),
          'url' => Url::fromRoute('entity.apiary.canonical', ['apiary' => $apiary->id()])->toString(),
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
