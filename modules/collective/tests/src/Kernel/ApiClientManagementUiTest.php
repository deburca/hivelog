<?php

declare(strict_types=1);

namespace Drupal\Tests\collective\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\collective\Controller\InsightAgentApiController;
use Drupal\collective\Entity\InsightAgent;
use Drupal\collective\Form\InsightAgentRegenerateTokenForm;
use Drupal\hivelog\Entity\Apiary;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the Insight Agent management UI + consent toggle (task 0091).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class InsightAgentManagementUiTest extends KernelTestBase {

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
    'hivelog',
    'collective',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('insight_agent');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system']);
    \Drupal::service('router.builder')->rebuild();

    // An admin user, so InsightAgentRegenerateTokenForm's own access
    // check (administer hivelog required) passes — none of these tests
    // are about access denial, so a straightforwardly-authorized current
    // user is the right default here.
    $admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $admin->save();
    $this->setCurrentUser($admin);
  }

  /**
   * Tests the add form shows the token once via a status message.
   *
   * And that it is not recoverable in plaintext after a fresh load.
   */
  public function testTokenShownOnceOnCreateAndNotRecoverableAfter(): void {
    $agent = InsightAgent::create(['label' => 'Test Agent']);

    $form_object = \Drupal::entityTypeManager()->getFormObject('insight_agent', 'add');
    $form_object->setEntity($agent);
    $form_object->save([], new FormState());

    $messages = \Drupal::messenger()->all();
    $warning_text = implode(' ', array_map('strval', $messages['warning'] ?? []));
    $this->assertStringContainsString('will not be shown again', $warning_text);
    $this->assertStringContainsString($agent->getPlainTextToken(), $warning_text);

    \Drupal::messenger()->deleteAll();

    $reloaded = InsightAgent::load($agent->id());
    $this->assertNull($reloaded->getPlainTextToken());
  }

  /**
   * Tests regenerating invalidates the previous token immediately.
   *
   * A follow-up API call using the stale token is rejected with 401.
   */
  public function testRegenerateInvalidatesPreviousToken(): void {
    $agent = InsightAgent::create(['label' => 'Test Agent']);
    $agent->save();
    $stale_token = $agent->getPlainTextToken();

    $form_object = new InsightAgentRegenerateTokenForm();
    $form_state = new FormState();
    $form = $form_object->buildForm([], $form_state, $agent);
    $form_object->submitForm($form, $form_state);

    $messages = \Drupal::messenger()->all();
    $warning_text = implode(' ', array_map('strval', $messages['warning'] ?? []));
    $this->assertStringContainsString('will not be shown again', $warning_text);

    $api_controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(InsightAgentApiController::class);
    $request = Request::create('/hivelog/api/hive-insights/contexts', 'GET');
    $request->headers->set('Authorization', 'Bearer ' . $stale_token);

    $response = $api_controller->contexts($request);
    $this->assertEquals(401, $response->getStatusCode());
  }

  /**
   * Tests `ai_insights_enabled` persists correctly through the apiary form.
   */
  public function testAiInsightsEnabledTogglePersists(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $this->assertFalse((bool) Apiary::load($apiary->id())->get('ai_insights_enabled')->value);

    $form_object = \Drupal::entityTypeManager()->getFormObject('apiary', 'edit');
    $form_object->setEntity($apiary);
    $apiary->set('ai_insights_enabled', TRUE);
    $form_object->save([], new FormState());

    $this->assertTrue((bool) Apiary::load($apiary->id())->get('ai_insights_enabled')->value);
  }

  /**
   * Tests the data-sharing disclosure appears on the apiary edit form.
   */
  public function testDisclosureAppearsOnApiaryEditForm(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();

    $form = \Drupal::service('entity.form_builder')->getForm($apiary, 'edit');

    $this->assertArrayHasKey('ai_insights_enabled_disclosure', $form);
    $rendered = \Drupal::service('renderer')->renderInIsolation($form['ai_insights_enabled_disclosure']);
    $this->assertStringContainsString('insight agent', (string) $rendered);
    $this->assertStringContainsString('Never shared', (string) $rendered);
  }

  /**
   * Tests the disclosure also appears on the apiary add form.
   */
  public function testDisclosureAppearsOnApiaryAddForm(): void {
    $apiary = Apiary::create(['name' => 'New Apiary']);
    $form = \Drupal::service('entity.form_builder')->getForm($apiary, 'add');
    $this->assertArrayHasKey('ai_insights_enabled_disclosure', $form);
  }

}
