<?php
/**
 * @file Generate a valid JSON:API access token for the reviewer user.
 */
// Find the Simple OAuth client
$client_storage = \Drupal::entityTypeManager()->getStorage('simple_oauth_client');
$client = $client_storage->loadByProperties(['identifier' => 'hivelog-ios']);
$client = reset($client);

if (!$client) {
  // Check if it's stored differently
  $all_clients = $client_storage->loadMultiple();
  foreach ($all_clients as $c) {
    echo "Client found: {$c->getIdentifier()} ({$c->getClientId()})\n";
  }
  exit(1);
}

echo "Found client: {$client->getIdentifier()}, clientId: {$client->getClientId()}\n";

// Generate the token using the service
$generator = \Drupal::service('simple_oauth.generator');
$value = $generator->generateToken();

// Save it directly
$id = \Drupal::entityTypeManager()->getStorage('oauth2_token')->create([
  'bundle' => 'access_token',
  'auth_user_id' => 2,
  'client_id' => (string)$client->id(),
  'value' => $value,
  'expire' => time() + 3600,
])->id();

echo "Token ID: $id\n";
echo "Access Token: $value\n";
