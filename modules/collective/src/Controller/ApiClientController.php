<?php

declare(strict_types=1);

namespace Drupal\collective\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\collective\Entity\ApiClient;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for the API Client canonical page.
 *
 * A field summary plus the context-read endpoint URL (task 0091's own
 * explicit requirement — "states the endpoint URL plainly... in one
 * place", now down to one endpoint since the write-back route was
 * retired in task 0101) and the "Regenerate Token" action. Follows
 * ADR-0004 (custom controllers over view builders), matching every other
 * hivelog entity's canonical page.
 */
class ApiClientController extends ControllerBase {

  /**
   * Builds the API Client canonical page.
   *
   * @param \Drupal\collective\Entity\ApiClient $api_client
   *   The client to display.
   *
   * @return array
   *   A render array.
   */
  public function view(ApiClient $api_client): array {
    if (!$api_client->access('view')) {
      throw new AccessDeniedHttpException();
    }

    $rows = [
      [$this->t('Label'), $api_client->label()],
      [$this->t('Enabled'), $api_client->get('enabled')->value ? $this->t('Yes') : $this->t('No')],
    ];
    if (!$api_client->get('last_run')->isEmpty()) {
      $rows[] = [
        $this->t('Last run'),
        $this->dateFormatter()->format((int) $api_client->get('last_run')->value),
      ];
    }
    else {
      $rows[] = [$this->t('Last run'), $this->t('Never')];
    }

    $build['summary'] = [
      '#type' => 'table',
      '#header' => [$this->t('Field'), $this->t('Value')],
      '#attributes' => ['class' => ['hivelog-api-client-table']],
      '#attached' => ['library' => ['hivelog/tables']],
      '#rows' => $rows,
    ];

    $build['endpoints'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['collective-api-client-endpoints']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('API endpoint'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Context read: <code>GET @url</code>', [
            '@url' => Url::fromRoute('collective.api_client.context', [], ['absolute' => TRUE])->toString(),
          ]),
        ],
      ],
    ];

    if ($api_client->access('update')) {
      $build['regenerate'] = [
        '#type' => 'link',
        '#title' => $this->t('Regenerate Token'),
        '#url' => Url::fromRoute('collective.api_client.regenerate_token', ['api_client' => $api_client->id()]),
        '#attributes' => ['class' => ['button']],
      ];
    }

    return $build;
  }

  /**
   * Title callback for the canonical page.
   *
   * @param \Drupal\collective\Entity\ApiClient $api_client
   *   The client being displayed.
   *
   * @return string
   *   The page title.
   */
  public function title(ApiClient $api_client): string {
    return $api_client->label();
  }

  /**
   * The date formatter service.
   *
   * @return \Drupal\Core\Datetime\DateFormatterInterface
   *   The date formatter.
   */
  protected function dateFormatter() {
    return \Drupal::service('date.formatter');
  }

}
