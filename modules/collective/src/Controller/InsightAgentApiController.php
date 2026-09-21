<?php

declare(strict_types=1);

namespace Drupal\collective\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\collective\Entity\HiveInsight;
use Drupal\collective\Entity\InsightAgent;
use Drupal\collective\HiveContextBuilder;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The two endpoints an external insight-generating agent needs.
 *
 * Per docs/project-management/decisions/0088-ai-insights-implementation.md
 * §2: reads minimised hive context, writes a `HiveInsight` back. Both
 * routes carry `_access: 'TRUE'` — no Drupal permission gate, no session
 * CSRF token — exactly mirroring
 * [[0077-sensor-ingestion-endpoint-and-device-auth]]'s own documented
 * exception: the caller is a stateless, credentialed external process,
 * authenticated here via `InsightAgent`'s own bearer token, not a
 * deliberate, narrow exception to the module's normal
 * `ApiaryAccessTrait`/session-CSRF model, scoped to these two routes.
 */
class InsightAgentApiController extends ControllerBase {

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
   * `GET /hivelog/api/hive-insights/contexts` — the actual enforcement
   * point for `Apiary.ai_insights_enabled`: a hive under an apiary that
   * hasn't opted in never appears in this response, under any `?hive=`
   * value.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The inbound request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   200 with a JSON array of context objects; 401 for a missing or
   *   unrecognised token; 403 for a disabled agent.
   */
  public function contexts(Request $request): JsonResponse {
    $agent = $this->resolveAgentFromToken($request);
    if ($agent === NULL) {
      return new JsonResponse(['error' => 'Invalid or missing agent token.'], Response::HTTP_UNAUTHORIZED);
    }
    if (!$agent->get('enabled')->value) {
      return new JsonResponse(['error' => 'This agent is disabled.'], Response::HTTP_FORBIDDEN);
    }

    $hives = $this->loadOptedInHives($request->query->get('hive'));

    $contexts = [];
    foreach ($hives as $hive) {
      $contexts[] = $this->contextBuilder->buildContext($hive);
    }

    return new JsonResponse($contexts, Response::HTTP_OK);
  }

  /**
   * Accepts a single insight write-back.
   *
   * `POST /hivelog/api/hive-insights`. Not a batch, unlike the sensor
   * ingestion endpoint — an agent produces at most one recommendation
   * per hive per run.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The inbound request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   201 with the created id on success; 401 for a missing/unrecognised
   *   token; 403 for a disabled agent; 422 for a malformed body or a
   *   validation failure; 404 if the referenced hive/apiary's apiary
   *   does not have `ai_insights_enabled = TRUE` — server-side
   *   defence-in-depth, even though the agent should never have
   *   received that hive from contexts() in the first place.
   */
  public function write(Request $request): JsonResponse {
    $agent = $this->resolveAgentFromToken($request);
    if ($agent === NULL) {
      return new JsonResponse(['error' => 'Invalid or missing agent token.'], Response::HTTP_UNAUTHORIZED);
    }
    if (!$agent->get('enabled')->value) {
      return new JsonResponse(['error' => 'This agent is disabled.'], Response::HTTP_FORBIDDEN);
    }

    $body = $this->parseBody($request);
    if ($body === NULL) {
      return new JsonResponse(['error' => 'Request body must be a single JSON object.'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    [$errors, $values] = $this->validateAndResolve($body);
    if (!empty($errors)) {
      return new JsonResponse(['error' => 'Validation failed.', 'details' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    if (!$values['target_apiary']->get('ai_insights_enabled')->value) {
      return new JsonResponse(['error' => 'This apiary does not have AI insights enabled.'], Response::HTTP_NOT_FOUND);
    }

    $insight = HiveInsight::create([
      'apiary' => $values['target_apiary']->id(),
      'hive' => $values['hive']?->id(),
      'scope' => $values['scope'],
      'verdict' => $values['verdict'],
      'recommendation' => $values['recommendation'],
      'signals' => $values['signals'],
      'confidence' => $values['confidence'],
      'context_snapshot' => $values['context_snapshot'],
      'generated' => $values['generated_timestamp'],
    ]);
    $insight->save();

    $agent->set('last_run', \Drupal::time()->getRequestTime());
    $agent->save();

    return new JsonResponse(['id' => $insight->id()], Response::HTTP_CREATED);
  }

  /**
   * Resolves the `InsightAgent` matching the request's bearer token.
   *
   * Checks every agent, not only enabled ones, so a token that matches a
   * disabled agent can be distinguished (403) from a token that matches
   * no agent at all (401) — same approach as
   * `\Drupal\nanoprobe\Controller\SensorIngestController`.
   */
  protected function resolveAgentFromToken(Request $request): ?InsightAgent {
    $header = $request->headers->get('Authorization', '');
    if (!str_starts_with($header, 'Bearer ')) {
      return NULL;
    }
    $token = substr($header, strlen('Bearer '));
    if ($token === '') {
      return NULL;
    }

    $storage = $this->entityTypeManager()->getStorage('insight_agent');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    if (empty($ids)) {
      return NULL;
    }

    /** @var \Drupal\collective\Entity\InsightAgent $agent */
    foreach ($storage->loadMultiple($ids) as $agent) {
      if ($agent->verifyToken($token)) {
        return $agent;
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

  /**
   * Decodes the request body into an associative array.
   *
   * @return array|null
   *   The decoded body, or NULL if it isn't valid JSON or isn't a JSON
   *   object (a JSON array is rejected — this endpoint takes exactly
   *   one insight per request, unlike the sensor ingestion endpoint's
   *   batch support).
   */
  protected function parseBody(Request $request): ?array {
    try {
      $decoded = json_decode($request->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      return NULL;
    }

    if (!is_array($decoded) || array_is_list($decoded)) {
      return NULL;
    }
    return $decoded;
  }

  /**
   * Validates the write-back body and resolves its entity references.
   *
   * @return array
   *   `[$errors, $values]` — $errors is a list of human-readable
   *   messages (empty if valid); $values holds the resolved/parsed
   *   fields (`scope`, `hive` (a Hive|null), `target_apiary` (the
   *   Apiary the insight belongs to either way), `verdict`,
   *   `recommendation`, `signals`, `confidence`, `context_snapshot`,
   *   `generated_timestamp`), only meaningful when $errors is empty.
   */
  protected function validateAndResolve(array $body): array {
    $errors = [];
    $values = [
      'hive' => NULL,
      'target_apiary' => NULL,
      'confidence' => NULL,
      'context_snapshot' => NULL,
    ];

    $scope = $body['scope'] ?? NULL;
    if (!in_array($scope, ['hive', 'apiary'], TRUE)) {
      $errors[] = 'scope must be "hive" or "apiary".';
    }
    $values['scope'] = $scope;

    if ($scope === 'hive') {
      $hive = isset($body['hive']) ? Hive::load($body['hive']) : NULL;
      if (!$hive) {
        $errors[] = 'hive must reference an existing hive when scope is "hive".';
      }
      else {
        $values['hive'] = $hive;
        $values['target_apiary'] = $hive->get('apiary')->entity;
      }
    }
    elseif ($scope === 'apiary') {
      $apiary = isset($body['apiary']) ? Apiary::load($body['apiary']) : NULL;
      if (!$apiary) {
        $errors[] = 'apiary must reference an existing apiary when scope is "apiary".';
      }
      else {
        $values['target_apiary'] = $apiary;
      }
    }

    $verdict = $body['verdict'] ?? NULL;
    if (!is_string($verdict) || !isset(HiveInsight::VERDICTS[$verdict])) {
      $errors[] = 'verdict must be one of: ' . implode(', ', array_keys(HiveInsight::VERDICTS));
    }
    $values['verdict'] = $verdict;

    $recommendation = $body['recommendation'] ?? NULL;
    if (!is_string($recommendation) || trim($recommendation) === '') {
      $errors[] = 'recommendation is required.';
    }
    $values['recommendation'] = $recommendation;

    $signals = $body['signals'] ?? NULL;
    if (!is_string($signals) || trim($signals) === '') {
      $errors[] = 'signals is required.';
    }
    $values['signals'] = $signals;

    $confidence = $body['confidence'] ?? NULL;
    if ($confidence !== NULL) {
      if (!is_string($confidence) || !isset(HiveInsight::CONFIDENCE_LEVELS[$confidence])) {
        $errors[] = 'confidence, if given, must be one of: ' . implode(', ', array_keys(HiveInsight::CONFIDENCE_LEVELS));
      }
      else {
        $values['confidence'] = $confidence;
      }
    }

    if (isset($body['context_snapshot'])) {
      $values['context_snapshot'] = is_string($body['context_snapshot']) ? $body['context_snapshot'] : NULL;
    }

    $generated = $body['generated'] ?? NULL;
    $generated_timestamp = $this->parseTimestamp($generated);
    if ($generated_timestamp === NULL) {
      $errors[] = 'generated must be a valid timestamp.';
    }
    $values['generated_timestamp'] = $generated_timestamp;

    return [$errors, $values];
  }

  /**
   * Parses a timestamp value (ISO 8601 string or UNIX timestamp) to epoch.
   */
  protected function parseTimestamp(mixed $value): ?int {
    if (is_int($value)) {
      return $value;
    }
    if (is_string($value) && $value !== '') {
      $timestamp = strtotime($value);
      return $timestamp === FALSE ? NULL : $timestamp;
    }
    return NULL;
  }

}
