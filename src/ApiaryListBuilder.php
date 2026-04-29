<?php

namespace Drupal\hivelog;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list builder for Apiary entities.
 */
class ApiaryListBuilder extends EntityListBuilder {

  use StringTranslationTrait;

  /**
   * The current user account.
   */
  protected AccountInterface $currentUser;

  /**
   * The user storage handler.
   */
  protected EntityStorageInterface $userStorage;

  /**
   * Constructs a new ApiaryListBuilder.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    AccountInterface $current_user,
    EntityStorageInterface $user_storage,
  ) {
    parent::__construct($entity_type, $storage);
    $this->currentUser = $current_user;
    $this->userStorage = $user_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $entity_type,
      $entity_type_manager->getStorage($entity_type->id()),
      $container->get('current_user'),
      $entity_type_manager->getStorage('user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['cbr'] = $this->t('CBR');
    $header['name'] = $this->t('Name');
    $header['location'] = $this->t('Location');
    $header['owner'] = $this->t('Owner');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $owner = $entity->getOwner();
    $row['cbr'] = $this->extractCbr($owner) ?: '—';
    $row['name'] = $entity->toLink();
    $row['location'] = $entity->get('location')->value
      ? mb_strimwidth($entity->get('location')->value, 0, 60, '...')
      : '';
    $row['owner'] = $owner ? $owner->getDisplayName() : '';
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    /** @var \Drupal\user\UserInterface|null $current */
    $current = $this->userStorage->load($this->currentUser->id());
    $cbr = $this->extractCbr($current);

    if ($cbr !== '') {
      $message = [
        '#markup' => $this->t('Your CBR number: @cbr', ['@cbr' => $cbr]),
      ];
    }
    elseif ($current) {
      $message = [
        '#type' => 'inline_template',
        '#template' => '{% trans %}You have not set a CBR number yet. <a href="{{ url }}">Update your profile</a> to add one.{% endtrans %}',
        '#context' => [
          'url' => $current->toUrl('edit-form')->toString(),
        ],
      ];
    }
    else {
      $message = [
        '#markup' => $this->t('Sign in to record your CBR number.'),
      ];
    }

    $build['cbr_summary'] = [
      '#type' => 'container',
      '#weight' => -100,
      '#attributes' => ['class' => ['hivelog-cbr-summary']],
      'message' => $message,
    ];

    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['user']);
    if ($current) {
      $cache->addCacheableDependency($current);
    }
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Extracts a trimmed CBR number from a user, if any.
   */
  protected function extractCbr(?UserInterface $user): string {
    if (!$user || !$user->hasField('cbr_number')) {
      return '';
    }
    return trim((string) $user->get('cbr_number')->value);
  }

}
