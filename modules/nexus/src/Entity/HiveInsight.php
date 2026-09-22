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
use Drupal\nexus\HiveInsightAccessControlHandler;

/**
 * Defines the Hive Insight entity — one AI-synthesised recommendation.
 *
 * The three-way daily verdict per
 * docs/project-management/decisions/0083-ai-assisted-apiary-insights.md:
 * an action to take now, a reason to inspect soon, or an explicit "all
 * clear." Machine-written only, by `nexus_cron()`'s own in-process write
 * (task 0103) — no add/edit form is ever built for this entity type,
 * same "machine-written only" shape as `nanoprobe`'s `SensorReading`.
 *
 * Relocated here from `collective` by task 0102, per
 * docs/project-management/decisions/0100-nexus-in-process-ai-synthesis.md
 * Decision §2 — schema unchanged from
 * [[0089-insight-agent-and-hive-insight-entities]]/
 * [[0088-ai-insights-implementation]] §1, only the owning module and base
 * table (`nexus_hive_insight`, was `collective_hive_insight`) moved.
 * `\Drupal\hivelog\ApiaryAccessTrait::resolveApiary()`'s `hive_insight`
 * branch (added to core in task 0089) needed no change — it only checks
 * `$entity->getEntityTypeId()`, not which module defines the class.
 */
#[ContentEntityType(
  id: 'hive_insight',
  label: new TranslatableMarkup('Hive Insight'),
  label_collection: new TranslatableMarkup('Hive Insights'),
  label_singular: new TranslatableMarkup('hive insight'),
  label_plural: new TranslatableMarkup('hive insights'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'access' => HiveInsightAccessControlHandler::class,
  ],
  base_table: 'nexus_hive_insight',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
)]
class HiveInsight extends ContentEntityBase implements EntityChangedInterface {

  use EntityChangedTrait;

  /**
   * The code-defined three-way verdict a HiveInsight may carry.
   *
   * Per [[0083-ai-assisted-apiary-insights]]: an action to take now, a
   * reason to inspect soon, or an explicit "all clear" — the positive
   * confirmation that monitoring ran and found nothing wrong, which
   * nothing else in hivelog provides anywhere else.
   */
  const VERDICTS = [
    'act_now' => 'Act now',
    'inspect_soon' => 'Inspect soon',
    'all_clear' => 'All clear',
  ];

  /**
   * The code-defined confidence levels a HiveInsight may carry.
   */
  const CONFIDENCE_LEVELS = [
    'high' => 'High',
    'medium' => 'Medium',
    'low' => 'Low',
  ];

  /**
   * {@inheritdoc}
   */
  public function label() {
    $verdict = self::VERDICTS[$this->get('verdict')->value] ?? $this->get('verdict')->value;
    $hive = $this->get('hive')->entity;
    $apiary = $this->get('apiary')->entity;
    $target = $hive ? $hive->label() : ($apiary ? $apiary->label() : t('Unknown'));
    return t('@verdict — @target', [
      '@verdict' => $verdict,
      '@target' => $target,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Defensive invariant: hive is required when scope = hive, and must
    // be unset when scope = apiary — same shape as
    // \Drupal\nanoprobe\Entity\SensorDevice's own guard.
    $scope = $this->get('scope')->value;
    $hive_id = $this->get('hive')->target_id;
    if ($scope === 'hive' && !$hive_id) {
      throw new \InvalidArgumentException('HiveInsight hive is required when scope is hive.');
    }
    if ($scope === 'apiary' && $hive_id) {
      throw new \InvalidArgumentException('HiveInsight hive must not be set when scope is apiary.');
    }

    $verdict = $this->get('verdict')->value;
    if (!isset(self::VERDICTS[$verdict])) {
      throw new \InvalidArgumentException("HiveInsight verdict '$verdict' is not a recognised verdict.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['apiary'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Apiary'))
      ->setDescription(t('The apiary this insight belongs to.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'apiary')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['hive'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Hive'))
      ->setDescription(t('The specific hive this insight covers. Required when scope is "Hive"; must be left empty when scope is "Apiary".'))
      ->setSetting('target_type', 'hive')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['scope'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Scope'))
      ->setDescription(t('A hive-scoped insight covers one specific hive. An apiary-scoped insight covers the whole site — reuses the same duality as CalendarAction.scope and SensorDevice.scope.'))
      ->setRequired(TRUE)
      ->setDefaultValue('hive')
      ->setSetting('allowed_values', [
        'hive' => 'Hive',
        'apiary' => 'Apiary',
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['verdict'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Verdict'))
      ->setDescription(t('The three-way recommendation: act now, inspect soon, or all clear.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', self::VERDICTS)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['recommendation'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Recommendation'))
      ->setDescription(t('A short imperative sentence, e.g. "Add a super" or "Possible swarm risk — inspect within 2 days".'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['signals'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Signals'))
      ->setDescription(t('The specific signals that drove this verdict — mandatory explainability per ADR-0083 §4, never a bare verdict. Lines starting with "- " render as a bulleted list (same convention as CalendarAction.description; render with \Drupal\hivelog\Utility\SimpleBulletText::render()).'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['confidence'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Confidence'))
      ->setDescription(t('How confident the provider is in this verdict, if it reports one.'))
      ->setSetting('allowed_values', self::CONFIDENCE_LEVELS)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['context_snapshot'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Context Snapshot'))
      ->setDescription(t('A copy of what HiveContextBuilder produced for this hive/apiary at generation time — an audit trail for what the provider actually saw.'));

    $fields['generated'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Generated'))
      ->setDescription(t('When nexus produced this insight.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time this insight was written.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time this insight was last updated.'));

    return $fields;
  }

}
