<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Turns a collection's filter form into a collapsible panel on a phone.
 *
 * On a phone a filter form is a column of fields taller than the screen, which
 * pushes the table it filters below the fold. Every `hivelog-filter-form` is
 * therefore wrapped (by `hivelog_form_alter()`, so no form class repeats it) in
 * a panel whose Filters bar opens and closes it: a visually hidden checkbox and
 * its label placed before the form, the same CSS-only disclosure as the app
 * nav's Menu button (task 0229), so no JavaScript and no flash of an open panel.
 * From 769px up the bar is hidden and the form shows as it always did.
 *
 * The bar says how many filters are set, because the closed panel would
 * otherwise hide why a list is short.
 */
final class HivelogFilterPanel {

  /**
   * The form's own class that marks it as a filter form.
   */
  public const FORM_CLASS = 'hivelog-filter-form';

  /**
   * Wraps a filter form's markup in the collapsible panel.
   *
   * @param array $form
   *   The form, which carries the `hivelog-filter-form` class; anything else
   *   is left alone.
   * @param string $form_id
   *   The form ID, which makes the toggle's `id` (one panel per form).
   */
  public static function wrap(array &$form, string $form_id): void {
    if (!in_array(self::FORM_CLASS, $form['#attributes']['class'] ?? [], TRUE)) {
      return;
    }
    $id = Html::getId('hivelog-filter-panel-' . $form_id);
    $active = self::activeCount($form);
    $count = $active > 0
      ? ' <span class="hivelog-filter-panel__count">' . Html::escape((string) \Drupal::translation()->formatPlural($active, '1 active', '@count active')) . '</span>'
      : '';

    $form['#attached']['library'][] = 'hivelog/filter_form';
    // Markup objects, because a plain #prefix goes through the admin XSS filter,
    // which drops <input> and <label>. Every dynamic part above is escaped.
    $form['#prefix'] = Markup::create(
      ($form['#prefix'] ?? '')
      . '<div class="hivelog-filter-panel">'
      . '<input type="checkbox" id="' . $id . '" class="hivelog-filter-panel__toggle">'
      . '<label for="' . $id . '" class="hivelog-filter-panel__label">'
      . Html::escape((string) new TranslatableMarkup('Filters')) . $count . '</label>'
    );
    $form['#suffix'] = Markup::create('</div>' . ($form['#suffix'] ?? ''));
  }

  /**
   * How many of a filter form's fields are set.
   *
   * A field counts when its default value (which the form fills from the
   * request) is neither empty nor the "- Any -" choice. The action buttons and
   * the hidden sort state are not filters.
   *
   * @param array $element
   *   The form, or a container within it.
   */
  public static function activeCount(array $element): int {
    $count = 0;
    foreach ($element as $key => $child) {
      if (!is_array($child) || (is_string($key) && str_starts_with($key, '#'))) {
        continue;
      }
      if (in_array($key, ['filter_actions', 'actions', 'range_notice'], TRUE) || str_starts_with((string) $key, 'sort_state_')) {
        continue;
      }
      $type = $child['#type'] ?? '';
      if (in_array($type, ['hidden', 'submit', 'button', 'token', 'html_tag', 'link', 'component', 'markup'], TRUE)) {
        continue;
      }
      if (isset($child['#default_value']) && $child['#default_value'] !== '' && $child['#default_value'] !== [] && $child['#default_value'] !== FALSE) {
        $count++;
      }
      $count += self::activeCount($child);
    }
    return $count;
  }

}
