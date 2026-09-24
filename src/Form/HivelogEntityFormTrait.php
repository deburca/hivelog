<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hivelog\HivelogEntityHierarchy;

/**
 * Adds a Cancel action to every hivelog add / edit entity form (task 0129).
 *
 * One rule, everywhere:
 * - A `?destination=` query wins, exactly as core's own `ConfirmFormHelper`
 *   honours it on delete forms.
 * - Otherwise, an edit form's Cancel returns to the entity's own
 *   canonical page.
 * - An add form's Cancel — scoped or site-wide — has no canonical page
 *   to return to yet (the entity is new), so it goes to the parent's
 *   canonical page (resolved via `HivelogEntityHierarchy`, the same map
 *   `HivelogBreadcrumbBuilder` and `HivelogEntityDeleteForm` read), else
 *   the entity type's own collection.
 *
 * Used by every hivelog-owned and submodule add / edit form via
 * `use HivelogEntityFormTrait;` — no other change needed, since none of
 * them override `actions()` themselves.
 */
trait HivelogEntityFormTrait {

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['cancel'] = [
      '#type' => 'component',
      '#component' => 'hivelog:button',
      '#props' => [
        'label' => (string) $this->t('Cancel'),
        'url' => $this->cancelUrl()->toString(),
        'variant' => 'default',
      ],
      // EntityForm::actionsElement() always moves `delete` to be
      // physically last in the actions array before it assigns weights
      // in array order — so appending `cancel` after `delete` here
      // still isn't enough on its own. An explicit weight higher than
      // anything core assigns (submit gets 5, delete gets 10) guarantees
      // Cancel renders after both, whether or not `delete` exists.
      '#weight' => 100,
    ];
    return $actions;
  }

  /**
   * Where Cancel goes.
   */
  protected function cancelUrl(): Url {
    $request = $this->getRequest();
    if ($request->query->has('destination')) {
      $options = UrlHelper::parse((string) $request->query->get('destination'));
      try {
        return Url::fromUserInput('/' . ltrim($options['path'], '/'), $options);
      }
      catch (\InvalidArgumentException) {
        // Fall through to the route-based cancel URL below.
      }
    }

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->entity;
    if (!$entity->isNew() && $entity->hasLinkTemplate('canonical')) {
      return $entity->toUrl('canonical');
    }
    return HivelogEntityHierarchy::parentOrCollectionUrl($entity);
  }

}
