<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\hivelog\Controller\QueenController;
use Drupal\hivelog\Entity\Queen;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the shared prose note block (task 0265).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogProseBlockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'datetime', 'options', 'file', 'image', 'geofield', 'hivelog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('apiary_membership');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('queen');
  }

  /**
   * Calls one of the trait's protected block builders on a real controller.
   */
  protected function call(string $method, mixed ...$args): array {
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(QueenController::class);
    return (new \ReflectionMethod($controller, $method))->invoke($controller, ...$args);
  }

  /**
   * Tests empty text gives no block.
   */
  public function testEmptyGivesNoBlock(): void {
    $this->assertSame([], $this->call('buildProseBlock', 'Notes', ''));
    $this->assertSame([], $this->call('buildProseBlock', 'Notes', "  \n "));
    $this->assertSame([], $this->call('buildProseBlock', 'Notes', []));
  }

  /**
   * Tests text is escaped, keeps its line breaks and sits under an H2.
   */
  public function testTextIsEscapedAndKeepsLineBreaks(): void {
    $build = $this->call('buildProseBlock', 'Notes', "Queen <b>seen</b>\nEggs present");
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('<h2>Notes</h2>', $html);
    $this->assertStringContainsString('hivelog-prose', $html);
    $this->assertStringContainsString('Queen &lt;b&gt;seen&lt;/b&gt;<br />', $html);
    $this->assertStringNotContainsString('<b>seen</b>', $html);
    $this->assertContains('hivelog/prose', $build['#attached']['library']);
  }

  /**
   * Tests a render array body (a bullet list) is kept as given.
   */
  public function testRenderArrayBodyIsKept(): void {
    $build = $this->call('buildProseBlock', 'Description', ['#markup' => '<ul><li>One</li></ul>']);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('<li>One</li>', $html);
  }

  /**
   * Tests a field block is empty for an empty field and shows a set one.
   */
  public function testFieldBlock(): void {
    $queen = Queen::create(['name' => 'Q', 'status' => 'active']);
    $this->assertSame([], $this->call('buildFieldProseBlock', 'Notes', $queen, 'notes'));
    $queen->set('notes', "Windy site\nGentle");
    $html = (string) \Drupal::service('renderer')->renderInIsolation($this->call('buildFieldProseBlock', 'Notes', $queen, 'notes'));
    $this->assertStringContainsString('Windy site<br />', $html);
  }

}
