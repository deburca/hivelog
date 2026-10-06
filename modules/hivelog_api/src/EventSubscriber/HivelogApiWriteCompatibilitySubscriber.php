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
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Before routing (32): a route's content-type requirement is matched
    // against the header as it is when routing runs.
    return [KernelEvents::REQUEST => ['onRequest', 40]];
  }

  /**
   * Whether a request is one this subscriber may rewrite.
   */
  public static function applies(Request $request): bool {
    if (!$request->isMethod('POST') || !$request->headers->has('Authorization')) {
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
   * Rewrites the request's content type, or turns an upload into a raw one.
   */
  public function onRequest(RequestEvent $event): void {
    // A sub-request (an error page) carries the original server variables.
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    if (!self::applies($request)) {
      return;
    }
    $type = strtolower(trim(explode(';', (string) $request->headers->get('Content-Type', ''))[0]));
    if ($type === 'application/json') {
      $request->headers->set('Content-Type', 'application/vnd.api+json');
    }
    elseif ($type === 'multipart/form-data') {
      $response = $this->convertUpload($request);
      if ($response) {
        $event->setResponse($response);
      }
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
