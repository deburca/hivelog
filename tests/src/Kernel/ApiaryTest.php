<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Apiary entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ApiaryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'datetime',
    'options',
    'geofield',
    'hivelog',
  ];

  /**
   * A test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_inspection');

    $this->user = User::create([
      'name' => 'testuser',
      'mail' => 'test@example.com',
    ]);
    $this->user->save();
  }

  /**
   * Tests basic apiary creation and field values.
   */
  public function testCreateApiary(): void {
    $apiary = Apiary::create([
      'name' => 'Home Apiary',
      'location' => 'Back garden, Dublin',
      'geolocation' => 'POINT (-6.2603 53.3498)',
      'notes' => 'Sheltered site near hedge.',
      'uid' => $this->user->id(),
    ]);
    $apiary->save();

    $this->assertNotEmpty($apiary->id());

    // Reload from storage.
    $loaded = Apiary::load($apiary->id());
    $this->assertEquals('Home Apiary', $loaded->label());
    $this->assertEquals('Back garden, Dublin', $loaded->get('location')->value);
    $this->assertEquals('Sheltered site near hedge.', $loaded->get('notes')->value);
    $this->assertNotEmpty($loaded->get('created')->value);
    $this->assertNotEmpty($loaded->get('changed')->value);
  }

  /**
   * Tests geolocation coordinate field.
   */
  public function testGeolocation(): void {
    // Geofield stores coordinates as WKT: POINT (longitude latitude).
    $apiary = Apiary::create([
      'name' => 'Mountain Apiary',
      'geolocation' => 'POINT (-6.2603 53.3498)',
    ]);
    $apiary->save();

    $loaded = Apiary::load($apiary->id());
    $this->assertEqualsWithDelta(53.3498, (float) $loaded->get('geolocation')->lat, 0.0001);
    $this->assertEqualsWithDelta(-6.2603, (float) $loaded->get('geolocation')->lon, 0.0001);
  }

  /**
   * Tests that geolocation field is optional.
   */
  public function testGeolocationOptional(): void {
    $apiary = Apiary::create([
      'name' => 'No GPS Apiary',
      'location' => 'Somewhere rural',
    ]);
    $apiary->save();

    $loaded = Apiary::load($apiary->id());
    $this->assertEmpty($loaded->get('geolocation')->lat);
    $this->assertEmpty($loaded->get('geolocation')->lon);
  }

  /**
   * Tests the owner (uid) field.
   */
  public function testOwner(): void {
    $apiary = Apiary::create([
      'name' => 'Owned Apiary',
      'uid' => $this->user->id(),
    ]);
    $apiary->save();

    $loaded = Apiary::load($apiary->id());
    $this->assertEquals($this->user->id(), $loaded->getOwnerId());
    $this->assertEquals('testuser', $loaded->getOwner()->getAccountName());
  }

  /**
   * Tests updating an apiary.
   */
  public function testUpdateApiary(): void {
    $apiary = Apiary::create([
      'name' => 'Original Name',
      'location' => 'Original location',
    ]);
    $apiary->save();
    $original_changed = $loaded = Apiary::load($apiary->id())->get('changed')->value;

    // Allow time difference.
    sleep(1);

    $apiary->set('name', 'Updated Name');
    $apiary->set('location', 'New location');
    $apiary->save();

    $loaded = Apiary::load($apiary->id());
    $this->assertEquals('Updated Name', $loaded->label());
    $this->assertEquals('New location', $loaded->get('location')->value);
  }

  /**
   * Tests deleting an apiary.
   */
  public function testDeleteApiary(): void {
    $apiary = Apiary::create(['name' => 'To Delete']);
    $apiary->save();
    $id = $apiary->id();

    $apiary->delete();

    $this->assertNull(Apiary::load($id));
  }

}
