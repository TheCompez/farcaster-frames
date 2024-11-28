<?php

require 'vendor/autoload.php';
require "core.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set up error logging
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error_log_file.log');

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

$client = new \GuzzleHttp\Client();

function setLog($data)
{
    $file_path = 'logs/dbtest.txt';

    // Ensure the directory exists
    $directory = dirname($file_path);
    if (!file_exists($directory)) {
        if (!mkdir($directory, 0777, true)) {
            error_log("Failed to create directory: $directory");
            die("Error creating directory.");
        }
    }
    
    // Force the creation of the file if it does not exist
    if (!file_exists($file_path)) {
        $file = fopen($file_path, 'w');
        if ($file === false) {
            error_log("Failed to create file: $file_path");
            die("Error creating file.");
        }
        fclose($file);
    
        // Set file permissions (optional but recommended)
        if (!chmod($file_path, 0666)) {
            error_log("Failed to set permissions for file: $file_path");
            die("Error setting file permissions.");
        }
    }
    
    // Open the file in append mode
    $file = fopen($file_path, 'a');
    
    // Check if file was opened successfully
    if ($file === false) {
        error_log("Failed to open file: $file_path");
        die("Error opening file.");
    }
    
    // Write to the file
    if (fwrite($file, $data . PHP_EOL) === false) {
        error_log("Failed to write to file: $file_path");
    }
    
    // Close the file
    fclose($file);
}

$servername = "p:localhost";
$username = "fc";
$password = "qH+uXlbE#atS";
$dbname = "fc";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function updateCache($userId, $viewerId, $apiKey, $conn) {
    // Define cache key
    $cacheKey = md5("follow_" . $userId . "_" . $viewerId);
    
    // Rate limit settings
    $rateLimitWindow = 60; // in seconds
    $rateLimitMaxRequests = 15;

    // Fetch cached data from the database
    $stmt = $conn->prepare('SELECT * FROM user_cache WHERE user_key = ?');
    $stmt->bind_param('s', $cacheKey);
    $stmt->execute();
    $result = $stmt->get_result();
    $cachedData = $result->fetch_assoc();

    if ($cachedData) {
        $data = json_decode($cachedData['json_data'], true);
        $lastRequestTime = $data['rate_limit']['last_request_time'] ?? 0;
        $requestCount = $data['rate_limit']['request_count'] ?? 0;

        if (time() - $lastRequestTime < $rateLimitWindow) {
            if ($requestCount >= $rateLimitMaxRequests) {
                return; // Skip update if rate limit is exceeded
            }
        } else {
            $requestCount = 0;
        }
    } else {
        $lastRequestTime = 0;
        $requestCount = 0;
    }

    $url = "https://api.neynar.com/v2/farcaster/user/bulk?fids=$userId&viewer_fid=$viewerId";

    $client = new Client();

    try {
        $response = $client->request('GET', $url, [
            'headers' => [
                'Accept' => 'application/json',
                'api_key' => $apiKey,
                'User-Agent' => 'Genyleap LLC/1.0'
            ]
        ]);

        $data = json_decode($response->getBody(), true);

        // $file_path = 'logs/data.txt';

        // // Open file in append mode
        // $file = fopen($file_path, 'a');
        
        // fwrite($file, $data. PHP_EOL);
        
        // Close the file
        // fclose($file);

        if (isset($data['users'][0]['viewer_context']['followed_by'])) {
            $data['last_updated'] = time();
            $data['rate_limit'] = [
                'last_request_time' => time(),
                'request_count' => $requestCount + 1
            ];
            $jsonData = json_encode($data);

            if ($cachedData) {
                $stmt = $conn->prepare('UPDATE user_cache SET json_data = ? WHERE user_key = ?');
                $stmt->bind_param('ss', $jsonData, $cacheKey);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare('INSERT INTO user_cache (user_key, json_data) VALUES (?, ?)');
                $stmt->bind_param('ss', $cacheKey, $jsonData);
                $stmt->execute();
            }
        } else {
            // Handle invalid response but do not delete cache
            // Optionally log or handle error
            error_log("Invalid response for userKey: $cacheKey");
        }
    } catch (Exception $e) {
        // Handle the exception (e.g., log the error)
        error_log("Error fetching data from API: " . $e->getMessage());
    }
}

// Get parameters from the query string
$userId = $argv[1];
$viewerId = $argv[2];
$apiKey = $argv[3];

if ($userId && $viewerId && $apiKey) {
    updateCache($userId, $viewerId, $apiKey, $conn);
} else {
    echo "Missing parameters.";
}

?>
