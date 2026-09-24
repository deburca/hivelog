<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Shared detail-page section builders for HiveLog canonical-page controllers.
 *
 * Extracted from nine near-identical controller copies (task 0125): Queen,
 * QueenObservation, HiveInspection, CalendarAction, HiveActionLog,
 * ApiaryActionLog, InventoryItem, InventoryPurchase and Product. Each
 * consuming controller must extend `\Drupal\Core\Controller\ControllerBase`
 * (for `$this->t()` and `$this->entityTypeManager`) and implement
 * `formatFieldValue()` for its own type-specific field rendering.
 * `buildPhotosGrid()` additionally requires a `$this->fileUrlGenerator`
 * property (`FileUrlGeneratorInterface`), only needed by controllers that
 * call it.
 *
 * Lives in the `hivelog` core namespace (not a controller base class) so
 * submodule controllers (SensorDevice, ApiClient, AiProviderConfig — see
 * [[0118-page-owned-edit-delete-then-retire-local-tasks]]) can use it too
 * without depending on a `hivelog`-specific controller hierarchy.
 */
trait HivelogDetailPageTrait {

  /**
   * Builds Edit and Delete action links for a detail page.
   */
  protected function buildActions(FieldableEntityInterface $entity): array {
    $buttons = [];
    if ($entity->access('update')) {
      $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $entity->toUrl('edit-form')->toString()];
    }
    if ($entity->access('delete')) {
      $buttons[] = [
        'label' => (string) $this->t('Delete'),
        'url' => $entity->toUrl('delete-form')->toString(),
        'variant' => 'danger',
      ];
    }
    if (empty($buttons)) {
      return [];
    }
    return [
      '#type' => 'component',
      '#component' => 'hivelog:button-group',
      '#props' => ['buttons' => $buttons],
      '#weight' => -10,
    ];
  }

  /**
   * Builds a consistently formatted detail-page section.
   *
   * The heading is an H2 (task 0128) — every detail page's only
   * structure below its H1 page title is a flat run of these sections
   * (Overview, Identity, …), so each one is a top-level section, never
   * nested inside another.
   */
  protected function buildSection($title, FieldableEntityInterface $entity, array $fields): array {
    $prefix = $this->detailPageClassPrefix($entity);
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ["hivelog-{$prefix}-section"],
      ],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $title,
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Field'),
          $this->t('Value'),
        ],
        '#rows' => $this->buildRows($entity, $fields),
        '#attributes' => [
          'class' => ["hivelog-{$prefix}-table"],
        ],
        '#attached' => ['library' => ['hivelog/tables']],
      ],
    ];
  }

  /**
   * Builds rows for a section table.
   */
  protected function buildRows(FieldableEntityInterface $entity, array $fields): array {
    $rows = [];

    foreach ($fields as $field_name) {
      $rows[] = [
        [
          'data' => [
            '#plain_text' => (string) $entity->get($field_name)->getFieldDefinition()->getLabel(),
          ],
        ],
        [
          'data' => $this->buildFieldValue($entity, $field_name),
        ],
      ];
    }

    return $rows;
  }

  /**
   * Builds the display value for a single field.
   *
   * Handles the one rule every field shares (empty → em dash) and
   * delegates everything else to `formatFieldValue()`.
   */
  protected function buildFieldValue(FieldableEntityInterface $entity, string $field_name): array {
    $field = $entity->get($field_name);

    if ($field->isEmpty()) {
      return [
        '#plain_text' => (string) $this->t('—'),
      ];
    }

    return $this->formatFieldValue($entity, $field_name, $field);
  }

  /**
   * Formats a non-empty field's value for display.
   *
   * Implemented per controller: the type-specific switch over field names
   * (entity references, dates, booleans, allowed-value lists, long text,
   * …) that used to be duplicated inside each copy of `buildFieldValue()`.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity being displayed.
   * @param string $field_name
   *   The field being formatted.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field's value, already confirmed non-empty.
   *
   * @return array
   *   A render array for the field's value cell.
   */
  abstract protected function formatFieldValue(FieldableEntityInterface $entity, string $field_name, FieldItemListInterface $field): array;

  /**
   * Builds a lazy-loaded thumbnail grid for an image field.
   *
   * Requires `$this->entityTypeManager` and `$this->fileUrlGenerator`.
   */
  protected function buildPhotosGrid(FieldableEntityInterface $entity, string $field_name = 'images'): array {
    if ($entity->get($field_name)->isEmpty()) {
      return [];
    }

    $image_style = $this->entityTypeManager
      ->getStorage('image_style')
      ->load('thumbnail');

    $items = [];
    foreach ($entity->get($field_name) as $item) {
      /** @var \Drupal\file\FileInterface|null $file */
      $file = $item->entity;
      if (!$file) {
        continue;
      }

      $full_url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
      $thumb_url = $image_style ? $image_style->buildUrl($file->getFileUri()) : $full_url;
      $alt = (string) ($item->alt ?? '');

      $items[] = [
        'full_url' => $full_url,
        'thumb_url' => $thumb_url,
        'alt' => $alt,
      ];
    }

    if (empty($items)) {
      return [];
    }

    $prefix = $this->detailPageClassPrefix($entity);
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ["hivelog-{$prefix}-section", "hivelog-{$prefix}-photos"],
      ],
      '#attached' => [
        'library' => ['hivelog/images'],
      ],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Photos'),
      ],
      'grid' => [
        '#type' => 'inline_template',
        '#template' => '<div class="hivelog-photos-grid">{% for item in items %}<a class="hivelog-photos-grid__item" href="{{ item.full_url }}" target="_blank" rel="noopener"><img src="{{ item.thumb_url }}" alt="{{ item.alt }}" loading="lazy" /></a>{% endfor %}</div>',
        '#context' => [
          'items' => $items,
        ],
      ],
    ];
  }

  /**
   * Derives the `hivelog-<prefix>-*` CSS class prefix for an entity type.
   *
   * Mechanically `str_replace('_', '-', $entity->getEntityTypeId())` for
   * every type except `hive_inspection`, whose section/table classes
   * predate this trait as `hivelog-inspection-*` (not
   * `hivelog-hive-inspection-*`) — kept as-is so rendered output doesn't
   * change (task 0125's own acceptance criterion), with
   * [[0131-single-detail-table-css-class]] left to rename the class
   * surface deliberately, separately.
   */
  protected function detailPageClassPrefix(FieldableEntityInterface $entity): string {
    $overrides = ['hive_inspection' => 'inspection'];
    $entity_type_id = $entity->getEntityTypeId();
    return $overrides[$entity_type_id] ?? str_replace('_', '-', $entity_type_id);
  }

}
