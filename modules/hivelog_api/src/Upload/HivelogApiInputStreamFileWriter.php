<?php

declare(strict_types=1);

namespace Drupal\hivelog_api\Upload;

use Drupal\file\Upload\InputStreamFileWriterInterface;
use Drupal\hivelog_api\EventSubscriber\HivelogApiWriteCompatibilitySubscriber;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Reads an upload from where the request put it, not only from `php://input`.
 *
 * JSON:API's file upload reads the raw request body through core's input
 * stream writer. PHP leaves `php://input` empty for a `multipart/form-data`
 * body and parses the file into a temporary file instead. When the
 * compatibility subscriber has relabelled such a request, this hands core that
 * file; any other request reads the stream exactly as before.
 */
class HivelogApiInputStreamFileWriter implements InputStreamFileWriterInterface {

  public function __construct(
    protected InputStreamFileWriterInterface $inner,
    protected RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function writeStreamToFile(string $stream = self::DEFAULT_STREAM, int $bytesToRead = self::DEFAULT_BYTES_TO_READ): string {
    $path = $this->requestStack->getCurrentRequest()?->attributes->get(HivelogApiWriteCompatibilitySubscriber::UPLOAD_ATTRIBUTE);
    if ($stream === self::DEFAULT_STREAM && is_string($path) && is_readable($path)) {
      $stream = $path;
    }
    return $this->inner->writeStreamToFile($stream, $bytesToRead);
  }

}
