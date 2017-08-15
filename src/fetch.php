<?php
namespace Gothick\Cruciverbal;

require_once __DIR__ . '/../vendor/autoload.php';

$root_dir = __DIR__ . "/../";

$config = new \Configula\Config($root_dir . 'config');
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $root_dir . $config->service_account_file);

$gcp = new \Gothick\GoogleCloudPrint\GoogleCloudPrint();
$printer = $gcp->search($config->printer)[0];
// TODO: What if we don't find the printer?

$pdf_streams = array();

// Fetch our crosswords (as Guzzle streams) for all "registered"
// providers. It's not exactly a DI Container, but then this isn't
// exactly a crucial, complex project :D
foreach ($config->providers as $provider_name => $provider_class) {
    $params = $config->provider_params[$provider_name];
    $provider = new $provider_class($params);
    $provider_streams = $provider->getPdfStreams();
    $pdf_streams = array_merge($pdf_streams, $provider_streams);
}

// Throw them at the printer
foreach ($pdf_streams as $pdf_stream) {
    $gcp->submit($pdf_stream, 'application/pdf', $printer->id);
}
