<?php

declare(strict_types=1);

namespace Drupal\hivelog_api\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hivelog_api\HivelogApiResources;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The module's entries on the status report.
 *
 * Drupal 11.3 and later run requirements as object-oriented hooks and skip
 * the procedural hook_requirements() of a module that marks it with
 * `#[LegacyRequirementsHook]`. The module did that without providing the
 * object-oriented hook, so on those versions neither entry appeared and a
 * missing OAuth key pair went unreported. This class is the object-oriented
 * hook; hivelog_api_requirements() in the install file calls it too, for
 * Drupal versions that only know the procedural one.
 */
final class HivelogApiRequirements implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get('config.factory'));
  }

  /**
   * Implements hook_runtime_requirements().
   *
   * @return array<string, array<string, mixed>>
   *   The requirements, keyed by name.
   */
  #[Hook('runtime_requirements')]
  public function runtime(): array {
    $requirements = [];

    // The OAuth server cannot issue a token without its key pair.
    $settings = $this->configFactory->get('simple_oauth.settings');
    $keys_ok = TRUE;
    foreach (['public_key', 'private_key'] as $key) {
      $path = (string) $settings->get($key);
      if ($path === '' || !is_readable($path)) {
        $keys_ok = FALSE;
      }
    }
    $requirements['hivelog_api_keys'] = [
      'title' => $this->t('HiveLog API: OAuth keys'),
      'value' => $keys_ok ? $this->t('Configured') : $this->t('Missing'),
      'description' => $keys_ok ? NULL : $this->t('The field app cannot sign in until simple_oauth has a readable public and private key. Generate them at /admin/config/people/simple_oauth, and keep them outside the web root.'),
      'severity' => $this->severity($keys_ok ? 'OK' : 'Error'),
    ];

    // For the authorization-code grant, simple_oauth lets a client request any
    // scope that has that grant enabled, not only the ones on its consumer. The
    // field app's client is public, so any other such scope is reachable by it.
    $others = [];
    foreach (Oauth2Scope::loadMultiple() as $scope) {
      if ($scope->id() !== HivelogApiResources::SCOPE && $scope->isGrantTypeEnabled('authorization_code')) {
        $others[] = $scope->id();
      }
    }
    $requirements['hivelog_api_scopes'] = [
      'title' => $this->t('HiveLog API: authorization-code scopes'),
      'value' => $others ? $this->t('Other scopes enabled') : $this->t('Only the field app scope'),
      'description' => $others ? $this->t('These scopes also allow the authorization-code grant, so the public field app client could request them: @scopes. Disable that grant on any broad scope.', ['@scopes' => implode(', ', $others)]) : NULL,
      'severity' => $this->severity($others ? 'Warning' : 'OK'),
    ];

    return $requirements;
  }

  /**
   * A severity in the form the running Drupal expects.
   *
   * Drupal 11.2 and later use an enum; earlier 11.x use the REQUIREMENT_*
   * constants, and the enum does not exist there.
   *
   * @param string $level
   *   `OK`, `Warning` or `Error`.
   *
   * @return mixed
   *   A RequirementSeverity case, or the matching constant.
   */
  private function severity(string $level): mixed {
    if (enum_exists(RequirementSeverity::class)) {
      return constant(RequirementSeverity::class . '::' . $level);
    }
    return constant('REQUIREMENT_' . strtoupper($level));
  }

}
