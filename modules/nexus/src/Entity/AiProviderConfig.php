<?php

declare(strict_types=1);

namespace Drupal\nexus\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\nexus\AiProviderConfigAccessControlHandler;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the AI Provider Config entity — how nexus reaches an AI provider.
 *
 * **Not a renamed `ApiClient`/`InsightAgent`.** `ApiClient`'s token is
 * deliberately one-way-hashed, correct for *verifying* a credential
 * presented *to* hivelog. This entity needs the opposite: a credential
 * hivelog sends *to* a third-party provider, which must be recoverable,
 * not just verifiable — so it never stores that credential itself. It
 * stores which integration mode (`MODES`), a provider identifier
 * (direct-API mode), an optional custom endpoint URL (custom-endpoint
 * mode), and a `key` field holding a `Key` module config-entity id. The
 * actual secret is resolved only at call time, via
 * `\Drupal::service('key.repository')->getKey($id)->getKeyValue()`
 * (task 0103's `nexus_cron()`), never persisted here in any form. See
 * docs/project-management/decisions/0100-nexus-in-process-ai-synthesis.md
 * Decision §2/§3 and its Implementation notes.
 *
 * Normally exactly one row per beekeeper, provisioned by an
 * administrator — same site-level-credential shape as `ApiClient`, same
 * `administer hivelog`-only create, same own/any access pattern.
 */
#[ContentEntityType(
  id: 'ai_provider_config',
  label: new TranslatableMarkup('AI Provider Config'),
  label_collection: new TranslatableMarkup('AI Provider Configs'),
  label_singular: new TranslatableMarkup('AI provider config'),
  label_plural: new TranslatableMarkup('AI provider configs'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'access' => AiProviderConfigAccessControlHandler::class,
  ],
  base_table: 'nexus_ai_provider_config',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
)]
class AiProviderConfig extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * The three provider integration modes, per ADR-0100 Decision §3.
   *
   * Checked in this priority order at call time: `ai_module` (Drupal AI
   * module, if installed/configured), `direct_api` (nexus's own minimal
   * HTTP client), `custom_endpoint` (a user-supplied URL + auth header).
   */
  const MODES = [
    'ai_module' => 'Drupal AI module',
    'direct_api' => 'Direct provider API',
    'custom_endpoint' => 'Custom endpoint',
  ];

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    $mode = $this->get('mode')->value;
    if (!isset(self::MODES[$mode])) {
      throw new \InvalidArgumentException("AiProviderConfig mode '$mode' is not a recognised mode.");
    }

    // A Drupal-AI-module config delegates credential storage to that
    // module's own provider plugins — no `key` reference is meaningful
    // here. The other two modes call a provider directly and need one.
    if ($mode !== 'ai_module' && $this->get('key')->isEmpty()) {
      throw new \InvalidArgumentException("AiProviderConfig key is required when mode is '$mode'.");
    }

    if ($mode === 'direct_api' && $this->get('provider')->isEmpty()) {
      throw new \InvalidArgumentException('AiProviderConfig provider is required when mode is direct_api.');
    }

    if ($mode === 'custom_endpoint' && $this->get('endpoint_url')->isEmpty()) {
      throw new \InvalidArgumentException('AiProviderConfig endpoint_url is required when mode is custom_endpoint.');
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
      ->setDescription(t('A short, human-readable name for this config, e.g. "Production AI Provider".'))
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

    $fields['mode'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Mode'))
      ->setDescription(t('Which of the three provider integration modes this config uses.'))
      ->setRequired(TRUE)
      ->setDefaultValue('direct_api')
      ->setSetting('allowed_values', self::MODES)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['provider'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Provider'))
      ->setDescription(t('The direct-API provider identifier, e.g. "anthropic". Only meaningful when mode is "Direct provider API".'))
      ->setSetting('max_length', 64)
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

    $fields['endpoint_url'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('Custom Endpoint URL'))
      ->setDescription(t('Only meaningful when mode is "Custom endpoint". nexus POSTs a fixed request shape here and expects a fixed response shape back — see ADR-0100 Decision §3.'))
      ->setDisplayOptions('form', [
        'type' => 'uri',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'uri_link',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Holds a `Key` config-entity id, not a secret — resolved to the
    // actual credential only at call time via key.repository. Task
    // 0104 gives this field's form widget the `key_select` element;
    // until then it's a plain textfield holding the same id.
    $fields['key'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Key'))
      ->setDescription(t('The Key module entity that stores the actual provider credential. Required unless mode is "Drupal AI module" (which manages its own credentials).'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['enabled'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Enabled'))
      ->setDescription(t('A disabled config is skipped by nexus_cron() entirely.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 5,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => 5,
        'settings' => [
          'format' => 'yes-no',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['last_run'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last Run'))
      ->setDescription(t('Updated on every successful nexus_cron() batch completion using this config.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user who provisioned this config.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time this config was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time this config was last updated.'));

    return $fields;
  }

}
