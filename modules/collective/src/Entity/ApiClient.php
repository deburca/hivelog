<?php

declare(strict_types=1);

namespace Drupal\collective\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\collective\ApiClientAccessControlHandler;
use Drupal\collective\ApiClientListBuilder;
use Drupal\collective\Form\ApiClientDeleteForm;
use Drupal\collective\Form\ApiClientForm;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the API Client entity — a site-level context-read credential.
 *
 * Not tied to one hive/apiary the way `SensorDevice` is — normally
 * exactly one row, provisioned by an administrator, authenticating GET
 * access to `HiveContextBuilder`'s output at
 * `collective.api_client.context`. Deliberately generic per
 * docs/project-management/decisions/0100-nexus-in-process-ai-synthesis.md
 * Decision §4: not AI-specific — the consumer may be the `nexus`
 * submodule (which actually reads context via a direct PHP service call,
 * not this HTTP route), a beekeeper's own script, or anything else that
 * holds a valid token.
 *
 * Renamed from `InsightAgent` (task 0089/0091) by task 0101 — the
 * previous name and its "insight write-back" half are retired, not this
 * entity's own credential shape, which carries over unchanged: same
 * `password_hash()`/`password_verify()` mechanism, same "never returned
 * in plaintext except immediately after generation/regeneration"
 * contract as `\Drupal\nanoprobe\Entity\SensorDevice` — independently
 * implemented here rather than shared, since the two entities live in
 * different, mutually-independent optional submodules per
 * docs/project-management/decisions/0098-nanoprobe-collective-locutus-submodule-split.md.
 */
#[ContentEntityType(
  id: 'api_client',
  label: new TranslatableMarkup('API Client'),
  label_collection: new TranslatableMarkup('API Clients'),
  label_singular: new TranslatableMarkup('API client'),
  label_plural: new TranslatableMarkup('API clients'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'access' => ApiClientAccessControlHandler::class,
    'list_builder' => ApiClientListBuilder::class,
    'form' => [
      'default' => ApiClientForm::class,
      'add' => ApiClientForm::class,
      'edit' => ApiClientForm::class,
      'delete' => ApiClientDeleteForm::class,
    ],
  ],
  base_table: 'collective_api_client',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'canonical' => '/hivelog/api-client/{api_client}',
    'add-form' => '/hivelog/api-client/add',
    'edit-form' => '/hivelog/api-client/{api_client}/edit',
    'delete-form' => '/hivelog/api-client/{api_client}/delete',
    'collection' => '/hivelog/api-clients',
  ],
)]
class ApiClient extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * The plaintext token generated in this request, if any.
   *
   * Never persisted — the `token` field always stores a `password_hash()`
   * digest, never the plaintext value. NULL after a normal load from
   * storage — the plaintext is not recoverable once this request ends.
   */
  protected ?string $plainTextToken = NULL;

  /**
   * Generates a new random token, stores its hash, and returns the plaintext.
   *
   * Called automatically from `preSave()` on insert if no token has been
   * set; call directly to regenerate an existing client's token, which
   * immediately invalidates the previous one.
   *
   * @return string
   *   The plaintext token — the only time it is ever available.
   */
  public function generateToken(): string {
    $plaintext = bin2hex(random_bytes(32));
    $this->set('token', password_hash($plaintext, PASSWORD_DEFAULT));
    $this->plainTextToken = $plaintext;
    return $plaintext;
  }

  /**
   * The plaintext token generated in this request, or NULL.
   *
   * @return string|null
   *   The plaintext token, or NULL if none was generated in this request.
   */
  public function getPlainTextToken(): ?string {
    return $this->plainTextToken;
  }

  /**
   * Whether $provided matches this client's stored token hash.
   *
   * @param string $provided
   *   The bearer token presented by a caller.
   *
   * @return bool
   *   TRUE if $provided hashes to the same value stored on this client.
   */
  public function verifyToken(string $provided): bool {
    $hash = $this->get('token')->value;
    return is_string($hash) && $hash !== '' && password_verify($provided, $hash);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    if ($this->isNew() && $this->get('token')->isEmpty()) {
      $this->generateToken();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setDescription(t('A short, human-readable name for this client, e.g. "Production API Client".'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Deliberately no display options — this stores a password_hash()
    // digest and must never be exposed via the Field UI or a view
    // display, only through generateToken()'s one-time plaintext return.
    $fields['token'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Token'))
      ->setDescription(t('Server-generated credential hash. Never shown in plaintext except immediately after generation or regeneration.'))
      ->setSetting('max_length', 255);

    $fields['enabled'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Enabled'))
      ->setDescription(t('A disabled client\'s context-read requests are rejected outright, not silently accepted.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => 1,
        'settings' => [
          'format' => 'yes-no',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['last_run'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last Run'))
      ->setDescription(t('Updated on every successful context read.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user who provisioned this client.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time this client was registered.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time this client was last updated.'));

    return $fields;
  }

}
