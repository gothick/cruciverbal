<?php

namespace Gothick\Cruciverbal;

require_once __DIR__ . '/../vendor/autoload.php';

$root_dir = __DIR__ . "/../";

$config = new \Configula\Config($root_dir . 'config');
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $root_dir . $config->service_account_file);

//$gcp = new \Gothick\GoogleCloudPrint\GoogleCloudPrint();
//$printer = $gcp->search($config->printer);

foreach ($config->providers as $provider_name => $provider_class) {
  echo $provider_name . ': ' . $provider_class . "\n";
  $params = $config->provider_params[$provider_name];
  $provider = new $provider_class($params);
  var_dump($provider->getPdfStreams());
  // TODO: Stuff with the provider that actually gets us crosswords.
}