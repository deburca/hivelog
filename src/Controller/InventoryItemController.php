<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\HivelogDetailPageTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Inventory Item pages.
 */
class InventoryItemController extends ControllerBase {

  use HivelogDetailPageTrait;

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

    // The facts are one strip of stat tiles (task 0260). Stock on hand leads: it
    // is computed from the purchase ledger, and is the number a beekeeper opens
    // this page for. The item's name is the page title.
    $unit = $inventory_item->get('unit')->getString();
    $tiles = [];
    if ($inventory_item->get('item_type')->getString() === 'consumable') {
      $stock = $inventory_item->getStockOnHand();
      $low = $inventory_item->isLowStock();
      $tiles['stock'] = [
        'label' => $this->t('Stock on hand'),
        'value' => $stock === NULL ? (string) $this->t('Unknown') : trim($this->tileNumber($stock, 3, TRUE) . ' ' . $unit),
        'sublabel' => $low ? $this->t('Low stock') : '',
        'variant' => $low ? 'critical' : 'default',
      ];
    }
    $purchased = $inventory_item->getTotalPurchasedQuantity();
    $weight = $this->tileNumber($inventory_item->get('weight_kg')->getString(), 3, TRUE);
    $life = (int) $inventory_item->get('useful_life_years')->getString();
    $tiles += [
      'item_type' => ['label' => $this->t('Type')],
      'category' => ['omit_empty' => TRUE],
      'status' => ['omit_empty' => TRUE],
      'weight_kg' => [
        'label' => $this->t('Unit weight'),
        'value' => $weight !== '' ? $weight . ' kg' : '',
        'omit_empty' => TRUE,
      ],
      'useful_life_years' => [
        'label' => $this->t('Useful life'),
        'value' => $life > 0 ? (string) $this->formatPlural($life, '1 year', '@count years') : '',
        'omit_empty' => TRUE,
      ],
      'purchased' => [
        'label' => $this->t('Total purchased'),
        'value' => $purchased > 0 ? trim($this->tileNumber($purchased, 3, TRUE) . ' ' . $unit) : '',
        'omit_empty' => TRUE,
      ],
      'apiary' => [],
    ];
    $facts = $this->buildTileStrip($inventory_item, $tiles);
    if ($facts) {
      $build['facts'] = $facts;
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
   * {@inheritdoc}
   */
  protected function formatFieldValue(FieldableEntityInterface $entity, string $field_name, FieldItemListInterface $field): array {
    /** @var \Drupal\hivelog\Entity\InventoryItem $entity */
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

      case 'low_stock_threshold':
        return [
          '#plain_text' => rtrim(rtrim(number_format((float) $field->value, 3, '.', ''), '0'), '.') . ' ' . $entity->get('unit')->value,
        ];

      case 'weight_kg':
        return [
          '#plain_text' => rtrim(rtrim(number_format((float) $field->value, 3, '.', ''), '0'), '.') . ' kg',
        ];

      default:
        return [
          '#plain_text' => (string) $field->value,
        ];
    }
  }

}
