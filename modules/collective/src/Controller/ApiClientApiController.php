<?php

declare(strict_types=1);

namespace Drupal\collective\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\collective\Entity\ApiClient;
use Drupal\collective\HiveContextBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The context-read endpoint any credentialed API client needs.
 *
 * Per docs/project-management/decisions/0100-nexus-in-process-ai-synthesis.md
 * Decision §1/§4: the insight write-back endpoint task 0090 originally
 * built here is retired outright — `nexus` writes `HiveInsight` itself,
 * in the same process that reads context, so no bearer-token write
 * exchange with itself is needed. This route carries `_access: 'TRUE'` —
 * no Drupal permission gate, no session CSRF token — exactly mirroring
 * [[0077-sensor-ingestion-endpoint-and-device-auth]]'s own documented
 * exception: the caller is a stateless, credentialed external process,
 * authenticated here via `ApiClient`'s own bearer token, a deliberate,
 * narrow exception to the module's normal
 * `ApiaryAccessTrait`/session-CSRF model, scoped to this one route.
 */
class ApiClientApiController extends ControllerBase {

  public function __construct(
    protected HiveContextBuilder $contextBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('collective.hive_context_builder'));
  }

  /**
   * Returns one minimised context object per opted-in hive.
   *
   * `GET /hivelog/api/collective/context` — the actual enforcement point
   * for `Apiary.ai_insights_enabled`: a hive under an apiary that hasn't
   * opted in never appears in this response, under any `?hive=` value.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The inbound request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   200 with a JSON array of context objects; 401 for a missing or
   *   unrecognised token; 403 for a disabled client.
   */
  public function contexts(Request $request): JsonResponse {
    $client = $this->resolveClientFromToken($request);
    if ($client === NULL) {
      return new JsonResponse(['error' => 'Invalid or missing client token.'], Response::HTTP_UNAUTHORIZED);
    }
    if (!$client->get('enabled')->value) {
      return new JsonResponse(['error' => 'This API client is disabled.'], Response::HTTP_FORBIDDEN);
    }

    $hives = $this->loadOptedInHives($request->query->get('hive'));

    $contexts = [];
    foreach ($hives as $hive) {
      $contexts[] = $this->contextBuilder->buildContext($hive);
    }

    $client->set('last_run', \Drupal::time()->getRequestTime());
    $client->save();

    return new JsonResponse($contexts, Response::HTTP_OK);
  }

  /**
   * Resolves the `ApiClient` matching the request's bearer token.
   *
   * Checks every client, not only enabled ones, so a token that matches
   * a disabled client can be distinguished (403) from a token that
   * matches no client at all (401) — same approach as
   * `\Drupal\nanoprobe\Controller\SensorIngestController`.
   */
  protected function resolveClientFromToken(Request $request): ?ApiClient {
    $header = $request->headers->get('Authorization', '');
    if (!str_starts_with($header, 'Bearer ')) {
      return NULL;
    }
    $token = substr($header, strlen('Bearer '));
    if ($token === '') {
      return NULL;
    }

    $storage = $this->entityTypeManager()->getStorage('api_client');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    if (empty($ids)) {
      return NULL;
    }

    /** @var \Drupal\collective\Entity\ApiClient $client */
    foreach ($storage->loadMultiple($ids) as $client) {
      if ($client->verifyToken($token)) {
        return $client;
      }
    }

    return NULL;
  }

  /**
   * Loads hives whose apiary has AI insights enabled.
   *
   * @param string|null $hive_filter
   *   The raw `?hive=` query value, if given.
   *
   * @return \Drupal\hivelog\Entity\Hive[]
   *   Opted-in hives. If $hive_filter is given but doesn't identify an
   *   opted-in hive, an empty array — never an error — so a
   *   misconfigured/malicious query can't distinguish "wrong id" from
   *   "not opted in" from the response shape.
   */
  protected function loadOptedInHives(?string $hive_filter): array {
    $apiary_storage = $this->entityTypeManager()->getStorage('apiary');
    $apiary_ids = $apiary_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('ai_insights_enabled', TRUE)
      ->execute();
    if (empty($apiary_ids)) {
      return [];
    }

    $hive_storage = $this->entityTypeManager()->getStorage('hive');
    $query = $hive_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('apiary', $apiary_ids, 'IN');
    if ($hive_filter !== NULL && $hive_filter !== '') {
      $query->condition('id', $hive_filter);
    }
    $hive_ids = $query->execute();

    return $hive_ids ? array_values($hive_storage->loadMultiple($hive_ids)) : [];
  }

}
