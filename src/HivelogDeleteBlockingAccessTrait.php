<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\hivelog\Delete\HivelogDeleteDependencyCounter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Layers the delete-dependency BLOCK check onto an owner-only delete result.
 *
 * Used by task 0141's six parent-side access control handlers (Apiary,
 * Hive, Queen, CalendarAction, InventoryItem, Product) — each already
 * grants `delete` to the owner via
 * `ApiaryAccessTrait::checkApiaryOwnerDeleteAccess()`; `blockDelete()`
 * forbids that otherwise-allowed result while any BLOCK row registered
 * against the entity still has children, via
 * `HivelogDeleteDependencyCounter::blockingAccessResult()`.
 *
 * A class using this trait must add
 * `implements \Drupal\Core\Entity\EntityHandlerInterface` itself — a
 * trait cannot declare that on the class using it, only supply the
 * `createInstance()` method the interface requires.
 */
trait HivelogDeleteBlockingAccessTrait {

  /**
   * The delete-dependency counter, used to forbid a blocked delete.
   */
  protected HivelogDeleteDependencyCounter $deleteDependencyCounter;

  /**
   * {@inheritdoc}
   *
   * `EntityAccessControlHandler` has no `createInstance()` of its own to
   * call via `parent::` — core instantiates it with `new $class($entity_type)`
   * unless the class implements `EntityHandlerInterface`, so this builds
   * the instance directly instead. phpstan can't see that every class
   * using this trait extends `EntityAccessControlHandler`, whose
   * constructor takes exactly `$entity_type` — hence the ignore below.
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    // @phpstan-ignore-next-line
    $instance = new static($entity_type);
    $instance->deleteDependencyCounter = $container->get('hivelog.delete_dependency_counter');
    return $instance;
  }

  /**
   * Forbids an otherwise-allowed delete while a BLOCK row has children.
   *
   * Leaves `$access` untouched when it isn't already allowed — there is
   * no point spending a dependency-count query on a delete the user
   * couldn't perform anyway, and a non-allowed result must not be
   * "upgraded" to forbidden-with-reason (`neutral` still means "core,
   * decide via other access checks").
   *
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The result of the ownership/permission check alone.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being deleted.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   `$access` unchanged, or forbidden (merged with `$access`'s own
   *   cacheability) when a BLOCK row has children.
   */
  protected function blockDelete(AccessResultInterface $access, EntityInterface $entity): AccessResultInterface {
    if (!$access->isAllowed()) {
      return $access;
    }
    $block = $this->deleteDependencyCounter->blockingAccessResult($entity);
    return $block ? $access->andIf($block) : $access;
  }

}
