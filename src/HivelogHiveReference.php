<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hivelog\Entity\Hive;

/**
 * How a hive reads in an autocomplete field (task 0241).
 *
 * "VV-01 · Vand Værk (12)": the hive's name, its apiary, and the id Drupal
 * needs to find the record again. Hive names repeat across apiaries, so the
 * bare name ("VV-01 (12)") left a beekeeper with several apiaries guessing.
 * The id stays: it is how the submitted text is resolved to a hive.
 */
final class HivelogHiveReference {

  /**
   * The separator between a hive's name and its apiary.
   */
  const SEPARATOR = ' · ';

  /**
   * A hive's label in an autocomplete, optionally with its apiary.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive.
   * @param bool $escape
   *   TRUE for the dropdown row, which is HTML; FALSE for the input's text.
   */
  public static function label(Hive $hive, bool $escape = FALSE): string {
    $name = (string) $hive->label();
    $apiary = $hive->get('apiary')->entity;
    if ($apiary) {
      $name .= self::SEPARATOR . $apiary->label();
    }
    return $escape ? Html::escape($name) : $name;
  }

  /**
   * Alters an entity-reference autocomplete widget that points at hives.
   *
   * Called from hook_field_widget_single_element_entity_reference_autocomplete_form_alter().
   */
  public static function alterWidget(array &$element, array $context): void {
    $definition = $context['items']->getFieldDefinition();
    if ($definition->getSetting('target_type') !== 'hive' || !isset($element['target_id'])) {
      return;
    }
    $element['target_id']['#selection_handler'] = 'default:hivelog_hive_with_apiary';
    $element['target_id']['#value_callback'] = [self::class, 'valueCallback'];
  }

  /**
   * Value callback: shows a saved hive with its apiary, like the dropdown does.
   *
   * Only the value shown for an existing reference is replaced (core would
   * give "VV-01 (12)"); what the user typed or picked is left to core.
   */
  public static function valueCallback(array &$element, mixed $input, FormStateInterface $form_state): mixed {
    $default = $element['#default_value'] ?? NULL;
    if ($input === FALSE && $default instanceof Hive) {
      return Tags::encode(self::label($default) . ' (' . $default->id() . ')');
    }
    return EntityAutocomplete::valueCallback($element, $input, $form_state);
  }

}
