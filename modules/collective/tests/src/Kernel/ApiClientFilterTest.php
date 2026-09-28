<?php

declare(strict_types=1);

namespace Drupal\Tests\collective\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\collective\Entity\ApiClient;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests filters on the API Clients list (task 0158).
 *
 * `/hivelog/api-clients` (previously unfiltered) now carries
 * `ApiClientFilterForm`, full-page-only, mirroring task 0132/0155/0157's
 * own established shape.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ApiClientFilterTest extends KernelTestBase {

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
    'collective',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('api_client');
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
   * Tests the list filters by enabled and Reset clears the query string.
   */
  public function testListEnabledFilterAndReset(): void {
    ApiClient::create(['label' => 'Production Client', 'enabled' => TRUE])->save();
    ApiClient::create(['label' => 'Retired Client', 'enabled' => FALSE])->save();

    $this->pushRoutedRequest('entity.api_client.collection', '/hivelog/api-clients', ['enabled' => '0']);
    $build = \Drupal::entityTypeManager()->getListBuilder('api_client')->render();

    $this->assertCount(1, $build['table']['#props']['rows']);
    $this->assertStringContainsString('Retired Client', (string) $build['table']['#props']['rows'][0]['cells'][0]);

    $reset_url = $this->resetUrl($build);
    $this->assertStringContainsString('/hivelog/api-clients', $reset_url);
    $this->assertStringNotContainsString('enabled', $reset_url);
  }

  /**
   * Tests the list filters by label (LIKE).
   */
  public function testListLabelFilter(): void {
    ApiClient::create(['label' => 'Nanoprobe Ingestion'])->save();
    ApiClient::create(['label' => 'Beekeeper Script'])->save();

    $this->pushRoutedRequest('entity.api_client.collection', '/hivelog/api-clients', ['label' => 'Nanoprobe']);
    $build = \Drupal::entityTypeManager()->getListBuilder('api_client')->render();

    $this->assertCount(1, $build['table']['#props']['rows']);
    $this->assertStringContainsString('Nanoprobe Ingestion', (string) $build['table']['#props']['rows'][0]['cells'][0]);
  }

  /**
   * Tests the list's empty state distinguishes filtered from unfiltered.
   */
  public function testListEmptyStateDistinguishesFilteredFromUnfiltered(): void {
    ApiClient::create(['label' => 'Production Client'])->save();

    $this->pushRoutedRequest('entity.api_client.collection', '/hivelog/api-clients', ['label' => 'ZZZNoMatch']);
    $build = \Drupal::entityTypeManager()->getListBuilder('api_client')->render();

    $this->assertCount(0, $build['table']['#props']['rows']);
    $this->assertStringContainsString('match the current filters', $build['table']['#props']['empty_message']);
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
