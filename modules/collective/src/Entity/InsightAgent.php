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
use Drupal\collective\Form\InsightAgentDeleteForm;
use Drupal\collective\Form\InsightAgentForm;
use Drupal\collective\InsightAgentAccessControlHandler;
use Drupal\collective\InsightAgentListBuilder;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Insight Agent entity — a site-level AI-insight credential.
 *
 * Not tied to one hive/apiary the way `SensorDevice` is — normally
 * exactly one row, provisioned by an administrator, authenticating the
 * external process that reads hive/apiary context and writes back
 * `HiveInsight` rows. Per
 * docs/project-management/decisions/0088-ai-insights-implementation.md
 * §1, deliberately has no `apiary`/`hive`/`scope` field.
 *
 * Token generation/verification mirrors
 * `\Drupal\nanoprobe\Entity\SensorDevice` exactly (same
 * `password_hash()`/`password_verify()` mechanism, same "never returned
 * in plaintext except immediately after generation/regeneration"
 * contract) — independently implemented here rather than shared, since
 * the two entities live in different, mutually-independent optional
 * submodules per
 * docs/project-management/decisions/0098-nanoprobe-collective-locutus-submodule-split.md.
 */
#[ContentEntityType(
  id: 'insight_agent',
  label: new TranslatableMarkup('Insight Agent'),
  label_collection: new TranslatableMarkup('Insight Agents'),
  label_singular: new TranslatableMarkup('insight agent'),
  label_plural: new TranslatableMarkup('insight agents'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'access' => InsightAgentAccessControlHandler::class,
    'list_builder' => InsightAgentListBuilder::class,
    'form' => [
      'default' => InsightAgentForm::class,
      'add' => InsightAgentForm::class,
      'edit' => InsightAgentForm::class,
      'delete' => InsightAgentDeleteForm::class,
    ],
  ],
  base_table: 'collective_insight_agent',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'canonical' => '/hivelog/insight-agent/{insight_agent}',
    'add-form' => '/hivelog/insight-agent/add',
    'edit-form' => '/hivelog/insight-agent/{insight_agent}/edit',
    'delete-form' => '/hivelog/insight-agent/{insight_agent}/delete',
    'collection' => '/hivelog/insight-agents',
  ],
)]
class InsightAgent extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

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
   * set; call directly to regenerate an existing agent's token, which
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
   * Whether $provided matches this agent's stored token hash.
   *
   * @param string $provided
   *   The bearer token presented by a client.
   *
   * @return bool
   *   TRUE if $provided hashes to the same value stored on this agent.
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
      ->setDescription(t('A short, human-readable name for this agent, e.g. "Production Insight Agent".'))
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
      ->setDescription(t('A disabled agent\'s context-read and insight-write requests are rejected outright, not silently accepted.'))
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
      ->setDescription(t('Updated on every successful batch completion.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user who provisioned this agent.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time this agent was registered.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time this agent was last updated.'));

    return $fields;
  }

}
