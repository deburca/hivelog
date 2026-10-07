<?php

/**
 * @file
 * Makes the demo's time-dependent records current again. Run by `ddev demo-reset` after the baseline
 * is restored (and safe to run any time).
 *
 * The baseline was saved when the demo was built, so what it holds is as old as the baseline: an
 * insight is "out of date" after 48 hours, and "Updated N hours ago" under a sensor tile grows. This
 * sets each hive's insight to be as recent as it was meant to be (the third deliberately three days old,
 * to show the out-of-date look) and adds a few sensor readings.
 *
 * Run with: drush php:script scripts/freshen.php
 */

use Drupal\assimilate\DemoDataProvisioner;

$storage = \Drupal::entityTypeManager()->getStorage('hive_insight');
$insights = $storage->loadMultiple($storage->getQuery()->accessCheck(FALSE)->sort('id')->execute());
$ages = [3600, 3 * 3600, 3 * 24 * 3600];
$i = 0;
foreach ($insights as $insight) {
  $insight->set('generated', time() - ($ages[$i++] ?? 3600))->save();
}

$generator = \Drupal::service('assimilate.mock_reading_generator');
foreach (\Drupal::service('assimilate.demo_data_provisioner')->getDemoDevices() as $device) {
  for ($n = 0; $n < 2; $n++) {
    $generator->generateReadingsForDevice($device);
  }
}
printf("Freshened %d insights and the sensor readings.\n", count($insights));
