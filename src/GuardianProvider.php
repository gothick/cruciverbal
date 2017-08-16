<?php
namespace Gothick\Cruciverbal;

require_once __DIR__ . '/../vendor/autoload.php';

class GuardianProvider implements iCrosswordProvider
{

    public function __construct($params = null)
    {}

    public function getPdfStreams()
    {
        $client = new \GuzzleHttp\Client([
            'base_uri' => 'https://crosswords-static.guim.co.uk'
        ]);
        
        $streams = array();
        // We don't need to do anything clever for the Guardian, it seems, as their
        // crossword URLs have a nice consistent format and they're not behind
        // a paywall. Yay!
        $now = new \DateTime();
        $date_string = $now->format('Ymd');
        $day_of_week = $now->format('w');
        
        if ($day_of_week >= 1 && $day_of_week <= 6) {
            // Weekday cryptic and Saturday prize.
            $streams[] = $client->request('GET', "/gdn.cryptic.$date_string.pdf")->getBody();
        }
        if ($day_of_week == 0) {
            // Sunday Everyman
            $streams[] = $client->request('GET', "/obs.everyman.$date_string.pdf")->getBody();
        }
        return $streams;
    }
}
