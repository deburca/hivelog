<?php

declare(strict_types=1);

namespace Drupal\collective\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\collective\Entity\InsightAgent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for the Insight Agent canonical page.
 *
 * A field summary plus the two endpoint URLs (task 0091's own explicit
 * requirement — "states the two endpoint URLs plainly... in one place")
 * and the "Regenerate Token" action. Follows ADR-0004 (custom
 * controllers over view builders), matching every other hivelog entity's
 * canonical page.
 */
class InsightAgentController extends ControllerBase {

  /**
   * Builds the Insight Agent canonical page.
   *
   * @param \Drupal\collective\Entity\InsightAgent $insight_agent
   *   The agent to display.
   *
   * @return array
   *   A render array.
   */
  public function view(InsightAgent $insight_agent): array {
    if (!$insight_agent->access('view')) {
      throw new AccessDeniedHttpException();
    }

    $rows = [
      [$this->t('Label'), $insight_agent->label()],
      [$this->t('Enabled'), $insight_agent->get('enabled')->value ? $this->t('Yes') : $this->t('No')],
    ];
    if (!$insight_agent->get('last_run')->isEmpty()) {
      $rows[] = [
        $this->t('Last run'),
        $this->dateFormatter()->format((int) $insight_agent->get('last_run')->value),
      ];
    }
    else {
      $rows[] = [$this->t('Last run'), $this->t('Never')];
    }

    $build['summary'] = [
      '#type' => 'table',
      '#rows' => $rows,
    ];

    $build['endpoints'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['collective-insight-agent-endpoints']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('API endpoints'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Context read: <code>GET @url</code>', [
            '@url' => Url::fromRoute('collective.hive_insight.contexts', [], ['absolute' => TRUE])->toString(),
          ]),
          $this->t('Insight write-back: <code>POST @url</code>', [
            '@url' => Url::fromRoute('collective.hive_insight.write', [], ['absolute' => TRUE])->toString(),
          ]),
        ],
      ],
    ];

    if ($insight_agent->access('update')) {
      $build['regenerate'] = [
        '#type' => 'link',
        '#title' => $this->t('Regenerate Token'),
        '#url' => Url::fromRoute('collective.insight_agent.regenerate_token', ['insight_agent' => $insight_agent->id()]),
        '#attributes' => ['class' => ['button']],
      ];
    }

    return $build;
  }

  /**
   * Title callback for the canonical page.
   *
   * @param \Drupal\collective\Entity\InsightAgent $insight_agent
   *   The agent being displayed.
   *
   * @return string
   *   The page title.
   */
  public function title(InsightAgent $insight_agent): string {
    return $insight_agent->label();
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
