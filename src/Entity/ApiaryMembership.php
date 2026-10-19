<?php

declare(strict_types=1);

namespace Drupal\hivelog\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog\ApiaryMembershipAccessControlHandler;
use Drupal\hivelog\Form\HivelogEntityDeleteForm;
use Drupal\hivelog\Form\ApiaryMembershipForm;
use Drupal\hivelog\HivelogEntityStorage;

/**
 * Defines the ApiaryMembership entity.
 *
 * Links a user to an apiary with a specific role (owner, editor, viewer).
 * One row per apiary-user pair enforced by schema unique key and a constraint
 * on the add form. Replaces the legacy `beekeepers` reference field on Apiary.
 */
#[ContentEntityType(
  id: 'apiary_membership',
  label: new TranslatableMarkup('Apiary membership'),
  label_collection: new TranslatableMarkup('Apiary memberships'),
  label_singular: new TranslatableMarkup('apiary membership'),
  label_plural: new TranslatableMarkup('apiary memberships'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'list_builder' => ApiaryMembershipListBuilder::class,
    'form' => [
      'default' => ApiaryMembershipForm::class,
      'add' => ApiaryMembershipForm::class,
      'edit' => ApiaryMembershipForm::class,
      'delete' => HivelogEntityDeleteForm::class,
    ],
    'access' => ApiaryMembershipAccessControlHandler::class,
  ],
  base_table: 'hivelog_apiary_membership',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'label' => 'account',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'canonical' => '/hivelog/apiary/{apiary}/membership/{apiary_membership}',
    'add-form' => '/hivelog/apiary/{apiary}/membership/add',
    'edit-form' => '/hivelog/apiary/{apiary}/membership/{apiary_membership}/edit',
    'delete-form' => '/hivelog/apiary/{apiary}/membership/{apiary_membership}/delete',
  ],
)]
class ApiaryMembership extends ContentEntityBase implements EntityChangedInterface {

  use EntityChangedTrait;

  /**
   * Allowed role values: owner, editor, viewer.
   */
  const ROLES = [
    'owner' => 'Owner',
    'editor' => 'Editor',
    'viewer' => 'Viewer',
  ];

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Ensure uid (owner) is set on insert for the entity system to work.
    if ($this->isNew() && !$this->get('uid')->entity) {
      $this->set('uid', \Drupal::currentUser());
    }

    // Guard against duplicate apiary-user memberships (backstop for
    // the schema unique key; forms check first for a friendly message).
    if ($this->isNew()) {
      $existing = \Drupal::entityQuery('apiary_membership')
        ->condition('apiary', $this->get('apiary')->target_id)
        ->condition('account', $this->get('account')->target_id)
        ->accessCheck(FALSE)
        ->execute();
      if ($existing) {
        throw new \InvalidArgumentException(
          t('A membership for this apiary and user already exists.')
        );
      }
    }
  }

  /**
   * Gets this membership's role value.
   *
   * @return string
   *   The role ('owner', 'editor', or 'viewer').
   */
  public function getRole(): string {
    return $this->get('role')->value;
  }

  /**
   * Gets the member user entity for this membership.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user, or NULL if not yet loaded.
   */
  public function getMember(): ?AccountInterface {
    return $this->get('account')->entity;
  }

  /**
   * Gets the parent Apiary entity for this membership.
   *
   * @return \Drupal\hivelog\Entity\Apiary|null
   *   The apiary, or NULL if not yet loaded.
   */
  public function getApiary(): ?Apiary {
    return $this->get('apiary')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['apiary'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Apiary'))
      ->setDescription(t('The apiary this membership belongs to.'))
      ->setSetting('target_type', 'apiary')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['account'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Account'))
      ->setDescription(t('The user who has this membership.'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 1])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['role'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Role'))
      ->setDescription(t('The role granted to this member within the apiary.'))
      ->setRequired(TRUE)
      ->setDefaultValue('viewer')
      ->setSetting('allowed_values', self::ROLES)
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 2])
      ->setDisplayConfigurable('form', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Created by'))
      ->setDescription(t('The user who created this membership record.'))
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the membership was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the membership was last updated.'));

    return $fields;
  }

}
