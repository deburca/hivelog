<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;

/**
 * Add forms for a harvest recorded directly (task 0271).
 */
class HarvestYieldController extends ControllerBase {

  /**
   * The add form for a harvest taken from one hive.
   */
  public function addForHive(Hive $hive): array {
    $harvest = $this->entityTypeManager()->getStorage('harvest_yield')->create([
      'hive' => $hive->id(),
      'apiary' => $hive->get('apiary')->target_id,
      'harvest_date' => date('Y-m-d'),
    ]);
    return $this->entityFormBuilder()->getForm($harvest, 'add');
  }

  /**
   * The add form for a harvest that belongs to the apiary as a whole.
   */
  public function addForApiary(Apiary $apiary): array {
    $harvest = $this->entityTypeManager()->getStorage('harvest_yield')->create([
      'apiary' => $apiary->id(),
      'harvest_date' => date('Y-m-d'),
    ]);
    return $this->entityFormBuilder()->getForm($harvest, 'add');
  }

}
