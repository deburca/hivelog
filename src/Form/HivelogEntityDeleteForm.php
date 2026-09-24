<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Url;
use Drupal\hivelog\HivelogEntityHierarchy;

/**
 * Shared delete-form base for every hivelog content entity (task 0127).
 *
 * One rule, everywhere:
 * - **Cancel** returns to the entity's own canonical page when it has
 *   one, otherwise to its parent's canonical page (resolved via
 *   `HivelogEntityHierarchy` — requirement / yield fall through to their
 *   calendar action). Cancel undoes the click that brought the user
 *   here.
 * - **After delete**: the parent's canonical page, else the entity
 *   type's own collection, else the dashboard.
 * - A `?destination=` query wins over both, as core already does: it is
 *   applied automatically to the rendered Cancel link by
 *   `ConfirmFormHelper::buildCancelLink()`, and to the post-delete
 *   redirect by core's form submission handling — neither override
 *   below needs to special-case it.
 *
 * `getQuestion()` and the deletion message are left to
 * `ContentEntityDeleteForm`'s own defaults, which already read the
 * entity type's singular label (`label_singular` on every hivelog
 * entity's `#[ContentEntityType]` attribute) — so most hivelog delete
 * forms need nothing beyond
 * `handlers.form.delete: HivelogEntityDeleteForm::class`. Subclasses
 * exist only where there is real extra logic:
 * `InventoryItemDeleteForm` / `ProductDeleteForm` (a historical-reference
 * warning in `getDescription()`), and the submodule forms
 * (`SensorDeviceDeleteForm`, `ApiClientDeleteForm`,
 * `AiProviderConfigDeleteForm`) for their own `getDescription()` text.
 */
class HivelogEntityDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    if (!$entity->isDefaultTranslation()) {
      return parent::getCancelUrl();
    }
    if ($entity->hasLinkTemplate('canonical')) {
      return $entity->toUrl('canonical');
    }
    return $this->parentOrFallbackUrl($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl() {
    return $this->parentOrFallbackUrl($this->getEntity());
  }

  /**
   * The parent's canonical page, else the entity's own collection.
   *
   * Falls through to the dashboard when neither is available.
   */
  protected function parentOrFallbackUrl(EntityInterface $entity): Url {
    if ($entity instanceof FieldableEntityInterface) {
      $parent = HivelogEntityHierarchy::resolveParent($entity);
      if ($parent && $parent->hasLinkTemplate('canonical')) {
        return $parent->toUrl('canonical');
      }
    }
    if ($entity->hasLinkTemplate('collection')) {
      return $entity->toUrl('collection');
    }
    return Url::fromRoute('hivelog.dashboard');
  }

}
