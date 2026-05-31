<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Resilient entity storage handler for HiveLog entities.
 *
 * Overrides methods that query the base table so that the module can be
 * uninstalled cleanly even when the underlying database tables have already
 * been removed (e.g. after a database restore or manual cleanup).
 *
 * Without this, Drupal's ContentUninstallValidator calls hasData() before
 * hook_uninstall() runs, and the resulting SQL exception aborts the entire
 * uninstall process.
 */
class HivelogEntityStorage extends SqlContentEntityStorage {

  /**
   * {@inheritdoc}
   */
  public function hasData() {
    try {
      return parent::hasData();
    }
    catch (\Exception $e) {
      // Table does not exist — no data to report.
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function countFieldData($storage_definition, $as_bool = FALSE) {
    try {
      return parent::countFieldData($storage_definition, $as_bool);
    }
    catch (\Exception $e) {
      // Table does not exist — report zero / false.
      return $as_bool ? FALSE : 0;
    }
  }

}
