<?php

require '../lib/vendor/autoload.php';
require "../lib/core.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set up error logging
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error_log_file.log');

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

$client = new \GuzzleHttp\Client();

$fidVerifier = new FidVerifier();

$currentURL = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$currentHost = dirname($_SERVER['PHP_SELF']);
$currentFolder = "https://$_SERVER[HTTP_HOST]$currentHost";
$currentFolder = rtrim($currentFolder, '/');
// $parentFolder = dirname($currentFolder);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

$configFile = 'config/frame-setting.json';

if (!file_exists($configFile)) {
    die('Configuration file not found.');
}

$config = json_decode(file_get_contents($configFile), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die('Error decoding JSON: ' . json_last_error_msg());
}

// Create an empty array to store mention data
$mentionList = [];
$frameImageResult = null;
$imageTimestamp = time();
$walletAddress[] = null;
$defaultWalletAddress = null;

$hasResult = null;
$isEligible = false;

// Get current Unix timestamp
$timestamp = time();

function convertToLowercase($input)
{
    // Convert the input string to lowercase
    $lowercaseInput = strtolower($input);
    return $lowercaseInput;
}

function getFidByUsername($username, $apiKey)
{
    // Initialize a cURL session
    $curl = curl_init();

    $addressPattern = '/^0x[a-fA-F0-9]{40}$/';
    // Regex pattern for matching usernames
    $usernamePattern = '/^[a-zA-Z0-9-]+(\.eth)?$/';

    $endPoint = null;

    // Check if the input matches the address pattern
    if (preg_match($addressPattern, $username)) {
        $endPoint = "https://build.far.quest/farcaster/v2/user-by-connected-address?address=";
    } elseif (preg_match($usernamePattern, $username)) {
        $endPoint = "https://build.far.quest/farcaster/v2/user-by-username?username=";
    } else {
    }

    // Set the URL and other appropriate options
    curl_setopt_array($curl, [
        CURLOPT_URL => $endPoint . urlencode($username),
        CURLOPT_RETURNTRANSFER => true, // Return the response as a string
        CURLOPT_HTTPHEADER => [
            'API-KEY: ' . $apiKey,
            'accept: application/json'
        ],
    ]);

    // Execute the cURL request
    $response = curl_exec($curl);

    // Check for errors in the cURL request
    if (curl_errno($curl)) {
        curl_close($curl);
        return 'cURL error: ' . curl_error($curl);
    }

    // Close the cURL session
    curl_close($curl);

    // Decode the JSON response
    $responseData = json_decode($response, true);

    // Check if decoding was successful and the expected data is present
    if (isset($responseData['result']['user']['fid'])) {
        return $responseData['result']['user']['fid'];
    } else {
        return false;
    }
}

function hasDecimal($number)
{
    $numberStr = strval($number);
    if (strpos($numberStr, '.') !== false) {
        return true;
    }
    return false;
}

function getTimeAgo($timestamp)
{
    $dateTime = new DateTime('@' . $timestamp);
    $currentDateTime = new DateTime('now', new DateTimeZone('UTC'));
    $interval = $currentDateTime->diff($dateTime);

    if ($interval->y > 0) {
        return $interval->format('%y years ago');
    } elseif ($interval->m > 0) {
        return $interval->format('%m months ago');
    } elseif ($interval->d > 0) {
        return $interval->format('%d days ago');
    } elseif ($interval->h > 0) {
        return $interval->format('%h hours ago');
    } elseif ($interval->i > 0) {
        return $interval->format('%i minutes ago');
    } else {
        return 'Just now';
    }
}

function calculateBoostPower($rank, $total = 500)
{
    $supportScore = (1 - ($rank - 1) / ($total - 1)) * 100;
    if ($rank > 500) {
        return "N/A";
    } else {
        return round($supportScore, 2) . "x";
    }
}


// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $fid = null;
    $frameImage = '';
    $randomNumber = rand(1000, 9999);

    if (isset($_GET['fid'])) {
        $fid = $_GET['fid'];
        $frameImage = "share/" . $fid . ".jpg";
    } else {
        $frameImage = $currentFolder . "/assets/rare-stats-start-intro-min.jpg?ts=" . $imageTimestamp;
    }

    $framePostUrl = $currentURL;

    echo '
    <html lang="en">
      <head><script src="https://genyleap.xyz/api/bootstrap/assets/js/color-modes.js"></script>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>' . $config['header']['metaTitle'] . '</title>
        <meta name="description" content="">
        <meta name="author" content="' . $config['header']['metaAuthor'] . '">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Rare Stats</title>
        <meta property="og:image" content="' . $frameImage . '" />
        <meta property="fc:frame" content="vNext" />
        <meta property="fc:frame:post_url" content="' . $framePostUrl . '" />
        <meta property="fc:frame:image" content="' . $frameImage . '" />
        <meta property="fc:frame:image:aspect_ratio" content="1:1" />
        
        <meta property="fc:frame:button:1" content="Check Me">
        <meta property="fc:frame:button:1:action" content="post">
        <meta property="fc:frame:button:1:target" content="' . $framePostUrl . '">
        
        <meta property="fc:frame:input:text" content="Enter username or wallet address" />
        
        <meta property="fc:frame:button:2" content="🔎">
        <meta property="fc:frame:button:2:action" content="post">
        <meta property="fc:frame:button:2:target" content="' . $framePostUrl . '">
        
        <meta property="fc:frame:button:3" content="Booster">
        <meta property="fc:frame:button:3:action" content="link">
        <meta property="fc:frame:button:3:target" content="https://warpcast.com/~/compose?text=Check Your rare booster! %0ABy @compez.eth%0A&embeds%5B%5D=https://rare-boost.genyframe.xyz">
        
        <meta property="fc:frame:button:4" content="⚙️ Action">
        <meta property="fc:frame:button:4:action" content="link">
        <meta property="fc:frame:button:4:target" content="https://warpcast.com/~/add-cast-action?icon=graph&name=$RARE+Stats&actionType=post&postUrl=https%3A%2F%2Fgenyleap.xyz%2Ffarcaster%2Factions%2Frarestats">

<style>
          .bd-placeholder-img {
            font-size: 1.125rem;
            text-anchor: middle;
            -webkit-user-select: none;
            -moz-user-select: none;
            user-select: none;
          }
    
          @media (min-width: 768px) {
            .bd-placeholder-img-lg {
              font-size: 3.5rem;
            }
          }
    
          .b-example-divider {
            width: 100%;
            height: 3rem;
            background-color: rgba(0, 0, 0, .1);
            border: solid rgba(0, 0, 0, .15);
            border-width: 1px 0;
            box-shadow: inset 0 .5em 1.5em rgba(0, 0, 0, .1), inset 0 .125em .5em rgba(0, 0, 0, .15);
          }
    
          .b-example-vr {
            flex-shrink: 0;
            width: 1.5rem;
            height: 100vh;
          }
    
          .bi {
            vertical-align: -.125em;
            fill: currentColor;
          }
    
          .nav-scroller {
            position: relative;
            z-index: 2;
            height: 2.75rem;
            overflow-y: hidden;
          }
    
          .nav-scroller .nav {
            display: flex;
            flex-wrap: nowrap;
            padding-bottom: 1rem;
            margin-top: -1px;
            overflow-x: auto;
            text-align: center;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
          }
    
          .btn-bd-primary {
            --bd-violet-bg: #712cf9;
            --bd-violet-rgb: 112.520718, 44.062154, 249.437846;
    
            --bs-btn-font-weight: 600;
            --bs-btn-color: var(--bs-white);
            --bs-btn-bg: var(--bd-violet-bg);
            --bs-btn-border-color: var(--bd-violet-bg);
            --bs-btn-hover-color: var(--bs-white);
            --bs-btn-hover-bg: #6528e0;
            --bs-btn-hover-border-color: #6528e0;
            --bs-btn-focus-shadow-rgb: var(--bd-violet-rgb);
            --bs-btn-active-color: var(--bs-btn-hover-color);
            --bs-btn-active-bg: #5a23c8;
            --bs-btn-active-border-color: #5a23c8;
          }
    
          .bd-mode-toggle {
            z-index: 1500;
          }
    
          .bd-mode-toggle .dropdown-menu .active .bi {
            display: block !important;
          }

.btn-light,
.btn-light:hover,
.btn-light:focus {
  color: #333;
  text-shadow: none;
}

body {
  text-shadow: 0 .05rem .1rem rgba(0, 0, 0, .5);
  box-shadow: inset 0 0 5rem rgba(0, 0, 0, .5);
}

.cover-container {
  max-width: 42em;
}


.nav-masthead .nav-link {
  color: rgba(255, 255, 255, .5);
  border-bottom: .25rem solid transparent;
}

.nav-masthead .nav-link:hover,
.nav-masthead .nav-link:focus {
  border-bottom-color: rgba(255, 255, 255, .25);
}

.nav-masthead .nav-link + .nav-link {
  margin-left: 1rem;
}

.nav-masthead .active {
  color: #fff;
  border-bottom-color: #fff;
}

.text-bg-dark-pro {
  color: #fff !important;
  background-color: #018d60;
}
</style>
      </head>
      <body class="d-flex h-100 text-center text-bg-dark-pro">
        <svg xmlns="http://www.w3.org/2000/svg" class="d-none">
          <symbol id="check2" viewBox="0 0 16 16">
            <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
          </symbol>
          <symbol id="circle-half" viewBox="0 0 16 16">
            <path d="M8 15A7 7 0 1 0 8 1v14zm0 1A8 8 0 1 1 8 0a8 8 0 0 1 0 16z"/>
          </symbol>
          <symbol id="moon-stars-fill" viewBox="0 0 16 16">
            <path d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278z"/>
            <path d="M10.794 3.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.734 1.734 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.734 1.734 0 0 0 9.31 6.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.734 1.734 0 0 0 1.097-1.097l.387-1.162zM13.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.156 1.156 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.156 1.156 0 0 0-.732-.732l-.774-.258a.145.145 0 0 1 0-.274l.774-.258c.346-.115.617-.386.732-.732L13.863.1z"/>
          </symbol>
          <symbol id="sun-fill" viewBox="0 0 16 16">
            <path d="M8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708z"/>
          </symbol>
        </svg>

        
    <div class="cover-container d-flex w-100 h-100 p-3 mx-auto flex-column">
      <header class="mb-auto">
      </header>
    
      <main class="px-3">
        <h1>' . $config['header']['metaTitle'] . '</h1>
        <p class="lead">In the vast digital landscape of the Web 3.0, where every word has the potential to resonate across borders and cultures, your content stands as a beacon of influence and inspiration. Through meticulous analysis and steadfast dedication, we have delved into the depths of your contents impact, uncovering a tapestry woven with data-driven insights and transformative potential.</p>
        <p class="lead">
        <a href="https://warpcast.com/~/compose?text=Check%20your%20$RARE%20stats.%20%20%0AFrame%20by%20@compez.eth%0A&embeds%5B%5D=https://superrare.genyframe.xyz" class="btn btn-lg btn-light fw-bold border-white bg-white"><i class="bi bi-arrow-down-right-circle"></i>Get Now</a>
        </p>
      </main>
      <footer class="mt-auto text-white-50">
        <p>Developed by <a href="https://warpcast.com/compez.eth" class="text-white">compez.eth</a>.</p>
      </footer>
    </div>
    <script src="https://genyleap.xyz/api/bootstrap/assets/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>';
}


class StatsFetcher
{
    private $client;
    private $currentFolder;
    private $authorization;

    public function __construct($authorization = '')
    {
        $this->currentFolder = isset($_SERVER['HTTP_HOST']) ? "https://$_SERVER[HTTP_HOST]" . rtrim(dirname($_SERVER['PHP_SELF']), '/') : 'https://genyleap.xyz/farcaster/superrare';

        $this->authorization = $authorization;
        $this->client = new Client();

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
    }

    private function make_api_request($url, $params = [], $headers = [], $method = "GET")
    {
        $options = [
            'headers' => array_merge([
                'Content-Type' => 'application/json',
                'User-Agent' => 'SuperRare Stats Pro/1.3.5 (Genyleap; comepz.eth)'
            ], $headers)
        ];

        if ($this->authorization) {
            $options['headers']['Authorization'] = $this->authorization;
        }

        if ($method === 'POST') {
            $options['json'] = $params;
        } else {
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
        }

        return $this->client->requestAsync($method, $url, $options)->then(
            function ($response) {
                return json_decode($response->getBody(), true);
            },
            function ($exception) {
                return null;
            }
        );
    }

    private function make_graphql_request($url, $query, $variables, $headers = [])
    {
        $options = [
            'headers' => array_merge([
                'Content-Type' => 'application/json'
            ], $headers),
            'json' => [
                'query' => $query,
                'variables' => $variables
            ]
        ];

        if ($this->authorization) {
            $options['headers']['Authorization'] = $this->authorization;
        }

        return $this->client->postAsync($url, $options)->then(
            function ($response) {
                return json_decode($response->getBody(), true);
            },
            function ($exception) {
                return null;
            }
        );
    }

    public function fetchTrends()
    {
        $url = 'https://build.far.quest/farcaster/v2/trends';
        $params = ['onlyTrends' => "true"];
        $headers = [
            'API-KEY' => 'Q182P-FZUCK-NG5KV-M92GC-F0LO1',
            'accept' => 'application/json'
        ];

        $responsePromise = $this->make_api_request($url, $params, $headers);

        return $responsePromise->then(function ($response) {
            if ($response === null) {
                echo "Error fetching Farcaster stats.\n";
                return null;
            }

            // Extract and return only the timestamp
            if (isset($response['result']['trends']['$RARE'])) {
                return $response['result']['trends']['$RARE'];
            } else {
                return "No trends found for \$RARE.\n";
                return null;
            }
        });
    }

    public function getSocialCapitalRank($identity)
    {
        // Your GraphQL query with identity directly included
        $query = <<<GRAPHQL
        query MyQuery {
        Socials(
            input: {
            filter: {
                dappName: {
                _eq: farcaster
                },
                identity: { _eq: "$identity" }
            },
            blockchain: ethereum
            }
        ) {
            Social {
            socialCapital {
                socialCapitalRank
            }
            }
        }
        }
GRAPHQL;
        // Make the GraphQL request and return the promise
        $headers = [
            'Authorization' => '1a6ac181d02964c2794102094a8a1d1b9',
            'User-Agent' => 'Moxie Earn Stats Pro/1.0 (Genyleap; comepz.eth)',
            'accept' => 'application/json'
        ];

        $variables = [];
        $promise = $this->make_graphql_request('https://api.airstack.xyz/graphql', $query, $variables, $headers);

        return $promise->then(
            function ($response) {
                if (isset($response['errors'])) {
                    throw new Exception('GraphQL query failed: ' . json_encode($response['errors']));
                }

                // Extract socialCapitalRank value
                if (isset($response['data']['Socials']['Social'][0]['socialCapital']['socialCapitalRank'])) {
                    return $response['data']['Socials']['Social'][0]['socialCapital']['socialCapitalRank'];
                } else {
                    return null;
                    // throw new Exception('SocialCapitalRank not found in the response.');
                }
            }
        );
    }

    public function getStreak($fid)
    {
        $url = 'https://client.warpcast.com/v2/channel-streaks';
        $params = ['fid' => $fid];
        $headers = [
            'Authorization' => 'MK-XdU9X1oj/US4E1xwKe8pACcvERsgvSNBK7OXEulbRX93ppgnGtyiMf4YrKztuFL4WTmjchAxkeyMdqs4fyqs9g==',
            'accept' => 'application/json'
        ];

        $responsePromise = $this->make_api_request($url, $params, $headers);

        return $responsePromise->then(function ($response) {
            if ($response === null) {
                echo "Error fetching Farcaster stats.\n";
                return null;
            }

            // Extract and return only the timestamp
            if (isset($response)) {
                return $response;
            } else {
                echo "No casts found.\n";
                return null;
            }
        });
    }

    /**
     * getRareStats function
     *
     * This function returns all the user's information from the Rare Protocol.
     * The information includes allowance, the number of tips sent, the number of tips received, 
     * and other related data.
     *
     * @return array An array containing various user information from the Rare Protocol
     */
    public function getRareStats($walletAddress)
    {
        $graphqlEndpoint = 'https://api.rare.xyz/v1/graphql';

        $query = <<<'GRAPHQL'
        query RareTipUsers($targetAddresses: [String!], $pagination: PaginationInput!) {
            rareTipUsers(targetAddresses: $targetAddresses, pagination: $pagination) {
                points
                totalAmountReceived
                totalAmountTipped
                totalTipCount
                allowanceRemaining
                allowanceTipped
                dailyAllowance
                dailyAmountReceived
                dailyTipCount
                farcasterProfile {
                    fName
                    fid
                }
                address
                isEligible
                srProfile {
                    srAvatarUri
                    srName
                    twitterHandle
                }
            }
        }
GRAPHQL;

        if (!is_array($walletAddress)) {
            $walletAddress = [$walletAddress];
        }

        // Variables
        $variables = [
            'targetAddresses' => $walletAddress,
            'pagination' => [
                'limit' => 1,
                'offset' => 0
            ]
        ];

        return $this->make_graphql_request($graphqlEndpoint, $query, $variables)->then(
            function ($data) {
                if (isset($data['data']['rareTipUsers']) && is_array($data['data']['rareTipUsers'])) {
                    $eligibleUsers = array_filter($data['data']['rareTipUsers'], function ($user) {
                        return isset($user['isEligible']) && $user['isEligible'] === true;
                    });
                    if (empty($eligibleUsers)) {
                        $eligibleUsers = array_filter($data['data']['rareTipUsers'], function ($user) {
                            if (isset($user['srProfile']) && $user['srProfile']['srAvatarUri'] !== null) {
                                return true;
                            }
                            return isset($user['srProfile']['srName']) && !empty($user['srProfile']['srName']);
                        });
                    }
                    $data['data']['rareTipUsers'] = array_values($eligibleUsers);
                }
                return $data;
            }
        );
    }

    public function getOpenRankStats($fid)
    {
        // Prepare payload as expected by the API
        $params = [$fid];

        $engagementUrl = 'https://graph.cast.k3l.io/scores/global/engagement/fids?engagement_type=2.0';
        $followingUrl = 'https://graph.cast.k3l.io/scores/global/following/fids';

        $channel_urls = array(
            'superrare' => 'https://graph.cast.k3l.io/channels/rankings/superrare/fids',
        );


        // Make asynchronous requests
        $engagementPromise = $this->make_api_request($engagementUrl, $params, [], "POST");
        $followingPromise = $this->make_api_request($followingUrl, $params, [], "POST");

        // Add promises for channel URLs
        $channelPromises = [];
        foreach ($channel_urls as $key => $url) {
            $channelPromises[$key] = $this->make_api_request($url, $params, [], "POST");
        }

        // Combine all promises
        $allPromises = array_merge(
            [
                'engagement' => $engagementPromise,
                'following' => $followingPromise,
            ],
            $channelPromises
        );

        return Utils::all($allPromises)->then(function ($responses) {
            return [
                'engagement' => $responses['engagement'],
                'superrare' => $responses['superrare']
            ];
        });
    }

    public function getFcStats($fid)
    {
        $cacheFile = 'power-users.json';
        $cacheDuration = 24 * 60 * 60;

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheDuration)) {
            $data = json_decode(file_get_contents($cacheFile), true);
        } else {
            $url = 'https://api.warpcast.com/v2/power-badge-users';
            $responsePromise = $this->make_api_request($url);
            $response = $responsePromise->wait();

            if ($response === null) {
                return \GuzzleHttp\Promise\Create::promiseFor(["users" => []]);
            }

            $data = $response;
            file_put_contents($cacheFile, json_encode($data));
        }

        if (isset($data['result']) && isset($data['result']['fids'])) {
            $fids = $data['result']['fids'];

            if (in_array($fid, $fids)) {
                return \GuzzleHttp\Promise\Create::promiseFor(["users" => [["power_badge" => true]]]);
            }
        }

        return \GuzzleHttp\Promise\Create::promiseFor(["users" => []]);
    }

    public function executeRequest($fid, $walletAddress)
    {
        $promises = [
            'openrank' => $this->getOpenRankStats($fid),
            'rare' => $this->getRareStats($walletAddress),
            'social_capital_rank' => $this->getSocialCapitalRank('fc_fid:' . $fid . ''),
            'trend' => $this->fetchTrends(),
            'streak' => $this->getStreak($fid),
            'fc' => $this->getFcStats($fid),
        ];

        Utils::settle($promises)->wait();

        $results = [];
        foreach ($promises as $key => $promise) {
            $results[$key] = $promise->wait();
        }

        $combined_data = [
            'openrank' => $results['openrank'] ?? null,
            'rare' => $results['rare'] ?? null,
            'social_capital_rank' => $results['social_capital_rank'] ?? null,
            'trend' => $results['trend'] ?? null,
            'fc' => $results['fc'] ?? null,
            'streak' => $results['streak'] ?? null,
        ];

        $json_data = json_encode($combined_data, JSON_PRETTY_PRINT);
        return $json_data;
    }
}

function formatDate($dateStr)
{
    // Convert the date string to a timestamp
    $timestamp = strtotime($dateStr);

    // Format the timestamp into "d M" format
    return date("d M", $timestamp);
}

function getColorBasedOnScore($image, $score)
{
    if ($score > 80) {
        return imagecolorallocate($image, 0, 133, 63);
    } elseif ($score > 50) {
        return imagecolorallocate($image, 124, 191, 65);
    } elseif ($score > 30) {
        return imagecolorallocate($image, 255, 143, 32);
    } else {
        return imagecolorallocate($image, 255, 76, 34);
    }
}

function isValidEthereumAddress($address)
{
    if (strlen($address) !== 42) {
        return false;
    }

    if (substr($address, 0, 2) !== '0x') {
        return false;
    }

    if (!ctype_xdigit(substr($address, 2))) {
        return false;
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $randomNumber = rand(1000, 9999);
    $responseUser = null;

    try {
        $body = json_decode(file_get_contents('php://input'), true);

        $fid = $body['untrustedData']['fid'] ?? null;
        $trustedDataMessageBytes = $body['trustedData']['messageBytes'] ?? null;
        $apiKey = '1a6ac181d02964c2794102094a8a1d1b9';
        $validator = new FramesValidator($apiKey);

        $validator->validateMessage($trustedDataMessageBytes);

        // Check if the fid is blocked
        if ($fidVerifier->checkBlockedFid($fid)) {
            $frameImageResult = $currentFolder . '/assets/rare-reported.jpg?ts=' . $timestamp;

            echo '
            <html lang="en">
            <head>
            <meta property="fc:frame" content="vNext" />
            <meta name="viewport" content="width=device-width"/>
            <meta property="fc:frame:image" content="' . $frameImageResult . '"/>
            <meta property="fc:frame:image:aspect_ratio" content="1:1" />
            <meta property="fc:frame:post_url" content="' . $framePostUrl . '" />
            <title>Service Banned</title>
            </head>
            </html>';
        }

        $buttonIndex = $body['untrustedData']['buttonIndex'] ?? null;
        $inputText = $body['untrustedData']['inputText'] ?? null;
        $address = $body['untrustedData']['address'] ?? null;

        $isUsername = false;
        $isWalletAddress = false;

        if ($inputText !== null) {
            // Regex pattern for matching the address
            $addressPattern = '/^0x[a-fA-F0-9]{40}$/';
            // Regex pattern for matching usernames
            $usernamePattern = '/^[a-zA-Z0-9]+(\.eth)?$/';

            // Check if the input matches the address pattern
            if (preg_match($addressPattern, $inputText)) {
                $isWalletAddress = true;
            }
            // Check if the input matches the username pattern
            elseif (preg_match($usernamePattern, $inputText)) {
                $isUsername = true;
            } else {
            }
        }

        //Search 🔎
        if ($buttonIndex == 2 && isset($inputText)) {
            $apiKey = 'Q182P-FZUCK-NG5KV-M92GC-F0LO1';
            $fid = getFidByUsername($inputText, $apiKey);

            if (empty($fid) || $fid == null) {
                $fid = $body['untrustedData']['fid'] ? $body['untrustedData']['fid'] : null;
            }

            $fidVerifier = new FidVerifier();
            if ($fidVerifier->checkBlockedFid($fid)) {
                $frameImageResult = $currentFolder . '/assets/rare-reported.jpg?ts=' . $timestamp;

                echo '
            <html lang="en">
            <head>
            <meta property="fc:frame" content="vNext" />
            <meta name="viewport" content="width=device-width"/>
            <meta property="fc:frame:image" content="' . $frameImageResult . '"/>
            <meta property="fc:frame:image:aspect_ratio" content="1:1" />
            <meta property="fc:frame:post_url" content="' . $framePostUrl . '" />
            <title>Service Banned</title>
            </head>
            </html>';
                // exit;
            }

            // Get User Data
            $responseUser = $client->request('GET', 'https://build.far.quest/farcaster/v2/user?fid=' . $fid, [
                'headers' => [
                    'API-KEY' => 'Q182P-FZUCK-NG5KV-M92GC-F0LO1',
                    'accept' => 'application/json',
                ],
            ]);
        } else {
            // Get User Data
            $responseUser = $client->request('GET', 'https://build.far.quest/farcaster/v2/user?fid=' . $fid, [
                'headers' => [
                    'API-KEY' => 'Q182P-FZUCK-NG5KV-M92GC-F0LO1',
                    'accept' => 'application/json',
                ],
            ]);
        }
        if ($buttonIndex == 3) {
        }



        $userData = json_decode($responseUser->getBody(), true);


        // Regex pattern for matching the address
        $addressPattern = '/^0x[a-fA-F0-9]{40}$/';
        // Regex pattern for matching usernames
        $usernamePattern = '/^[a-zA-Z0-9]+(\.eth)?$/';

        // Check if the input matches the address pattern
        if (preg_match($addressPattern, $inputText)) {
            $walletAddress = convertToLowercase($inputText);
        }
        // Check if the input matches the username pattern
        elseif (preg_match($usernamePattern, $inputText)) {

            if ($userData !== null && isset($userData['result']['user'])) {
                $walletAddresses = [];

                if (!empty($userData['result']['user']['allConnectedAddresses']['ethereum'])) {
                    $addresses = $userData['result']['user']['allConnectedAddresses']['ethereum'];
                    if (is_array($addresses)) {
                        foreach ($addresses as $address) {
                            if (isValidEthereumAddress($address)) {
                                $walletAddresses[] = $address;
                            }
                        }
                    } else {
                        if (isValidEthereumAddress($addresses)) {
                            $walletAddresses[] = $addresses;
                        }
                    }
                }

                if (isset($userData['result']['user']['connectedAddress'])) {
                    $address = $userData['result']['user']['connectedAddress'];
                    if (isValidEthereumAddress($address)) {
                        $walletAddresses[] = $address;
                    }
                }

                if (isset($userData['result']['user']['custodyAddress'])) {
                    $address = $userData['result']['user']['custodyAddress'];
                    if (isValidEthereumAddress($address)) {
                        $walletAddresses[] = $address;
                    }
                }

                $walletAddress = $walletAddresses;
            } else {
                $walletAddress = [];
            }
        } else {

            if ($userData !== null && isset($userData['result']['user'])) {
                $walletAddresses = [];

                if (!empty($userData['result']['user']['allConnectedAddresses']['ethereum'])) {
                    $addresses = $userData['result']['user']['allConnectedAddresses']['ethereum'];
                    if (is_array($addresses)) {
                        foreach ($addresses as $address) {
                            if (isValidEthereumAddress($address)) {
                                $walletAddresses[] = $address;
                            }
                        }
                    } else {
                        if (isValidEthereumAddress($addresses)) {
                            $walletAddresses[] = $addresses;
                        }
                    }
                }

                if (isset($userData['result']['user']['connectedAddress'])) {
                    $address = $userData['result']['user']['connectedAddress'];
                    if (isValidEthereumAddress($address)) {
                        $walletAddresses[] = $address;
                    }
                }

                if (isset($userData['result']['user']['custodyAddress'])) {
                    $address = $userData['result']['user']['custodyAddress'];
                    if (isValidEthereumAddress($address)) {
                        $walletAddresses[] = $address;
                    }
                }

                $walletAddress = $walletAddresses;
            } else {
                $walletAddress = [];
            }
        }



        $framePostUrl = $currentURL;

        ///////// GENERATING...

        // Increase image resolution
        $width = 1080;
        $height = 1080;

        $maxAmountInfo = null;


        // Set the content type header to indicate that the response will be an image
        header('Content-Type: image/png');

        $users = array();

        $output = [];

        function adjustAllowance($allowanceRemaining)
        {
            // Define a small tolerance value
            $tolerance = 1.0e-12;

            // Check if the absolute value is within the tolerance range
            if (abs($allowanceRemaining) < $tolerance) {
                return 0;
            }

            // Return 0 if the allowanceRemaining is negative
            if ($allowanceRemaining < 0) {
                return 0;
            }

            // Return the original value if it is outside the tolerance range and positive
            return $allowanceRemaining;
        }


        /**
         * Crop an image while preserving aspect ratio.
         *
         * @param resource $image Source image resource
         * @param int $targetWidth Target width of the cropped image
         * @param int $targetHeight Target height of the cropped image
         * @return resource|false Returns the cropped image resource or false on failure
         */
        function preserveAspectCrop($image, $targetWidth, $targetHeight)
        {
            // Get the original dimensions of the image
            $originalWidth = imagesx($image);
            $originalHeight = imagesy($image);

            // Calculate the aspect ratio of the original image
            $originalAspectRatio = $originalWidth / $originalHeight;

            // Calculate the aspect ratio of the target dimensions
            $targetAspectRatio = $targetWidth / $targetHeight;

            // Calculate the new dimensions of the cropped image
            if ($originalAspectRatio > $targetAspectRatio) {
                // Original image is wider than the target dimensions
                $newWidth = $originalHeight * $targetAspectRatio;
                $newHeight = $originalHeight;
            } else {
                // Original image is taller than or same aspect ratio as the target dimensions
                $newWidth = $originalWidth;
                $newHeight = $originalWidth / $targetAspectRatio;
            }

            // Calculate the coordinates for cropping the image
            $cropX = ($originalWidth - $newWidth) / 2;
            $cropY = ($originalHeight - $newHeight) / 2;

            // Create a blank canvas for the cropped image
            $croppedImage = imagecreatetruecolor($targetWidth, $targetHeight);

            // Crop and copy the original image onto the canvas
            // $success = imagecopyresampled($croppedImage, $image, 0, 0, $cropX, $cropY, $targetWidth, $targetHeight, $newWidth, $newHeight);
            $roundedCropX = round($cropX);
            $roundedCropY = round($cropY);
            $success = imagecopyresampled(
                $croppedImage,
                $image,
                0,
                0,
                $roundedCropX,
                $roundedCropY,
                $targetWidth,
                $targetHeight,
                $newWidth,
                $newHeight
            );

            if ($success) {
                return $croppedImage;
            } else {
                return false;
            }
        }

        /////////////////// OPEN QUERIES /////////////////// 

        $statsFetcher = new StatsFetcher();
        $data = json_decode($statsFetcher->executeRequest($fid, $walletAddress), true);

        $hasApiError = false;
        try {
            if ($data['rare'] == null) {
                $hasApiError = true;
                $frameImageResult = $currentFolder . '/assets/rare-server-error.jpg?ts=' . $timestamp;

                echo '
        <html lang="en">
        <head>
        <meta property="fc:frame" content="vNext" />
        <meta name="viewport" content="width=device-width"/>
        <meta property="fc:frame:image" content="' . $frameImageResult . '"/>
        <meta property="fc:frame:image:aspect_ratio" content="1:1" />
        <meta property="fc:frame:post_url" content="' . $framePostUrl . '" />
        
        <meta property="fc:frame:button:1" content="My Stats">
        <meta property="fc:frame:button:1:action" content="post">
        <meta property="fc:frame:button:1:target" content="' . $framePostUrl . '">

        <meta property="fc:frame:input:text" content="Enter username or wallet address" />
        <meta property="fc:frame:button:2" content="🔎">
        <meta property="fc:frame:button:2:action" content="post">
        <meta property="fc:frame:button:2:target" content="' . $framePostUrl . '">
        
        <title>' . $config['header']['metaTitle'] . '</title>
          </head>
        </html>';
            }

            if ($hasApiError !== false) {
                // Process $result data
                $hasApiError = false;

                // Filter results to only include those where isEligible is true
                $filteredResults = array_filter($data['rare']['data']['rareTipUsers'], function ($user) {
                    return $user['isEligible'] === true;
                });

                // Get the first eligible result if it exists, otherwise get the second user
                if (!empty($filteredResults)) {
                    $eligibleResult = reset($filteredResults);
                    // Output the data object with rareTipUsers array
                    $data = ['data' => ['rareTipUsers' => [$eligibleResult]]];
                } else {
                    if (isset($data['data']['rareTipUsers'][1])) {
                        $eligibleResult = $data['data']['rareTipUsers'][1];
                        // Output the data object with rareTipUsers array
                        $data = ['data' => ['rareTipUsers' => [$eligibleResult]]];
                    } else {
                        // Output the data object with rareTipUsers array
                        $data = ['data' => ['rareTipUsers' => $data['data']['rareTipUsers']]];
                    }
                }
            }
        } catch (ClientException $e) {
            $hasApiError = true;
        }


        if ($hasApiError == true) {

            $frameImageResult = $currentFolder . '/assets/rare-server-api-error.jpg?ts=' . $timestamp;
        } else if (isset($data['errors'])) {

            $frameImageResult = $currentFolder . '/assets/rare-server-error.jpg?ts=' . $timestamp;
        } else {

            $points = 0;
            $dailyAmountReceived = 0;
            $totalReceived = 0;
            $dailyTipCount = 0;
            $totalTipCount = 0;
            $totalAmountTipped = 0.0;
            $dailyAllowance = 0.0;
            $allowanceRemaining = 0.0;
            $allowanceRemainingFull = 0.0;

            $fName = null;
            $srAvatar = null;
            $xTwitterHandle = null;



            if (isset($data['rare']['data']['rareTipUsers'])) {
                foreach ($data['rare']['data']['rareTipUsers'] as $user) {
                    if (isset($user['isEligible'])) {
                        $isEligible = $user['isEligible'];
                    }

                    // Check if farcasterProfile is not empty
                    if (!empty($user['farcasterProfile'])) {
                        // Access the first object in the array
                        $farcasterProfile = $user['farcasterProfile'][0];
                        $fName = $farcasterProfile['fName'];
                        $hasResult = true;
                    }
                    // Check if srProfile is not empty
                    if (!empty($user['srProfile'])) {
                        // Access the srProfile array
                        $srProfile = $user['srProfile'];
                        // Access srAvatarUri within srProfile
                        $srAvatar = $srProfile['srAvatarUri'];
                        $xTwitterHandle = isset($srProfile['twitterHandle']) ? '@' . $srProfile['twitterHandle'] : 'Not Set';
                        if ($srProfile['twitterHandle'] == "https") {
                            $xTwitterHandle = "N/A";
                        }
                    }
                }


                foreach ($data['rare']['data']['rareTipUsers'] as $user) {
                    $points = $user['points'];
                    $dailyAmountReceived = $user['dailyAmountReceived'];
                    $totalReceived = $user['totalAmountReceived'];
                    $dailyTipCount = $user['dailyTipCount'];
                    $totalTipCount = $user['totalTipCount'];
                    $dailyAllowance = $user['dailyAllowance'] ? $user['dailyAllowance'] : 0.0;
                    $totalAmountTipped = $user['totalAmountTipped'] ? $user['totalAmountTipped'] : 0.0;
                    $allowanceRemaining = $user['allowanceRemaining'] ? $user['allowanceRemaining'] : 0.0;
                    $allowanceRemainingFull = $user['allowanceRemaining'];
                }
            } else {
                $hasResult = false;
            }

            /////////////////// CLOSE QUERIES /////////////////// 

            // Create a blank image with increased dimensions
            $image = imagecreatetruecolor($width, $height);

            $backgroundImagePath = $currentFolder . '/assets/rare-stats-dev.jpg?ts=' . $imageTimestamp;

            $backgroundImage = imagecreatefromjpeg($backgroundImagePath);

            // Define colors
            $backgroundColor = imagecolorallocate($image, 255, 255, 255); // white
            $textColor = imagecolorallocate($image, 0, 0, 0); // black
            $tableHeaderColor = imagecolorallocate($image, 0, 0, 0); // white for table header
            $rectangleColor = imagecolorallocate($image, 0, 0, 0); // black

            // Fill the background with white color
            imagefilledrectangle($image, 0, 0, $width, $height, $backgroundColor);

            // Resize background image to fit the canvas
            list($bgWidth, $bgHeight) = getimagesize($backgroundImagePath);
            imagecopyresampled($image, $backgroundImage, 0, 0, 0, 0, $width, $height, $bgWidth, $bgHeight);

            // Load a font (replace assets/Inter/Inter-Medium.ttf with the path to your font file)
            $fontPath = 'assets/Inter/Inter-Medium.ttf';
            $fontBoldPath = 'assets/Inter/Inter-Bold.ttf';
            $fontIconPath = 'assets/NotoColorEmoji-Regular.ttf';
            $fontSize = 24;


            $owner = null;
            // Get User Data
            $responseUser = $client->request('GET', 'https://build.far.quest/farcaster/v2/user?fid=' . $fid, [
                'headers' => [
                    'API-KEY' => 'Q182P-FZUCK-NG5KV-M92GC-F0LO1',
                    'accept' => 'application/json',
                ],
            ]);

            if ($userData !== null && isset($userData['result']['user'])) {
                $owner = $userData['result']['user'];
            }

            $fName = isset($owner['username']) ? $owner['username'] : '';

            $config = json_decode(file_get_contents('../lib/dbconfig.json'), true);
            $mysqlConfig = $config['mysql']['databases']['superrare']; // Adjust for different databases

            $servername = $config['mysql']['host'];
            $username = $mysqlConfig['user'];
            $password = $mysqlConfig['password'];
            $dbname = $mysqlConfig['name'];


            // $conn = new mysqli($servername, $username, $password, $dbname);
            $conn = new mysqli($servername, $username, $password, $dbname);

            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }

            if (is_array($walletAddress) && !empty($walletAddress)) {
                $walletAddress = $walletAddress[0];
            } else {
                $walletAddress = $walletAddress;
            }

            $timestamp = time();

            $sql = "INSERT INTO user_allowance (fid, username, wallet_address, count, last_timestamp)
        VALUES (?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE count = count + 1, last_timestamp = VALUES(last_timestamp)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issi", $fid, $fName, $walletAddress, $timestamp);
            $stmt->execute();
            $stmt->close();

            $conn->close();

            // Initial Y position for the table header
            $tableHeaderY = 440;

            // Draw table header titles
            $columnWidth = 320;
            $columnNameX = 90;
            $columnTippingTimeX = $columnNameX + $columnWidth + 50;
            $columnPointsX = $columnTippingTimeX + $columnWidth + 50;
            $columnTitleFontSize = $fontSize + 2;

            // Initial Y position for the first user data row
            $userDataRowY = $tableHeaderY + 80;

            $fontSizeContent = 20;

            // Check if the avatar URL is set and not empty
            if (!empty($srAvatar)) {
                // Load the avatar image from URL
                $avatar = @imagecreatefromjpeg($srAvatar);

                // Check if the image was loaded successfully
                if ($avatar !== false) {
                    // Resize the image while preserving aspect ratio and fitting it into the bounding box
                    $resizedAvatar = preserveAspectCrop($avatar, 120, 120);

                    // Check if the resizing was successful
                    if ($resizedAvatar !== false) {
                        // Draw the resized image onto the main image at the specified position
                        imagecopyresampled($image, $resizedAvatar, 580, 690, 0, 0, 120, 120, imagesx($resizedAvatar), imagesy($resizedAvatar));

                        // Free memory by destroying the resized image resource
                        imagedestroy($resizedAvatar);
                    } else {
                        // Handle the case when resizing fails
                        echo "Failed to resize the avatar image.";
                    }

                    // Free memory by destroying the original avatar image resource
                    imagedestroy($avatar);
                }
            }


            // Create a blank image with dimensions 200x200
            $imageWidth = 80;
            $imageHeight = 80;

            // Allocate colors
            $backgroundColor = imagecolorallocate($image, 240, 240, 240);
            $progressColor = null;
            $fontColor = imagecolorallocate($image, 0, 0, 0); // black color for text

            // Calculate remaining time until 00:00 GMT
            $currentTimestamp = time();
            $gmtTimestamp = gmmktime(0, 0, 0, gmdate('n', $currentTimestamp), gmdate('j', $currentTimestamp), gmdate('Y', $currentTimestamp));
            $remainingTimeSeconds = $gmtTimestamp - $currentTimestamp;

            // Calculate total time until next 00:00 GMT
            $totalTimeSeconds = 24 * 60 * 60; // 24 hours in seconds

            // Calculate progress angle
            $progressAngle = (($totalTimeSeconds - $remainingTimeSeconds) / $totalTimeSeconds) * 360;

            // Add text for remaining time
            $remainingTimeString = gmdate('H:i:s', $remainingTimeSeconds);
            $remainingTimeString = preg_replace('/(?<=\d):/', ' :', $remainingTimeString);


            // Calculate remaining time in hours
            if ($remainingTimeSeconds < 0) {
                $remainingTimeSeconds += 24 * 60 * 60; // Add 24 hours in seconds
            }

            $remainingTimeHours = $remainingTimeSeconds / 3600;

            // Define the colors for different remaining time ranges
            if ($remainingTimeHours > 12) {
                $progressColor = imagecolorallocate($image, 76, 175, 80); // Green color
            } elseif ($remainingTimeHours <= 12 && $remainingTimeHours > 6) {
                $progressColor = imagecolorallocate($image, 255, 165, 0); // Orange color
            } else {
                $progressColor = imagecolorallocate($image, 255, 0, 0); // Red color
            }


            // Define the center and radius of the circle
            $centerX = 470;
            $centerY = 512;
            $radius = $imageWidth / 3; // You can adjust the radius as needed

            // Add text for remaining time
            imagettftext($image, $fontSize - 3, 0, 280, 530, imagecolorallocate($image, 0, 0, 0), $fontPath, $remainingTimeString);
            imagettftext($image, $fontSize - 3, 0, 413, 520, imagecolorallocate($image, 0, 0, 0), $fontPath, '″');
            imagettftext($image, $fontSize - 3, 0, 363, 520, imagecolorallocate($image, 0, 0, 0), $fontPath, "′");

            // Draw progress circle sector
            imagefilledarc($image, $centerX, $centerY, round($radius * 2), round($radius * 2), -90, -90 + floor($progressAngle), $progressColor, IMG_ARC_PIE);

            $titleOwner = "@" . $fName;
            imagettftext($image, $fontSize + 3, 0, 600, 640, imagecolorallocate($image, 255, 255, 255), $fontPath, $titleOwner);

            $fidOwner = $fid;
            imagettftext($image, $fontSize - 8, 0, 610, 837, imagecolorallocate($image, 0, 0, 0), $fontPath, $fidOwner);


            //Season
            imagettftext($image, $fontSize + 4, 0, 310, 216, imagecolorallocate($image, 0, 0, 0), $fontPath, "3");

            $targetDate = '2024-10-09';

            // Get the current date
            $currentDate = date('Y-m-d');

            // Convert dates to DateTime objects
            $targetDateTime = new DateTime($targetDate);
            $currentDateTime = new DateTime($currentDate);

            // Calculate the difference
            $interval = $currentDateTime->diff($targetDateTime);

            // Get the number of remaining days
            $remainingDays = $interval->days;

            $percentageDifference = $data['trend']['percentageDifference'];
            $trendCastsCount = $data['trend']['count'];

            if ($trendCastsCount >= 1000) {
                $formattedCastValue = number_format($trendCastsCount / 1000, 1);
                $formattedCastValue = rtrim($formattedCastValue, '.0') . 'k';
            } else {
                $formattedCastValue = number_format($trendCastsCount);
            }

            // Access the rank for the 'engagement' category
            if (isset($data['openrank']['engagement']['result'][0]['rank'])) {
                $engagementRank = $data['openrank']['engagement']['result'][0]['rank'];
            } else {
                $engagementRank = "N/A";
            }
            // Access the rank for the 'superrare' category
            if (isset($data['openrank']['superrare']['result'][0]['rank'])) {
                $superRareRank = $data['openrank']['superrare']['result'][0]['rank'];
            } else {
                $superRareRank = "N/A";
            }

            // Define colors
            $redColor = imagecolorallocate($image, 252, 82, 4); // Red color
            $greenColor = imagecolorallocate($image, 63, 190, 143); // Green color

            $countTextColor = null;
            // Check if the count has a negative sign
            if (strpos($percentageDifference, '-') !== false) {
                $countTextColor = $redColor; // Set text color to red for negative count
                $percentageDifference = round($percentageDifference, 3);
            } else {
                $countTextColor = $greenColor; // Set text color to green for non-negative count
                $percentageDifference = '+' . round($percentageDifference, 3);
            }

            imagettftext($image, $fontSize + 1, 0, 870, 255, $countTextColor, $fontPath, number_format($percentageDifference, 2) . "%");
            imagettftext($image, $fontSize + 1, 0, 700, 255, imagecolorallocate($image, 0, 0, 0), $fontPath, $formattedCastValue);
            imagettftext($image, $fontSize - 7, 0, 510, 210, imagecolorallocate($image, 0, 0, 0), $fontPath, "TBA");
            imagettftext($image, $fontSize - 6, 0, 560, 250, imagecolorallocate($image, 0, 0, 0), $fontPath, "-");
            imagettftext($image, $fontSize - 4, 0, 940, 765, imagecolorallocate($image, 0, 0, 0), $fontPath, $superRareRank . 'th');
            imagettftext($image, $fontSize - 4, 0, 940, 810, imagecolorallocate($image, 0, 0, 0), $fontPath, $engagementRank);
            imagettftext($image, $fontSize - 4, 0, 940, 850, imagecolorallocate($image, 0, 0, 0), $fontPath, $data['social_capital_rank'] ? number_format($data['social_capital_rank'], 0, '.', ',') : "N/A");

            $boostRate = calculateBoostPower($superRareRank);
            imagettftext($image, $fontSize - 4, 0, 940, 890, getColorBasedOnScore($image, $boostRate), $fontBoldPath, $boostRate);
            $userStreak = (string)$data['streak']['result']['streak']['streakCount'];
            if ($userStreak) {
                imagettftext($image, $fontSize + 4, 0, 580, 890, imagecolorallocate($image, 245, 127, 0), $fontPath, $userStreak ? $userStreak : "N/A");
            } else {
                imagettftext($image, $fontSize + 4, 0, 575, 890, imagecolorallocate($image, 245, 127, 0), $fontPath, $userStreak ? $userStreak : "N/A");
            }

            $powerBadgeImage = @imagecreatefrompng("assets/power-enabled.png");
            // Create a true color image with alpha channel support
            $resizedPowerBadge = imagecreatetruecolor(64, 64);

            // Enable alpha blending and save alpha
            imagealphablending($resizedPowerBadge, false);
            imagesavealpha($resizedPowerBadge, true);

            // Fill the image with a transparent color
            $transparentColor = imagecolorallocatealpha($resizedPowerBadge, 0, 0, 0, 127);
            imagefill($resizedPowerBadge, 0, 0, $transparentColor);
            imagecopyresampled($resizedPowerBadge, $powerBadgeImage, 440, 600, 0, 0, 64, 64, imagesx($powerBadgeImage), imagesy($powerBadgeImage));

            $allowanceRemaining = adjustAllowance($allowanceRemaining);
            $decimalPosition = strpos($allowanceRemaining, '.');
            if ($decimalPosition !== false) {
                $allowanceRemaining = substr($allowanceRemaining, 0, $decimalPosition + 4); // Take three digits after the decimal point
            }

            if ($dailyAllowance != 0) {
                $remainingPercentage = ($allowanceRemaining / $dailyAllowance) * 100;
            } else {
                // Handle the case when $dailyAllowance is zero
                $remainingPercentage = 0; // or any other appropriate value or error handling
            }

            $remainingPercentage = round($remainingPercentage, 1);

            if ($dailyAllowance == 0.0 || $allowanceRemaining == 0) {
                $energy = @imagecreatefrompng("assets/no-egenry.png");
            } else {
                if ($remainingPercentage >= 70) {
                    $energy = @imagecreatefrompng("assets/full-egenry.png");
                } elseif ($remainingPercentage >= 20 && $remainingPercentage < 70) {
                    $energy = @imagecreatefrompng("assets/med-egenry.png");
                } else {
                    $energy = @imagecreatefrompng("assets/low-egenry.png");
                }
            }

            // Create a true color image with alpha channel support
            $resizedBattery = imagecreatetruecolor(72, 72);

            // Enable alpha blending and save alpha
            imagealphablending($resizedBattery, false);
            imagesavealpha($resizedBattery, true);

            // Fill the image with a transparent color
            $transparentColor = imagecolorallocatealpha($resizedBattery, 0, 0, 0, 127);
            imagefill($resizedBattery, 0, 0, $transparentColor);

            // Resize the image
            imagecopyresampled($resizedBattery, $energy, 0, 0, 0, 0, 72, 72, imagesx($energy), imagesy($energy));

            // Enable alpha blending and save alpha for the destination image
            imagealphablending($image, true);
            imagesavealpha($image, true);

            // Copy the resized image onto the destination image
            imagecopyresampled($image, $resizedBattery, 440, 400, 0, 0, 72, 72, imagesx($resizedBattery), imagesy($resizedBattery));

            //// TRUST
            $userStar = @imagecreatefrompng("assets/star-no.png");


            // Create a true color image with alpha channel support
            $resizedStar = imagecreatetruecolor(164, 33);

            // Enable alpha blending and save alpha
            imagealphablending($resizedStar, false);
            imagesavealpha($resizedStar, true);

            // Fill the image with a transparent color
            $transparentColor = imagecolorallocatealpha($resizedStar, 0, 0, 0, 127);
            imagefill($resizedStar, 0, 0, $transparentColor);

            // Resize the image
            imagecopyresampled($resizedStar, $userStar, 0, 0, 0, 0, 164, 33, imagesx($userStar), imagesy($userStar));

            // Enable alpha blending and save alpha for the destination image
            imagealphablending($image, true);
            imagesavealpha($image, true);

            // Copy the resized image onto the destination image
            imagecopyresampled($image, $resizedStar, 850, 695, 0, 0, 164, 33, imagesx($resizedStar), imagesy($resizedStar));


            /// TRUST
            imagettftext($image, $fontSize + 4, 0, 280, 420, imagecolorallocate($image, 0, 0, 0), $fontPath, $dailyAllowance);
            imagettftext($image, $fontSize + 4, 0, 280, 475, imagecolorallocate($image, 0, 0, 0), $fontPath, $allowanceRemaining);

            // imagettftext($image, $fontSize - 12, 0, 280, 445, imagecolorallocate($image, 0, 0, 0), $fontPath, $allowanceRemainingFull);
            imagettftext($image, $fontSize + 4, 0, 820, 425, imagecolorallocate($image, 0, 0, 0), $fontPath, $dailyTipCount);
            imagettftext($image, $fontSize + 4, 0, 820, 480, imagecolorallocate($image, 0, 0, 0), $fontPath, $totalTipCount);
            imagettftext($image, $fontSize + 4, 0, 820, 530, imagecolorallocate($image, 0, 0, 0), $fontPath, substr($totalAmountTipped, 0, 7));


            $redColor = imagecolorallocate($image, 252, 82, 4); // Red color
            $greenColor = imagecolorallocate($image, 63, 190, 143); // Green color

            if (isset($isEligible) && $isEligible == true) {
                imagettftext($image, $fontSize - 3, 0, 290, 266, $greenColor, $fontPath, "Yes");
            } else {
                imagettftext($image, $fontSize - 3, 0, 290, 266, $redColor, $fontPath, "No");
            }

            //isEligible

            imagettftext($image, $fontSize + 4, 0, 350, 711, imagecolorallocate($image, 0, 0, 0), $fontPath, round($dailyAmountReceived, 3));

            $imageWidth = imagesx($image);
            $imageHeight = imagesy($image);

            // The size of your font and the text to be centered
            $fontSize = 24; // Adjust as necessary
            $totalReceived = round($totalReceived, 3);

            // Calculate the bounding box of the text
            $bbox = imagettfbbox($fontSize + 12, 0, $fontBoldPath, $totalReceived);

            // Calculate the width of the text
            $textWidth = $bbox[2] - $bbox[0];

            // Calculate the height of the text (optional, if you want to center vertically too)
            $textHeight = $bbox[1] - $bbox[7];

            // Calculate X and Y coordinates to center the text
            $x = ($imageWidth / 4) - ($textWidth / 4);
            $y = ($imageHeight / 2) + ($textHeight / 2); // Adjust as necessary for vertical positioning

            // Set the color for the text
            $color = imagecolorallocate($image, 6, 137, 67);

            // Add the text to the image
            if (hasDecimal($totalReceived) && $totalReceived < 1000) {
                imagettftext($image, $fontSize + 5, 0, $x + 10, $y + 235, $color, $fontBoldPath, number_format((float)$totalReceived, 3, '.', ','));
            } else if (!hasDecimal($totalReceived) && $totalReceived < 1000) {
                imagettftext($image, $fontSize + 12, 0, $x + 15, $y + 235, $color, $fontBoldPath, number_format((float)$totalReceived, 3, '.', ','));
            } else {
                if (hasDecimal($totalReceived)) {
                    imagettftext($image, $fontSize + 12, 0, $x - 35, $y + 235, $color, $fontBoldPath, number_format((float)$totalReceived, 3, '.', ','));
                } else {
                    imagettftext($image, $fontSize + 12, 0, $x + 3, $y + 235, $color, $fontBoldPath, number_format((float)$totalReceived, 3, '.', ','));
                }
            }

            // Define the folder path to save the images
            $folderPath = 'share/';

            // Check if the folder exists, if not, create it
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }

            ob_start();

            // Define compression quality for JPEG (0 - low quality, 100 - high quality)
            $compression_quality = 80;

            // Save the image as JPG with specified compression quality
            imagejpeg($image, $folderPath . $fid . '-all.jpg', $compression_quality);

            // Output the image as JPEG with compression
            imagejpeg($image, null, $compression_quality);

            // Get the buffered content as a string
            $image_data = ob_get_clean();

            $image_base64 = base64_encode($image_data);

            $frameImageResult = 'data:image/jpg;base64,' . $image_base64;



            // Free up memory
            // Clean up
            imagedestroy($energy);
            imagedestroy($resizedBattery);
            imagedestroy($resizedStar);
            imagedestroy($image);

            $mentionRes = ''; // Initialize $mentionRes as an empty string
            $uniqueMentions = [];

            foreach ($mentionList as $mention) {
                $username = $mention['username'];
                // Check if the username is not empty and is not already processed
                if (!empty($username) && !isset($uniqueMentions[$username])) {
                    $mentionRes .= "@" . $username . "%0A"; // Add "@" before the username and use "%0A" for newline
                    $uniqueMentions[$username] = true;
                }
            }


            // Remove the trailing newline and "@" symbol if it exists
            $mentionRes = rtrim($mentionRes, "%0A@");
        }

        $viewerId = 321969; // ID of the viewer

        $hasFollowed = $fidVerifier->hasFollowed($fid, $viewerId, "NEYNAR_API_DOCS");

        if (isset($hasFollowed) && $hasFollowed == false) {
            $frameImageResult = $currentFolder . '/assets/rare-stats-access.jpg?ts=' . $timestamp;
            echo '
        <html lang="en">
        <head>
        <meta property="fc:frame" content="vNext" />
        <meta name="viewport" content="width=device-width"/>
        <meta property="fc:frame:image" content="' . $frameImageResult . '"/>
        <meta property="fc:frame:image:aspect_ratio" content="1:1" />
        <meta property="fc:frame:post_url" content="' . $framePostUrl . '" />
        
        <meta property="fc:frame:button:1" content="Check Again">
        <meta property="fc:frame:button:1:action" content="post">
        <meta property="fc:frame:button:1:target" content="' . $framePostUrl . '">
        
        <meta name="fc:frame:button:2" content="Follow Channel">
        <meta name="fc:frame:button:2:action" content="link">
        <meta name="fc:frame:button:2:target" content="https://warpcast.com/~/channel/genyleap">

        <meta name="fc:frame:button:3" content="Follow Creator">
        <meta name="fc:frame:button:3:action" content="link">
        <meta name="fc:frame:button:3:target" content="https://warpcast.com/compez.eth">
          <title>Frame Access Error</title>
          </head>
        </html>';
        } else if (isset($data['errors'])) {
            echo '
        <html lang="en">
        <head>
        <meta property="fc:frame" content="vNext" />
        <meta name="viewport" content="width=device-width"/>
        <meta property="fc:frame:image" content="' . $frameImageResult . '"/>
        <meta property="fc:frame:image:aspect_ratio" content="1:1" />
        <meta property="fc:frame:post_url" content="' . $framePostUrl . '" />
        
        <meta property="fc:frame:button:1" content="My Stats">
        <meta property="fc:frame:button:1:action" content="post">
        <meta property="fc:frame:button:1:target" content="' . $framePostUrl . '">

        <meta property="fc:frame:input:text" content="Enter username or wallet address" />
        <meta property="fc:frame:button:2" content="🔎">
        <meta property="fc:frame:button:2:action" content="post">
        <meta property="fc:frame:button:2:target" content="' . $framePostUrl . '">
        <title>' . $config['header']['metaTitle'] . '</title>
          </head>
        </html>';
        } else if ($hasApiError || !$hasResult) {
            echo '
        <html lang="en">
        <head>
        <meta property="fc:frame" content="vNext" />
        <meta name="viewport" content="width=device-width"/>
        <meta property="fc:frame:image" content="' . $frameImageResult . '"/>
        <meta property="fc:frame:image:aspect_ratio" content="1:1" />
        <meta property="fc:frame:post_url" content="' . $framePostUrl . '" />
        
        <meta property="fc:frame:button:1" content="My Stats">
        <meta property="fc:frame:button:1:action" content="post">
        <meta property="fc:frame:button:1:target" content="' . $framePostUrl . '">

        <meta property="fc:frame:input:text" content="Enter username or wallet address" />
        <meta property="fc:frame:button:2" content="🔎">
        <meta property="fc:frame:button:2:action" content="post">
        <meta property="fc:frame:button:2:target" content="' . $framePostUrl . '">
            <title>' . $config['header']['metaTitle'] . '</title>
          </head>
        </html>';
        } else {
            echo '
        <html lang="en">
        <head>
        <meta property="fc:frame" content="vNext" />
        <meta name="viewport" content="width=device-width"/>
        <meta property="fc:frame:image" content="' . $frameImageResult . '"/>
        <meta property="fc:frame:image:aspect_ratio" content="1:1" />
        <meta property="fc:frame:post_url" content="' . $framePostUrl . '" />
        
        <meta property="fc:frame:button:1" content="My Stats">
        <meta property="fc:frame:button:1:action" content="post">
        <meta property="fc:frame:button:1:target" content="' . $framePostUrl . '">
        
        <meta property="fc:frame:input:text" content="Enter username or wallet address" />
        <meta property="fc:frame:button:2" content="🔎">
        <meta property="fc:frame:button:2:action" content="post">
        <meta property="fc:frame:button:2:target" content="' . $framePostUrl . '">
        
        <meta name="fc:frame:button:3" content="🔄 Share">
        <meta name="fc:frame:button:3:action" content="link">
        <meta name="fc:frame:button:3:target" content="https://warpcast.com/~/compose?text=Check your $RARE stats.  %0AFrame by @compez.eth%0A&embeds%5B%5D=' . $currentFolder . '/view/allowance?fid=' . $fid . '/' . $imageTimestamp . '">

          <title>' . $config['header']['metaTitle'] . '</title>
          </head>
        </html>';
        }
    } catch (Exception $e) {
        error_log($e);
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request']);
    }
}
