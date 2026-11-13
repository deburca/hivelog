<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Builds a row of `hivelog:stat-tile` facts from an entity's fields (task 0256).
 *
 * The detail pages used to render short, enumerated values (status, type,
 * dates, a yes/no) as stacked two-column tables. A strip of stat tiles says the
 * same in far less space and matches the dashboard and the hive page. This is
 * the one place that turns a field into a tile, so each page names the fields
 * it wants and nothing else.
 *
 * A consuming class needs `$this->t()` (any `ControllerBase`). It is used by
 * `HivelogDetailPageTrait`, so the controllers that use that get it, and may be
 * used on its own (the apiary page).
 *
 * Each tile is described by an array, keyed by the field it shows (or by any
 * name, when it carries its own `value`). All keys are optional:
 * - `field`: the field to read, when the array key is not the field name.
 * - `label`: the tile label; the field's label by default.
 * - `value`: the already-formatted value, instead of reading a field. Present
 *   but NULL or '' means "no value".
 * - `url`: a destination for the whole tile (string or Url). For a reference
 *   field the tile links to the referenced record by default, when the viewer
 *   may see it; set FALSE to suppress that.
 * - `sublabel`, `variant`: the tile's sub-line and its tint (default,
 *   critical, warning, ok).
 * - `omit_empty`: drop the tile when there is no value, instead of "—".
 *
 * A value is never made into a link of its own: a tile is either a link as a
 * whole or a plain block, because a link inside a link is not valid markup.
 *
 * Override `tileFieldText()` in a controller for a field that needs special
 * wording; the default covers booleans, allowed-value lists, references, dates
 * and text.
 */
trait HivelogStatTileStripTrait {

  /**
   * The longest value a tile shows before it is cut with an ellipsis.
   */
  protected const TILE_VALUE_MAX_LENGTH = 80;

  /**
   * Builds the strip.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The record the fields belong to.
   * @param array<int|string, array|string> $tiles
   *   Tile descriptions (see the trait docblock). A plain string entry is
   *   shorthand for a field name with no options.
   * @param string[] $extra_classes
   *   Further classes for the wrapper, e.g. one kept for theming continuity.
   *
   * @return array
   *   A container render array, or an empty array when no tile has a value.
   */
  protected function buildTileStrip(FieldableEntityInterface $entity, array $tiles, array $extra_classes = []): array {
    $cache = new CacheableMetadata();
    $cache->addCacheableDependency($entity);
    $strip = [];

    foreach ($tiles as $key => $definition) {
      if (is_string($definition)) {
        $key = $definition;
        $definition = [];
      }
      $field_name = $definition['field'] ?? (string) $key;
      $field = $entity->hasField($field_name) ? $entity->get($field_name) : NULL;

      if (array_key_exists('value', $definition)) {
        $value = trim((string) $definition['value']);
      }
      elseif ($field && !$field->isEmpty()) {
        $value = trim($this->tileFieldText($field));
      }
      else {
        $value = '';
      }
      if ($value === '' && !empty($definition['omit_empty'])) {
        continue;
      }

      $label = $definition['label'] ?? ($field ? $field->getFieldDefinition()->getLabel() : (string) $key);
      $url = $definition['url'] ?? NULL;
      if ($url === NULL && $field && $value !== '') {
        $url = $this->tileReferenceUrl($field, $cache);
      }
      if ($url instanceof Url) {
        $url = $url->toString();
      }

      $strip[(string) $key] = [
        '#type' => 'component',
        '#component' => 'hivelog:stat-tile',
        '#props' => [
          'value' => $value !== '' ? Unicode::truncate($value, static::TILE_VALUE_MAX_LENGTH, TRUE, TRUE) : (string) $this->t('—'),
          'label' => (string) $label,
          'url' => $url ? (string) $url : '',
          'sublabel' => (string) ($definition['sublabel'] ?? ''),
          'sublabel_variant' => $definition['variant'] ?? 'default',
        ],
      ];
    }

    if (!$strip) {
      return [];
    }

    $strip['#type'] = 'container';
    $strip['#attributes'] = ['class' => array_merge(['hivelog-stat-tiles', 'hivelog-stat-tiles--facts'], $extra_classes)];
    $cache->applyTo($strip);
    return $strip;
  }

  /**
   * A non-empty field's value as plain text for a tile.
   *
   * Booleans become Yes/No, an allowed-value list its label, a reference the
   * record's label, a date `Y-m-d`; long text is flattened to one line. Anything
   * else is the first column's string.
   */
  protected function tileFieldText(FieldItemListInterface $field): string {
    $definition = $field->getFieldDefinition();
    $type = $definition->getType();
    // The first item's main column. Read through getValue() (an array) rather
    // than the magic ->value property, which static analysis cannot see.
    $raw = $field->getValue()[0]['value'] ?? '';

    switch ($type) {
      case 'boolean':
        return (string) ($raw ? $this->t('Yes') : $this->t('No'));

      case 'entity_reference':
        $target = $field->entity;
        if (!$target) {
          return '';
        }
        return $target instanceof UserInterface ? (string) $target->getDisplayName() : (string) $target->label();

      case 'timestamp':
      case 'created':
      case 'changed':
        return date('Y-m-d', (int) $raw);

      case 'string_long':
      case 'text_long':
      case 'text':
        return trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $raw)));
    }

    $allowed = $definition->getSetting('allowed_values');
    if (is_array($allowed) && isset($allowed[$raw])) {
      return (string) $allowed[$raw];
    }
    return (string) $raw;
  }

  /**
   * The page a reference field's tile should link to, if any.
   *
   * Only for a record the viewer may see and that has a page; the anonymous
   * user and a deleted record give nothing. The target's cache tags are added
   * so the strip is invalidated when it changes or access to it does.
   */
  protected function tileReferenceUrl(FieldItemListInterface $field, CacheableMetadata $cache): ?string {
    if ($field->getFieldDefinition()->getType() !== 'entity_reference') {
      return NULL;
    }
    $target = $field->entity;
    if (!$target || ($target instanceof UserInterface && $target->isAnonymous()) || !$target->hasLinkTemplate('canonical')) {
      return NULL;
    }
    $cache->addCacheableDependency($target);
    $access = $target->access('view', NULL, TRUE);
    $cache->addCacheableDependency($access);
    return $access->isAllowed() ? $target->toUrl()->toString() : NULL;
  }

}
