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
use Drupal\hivelog\ApiaryAccessControlHandler;
use Drupal\hivelog\ApiaryListBuilder;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\hivelog\Form\HivelogEntityDeleteForm;
use Drupal\hivelog\Form\ApiaryForm;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Apiary entity.
 */
#[ContentEntityType(
  id: 'apiary',
  label: new TranslatableMarkup('Apiary'),
  label_collection: new TranslatableMarkup('Apiaries'),
  label_singular: new TranslatableMarkup('apiary'),
  label_plural: new TranslatableMarkup('apiaries'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'list_builder' => ApiaryListBuilder::class,
    'form' => [
      'default' => ApiaryForm::class,
      'add' => ApiaryForm::class,
      'edit' => ApiaryForm::class,
      'delete' => HivelogEntityDeleteForm::class,
    ],
    'access' => ApiaryAccessControlHandler::class,
  ],
  base_table: 'hivelog_apiary',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'label' => 'name',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'canonical' => '/hivelog/apiary/{apiary}',
    'add-form' => '/hivelog/apiary/add',
    'edit-form' => '/hivelog/apiary/{apiary}/edit',
    'delete-form' => '/hivelog/apiary/{apiary}/delete',
    'collection' => '/hivelog/apiaries',
  ],
)]
class Apiary extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * Per-request cache of roles: apiary id => user id => role key.
   *
   * @var array<int, array<int, string>>
   */
  protected static array $roleCache = [];

  /**
   * Forgets the cached roles; called whenever a membership changes.
   */
  public static function resetRoleCache(): void {
    static::$roleCache = [];
  }

  /**
   * Gets the role an account holds in this apiary, if any.
   *
   * Access comes from the current membership only (task 0224): never from
   * the apiary's `uid`, and never from having created a record.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return string|null
   *   `owner`, `keeper` or `novice`, or NULL if the account is not a member.
   */
  public function getRoleOf(AccountInterface $account): ?string {
    if ($this->isNew() || !$account->id()) {
      return NULL;
    }
    $apiary_id = (int) $this->id();
    if (!isset(static::$roleCache[$apiary_id])) {
      static::$roleCache[$apiary_id] = [];
      $storage = \Drupal::entityTypeManager()->getStorage('apiary_membership');
      foreach ($storage->loadByProperties(['apiary' => $apiary_id]) as $membership) {
        /** @var \Drupal\hivelog\Entity\ApiaryMembership $membership */
        static::$roleCache[$apiary_id][(int) $membership->get('account')->target_id] = $membership->getRole();
      }
    }
    return static::$roleCache[$apiary_id][(int) $account->id()] ?? NULL;
  }

  /**
   * Checks whether the account holds at least the given role here.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $minimum
   *   The role key: `novice`, `keeper` or `owner`.
   *
   * @return bool
   *   TRUE if the account's role ranks at or above the minimum.
   */
  public function hasRoleAtLeast(AccountInterface $account, string $minimum): bool {
    $role = $this->getRoleOf($account);
    return $role !== NULL
      && (ApiaryMembership::ROLE_RANK[$role] ?? 0) >= (ApiaryMembership::ROLE_RANK[$minimum] ?? PHP_INT_MAX);
  }

  /**
   * Checks whether the given account is a member of this apiary, any role.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check.
   *
   * @return bool
   *   TRUE if the account has a membership here.
   */
  public function isApiaryMember(AccountInterface $account): bool {
    return $this->getRoleOf($account) !== NULL;
  }

  /**
   * Checks whether the given account is an owner of this apiary.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check.
   *
   * @return bool
   *   TRUE if the account's membership has the `owner` role.
   */
  public function isOwner(AccountInterface $account): bool {
    return $this->getRoleOf($account) === 'owner';
  }

  /**
   * Gets this apiary's members with their roles.
   *
   * @return \Drupal\hivelog\Entity\ApiaryMembership[]
   *   The membership entities, indexed by numeric key.
   */
  public function getApiaryMembers(): array {
    // Memberships point at the apiary, not the other way round, so the
    // membership rows are the source of truth.
    if ($this->isNew()) {
      return [];
    }
    $storage = \Drupal::entityTypeManager()->getStorage('apiary_membership');
    $ids = $storage->getQuery()
      ->condition('apiary', $this->id())
      ->sort('id')
      ->accessCheck(FALSE)
      ->execute();
    $members = array_values($storage->loadMultiple($ids));
    return $members;
  }

  /**
   * Checks whether this apiary is publicly visible.
   *
   * @return bool
   *   TRUE if the visibility field is set to 'public'.
   */
  public function isPublic(): bool {
    return $this->get('visibility')->value === 'public';
  }

  /**
   * {@inheritdoc}
   *
   * Seeds a default starter calendar (see
   * CalendarAction::DEFAULT_STARTER_CALENDAR) the first time an apiary is
   * created, so a new apiary starts with a usable, fully-editable seasonal
   * plan instead of an empty one — see ADR-0025. Only runs on insert
   * (`!$update`); re-saving an existing apiary never re-seeds or
   * duplicates it. A seeding failure is logged and swallowed rather than
   * propagated, so it can never block the apiary itself from being
   * created.
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    if (!$update) {
      $this->ensureOwnerMembership();
      try {
        CalendarAction::seedDefaultsForApiary($this);
      }
      catch (\Exception $e) {
        \Drupal::logger('hivelog')->error(
          'Failed to seed the default starter calendar for apiary @id: @message',
          ['@id' => $this->id(), '@message' => $e->getMessage()]
        );
      }
    }
  }

  /**
   * Gives a new apiary's creator an `owner` membership.
   *
   * Access comes from memberships, so without this the person who creates an
   * apiary would not be able to open it. `uid` stays as "created by".
   */
  protected function ensureOwnerMembership(): void {
    $uid = (int) $this->getOwnerId();
    if ($uid <= 0) {
      return;
    }
    $storage = \Drupal::entityTypeManager()->getStorage('apiary_membership');
    if ($storage->loadByProperties(['apiary' => $this->id(), 'account' => $uid])) {
      return;
    }
    $storage->create([
      'apiary' => $this->id(),
      'account' => $uid,
      'role' => 'owner',
      'uid' => $uid,
    ])->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the apiary.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['location'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Location'))
      ->setDescription(t('Address or description of the apiary location.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 1,
        'settings' => [
          'rows' => 3,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['geolocation'] = BaseFieldDefinition::create('geofield')
      ->setLabel(t('Geolocation'))
      ->setDescription(t('GPS coordinates of the apiary. Click or drag the marker on the map to set the location.'))
      ->setDisplayOptions('form', [
        'type' => 'leaflet_widget_default',
        'weight' => 2,
        'settings' => [
          'map' => [
            'leaflet_map' => 'OSM Mapnik',
            'height' => 300,
            'auto_center' => TRUE,
            // Where an apiary with no location yet starts: Denmark. (The
            // widget reads `map_position`; a bare `center` key is ignored.)
            'map_position' => [
              'force' => FALSE,
              'center' => ['lat' => 55.9, 'lon' => 10.5],
              'zoom' => 7,
              'minZoom' => 1,
              'maxZoom' => 18,
              'zoomFiner' => 0,
            ],
          ],
          'input' => [
            'show' => FALSE,
          ],
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'leaflet_formatter_default',
        'weight' => 2,
        'settings' => [
          'leaflet_map' => 'OSM Mapnik',
          'height' => 200,
          'height_unit' => 'px',
          'hide_empty_map' => TRUE,
          'disable_wheel' => TRUE,
          'map_position' => [
            'force' => FALSE,
            'center' => ['lat' => 0, 'lon' => 0],
            'zoom' => 14,
            'minZoom' => 1,
            'maxZoom' => 18,
            'zoomFiner' => 0,
          ],
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('General notes about the apiary.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 4,
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['visibility'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Visibility'))
      ->setDescription(t('Controls whether non-members can view this apiary and its hives. Private apiaries are only visible to the owner and approved members.'))
      ->setRequired(TRUE)
      ->setDefaultValue('private')
      ->setSetting('allowed_values', [
        'private' => 'Private',
        'public' => 'Public',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Opt-in, not opt-out — mirrors this field's own shape (a per-apiary
    // toggle, not a global setting), per
    // docs/project-management/decisions/0087-ai-insights-hosting-and-privacy-model.md.
    // Consumed by the optional `collective` submodule; core itself does
    // nothing with this value beyond storing it — a beekeeper with no
    // AI-insights module installed simply has an unused flag, the same
    // small, accepted trade-off as ApiaryAccessTrait's sensor_device/
    // hive_insight resolution branches (see
    // docs/project-management/decisions/0098-nanoprobe-collective-locutus-submodule-split.md
    // §6).
    $fields['ai_insights_enabled'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('AI insights enabled'))
      ->setDescription(t('Allow an insight agent to read this apiary\'s data and generate AI-assisted recommendations. Off by default — turning this on shares apiary/hive data (never precise location) with whatever service the provisioned insight agent uses.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user who owns this apiary.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 8,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the apiary was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the apiary was last updated.'));

    return $fields;
  }

}
