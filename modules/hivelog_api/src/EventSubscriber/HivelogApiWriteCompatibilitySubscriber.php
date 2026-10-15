<?php

declare(strict_types=1);

namespace Drupal\hivelog_api\EventSubscriber;

use Drupal\hivelog_api\HivelogApiResources;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Lets the API's writes use the content types a shared host's firewall allows.
 *
 * JSON:API needs `application/vnd.api+json` for a write and
 * `application/octet-stream` for a file upload. Some web hosts run a firewall
 * that refuses those (and `PATCH` and `PUT`) before the request reaches Drupal,
 * so a field app on such a host can read and sign in but can record nothing.
 * Both are answered here: on the versioned API, a `POST` that carries an
 * `Authorization` header may be sent as `application/json` (read as JSON:API's
 * own type) or, for a photo, as `multipart/form-data` with one file part (read
 * as the raw upload JSON:API expects: the request is relabelled, and
 * HivelogApiInputStreamFileWriter hands core the uploaded file where core would
 * read `php://input`, which PHP leaves empty for a multipart body). JSON:API then
 * does everything it always does with the request.
 *
 * The same firewalls refuse `PATCH`, `PUT` and `DELETE` outright, so an edit
 * or a delete is sent as a `POST` with `X-HTTP-Method-Override: PATCH` or
 * `DELETE` (task 0220). Symfony would honour that header on any POST for any
 * method; on the versioned prefix it is narrowed here to exactly those two
 * methods and, like the other leniencies, to a request with an `Authorization`
 * header. Everything else is reset to the plain POST it really is, so the
 * override can never reach `PUT`, `GET` or a plain-JSON route, nor a browser
 * session. JSON:API then runs the request as the method it names: its access
 * checks, validation and the app role's ceiling are untouched, so the override
 * grants nothing a real `PATCH` or `DELETE` would not.
 *
 * Only a request with an `Authorization` header is touched, so a browser
 * session gets nothing new: a form on another site can send
 * `multipart/form-data`, but cannot set that header.
 */
class HivelogApiWriteCompatibilitySubscriber implements EventSubscriberInterface {

  /**
   * The largest photo accepted this way, in bytes.
   */
  public const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

  /**
   * The request attribute holding the path of an uploaded file to read.
   */
  public const UPLOAD_ATTRIBUTE = 'hivelog_api.upload_path';

  /**
   * Plain-JSON routes under the prefix, which are not JSON:API resources.
   */
  protected const PLAIN_ROUTES = ['computed/', 'schema', 'sign-out'];

  /**
   * The request header that names the method a POST stands for.
   */
  public const OVERRIDE_HEADER = 'X-HTTP-Method-Override';

  /**
   * The only methods a POST may stand for.
   */
  public const OVERRIDE_METHODS = ['PATCH', 'DELETE'];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Before routing (32): a route's content-type requirement is matched
    // against the header as it is when routing runs.
    return [KernelEvents::REQUEST => ['onRequest', 40]];
  }

  /**
   * The method a request really arrived with, before any override.
   */
  protected static function realMethod(Request $request): string {
    return strtoupper((string) $request->server->get('REQUEST_METHOD', 'GET'));
  }

  /**
   * Whether a request is one this subscriber may rewrite.
   */
  public static function applies(Request $request): bool {
    if (self::realMethod($request) !== 'POST' || !$request->headers->has('Authorization')) {
      return FALSE;
    }
    if (!HivelogApiResources::isVersionedRequest($request)) {
      return FALSE;
    }
    $path = substr($request->getPathInfo(), strlen(HivelogApiResources::PATH_PREFIX) + 1);
    foreach (self::PLAIN_ROUTES as $plain) {
      if (str_starts_with($path, $plain)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * The method a request asks to be treated as, if that is allowed.
   *
   * @return string|null
   *   `PATCH` or `DELETE` for a request that applies and names one of them,
   *   otherwise NULL (no override, or one that is not allowed).
   */
  public static function overrideMethod(Request $request): ?string {
    if (!self::applies($request)) {
      return NULL;
    }
    $named = strtoupper(trim((string) $request->headers->get(self::OVERRIDE_HEADER, '')));
    return in_array($named, self::OVERRIDE_METHODS, TRUE) ? $named : NULL;
  }

  /**
   * Rewrites the request's content type and method, or turns an upload into a raw one.
   */
  public function onRequest(RequestEvent $event): void {
    // A sub-request (an error page) carries the original server variables.
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    if (!HivelogApiResources::isVersionedRequest($request) || self::realMethod($request) !== 'POST') {
      return;
    }
    $override = self::overrideMethod($request);
    if (!$override) {
      // Symfony reads the header on any POST, for any method: on the versioned
      // API only the two allowed ones, with credentials in a header, count.
      // Resetting the method also drops one something earlier has cached.
      if ($request->headers->has(self::OVERRIDE_HEADER)) {
        $request->headers->remove(self::OVERRIDE_HEADER);
        $request->setMethod('POST');
      }
    }
    if (!self::applies($request)) {
      return;
    }
    $type = strtolower(trim(explode(';', (string) $request->headers->get('Content-Type', ''))[0]));
    if ($type === 'application/json') {
      $request->headers->set('Content-Type', 'application/vnd.api+json');
    }
    elseif ($type === 'multipart/form-data' && !$override) {
      $response = $this->convertUpload($request);
      if ($response) {
        $event->setResponse($response);
      }
    }
    if ($override) {
      $request->setMethod($override);
    }
  }

  /**
   * Turns a multipart upload into the raw upload JSON:API expects.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|null
   *   An error response, or NULL when the request has been converted.
   */
  protected function convertUpload(Request $request): ?JsonResponse {
    $file = $this->firstFile($request->files->all());
    if (!$file) {
      return $this->error(400, 'Bad Request', 'A multipart upload must contain one file part.');
    }
    if (!$file->isValid()) {
      return $this->error(400, 'Bad Request', 'The upload failed: ' . $file->getErrorMessage());
    }
    if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
      return $this->error(413, 'Payload Too Large', 'The file is larger than ' . (self::MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB.');
    }
    if ($file->getSize() === 0) {
      return $this->error(400, 'Bad Request', 'The uploaded file is empty.');
    }

    // Quotes and control characters cannot sit inside the header's quoted name.
    $name = preg_replace('/[\x00-\x1f\x7f"\\\\]/', '', basename($file->getClientOriginalName())) ?? '';
    $name = $name !== '' ? $name : 'upload';

    // Relabel the request as the raw upload JSON:API's route expects, and tell
    // the stream writer where the bytes are.
    $request->headers->set('Content-Type', 'application/octet-stream');
    $request->headers->set('Content-Disposition', 'file; filename="' . $name . '"');
    $request->attributes->set(self::UPLOAD_ATTRIBUTE, $file->getPathname());
    return NULL;
  }

  /**
   * Finds the first uploaded file among possibly nested file inputs.
   *
   * @param array $files
   *   The request's files.
   */
  protected function firstFile(array $files): ?UploadedFile {
    foreach ($files as $entry) {
      if ($entry instanceof UploadedFile) {
        return $entry;
      }
      if (is_array($entry) && ($found = $this->firstFile($entry))) {
        return $found;
      }
    }
    return NULL;
  }

  /**
   * An error in JSON:API's shape, answered directly.
   */
  protected function error(int $status, string $title, string $detail): JsonResponse {
    return new JsonResponse(
      ['errors' => [['status' => (string) $status, 'title' => $title, 'detail' => $detail]]],
      $status,
      ['Content-Type' => 'application/vnd.api+json']
    );
  }

}
