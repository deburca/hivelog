<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Turns the seasonal calendar's ISO week numbers into dates (task 0283).
 *
 * A calendar action is scheduled in week numbers (`week_start`, `week_end`),
 * which is a good way to store "every year around then" and a poor way to
 * show it: few beekeepers know which week it is. Everywhere a window is
 * shown it is shown as dates for the year in view, with the weeks after it:
 * "30 Mar – 3 May (W14–W18)". Storage, sorting and the API are unchanged.
 */
final class HivelogWeekDates {

  /**
   * The first and last day of a week window in a year.
   *
   * Monday of the first week to Sunday of the last. ISO week 1 can begin in
   * the previous December and the last week can end in the next January;
   * the dates are given as they fall, not clamped to the year.
   *
   * @param int $year
   *   The ISO year.
   * @param int $week_start
   *   The first week, 1 to 53.
   * @param int|string|null $week_end
   *   The last week, or NULL / '' for a single week. A value below the first
   *   week is treated as a single week.
   *
   * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
   *   The first and the last day.
   */
  public static function bounds(int $year, int $week_start, int|string|null $week_end = NULL): array {
    $end = ($week_end === NULL || $week_end === '' || (int) $week_end < $week_start) ? $week_start : (int) $week_end;
    $midnight = new \DateTimeImmutable('today midnight');
    return [
      $midnight->setISODate($year, $week_start, 1),
      $midnight->setISODate($year, $end, 7),
    ];
  }

  /**
   * The weeks alone: "W14–W18", or "W14" for a single week.
   */
  public static function weeks(int $week_start, int|string|null $week_end = NULL): string {
    $single = $week_end === NULL || $week_end === '' || (int) $week_end <= $week_start;
    return 'W' . $week_start . ($single ? '' : '–W' . (int) $week_end);
  }

  /**
   * The dates alone: "30 Mar – 3 May", or "6–12 Apr" within one month.
   */
  public static function dates(int $year, int $week_start, int|string|null $week_end = NULL): string {
    [$first, $last] = self::bounds($year, $week_start, $week_end);
    if ($first->format('Y-m') === $last->format('Y-m')) {
      return $first->format('j') . '–' . $last->format('j') . ' ' . self::month($last);
    }
    return $first->format('j') . ' ' . self::month($first) . ' – ' . $last->format('j') . ' ' . self::month($last);
  }

  /**
   * Dates with the weeks after them: "30 Mar – 3 May (W14–W18)".
   *
   * An empty string when there is no first week.
   */
  public static function label(int $year, int|string|null $week_start, int|string|null $week_end = NULL): string {
    if ($week_start === NULL || $week_start === '' || (int) $week_start < 1) {
      return '';
    }
    $week_start = (int) $week_start;
    return self::dates($year, $week_start, $week_end) . ' (' . self::weeks($week_start, $week_end) . ')';
  }

  /**
   * A month's abbreviated name in the interface language.
   *
   * The same strings and context core's date formatter translates, so a
   * site that has them translated gets them here too.
   */
  protected static function month(\DateTimeImmutable $date): string {
    $months = [
      1 => new TranslatableMarkup('Jan', [], ['context' => 'Abbreviated month name']),
      2 => new TranslatableMarkup('Feb', [], ['context' => 'Abbreviated month name']),
      3 => new TranslatableMarkup('Mar', [], ['context' => 'Abbreviated month name']),
      4 => new TranslatableMarkup('Apr', [], ['context' => 'Abbreviated month name']),
      5 => new TranslatableMarkup('May', [], ['context' => 'Abbreviated month name']),
      6 => new TranslatableMarkup('Jun', [], ['context' => 'Abbreviated month name']),
      7 => new TranslatableMarkup('Jul', [], ['context' => 'Abbreviated month name']),
      8 => new TranslatableMarkup('Aug', [], ['context' => 'Abbreviated month name']),
      9 => new TranslatableMarkup('Sep', [], ['context' => 'Abbreviated month name']),
      10 => new TranslatableMarkup('Oct', [], ['context' => 'Abbreviated month name']),
      11 => new TranslatableMarkup('Nov', [], ['context' => 'Abbreviated month name']),
      12 => new TranslatableMarkup('Dec', [], ['context' => 'Abbreviated month name']),
    ];
    return (string) $months[(int) $date->format('n')];
  }

}
