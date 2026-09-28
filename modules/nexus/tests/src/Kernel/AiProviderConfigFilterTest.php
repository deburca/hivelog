<?php

declare(strict_types=1);

namespace Drupal\Tests\nexus\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\nexus\Entity\AiProviderConfig;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests filters on the AI Provider Configs list (task 0157).
 *
 * `/hivelog/ai-provider-configs` (previously unfiltered) now carries
 * `AiProviderConfigFilterForm`, full-page-only, mirroring task 0132/0155's
 * own established shape.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class AiProviderConfigFilterTest extends KernelTestBase {

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
    'key',
    'hivelog',
    'collective',
    'nexus',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('ai_provider_config');
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
   * Tests the list filters by mode and Reset clears the query string.
   */
  public function testListModeFilterAndReset(): void {
    AiProviderConfig::create([
      'label' => 'Direct Config',
      'mode' => 'direct_api',
      'provider' => 'anthropic',
      'key' => 'test_key',
    ])->save();
    AiProviderConfig::create([
      'label' => 'Custom Config',
      'mode' => 'custom_endpoint',
      'endpoint_url' => 'https://example.com/ai',
      'key' => 'test_key',
    ])->save();

    $this->pushRoutedRequest('entity.ai_provider_config.collection', '/hivelog/ai-provider-configs', ['mode' => 'custom_endpoint']);
    $build = \Drupal::entityTypeManager()->getListBuilder('ai_provider_config')->render();

    $this->assertCount(1, $build['table']['#props']['rows']);
    $this->assertStringContainsString('Custom Config', (string) $build['table']['#props']['rows'][0]['cells'][0]);

    $reset_url = $this->resetUrl($build);
    $this->assertStringContainsString('/hivelog/ai-provider-configs', $reset_url);
    $this->assertStringNotContainsString('mode', $reset_url);
  }

  /**
   * Tests the list filters by enabled and by provider (LIKE).
   */
  public function testListEnabledAndProviderFilter(): void {
    AiProviderConfig::create([
      'label' => 'Anthropic Config',
      'mode' => 'direct_api',
      'provider' => 'anthropic',
      'key' => 'test_key',
      'enabled' => TRUE,
    ])->save();
    AiProviderConfig::create([
      'label' => 'Disabled OpenAI Config',
      'mode' => 'direct_api',
      'provider' => 'openai',
      'key' => 'test_key',
      'enabled' => FALSE,
    ])->save();

    $this->pushRoutedRequest('entity.ai_provider_config.collection', '/hivelog/ai-provider-configs', ['enabled' => '1']);
    $build = \Drupal::entityTypeManager()->getListBuilder('ai_provider_config')->render();
    $this->assertCount(1, $build['table']['#props']['rows']);

    $this->pushRoutedRequest('entity.ai_provider_config.collection', '/hivelog/ai-provider-configs', ['provider' => 'open']);
    $build = \Drupal::entityTypeManager()->getListBuilder('ai_provider_config')->render();
    $this->assertCount(1, $build['table']['#props']['rows']);
    $this->assertStringContainsString('Disabled OpenAI Config', (string) $build['table']['#props']['rows'][0]['cells'][0]);
  }

  /**
   * Tests the list's empty state distinguishes filtered from unfiltered.
   */
  public function testListEmptyStateDistinguishesFilteredFromUnfiltered(): void {
    AiProviderConfig::create([
      'label' => 'Anthropic Config',
      'mode' => 'direct_api',
      'provider' => 'anthropic',
      'key' => 'test_key',
    ])->save();

    $this->pushRoutedRequest('entity.ai_provider_config.collection', '/hivelog/ai-provider-configs', ['provider' => 'ZZZNoMatch']);
    $build = \Drupal::entityTypeManager()->getListBuilder('ai_provider_config')->render();

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
