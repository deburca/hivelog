<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Unit;

use Drupal\hivelog\HivelogFilterPanel;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the captcha removal on filter forms.
 */
#[Group('hivelog')]
class HivelogFilterPanelTest extends UnitTestCase {

  /**
   * A captcha element is removed at any depth; other elements stay.
   */
  public function testRemoveCaptchaAtAnyDepth(): void {
    $form = [
      '#attributes' => ['class' => ['hivelog-filter-form']],
      'name' => ['#type' => 'textfield'],
      'captcha' => ['#type' => 'captcha', '#captcha_type' => 'x/y'],
      'actions' => [
        '#type' => 'actions',
        'captcha' => ['#type' => 'captcha'],
        'submit' => ['#type' => 'submit'],
      ],
    ];
    $result = HivelogFilterPanel::removeCaptcha($form);
    $this->assertArrayNotHasKey('captcha', $result);
    $this->assertArrayNotHasKey('captcha', $result['actions']);
    $this->assertArrayHasKey('name', $result);
    $this->assertArrayHasKey('submit', $result['actions']);
    $this->assertSame(['class' => ['hivelog-filter-form']], $result['#attributes']);
  }

}
