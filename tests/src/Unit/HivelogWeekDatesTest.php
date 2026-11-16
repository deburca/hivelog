<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Unit;

use Drupal\hivelog\HivelogWeekDates;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests turning the calendar's ISO weeks into dates (task 0283).
 */
#[Group('hivelog')]
class HivelogWeekDatesTest extends UnitTestCase {

  /**
   * Formats a pair of bounds as two ISO dates.
   *
   * @param array{0: \DateTimeImmutable, 1: \DateTimeImmutable} $bounds
   *   The bounds.
   *
   * @return string[]
   *   The first and last day.
   */
  protected function iso(array $bounds): array {
    return [$bounds[0]->format('Y-m-d'), $bounds[1]->format('Y-m-d')];
  }

  /**
   * A window runs from the Monday of its first week to the last's Sunday.
   */
  public function testBoundsOfWindow(): void {
    $this->assertSame(['2026-03-30', '2026-05-03'], $this->iso(HivelogWeekDates::bounds(2026, 14, 18)));
  }

  /**
   * A single week, however it is written, is Monday to Sunday.
   */
  public function testBoundsOfSingleWeek(): void {
    $expected = ['2026-04-06', '2026-04-12'];
    $this->assertSame($expected, $this->iso(HivelogWeekDates::bounds(2026, 15)));
    $this->assertSame($expected, $this->iso(HivelogWeekDates::bounds(2026, 15, '')));
    $this->assertSame($expected, $this->iso(HivelogWeekDates::bounds(2026, 15, 15)));
    // An end before the start is not a window.
    $this->assertSame($expected, $this->iso(HivelogWeekDates::bounds(2026, 15, 3)));
  }

  /**
   * Weeks at either end of the year reach into the neighbouring year.
   */
  public function testBoundsAcrossYearEnds(): void {
    // 2026-W01 begins on Monday 29 December 2025.
    $this->assertSame(['2025-12-29', '2026-01-04'], $this->iso(HivelogWeekDates::bounds(2026, 1)));
    // 2026 has 53 weeks; the last ends on Sunday 3 January 2027.
    $this->assertSame(['2026-12-21', '2027-01-03'], $this->iso(HivelogWeekDates::bounds(2026, 52, 53)));
  }

  /**
   * The same weeks fall on different dates in another year.
   */
  public function testBoundsFollowTheYear(): void {
    $this->assertSame(['2027-04-05', '2027-05-09'], $this->iso(HivelogWeekDates::bounds(2027, 14, 18)));
  }

  /**
   * The weeks-only label.
   */
  public function testWeeks(): void {
    $this->assertSame('W14–W18', HivelogWeekDates::weeks(14, 18));
    $this->assertSame('W14', HivelogWeekDates::weeks(14));
    $this->assertSame('W14', HivelogWeekDates::weeks(14, ''));
    $this->assertSame('W14', HivelogWeekDates::weeks(14, 14));
  }

}
