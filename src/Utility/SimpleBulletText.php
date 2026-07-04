<?php

declare(strict_types=1);

namespace Drupal\hivelog\Utility;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Markup;

/**
 * Renders plain text with a lightweight "- " / "* " bullet-list convention.
 *
 * Used for CalendarAction::description (and any other string_long field
 * that wants simple bullet support without a text-format/WYSIWYG
 * dependency the module doesn't otherwise have). Every line is escaped
 * with Html::escape() before any markup is added, per the sanitisation
 * contract in
 * docs/project-management/decisions/0017-output-sanitisation-policy.md.
 *
 * @see \Drupal\hivelog\Entity\CalendarAction
 */
class SimpleBulletText {

  /**
   * Renders text as safe HTML, wrapping "- "/"* " lines in a bullet list.
   *
   * Consecutive lines starting with "- " or "* " are grouped into a single
   * <ul> with one <li> per line (the marker itself is stripped). Other
   * non-empty lines each become their own <p>. Blank lines only close the
   * current list/paragraph run; they do not produce empty output.
   *
   * @param string $text
   *   The raw (untrusted) plain text.
   *
   * @return \Drupal\Core\Render\Markup
   *   Safe, pre-escaped HTML markup suitable for a render array's
   *   '#markup' key.
   */
  public static function render(string $text): Markup {
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
    $html = '';
    $list_items = [];

    foreach ($lines as $line) {
      $trimmed = trim($line);

      if ($trimmed === '') {
        $html .= static::flushList($list_items);
        continue;
      }

      if (str_starts_with($trimmed, '- ') || str_starts_with($trimmed, '* ')) {
        $list_items[] = Html::escape(trim(substr($trimmed, 2)));
        continue;
      }

      $html .= static::flushList($list_items);
      $html .= '<p>' . Html::escape($trimmed) . '</p>';
    }
    $html .= static::flushList($list_items);

    return Markup::create($html);
  }

  /**
   * Flushes any buffered bullet-list items into a <ul> and clears them.
   *
   * @param array<int, string> $list_items
   *   Already-escaped list item strings, passed by reference so the caller's
   *   buffer is cleared after flushing.
   *
   * @return string
   *   The rendered `<ul>` markup, or an empty string if there were no
   *   buffered items.
   */
  protected static function flushList(array &$list_items): string {
    if (!$list_items) {
      return '';
    }
    $html = '<ul><li>' . implode('</li><li>', $list_items) . '</li></ul>';
    $list_items = [];
    return $html;
  }

}
