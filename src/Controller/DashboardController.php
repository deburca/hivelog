<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The HiveLog dashboard — the module's landing page at /hivelog.
 *
 * See ADR-0057 and task 0056. Criterion 2 builds the shell: the header
 * strip (current ISO week + the CBR summary line, moved here from
 * ApiaryListBuilder) and the first-run welcome state. The operational
 * widgets — needs-attention queue, stat tiles, upcoming, recent activity,
 * apiaries summary — are added by criteria 3–6; the stat-tile SDC and the
 * hivelog/dashboard CSS library land here so those criteria only add
 * markup.
 */
class DashboardController extends ControllerBase {

  /**
   * Constructs a DashboardController.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
  ) {
    // $entityTypeManager / $currentUser are untyped properties inherited
    // from ControllerBase; assign them rather than redeclaring them.
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * Renders the dashboard landing page.
   */
  public function view(): array {
    $cache = new CacheableMetadata();

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-dashboard']],
      '#attached' => ['library' => ['hivelog/dashboard']],
      'header' => $this->buildHeader($cache),
    ];

    // The apiaries this user may view. When there are none the whole
    // dashboard is the first-run welcome card; otherwise the interim body
    // stands in until criteria 3–6 replace it with the real widgets.
    $storage = $this->entityTypeManager->getStorage('apiary');
    $ids = $storage->getQuery()->accessCheck(TRUE)->execute();
    $apiaries = $ids ? $storage->loadMultiple($ids) : [];
    $apiaries = array_filter(
      $apiaries,
      fn($apiary) => $apiary->access('view', $this->currentUser)
    );
    $cache->addCacheTags($this->entityTypeManager->getDefinition('apiary')->getListCacheTags());

    $build['body'] = $apiaries ? $this->buildInterimBody() : $this->buildWelcome();

    // - user: the CBR line is per-user (not per-permission).
    // - max-age: the header prints the current ISO week, so the render must
    //   not outlive the week boundary — matches ApiaryController /
    //   HiveController's calendar sections.
    $cache
      ->addCacheContexts(['user'])
      ->setCacheMaxAge($this->secondsUntilNextIsoWeek());
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Builds the header strip: the ISO-week badge and the CBR summary line.
   */
  protected function buildHeader(CacheableMetadata $cache): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-dashboard__header']],
      'week' => [
        '#type' => 'inline_template',
        '#template' => '<p class="hivelog-dashboard__week">{% trans %}Week <strong>{{ week }}</strong> · {{ year }}{% endtrans %}</p>',
        '#context' => [
          'week' => (int) date('W'),
          'year' => (int) date('Y'),
        ],
      ],
      'cbr' => $this->buildCbrSummary($cache),
    ];
  }

  /**
   * Builds the current user's CBR summary line (moved from ApiaryListBuilder).
   */
  protected function buildCbrSummary(CacheableMetadata $cache): array {
    $user = NULL;
    if ($this->currentUser->isAuthenticated()) {
      /** @var \Drupal\user\UserInterface|null $user */
      $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    }
    if ($user) {
      $cache->addCacheableDependency($user);
    }

    $cbr = $this->extractCbr($user);
    if ($cbr !== '') {
      $message = ['#markup' => $this->t('Your CBR number: @cbr', ['@cbr' => $cbr])];
    }
    elseif ($user) {
      $message = [
        '#type' => 'inline_template',
        '#template' => '{% trans %}You have not set a CBR number yet. <a href="{{ url }}">Update your profile</a> to add one.{% endtrans %}',
        '#context' => ['url' => $user->toUrl('edit-form')->toString()],
      ];
    }
    else {
      $message = ['#markup' => $this->t('Sign in to record your CBR number.')];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-cbr-summary']],
      'message' => $message,
    ];
  }

  /**
   * Builds the first-run welcome card, shown when the user has no apiaries.
   */
  protected function buildWelcome(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-dashboard__welcome']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Welcome to HiveLog'),
      ],
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Start by adding an apiary — a location where you keep hives. Creating one seeds a 31-entry seasonal calendar you can adjust, then you can add hives, queens and inspections under it.'),
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-dashboard__welcome-actions']],
        'add' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Add your first apiary'),
            'url' => Url::fromRoute('entity.apiary.add_form')->toString(),
            'variant' => 'primary',
          ],
        ],
      ],
    ];
  }

  /**
   * Builds the interim body shown until the real widgets land (criteria 3–6).
   */
  protected function buildInterimBody(): array {
    return [
      '#type' => 'container',
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Dashboard widgets are being built. In the meantime, manage your beekeeping from the sections in the menu.'),
      ],
      'apiaries' => [
        '#type' => 'component',
        '#component' => 'hivelog:button',
        '#props' => [
          'label' => (string) $this->t('Go to Apiaries'),
          'url' => Url::fromRoute('entity.apiary.collection')->toString(),
        ],
      ],
    ];
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

  /**
   * Seconds remaining until the ISO week changes (next Monday, midnight).
   *
   * Bounds the cache max-age for the header's current-week badge so a
   * cached render never shows a stale week after the boundary passes.
   * Mirrors ApiaryController / HiveController.
   *
   * @return int
   *   Seconds until the next ISO week boundary.
   */
  protected function secondsUntilNextIsoWeek(): int {
    $now = new \DateTimeImmutable('now');
    $next_boundary = new \DateTimeImmutable('next monday midnight');
    return max(0, $next_boundary->getTimestamp() - $now->getTimestamp());
  }

}
