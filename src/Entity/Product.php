<?php

declare(strict_types=1);

namespace Drupal\hivelog\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog\Form\ProductDeleteForm;
use Drupal\hivelog\Form\ProductForm;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\hivelog\ProductAccessControlHandler;
use Drupal\hivelog\ProductListBuilder;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Product entity.
 *
 * A Product is one catalog entry for something a beekeeper produces and
 * sells — honey, beeswax, propolis — scoped to a single apiary. Unlike
 * `InventoryItem`, there is no separate purchase ledger: `expected_unit_price`
 * lives directly on the product as a single mutable current-best-guess
 * price, since potential income is an aggregate assumption, not an audited
 * sales record (see ADR-0034's confirmed decision).
 *
 * See docs/project-management/decisions/0034-honey-wax-propolis-yield-and-potential-income.md
 * for the full design.
 */
#[ContentEntityType(
  id: 'product',
  label: new TranslatableMarkup('Product'),
  label_collection: new TranslatableMarkup('Products'),
  label_singular: new TranslatableMarkup('product'),
  label_plural: new TranslatableMarkup('products'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'list_builder' => ProductListBuilder::class,
    'form' => [
      'default' => ProductForm::class,
      'add' => ProductForm::class,
      'edit' => ProductForm::class,
      'delete' => ProductDeleteForm::class,
    ],
    'access' => ProductAccessControlHandler::class,
  ],
  base_table: 'hivelog_product',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'label' => 'name',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'canonical' => '/hivelog/product/{product}',
    'add-form' => '/hivelog/product/add',
    'edit-form' => '/hivelog/product/{product}/edit',
    'delete-form' => '/hivelog/product/{product}/delete',
    'collection' => '/hivelog/products',
  ],
)]
class Product extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['apiary'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Apiary'))
      ->setDescription(t('The apiary this product belongs to.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'apiary')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('A short name for this product, e.g. "Honey" or "Beeswax".'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['unit'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Unit'))
      ->setDescription(t('The unit this product is measured in, e.g. "kg", "jar", "bar". Pick one unit and use it consistently for this product — there is no unit conversion.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 32)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['expected_unit_price'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Expected Unit Price'))
      ->setDescription(t('Your current best estimate of what a unit of this product sells for. A single editable assumption, not a sales history — potential income is computed from this figure, not from actual recorded sales.'))
      ->setRequired(TRUE)
      ->setSetting('precision', 10)
      ->setSetting('scale', 2)
      ->setSetting('min', 0)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('Discontinued products are hidden from new yield-recipe selection, but remain listed here for management.'))
      ->setRequired(TRUE)
      ->setDefaultValue('active')
      ->setSetting('allowed_values', [
        'active' => 'Active',
        'discontinued' => 'Discontinued',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 4,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user who created this product.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the product was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the product was last updated.'));

    return $fields;
  }

}
