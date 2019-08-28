<?php
namespace Gothick\Cruciverbal;

require_once __DIR__ . '/../vendor/autoload.php';

class TimesProvider implements iCrosswordProvider
{

    /** @var string Website username */
    private $username;

    /** @var string Website password */
    private $password;

    /** @var boolean Should we fetch the quick cryptic? */
    private $fetch_qc;
    private $fetch_mephisto;

    /** @var \GuzzleHttp\Client $client */
    private $client;

    public function __construct($params = null)
    {
        if (empty($params['username']) || empty($params['password'])) {
            throw new \Exception('No username and password configured');
        }
        $this->username = $params['username'];
        $this->password = $params['password'];
        $this->fetch_qc = isset($params['fetch_qc']) ? $params['fetch_qc'] : true;
        $this->fetch_mephisto = isset($params['fetch_mephisto']) ? $params['fetch_mephisto'] : true;
    }

    public function getPdfStreams()
    {
        try {

            $this->client = new \GuzzleHttp\Client([
                'cookies' => true
            ]);

            // Log into the Times website and grab the Crossword Club page, saving the login cookie while we're there.
            $response = $this->client->request('POST', 'https://login.thetimes.co.uk/?gotoUrl=https%3A%2F%2Fwww.thetimes.co.uk%2Fpuzzleclub%2Fcrosswordclub%2F', [
                'form_params' => [
                    'gotoUrl' => 'https%3A%2F%2Fwww.thetimes.co.uk%2Fpuzzleclub%2Fcrosswordclub%2F',
                    'username' => $this->username,
                    'password' => $this->password,
                    'rememberMe' => 'on',
                    'Submit' => 'Login'
                ],
                    /* The Times login system doesn't half chuck you around the place. */
                    'allow_redirects' => [
                    'max' => 10
                ]
            ]);
            if ($response->getStatusCode() != 200) {
                throw new \Exception('Error logging in: ' . $response->getStatusCode . ': ' . $response->getReasonPhrase());
            }
        }
        catch(\Exception $e) {
            throw new \Exception("Unexpected exception in getPdfStreams", $e->getCode(), $e);
        }
        return $this->findStreams($response->getBody());
    }

    /**
     * Parse the homepage of the Crossword Club website and find links to the
     * puzzles we're interested in, then return the PDFs of them as an array
     * of Guzzle Streams.
     *
     * @param \GuzzleHttp\Psr7\Stream $homepage
     *            HTML of the Crossword Club Homepage
     * @return \GuzzleHttp\Psr7\Stream[] Array of crossword PDF streams
     */
    function findStreams($homepage)
    {
        $streams = array();

        try {
            $qp = \html5qp((string) $homepage);
        }
        catch (\Exception $e) {
            throw new \Exception('Error creating QueryPath for ' . $homepage, $e->getCode(), $e);
        }
        $day_of_week = date('w');

        if ($day_of_week == 0) {
            // Sunday: Sunday Times Cryptic and Mephisto
            $print_url = $qp->find('h3:contains("Sunday Times Cryptic"):first')
                ->closest('.PuzzleItem')
                ->find('.PuzzleItem--print-link a')
                ->attr('href');
            $this->grab($print_url);
            $streams[] = $this->grab($print_url);
            if ($this->fetch_mephisto) {
              $print_url = $qp->find('h3:contains("Mephisto"):first')
                  ->closest('.PuzzleItem')
                  ->find('.PuzzleItem--print-link a')
                  ->attr('href');
              $streams[] = $this->grab($print_url);
            }
        } else {
            // Monday to Saturday: Standard Cryptic
            $print_url = $qp->find('h3:contains("Times Cryptic No"):first')
                ->closest('.PuzzleItem')
                ->find('.PuzzleItem--print-link a')
                ->attr('href');
            $streams[] = $this->grab($print_url);

            // Monday to Friday: Quick Cryptic
            if ($this->fetch_qc && $day_of_week >= 1 && $day_of_week <= 5) {
                $print_url = $qp->find('h3:contains("Quick"):first')
                    ->closest('.PuzzleItem')
                    ->find('.PuzzleItem--print-link a')
                    ->attr('href');
                $streams[] = $this->grab($print_url);
            }
        }
        return $streams;
    }

    private function grab($print_url)
    {
        try {
            $response = $this->client->request('GET', $print_url);
            if ($response->getStatusCode() != 200) {
                throw new \Exception("Error fetching url '$print_url': " . $response->getStatusCode . ': ' . $response->getReasonPhrase());
            }
        }
        catch (\Exception $e) {
            // echo "Error fetching url '$print_url' in " . __FUNCTION__ . "\n";
            throw new \Exception("Error fetching url '$print_url'", $e->getCode(), $e);
        }
        // MG The Times have now fixed their bloody printing! No need to blacken it manually.
        return $response->getBody();
        //return new TimesBlackener($response->getBody());
    }
}
