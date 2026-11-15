<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\HarvestYield;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\Product;

/**
 * The "Harvests" section of the hive and apiary pages (tasks 0271, 0286).
 *
 * Lists every harvest, newest first, whichever way it was recorded
 * (ADR-0110), with a "Record harvest" button. The Source column says where
 * each came from: the calendar action it was reported with, linking to that
 * log, or "Recorded directly". A directly recorded harvest is edited and
 * deleted here; one from the calendar is managed from its log.
 *
 * The using class must have `$entityTypeManager`, `$renderer` and `t()`
 * (the hive and apiary controllers do).
 */
trait HivelogHarvestSectionTrait {

  /**
   * How many harvests the section shows.
   */
  protected const HARVESTS_SHOWN = 20;

  /**
   * Builds the section for a hive, or for an apiary and all its hives.
   *
   * @param \Drupal\hivelog\Entity\Hive|\Drupal\hivelog\Entity\Apiary $parent
   *   The page's hive or apiary.
   *
   * @return array
   *   A render array: a heading with the button, and the table.
   */
  protected function buildHarvestSection(Hive|Apiary $parent): array {
    $for_hive = $parent instanceof Hive;
    $storage = $this->entityTypeManager->getStorage('harvest_yield');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition($for_hive ? 'hive' : 'apiary', $parent->id())
      ->sort('harvest_date', 'DESC')
      ->sort('id', 'DESC')
      ->range(0, static::HARVESTS_SHOWN)
      ->execute();

    $cache_tags = $this->entityTypeManager->getDefinition('harvest_yield')->getListCacheTags();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $harvest) {
      /** @var \Drupal\hivelog\Entity\HarvestYield $harvest */
      if (!$harvest->access('view')) {
        continue;
      }
      $product = $harvest->get('product')->entity;
      $product = $product instanceof Product ? $product : NULL;
      $quantity = rtrim(rtrim(number_format((float) $harvest->get('quantity')->value, 3, '.', ''), '0'), '.');
      $cells = [
        Html::escape((string) $harvest->get('harvest_date')->value),
        Html::escape($product ? (string) $product->label() : (string) $this->t('Unknown product')),
        Html::escape(trim($quantity . ' ' . ($product ? $product->get('unit')->value : ''))),
      ];
      if (!$for_hive) {
        $hive = $harvest->get('hive')->entity;
        $cells[] = $hive ? $hive->toLink()->toString() : '';
      }
      $log = $harvest->get('hive_action_log')->entity ?: $harvest->get('apiary_action_log')->entity;
      $cells[] = $this->harvestSourceCell($harvest, $log);
      if ($log instanceof EntityInterface) {
        // The Source cell names the log's calendar action.
        $cache_tags = array_merge($cache_tags, $log->getCacheTags());
      }
      $cells[] = Html::escape(trim((string) $harvest->get('notes')->value));

      // A harvest from the calendar is changed where it was reported, so
      // its quantity and its log cannot disagree.
      $buttons = [];
      if ($log instanceof EntityInterface) {
        if ($log->access('update')) {
          $buttons[] = ['label' => (string) $this->t('Edit in log'), 'url' => $log->toUrl('edit-form')->toString()];
        }
      }
      elseif ($harvest->isDirect() && $harvest->access('update')) {
        $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $harvest->toUrl('edit-form')->toString()];
      }
      if ($harvest->isDirect() && $harvest->access('delete')) {
        $buttons[] = [
          'label' => (string) $this->t('Delete'),
          'url' => $harvest->toUrl('delete-form')->toString(),
          'variant' => 'danger',
        ];
      }
      $operations = $buttons ? [
        '#type' => 'component',
        '#component' => 'hivelog:button-group',
        '#props' => ['buttons' => $buttons],
      ] : [];
      $cells[] = $buttons ? (string) $this->renderer->renderInIsolation($operations) : '';
      $rows[] = ['cells' => $cells];
      $cache_tags = array_merge($cache_tags, $harvest->getCacheTags());
    }

    $headers = [$this->t('Date'), $this->t('Product'), $this->t('Quantity')];
    if (!$for_hive) {
      $headers[] = $this->t('Hive');
    }
    $headers[] = $this->t('Source');
    $headers[] = $this->t('Notes');
    $headers[] = $this->t('Operations');

    $add_url = $for_hive
      ? Url::fromRoute('hivelog.harvest.add_for_hive', ['hive' => $parent->id()])
      : Url::fromRoute('hivelog.harvest.add_for_apiary', ['apiary' => $parent->id()]);

    $build = [
      '#cache' => [
        'tags' => array_values(array_unique($cache_tags)),
        'contexts' => ['user'],
      ],
      'heading' => [
        '#type' => 'container',
        // 'harvests' is the anchor the delete-blocked page links to.
        '#attributes' => ['class' => ['hivelog-list-heading'], 'id' => 'harvests'],
        'title' => [
          '#type' => 'html_tag',
          // H2: a top-level section of the page.
          '#tag' => 'h2',
          '#value' => $this->t('Harvests'),
          '#attributes' => ['class' => ['hivelog-list-heading__title']],
        ],
      ],
      'table' => [
        '#type' => 'component',
        '#component' => 'hivelog:entity-table',
        '#props' => [
          'headers' => array_map('strval', $headers),
          'rows' => $rows,
          'empty_message' => (string) ($for_hive
            ? $this->t('No harvest has been recorded for this hive yet.')
            : $this->t('No harvest has been recorded for this apiary yet.')),
        ],
      ],
    ];
    if ($add_url->access()) {
      $build['heading']['actions'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-list-heading__action']],
        'add' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Record harvest'),
            'url' => $add_url->toString(),
            'variant' => 'primary',
          ],
        ],
      ];
    }
    return $build;
  }

  /**
   * Says where a harvest came from, for the Source column.
   *
   * @param \Drupal\hivelog\Entity\HarvestYield $harvest
   *   The harvest.
   * @param mixed $log
   *   Its hive or apiary action log, or NULL.
   *
   * @return string
   *   Escaped HTML: the calendar action's title, linking to the log when the
   *   viewer may open it, or "Recorded directly".
   */
  protected function harvestSourceCell(HarvestYield $harvest, mixed $log): string {
    if (!$log instanceof FieldableEntityInterface) {
      return $harvest->isDirect()
        ? Html::escape((string) $this->t('Recorded directly'))
        : Html::escape((string) $this->t('Calendar'));
    }
    $action = $log->get('calendar_action')->entity;
    $title = Html::escape((string) $this->t('Calendar: @action', [
      '@action' => $action ? $action->label() : $this->t('deleted action'),
    ]));
    return $log->access('view')
      ? '<a href="' . Html::escape($log->toUrl('canonical')->toString()) . '">' . $title . '</a>'
      : $title;
  }

}
