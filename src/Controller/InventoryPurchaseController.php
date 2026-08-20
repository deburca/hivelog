<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\InventoryPurchase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Inventory Purchase pages.
 */
class InventoryPurchaseController extends ControllerBase {

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

    $build += [
      'overview' => $this->buildSection($this->t('Overview'), $inventory_purchase, [
        'apiary',
        'item',
        'purchase_date',
      ]),
      'cost' => $this->buildSection($this->t('Cost'), $inventory_purchase, [
        'quantity',
        'unit_price',
        'total_cost',
        'supplier',
      ]),
      'notes' => $this->buildSection($this->t('Notes'), $inventory_purchase, [
        'notes',
      ]),
    ];

    // Disposal is its own section, shown only once a disposal date is
    // actually recorded — matching how ApiaryController's Stock on Hand
    // section only appears for consumables, rather than always rendering
    // an empty section for the common (never-disposed) case.
    if (!$inventory_purchase->get('disposal_date')->isEmpty()) {
      $build['disposal'] = $this->buildSection($this->t('Disposal'), $inventory_purchase, [
        'disposal_date',
        'disposal_reason',
      ]);
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
   * Builds Edit and Delete action links for the purchase view.
   */
  protected function buildActions(InventoryPurchase $inventory_purchase): array {
    $buttons = [];
    if ($inventory_purchase->access('update')) {
      $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $inventory_purchase->toUrl('edit-form')->toString()];
    }
    if ($inventory_purchase->access('delete')) {
      $buttons[] = [
        'label' => (string) $this->t('Delete'),
        'url' => $inventory_purchase->toUrl('delete-form')->toString(),
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
   * Builds a consistently formatted purchase section.
   */
  protected function buildSection($title, InventoryPurchase $inventory_purchase, array $fields): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['hivelog-inventory-purchase-section'],
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
        '#rows' => $this->buildRows($inventory_purchase, $fields),
        '#attributes' => [
          'class' => ['hivelog-inventory-purchase-table'],
        ],
        '#attached' => ['library' => ['hivelog/tables']],
      ],
    ];
  }

  /**
   * Builds rows for a section table.
   */
  protected function buildRows(InventoryPurchase $inventory_purchase, array $fields): array {
    $rows = [];

    foreach ($fields as $field_name) {
      $rows[] = [
        [
          'data' => [
            '#plain_text' => (string) $inventory_purchase->get($field_name)->getFieldDefinition()->getLabel(),
          ],
        ],
        [
          'data' => $this->buildFieldValue($inventory_purchase, $field_name),
        ],
      ];
    }

    return $rows;
  }

  /**
   * Builds the display value for a single purchase field.
   */
  protected function buildFieldValue(InventoryPurchase $inventory_purchase, string $field_name): array {
    $field = $inventory_purchase->get($field_name);

    if ($field->isEmpty()) {
      return [
        '#plain_text' => (string) $this->t('—'),
      ];
    }

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
