<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\ApiaryMembership;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'), $container->get('renderer'));
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

    if (!$apiary || !\Drupal::currentUser()->hasPermission('edit own apiary')) {
      return [];
    }

    // A plain container with the same heading row the apiary page's own
    // sections use, not a fieldset: themes style a fieldset legend as a form
    // label, and it was easy to mistake for part of the Owner field above.
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-members'], 'id' => 'members'],
      'heading' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-list-heading']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('Members'),
          '#attributes' => ['class' => ['hivelog-list-heading__title']],
        ],
        // The scoped-add route is a page of its own: an inline form would
        // conflict with this (the apiary) form's submit handler.
        'add' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Add a member'),
            'url' => Url::fromRoute('hivelog.apiary_membership.add', ['apiary' => $apiary_id])->toString(),
            'variant' => 'primary',
            'extra_classes' => 'hivelog-list-heading__action',
          ],
        ],
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('People who can access this apiary and its hives.'),
        '#attributes' => ['class' => ['description']],
      ],
      '#attached' => ['library' => ['hivelog/buttons']],
    ];

    $memberships = $apiary->getApiaryMembers();

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
        Html::escape((string) (ApiaryMembership::ROLES[$membership->getRole()] ?? $membership->getRole())),
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

    return $build;
  }

}
