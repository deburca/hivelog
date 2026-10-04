<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * The current user must be allowed to act on the referenced parent record.
 *
 * Create access in HiveLog is a global permission and cannot see which parent
 * a new record points at; the web UI checks the parent on the scoped add
 * route instead (task 0133, `_entity_access: hive.update`). A non-route
 * caller such as JSON:API has no route, so without this constraint it could
 * create a record in, or move a record into, another beekeeper's apiary or
 * hive (task 0198). This is the same rule the routes apply, as a
 * constraint, so every caller is held to it.
 *
 * Only a reference that is new or has changed is checked, so editing an
 * unrelated field of an existing record never fails because the parent's
 * access has since changed.
 */
#[Constraint(
  id: 'HivelogParentAccess',
  label: new TranslatableMarkup('Parent record access', [], ['context' => 'Validation'])
)]
class HivelogParentAccessConstraint extends SymfonyConstraint {

  #[HasNamedArguments]
  public function __construct(
    mixed $options = NULL,
    public string $operation = 'update',
    public string $message = 'You do not have permission to add records to, or move records into, the selected @type.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct($options, $groups, $payload);
  }

}
