<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use Drupal\hivelog_api\EventSubscriber\HivelogApiWriteCompatibilitySubscriber;
use Drupal\hivelog_api\HivelogApiResources;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests writes sent as the content types a shared host's firewall allows.
 *
 * A host firewall on the live site refused `application/vnd.api+json`,
 * `application/octet-stream`, `PATCH` and `PUT` before Drupal saw the request
 * (task 0212). The API accepts `application/json` for a record and
 * `multipart/form-data` for a photo instead, for a request with an
 * `Authorization` header, on the versioned prefix only.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiCompatibleWritesTest extends HivelogApiKernelTestBase {

  protected const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

  /**
   * Sends a request with chosen content type and body, through the kernel.
   *
   * @param string $path
   *   The path.
   * @param string $content_type
   *   The Content-Type header, or '' for none.
   * @param string|null $body
   *   The raw body.
   * @param \Drupal\user\Entity\User|null $user
   *   Who to authenticate as (Basic auth stands in for the bearer token), or
   *   NULL to send no credentials.
   * @param \Symfony\Component\HttpFoundation\File\UploadedFile[] $files
   *   Uploaded files, as PHP would have parsed a multipart body.
   *
   * @return array{status: int, body: array|null, raw: string}
   *   The response.
   */
  protected function send(string $path, string $content_type, ?string $body, $user = NULL, array $files = []): array {
    $server = ['HTTP_ACCEPT' => 'application/vnd.api+json'];
    if ($content_type !== '') {
      $server['CONTENT_TYPE'] = $content_type;
    }
    if ($user) {
      $server['PHP_AUTH_USER'] = $user->getAccountName();
      $server['PHP_AUTH_PW'] = 'pw-' . $user->getAccountName();
    }
    $request = Request::create('http://localhost' . $path, 'POST', [], [], $files, $server, $body);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $response = $this->container->get('http_kernel')->handle($request);
    $raw = (string) $response->getContent();
    $this->container->get('request_stack')->pop();
    return ['status' => $response->getStatusCode(), 'body' => json_decode($raw, TRUE), 'raw' => $raw];
  }

  /**
   * An inspection document for a hive.
   */
  protected function inspectionJson(string $hive_uuid, ?string $id = NULL): string {
    $data = [
      'type' => 'hive_inspection--hive_inspection',
      'attributes' => ['inspection_date' => '2026-07-01'],
      'relationships' => ['hive' => ['data' => ['type' => 'hive--hive', 'id' => $hive_uuid]]],
    ];
    if ($id) {
      $data['id'] = $id;
    }
    return json_encode(['data' => $data]);
  }

  /**
   * A record sent as plain JSON is created, the same as the standard type.
   */
  public function testRecordSentAsApplicationJsonIsCreated(): void {
    $uuid = \Drupal::service('uuid')->generate();
    $path = '/hivelog/api/v1/hive_inspection/hive_inspection';

    $response = $this->send($path, 'application/json', $this->inspectionJson($this->fixtures['my_hive']->uuid(), $uuid), $this->me);
    $this->assertSame(201, $response['status'], $response['raw']);
    $this->assertSame($uuid, $response['body']['data']['id']);

    // The server's rules still apply: a retry is a conflict, a foreign hive is refused.
    $retry = $this->send($path, 'application/json; charset=utf-8', $this->inspectionJson($this->fixtures['my_hive']->uuid(), $uuid), $this->me);
    $this->assertSame(409, $retry['status'], $retry['raw']);
    $foreign = $this->send($path, 'application/json', $this->inspectionJson($this->fixtures['their_hive']->uuid()), $this->me);
    $this->assertSame(422, $foreign['status'], $foreign['raw']);
  }

  /**
   * Without the leniency the standard route still refuses application/json.
   */
  public function testPlainJsonStaysRefusedOffTheVersionedApi(): void {
    $response = $this->send('/jsonapi/hive_inspection/hive_inspection', 'application/json', $this->inspectionJson($this->fixtures['my_hive']->uuid()), $this->me);
    // Refused either way (405 while JSON:API is read-only, as it is by default,
    // 415 otherwise): the leniency does not reach plain /jsonapi.
    $this->assertContains($response['status'], [405, 415], $response['raw']);
  }

  /**
   * A request with no Authorization header is not rewritten.
   */
  public function testRequestWithoutCredentialsIsLeftAlone(): void {
    $request = Request::create('http://localhost/hivelog/api/v1/hive_inspection/hive_inspection', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
    $this->assertFalse(HivelogApiWriteCompatibilitySubscriber::applies($request));
    $request->headers->set('Authorization', 'Bearer abc');
    $this->assertTrue(HivelogApiWriteCompatibilitySubscriber::applies($request));
  }

  /**
   * Only POSTs on JSON:API resources are touched, not the plain-JSON routes.
   */
  public function testOnlyJsonApiResourceWritesApply(): void {
    $make = function (string $method, string $path): Request {
      $request = Request::create('http://localhost' . $path, $method);
      $request->headers->set('Authorization', 'Bearer abc');
      return $request;
    };
    $applies = fn(string $method, string $path): bool => HivelogApiWriteCompatibilitySubscriber::applies($make($method, $path));
    $this->assertTrue($applies('POST', '/hivelog/api/v1/hive_inspection/hive_inspection'));
    $this->assertTrue($applies('POST', '/hivelog/api/v1/hive_inspection/hive_inspection/abc/images'));
    $this->assertFalse($applies('GET', '/hivelog/api/v1/hive_inspection/hive_inspection'));
    $this->assertFalse($applies('POST', '/hivelog/api/v1/sign-out'));
    $this->assertFalse($applies('POST', '/hivelog/api/v1/schema'));
    $this->assertFalse($applies('POST', '/hivelog/api/v1/computed/alerts'));
    $this->assertFalse($applies('POST', '/jsonapi/hive_inspection/hive_inspection'));
  }

  /**
   * A photo sent as multipart/form-data is attached like a raw upload.
   */
  public function testMultipartPhotoIsAttachedToTheInspection(): void {
    $inspection = $this->fixtures['my_inspection'];
    $path = '/hivelog/api/v1/hive_inspection/hive_inspection/' . $inspection->uuid() . '/images';
    $upload = $this->uploadedPng('hive photo.png');

    $response = $this->send($path, 'multipart/form-data; boundary=x', NULL, $this->me, ['file' => $upload]);
    $this->assertSame(200, $response['status'], $response['raw']);

    /** @var \Drupal\hivelog\Entity\HiveInspection $reloaded */
    $reloaded = \Drupal::entityTypeManager()->getStorage('hive_inspection')->load($inspection->id());
    $this->assertCount(1, $reloaded->get('images'));
    /** @var \Drupal\file\FileInterface $file */
    $file = $reloaded->get('images')->entity;
    $this->assertSame('hive photo.png', $file->getFilename(), 'The upload\'s file name becomes the stored file name.');
    $this->assertSame(base64_decode(self::PNG), file_get_contents($file->getFileUri()), 'The stored bytes are the uploaded bytes.');
  }

  /**
   * A multipart request with no file, or an empty one, is a clean 400.
   */
  public function testMultipartWithNoFileIsRefused(): void {
    $path = '/hivelog/api/v1/hive_inspection/hive_inspection/' . $this->fixtures['my_inspection']->uuid() . '/images';
    $none = $this->send($path, 'multipart/form-data; boundary=x', NULL, $this->me);
    $this->assertSame(400, $none['status'], $none['raw']);
    $this->assertSame('400', $none['body']['errors'][0]['status']);

    $empty_path = tempnam(sys_get_temp_dir(), 'hl');
    $empty = $this->send($path, 'multipart/form-data; boundary=x', NULL, $this->me, ['file' => new UploadedFile($empty_path, 'a.png', 'image/png', NULL, TRUE)]);
    $this->assertSame(400, $empty['status'], $empty['raw']);
  }

  /**
   * Someone else's inspection still cannot be given a photo.
   */
  public function testMultipartCannotAttachToAnotherBeekeepersRecord(): void {
    $path = '/hivelog/api/v1/hive_inspection/hive_inspection/' . $this->fixtures['their_inspection']->uuid() . '/images';
    $response = $this->send($path, 'multipart/form-data; boundary=x', NULL, $this->me, ['file' => $this->uploadedPng('x.png')]);
    $this->assertContains($response['status'], [403, 404], $response['raw']);
    /** @var \Drupal\hivelog\Entity\HiveInspection $reloaded */
    $reloaded = \Drupal::entityTypeManager()->getStorage('hive_inspection')->load($this->fixtures['their_inspection']->id());
    $this->assertCount(0, $reloaded->get('images'));
  }

  /**
   * The discovery document lists what the server accepts.
   */
  public function testDiscoveryListsTheFeatures(): void {
    $response = $this->api('GET', '/hivelog/api/v1');
    $this->assertSame(200, $response['status']);
    $this->assertSame(['json_write', 'multipart_upload'], $response['body']['meta']['hivelog_api']['features']);
    $this->assertSame(HivelogApiResources::FEATURES, $response['body']['meta']['hivelog_api']['features']);
    $this->assertSame(1, $response['body']['meta']['hivelog_api']['api_version'], 'The contract version is unchanged.');
  }

  /**
   * Writes a small PNG to a temporary file as an uploaded file.
   */
  protected function uploadedPng(string $client_name): UploadedFile {
    $path = tempnam(sys_get_temp_dir(), 'hl');
    file_put_contents($path, base64_decode(self::PNG));
    return new UploadedFile($path, $client_name, 'image/png', NULL, TRUE);
  }

}
