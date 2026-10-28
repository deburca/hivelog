<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * The friendly error pages (task 0238).
 *
 * Each page is a route a site can name as its 404 or 403 page
 * (`system.site:page.404`), so Drupal renders it inside the theme for a
 * missing or forbidden path. The route is open to everyone: a visitor who is
 * refused must still be able to read the page that says so. The headline is
 * the page title (the theme's H1); the body is the `hivelog:error-page`
 * component.
 */
class ErrorPageController extends ControllerBase {

  /**
   * Title of the page-not-found page.
   */
  public function notFoundTitle(): TranslatableMarkup {
    return $this->t('Oops! This hive is empty.');
  }

  /**
   * Builds the page-not-found page.
   *
   * @return array
   *   A render array.
   */
  public function notFound(): array {
    $link = Link::fromTextAndUrl($this->t('HiveLog home page'), Url::fromRoute('<front>'))->toString();

    return [
      '#type' => 'component',
      '#component' => 'hivelog:error-page',
      '#props' => [
        'scene' => 'not-found',
        'sign' => (string) $this->t('Vacant'),
      ],
      '#slots' => [
        'message' => [
          '#markup' => $this->t('These bees may have fled. Head back to the @home and pick up their trail from there.', ['@home' => $link]),
        ],
      ],
      '#cache' => ['tags' => ['config:system.site']],
    ];
  }

  /**
   * Title of the access-denied page.
   */
  public function accessDeniedTitle(): TranslatableMarkup {
    return $this->t("Hold on, this hive isn't yours.");
  }

  /**
   * Builds the access-denied page.
   *
   * A signed-out visitor is offered a sign-in link that returns them to the
   * page they were refused, since they may well be a member who is simply not
   * logged in; a signed-in one only the way home.
   *
   * @return array
   *   A render array.
   */
  public function accessDenied(): array {
    $home = Link::fromTextAndUrl($this->t('HiveLog home page'), Url::fromRoute('<front>'))->toString();

    if ($this->currentUser()->isAnonymous()) {
      $login_url = Url::fromRoute('user.login', [], ['query' => $this->getRedirectDestination()->getAsArray()]);
      $login = Link::fromTextAndUrl($this->t('Sign in'), $login_url)->toString();
      $message = $this->t("Only authorised bees are allowed in. @login to see if you're one of them, or head back to the @home.", [
        '@login' => $login,
        '@home' => $home,
      ]);
    }
    else {
      $message = $this->t('Only authorised bees are allowed in. Head back to the @home and carry on from there.', ['@home' => $home]);
    }

    return [
      '#type' => 'component',
      '#component' => 'hivelog:error-page',
      '#props' => [
        'scene' => 'access-denied',
        'sign' => (string) $this->t('Residents only'),
      ],
      '#slots' => [
        'message' => ['#markup' => $message],
      ],
      '#cache' => [
        'tags' => ['config:system.site'],
        'contexts' => ['user.roles:anonymous', 'url.query_args:destination'],
      ],
    ];
  }

}
