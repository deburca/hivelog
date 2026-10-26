<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\ApiaryMembership;
use Drupal\hivelog\HivelogOwnerWarning;

/**
 * Renders the member management section on an apiary's edit page.
 */
class ApiaryMemberManagerForm extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
  }

  /**
   * Builds the member management render array for an apiary.
   *
   * @param int|string $apiary_id
   *   The apiary entity ID whose members are shown. Route parameters are
   *   always strings; this method casts to int for entity loading.
   *
   * @return array
   *   A render array to embed into ApiaryForm's build array.
   */
  public function render(int|string $apiary_id): array {
    $apiary_id = (int) $apiary_id;
    /** @var \Drupal\hivelog\Entity\Apiary $apiary */
    $apiary = $this->entityTypeManager->getStorage('apiary')->load($apiary_id);

    if (!$apiary || !$this->currentUser()->hasPermission('edit own apiary')) {
      return [];
    }

    // A form item, so the title and description look exactly like the other
    // settings on the edit form (Visibility, Owner) rather than like a page
    // section; a big page-level heading inside a tab was out of place.
    $build = [
      '#type' => 'item',
      '#title' => $this->t('Members'),
      '#description' => $this->t('People who can access this apiary and its hives.'),
      // A form item renders no attributes of its own, so the anchor the
      // "Manage members" link and Cancel return to goes on a wrapper.
      '#prefix' => '<div id="members" class="hivelog-members">',
      '#suffix' => '</div>',
      '#attached' => ['library' => ['hivelog/buttons']],
    ];

    $memberships = $apiary->getApiaryMembers();

    // More than two owners, or whose CBR number the apiary carries (task 0232).
    $build['owner_warning'] = HivelogOwnerWarning::build($apiary, $this->currentUser()) + ['#weight' => -5];
    $primary = $apiary->getPrimaryOwner();
    if ($primary) {
      $cbr = $apiary->getCbrNumber();
      $build['cbr'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $cbr !== ''
          ? $this->t("The apiary's CBR number is @cbr, from @owner, the owner for longest.", [
            '@cbr' => $cbr,
            '@owner' => $primary->getDisplayName(),
          ])
          : $this->t('The apiary has no CBR number: @owner, the owner for longest, has not set one.', [
            '@owner' => $primary->getDisplayName(),
          ]),
        '#attributes' => ['class' => ['description']],
        '#weight' => -4,
      ];
    }

    // An apiary must always keep at least one owner.
    $owner_count = count(array_filter($memberships, fn($m): bool => $m->getRole() === 'owner'));

    $rows = [];
    foreach ($memberships as $membership) {
      $account = $membership->getMember();
      if (!$account) {
        continue;
      }
      $id = $membership->id();
      $params = ['apiary' => $apiary_id, 'apiary_membership' => $id];
      $is_last_owner = $owner_count <= 1 && $membership->getRole() === 'owner';

      $buttons = [[
        'label' => (string) $this->t('Edit role'),
        'url' => Url::fromRoute('entity.apiary_membership.edit_form', $params)->toString(),
      ]
];
      if (!$is_last_owner) {
        $buttons[] = [
          'label' => (string) $this->t('Remove'),
          'url' => Url::fromRoute('entity.apiary_membership.delete_form', $params)->toString(),
          'variant' => 'danger',
        ];
      }
      $operations = [
        '#type' => 'component',
        '#component' => 'hivelog:button-group',
        '#props' => ['buttons' => $buttons],
        '#attached' => ['library' => ['hivelog/buttons']],
      ];

      // Cells are output raw by the component, so escape the text ones.
      $rows[] = [
      'cells' => [
        Html::escape($account->getDisplayName()),
        Html::escape((string) ApiaryMembership::roleLabel($membership->getRole())),
        (string) $this->renderer->renderInIsolation($operations),
      ]
];
    }

    $build['table'] = [
      '#type' => 'component',
      '#component' => 'hivelog:entity-table',
      '#props' => [
        'headers' => [
          (string) $this->t('Member'),
          (string) $this->t('Role'),
          (string) $this->t('Operations'),
        ],
        'rows' => $rows,
        'empty_message' => (string) $this->t('No members yet.'),
      ],
    ];

    // The scoped-add route is a page of its own: an inline form would
    // conflict with this (the apiary) form's submit handler.
    $build['add'] = [
      '#type' => 'component',
      '#component' => 'hivelog:button',
      '#props' => [
        'label' => (string) $this->t('Add a member'),
        'url' => Url::fromRoute('hivelog.apiary_membership.add', ['apiary' => $apiary_id])->toString(),
        'variant' => 'primary',
      ],
      '#weight' => 10,
    ];

    return $build;
  }

}
