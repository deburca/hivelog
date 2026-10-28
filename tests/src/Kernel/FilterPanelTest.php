<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\hivelog\Form\HivelogHiveFilterForm;
use Drupal\hivelog\HivelogFilterPanel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the collapsible panel around every filter form (task 0240).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class FilterPanelTest extends KernelTestBase {

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
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('apiary_membership');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('queen');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * Renders the Hives filter form for a request with the given query.
   */
  protected function renderHiveFilter(array $query = []): string {
    $request = Request::create('/hivelog/hives', 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);

    $form = \Drupal::formBuilder()->getForm(HivelogHiveFilterForm::class);
    return (string) \Drupal::service('renderer')->renderInIsolation($form);
  }

  /**
   * The form sits inside a panel whose bar is its checkbox's label.
   */
  public function testFilterFormIsWrappedInPanel(): void {
    $html = $this->renderHiveFilter();

    $this->assertStringContainsString('<div class="hivelog-filter-panel">', $html);
    $this->assertMatchesRegularExpression(
      '/<input type="checkbox" id="(hivelog-filter-panel-[a-z0-9-]+)" class="hivelog-filter-panel__toggle">'
      . '<label for="\1" class="hivelog-filter-panel__label">Filters<\/label>/',
      $html,
      'The checkbox comes first and its label directly after it, so CSS can reach the form with ~.'
    );
    $this->assertLessThan(strpos($html, '<form'), strpos($html, 'hivelog-filter-panel__toggle'), 'The toggle precedes the form.');
    $this->assertStringContainsString('</form></div>', preg_replace('/>\s+</', '><', $html), 'The panel closes right after the form.');
    $this->assertStringNotContainsString('hivelog-filter-panel__count', $html, 'No filter set, no badge.');
  }

  /**
   * The bar says how many filters are set, so a closed panel explains a short list.
   */
  public function testBarCountsTheFiltersThatAreSet(): void {
    $html = $this->renderHiveFilter(['status' => 'active']);
    $this->assertStringContainsString('<span class="hivelog-filter-panel__count">1 active</span>', $html);

    $html = $this->renderHiveFilter(['status' => 'active', 'name' => 'VV']);
    $this->assertStringContainsString('<span class="hivelog-filter-panel__count">2 active</span>', $html);
  }

  /**
   * Only filter fields count, not the buttons or the hidden sort state.
   */
  public function testActiveCountIgnoresActionsAndSortState(): void {
    $form = [
      '#attributes' => ['class' => ['hivelog-filter-form']],
      'filters' => [
        '#type' => 'container',
        'status' => ['#type' => 'select', '#default_value' => 'active'],
        'name' => ['#type' => 'textfield', '#default_value' => ''],
        'week' => ['#type' => 'number', '#default_value' => 0],
        'range' => [
          '#type' => 'container',
          'from' => ['#type' => 'date', '#default_value' => '2026-05-01'],
        ],
      ],
      'filter_actions' => [
        'submit' => ['#type' => 'submit', '#value' => 'Filter', '#default_value' => 'x'],
      ],
      'sort_state_sort' => ['#type' => 'html_tag', '#attributes' => ['value' => 'name']],
    ];

    $this->assertSame(3, HivelogFilterPanel::activeCount($form), 'status, the 0 week and the nested date.');
  }

  /**
   * A form that is not a filter form is left alone.
   */
  public function testOtherFormsAreNotWrapped(): void {
    $form = ['#attributes' => ['class' => ['hivelog-entity-form']]];
    HivelogFilterPanel::wrap($form, 'some_form');

    $this->assertArrayNotHasKey('#prefix', $form);
    $this->assertArrayNotHasKey('#suffix', $form);
  }

}
