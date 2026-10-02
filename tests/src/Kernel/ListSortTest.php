<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\Product;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests sortable columns on the full collection lists (task 0171).
 *
 * Hives (name and status sortable; Apiary and Breed deliberately not) and
 * Inspections (a numeric and a nullable column) stand in for every list: the
 * mechanism lives once in `HivelogListBuilder`, and each list only declares
 * its `getSortableColumns()` map.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ListSortTest extends KernelTestBase {

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
   * A test apiary.
   */
  protected Apiary $apiary;

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
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('inventory_usage');
    $this->installEntitySchema('product');
    $this->installEntitySchema('hive_inspection');
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'admin', 'label' => 'Admin']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $user = User::create(['name' => 'tester', 'mail' => 'tester@example.com']);
    $user->addRole('admin');
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();
  }

  /**
   * Pushes a request onto the stack, routed through the real router.
   */
  protected function pushRoutedRequest(string $path, array $query = []): void {
    $request = Request::create($path, 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->attributes->add(\Drupal::service('router')->matchRequest($request));
    \Drupal::service('request_stack')->push($request);
  }

  /**
   * Creates hives in the given order (so their ids follow it).
   *
   * @param string[] $names
   *   Hive names, in creation order.
   * @param string $status
   *   Their status.
   */
  protected function makeHives(array $names, string $status = 'active'): void {
    foreach ($names as $name) {
      Hive::create(['name' => $name, 'apiary' => $this->apiary->id(), 'status' => $status])->save();
    }
  }

  /**
   * Renders the Hives list and returns its build.
   */
  protected function hiveList(array $query = []): array {
    $this->pushRoutedRequest('/hivelog/hives', $query);
    return \Drupal::entityTypeManager()->getListBuilder('hive')->render();
  }

  /**
   * The first-column text of each row, in order.
   *
   * @return string[]
   *   Plain-text first cells.
   */
  protected function firstColumn(array $build): array {
    return array_map(
      fn(array $row) => trim(strip_tags((string) $row['cells'][0])),
      $build['table']['#props']['rows']
    );
  }

  /**
   * With no sort the order is unchanged: by id, i.e. creation order.
   */
  public function testDefaultOrderIsUnchangedWithoutSort(): void {
    $this->makeHives(['Charlie', 'Alpha', 'Bravo']);
    $this->assertSame(['Charlie', 'Alpha', 'Bravo'], $this->firstColumn($this->hiveList()));
  }

  /**
   * A sortable column sorts ascending and descending.
   */
  public function testSortAscendingAndDescending(): void {
    $this->makeHives(['Charlie', 'Alpha', 'Bravo']);

    $asc = $this->hiveList(['sort' => 'name', 'order' => 'asc']);
    $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->firstColumn($asc));
    $desc = $this->hiveList(['sort' => 'name', 'order' => 'desc']);
    $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $this->firstColumn($desc));
    // No `order` means ascending.
    $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->firstColumn($this->hiveList(['sort' => 'name'])));
  }

  /**
   * Anything not a sortable header key falls back to the default order.
   *
   * Includes a real header ("breed") whose column is deliberately not
   * sortable, a made-up key, an entity key, array-valued parameters and a
   * junk `order`.
   */
  public function testUnsortableOrBogusSortFallsBackToDefault(): void {
    $this->makeHives(['Charlie', 'Alpha', 'Bravo']);
    $default = ['Charlie', 'Alpha', 'Bravo'];

    foreach (['breed', 'bogus', 'id', 'uuid', 'apiary.entity.name', "name' OR 1=1 --", ''] as $bad) {
      $this->assertSame($default, $this->firstColumn($this->hiveList(['sort' => $bad])), "sort=$bad must be ignored.");
    }
    $array_sort = $this->hiveList(['sort' => ['name']]);
    $this->assertSame($default, $this->firstColumn($array_sort), 'An array sort must be ignored.');
    $array_order = $this->hiveList(['sort' => 'name', 'order' => ['desc']]);
    $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->firstColumn($array_order), 'An array order means ascending.');
    $junk_order = $this->hiveList(['sort' => 'name', 'order' => 'sideways']);
    $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->firstColumn($junk_order));
  }

  /**
   * Sorting and filtering apply together.
   */
  public function testSortAndFilterTogether(): void {
    $this->makeHives(['Charlie', 'Alpha', 'Bravo']);
    $this->makeHives(['Zulu'], 'inactive');

    $build = $this->hiveList(['status' => 'active', 'sort' => 'name', 'order' => 'desc']);
    $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $this->firstColumn($build));
  }

  /**
   * Numeric columns sort numerically; NULLs and ties are stable.
   */
  public function testNumericSortAndNullsAndTies(): void {
    $hive = Hive::create(['name' => 'H', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $hive->save();
    // Created in this order so ids follow it; 100 sorts last numerically
    // but first as text.
    $fixtures = [
      ['2026-01-01', 100.0],
      ['2026-01-02', 9.5],
      ['2026-01-03', NULL],
      ['2026-01-04', 10.5],
      ['2026-01-05', 9.5],
    ];
    foreach ($fixtures as [$date, $weight]) {
      HiveInspection::create(['hive' => $hive->id(), 'inspection_date' => $date, 'weight' => $weight])->save();
    }

    $weights = function (string $order): array {
      $this->pushRoutedRequest('/hivelog/inspections', ['sort' => 'weight', 'order' => $order]);
      $build = \Drupal::entityTypeManager()->getListBuilder('hive_inspection')->render();
      // Date (first cell) identifies the row.
      return array_map(fn(array $row) => trim(strip_tags((string) $row['cells'][0])), $build['table']['#props']['rows']);
    };

    // Ascending: NULL first, then 9.5 (two ties, kept in id order), 10.5, 100.
    $this->assertSame(['2026-01-03', '2026-01-02', '2026-01-05', '2026-01-04', '2026-01-01'], $weights('asc'));
    // Descending is the exact reverse for values; the 9.5 tie stays in id order.
    $this->assertSame(['2026-01-01', '2026-01-04', '2026-01-02', '2026-01-05', '2026-01-03'], $weights('desc'));
  }

  /**
   * The Apiary column sorts by the related apiary's name (asc and desc).
   *
   * Hive names are chosen so that sorting by apiary gives a different order
   * from sorting by hive name, and from creation order.
   */
  public function testApiaryColumnSortsByApiaryName(): void {
    $barn = Apiary::create(['name' => 'Barn']);
    $barn->save();
    $meadow = Apiary::create(['name' => 'Meadow']);
    $meadow->save();
    // Created: M1 (Meadow), T1 (Test Apiary), B1 (Barn), M2 (Meadow).
    foreach ([['M1', $meadow], ['T1', $this->apiary], ['B1', $barn], ['M2', $meadow]] as [$name, $apiary]) {
      Hive::create(['name' => $name, 'apiary' => $apiary->id(), 'status' => 'active'])->save();
    }

    // Barn, Meadow x2 (kept in creation order by the id tie-breaker), Test.
    $asc = $this->hiveList(['sort' => 'apiary', 'order' => 'asc']);
    $this->assertSame(['B1', 'M1', 'M2', 'T1'], $this->firstColumn($asc));
    $desc = $this->hiveList(['sort' => 'apiary', 'order' => 'desc']);
    $this->assertSame(['T1', 'M1', 'M2', 'B1'], $this->firstColumn($desc));
  }

  /**
   * A record whose apiary has gone is still listed when sorting by apiary.
   *
   * The relationship sort must join LEFT, or an orphan would silently vanish
   * from the list (and from its pager) just because someone clicked a header.
   */
  public function testOrphanedRecordStaysListedWhenSortingByApiary(): void {
    $gone = Apiary::create(['name' => 'Gone']);
    $gone->save();
    Hive::create(['name' => 'Orphan', 'apiary' => $gone->id(), 'status' => 'active'])->save();
    Hive::create(['name' => 'Kept', 'apiary' => $this->apiary->id(), 'status' => 'active'])->save();
    // Delete through storage, which does not run the module's delete policy.
    $gone->delete();

    $this->assertSame(['Orphan', 'Kept'], $this->firstColumn($this->hiveList(['sort' => 'apiary', 'order' => 'asc'])), 'NULL sorts first ascending.');
    $this->assertSame(['Kept', 'Orphan'], $this->firstColumn($this->hiveList(['sort' => 'apiary', 'order' => 'desc'])));
  }

  /**
   * Inventory Items, Purchases and Products sort by their Apiary column.
   *
   * These are the lists that mix records from several apiaries.
   */
  public function testApiaryColumnOnInventoryAndProductLists(): void {
    $barn = Apiary::create(['name' => 'Barn']);
    $barn->save();
    $zeta = Apiary::create(['name' => 'Zeta']);
    $zeta->save();

    // Created Zeta first so creation order is the opposite of apiary order.
    $zeta_item = InventoryItem::create([
      'apiary' => $zeta->id(),
      'name' => 'Zeta Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $zeta_item->save();
    $barn_item = InventoryItem::create([
      'apiary' => $barn->id(),
      'name' => 'Barn Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $barn_item->save();
    foreach ([$zeta_item, $barn_item] as $item) {
      InventoryPurchase::create([
        'apiary' => $item->get('apiary')->target_id,
        'item' => $item->id(),
        'purchase_date' => '2026-01-01',
        'quantity' => 1,
        'unit_price' => 1,
      ])->save();
    }
    Product::create(['apiary' => $zeta->id(), 'name' => 'Zeta Honey', 'unit' => 'kg', 'status' => 'active'])->save();
    Product::create(['apiary' => $barn->id(), 'name' => 'Barn Honey', 'unit' => 'kg', 'status' => 'active'])->save();

    $cases = [
      ['inventory_item', '/hivelog/inventory-items', ['Barn Sugar', 'Zeta Sugar']],
      ['inventory_purchase', '/hivelog/inventory-purchases', ['Barn Sugar', 'Zeta Sugar']],
      ['product', '/hivelog/products', ['Barn Honey', 'Zeta Honey']],
    ];
    foreach ($cases as [$type, $path, $ascending]) {
      $rows = function (string $order) use ($type, $path): array {
        $this->pushRoutedRequest($path, ['sort' => 'apiary', 'order' => $order]);
        $build = \Drupal::entityTypeManager()->getListBuilder($type)->render();
        return $this->firstColumn($build);
      };
      $this->assertSame($ascending, $rows('asc'), "$type ascending by apiary");
      $this->assertSame(array_reverse($ascending), $rows('desc'), "$type descending by apiary");
    }
  }

  /**
   * The Apiary Action Log list sorts by its apiary too.
   */
  public function testApiaryColumnOnApiaryActionLogList(): void {
    $barn = Apiary::create(['name' => 'Barn']);
    $barn->save();
    $zeta = Apiary::create(['name' => 'Zeta']);
    $zeta->save();
    $this->installEntitySchema('apiary_action_log');
    foreach ([$zeta, $barn] as $apiary) {
      $action = CalendarAction::create([
        'apiary' => $apiary->id(),
        'title' => 'Action of ' . $apiary->label(),
        'description' => 'Desc.',
        'week_start' => 10,
      ]);
      $action->save();
      ApiaryActionLog::create([
        'apiary' => $apiary->id(),
        'calendar_action' => $action->id(),
        'status' => 'done',
        'year' => 2026,
      ])->save();
    }

    $this->pushRoutedRequest('/hivelog/apiary-action-logs', ['sort' => 'apiary', 'order' => 'asc']);
    $build = \Drupal::entityTypeManager()->getListBuilder('apiary_action_log')->render();
    $apiaries = array_map(fn(array $row) => trim(strip_tags((string) $row['cells'][0])), $build['table']['#props']['rows']);
    // Test Apiary's own seeded rows (none have logs) are absent; ours only.
    $ours = array_values(array_filter($apiaries, fn($name) => in_array($name, ['Barn', 'Zeta'], TRUE)));
    $this->assertSame(['Barn', 'Zeta'], $ours);
  }

  /**
   * The Hive Action Log list sorts by its Hive column (the hive's name).
   *
   * That list mixes the logs of every hive, so Hive is the column people
   * want to group by.
   */
  public function testHiveColumnOnHiveActionLogList(): void {
    $this->installEntitySchema('hive_action_log');
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Shared Action',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $action->save();
    // Created Charlie, Alpha, Bravo; a second Alpha log checks the tie order.
    foreach (['Charlie', 'Alpha', 'Bravo', 'Alpha'] as $name) {
      $hive = Hive::create(['name' => $name, 'apiary' => $this->apiary->id(), 'status' => 'active']);
      $hive->save();
      HiveActionLog::create([
        'hive' => $hive->id(),
        'calendar_action' => $action->id(),
        'status' => 'done',
        'year' => 2026,
      ])->save();
    }

    $hives = function (string $order): array {
      $this->pushRoutedRequest('/hivelog/hive-action-logs', ['sort' => 'hive', 'order' => $order]);
      $build = \Drupal::entityTypeManager()->getListBuilder('hive_action_log')->render();
      return array_map(fn(array $row) => trim(strip_tags((string) $row['cells'][0])), $build['table']['#props']['rows']);
    };
    $this->assertSame(['Alpha', 'Alpha', 'Bravo', 'Charlie'], $hives('asc'));
    $this->assertSame(['Charlie', 'Bravo', 'Alpha', 'Alpha'], $hives('desc'));
  }

  /**
   * Sortable headers link to their next sort and report their state.
   */
  public function testColumnSortsOfferNextDirectionAndState(): void {
    $this->makeHives(['Alpha']);

    $sorts = $this->hiveList()['table']['#props']['column_sorts'];
    $this->assertSame(['Hive', 'Apiary', 'Status'], array_keys($sorts), 'Only the sortable headers (not Breed) carry a sort.');
    $this->assertSame('none', $sorts['Hive']['direction']);
    $this->assertStringContainsString('sort=name', $sorts['Hive']['url']);
    $this->assertStringContainsString('order=asc', $sorts['Hive']['url']);

    $sorts = $this->hiveList(['sort' => 'name', 'order' => 'asc'])['table']['#props']['column_sorts'];
    $this->assertSame('ascending', $sorts['Hive']['direction']);
    $this->assertStringContainsString('order=desc', $sorts['Hive']['url'], 'Clicking the ascending column offers descending.');
    $this->assertSame('none', $sorts['Status']['direction']);

    $sorts = $this->hiveList(['sort' => 'name', 'order' => 'desc'])['table']['#props']['column_sorts'];
    $this->assertSame('descending', $sorts['Hive']['direction']);
    $this->assertStringContainsString('order=asc', $sorts['Hive']['url'], 'Clicking the descending column offers ascending again.');
  }

  /**
   * A new sort keeps the active filters but drops the page number.
   */
  public function testSortLinksKeepFiltersAndDropPage(): void {
    $this->makeHives(['Alpha']);

    $sorts = $this->hiveList(['status' => 'active', 'page' => '2'])['table']['#props']['column_sorts'];
    $url = urldecode(html_entity_decode($sorts['Status']['url']));
    $this->assertStringContainsString('status=active', $url);
    $this->assertStringNotContainsString('page=', $url);
  }

  /**
   * A list with no sortable columns passes no sort prop at all.
   */
  public function testListWithoutSortableColumnsPassesNoSortProp(): void {
    $this->pushRoutedRequest('/hivelog/calendar-actions');
    $build = \Drupal::entityTypeManager()->getListBuilder('calendar_action')->render();
    $this->assertArrayNotHasKey('column_sorts', $build['table']['#props']);
  }

  /**
   * The filter form carries the active sort as hidden inputs; Reset clears it.
   */
  public function testFilterFormKeepsSortAndResetClearsIt(): void {
    $this->makeHives(['Alpha']);

    $build = $this->hiveList(['sort' => 'name', 'order' => 'desc']);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build['filter']);
    $this->assertMatchesRegularExpression('/<input[^>]*name="sort"[^>]*value="name"/', $html);
    $this->assertMatchesRegularExpression('/<input[^>]*name="order"[^>]*value="desc"/', $html);

    $reset_url = $build['filter']['filter_actions']['reset']['#props']['url'];
    $this->assertStringNotContainsString('sort', $reset_url);
    $this->assertStringNotContainsString('order', $reset_url);

    $html = (string) \Drupal::service('renderer')->renderInIsolation($this->hiveList(['status' => 'active'])['filter']);
    $this->assertStringNotContainsString('name="sort"', $html, 'No hidden sort input when no sort is active.');
  }

  /**
   * Paging a sorted list keeps the order and the pager links keep the sort.
   */
  public function testPagingKeepsSort(): void {
    $this->makeHives(['Echo', 'Delta', 'Alpha', 'Charlie', 'Bravo']);
    $list_builder = \Drupal::entityTypeManager()->getListBuilder('hive');
    (new \ReflectionProperty($list_builder, 'limit'))->setValue($list_builder, 2);

    $this->pushRoutedRequest('/hivelog/hives', ['sort' => 'name', 'order' => 'asc']);
    $build = $list_builder->render();
    $this->assertSame(['Alpha', 'Bravo'], $this->firstColumn($build));
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build['pager']);
    $this->assertStringContainsString('sort=name', $html);
    $this->assertStringContainsString('order=asc', $html);

    $this->pushRoutedRequest('/hivelog/hives', ['sort' => 'name', 'order' => 'asc', 'page' => '1']);
    $this->assertSame(['Charlie', 'Delta'], $this->firstColumn($list_builder->render()));
  }

  /**
   * The rendered table marks the sorted header and links the sortable ones.
   */
  public function testRenderedTableHasAriaSortAndSortLinks(): void {
    $this->makeHives(['Alpha']);

    $build = $this->hiveList(['sort' => 'name', 'order' => 'asc']);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build['table']);

    $this->assertSame(1, substr_count($html, 'aria-sort="ascending"'));
    $this->assertSame(2, substr_count($html, 'aria-sort="none"'), 'The other sortable headers report none.');
    $this->assertSame(3, substr_count($html, 'class="hivelog-entity-table__sort"'), 'Three sortable headers are links.');
    $this->assertStringContainsString('hivelog-entity-table--sortable', $html);
    $this->assertStringContainsString('aria-hidden="true"', $html, 'The arrow is decoration.');
    // The Breed header is computed, so plain text rather than a link.
    $this->assertMatchesRegularExpression('/<th[^>]*>\s*Breed\s*<\/th>/', $html);
  }

}
