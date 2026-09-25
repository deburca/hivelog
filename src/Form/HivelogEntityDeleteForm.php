<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\hivelog\Delete\HivelogDeleteDependencyCounter;
use Drupal\hivelog\Delete\HivelogDeleteDependencyRegistry;
use Drupal\hivelog\HivelogEntityHierarchy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared delete-form base for every hivelog content entity (task 0127).
 *
 * One rule, everywhere:
 * - **Cancel** returns to the entity's own canonical page when it has
 *   one, otherwise to its parent's canonical page (resolved via
 *   `HivelogEntityHierarchy` — requirement / yield fall through to their
 *   calendar action). Cancel undoes the click that brought the user
 *   here.
 * - **After delete**: the parent's canonical page, else the entity
 *   type's own collection, else the dashboard.
 * - A `?destination=` query wins over both, as core already does: it is
 *   applied automatically to the rendered Cancel link by
 *   `ConfirmFormHelper::buildCancelLink()`, and to the post-delete
 *   redirect by core's form submission handling — neither override
 *   below needs to special-case it.
 *
 * `getQuestion()` and the deletion message are left to
 * `ContentEntityDeleteForm`'s own defaults, which already read the
 * entity type's singular label (`label_singular` on every hivelog
 * entity's `#[ContentEntityType]` attribute) — so most hivelog delete
 * forms need nothing beyond
 * `handlers.form.delete: HivelogEntityDeleteForm::class`. Subclasses
 * exist only where there is real extra logic:
 * `InventoryItemDeleteForm` / `ProductDeleteForm` (a historical-reference
 * warning in `getDescription()`), and the submodule forms
 * (`SensorDeviceDeleteForm`, `ApiClientDeleteForm`,
 * `AiProviderConfigDeleteForm`) for their own `getDescription()` text.
 *
 * Task 0134 adds one dependency section per treatment
 * (`HivelogDeleteDependencyRegistry`) the entity has children under:
 * BLOCK hides the Delete button entirely; WARN, CASCADE and DETACH
 * inform without changing what Delete does (their actual cascade /
 * detach execution is task 0142 / 0143's, run from
 * `hivelog_entity_predelete()` regardless of which path triggered the
 * delete, not from this form).
 */
class HivelogEntityDeleteForm extends ContentEntityDeleteForm {

  /**
   * The delete-dependency counter (task 0134).
   */
  protected HivelogDeleteDependencyCounter $dependencyCounter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->dependencyCounter = $container->get('hivelog.delete_dependency_counter');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $sections = $this->buildDependencySections();
    if ($sections) {
      $form['hivelog_delete_dependencies'] = $sections;
    }
    if ($this->hasBlockingDependency()) {
      unset($form['actions']['submit']);
    }

    return $form;
  }

  /**
   * The form-level half of ADR-0103's "no Delete button" rule.
   *
   * Whether any BLOCK row has children. Access-layer enforcement (so
   * the block also holds on every other path: list buttons, the
   * Navigation top bar, API, drush) is task 0141's, via
   * `HivelogDeleteDependencyCounter::blockingAccessResult()`.
   */
  protected function hasBlockingDependency(): bool {
    foreach ($this->dependencyCounter->countsFor($this->getEntity()) as $row) {
      if ($row['treatment'] === HivelogDeleteDependencyRegistry::BLOCK) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * One notice section per treatment present among the entity's rows.
   *
   * Returns an empty array when it has none.
   */
  protected function buildDependencySections(): array {
    $counts = $this->dependencyCounter->countsFor($this->getEntity());
    if (!$counts) {
      return [];
    }

    $by_treatment = [];
    foreach ($counts as $row) {
      $by_treatment[$row['treatment']][] = $row;
    }

    $build = [
      '#weight' => -10,
      '#attached' => ['library' => ['hivelog/notices']],
    ];
    if (!empty($by_treatment[HivelogDeleteDependencyRegistry::BLOCK])) {
      $build['block'] = $this->buildTreatmentSection(
        $this->t("Can't delete yet"),
        $by_treatment[HivelogDeleteDependencyRegistry::BLOCK],
        'critical',
      );
    }
    if (!empty($by_treatment[HivelogDeleteDependencyRegistry::WARN])) {
      $build['warn'] = $this->buildTreatmentSection(
        $this->t('Before you delete'),
        $by_treatment[HivelogDeleteDependencyRegistry::WARN],
        'warning',
      );
    }
    if (!empty($by_treatment[HivelogDeleteDependencyRegistry::CASCADE])) {
      $build['cascade'] = $this->buildTreatmentSection(
        $this->t('Will also be deleted'),
        $by_treatment[HivelogDeleteDependencyRegistry::CASCADE],
        'warning',
      );
    }
    if (!empty($by_treatment[HivelogDeleteDependencyRegistry::DETACH])) {
      $build['detach'] = $this->buildTreatmentSection(
        $this->t('Will be kept but unlinked'),
        $by_treatment[HivelogDeleteDependencyRegistry::DETACH],
        'warning',
      );
    }
    return $build;
  }

  /**
   * One `.hivelog-notice--*` box listing every row in `$rows`.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $heading
   *   The section heading.
   * @param array[] $rows
   *   Counted rows (from `HivelogDeleteDependencyCounter::countsFor()`)
   *   sharing one treatment.
   * @param string $variant
   *   Either `critical` or `warning` — which `.hivelog-notice--*` class
   *   and token pair to use.
   */
  protected function buildTreatmentSection($heading, array $rows, string $variant): array {
    $items = [];
    foreach ($rows as $row) {
      $items[] = $this->buildRowItem($row);
    }
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ["hivelog-notice--$variant"]],
      'heading' => [
        '#type' => 'html_tag',
        // H2 (task 0128) — these are the delete form's only top-level
        // sections below its H1 page title.
        '#tag' => 'h2',
        '#value' => $heading,
      ],
      'items' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
  }

  /**
   * The render array for a single counted row.
   *
   * The count, with a link when the current user may access it and the
   * row names one, plus — for a DETACH row — a short parenthetical
   * naming the specific consequence (task 0143), e.g. "2 queens (they
   * will become unassigned and inactive)".
   */
  protected function buildRowItem(array $row): array {
    $entity_type = $this->entityTypeManager->getDefinition($row['child']);
    $text = $this->formatPlural(
      $row['total'],
      '1 @label',
      '@count @label_plural',
      ['@label' => $entity_type->getSingularLabel(), '@label_plural' => $entity_type->getPluralLabel()],
    );

    if (!empty($row['not_deletable'])) {
      $text = $this->formatPlural(
        $row['not_deletable'],
        '@text (1 you cannot delete yourself — ask its owner or a site administrator)',
        '@text (@count you cannot delete yourself — ask their owner or a site administrator)',
        ['@text' => $text],
      );
    }

    if ($row['treatment'] === HivelogDeleteDependencyRegistry::DETACH) {
      $note = $this->detachConsequenceNote($row);
      if ($note) {
        $text = $this->t('@text (@note)', ['@text' => $text, '@note' => $note]);
      }
    }

    $url = HivelogDeleteDependencyRegistry::manageUrl($row, $this->getEntity());
    if ($url && $url->access()) {
      return ['#markup' => Link::fromTextAndUrl($text, $url)->toString()];
    }
    return ['#markup' => $text];
  }

  /**
   * The specific consequence a DETACH row's children face (task 0143).
   *
   * A literal `match()` rather than a variable-keyed array, per Drupal
   * coding standards (translated strings must be statically
   * extractable) — matches `HivelogBreadcrumbBuilder::terminalCrumbLabel()`'s
   * own established pattern for the same reason.
   */
  protected function detachConsequenceNote(array $row): ?TranslatableMarkup {
    return match ($row['adr_row']) {
      '11' => $this->t('they will become unassigned and inactive'),
      '12' => $this->t('it will become apiary-scoped'),
      default => NULL,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    if (!$entity->isDefaultTranslation()) {
      return parent::getCancelUrl();
    }
    if ($entity->hasLinkTemplate('canonical')) {
      return $entity->toUrl('canonical');
    }
    return $this->parentOrFallbackUrl($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl() {
    return $this->parentOrFallbackUrl($this->getEntity());
  }

  /**
   * The parent's canonical page, else the entity's own collection.
   *
   * Falls through to the dashboard when neither is available.
   */
  protected function parentOrFallbackUrl(EntityInterface $entity): Url {
    return HivelogEntityHierarchy::parentOrCollectionUrl($entity);
  }

}
