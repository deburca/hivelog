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
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog\CalendarActionAccessControlHandler;
use Drupal\hivelog\CalendarActionListBuilder;
use Drupal\hivelog\Form\CalendarActionDeleteForm;
use Drupal\hivelog\Form\CalendarActionForm;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Calendar Action entity.
 *
 * A CalendarAction is one recurring seasonal duty (varroa treatment,
 * harvest, winter prep, etc.) belonging to a single apiary. It is the
 * "plan" half of the seasonal calendar: shared by every hive in the
 * apiary, week-number scheduled rather than date-scheduled, and can be
 * disabled without losing history that HiveActionLog rows may reference.
 *
 * See docs/project-management/decisions/0025-seasonal-calendar-and-hive-action-tracking.md
 * for the full design.
 */
#[ContentEntityType(
  id: 'calendar_action',
  label: new TranslatableMarkup('Calendar Action'),
  label_collection: new TranslatableMarkup('Calendar Actions'),
  label_singular: new TranslatableMarkup('calendar action'),
  label_plural: new TranslatableMarkup('calendar actions'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'list_builder' => CalendarActionListBuilder::class,
    'form' => [
      'default' => CalendarActionForm::class,
      'add' => CalendarActionForm::class,
      'edit' => CalendarActionForm::class,
      'delete' => CalendarActionDeleteForm::class,
    ],
    'access' => CalendarActionAccessControlHandler::class,
  ],
  base_table: 'hivelog_calendar_action',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'label' => 'title',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'canonical' => '/hivelog/calendar-action/{calendar_action}',
    'edit-form' => '/hivelog/calendar-action/{calendar_action}/edit',
    'delete-form' => '/hivelog/calendar-action/{calendar_action}/delete',
  ],
)]
class CalendarAction extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * Illustrative starter calendar seeded on new apiaries.
   *
   * Deliberately as exhaustive as practical: it is trivial for a beekeeper
   * to disable or delete an entry that doesn't apply to them, but far more
   * effort to think of and add everything themselves from a blank
   * calendar. Covers the full annual cycle for a generic Northern
   * Hemisphere / temperate-climate apiary — treatments, buildup, swarm
   * management, requeening, both honey harvests, autumn/winter prep, and
   * routine record-keeping/registration reminders. Every seeded row is an
   * ordinary, fully editable/deletable `CalendarAction` — there is no
   * "is default" flag (see `seedDefaultsForApiary()`).
   *
   * Week numbers are illustrative starting points, not prescriptive —
   * exact timing depends on local climate and forage; a beekeeper should
   * adjust them (or the categories/descriptions) to match their own
   * conditions. Southern Hemisphere apiaries will want to shift every
   * entry by roughly half a year; this is a known, documented limitation
   * (see ADR-0025 Consequences), not a bug — hemisphere-aware seeding is
   * an explicit non-goal of this task.
   *
   * @see \Drupal\hivelog\Entity\CalendarAction::seedDefaultsForApiary()
   */
  const DEFAULT_STARTER_CALENDAR = [
    [
      'title' => 'Midwinter Cluster Check',
      'description' => "Brief external check on a mild day — do not open the hive.
- Heft to check stores.
- Clear dead bees from the entrance.
- Listen/knock check for cluster activity.",
      'category' => 'winter_prep',
      'week_start' => 1,
      'week_end' => 3,
    ],
    [
      'title' => 'Renew Central Beehive Registration (CBR)',
      'description' => "Registration/renewal is typically due early in the year.
- Confirm your apiary and hive details are up to date.
- Renew via the Central Beehive Registration (CBR) system if applicable in your jurisdiction.
- Update your recorded hive count for this apiary.",
      'category' => 'other',
      'week_start' => 2,
      'week_end' => 4,
    ],
    [
      'title' => 'Order & Prepare Equipment for the Season',
      'description' => "Get ahead of the season while workload is still low.
- Order new frames, foundation and woodware needed for the coming year.
- Clean and repair supers, floors and roofs stored over winter.
- Sterilise old equipment (blowtorch or washing soda solution) before reuse.",
      'category' => 'other',
      'week_start' => 4,
      'week_end' => 6,
    ],
    [
      'title' => 'Fondant / Emergency Feed Check',
      'description' => "Check stores are holding out through late winter.
- Heft each hive; top up fondant if it feels light.
- On a mild, still day, briefly check for signs of isolation starvation without fully opening the hive.
- Replace any fondant that has crystallised or dried out.",
      'category' => 'feeding',
      'week_start' => 5,
      'week_end' => 7,
    ],
    [
      'title' => 'Apiary Site & Hygiene Check',
      'description' => "General site maintenance ahead of the first full inspection.
- Check hive stands, straps and roofs after winter weather.
- Clear vegetation away from hive entrances.
- Check for woodpecker or other winter damage to woodware.",
      'category' => 'other',
      'week_start' => 7,
      'week_end' => 9,
    ],
    [
      'title' => 'Spring Inspection & Clean-up',
      'description' => "First full inspection once temperatures are reliably above 10°C.
- Check winter survival and queen status.
- Remove mouse guards/entrance reducers.
- Scrape frames and clean floors.",
      'category' => 'spring_buildup',
      'week_start' => 9,
      'week_end' => 11,
    ],
    [
      'title' => 'Assess Winter Losses & Consolidate Colonies',
      'description' => "Take stock of the apiary after winter.
- Record which colonies did not survive, and note a likely cause where possible.
- Consolidate weak survivors onto fewer frames to help them keep warm.
- Reduce entrances on any small nucleus colonies.",
      'category' => 'other',
      'week_start' => 10,
      'week_end' => 12,
    ],
    [
      'title' => 'Begin Spring Stimulative Feeding',
      'description' => "Support early brood rearing if stores or forage are short.
- Feed thin sugar syrup (roughly 1:1) to stimulate brood rearing.
- Only feed if stores are genuinely low or forage is scarce — avoid overfeeding a colony that has enough.
- Stop stimulative feeding once a natural nectar flow begins.",
      'category' => 'feeding',
      'week_start' => 10,
      'week_end' => 13,
    ],
    [
      'title' => 'Varroa Treatment (Spring)',
      'description' => "Assess and treat mite load before the main flow.
- Perform a mite wash or sugar roll count.
- Treat if above threshold, following label instructions.
- Remove supers before treating if required by the product.",
      'category' => 'varroa_treatment',
      'week_start' => 14,
      'week_end' => 16,
    ],
    [
      'title' => 'Equalise Colonies & Reverse Brood Boxes',
      'description' => "Even out colony strength as spring buildup accelerates.
- Move frames of brood or stores from very strong colonies to weaker ones if needed.
- Reverse brood boxes if the queen has moved fully into the top box, to free up laying space below.",
      'category' => 'spring_buildup',
      'week_start' => 14,
      'week_end' => 16,
    ],
    [
      'title' => 'Add Supers Ahead of the Flow',
      'description' => "Keep ahead of colony growth so bees never feel congested.
- Add a queen excluder and first super once the brood box holds 7-8 frames of bees.
- Add further supers ahead of need, not behind it — congestion drives swarming.",
      'category' => 'spring_buildup',
      'week_start' => 15,
      'week_end' => 17,
    ],
    [
      'title' => 'Set Bait Hives / Swarm Traps',
      'description' => "Prepare to capture swarms before they establish elsewhere.
- Position one or more bait hives (with old comb or lemongrass oil as an attractant) near the apiary.
- Check bait hives weekly through the swarm season.",
      'category' => 'swarm_prevention',
      'week_start' => 15,
      'week_end' => 17,
    ],
    [
      'title' => 'Swarm Prevention Check',
      'description' => "Check weekly for queen cells during peak swarm season.
- Look for swarm cells on the bottom of frames.
- Add supers/space ahead of need.
- Consider an artificial swarm if cells are found.",
      'category' => 'swarm_prevention',
      'week_start' => 19,
      'week_end' => 22,
    ],
    [
      'title' => 'Queen Rearing / Raise Replacement Queens',
      'description' => "Raise your own replacement and spare queens while drones are plentiful.
- Graft larvae, or select cells, from your best-performing colonies.
- Run queen-rearing nucs to mate the resulting virgin queens.
- Mark and record new queens as they emerge and begin laying.",
      'category' => 'requeening',
      'week_start' => 19,
      'week_end' => 22,
    ],
    [
      'title' => 'Split Strong Colonies / Make Increase',
      'description' => "Turn swarm pressure into planned increase rather than lost swarms.
- Make artificial swarms or nucleus splits from colonies already showing swarm preparations.
- A controlled split now is far preferable to losing an uncontrolled swarm later.",
      'category' => 'swarm_prevention',
      'week_start' => 20,
      'week_end' => 22,
    ],
    [
      'title' => 'Requeening Window (Introduce New Queens)',
      'description' => "Introduce queens raised earlier in the season.
- Introduce mated queens from spring queen rearing using a suitable introduction cage.
- Requeen any colony that is unusually defensive or shows a poor/spotty laying pattern.",
      'category' => 'requeening',
      'week_start' => 24,
      'week_end' => 26,
    ],
    [
      'title' => 'Harvest Spring Honey',
      'description' => "Harvest capped spring honey supers.
- Confirm at least 80% of the frame is capped before pulling.
- Leave adequate stores for the colony.
- Extract promptly and return wet supers for cleaning.",
      'category' => 'harvest_spring',
      'week_start' => 25,
      'week_end' => 27,
    ],
    [
      'title' => 'Mid-Season Health & Brood Disease Check',
      'description' => "A dedicated disease-focused check between the two harvests.
- Check the brood pattern closely for signs of AFB, EFB, chalkbrood or sacbrood.
- Report any suspected notifiable disease to your local authority/bee inspectorate promptly.",
      'category' => 'other',
      'week_start' => 28,
      'week_end' => 30,
    ],
    [
      'title' => 'Monitor Honey Stores & Add Supers',
      'description' => "Stay ahead of the summer flow.
- Add supers proactively so the colony never runs short of storage space.
- Check queen excluders are not clogged with wax or propolis, restricting bee movement.",
      'category' => 'harvest_summer',
      'week_start' => 29,
      'week_end' => 31,
    ],
    [
      'title' => 'Harvest Summer Honey',
      'description' => "Harvest capped summer honey supers.
- Confirm at least 80% of the frame is capped before pulling.
- Leave adequate stores for the colony.
- Extract promptly and return wet supers for cleaning.",
      'category' => 'harvest_summer',
      'week_start' => 31,
      'week_end' => 33,
    ],
    [
      'title' => 'Varroa Treatment (Late Summer)',
      'description' => "Treat again after the summer harvest and before winter bees are raised.
- Perform a mite wash or sugar roll count.
- Treat if above threshold, following label instructions.",
      'category' => 'varroa_treatment',
      'week_start' => 33,
      'week_end' => 35,
    ],
    [
      'title' => 'Requeen Failing Colonies',
      'description' => "Late-summer requeening gives a new queen time to establish before winter.
- Requeen any colony with a failing, drone-laying, or otherwise underperforming queen.
- Source locally-adapted stock where possible for better winter survival.",
      'category' => 'requeening',
      'week_start' => 33,
      'week_end' => 35,
    ],
    [
      'title' => 'Combine Weak Colonies',
      'description' => "Consolidate ahead of winter rather than overwintering colonies unlikely to survive.
- Unite any colony too weak to survive winter on its own, using the newspaper method.
- Never overwinter a colony you don't genuinely expect to make it through — combine or accept the loss now.",
      'category' => 'other',
      'week_start' => 36,
      'week_end' => 38,
    ],
    [
      'title' => 'Autumn Feeding',
      'description' => "Check and top up winter stores.
- Heft or weigh each hive.
- Feed sugar syrup or fondant as needed to reach target winter weight.",
      'category' => 'feeding',
      'week_start' => 37,
      'week_end' => 39,
    ],
    [
      'title' => 'Final Varroa Check & Treatment Follow-up',
      'description' => "Confirm the late-summer treatment worked before winter bees are fully raised.
- Recheck mite drop after the late-summer treatment.
- Apply a follow-up treatment if levels are still high.",
      'category' => 'varroa_treatment',
      'week_start' => 38,
      'week_end' => 40,
    ],
    [
      'title' => 'Mouse Guards & Woodpecker Protection',
      'description' => "Protect hives from common winter pests/predators.
- Fit mouse guards to every hive entrance.
- Fit woodpecker netting or wrap hives in areas where green woodpeckers are a known problem.",
      'category' => 'winter_prep',
      'week_start' => 40,
      'week_end' => 42,
    ],
    [
      'title' => 'Winter Preparation',
      'description' => "Prepare colonies for winter.
- Reduce entrances and fit mouse guards.
- Check final stores weight.
- Insulate/wrap hives if appropriate for your climate.",
      'category' => 'winter_prep',
      'week_start' => 41,
      'week_end' => 43,
    ],
    [
      'title' => 'Final Stores Weight Check',
      'description' => "Confirm every colony has enough to make it through winter.
- Heft or weigh each hive — a full-size colony typically needs a substantial stores reserve to see it through winter (roughly 18-25kg, depending on climate).
- Feed fondant immediately to any hive that feels light.",
      'category' => 'winter_prep',
      'week_start' => 42,
      'week_end' => 44,
    ],
    [
      'title' => 'Ventilation & Moisture Check',
      'description' => "Damp kills colonies faster than cold.
- Check hives have adequate ventilation to prevent condensation dripping onto the winter cluster.
- Tilt hives slightly forward if needed so any condensation drains away from the entrance.",
      'category' => 'winter_prep',
      'week_start' => 44,
      'week_end' => 46,
    ],
    [
      'title' => 'Midwinter Varroa Treatment (Broodless Period)',
      'description' => "The broodless period gives the most effective single mite treatment of the year.
- Apply an oxalic-acid-based (or equivalent) treatment once the colony is broodless, for maximum effectiveness.
- Choose a cold, dry, still day and treat quickly to minimise heat loss from the cluster.",
      'category' => 'varroa_treatment',
      'week_start' => 45,
      'week_end' => 48,
    ],
    [
      'title' => 'Apiary Record-Keeping & Season Review',
      'description' => "Close out the season's records while it's fresh in mind.
- Update hive, queen and inspection records for the year.
- Review which colonies to requeen, split, combine or replace next season.
- Note what worked and what didn't for next year's plan.",
      'category' => 'other',
      'week_start' => 49,
      'week_end' => 51,
    ],
  ];

  /**
   * Creates the default starter calendar for a newly created apiary.
   *
   * Called from Apiary::postSave() on insert only. Every seeded row is a
   * perfectly normal, fully editable/deletable CalendarAction — there is
   * no "is default" flag, so a beekeeper edits, disables, or deletes any
   * of them exactly like one they authored themselves. Seeding failures
   * are logged and swallowed by the caller so they never block the apiary
   * itself from being created.
   *
   * @param \Drupal\hivelog\Entity\Apiary $apiary
   *   The apiary to seed a starter calendar for.
   */
  public static function seedDefaultsForApiary(Apiary $apiary): void {
    foreach (static::DEFAULT_STARTER_CALENDAR as $definition) {
      static::create($definition + [
        'apiary' => $apiary->id(),
        'recurring' => TRUE,
        'enabled' => TRUE,
      ])->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Defensive invariant: week_end, when set, must not precede week_start.
    // CalendarActionForm::validateForm() already blocks this at the UI
    // layer; this guards programmatic creation (e.g. seeding, tests) too.
    $week_start = $this->get('week_start')->value;
    $week_end = $this->get('week_end')->value;
    if ($week_start !== NULL && $week_end !== NULL && $week_end !== ''
      && (int) $week_end < (int) $week_start) {
      throw new \InvalidArgumentException('CalendarAction week_end must be greater than or equal to week_start.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['apiary'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Apiary'))
      ->setDescription(t('The apiary this calendar action belongs to. Shared by every hive in the apiary.'))
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

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('A short name for this action, e.g. "Harvest Spring Honey".'))
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

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Description'))
      ->setDescription(t('The full description of the activity. Lines starting with "- " render as a bulleted list.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 2,
        'settings' => [
          'rows' => 6,
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['category'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Category'))
      ->setDescription(t('An optional classification for this action.'))
      ->setSetting('allowed_values', [
        'varroa_treatment' => 'Varroa Treatment',
        'feeding' => 'Feeding',
        'spring_buildup' => 'Spring Buildup',
        'swarm_prevention' => 'Swarm Prevention',
        'harvest_spring' => 'Harvest (Spring)',
        'harvest_summer' => 'Harvest (Summer)',
        'winter_prep' => 'Winter Preparation',
        'requeening' => 'Requeening',
        'other' => 'Other',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['week_start'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Week Start'))
      ->setDescription(t('The ISO-8601 week number (1-53) this action is due.'))
      ->setRequired(TRUE)
      ->setSetting('min', 1)
      ->setSetting('max', 53)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 4,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['week_end'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Week End'))
      ->setDescription(t('Optional end of the ISO-8601 week window (1-53), if this action spans multiple weeks. Leave empty to treat it as a single week.'))
      ->setSetting('min', 1)
      ->setSetting('max', 53)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 5,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['recurring'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Recurring'))
      ->setDescription(t('Whether this action recurs at the same week(s) every year.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => 6,
        'settings' => [
          'format' => 'yes-no',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['enabled'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Enabled'))
      ->setDescription(t('Disabled actions are hidden from the apiary calendar and every hive checklist, but remain listed here for management.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 7,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => 7,
        'settings' => [
          'format' => 'yes-no',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user who created this calendar action.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 8,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the calendar action was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the calendar action was last updated.'));

    return $fields;
  }

}
