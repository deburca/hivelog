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
use Drupal\hivelog\ApiaryMembershipListBuilder;
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
   * Allowed role values: owner, keeper, novice (task 0224).
   *
   * Labels are the English source strings; the member form and the
   * translations show them. `keeper` is "Beekeeper": the key avoids a clash
   * with the Drupal site role `beekeeper` that permissions and tests use.
   */
  const ROLES = [
    'owner' => 'Owner',
    'keeper' => 'Beekeeper',
    'novice' => 'Novice',
  ];

  /**
   * The roles ordered by what they may do; a higher rank includes a lower.
   *
   * Novice (read) < keeper (every action except user, owner and apiary
   * settings management) < owner (everything).
   */
  const ROLE_RANK = [
    'novice' => 1,
    'keeper' => 2,
    'owner' => 3,
  ];

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Ensure uid (owner) is set on insert for the entity system to work.
    // Set the id, not the AccountProxy: an entity reference needs an id or a
    // real entity, and the proxy is neither.
    if ($this->isNew() && $this->get('uid')->isEmpty()) {
      $this->set('uid', \Drupal::currentUser()->id());
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
          (string) t('A membership for this apiary and user already exists.')
        );
      }
    }

    // When this membership became an owner membership (task 0232): the owner
    // who has held the role longest is the apiary's primary owner, whose CBR
    // number the apiary carries. Set when the role becomes `owner` (a new owner,
    // or a member promoted), cleared when it stops being one, and otherwise kept.
    if ($this->getRole() === 'owner') {
      if ($this->get('owner_since')->isEmpty()) {
        $this->set('owner_since', \Drupal::time()->getRequestTime());
      }
    }
    elseif (!$this->get('owner_since')->isEmpty()) {
      $this->set('owner_since', NULL);
    }

    // An apiary always keeps an owner: the last owner cannot be demoted
    // (the form says so first; this is the backstop for other callers).
    if (!$this->isNew() && $this->getRole() !== 'owner' && $this->isLastOwner(TRUE)) {
      throw new \InvalidArgumentException(
        (string) t('The last owner of an apiary cannot be given another role.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    Apiary::resetRoleCache();
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);
    Apiary::resetRoleCache();
  }

  /**
   * Whether this is the only owner membership of its apiary.
   *
   * @param bool $stored
   *   TRUE to ask whether the STORED row is the apiary's only owner (a
   *   membership being demoted is still `owner` in storage while its new
   *   value is not), FALSE to ask about the current values.
   *
   * @return bool
   *   TRUE if this membership is, or in storage was, the apiary's only owner.
   */
  public function isLastOwner(bool $stored = FALSE): bool {
    $apiary_id = $this->get('apiary')->target_id;
    if (!$apiary_id) {
      return FALSE;
    }
    if ($stored) {
      $original = $this->original ?? NULL;
      if (!$original || $original->get('role')->value !== 'owner') {
        return FALSE;
      }
    }
    elseif ($this->getRole() !== 'owner') {
      return FALSE;
    }
    $owners = \Drupal::entityQuery('apiary_membership')
      ->condition('apiary', $apiary_id)
      ->condition('role', 'owner')
      ->accessCheck(FALSE)
      ->execute();
    return count($owners) <= 1;
  }

  /**
   * {@inheritdoc}
   *
   * The link templates are nested under the apiary, so every URL needs it.
   */
  protected function urlRouteParameters($rel) {
    $parameters = parent::urlRouteParameters($rel);
    $parameters['apiary'] = $this->get('apiary')->target_id;
    return $parameters;
  }

  /**
   * Gets the translated label of a role.
   *
   * `ROLES` holds the English source strings (like every list in this module,
   * option labels are untranslated); this is for text a person reads.
   *
   * @param string $role
   *   The role key: `owner`, `keeper` or `novice`.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label.
   */
  public static function roleLabel(string $role): TranslatableMarkup {
    return match ($role) {
      'owner' => new TranslatableMarkup('Owner'),
      'keeper' => new TranslatableMarkup('Beekeeper'),
      'novice' => new TranslatableMarkup('Novice'),
      default => new TranslatableMarkup('@role', ['@role' => $role]),
    };
  }

  /**
   * Gets this membership's role value.
   *
   * @return string
   *   The role ('owner', 'keeper' or 'novice').
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
      ->setDefaultValue('novice')
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

    $fields['owner_since'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Owner since'))
      ->setDescription(t('When this membership became an owner membership. The owner who has held the role longest is the apiary\'s primary owner, whose CBR number the apiary carries.'))
      ->setReadOnly(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the membership was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the membership was last updated.'));

    return $fields;
  }

}
