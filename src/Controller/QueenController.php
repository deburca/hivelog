<?php

namespace Drupal\hivelog\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\Queen;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Queen pages.
 */
class QueenController extends ControllerBase {

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder,
  ) {
    // $entityTypeManager and $entityFormBuilder are untyped properties
    // inherited from ControllerBase; assign them rather than redeclaring
    // them with types.
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
    );
  }

  /**
   * Provides the add form for a queen within a hive context.
   *
   * Pre-populates the queen's `hive` reference so the user does not have to
   * pick the hive manually when adding from the hive page.
   */
  public function addForm(Hive $hive) {
    $queen = $this->entityTypeManager->getStorage('queen')->create([
      'hive' => $hive->id(),
      'status' => 'active',
    ]);
    return $this->entityFormBuilder->getForm($queen, 'add');
  }

  /**
   * Displays a queen entity.
   */
  public function view(Queen $queen) {
    $view_builder = $this->entityTypeManager->getViewBuilder('queen');
    $build = [
      'queen' => $view_builder->view($queen),
    ];

    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($queen);
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Title callback for the queen view page.
   */
  public function title(Queen $queen) {
    return $queen->label();
  }

}
