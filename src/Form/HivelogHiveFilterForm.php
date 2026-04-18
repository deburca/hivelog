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

    $form['filters']['bee_breed'] = [
      '#type' => 'select',
      '#title' => $this->t('Breed'),
      '#options' => ['' => $this->t('- Any -')] + $hive_fields['bee_breed']->getSetting('allowed_values'),
      '#default_value' => $query ? (string) $query->get('bee_breed', '') : '',
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

    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['hivelog-filter-form__actions']],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];
    if ($apiary) {
      $form['actions']['reset'] = [
        '#type' => 'link',
        '#title' => $this->t('Reset'),
        '#url' => Url::fromRoute('entity.apiary.canonical', ['apiary' => $apiary->id()]),
        '#attributes' => ['class' => ['button']],
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
