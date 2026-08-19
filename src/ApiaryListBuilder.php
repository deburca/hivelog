<?php

namespace Drupal\hivelog;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list builder for Apiary entities.
 */
class ApiaryListBuilder extends EntityListBuilder {

  use StringTranslationTrait;

  /**
   * Number of apiaries shown per page.
   *
   * @var int
   */
  protected $limit = 20;

  /**
   * The current user account.
   */
  protected AccountInterface $currentUser;

  /**
   * The user storage handler.
   */
  protected EntityStorageInterface $userStorage;

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * Constructs a new ApiaryListBuilder.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    AccountInterface $current_user,
    EntityStorageInterface $user_storage,
    RendererInterface $renderer,
  ) {
    parent::__construct($entity_type, $storage);
    $this->currentUser = $current_user;
    $this->userStorage = $user_storage;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $entity_type,
      $entity_type_manager->getStorage($entity_type->id()),
      $container->get('current_user'),
      $entity_type_manager->getStorage('user'),
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['cbr'] = $this->t('CBR');
    $header['name'] = $this->t('Name');
    $header['location'] = $this->t('Location');
    $header['owner'] = $this->t('Owner');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $owner = $entity->getOwner();
    $row['cbr'] = $this->extractCbr($owner) ?: '—';
    $row['name'] = $entity->toLink()->toString();
    $row['location'] = $entity->get('location')->value
      ? mb_strimwidth($entity->get('location')->value, 0, 60, '...')
      : '';
    $row['owner'] = $owner ? $owner->getDisplayName() : '';

    // Build operations as plain button links instead of the default
    // dropbutton widget returned by parent::buildRow().
    $buttons = [];
    if ($entity->access('update') && $entity->hasLinkTemplate('edit-form')) {
      $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $entity->toUrl('edit-form')->toString()];
    }
    if ($entity->access('delete') && $entity->hasLinkTemplate('delete-form')) {
      $buttons[] = [
        'label' => (string) $this->t('Delete'),
        'url' => $entity->toUrl('delete-form')->toString(),
        'variant' => 'danger',
      ];
    }
    $row['operations']['data'] = [
      '#type' => 'component',
      '#component' => 'hivelog:button-group',
      '#props' => [
        'buttons' => $buttons,
      ],
    ];

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    // Build the table using the SDC component instead of the inherited
    // #type => 'table' from EntityListBuilder::render().
    $headers = array_map('strval', array_values($this->buildHeader()));
    $rows = [];
    foreach ($this->load() as $entity) {
      $row = $this->buildRow($entity);
      if (!$row) {
        continue;
      }
      // Pre-render the operations cell (which contains render arrays).
      $ops = $row['operations']['data'] ?? [];
      $ops_html = !empty($ops) ? $this->renderer->renderInIsolation($ops) : '';

      $rows[] = [
        'cells' => [
          $row['cbr'],
          $row['name'],
          $row['location'] ?? '',
          $row['owner'] ?? '',
          $ops_html,
        ],
      ];
    }

    // Heading row with "Add Apiary" action, matching the pattern used on
    // all other list pages in the module.
    $build['heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading']],
      '#weight' => -90,
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Apiaries'),
        '#attributes' => ['class' => ['hivelog-list-heading__title']],
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-list-heading__action']],
        'buttons' => [
          '#type' => 'component',
          '#component' => 'hivelog:button-group',
          '#props' => [
            'buttons' => [
              [
                'label' => (string) $this->t('Add Apiary'),
                'url' => Url::fromRoute('entity.apiary.add_form')->toString(),
                'variant' => 'primary',
              ],
              [
                'label' => (string) $this->t('View all Queens'),
                'url' => Url::fromRoute('entity.queen.collection')->toString(),
              ],
            ],
          ],
        ],
      ],
      '#attached' => ['library' => ['hivelog/buttons']],
    ];

    $build['table'] = [
      '#type' => 'component',
      '#component' => 'hivelog:entity-table',
      '#props' => [
        'headers' => $headers,
        'rows' => $rows,
        'empty_message' => (string) $this->t('There are no @label yet.', [
          '@label' => $this->entityType->getPluralLabel(),
        ]),
      ],
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    ];

    // Add pager since $this->limit is set.
    $build['pager'] = [
      '#type' => 'pager',
      '#weight' => 10,
    ];

    /** @var \Drupal\user\UserInterface|null $current */
    $current = $this->userStorage->load($this->currentUser->id());
    $cbr = $this->extractCbr($current);

    if ($cbr !== '') {
      $message = [
        '#markup' => $this->t('Your CBR number: @cbr', ['@cbr' => $cbr]),
      ];
    }
    elseif ($current) {
      $message = [
        '#type' => 'inline_template',
        '#template' => '{% trans %}You have not set a CBR number yet. <a href="{{ url }}">Update your profile</a> to add one.{% endtrans %}',
        '#context' => [
          'url' => $current->toUrl('edit-form')->toString(),
        ],
      ];
    }
    else {
      $message = [
        '#markup' => $this->t('Sign in to record your CBR number.'),
      ];
    }

    $build['cbr_summary'] = [
      '#type' => 'container',
      '#weight' => -100,
      '#attributes' => ['class' => ['hivelog-cbr-summary']],
      'message' => $message,
    ];

    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['user']);
    if ($current) {
      $cache->addCacheableDependency($current);
    }
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Extracts a trimmed CBR number from a user, if any.
   */
  protected function extractCbr(?UserInterface $user): string {
    if (!$user || !$user->hasField('cbr_number')) {
      return '';
    }
    return trim((string) $user->get('cbr_number')->value);
  }

}
