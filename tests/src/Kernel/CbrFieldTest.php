<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the per-user Central Beehive Registration (CBR) number field.
 *
 * Issue #59: a CBR field lives on the user entity and is editable from the
 * standard user form. The apiary list shows each apiary owner's CBR in its
 * first column (covered here); the current user's own CBR summary line
 * moved to the dashboard (see DashboardTest, ADR-0057).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class CbrFieldTest extends KernelTestBase {

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
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_inspection');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * The CBR field is registered as a base field on the user entity.
   */
  public function testCbrBaseFieldDefinition(): void {
    $definitions = \Drupal::service('entity_field.manager')
      ->getBaseFieldDefinitions('user');

    $this->assertArrayHasKey('cbr_number', $definitions);
    $field = $definitions['cbr_number'];
    $this->assertSame('string', $field->getType());
    $this->assertSame(64, $field->getSetting('max_length'));
    $this->assertFalse($field->isRequired());
    $this->assertSame(1, $field->getCardinality());
    $this->assertSame('hivelog', $field->getProvider());
  }

  /**
   * CBR values round-trip through user save/load.
   */
  public function testCbrRoundTrip(): void {
    $user = User::create([
      'name' => 'beekeeper',
      'mail' => 'beekeeper@example.com',
      'cbr_number' => 'IE-0001',
    ]);
    $user->save();

    $reloaded = User::load($user->id());
    $this->assertSame('IE-0001', $reloaded->get('cbr_number')->value);
  }

  /**
   * The list builder header is CBR, Name, Location, Owner — in that order.
   */
  public function testListBuilderHeaderOrder(): void {
    $list_builder = \Drupal::entityTypeManager()->getListBuilder('apiary');
    $header = $list_builder->buildHeader();
    $keys = array_keys($header);
    // Drop trailing 'operations' (or other parent::buildHeader() keys).
    $leading = array_slice($keys, 0, 4);
    $this->assertSame(['cbr', 'name', 'location', 'owner'], $leading);
  }

  /**
   * Each apiary row shows the owner's CBR or an em-dash fallback.
   */
  public function testListBuilderRowCbrColumn(): void {
    $with_cbr = User::create([
      'name' => 'with-cbr',
      'mail' => 'with@example.com',
      'cbr_number' => 'IE-1234',
    ]);
    $with_cbr->save();

    $without_cbr = User::create([
      'name' => 'without-cbr',
      'mail' => 'without@example.com',
    ]);
    $without_cbr->save();

    $apiary_with = Apiary::create([
      'name' => 'With CBR',
      'uid' => $with_cbr->id(),
    ]);
    $apiary_with->save();

    $apiary_without = Apiary::create([
      'name' => 'Without CBR',
      'uid' => $without_cbr->id(),
    ]);
    $apiary_without->save();

    $list_builder = \Drupal::entityTypeManager()->getListBuilder('apiary');
    $row_with = $list_builder->buildRow($apiary_with);
    $row_without = $list_builder->buildRow($apiary_without);

    $this->assertSame('IE-1234', $row_with['cbr']);
    $this->assertSame('—', $row_without['cbr']);
  }

  /**
   * The apiary list links to the queen collection.
   *
   * Issue: the HiveLog menu moved into the front-end `main` menu
   * (`hivelog.links.menu.yml`), where the default one-level-deep menu
   * block silently hides the "Queens" child link. An explicit in-page
   * link on the apiary list page means the queen collection stays
   * reachable regardless of menu block configuration.
   */
  public function testLandingPageLinksToQueenCollection(): void {
    $user = User::create([
      'name' => 'queens-link-tester',
      'mail' => 'queens-link-tester@example.com',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $build = \Drupal::entityTypeManager()->getListBuilder('apiary')->render();
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('View all Queens', $html);
    $this->assertStringContainsString('/hivelog/queens', $html);
  }

}
