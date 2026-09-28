<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\Queen;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests filters on the Apiaries and Queens lists (task 0155).
 *
 * `/hivelog/apiaries` and `/hivelog/queens` (previously unfiltered) now
 * carry `HivelogApiaryFilterForm` / `HivelogQueenFilterForm` — both
 * full-page-only, neither has an embedded counterpart elsewhere the
 * way Hive/Inspection/QueenObservation's filters do (task 0132's own
 * `FullListFilterTest`, which this file's shape mirrors).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ApiaryQueenFilterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'datetime',
    'options',
    'file',
    'image',
    'geofield',
    'hivelog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('queen');
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'admin', 'label' => 'Admin']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $user = User::create(['name' => 'tester', 'mail' => 'tester@example.com']);
    $user->addRole('admin');
    $user->save();
    \Drupal::currentUser()->setAccount($user);
  }

  /**
   * Pushes a request onto the stack, routed as `$route_name`.
   *
   * Matches the request through the real router first, so
   * `current_route_match` (and therefore `Url::fromRoute('<current>')`,
   * which Reset relies on) resolves to this route — a bare
   * `Request::create()` alone leaves no route attributes set.
   */
  protected function pushRoutedRequest(string $route_name, string $path, array $query = []): void {
    $request = Request::create($path, 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->attributes->add(\Drupal::service('router')->matchRequest($request));
    \Drupal::service('request_stack')->push($request);
  }

  /**
   * Tests the Apiaries list filters by visibility and Reset clears it.
   */
  public function testApiaryListVisibilityFilterAndReset(): void {
    Apiary::create(['name' => 'Public Apiary', 'visibility' => 'public'])->save();
    Apiary::create(['name' => 'Private Apiary', 'visibility' => 'private'])->save();

    $this->pushRoutedRequest('entity.apiary.collection', '/hivelog/apiaries', ['visibility' => 'public']);
    $build = \Drupal::entityTypeManager()->getListBuilder('apiary')->render();

    $this->assertCount(1, $build['table']['#props']['rows']);
    $this->assertStringContainsString('Public Apiary', (string) $build['table']['#props']['rows'][0]['cells'][0]);

    $reset_url = $this->resetUrl($build);
    $this->assertStringContainsString('/hivelog/apiaries', $reset_url);
    $this->assertStringNotContainsString('visibility', $reset_url);
  }

  /**
   * Tests the Apiaries list filters by name and the empty state distinguishes.
   */
  public function testApiaryListNameFilterAndEmptyState(): void {
    Apiary::create(['name' => 'Ravnholt Home', 'visibility' => 'private'])->save();

    $this->pushRoutedRequest('entity.apiary.collection', '/hivelog/apiaries', ['name' => 'Ravnholt']);
    $build = \Drupal::entityTypeManager()->getListBuilder('apiary')->render();
    $this->assertCount(1, $build['table']['#props']['rows']);

    $this->pushRoutedRequest('entity.apiary.collection', '/hivelog/apiaries', ['name' => 'Nonexistent']);
    $build = \Drupal::entityTypeManager()->getListBuilder('apiary')->render();
    $this->assertCount(0, $build['table']['#props']['rows']);
    $this->assertStringContainsString('match the current filters', $build['table']['#props']['empty_message']);
  }

  /**
   * Tests the Queens list filters by status and breed, Reset clears both.
   */
  public function testQueenListStatusAndBreedFilterAndReset(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    Queen::create([
      'name' => 'Q1',
      'hive' => $hive->id(),
      'queen_year' => 2025,
      'breed' => 'buckfast',
      'status' => 'active',
    ])->save();
    Queen::create([
      'name' => 'Q2',
      'hive' => $hive->id(),
      'queen_year' => 2024,
      'breed' => 'carniolan',
      'status' => 'inactive',
    ])->save();

    $this->pushRoutedRequest('entity.queen.collection', '/hivelog/queens', ['status' => 'active', 'breed' => 'buckfast']);
    $build = \Drupal::entityTypeManager()->getListBuilder('queen')->render();

    $this->assertCount(1, $build['table']['#props']['rows']);
    $this->assertStringContainsString('Q1', (string) $build['table']['#props']['rows'][0]['cells'][0]);

    $reset_url = $this->resetUrl($build);
    $this->assertStringContainsString('/hivelog/queens', $reset_url);
    $this->assertStringNotContainsString('breed', $reset_url);
  }

  /**
   * Tests the Queens list's name filter matches origin too (an OR group).
   */
  public function testQueenListNameFilterMatchesOrigin(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    Queen::create([
      'name' => 'Q1',
      'origin' => 'Local breeder',
      'hive' => $hive->id(),
      'queen_year' => 2025,
      'status' => 'active',
    ])->save();
    Queen::create([
      'name' => 'Q2',
      'origin' => 'Swarm',
      'hive' => $hive->id(),
      'queen_year' => 2024,
      'status' => 'active',
    ])->save();

    // Matches via 'origin', not 'name' — proves the OR condition group.
    $this->pushRoutedRequest('entity.queen.collection', '/hivelog/queens', ['name' => 'breeder']);
    $build = \Drupal::entityTypeManager()->getListBuilder('queen')->render();

    $this->assertCount(1, $build['table']['#props']['rows']);
    $this->assertStringContainsString('Q1', (string) $build['table']['#props']['rows'][0]['cells'][0]);
  }

  /**
   * Extracts the rendered Reset button's URL from a list builder's build.
   */
  protected function resetUrl(array $build): string {
    $reset = $build['filter']['filter_actions']['reset']['#props']['url'] ?? NULL;
    $this->assertNotNull($reset, 'Reset button was not rendered.');
    return $reset;
  }

}
