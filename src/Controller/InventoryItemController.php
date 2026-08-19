<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\InventoryItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Inventory Item pages.
 */
class InventoryItemController extends ControllerBase {

  /**
   * Constructs an InventoryItemController.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder,
  ) {
    // $entityTypeManager / $entityFormBuilder are untyped properties
    // inherited from ControllerBase; assign them rather than redeclaring
    // them with types.
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
    );
  }

  /**
   * Provides the add form for an inventory item within an apiary context.
   */
  public function addForm(Apiary $apiary) {
    $item = $this->entityTypeManager->getStorage('inventory_item')->create([
      'apiary' => $apiary->id(),
    ]);
    return $this->entityFormBuilder->getForm($item, 'add');
  }

  /**
   * Displays an inventory item with its fields grouped into readable sections.
   */
  public function view(InventoryItem $inventory_item) {
    $build = [
      'actions' => $this->buildActions($inventory_item),
    ];

    $build += [
      'overview' => $this->buildSection($this->t('Overview'), $inventory_item, [
        'apiary',
        'name',
        'category',
        'unit',
        'status',
      ]),
      'type' => $this->buildSection($this->t('Type & Depreciation'), $inventory_item, [
        'item_type',
        'useful_life_years',
      ]),
    ];

    // Stock on hand isn't a stored field — it's computed from the
    // purchase (and, eventually, usage) ledger, so it gets its own
    // section rather than going through buildRows()'s field-driven path.
    if ($inventory_item->get('item_type')->value === 'consumable') {
      $stock = $inventory_item->getStockOnHand();
      $unit = $inventory_item->get('unit')->value;
      $build['stock'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-inventory-item-section']],
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Stock on Hand'),
        ],
        'value' => [
          '#markup' => '<p>' . ($stock === NULL ? (string) $this->t('Unknown') : rtrim(rtrim(number_format($stock, 3, '.', ''), '0'), '.') . ' ' . $unit) . '</p>',
        ],
      ];
    }

    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($inventory_item)
      ->addCacheTags($this->entityTypeManager->getDefinition('inventory_purchase')->getListCacheTags());
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Title callback for the inventory item view page.
   */
  public function title(InventoryItem $inventory_item) {
    return $inventory_item->label();
  }

  /**
   * Builds Edit and Delete action links for the inventory item view.
   */
  protected function buildActions(InventoryItem $inventory_item): array {
    $buttons = [];
    if ($inventory_item->access('update')) {
      $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $inventory_item->toUrl('edit-form')->toString()];
    }
    if ($inventory_item->access('delete')) {
      $buttons[] = [
        'label' => (string) $this->t('Delete'),
        'url' => $inventory_item->toUrl('delete-form')->toString(),
        'variant' => 'danger',
      ];
    }
    if (empty($buttons)) {
      return [];
    }
    return [
      '#type' => 'component',
      '#component' => 'hivelog:button-group',
      '#props' => ['buttons' => $buttons],
      '#weight' => -10,
    ];
  }

  /**
   * Builds a consistently formatted inventory item section.
   */
  protected function buildSection($title, InventoryItem $inventory_item, array $fields): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['hivelog-inventory-item-section'],
      ],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $title,
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Field'),
          $this->t('Value'),
        ],
        '#rows' => $this->buildRows($inventory_item, $fields),
        '#attributes' => [
          'class' => ['hivelog-inventory-item-table'],
        ],
        '#attached' => ['library' => ['hivelog/tables']],
      ],
    ];
  }

  /**
   * Builds rows for a section table.
   */
  protected function buildRows(InventoryItem $inventory_item, array $fields): array {
    $rows = [];

    foreach ($fields as $field_name) {
      $rows[] = [
        [
          'data' => [
            '#plain_text' => (string) $inventory_item->get($field_name)->getFieldDefinition()->getLabel(),
          ],
        ],
        [
          'data' => $this->buildFieldValue($inventory_item, $field_name),
        ],
      ];
    }

    return $rows;
  }

  /**
   * Builds the display value for a single inventory item field.
   */
  protected function buildFieldValue(InventoryItem $inventory_item, string $field_name): array {
    $field = $inventory_item->get($field_name);

    if ($field->isEmpty()) {
      return [
        '#plain_text' => (string) $this->t('—'),
      ];
    }

    switch ($field_name) {
      case 'apiary':
        return $field->entity ? $field->entity->toLink()->toRenderable() : [
          '#plain_text' => (string) $this->t('—'),
        ];

      case 'category':
      case 'item_type':
      case 'status':
        $allowed_values = $field->getSetting('allowed_values');
        return [
          '#plain_text' => (string) ($allowed_values[$field->value] ?? $field->value),
        ];

      default:
        return [
          '#plain_text' => (string) $field->value,
        ];
    }
  }

}
