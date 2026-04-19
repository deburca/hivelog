<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\ApiaryController;
use Drupal\hivelog\Controller\HiveController;
use Drupal\hivelog\Controller\HiveInspectionController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests explicit cache metadata on HiveLog controller views.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ControllerCacheMetadataTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'datetime',
    'options',
    'file',
    'image',
    'geofield',
    'hivelog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_inspection');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
    $this->installSchema('file', ['file_usage']);

    $user = User::create([
      'name' => 'tester',
      'mail' => 'tester@example.com',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    // FormBuilder requires a session on the current request; ensure one is
    // present since the controllers build forms.
    $request = Request::create('/admin/hivelog/apiary/1', 'GET');
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
  }

  /**
   * Tests that the apiary view declares expected cache contexts and tags.
   */
  public function testApiaryViewCacheMetadata(): void {
    $apiary = Apiary::create(['name' => 'Cache Apiary']);
    $apiary->save();

    $hive = Hive::create([
      'name' => 'Cache Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($apiary);

    $this->assertContains('url.query_args', $build['#cache']['contexts']);
    $this->assertContains('user.permissions', $build['#cache']['contexts']);

    $hive_list_tags = \Drupal::entityTypeManager()
      ->getDefinition('hive')
      ->getListCacheTags();
    foreach ($hive_list_tags as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }

    foreach ($apiary->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
    foreach ($hive->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
  }

  /**
   * Tests that the hive view declares expected cache contexts and tags.
   */
  public function testHiveViewCacheMetadata(): void {
    $apiary = Apiary::create(['name' => 'Cache Apiary']);
    $apiary->save();

    $hive = Hive::create([
      'name' => 'Cache Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    $inspection = HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2024-06-15',
    ]);
    $inspection->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);

    $this->assertContains('url.query_args', $build['#cache']['contexts']);
    $this->assertContains('user.permissions', $build['#cache']['contexts']);

    $inspection_list_tags = \Drupal::entityTypeManager()
      ->getDefinition('hive_inspection')
      ->getListCacheTags();
    foreach ($inspection_list_tags as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }

    // The hive view now surfaces the active queen, so its list cache tag
    // must be declared so the page invalidates whenever a queen changes.
    $queen_list_tags = \Drupal::entityTypeManager()
      ->getDefinition('queen')
      ->getListCacheTags();
    foreach ($queen_list_tags as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }

    foreach ($hive->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
    foreach ($inspection->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
  }

  /**
   * Tests that the hive inspection view declares expected cache metadata.
   */
  public function testHiveInspectionViewCacheMetadata(): void {
    $apiary = Apiary::create(['name' => 'Cache Apiary']);
    $apiary->save();

    $hive = Hive::create([
      'name' => 'Cache Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    $inspection = HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2024-06-15',
    ]);
    $inspection->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveInspectionController::class);
    $build = $controller->view($inspection);

    $this->assertContains('user.permissions', $build['#cache']['contexts']);

    foreach ($inspection->getCacheTags() as $tag) {
      $this->assertContains($tag, $build['#cache']['tags']);
    }
  }

  /**
   * Tests that each controller can be instantiated from the class resolver,
   * which exercises the full service-container dependency injection path.
   */
  public function testControllersUseDependencyInjection(): void {
    $class_resolver = \Drupal::service('class_resolver');

    $apiary_controller = $class_resolver->getInstanceFromDefinition(ApiaryController::class);
    $this->assertInstanceOf(ApiaryController::class, $apiary_controller);

    $hive_controller = $class_resolver->getInstanceFromDefinition(HiveController::class);
    $this->assertInstanceOf(HiveController::class, $hive_controller);

    $inspection_controller = $class_resolver->getInstanceFromDefinition(HiveInspectionController::class);
    $this->assertInstanceOf(HiveInspectionController::class, $inspection_controller);
  }

}
