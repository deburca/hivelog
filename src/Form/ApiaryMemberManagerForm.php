<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the member management section on an apiary's edit page.
 */
class ApiaryMemberManagerForm extends ControllerBase {

  /**
   * The entity type manager.
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Builds the member management render array for an apiary.
   *
   * @param int $apiary_id
   *   The apiary entity ID whose members are shown.
   *
   * @return array
   *   A render array to embed into ApiaryForm's build array.
   */
  public function render(int $apiary_id): array {
    /** @var \Drupal\hivelog\Entity\Apiary $apiary */
    $apiary = $this->entityTypeManager->getStorage('apiary')->load($apiary_id);

    if (!$apiary || !\Drupal::currentUser()->hasPermission('edit own apiary')) {
      return [];
    }

    $build = [
      '#type' => 'fieldset',
      '#title' => $this->t('Members'),
      '#description' => $this->t('People who can access this apiary and its hives.'),
      '#collapsed' => FALSE,
      '#collapsible' => TRUE,
    ];

    // Load current memberships.
    $memberships = [];
    foreach ($apiary->get('apiary_memberships') as $item) {
      /** @var \Drupal\hivelog\Entity\ApiaryMembership|null $membership */
      $membership = $item->entity;
      if ($membership) {
        $memberships[$membership->id()] = $membership;
      }
    }

    // Count owners for "last owner" guard.
    $owner_count = count(array_filter($memberships, fn($m): bool => $m->getRole() === 'owner'));

    if (count($memberships) > 0) {
      $header = [
        'account' => $this->t('Member'),
        'role' => $this->t('Role'),
        'operations' => $this->t('Operations'),
      ];
      $rows = [];
      foreach ($memberships as $id => $membership) {
        /** @var \Drupal\user\UserInterface $account */
        $account = $membership->getMember();
        if (!$account) {
          continue;
        }

        // Guard: skip the apiary owner from removal when they are the last
        // owner (an apiary must always have at least one owner).
        $is_owner = $membership->getRole() === 'owner';
        $is_last_owner = $owner_count <= 1 && $is_owner;

        $rows[$id] = [
          'account' => ['#markup' => $this->escape($account->getDisplayName())],
          'role' => ['#markup' => ApiaryMembership::ROLES[$membership->getRole()] ?? $membership->getRole()],
          'operations' => [
            '#type' => 'hivelog:button-group',
            'buttons' => [
              [
                '#type' => 'link',
                '#title' => $this->t('Edit role'),
                '#url' => Url::fromRoute(
                  'entity.apiary_membership.edit_form',
                  ['apiary_membership' => $id]
                ),
              ],
              // Only show remove if not the apiary owner or if there's another
              // owner.  A new member is added via the scoped-add route below.
              [
                '#type' => 'link',
                '#title' => $this->t('Remove'),
                '#url' => Url::fromRoute(
                  'entity.apiary_membership.delete_form',
                  ['apiary_membership' => $id]
                ),
                '#button_class' => 'button--danger',
                '#access' => !$is_owner || !$is_last_owner,
              ],
            ],
          ],
        ];
      }

      $build['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No members yet.'),
        '#attributes' => ['class' => ['hivelog-detail-table']],
      ];
    }

    // "Add member" link to the scoped-add route.  Inline submission would
    // conflict with the parent apiary form's submit handler, so we provide a
    // dedicated form page instead (ApiaryMembershipForm).
    $build['add_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Add a member'),
      '#url' => Url::fromRoute(
        'hivelog.apiary_membership.add',
        ['apiary' => $apiary_id]
      ),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    return $build;
  }

}
