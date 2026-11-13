<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\HivelogDetailPageTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Inventory Purchase pages.
 */
class InventoryPurchaseController extends ControllerBase {

  use HivelogDetailPageTrait;

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Constructs an InventoryPurchaseController.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder,
    DateFormatterInterface $date_formatter,
  ) {
    // $entityTypeManager / $entityFormBuilder are untyped properties
    // inherited from ControllerBase; assign them rather than redeclaring
    // them with types.
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Provides the add form for a purchase within an apiary context.
   */
  public function addForm(Apiary $apiary) {
    $purchase = $this->entityTypeManager->getStorage('inventory_purchase')->create([
      'apiary' => $apiary->id(),
    ]);
    return $this->entityFormBuilder->getForm($purchase, 'add');
  }

  /**
   * Displays a purchase with its fields grouped into readable sections.
   */
  public function view(InventoryPurchase $inventory_purchase) {
    $build = [
      'actions' => $this->buildActions($inventory_purchase),
    ];

    // Short facts are one strip of stat tiles (task 0260); notes stay text.
    $item = $inventory_purchase->get('item')->entity;
    $unit = $item instanceof FieldableEntityInterface ? $item->get('unit')->getString() : '';
    $disposed = !$inventory_purchase->get('disposal_date')->isEmpty();
    $reason = trim((string) preg_replace('/\s+/', ' ', $inventory_purchase->get('disposal_reason')->getString()));
    $quantity = $this->tileNumber($inventory_purchase->get('quantity')->getString(), 3, TRUE);
    $facts = $this->buildTileStrip($inventory_purchase, [
      'item' => [],
      'purchase_date' => ['label' => $this->t('Date')],
      'quantity' => ['value' => trim($quantity . ' ' . $unit), 'omit_empty' => TRUE],
      'unit_price' => [
        'label' => $this->t('Unit price'),
        'value' => $this->tileNumber($inventory_purchase->get('unit_price')->getString()),
        'omit_empty' => TRUE,
      ],
      'total_cost' => [
        'label' => $this->t('Total cost'),
        'value' => $this->tileNumber($inventory_purchase->get('total_cost')->getString()),
        'omit_empty' => TRUE,
      ],
      'supplier' => ['omit_empty' => TRUE],
      'disposal_date' => [
        'label' => $this->t('Disposed'),
        'sublabel' => $disposed ? Unicode::truncate($reason, 60, TRUE, TRUE) : '',
        'variant' => 'warning',
        'omit_empty' => TRUE,
      ],
      'apiary' => [],
    ]);
    if ($facts) {
      $build['facts'] = $facts;
    }
    if (!$inventory_purchase->get('notes')->isEmpty()) {
      $build['notes'] = $this->buildSection($this->t('Notes'), $inventory_purchase, ['notes']);
    }

    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($inventory_purchase);
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Title callback for the purchase view page.
   */
  public function title(InventoryPurchase $inventory_purchase) {
    return $inventory_purchase->label();
  }

  /**
   * {@inheritdoc}
   */
  protected function formatFieldValue(FieldableEntityInterface $entity, string $field_name, FieldItemListInterface $field): array {
    /** @var \Drupal\hivelog\Entity\InventoryPurchase $entity */
    switch ($field_name) {
      case 'apiary':
      case 'item':
        return $field->entity ? $field->entity->toLink()->toRenderable() : [
          '#plain_text' => (string) $this->t('—'),
        ];

      case 'purchase_date':
      case 'disposal_date':
        $timestamp = strtotime($field->value . ' 00:00:00 UTC');
        return [
          '#plain_text' => $timestamp !== FALSE
            ? $this->dateFormatter->format($timestamp, 'custom', 'Y-m-d')
            : (string) $field->value,
        ];

      case 'disposal_reason':
        return [
          '#markup' => nl2br(Html::escape((string) $field->value)),
        ];

      case 'quantity':
      case 'unit_price':
      case 'total_cost':
        return [
          '#plain_text' => number_format((float) $field->value, $field_name === 'quantity' ? 3 : 2),
        ];

      case 'notes':
        return [
          '#markup' => nl2br(Html::escape((string) $field->value)),
        ];

      default:
        return [
          '#plain_text' => (string) $field->value,
        ];
    }
  }

}
