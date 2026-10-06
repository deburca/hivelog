<?php

/**
 * @file
 * Seeds the demo site with believable records owned by the reviewer account.
 *
 * Run by `ddev demo-setup` (drush php:script scripts/seed.php) after the modules are
 * enabled and the `reviewer` user exists. Everything is invented. The `assimilate` module has
 * already made a demo apiary, hive, inspection and two mock sensors when it was enabled, with no
 * owner; this script gives them to the reviewer (the app only shows what its signed-in user
 * owns), renames them, and adds hives, queens, inspections with photos, queen observations and
 * seasonal jobs so that every screen of the app has something real to show.
 *
 * Writing goes through the entity API as user 1, because creating a child record needs update
 * access to its parent and the script has no logged-in user of its own. Each record is
 * validated before it is saved, so a rule change in HiveLog fails here, loudly.
 */

use Drupal\assimilate\DemoDataProvisioner;
use Drupal\Core\File\FileExists;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveComponent;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Entity\QueenObservation;
use Drupal\user\Entity\User;

$reviewer = user_load_by_name('reviewer');
if (!$reviewer) {
  throw new \RuntimeException('Create the reviewer user first (ddev demo-setup does).');
}
$uid = (int) $reviewer->id();
\Drupal::service('account_switcher')->switchTo(User::load(1));
mt_srand(2026);

/**
 * Validates an entity, then saves it.
 */
function demo_save($entity) {
  $violations = $entity->validate();
  if ($violations->count()) {
    $messages = [];
    foreach ($violations as $violation) {
      $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
    }
    throw new \RuntimeException($entity->getEntityTypeId() . ' is invalid: ' . implode('; ', $messages));
  }
  $entity->save();
  return $entity;
}

/**
 * A date this many days ago, as Y-m-d.
 */
function demo_date(int $days_ago): string {
  return (new \DateTimeImmutable('today'))->modify("-$days_ago days")->format('Y-m-d');
}

/**
 * Picks one of the given values, deterministically (the seed is fixed).
 */
function demo_pick(array $values) {
  return $values[mt_rand(0, count($values) - 1)];
}

/**
 * Draws a simple comb-patterned picture and stores it as a permanent file.
 */
function demo_photo(string $name, string $caption, int $uid) {
  $w = 1200;
  $h = 900;
  $img = imagecreatetruecolor($w, $h);
  for ($y = 0; $y < $h; $y++) {
    $t = $y / $h;
    $c = imagecolorallocate($img, (int) (230 - 60 * $t), (int) (170 - 50 * $t), (int) (60 - 20 * $t));
    imageline($img, 0, $y, $w, $y, $c);
  }
  $line = imagecolorallocate($img, 120, 80, 20);
  $fill = imagecolorallocatealpha($img, 255, 214, 120, 70);
  $r = 70;
  for ($row = -1; $row < $h / ($r * 1.5) + 1; $row++) {
    for ($col = -1; $col < $w / ($r * 1.74) + 1; $col++) {
      $cx = $col * $r * 1.74 + ($row % 2 ? $r * 0.87 : 0);
      $cy = $row * $r * 1.5;
      $pts = [];
      for ($i = 0; $i < 6; $i++) {
        $a = deg2rad(60 * $i - 90);
        $pts[] = (int) ($cx + $r * 0.92 * cos($a));
        $pts[] = (int) ($cy + $r * 0.92 * sin($a));
      }
      imagefilledpolygon($img, $pts, $fill);
      imagepolygon($img, $pts, $line);
    }
  }
  $white = imagecolorallocate($img, 255, 255, 255);
  imagefilledrectangle($img, 0, $h - 90, $w, $h, imagecolorallocate($img, 40, 30, 10));
  imagestring($img, 5, 30, $h - 60, $caption . ' (demo picture)', $white);
  ob_start();
  imagejpeg($img, NULL, 82);
  $data = ob_get_clean();
  imagedestroy($img);
  $dir = 'public://demo';
  \Drupal::service('file_system')->prepareDirectory($dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
  $file = \Drupal::service('file.repository')->writeData($data, "$dir/$name.jpg", FileExists::Replace);
  $file->setOwnerId($uid)->save();
  return $file;
}

$state = \Drupal::state();

// 1. The apiary and the first hive, made by assimilate: give them to the reviewer.
$apiary = Apiary::load($state->get(DemoDataProvisioner::DEMO_APIARY_ID_STATE_KEY));
$hive1 = Hive::load($state->get(DemoDataProvisioner::DEMO_HIVE_ID_STATE_KEY));
if (!$apiary || !$hive1) {
  throw new \RuntimeException('assimilate has not provisioned its demo apiary and hive.');
}
$apiary->set('name', 'Heathland Apiary')
  ->set('uid', $uid)
  ->set('location', 'On the edge of the heath, north of Copenhagen (demo)')
  ->set('geolocation', 'POINT (12.4690 55.7240)')
  ->set('notes', 'A demonstration apiary with invented records. Anything you record here is removed when the demo is reset.');
demo_save($apiary);

$hive1->set('name', 'Hive 1 - Heather')->set('uid', $uid)
  ->set('hive_type', 'langstroth')->set('hive_material', 'wood')->set('temperament', 'calm')
  ->set('notes', 'The strongest colony; on a weight scale.');
demo_save($hive1);

$hives = [$hive1];
foreach ([
  ['Hive 2 - Clover', 'norwegian', 'wood', 'moderate', 'Swarmed once in June; requeened since.'],
  ['Hive 3 - Nuc', 'normal', 'styrofoam', 'calm', 'A small nucleus colony made in the spring.'],
] as [$name, $type, $material, $temperament, $notes]) {
  $hives[] = demo_save(Hive::create([
    'name' => $name, 'apiary' => $apiary->id(), 'status' => 'active', 'uid' => $uid,
    'hive_type' => $type, 'hive_material' => $material, 'temperament' => $temperament, 'notes' => $notes,
  ]));
}

// 2. Queens: an older, retired one in the first hive, and an active one in each.
demo_save(Queen::create([
  'name' => 'Old Queen (2024)', 'origin' => 'Local breeder', 'queen_year' => 2024, 'breed' => 'buckfast',
  'temperament' => 'calm', 'hive' => $hive1->id(), 'status' => 'inactive', 'uid' => $uid,
  'introduction_date' => demo_date(700), 'notes' => 'Replaced after two good seasons.',
]));
$queens = [];
foreach ([
  [$hives[0], 'Ingrid', 2025, 'buckfast', 'calm', 'Local breeder', 240],
  [$hives[1], 'Freja', 2026, 'carniolan', 'moderate', 'Bought as a mated queen', 120],
  [$hives[2], 'Astrid', 2026, 'buckfast', 'calm', 'Raised in the apiary', 60],
] as [$hive, $name, $year, $breed, $temperament, $origin, $age]) {
  $queens[] = demo_save(Queen::create([
    'name' => $name, 'origin' => $origin, 'queen_year' => $year, 'breed' => $breed, 'temperament' => $temperament,
    'hive' => $hive->id(), 'status' => 'active', 'uid' => $uid, 'introduction_date' => demo_date($age),
  ]));
}

// 3. Inspections, newest last in each list; a few carry a photo.
$notes = [
  'Plenty of capped brood and fresh eggs.', 'Calm and busy; the nectar flow is on.',
  'Added a super; the frames at the edges are being drawn out.', 'Queen seen on the third frame.',
  'Cleaned the floor and checked the entrance reducer.', 'Heavy with stores; no feeding needed.',
];
$plans = [
  [0, [49, 42, 35, 28, 21, 14, 7]],
  [1, [45, 30, 16, 5]],
  [2, [38, 12]],
];
$photo_for = ['0:7' => 'Brood frame', '1:16' => 'Entrance', '0:2' => 'Heather honey'];
foreach ($plans as [$index, $days]) {
  foreach ($days as $ago) {
    $values = [
      'hive' => $hives[$index]->id(), 'uid' => $uid, 'inspection_date' => demo_date($ago),
      'queen_seen' => (bool) mt_rand(0, 1), 'eggs_seen' => TRUE, 'queen_cells' => ($index === 2 && $ago === 12),
      'brood_pattern' => demo_pick(['good', 'good', 'good', 'fair']),
      'honey_stores' => demo_pick(['abundant', 'adequate', 'adequate']),
      'pollen_stores' => demo_pick(['abundant', 'adequate']),
      'temperament' => demo_pick(['calm', 'calm', 'moderate']),
      'population' => $index === 2 ? 'moderate' : demo_pick(['strong', 'strong', 'moderate']),
      'varroa_check' => ($ago % 14 === 0), 'disease_signs' => 'none',
      'weight' => round(18 + mt_rand(0, 120) / 10, 1), 'fed' => FALSE, 'supers' => $index === 0 ? mt_rand(1, 3) : 1,
      'notes' => demo_pick($notes),
    ];
    if ($values['varroa_check']) {
      $values['varroa_count'] = mt_rand(1, 6);
    }
    if ($index === 2 && $ago === 12) {
      $values['action_taken'] = 'Removed two queen cells to keep the nucleus together.';
    }
    $inspection = HiveInspection::create($values);
    $key = "$index:$ago";
    if (isset($photo_for[$key])) {
      $file = demo_photo('inspection-' . str_replace(':', '-', $key), $photo_for[$key], $uid);
      $inspection->set('images', [['target_id' => $file->id(), 'alt' => $photo_for[$key]]]);
    }
    demo_save($inspection);
  }
}
// assimilate's own inspection (today) reads as one of the beekeeper's.
$own = HiveInspection::load($state->get(DemoDataProvisioner::DEMO_INSPECTION_ID_STATE_KEY));
if ($own) {
  $own->set('uid', $uid)->set('notes', 'Strong colony, plenty of brood; weighed on the scale.');
  demo_save($own);
}

// 4. Queen observations for the first hive's queen, and one for another.
foreach ([[60, 'excellent', 'Laying steadily, calm on the comb.'], [25, 'good', 'Seen twice this month.'], [6, 'excellent', 'Marked and easy to find.']] as [$ago, $health, $text]) {
  demo_save(QueenObservation::create([
    'queen' => $queens[0]->id(), 'observation_date' => demo_date($ago), 'health' => $health,
    'temperament' => 'calm', 'active' => TRUE, 'notes' => $text, 'uid' => $uid,
  ]));
}
demo_save(QueenObservation::create([
  'queen' => $queens[1]->id(), 'observation_date' => demo_date(9), 'health' => 'good',
  'temperament' => 'moderate', 'active' => TRUE, 'notes' => 'A little nervous when the frames are lifted.', 'uid' => $uid,
]));

// 5. Seasonal jobs. HiveLog gives every new apiary its whole starter calendar (about thirty
// jobs), which for three hives makes dozens of alerts: too much to review. Replace it with a few,
// two of them due about now so the Alerts tab has something, and two later. None may be overdue:
// a job whose weeks have passed shows as a red alert, which is not the first thing a reviewer
// should see. The clamp keeps a late-year week inside the calendar.
$calendar = \Drupal::entityTypeManager()->getStorage('calendar_action');
$starter = $calendar->getQuery()->accessCheck(FALSE)->condition('apiary', $apiary->id())->execute();
if ($starter) {
  $calendar->delete($calendar->loadMultiple($starter));
}
$week = (int) date('W');
$clamp = static fn(int $w): int => max(1, min(53, $w));
foreach ([
  ['Varroa treatment', 'varroa_treatment', 'hive', $week - 1, $week + 3, 'Treat every colony once the honey is off, and check the count a few weeks later.'],
  ['Autumn feeding', 'feeding', 'hive', $week, $week + 2, 'Feed syrup until each hive has enough stores for the winter.'],
  ['Winter preparation', 'winter_prep', 'apiary', $week + 4, $week + 7, 'Reduce entrances, add mouse guards and tilt the hives slightly forward.'],
  ['Oxalic acid trickle', 'varroa_treatment', 'hive', $week + 9, $week + 11, 'Once the colony is broodless, trickle oxalic acid on a cold, calm day.'],
] as [$title, $category, $scope, $start, $end, $description]) {
  demo_save(CalendarAction::create([
    'apiary' => $apiary->id(), 'title' => $title, 'description' => $description, 'category' => $category,
    'scope' => $scope, 'week_start' => $clamp($start), 'week_end' => $clamp($end), 'recurring' => TRUE,
    'enabled' => TRUE, 'uid' => $uid,
  ]));
}

// 6. What each hive is built from, with the weight of each part, so the hive page can net its empty
// weight out of the scale reading ("Net Colony Weight"). Durable items bought once; the parts a
// hive uses are drawn from the apiary's stock, and the stock bought covers them with a spare.
$parts = [
  // name, kg each, bought, unit price, [hive index => quantity]
  ['Floor board', 2.0, 4, 28, [0 => 1, 1 => 1, 2 => 1]],
  ['Brood box with frames', 3.5, 4, 55, [0 => 1, 1 => 1, 2 => 1]],
  ['Honey super with frames', 2.5, 2, 40, [0 => 1]],
  ['Roof', 1.5, 4, 24, [0 => 1, 1 => 1, 2 => 1]],
];
foreach ($parts as [$name, $kg, $bought, $price, $uses]) {
  $item = demo_save(InventoryItem::create([
    'apiary' => $apiary->id(), 'name' => $name, 'unit' => 'each', 'item_type' => 'durable',
    'useful_life_years' => 15, 'weight_kg' => $kg, 'uid' => $uid,
  ]));
  demo_save(InventoryPurchase::create([
    'apiary' => $apiary->id(), 'item' => $item->id(), 'purchase_date' => demo_date(400),
    'quantity' => $bought, 'unit_price' => $price, 'uid' => $uid,
  ]));
  foreach ($uses as $index => $quantity) {
    demo_save(HiveComponent::create(['hive' => $hives[$index]->id(), 'item' => $item->id(), 'quantity' => $quantity, 'uid' => $uid]));
  }
}

// 7. A few sensor readings, so the hive page's stat tiles have something to show.
$generator = \Drupal::service('assimilate.mock_reading_generator');
foreach (\Drupal::service('assimilate.demo_data_provisioner')->getDemoDevices() as $device) {
  for ($i = 0; $i < 4; $i++) {
    $generator->generateReadingsForDevice($device);
  }
}

\Drupal::service('account_switcher')->switchBack();
printf("Seeded: 1 apiary, %d hives, %d queens, inspections, queen observations, hive components and seasonal jobs, owned by user %d.\n", count($hives), count($queens) + 1, $uid);
