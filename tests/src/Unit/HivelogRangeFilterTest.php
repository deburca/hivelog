<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\hivelog\HivelogRangeFilter;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for HivelogRangeFilter (task 0169).
 */
#[CoversClass(HivelogRangeFilter::class)]
#[Group('hivelog')]
class HivelogRangeFilterTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // TranslatableMarkup resolves the translator from the container.
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Strings a result's notices, for comparison.
   *
   * @param array $result
   *   A `[from, to, notices]` triple.
   *
   * @return string[]
   *   The notices as plain strings.
   */
  protected function notices(array $result): array {
    return array_map('strval', $result[2]);
  }

  /**
   * A valid, ordered date pair passes through with no notice.
   */
  public function testOrderedDatesUnchanged(): void {
    $result = HivelogRangeFilter::normaliseDates('2026-01-01', '2026-06-30');
    $this->assertSame(['2026-01-01', '2026-06-30'], [$result[0], $result[1]]);
    $this->assertSame([], $result[2]);
  }

  /**
   * Equal dates are a one-day range, not a reversal.
   */
  public function testEqualDatesAreNotSwapped(): void {
    $result = HivelogRangeFilter::normaliseDates('2026-03-03', '2026-03-03');
    $this->assertSame(['2026-03-03', '2026-03-03'], [$result[0], $result[1]]);
    $this->assertSame([], $result[2]);
  }

  /**
   * A reversed date pair is swapped and says so.
   */
  public function testReversedDatesAreSwappedWithNotice(): void {
    $result = HivelogRangeFilter::normaliseDates('2026-06-30', '2026-01-01');
    $this->assertSame(['2026-01-01', '2026-06-30'], [$result[0], $result[1]]);
    $this->assertCount(1, $result[2]);
    $this->assertStringContainsString('swapped', $this->notices($result)[0]);
  }

  /**
   * One side empty is fine, in either direction, with no notice.
   */
  public function testOneSidedRangesAreLeftAlone(): void {
    $result = HivelogRangeFilter::normaliseDates('2026-06-30', '');
    $this->assertSame(['2026-06-30', ''], [$result[0], $result[1]]);
    $this->assertSame([], $result[2]);

    $result = HivelogRangeFilter::normaliseDates('', '2026-01-01');
    $this->assertSame(['', '2026-01-01'], [$result[0], $result[1]]);
    $this->assertSame([], $result[2]);

    $result = HivelogRangeFilter::normaliseDates('', '');
    $this->assertSame(['', ''], [$result[0], $result[1]]);
    $this->assertSame([], $result[2]);
  }

  /**
   * Values that aren't real YYYY-MM-DD days are dropped with a notice.
   */
  #[DataProvider('invalidDateProvider')]
  public function testInvalidDateIsIgnored(string $bad): void {
    $result = HivelogRangeFilter::normaliseDates($bad, '2026-06-30');
    $this->assertSame(['', '2026-06-30'], [$result[0], $result[1]]);
    $this->assertCount(1, $result[2]);
    $this->assertStringContainsString('not a valid date', $this->notices($result)[0]);
  }

  /**
   * Provides malformed date strings.
   */
  public static function invalidDateProvider(): array {
    return [
      'words' => ['not-a-date'],
      'impossible day' => ['2026-02-30'],
      'impossible month' => ['2026-13-01'],
      'no zero padding' => ['2026-6-3'],
      'slashes' => ['2026/06/03'],
      'trailing junk' => ['2026-06-03x'],
      'with time' => ['2026-06-03 10:00'],
    ];
  }

  /**
   * An invalid date is dropped before the reversal check, not compared.
   */
  public function testInvalidDateIsNotUsedForReversalCheck(): void {
    $result = HivelogRangeFilter::normaliseDates('zzzz', '2026-01-01');
    $this->assertSame(['', '2026-01-01'], [$result[0], $result[1]]);
    $this->assertCount(1, $result[2], 'Only the invalid-date notice, no swap notice.');
  }

  /**
   * The user's raw value is shown truncated, so a huge value can't bloat it.
   */
  public function testInvalidValueInNoticeIsTruncated(): void {
    $result = HivelogRangeFilter::normaliseDates(str_repeat('x', 500), '');
    $this->assertLessThan(120, mb_strlen($this->notices($result)[0]));
  }

  /**
   * A valid week pair passes through; reversed weeks are swapped.
   */
  public function testWeeks(): void {
    $result = HivelogRangeFilter::normaliseWeeks('10', '20');
    $this->assertSame([10, 20, []], $result);

    $result = HivelogRangeFilter::normaliseWeeks('30', '20');
    $this->assertSame([20, 30], [$result[0], $result[1]]);
    $this->assertStringContainsString('swapped', $this->notices($result)[0]);
  }

  /**
   * Weeks are clamped to 1-53; non-numeric weeks are ignored with a notice.
   */
  public function testWeekClampingAndInvalid(): void {
    $result = HivelogRangeFilter::normaliseWeeks('0', '99');
    $this->assertSame([1, 53, []], $result);

    $result = HivelogRangeFilter::normaliseWeeks('abc', '20');
    $this->assertSame([NULL, 20], [$result[0], $result[1]]);
    $this->assertStringContainsString('not a valid week', $this->notices($result)[0]);

    foreach (['-5', '2.5', '1e1'] as $bad) {
      $this->assertNull(HivelogRangeFilter::normaliseWeeks($bad, '')[0], "'$bad' must be ignored.");
    }

    $this->assertSame([NULL, NULL, []], HivelogRangeFilter::normaliseWeeks('', ''));
  }

  /**
   * Reading the pair from a request honours custom (prefixed) keys.
   */
  public function testDatesFromRequestUsesGivenKeys(): void {
    $request = Request::create('/x', 'GET', [
      'obs_date_from' => '2026-06-30',
      'obs_date_to' => '2026-01-01',
      'date_from' => '1999-01-01',
    ]);

    $result = HivelogRangeFilter::datesFromRequest($request, 'obs_date_from', 'obs_date_to');
    $this->assertSame(['2026-01-01', '2026-06-30'], [$result[0], $result[1]]);

    $result = HivelogRangeFilter::datesFromRequest($request);
    $this->assertSame(['1999-01-01', ''], [$result[0], $result[1]]);
  }

  /**
   * The notice element is empty with no notices, a status paragraph with some.
   */
  public function testNoticeElement(): void {
    $this->assertSame([], HivelogRangeFilter::noticeElement([]));

    $element = HivelogRangeFilter::noticeElement(HivelogRangeFilter::normaliseDates('2026-06-30', '2026-01-01')[2]);
    $this->assertStringContainsString('role="status"', $element['#prefix']);
    $this->assertStringContainsString('swapped', $element['#markup']);
  }

  /**
   * The notice HTML-escapes a hostile value.
   */
  public function testNoticeEscapesHostileValue(): void {
    $notices = HivelogRangeFilter::normaliseDates('<script>alert(1)</script>', '')[2];
    $element = HivelogRangeFilter::noticeElement($notices);
    $this->assertStringNotContainsString('<script>', $element['#markup']);
  }

}
