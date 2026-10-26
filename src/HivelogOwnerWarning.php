<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Session\AccountInterface;
use Drupal\hivelog\Entity\Apiary;

/**
 * Builds the "more than two owners" warning shown to those who can fix it.
 *
 * Task 0232. An apiary changes hands as "A adds B as owner; later B removes A",
 * so two owners is normal for a while. More than two usually means a hand-over
 * that was never finished.
 */
final class HivelogOwnerWarning {

  /**
   * The most owners an apiary should have, even during a hand-over.
   */
  const MAX_OWNERS = 2;

  /**
   * Builds the warning for an apiary, if there is one to show.
   *
   * @param \Drupal\hivelog\Entity\Apiary $apiary
   *   The apiary.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The viewer: only someone who may manage the apiary's members sees it.
   *
   * @return array
   *   A render array: the warning, or only cache metadata when there is none.
   */
  public static function build(Apiary $apiary, AccountInterface $account): array {
    $cache = [
      'tags' => array_merge($apiary->getCacheTags(), ['apiary_membership_list', 'user_list']),
      'contexts' => ['user'],
    ];
    $owners = $apiary->getOwnerMemberships();
    if (count($owners) <= self::MAX_OWNERS || !$apiary->access('update', $account)) {
      return ['#cache' => $cache];
    }

    $names = [];
    foreach ($owners as $membership) {
      $user = $membership->getMember();
      $names[] = $user ? $user->getDisplayName() : '?';
    }
    $primary = $apiary->getPrimaryOwner();

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['messages', 'messages--warning'], 'role' => 'alert'],
      'text' => [
        '#markup' => t('This apiary has @count owners (@names), but during a hand-over it should have at most @max. Remove the owner who is leaving. The apiary carries the CBR number of @primary, who has been an owner the longest.', [
          '@count' => count($owners),
          '@names' => implode(', ', $names),
          '@max' => self::MAX_OWNERS,
          '@primary' => $primary ? $primary->getDisplayName() : '?',
        ]),
      ],
      '#cache' => $cache,
    ];
  }

}
