<?php

namespace Drupal\hivelog\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;

/**
 * Controller for Hive Inspection pages.
 */
class HiveInspectionController extends ControllerBase {

  /**
   * Provides the add form for an inspection within a hive context.
   */
  public function addForm(Hive $hive) {
    $inspection = $this->entityTypeManager()->getStorage('hive_inspection')->create([
      'hive' => $hive->id(),
    ]);
    return $this->entityFormBuilder()->getForm($inspection, 'add');
  }

  /**
   * Displays a hive inspection.
   */
  public function view(HiveInspection $hive_inspection) {
    $view_builder = $this->entityTypeManager()->getViewBuilder('hive_inspection');
    return $view_builder->view($hive_inspection);
  }

  /**
   * Title callback for the inspection view page.
   */
  public function title(HiveInspection $hive_inspection) {
    return $hive_inspection->label();
  }

}
