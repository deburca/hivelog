<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base list builder for HiveLog entity collection pages.
 *
 * Renders the row "Operations" column as a hivelog:button-group cluster
 * (Edit + Delete, Delete in the danger variant) instead of Drupal core's
 * collapsed dropbutton widget, so every HiveLog collection page matches
 * the flat action-button style already used on the canonical pages, the
 * embedded child lists and the dashboard (ADR-0012, task 0068).
 *
 * Subclasses that render their own table via the hivelog:entity-table SDC
 * call buildOperations() directly for the cell; the ones that keep core's
 * `#type => 'table'` get it automatically through
 * EntityListBuilder::buildRow().
 *
 * load() also filters rows by per-entity access('view') (task 0124). Core
 * EntityListBuilder's query `accessCheck(TRUE)` only filters rows for an
 * entity type with a `query_access` handler; none of HiveLog's entity
 * types declare one, so without this the collection routes' coarse
 * `view own X+view any X+administer hivelog` permission gate lets any
 * user with an "own" permission see every other user's rows too —
 * apiary/hive access here is genuinely per-record and multi-tenant, not a
 * flat owner check on a handful of site-level rows.
 */
abstract class HivelogListBuilder extends EntityListBuilder {

  /**
   * The current user, used to filter load() by per-row view access.
   */
  protected AccountInterface $currentUser;

  /**
   * {@inheritdoc}
   *
   * Subclasses that override createInstance() to inject additional
   * services (a renderer, mostly) must still set $instance->currentUser
   * themselves — see ApiaryListBuilder::createInstance() for the pattern.
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entities = parent::load();
    return array_filter($entities, fn(EntityInterface $entity) => $entity->access('view', $this->currentUser));
  }

  /**
   * {@inheritdoc}
   *
   * A hivelog:button-group cluster in place of core's dropbutton.
   */
  public function buildOperations(EntityInterface $entity) {
    $buttons = [];
    if ($entity->access('update') && $entity->hasLinkTemplate('edit-form')) {
      $buttons[] = [
        'label' => (string) $this->t('Edit'),
        'url' => $entity->toUrl('edit-form')->toString(),
      ];
    }
    if ($entity->access('delete') && $entity->hasLinkTemplate('delete-form')) {
      $buttons[] = [
        'label' => (string) $this->t('Delete'),
        'url' => $entity->toUrl('delete-form')->toString(),
        'variant' => 'danger',
      ];
    }

    return [
      '#type' => 'component',
      '#component' => 'hivelog:button-group',
      '#props' => ['buttons' => $buttons],
      // Core's `#type => 'table'` (used by the plain subclasses) does not
      // pull in the button library on its own.
      '#attached' => ['library' => ['hivelog/buttons']],
    ];
  }

}
