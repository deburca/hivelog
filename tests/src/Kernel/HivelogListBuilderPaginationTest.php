<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests HivelogListBuilder::render() actually paginates (task 0126).
 *
 * Before this task, only `ApiaryListBuilder` (`$limit = 20`) output a
 * `#type => 'pager'` element at all — every other list builder's custom
 * `render()` silently dropped it, and the five list builders that fell
 * back to core's plain `#type => 'table'` never overrode `render()` in
 * the first place, so their pager was equally absent even though core's
 * `$limit` default of 50 was still being applied at the query level.
 * Past the limit, rows past the cut simply never appeared, with nothing
 * in the UI to indicate more existed. `HiveListBuilder` (a former
 * core-table builder, default `$limit = 50`) is used here alongside
 * `ApiaryListBuilder` (a former SDC-table builder, custom `$limit = 20`)
 * so both previously-different page shapes are proven fixed.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogListBuilderPaginationTest extends KernelTestBase {

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

    // The first user is uid 1 (superuser), so list-builder access passes.
    $user = User::create(['name' => 'tester', 'mail' => 'tester@example.com']);
    $user->save();
    \Drupal::currentUser()->setAccount($user);
  }

  /**
   * Pushes a request with the given query parameters onto the request stack.
   */
  protected function pushRequestWithQuery(array $query): void {
    $request = Request::create('/hivelog/apiaries', 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
  }

  /**
   * Tests ApiaryListBuilder (custom $limit = 20) paginates correctly.
   */
  public function testApiaryListPaginatesAtCustomLimit(): void {
    for ($i = 1; $i <= 21; $i++) {
      Apiary::create(['name' => sprintf('Apiary %02d', $i), 'uid' => 1])->save();
    }

    $list_builder = \Drupal::entityTypeManager()->getListBuilder('apiary');

    $this->assertCount(20, $list_builder->load(), 'First page holds exactly $limit rows.');

    $build = $list_builder->render();
    $this->assertArrayHasKey('pager', $build, 'A pager element is present.');
    $this->assertEquals('pager', $build['pager']['#type']);
    $this->assertCount(20, $build['table']['#props']['rows']);

    $this->pushRequestWithQuery(['page' => '1']);
    $this->assertCount(1, $list_builder->load(), 'Second page holds the remaining row.');
  }

  /**
   * Tests HiveListBuilder (default $limit = 50) paginates correctly.
   *
   * Before task 0126 this list builder had no pager at all — the 51st
   * row would have silently vanished.
   */
  public function testHiveListPaginatesAtDefaultLimit(): void {
    $apiary = Apiary::create(['name' => 'Big Apiary', 'uid' => 1]);
    $apiary->save();
    for ($i = 1; $i <= 51; $i++) {
      Hive::create([
        'name' => sprintf('Hive %03d', $i),
        'apiary' => $apiary->id(),
        'status' => 'active',
        'uid' => 1,
      ])->save();
    }

    $list_builder = \Drupal::entityTypeManager()->getListBuilder('hive');

    $this->assertCount(50, $list_builder->load(), 'First page holds exactly $limit rows.');

    $build = $list_builder->render();
    $this->assertArrayHasKey('pager', $build, 'A pager element is present.');
    $this->assertEquals('pager', $build['pager']['#type']);
    $this->assertCount(50, $build['table']['#props']['rows']);

    $this->pushRequestWithQuery(['page' => '1']);
    $this->assertCount(1, $list_builder->load(), 'Second page holds the remaining row — nothing is silently dropped.');
  }

}
