<?php

declare(strict_types=1);

namespace Drupal\nexus;

/**
 * Thrown when a provider call cannot be completed.
 *
 * Covers misconfiguration (unsupported provider, unresolvable key,
 * missing endpoint), a soft-dependency gap (the Drupal AI module isn't
 * installed/configured), and network/API failures. Always caught and
 * logged per-hive by `NexusInsightGenerator` — a single hive's failure
 * never aborts the rest of a `nexus_cron()` run.
 */
class NexusProviderException extends \RuntimeException {}
