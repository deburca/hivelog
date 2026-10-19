<?php

declare(strict_types=1);

namespace Drupal\hivelog;

/**
 * Provides a list builder for ApiaryMembership entities.
 *
 * Not exposed as a collection route — membership management is done inline on
 * the parent apiary's edit form (see ApiaryForm) and canonical page. The list
 * builder exists only so the entity system has a valid handler registered.
 */
class ApiaryMembershipListBuilder extends HivelogListBuilder {

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    // No collection route — this is never called from a page.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getBaseRoute(): string {
    return 'entity.apiary.canonical';
  }

}
