<?php

declare(strict_types=1);

namespace Drupal\Tests\nexus\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\nexus\Controller\AiProviderConfigController;
use Drupal\nexus\Entity\AiProviderConfig;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the AI Provider Config management UI (task 0104).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class AiProviderConfigManagementUiTest extends KernelTestBase {

  use UserCreationTrait;

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
    $this->installEntitySchema('user');
    $this->installEntitySchema('ai_provider_config');
    $this->installConfig(['system']);
    \Drupal::service('router.builder')->rebuild();

    // Explicitly active: the 'default:user' entity reference selection
    // handler (used by the `uid` owner field's ValidReferenceConstraint,
    // exercised by validateForm() in this test class — unlike every
    // other management-UI test, which only ever calls save() directly
    // and so never triggers it) filters to active users only. An
    // inactive owner would fail reference validation with a misleading
    // "does not exist" message that has nothing to do with the
    // mode-conditional checks these tests actually target.
    $admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com', 'status' => 1]);
    $admin->save();
    $this->setCurrentUser($admin);
  }

  /**
   * Builds the add form's render array for a given set of field values.
   */
  protected function buildAddForm(array $values): array {
    $entity = AiProviderConfig::create($values);
    $form_object = \Drupal::entityTypeManager()->getFormObject('ai_provider_config', 'add');
    $form_object->setEntity($entity);
    $form_state = new FormState();
    return \Drupal::formBuilder()->buildForm($form_object, $form_state);
  }

  /**
   * Tests saving a valid direct_api-mode config through the real form.
   */
  public function testFormSavesDirectApiMode(): void {
    $entity = AiProviderConfig::create([
      'label' => 'Anthropic Direct',
      'mode' => 'direct_api',
      'provider' => 'anthropic',
      'key' => 'anthropic_api_key',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('ai_provider_config', 'add');
    $form_object->setEntity($entity);
    $form_object->save([], new FormState());

    $loaded = AiProviderConfig::load($entity->id());
    $this->assertEquals('direct_api', $loaded->get('mode')->value);
    $this->assertEquals('anthropic', $loaded->get('provider')->value);
    $this->assertEquals('anthropic_api_key', $loaded->get('key')->value);

    $messages = \Drupal::messenger()->all();
    $status_text = implode(' ', array_map('strval', $messages['status'] ?? []));
    $this->assertStringContainsString('has been created', $status_text);
  }

  /**
   * Tests saving a valid custom_endpoint-mode config through the real form.
   */
  public function testFormSavesCustomEndpointMode(): void {
    $entity = AiProviderConfig::create([
      'label' => 'Custom Endpoint',
      'mode' => 'custom_endpoint',
      'endpoint_url' => 'https://example.com/nexus-endpoint',
      'key' => 'custom_endpoint_key',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('ai_provider_config', 'add');
    $form_object->setEntity($entity);
    $form_object->save([], new FormState());

    $loaded = AiProviderConfig::load($entity->id());
    $this->assertEquals('custom_endpoint', $loaded->get('mode')->value);
    $this->assertEquals('https://example.com/nexus-endpoint', $loaded->get('endpoint_url')->value);
  }

  /**
   * Tests validation rejects a direct_api config with no key selected.
   *
   * Confirms the fix for a real bug caught by hand while verifying this
   * task in a browser: before `AiProviderConfigForm::validateForm()`
   * existed, submitting this exact combination threw
   * `AiProviderConfig::preSave()`'s `\InvalidArgumentException` as an
   * uncaught, raw "unexpected error" page instead of a normal inline
   * form error.
   */
  public function testFormValidationRejectsMissingKeyForDirectApiMode(): void {
    $entity = AiProviderConfig::create([
      'label' => 'Missing Key',
      'mode' => 'direct_api',
      'provider' => 'anthropic',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('ai_provider_config', 'add');
    $form_object->setEntity($entity);
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $form_object->validateForm($form, $form_state);

    $this->assertArrayHasKey('key', $form_state->getErrors());
    $this->assertStringContainsString('Key is required', (string) $form_state->getErrors()['key']);
  }

  /**
   * Tests validation rejects a direct_api config with no provider given.
   */
  public function testFormValidationRejectsMissingProviderForDirectApiMode(): void {
    $entity = AiProviderConfig::create([
      'label' => 'Missing Provider',
      'mode' => 'direct_api',
      'key' => 'some_key',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('ai_provider_config', 'add');
    $form_object->setEntity($entity);
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $form_object->validateForm($form, $form_state);

    $this->assertArrayHasKey('provider', $form_state->getErrors());
    $this->assertStringContainsString('Provider is required', (string) $form_state->getErrors()['provider']);
  }

  /**
   * Tests validation rejects a custom_endpoint config with no URL given.
   */
  public function testFormValidationRejectsMissingEndpointUrlForCustomEndpointMode(): void {
    $entity = AiProviderConfig::create([
      'label' => 'Missing Endpoint',
      'mode' => 'custom_endpoint',
      'key' => 'some_key',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('ai_provider_config', 'add');
    $form_object->setEntity($entity);
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $form_object->validateForm($form, $form_state);

    $this->assertArrayHasKey('endpoint_url', $form_state->getErrors());
    $this->assertStringContainsString('Custom Endpoint URL is required', (string) $form_state->getErrors()['endpoint_url']);
  }

  /**
   * Tests validation passes an ai_module config with no key/provider at all.
   *
   * Asserts absence of an error on this class's own three fields
   * specifically (`key`/`provider`/`endpoint_url`), not
   * `$form_state->hasAnyErrors() === FALSE` outright — calling
   * `validateForm()` a second time on a form that was built but never
   * actually submitted (as every test in this class does, deliberately,
   * to reach `validateForm()` directly) can also surface an unrelated
   * `uid` reference-validity error from `ContentEntityForm`'s own
   * parent validation, a pre-existing quirk of this specific testing
   * technique, not something task 0104 introduced or needs to fix.
   */
  public function testFormValidationPassesAiModeWithNoKeyOrProvider(): void {
    $entity = AiProviderConfig::create([
      'label' => 'AI Module, No Key',
      'mode' => 'ai_module',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('ai_provider_config', 'add');
    $form_object->setEntity($entity);
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $form_object->validateForm($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertArrayNotHasKey('key', $errors);
    $this->assertArrayNotHasKey('provider', $errors);
    $this->assertArrayNotHasKey('endpoint_url', $errors);
  }

  /**
   * Tests saving a valid ai_module-mode config — no key/provider needed.
   */
  public function testFormSavesAiModuleMode(): void {
    $entity = AiProviderConfig::create([
      'label' => 'AI Module Config',
      'mode' => 'ai_module',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('ai_provider_config', 'add');
    $form_object->setEntity($entity);
    $status = $form_object->save([], new FormState());

    $this->assertEquals(SAVED_NEW, $status);
    $this->assertNotNull(AiProviderConfig::load($entity->id()));
  }

  /**
   * Tests `provider`/`endpoint_url`/`key` carry the right `#states`.
   *
   * Kernel tests can't exercise real client-side JS, but the `#states`
   * array itself is a real, inspectable part of the render array — this
   * asserts the exact selector/mode-value pairs the form actually
   * builds, not just that some `#states` key is present.
   */
  public function testModeConditionalStatesAreWiredCorrectly(): void {
    $form = $this->buildAddForm(['label' => 'States Probe', 'mode' => 'direct_api']);

    $this->assertEquals(
      ['visible' => [':input[name="mode"]' => ['value' => 'direct_api']]],
      $form['provider']['widget'][0]['value']['#states']
    );
    $this->assertEquals(
      ['visible' => [':input[name="mode"]' => ['value' => 'custom_endpoint']]],
      $form['endpoint_url']['widget'][0]['value']['#states']
    );
    $this->assertEquals(
      [
        'visible' => [
          ':input[name="mode"]' => [
            ['value' => 'direct_api'],
            ['value' => 'custom_endpoint'],
          ],
        ],
      ],
      $form['key']['widget'][0]['value']['#states']
    );
  }

  /**
   * Tests `key`'s widget is Key module's own `key_select` element.
   */
  public function testKeyFieldUsesKeySelectWidget(): void {
    $form = $this->buildAddForm(['label' => 'Key Widget Probe', 'mode' => 'direct_api']);
    $this->assertEquals('key_select', $form['key']['widget'][0]['value']['#type']);
  }

  /**
   * Tests the canonical page's live AI-module-status check.
   *
   * The `ai` module is not in this test's $modules list (nexus's own
   * dependency on it is soft, not hard — see AiModuleProviderCaller's
   * own docblock), so `moduleExists('ai')` genuinely returns FALSE here,
   * exactly like a real site that never installed it. This is a real
   * exercise of the "not installed" branch, not a fake/stub of it.
   */
  public function testCanonicalPageShowsAiModuleNotInstalledWarning(): void {
    $config = AiProviderConfig::create(['label' => 'AI Module Config', 'mode' => 'ai_module']);
    $config->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(AiProviderConfigController::class);
    $build = $controller->view($config);

    $this->assertArrayHasKey('ai_module_status', $build);
    $rendered = \Drupal::service('renderer')->renderInIsolation($build['ai_module_status']);
    $this->assertStringContainsString('not installed', (string) $rendered);
  }

  /**
   * Tests the AI-module-status block is absent for the other two modes.
   *
   * It's only meaningful for `ai_module` mode — showing it regardless
   * would be misleading for a config that never intends to use that
   * module at all.
   */
  public function testAiModuleStatusAbsentForOtherModes(): void {
    $config = AiProviderConfig::create([
      'label' => 'Direct API Config',
      'mode' => 'direct_api',
      'provider' => 'anthropic',
      'key' => 'some_key',
    ]);
    $config->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(AiProviderConfigController::class);
    $build = $controller->view($config);

    $this->assertArrayNotHasKey('ai_module_status', $build);
  }

}
