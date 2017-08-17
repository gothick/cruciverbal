<?php

namespace Gothick\Cruciverbal;

require_once __DIR__ . '/../vendor/autoload.php';

$root_dir = __DIR__ . "/../";

$config = new \Configula\Config($root_dir . 'config');
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $root_dir . $config->service_account_file);

//$gcp = new \Gothick\GoogleCloudPrint\GoogleCloudPrint();
//$printers = $gcp->search("Brother");

$client = new \GuzzleHttp\Client();
$response = $client->request('GET', 'https://dl.dropboxusercontent.com/u/838327/Throwaway/crossword-20170805-25132.pdf');
$body = $response->getBody();
$blackener = new TimesBlackener($body);
var_dump($blackener->getContents());
//$blackener->close();