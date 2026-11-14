<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Filter form for the hive table on the apiary view page and `/hivelog/hives`.
 *
 * Submits via GET so filter state is reflected in the URL query string and
 * plays nicely with Drupal's pager. Works with or without a parent apiary
 * (task 0132) — `ApiaryController::view()` passes one for the embedded
 * table; `HiveListBuilder` (the full `/hivelog/hives` list) passes none,
 * and Reset then targets the current route instead of the apiary page.
 *
 * `extract()` / `apply()` are static so `ApiaryController` and
 * `HiveListBuilder` share one implementation instead of each keeping its
 * own copy — see AGENTS.md "Routing, controllers and forms" for why a
 * scoped add route matters here too (Add Hive still requires an apiary
 * context; only the filter/list side of this page works without one).
 */
class HivelogHiveFilterForm extends FormBase {

  /**
   * The `status` query value that lists hives of every status (task 0269).
   */
  public const STATUS_ANY = 'all';

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
    $filters = $request ? static::extract($request) : [];

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

    // Active hives are the default (task 0269): the empty value means
    // "active", so a plain visit hides hives that are dead, sold, merged or
    // inactive, and the default does not count as a filter the user set.
    // "Any" is the explicit value static::STATUS_ANY.
    $statuses = $hive_fields['status']->getSetting('allowed_values');
    $active_label = $statuses['active'];
    unset($statuses['active']);
    $form['filters']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => ['' => $active_label, static::STATUS_ANY => $this->t('- Any -')] + $statuses,
      '#default_value' => $filters['status'] ?? '',
    ];

    // Breed lives on the active queen, not the hive (a hive's breed
    // identity comes from whichever queen currently occupies it) — see
    // apply()'s own hiveIdsForActiveQueenBreed().
    $form['filters']['breed'] = [
      '#type' => 'select',
      '#title' => $this->t('Breed'),
      '#options' => ['' => $this->t('- Any -')] + $queen_fields['breed']->getSetting('allowed_values'),
      '#default_value' => $filters['breed'] ?? '',
    ];

    $form['filters']['temperament'] = [
      '#type' => 'select',
      '#title' => $this->t('Temperament'),
      '#options' => ['' => $this->t('- Any -')] + $hive_fields['temperament']->getSetting('allowed_values'),
      '#default_value' => $filters['temperament'] ?? '',
    ];

    $form['filters']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name contains'),
      '#size' => 20,
      '#default_value' => $filters['name'] ?? '',
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
    // With a parent apiary (the embedded table), Reset returns to the
    // apiary page. Without one (the full /hivelog/hives list), Reset
    // targets the current route with the query string cleared — '<current>'
    // resolves to this request's own route match, no route name needed.
    $reset_url = $apiary
      ? Url::fromRoute('entity.apiary.canonical', ['apiary' => $apiary->id()])
      : Url::fromRoute('<current>');
    $form['filter_actions']['reset'] = [
      '#type' => 'component',
      '#component' => 'hivelog:button',
      '#props' => [
        'label' => (string) $this->t('Reset'),
        'url' => $reset_url->toString(),
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
   * Extracts hive filter values from a request's query string.
   *
   * @return array<string, string>
   *   Associative array keyed by filter name. Only non-empty values are
   *   included.
   */
  public static function extract(Request $request): array {
    $filters = [];
    foreach (['status', 'breed', 'temperament', 'name'] as $key) {
      $value = trim((string) $request->query->get($key, ''));
      if ($value !== '') {
        $filters[$key] = $value;
      }
    }
    // "Active" is the default, not a filter (task 0269): an explicit
    // `status=active` (an old bookmark) reads the same as no status at all.
    if (($filters['status'] ?? '') === 'active') {
      unset($filters['status']);
    }
    return $filters;
  }

  /**
   * Whether the default (active only) view is hiding a hive from this user.
   *
   * For the empty-state message: a list with no active hives is not a list
   * with no hives if others exist (task 0269). Only hives the current user
   * may view count, so the message never gives away a hive that is hidden
   * from them; the entity query has no access of its own for this type.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The hive storage.
   * @param \Drupal\hivelog\Entity\Apiary|null $apiary
   *   Limit the question to one apiary, or NULL for every hive.
   */
  public static function hasViewableHiveNotActive(EntityStorageInterface $storage, ?Apiary $apiary = NULL): bool {
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 'active', '<>')
      ->range(0, 50);
    if ($apiary) {
      $query->condition('apiary', $apiary->id());
    }
    foreach ($storage->loadMultiple($query->execute()) as $hive) {
      if ($hive->access('view')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Applies hive filter values (from `extract()`) to an entity query.
   */
  public static function apply(QueryInterface $query, array $filters): void {
    // No status means active hives only; STATUS_ANY lifts the condition
    // (task 0269).
    $status = $filters['status'] ?? 'active';
    if ($status !== static::STATUS_ANY) {
      $query->condition('status', $status);
    }
    if (isset($filters['breed'])) {
      // Breed lives on the active queen, not the hive, so resolve matching
      // hive ids via the queen entity first (see Hive::getActiveQueen()).
      $hive_ids = static::hiveIdsForActiveQueenBreed($filters['breed']);
      $query->condition('id', $hive_ids, 'IN');
    }
    if (isset($filters['temperament'])) {
      $query->condition('temperament', $filters['temperament']);
    }
    if (isset($filters['name'])) {
      $query->condition('name', '%' . static::escapeLike($filters['name']) . '%', 'LIKE');
    }
  }

  /**
   * Finds hive ids whose active queen has the given breed.
   *
   * @param string $breed
   *   One of the `breed` field's allowed values on the Queen entity.
   *
   * @return int[]
   *   Matching hive ids, or `[0]` (a value no hive can have) if none match,
   *   so callers can pass the result straight into an `IN` condition.
   */
  protected static function hiveIdsForActiveQueenBreed(string $breed): array {
    $queen_storage = \Drupal::entityTypeManager()->getStorage('queen');
    $queen_ids = $queen_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('breed', $breed)
      ->condition('status', 'active')
      ->execute();

    $hive_ids = [];
    if ($queen_ids) {
      foreach ($queen_storage->loadMultiple($queen_ids) as $queen) {
        $hive_id = $queen->get('hive')->target_id;
        if ($hive_id) {
          $hive_ids[] = $hive_id;
        }
      }
    }

    return $hive_ids ?: [0];
  }

  /**
   * Escapes LIKE wildcard characters for safe use inside a LIKE condition.
   */
  protected static function escapeLike(string $value): string {
    return addcslashes($value, '\\%_');
  }

}
