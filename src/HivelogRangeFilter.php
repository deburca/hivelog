<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\HttpFoundation\Request;

/**
 * Normalises the From/To pair of a filter form (task 0169).
 *
 * A reversed pair ("From" later than "To") used to be applied as two
 * independent conditions, so it silently matched nothing; a malformed date
 * in a hand-edited query string was passed straight to the query. This
 * helper is the one place that decides what happens instead, so every
 * filter with a range behaves the same way:
 *
 * - A date that isn't a real `YYYY-MM-DD` day is ignored, and the notice
 *   says so.
 * - A reversed pair is swapped (the same thing Calendar Actions' week range
 *   always did), and the notice says so. Swapping rather than rejecting
 *   keeps the beekeeper's evident intent instead of throwing the whole
 *   range away.
 *
 * Every method is static and returns the notices to show instead of
 * emitting them, because `extract()` on the filter forms is also called by
 * list builders and controllers that must not print anything.
 */
final class HivelogRangeFilter {

  /**
   * Normalises a pair of `YYYY-MM-DD` strings.
   *
   * @return array{0: string, 1: string, 2: \Drupal\Core\StringTranslation\TranslatableMarkup[]}
   *   `[from, to, notices]`; `from`/`to` are `''` when absent or ignored.
   */
  public static function normaliseDates(string $from, string $to): array {
    $notices = [];
    $values = ['from' => trim($from), 'to' => trim($to)];

    foreach ($values as $key => $value) {
      if ($value !== '' && !self::isValidDate($value)) {
        $notices[] = new TranslatableMarkup('Ignored "@value": not a valid date (use YYYY-MM-DD).', [
          '@value' => mb_substr($value, 0, 20),
        ]);
        $values[$key] = '';
      }
    }

    // ISO dates sort the same as strings, so a plain comparison is enough.
    if ($values['from'] !== '' && $values['to'] !== '' && $values['from'] > $values['to']) {
      [$values['from'], $values['to']] = [$values['to'], $values['from']];
      $notices[] = new TranslatableMarkup('"From" was later than "To", so the two dates were swapped.');
    }

    return [$values['from'], $values['to'], $notices];
  }

  /**
   * Normalises a pair of ISO week numbers.
   *
   * Each is kept only if it is all digits, then clamped to 1-53.
   *
   * @return array{0: ?int, 1: ?int, 2: \Drupal\Core\StringTranslation\TranslatableMarkup[]}
   *   `[from, to, notices]`; `from`/`to` are NULL when absent or ignored.
   */
  public static function normaliseWeeks(string $from, string $to): array {
    $notices = [];
    $values = [];
    foreach (['from' => trim($from), 'to' => trim($to)] as $key => $value) {
      if ($value === '') {
        $values[$key] = NULL;
      }
      elseif (ctype_digit($value)) {
        $values[$key] = max(1, min(53, (int) $value));
      }
      else {
        $values[$key] = NULL;
        $notices[] = new TranslatableMarkup('Ignored "@value": not a valid week number.', [
          '@value' => mb_substr($value, 0, 20),
        ]);
      }
    }

    if ($values['from'] !== NULL && $values['to'] !== NULL && $values['from'] > $values['to']) {
      [$values['from'], $values['to']] = [$values['to'], $values['from']];
      $notices[] = new TranslatableMarkup('"From week" was later than "To week", so the two weeks were swapped.');
    }

    return [$values['from'], $values['to'], $notices];
  }

  /**
   * Normalises a date pair read from a request's query string.
   *
   * @return array{0: string, 1: string, 2: \Drupal\Core\StringTranslation\TranslatableMarkup[]}
   *   As `normaliseDates()`.
   */
  public static function datesFromRequest(Request $request, string $from_key = 'date_from', string $to_key = 'date_to'): array {
    return self::normaliseDates(
      (string) $request->query->get($from_key, ''),
      (string) $request->query->get($to_key, ''),
    );
  }

  /**
   * Whether `$value` is a real `YYYY-MM-DD` calendar day.
   */
  public static function isValidDate(string $value): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
      return FALSE;
    }
    return checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
  }

  /**
   * A render array for the notices, or `[]` when there are none.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup[] $notices
   *   Notices from one of the `normalise*()` methods.
   */
  public static function noticeElement(array $notices): array {
    if (!$notices) {
      return [];
    }
    return [
      '#markup' => implode(' ', array_map('strval', $notices)),
      '#prefix' => '<p class="hivelog-filter-form__notice" role="status">',
      '#suffix' => '</p>',
      '#weight' => -10,
    ];
  }

}
