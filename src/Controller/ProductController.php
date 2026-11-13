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
use Drupal\hivelog\Entity\Product;
use Drupal\hivelog\HivelogDetailPageTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Product pages.
 */
class ProductController extends ControllerBase {

  use HivelogDetailPageTrait;

  /**
   * Constructs a ProductController.
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
   * Provides the add form for a product within an apiary context.
   */
  public function addForm(Apiary $apiary) {
    $product = $this->entityTypeManager->getStorage('product')->create([
      'apiary' => $apiary->id(),
    ]);
    return $this->entityFormBuilder->getForm($product, 'add');
  }

  /**
   * Displays a product with its fields grouped into readable sections.
   */
  public function view(Product $product) {
    $build = [
      'actions' => $this->buildActions($product),
    ];

    // The facts are one strip of stat tiles (task 0260); the name is the title.
    $facts = $this->buildTileStrip($product, [
      'unit' => ['omit_empty' => TRUE],
      'expected_unit_price' => [
        'label' => $this->t('Expected price'),
        'value' => $this->tileNumber($product->get('expected_unit_price')->getString()),
        'omit_empty' => TRUE,
      ],
      'status' => ['omit_empty' => TRUE],
      'apiary' => [],
    ]);
    if ($facts) {
      $build['facts'] = $facts;
    }

    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($product);
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Title callback for the product view page.
   */
  public function title(Product $product) {
    return $product->label();
  }

  /**
   * {@inheritdoc}
   */
  protected function formatFieldValue(FieldableEntityInterface $entity, string $field_name, FieldItemListInterface $field): array {
    /** @var \Drupal\hivelog\Entity\Product $entity */
    switch ($field_name) {
      case 'apiary':
        return $field->entity ? $field->entity->toLink()->toRenderable() : [
          '#plain_text' => (string) $this->t('—'),
        ];

      case 'status':
        $allowed_values = $field->getSetting('allowed_values');
        return [
          '#plain_text' => (string) ($allowed_values[$field->value] ?? $field->value),
        ];

      case 'expected_unit_price':
        return [
          '#plain_text' => number_format((float) $field->value, 2),
        ];

      default:
        return [
          '#plain_text' => (string) $field->value,
        ];
    }
  }

}
