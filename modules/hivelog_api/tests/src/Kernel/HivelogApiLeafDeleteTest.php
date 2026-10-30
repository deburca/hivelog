<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use Drupal\file\Entity\File;
use Drupal\hivelog\Entity\ApiaryMembership;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Entity\QueenObservation;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests deleting the two records the field-app role may delete (task 0220).
 *
 * The role holds `delete own hive inspection` and `delete own queen
 * observation`, nothing else. Who may delete which record is decided by the
 * access handlers, exactly as on the web: an apiary's owner may delete any
 * inspection in it, a member only the ones they created. The photos of a
 * deleted record are left as the web leaves them.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiLeafDeleteTest extends HivelogApiKernelTestBase {

  /**
   * The item path of a record.
   */
  protected function path(string $type, string $uuid): string {
    return "/api/v1/$type/$type/$uuid";
  }

  /**
   * An inspection in the owner's hive, created by someone.
   */
  protected function inspectionBy(int|string $uid, string $date = '2026-06-10'): HiveInspection {
    $inspection = HiveInspection::create([
      'hive' => $this->fixtures['my_hive']->id(),
      'inspection_date' => $date,
      'uid' => $uid,
    ]);
    $inspection->save();
    return $inspection;
  }

  /**
   * A permanent file attached as the only photo of an inspection.
   */
  protected function addPhoto(HiveInspection $inspection, string $name): File {
    $file = File::create(['uri' => 'public://' . $name, 'filename' => $name, 'status' => 1, 'uid' => $this->me->id()]);
    $file->save();
    $inspection->set('images', [['target_id' => $file->id(), 'alt' => 'A photo']]);
    $inspection->save();
    return $file;
  }

  /**
   * How many records use a file, by the file usage service.
   */
  protected function usageCount(File $file): int {
    $count = 0;
    foreach (\Drupal::service('file.usage')->listUsage($file) as $module => $types) {
      foreach ($types as $ids) {
        $count += array_sum($ids);
      }
    }
    return $count;
  }

  /**
   * An owner and a beekeeper may delete any inspection in the apiary; a novice none.
   *
   * What a user may delete follows their current role, not who recorded the
   * inspection (task 0224).
   */
  public function testOwnerAndBeekeeperDeleteAnyNoviceNone(): void {
    $keeper = $this->beekeeper('keeper_member');
    $novice = $this->beekeeper('novice_member');
    foreach ([['keeper', $keeper], ['novice', $novice]] as [$role, $user]) {
      ApiaryMembership::create([
        'apiary' => $this->fixtures['my_apiary']->id(),
        'account' => $user->id(),
        'role' => $role,
      ])->save();
    }
    $storage = \Drupal::entityTypeManager()->getStorage('hive_inspection');
    $delete = fn($inspection, $user) => $this->api('DELETE', $this->path('hive_inspection', $inspection->uuid()), NULL, $user);

    // A novice cannot delete the owner's, nor one they recorded themselves.
    $byOwner = $this->inspectionBy($this->me->id(), '2026-06-11');
    $byNovice = $this->inspectionBy($novice->id(), '2026-06-12');
    $this->assertSame(403, $delete($byOwner, $novice)['status']);
    $this->assertSame(403, $delete($byNovice, $novice)['status']);
    $this->assertNotNull($storage->load($byOwner->id()));
    $this->assertNotNull($storage->load($byNovice->id()));

    // A beekeeper deletes what the owner and a novice recorded.
    $response = $delete($byOwner, $keeper);
    $this->assertSame(204, $response['status'], $response['raw']);
    $this->assertNull($storage->load($byOwner->id()));
    $this->assertSame(204, $delete($byNovice, $keeper)['status']);

    // The owner deletes what a beekeeper recorded.
    $byKeeper = $this->inspectionBy($keeper->id(), '2026-06-13');
    $owner = $delete($byKeeper, $this->me);
    $this->assertSame(204, $owner['status'], $owner['raw']);
    $this->assertNull($storage->load($byKeeper->id()));
  }

  /**
   * An outsider can delete nothing in someone else's apiary.
   */
  public function testOutsiderDeletesNothing(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('hive_inspection');
    $theirs = $this->fixtures['their_inspection'];
    $response = $this->api('DELETE', $this->path('hive_inspection', $theirs->uuid()), NULL, $this->me);
    $this->assertContains($response['status'], [403, 404], $response['raw']);
    $this->assertNotNull($storage->load($theirs->id()));
    $this->assertSame(401, $this->api('DELETE', $this->path('hive_inspection', $theirs->uuid()))['status'], 'No credentials: 401');
  }

  /**
   * A queen observation is deleted by the person who owns it.
   */
  public function testQueenObservationIsDeleted(): void {
    $queen = Queen::create([
      'name' => 'Queen',
      'hive' => $this->fixtures['my_hive']->id(),
      'queen_year' => 2025,
      'uid' => $this->me->id(),
    ]);
    $queen->save();
    $observation = QueenObservation::create([
      'queen' => $queen->id(),
      'observation_date' => '2026-06-02',
      'health' => 'good',
      'uid' => $this->me->id(),
    ]);
    $observation->save();

    $response = $this->api('DELETE', $this->path('queen_observation', $observation->uuid()), NULL, $this->me);
    $this->assertSame(204, $response['status'], $response['raw']);
    $this->assertNull(\Drupal::entityTypeManager()->getStorage('queen_observation')->load($observation->id()));
    // The queen is untouched, and is still not deletable by the app.
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('queen')->load($queen->id()));
    $this->assertSame(403, $this->api('DELETE', $this->path('queen', $queen->uuid()), NULL, $this->me)['status']);
  }

  /**
   * A refusal for want of permission is a bare 403, so the app words it itself.
   *
   * (A refusal for a BLOCK rule, a record with children, does carry the
   * server's reason in `detail`: see HivelogApiAccessParityTest.)
   */
  public function testRefusedDeleteIsPlainForbidden(): void {
    $response = $this->api('DELETE', $this->path('hive', $this->fixtures['my_hive']->uuid()), NULL, $this->me);
    $this->assertSame(403, $response['status']);
    $this->assertSame('403', $response['body']['errors'][0]['status']);
    $this->assertSame('Forbidden', $response['body']['errors'][0]['title']);
  }

  /**
   * Deleting an inspection leaves its photos' files exactly as the web does.
   *
   * Drupal's file field drops the file's usage when its record goes, and
   * makes the file temporary (for cron to remove) only when the site's
   * `make_unused_managed_files_temporary` setting is on, which it is not by
   * default. The API goes through the same entity delete as the web form, so
   * both surfaces leave the same thing behind; this records what that is.
   */
  public function testDeletingAnInspectionTreatsItsPhotosAsTheWebDoes(): void {
    $viaApi = $this->inspectionBy($this->me->id());
    $fileApi = $this->addPhoto($viaApi, 'api.png');
    $viaWeb = $this->inspectionBy($this->me->id(), '2026-06-11');
    $fileWeb = $this->addPhoto($viaWeb, 'web.png');
    $this->assertSame(1, $this->usageCount($fileApi));

    $response = $this->api('DELETE', $this->path('hive_inspection', $viaApi->uuid()), NULL, $this->me);
    $this->assertSame(204, $response['status'], $response['raw']);
    // What the web's delete form does: the entity's own delete().
    $viaWeb->delete();

    foreach (['API' => $fileApi, 'web' => $fileWeb] as $surface => $file) {
      $this->assertSame(0, $this->usageCount($file), "After the $surface delete the record no longer uses the file.");
      $kept = File::load($file->id());
      $this->assertNotNull($kept, "After the $surface delete the file is kept (unused files are made temporary only if the site says so).");
      $this->assertTrue($kept->isPermanent(), "After the $surface delete the file is still permanent.");
    }
  }

  /**
   * Removing a photo by detaching it behaves as removing it in the web form.
   */
  public function testDetachingPhotoTreatsFileAsWebDoes(): void {
    $viaApi = $this->inspectionBy($this->me->id());
    $fileApi = $this->addPhoto($viaApi, 'keep-api.png');
    $viaWeb = $this->inspectionBy($this->me->id(), '2026-06-11');
    $fileWeb = $this->addPhoto($viaWeb, 'keep-web.png');

    $response = $this->api('PATCH', $this->path('hive_inspection', $viaApi->uuid()) . '/relationships/images', ['data' => []], $this->me);
    $this->assertSame(204, $response['status'], $response['raw']);
    $viaWeb->set('images', [])->save();

    $this->assertCount(0, HiveInspection::load($viaApi->id())->get('images'), 'The photo is no longer on the record.');
    foreach (['API' => $fileApi, 'web' => $fileWeb] as $surface => $file) {
      $this->assertSame(0, $this->usageCount($file), "After the $surface removal the record no longer uses the file.");
      $kept = File::load($file->id());
      $this->assertNotNull($kept, "After the $surface removal the file is kept.");
      $this->assertTrue($kept->isPermanent(), "After the $surface removal the file is still permanent.");
    }
  }

}
