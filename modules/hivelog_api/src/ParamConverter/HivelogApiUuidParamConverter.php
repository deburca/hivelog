<?php

declare(strict_types=1);

namespace Drupal\hivelog_api\ParamConverter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Symfony\Component\Routing\Route;

/**
 * Loads an entity by UUID for a route parameter typed `hivelog_api_uuid:<type>`.
 *
 * An API client knows records by UUID (the JSON:API id), not by Drupal's
 * integer id. An unknown UUID converts to NULL, which is a 404; access to a
 * record that exists is still the route's `_entity_access` check.
 */
class HivelogApiUuidParamConverter implements ParamConverterInterface {

  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $entity_type_id = substr($definition['type'], strlen('hivelog_api_uuid:'));
    $entities = $this->entityTypeManager->getStorage($entity_type_id)->loadByProperties(['uuid' => $value]);
    return $entities ? reset($entities) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return !empty($definition['type']) && str_starts_with($definition['type'], 'hivelog_api_uuid:');
  }

}
