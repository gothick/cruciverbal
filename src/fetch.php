<?php
namespace Gothick\Cruciverbal;

require_once __DIR__ . '/../vendor/autoload.php';

use Configula\ConfigFactory as Config;

try {
    $root_dir = __DIR__ . "/../";

    $config = Config::loadPath($root_dir . 'config');

    # Sometimes we may comment out all our providers.
    if (!$config->hasValue("providers")) {
      echo("Exiting: No providers configured.\n");
      exit(0);
    }

    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $root_dir . $config->service_account_file);

    $gcp = new \Gothick\GoogleCloudPrint\GoogleCloudPrint();
    $printers = $gcp->search($config->printer);

    if (count($printers)) {
        $printer = $printers[0];
    } else {
        throw new \Exception('Printer not found in search by name: ' . $config->printer);
    }

    $pdf_streams = array();

    // Fetch our crosswords (as Guzzle streams) for all "registered"
    // providers. It's not exactly a DI Container, but then this isn't
    // exactly a crucial, complex project :D
    foreach ($config->providers as $provider_name => $provider_class) {
        try {
            $params = $config->provider_params[$provider_name];
            $provider = new $provider_class($params);
            $provider_streams = $provider->getPdfStreams();
            $pdf_streams = array_merge($pdf_streams, $provider_streams);
            // Throw them at the printer
            foreach ($pdf_streams as $pdf_stream) {
                $result = $gcp->submit($pdf_stream, 'application/pdf', $printer->id);
                if (! $result->success) {
                    // TODO: When we see this thrown, get some details to add to the exception.
                    throw new \Exception('Error submitting print job.');
                }
            }
        }
        catch(\Exception $e)
        {
            // It's quite likely only one provider had a problem, so we log the error
            // but continue looping.
            error_log("Error fetching crossword for provider '$provider_name'\n");
            error_log($e);
        }
    }
}
catch (\Exception $e) {
    error_log("Error during fetch: {$e->getMessage()} \n");
    error_log($e);
    throw $e;
}
