<?php

require '../../lib/vendor/autoload.php';
require "../../lib/core.php";

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Exception\ClientException;

$dailyAllowance = null;
$remainingAllowance = null;

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set up error logging
ini_set('log_errors', 1);
ini_set('error_log', '../logs/action_error_log_file.log');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    $client = new \GuzzleHttp\Client();

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
            $promise = $this->make_graphql_request('https://api.airstack.xyz/graphql', $query, $headers);

            return $promise->then(
                function ($response) {
                    if (isset($response['errors'])) {
                        throw new Exception('GraphQL query failed: ' . json_encode($response['errors']));
                    }

                    // Extract socialCapitalRank value
                    if (isset($response['data']['Socials']['Social'][0]['socialCapital']['socialCapitalRank'])) {
                        return $response['data']['Socials']['Social'][0]['socialCapital']['socialCapitalRank'];
                    } else {
                        throw new Exception('SocialCapitalRank not found in the response.');
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
                    'limit' => 10,
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

            $engagementUrl = 'https://graph.cast.k3l.io/scores/global/engagement/fids';
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


    // Function to validate URL
    function validateURL($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && parse_url($url, PHP_URL_SCHEME) === 'https';
    }

    // Function to parse query parameters
    function parseQueryParameters($url)
    {
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $params);
        return $params;
    }

    function getContent($hash)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', 'https://build.far.quest/farcaster/v2/cast?hash=' . $hash, [
            'headers' => [
                'API-KEY' => 'Q182P-FZUCK-NG5KV-M92GC-F0LO1',
                'accept' => 'application/json',
                'User-Agent' => 'SuperRare Verify tip action/1.0 (Genyleap; comepz.eth)'
            ],
        ]);

        // Decode the JSON response
        $data = json_decode($response->getBody(), true);

        // Check if the required fields exist
        if (isset($data['result']['cast']['text'])) {
            // Extract the "text" field
            $text = $data['result']['cast']['text'];

            // Validate the "text" field
            if (preg_match('/(\d+(\.\d+)?) \$(rare|RARE)/i', $text)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
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

    // Get raw JSON data from PHP input stream
    $jsonData = file_get_contents('php://input');

    // Decode JSON data
    $data = json_decode($jsonData, true);

    $statValue = null;
    $walletAddress = null;

    if (isset($data['errors'])) {
        $statValue = "Internal Rare Protocol Error!";
    } else {
        // Check if 'untrustedData' and 'castId' are set
        if (isset($data['untrustedData']['castId']['fid'])) {
            $fromFid = $data['untrustedData']['fid'];
            $fid = (string) $data['untrustedData']['castId']['fid'];
            $hash = (string) $data['untrustedData']['castId']['hash'];


            $trustedDataMessageBytes = $data['trustedData']['messageBytes'] ?? null;

            $apiKey = '1a6ac181d02964c2794102094a8a1d1b9';
            $validator = new FramesValidator($apiKey);

            $validator->validateMessage($trustedDataMessageBytes);

            // Request user data using the fid
            try {
                $response = $client->request('GET', 'https://build.far.quest/farcaster/v2/user?fid=' . $fid, [
                    'headers' => [
                        'API-KEY' => 'Q182P-FZUCK-NG5KV-M92GC-F0LO1',
                        'accept' => 'application/json',
                    ],
                ]);

                $userData = json_decode($response->getBody(), true);

                $responseUser = $client->request('GET', 'https://build.far.quest/farcaster/v2/user?fid=' . $fromFid, [
                    'headers' => [
                        'API-KEY' => 'Q182P-FZUCK-NG5KV-M92GC-F0LO1',
                        'accept' => 'application/json',
                    ],
                ]);

                $userDataUser = json_decode($responseUser->getBody(), true);


                $fName = null;

                if ($userData !== null && isset($userData['result']['user'])) {
                    $walletAddress = $userData['result']['user']['allConnectedAddresses']['ethereum'];

                    $servername = "p:localhost";
                    $username = "superrare";
                    $password = "qH+uXlbE#atS";
                    $dbname = "superrare";

                    $conn = new mysqli($servername, $username, $password, $dbname);

                    if ($conn->connect_error) {
                        die("Connection failed: " . $conn->connect_error);
                    }


                    $timestamp = time();

                    $sql = "INSERT INTO user_action (fid, username, wallet_address, count, last_timestamp)
                VALUES (?, ?, ?, 1, ?)
                ON DUPLICATE KEY UPDATE count = count + 1, last_timestamp = VALUES(last_timestamp)";
                    $stmt = $conn->prepare($sql);


                    $fidU = null;
                    $fidUsername = null;
                    $address = $fidU;

                    //  if ($userDataUser !== null && isset($userDataUser['result']['user'])) {
                    //      $fidU = $userDataUser['result']['user']['fid'];
                    //      $fidUsername = $userDataUser['result']['user']['username'];
                    //     $address = $userDataUser['result']['user']['allConnectedAddresses']['ethereum'];

                    //     if (is_array($address) && !empty($address)) {
                    //         $address = $address[0];
                    //     } else {
                    //         $address = $address;
                    //     }

                    //  }

                    if ($userData !== null && isset($userData['result']['user'])) {
                        $walletAddresses = [];

                        $fidU = $userData['result']['user']['fid'];
                        $fidUsername = $userData['result']['user']['username'];
                        $address = $userData['result']['user']['allConnectedAddresses']['ethereum'];

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


                    $fName = $fidU;
                    $stmt->bind_param("issi", $fidU, $fidUsername, $address, $timestamp);
                    $stmt->execute();
                    $stmt->close();
                    $conn->close();

                    $statsFetcher = new StatsFetcher();
                    $graphqlData = json_decode($statsFetcher->executeRequest($fid, $walletAddress), true);
                    $dailyAllowance = 0.0;
                    $remainingAllowance = 0.0;
                    foreach ($graphqlData['rare']['data']['rareTipUsers'] as $user) {
                        // $points = $user['points'];
                        // $dailyAmountReceived = $user['dailyAmountReceived'];
                        // $totalReceived = $user['totalAmountReceived'];
                        // $dailyTipCount = $user['dailyTipCount'];
                        // $totalTipCount = $user['totalTipCount'];
                        $dailyAllowance = $user['dailyAllowance'] ? $user['dailyAllowance'] : 0.0;
                        // $totalAmountTipped = $user['totalAmountTipped'] ? $user['totalAmountTipped'] : 0.0;
                        $remainingAllowance = $user['allowanceRemaining'] ? $user['allowanceRemaining'] : 0.0;
                        // $allowanceRemainingFull = $user['allowanceRemaining'];
                    }

                    // $rareTipUsers = $graphqlData['rare']['data']['rareTipUsers'];
                    // $dailyAllowance = number_format((float)$rareTipUsers[0]['dailyAllowance'], 3, '.', ',') ?? null;
                    // $remainingAllowance = number_format((float)$rareTipUsers[0]['allowanceRemaining'], 3, '.', ',') ?? null;

                    if (isset($graphqlData['rare']['data']['rareTipUsers'])) {
                        $hasResult = true;
                        $parValidOne = getContent($hash);
                        foreach ($graphqlData['rare']['data']['rareTipUsers'] as $user) {
                            if ($user['isEligible']) {
                                $parValidTwo = 1;
                            } else {
                                $parValidTwo = 0;
                            }
                        }

                        if ($parValidOne == true && $parValidTwo == 1) {
                            $statValue = "✅ Valid! Allowance/Remaining [" . $dailyAllowance . "/" . $remainingAllowance . "]";
                        } else {
                            $statValue = "🚫 Invalid! Allowance/Remaining [$dailyAllowance/$remainingAllowance]";
                        }
                    }
                } else {
                    $statValue = "Failed to parse user data or connected address not found";
                }
            } catch (ClientException $e) {
                // Handle exceptions
                if ($e->getResponse()->getStatusCode() === 404) {
                    $statValue = "No data found for this user!";
                } else {
                    // Handle other errors
                    $statValue = "Failed to fetch allowance information: " . $e->getMessage();
                }
            }
        } else {
            $statValue = "Failed to parse JSON data or castId not found";
        }
    }

    // Load URL
    $url = "https://warpcast.com/~/add-cast-action?icon=check&name=Verify \$RARE 💎&description=Check if given rare tip is valid&actionType=post&postUrl=https://superrare.genyframe.xyz/action/valid";

    // Parse query parameters
    $queryParams = parseQueryParameters($url);

    // Validate and retrieve parameters
    if (isset($queryParams['postUrl']) && validateURL($queryParams['postUrl'])) {

        $name = $queryParams['name'];
        $icon = $queryParams['icon'];
        $actionType = $queryParams['actionType'];
        $postUrl = $queryParams['postUrl'];

        // Create JSON output
        $output = array(
            'name' => $name,
            'description' => "Check if given rare tip is valid.",
            'icon' => $icon,
            'actionType' => $actionType,
            'postUrl' => $postUrl,
            'message' => (string)$statValue
        );

        echo json_encode($output);
    } else {
        // Invalid parameters
        $error = array(
            'error' => 'Invalid parameters',
            'message' => 'Ensure all parameters meet the required criteria.'
        );
        echo json_encode($error);
    }
} else {
    // Create an associative array with the desired structure
    $data = array(
        "name" => "Verify \$RARE 💎",
        "icon" => "check",
        "description" => "Check if given rare tip is valid.",
        "aboutUrl" => "https://warpcast.com/compez.eth",
        "action" => array(
            "type" => "post"
        )
    );

    // Encode the array into a JSON string
    $jsonOutput = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Print the JSON output
    header('Content-Type: application/json');
    echo $jsonOutput;
}
