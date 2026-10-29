<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog\HivelogHiveReference;

/**
 * Offers hives as "Hive · Apiary" in an autocomplete (task 0241).
 *
 * Hive names are only unique inside their apiary, so "VV-01" can be several
 * hives; the apiary tells them apart. Only the label changes: which hives are
 * offered is the default selection's. HivelogHiveReference::alterWidget()
 * puts this on every hive autocomplete widget.
 */
#[EntityReferenceSelection(
  id: "default:hivelog_hive_with_apiary",
  label: new TranslatableMarkup("Hivelog: hive with its apiary"),
  group: "default",
  weight: 0,
  entity_types: ["hive"],
)]
class HiveWithApiarySelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $options = parent::getReferenceableEntities($match, $match_operator, $limit);
    $ids = [];
    foreach ($options as $bundle_options) {
      $ids = array_merge($ids, array_keys($bundle_options));
    }
    if (!$ids) {
      return $options;
    }
    $hives = $this->entityTypeManager->getStorage('hive')->loadMultiple($ids);
    foreach ($options as $bundle => $bundle_options) {
      foreach (array_keys($bundle_options) as $id) {
        if (isset($hives[$id])) {
          $options[$bundle][$id] = HivelogHiveReference::label($hives[$id], TRUE);
        }
      }
    }
    return $options;
  }

}
