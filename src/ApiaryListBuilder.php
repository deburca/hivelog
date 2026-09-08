<?php

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list builder for Apiary entities.
 */
class ApiaryListBuilder extends HivelogListBuilder {

  use StringTranslationTrait;

  /**
   * Number of apiaries shown per page.
   *
   * @var int
   */
  protected $limit = 20;

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
    RendererInterface $renderer,
  ) {
    parent::__construct($entity_type, $storage);
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
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

    $row['operations']['data'] = $this->buildOperations($entity);

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

    // The current user's CBR summary banner moved to the dashboard landing
    // page (DashboardController, ADR-0057 / task 0056). The per-row CBR
    // column below — the apiary owner's number — stays here.
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
