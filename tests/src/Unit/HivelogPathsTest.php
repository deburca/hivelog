<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Unit;

use Drupal\Core\Site\Settings;
use Drupal\hivelog\HivelogPaths;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the path prefix helper (task 0246).
 */
#[Group('hivelog')]
class HivelogPathsTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    new Settings([]);
    parent::tearDown();
  }

  /**
   * Without the setting the pages stay at /hivelog and nothing moves.
   */
  public function testDefaultMovesNothing(): void {
    new Settings([]);
    $this->assertSame('hivelog', HivelogPaths::prefix());
    $this->assertNull(HivelogPaths::moved('/hivelog/apiaries'));
    $this->assertTrue(HivelogPaths::matches('/hivelog'));
    $this->assertTrue(HivelogPaths::matches('/hivelog/hive/{hive}'));
    $this->assertFalse(HivelogPaths::matches('/app/hive/{hive}'));
  }

  /**
   * A configured prefix replaces /hivelog on every path under it.
   */
  public function testConfiguredPrefixMovesPaths(): void {
    new Settings(['hivelog_path_prefix' => 'app']);
    $this->assertSame('app', HivelogPaths::prefix());
    $this->assertSame('/app', HivelogPaths::moved('/hivelog'));
    $this->assertSame('/app/apiary/{apiary}/edit', HivelogPaths::moved('/hivelog/apiary/{apiary}/edit'));
    $this->assertTrue(HivelogPaths::matches('/app'));
    $this->assertTrue(HivelogPaths::matches('/app/hives'));
    $this->assertFalse(HivelogPaths::matches('/hivelog/hives'));
    // A path that merely starts with the same letters is not under it.
    $this->assertFalse(HivelogPaths::matches('/apple'));
  }

  /**
   * Routes not under /hivelog, and the machine endpoints, never move.
   */
  public function testOtherPathsStay(): void {
    new Settings(['hivelog_path_prefix' => 'app']);
    $this->assertNull(HivelogPaths::moved('/user/login'));
    $this->assertNull(HivelogPaths::moved('/api/v1/schema'));
    $this->assertNull(HivelogPaths::moved('/hivelogger'));
    $this->assertNull(HivelogPaths::moved('/hivelog/api/sensor-readings'));
    $this->assertNull(HivelogPaths::moved('/hivelog/api/collective/context'));
    // /hivelog/apiaries is a page, not under /hivelog/api/.
    $this->assertSame('/app/apiaries', HivelogPaths::moved('/hivelog/apiaries'));
  }

  /**
   * An unusable value is ignored rather than breaking routing.
   */
  #[DataProvider('unusableValues')]
  public function testUnusableValueFallsBack(mixed $value): void {
    new Settings(['hivelog_path_prefix' => $value]);
    $this->assertSame('hivelog', HivelogPaths::prefix());
    $this->assertNull(HivelogPaths::moved('/hivelog/hives'));
  }

  /**
   * Values the setting must not take.
   */
  public static function unusableValues(): array {
    return [
      'empty' => [''],
      'not a string' => [42],
      'capitals' => ['App'],
      'space' => ['my app'],
      'nested' => ['a/b'],
      'leading hyphen' => ['-app'],
      'reserved: admin' => ['admin'],
      'reserved: user' => ['user'],
      'reserved: api' => ['api'],
      'too long' => [str_repeat('a', 33)],
    ];
  }

  /**
   * Slashes around the value are tolerated.
   */
  public function testSurroundingSlashesAreTrimmed(): void {
    new Settings(['hivelog_path_prefix' => '/app/']);
    $this->assertSame('app', HivelogPaths::prefix());
  }

}
