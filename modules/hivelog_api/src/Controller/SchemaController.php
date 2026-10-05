<?php

declare(strict_types=1);

namespace Drupal\hivelog_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\hivelog_api\HivelogApiResources;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Describes the writable fields of the exposed types, for building forms.
 *
 * JSON:API says nothing about a field's allowed values, labels or limits, and
 * a form that hard-codes them in the app goes stale the day the module adds an
 * option (task 0205). So the app asks here, and the lists it shows are the ones
 * the entity classes declare in `baseFieldDefinitions()`: the same lists the
 * server validates against, so an option the app offers is never refused.
 *
 * Only static facts about the data model, never user data.
 */
class SchemaController extends ControllerBase {

  /**
   * Fields the server owns: never offered for editing.
   */
  protected const SERVER_FIELDS = ['id', 'uuid', 'uid', 'created', 'changed'];

  public function __construct(
    protected EntityFieldManagerInterface $fieldManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self($container->get('entity_field.manager'));
  }

  /**
   * Returns {"data": {"<type>": {"fields": {"<field>": {...}}}}}.
   */
  public function index(): CacheableJsonResponse {
    $types = [];
    foreach (HivelogApiResources::narrowedTypes() as $type) {
      $fields = [];
      foreach ($this->fieldManager->getFieldDefinitions($type, $type) as $name => $definition) {
        if (in_array($name, self::SERVER_FIELDS, TRUE) || $definition->isComputed() || $definition->isReadOnly()) {
          continue;
        }
        $fields[$name] = $this->describe($definition);
      }
      $types[$type] = ['fields' => $fields];
    }

    $response = new CacheableJsonResponse(['data' => $types], 200, ['Content-Type' => 'application/vnd.api+json']);
    $response->addCacheableDependency((new CacheableMetadata())
      ->addCacheContexts(['languages:language_interface'])
      ->addCacheTags(['entity_field_info']));
    return $response;
  }

  /**
   * Describes one field in the terms a form needs.
   *
   * @return array<string, mixed>
   *   `kind` is the control a form should use: boolean, integer, number, text
   *   (single line), long_text, date, choice (one of `options`), reference (to
   *   the resource type `target`) or images; anything else is `other` and a
   *   client should leave it alone.
   */
  protected function describe(FieldDefinitionInterface $definition): array {
    $storage = $definition->getFieldStorageDefinition();
    $type = $definition->getType();
    $description = $definition->getDescription();
    $info = [
      'kind' => match ($type) {
        'boolean' => 'boolean',
        'integer' => 'integer',
        'float', 'decimal' => 'number',
        'string' => 'text',
        'string_long', 'text_long' => 'long_text',
        'datetime' => 'date',
        'list_string' => 'choice',
        'entity_reference' => 'reference',
        'image' => 'images',
        default => 'other',
      },
      'label' => (string) $definition->getLabel(),
      'required' => $definition->isRequired(),
      'multiple' => $storage->getCardinality() !== 1,
    ];
    if ($description !== NULL && (string) $description !== '') {
      $info['description'] = (string) $description;
    }
    if ($type === 'list_string') {
      $options = [];
      foreach ((array) $storage->getSetting('allowed_values') as $value => $label) {
        $options[] = ['value' => (string) $value, 'label' => (string) $label];
      }
      $info['options'] = $options;
    }
    foreach (['min', 'max', 'max_length'] as $setting) {
      $value = $definition->getSetting($setting);
      if ($value !== NULL && $value !== '') {
        $info[$setting] = $value + 0;
      }
    }
    if ($type === 'entity_reference') {
      $target = (string) $definition->getSetting('target_type');
      $info['target'] = $target . '--' . $target;
    }
    return $info;
  }

}
