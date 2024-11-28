<?php

require_once('vendor/autoload.php');

class FidVerifier
{
    private $conn;
    private $blockedFids;

    // Database connection settings
    private $servername = "p:localhost";
    private $username = "";
    private $password = "";
    private $dbname = "fc";

    public function __construct()
    {
        // Create connection
        $this->conn = new mysqli($this->servername, $this->username, $this->password, $this->dbname);

        // Check connection
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }

        $this->loadBlockedFids();
    }

    private function loadBlockedFids()
    {
        $this->blockedFids = [];

        // Query to fetch blocked FIDs from the database
        $sql = "SELECT fid FROM banned_user WHERE status = 1";
        $result = $this->conn->query($sql);

        if ($result->num_rows > 0) {
            // Store all blocked FIDs in the array
            while ($row = $result->fetch_assoc()) {
                $this->blockedFids[] = $row['fid'];
            }
        }
    }

    public function checkBlockedFid($fid)
    {
        if (in_array($fid, $this->blockedFids)) {
            return true;
        }
        return false;
    }

    public function insertBlockedFid($fid, $walletAddresses = null)
    {
        $stmt = $this->conn->prepare("INSERT INTO banned_user (fid, wallet_addresses, status) VALUES (?, ?, 1)");
        $stmt->bind_param("is", $fid, $walletAddresses);

        if ($stmt->execute()) {
            echo "New record created successfully";
            // Update the local blockedFids array
            $this->blockedFids[] = $fid;
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    }

    public function enableBan($fid)
    {
        $stmt = $this->conn->prepare("UPDATE banned_user SET status = 1 WHERE fid = ?");
        $stmt->bind_param("i", $fid);

        if ($stmt->execute()) {
            echo "Ban enabled successfully";
            // Update the local blockedFids array
            if (!in_array($fid, $this->blockedFids)) {
                $this->blockedFids[] = $fid;
            }
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    }

    public function disableBan($fid)
    {
        $stmt = $this->conn->prepare("UPDATE banned_user SET status = 0 WHERE fid = ?");
        $stmt->bind_param("i", $fid);

        if ($stmt->execute()) {
            echo "Ban disabled successfully";
            // Update the local blockedFids array
            $key = array_search($fid, $this->blockedFids);
            if ($key !== false) {
                unset($this->blockedFids[$key]);
            }
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    }

    // Close the database connection when the object is destroyed
    public function __destruct()
    {
        $this->conn->close();
    }

    public function hasFollowed($userId, $viewerId, $apiKey)
    {

        if ($userId === $viewerId) {
            return true;
        }

        // Cache lifetime and update interval
        $cacheLifetime = 3600; // 1 hour
        $updateInterval = 86400; // 24 hour

        $servername = "p:localhost";
        $username = "";
        $password = "";
        $dbname = "fc";

        // Create connection
        $conn = new mysqli($servername, $username, $password, $dbname);

        // Check connection
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        $data = null;
        $cacheValid = 0;

        $cacheKey = md5("follow_" . $userId . "_" . $viewerId);

        // Fetch cached data from the database
        $stmt = $conn->prepare('SELECT * FROM user_cache WHERE user_key = ?');
        $stmt->bind_param('s', $cacheKey);
        $stmt->execute();
        $result = $stmt->get_result();
        $cachedData = $result->fetch_assoc();

        // Check if cache file exists and is still valid
        if ($cachedData) {
            $data = json_decode($cachedData['json_data'], true);
            // Ensure the data is valid

            if (is_array($data) && isset($data['users'][0]['viewer_context']['followed_by']) && isset($data['last_updated'])) {
                // Check the cache validity based on the file modification time
                $cacheModTime = filemtime($data['rate_limit']['last_request_time']);
                if ((time() - $cacheModTime) < $cacheLifetime) {
                    $cacheValid = 1;
                } else {
                    $cacheValid = 0;
                }

                // Check if the cache needs to be refreshed based on the update interval
                if ((time() - $data['last_updated']) >= $updateInterval) {
                    // Initiate async request to refresh cache
                    $asyncScript = __DIR__ . '/update_cache.php';
                    $command = "php $asyncScript $userId $viewerId $apiKey > /dev/null 2>&1 &";
                    exec($command);
                }
            }
        } else {

            $asyncScript = __DIR__ . '/update_cache.php';
            $command = "php $asyncScript $userId $viewerId $apiKey > /dev/null 2>&1 &";
            exec($command);
            return false;
        }

        // If cache is valid and followed_by is true, return true
        if ($cacheValid && $data['users'][0]['viewer_context']['followed_by'] === true) {
            return true;
        }

        // If the cache is not valid or followed_by is false, initiate an async request to refresh the cache
        if (!$cacheValid && ($data['users'][0]['viewer_context']['followed_by'] === false)) {
            $asyncScript = __DIR__ . '/update_cache.php';
            $command = "php $asyncScript $userId $viewerId $apiKey > /dev/null 2>&1 &";
            exec($command);
        }

        // Return false if cache is not valid or followed_by is false
        return $cacheValid == false && $data['users'][0]['viewer_context']['followed_by'] === false ? false : true;
    }
}

class FramesValidator
{
    private $apiKey;
    private $apiUrl;

    public function __construct($apiKey)
    {
        $this->apiKey = trim($apiKey);
        $this->apiUrl = 'https://hubs.airstack.xyz/v1/validateMessage';
    }

    private function hexStringToBinary($hexstring)
    {
        // Convert the hex string to raw binary data
        return hex2bin($hexstring);
    }

    public function validateMessage($messageBytesHex)
    {
        // Convert the hexadecimal string to binary data
        $messageBytes = $this->hexStringToBinary($messageBytesHex);

        // Initialize cURL
        $ch = curl_init($this->apiUrl);
        $headers = [
            'Content-Type: application/octet-stream',
            'x-airstack-hubs: ' . $this->apiKey
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $messageBytes);  // Send raw binary data
        curl_setopt($ch, CURLOPT_HEADER, true);  // Include headers in the output

        // Execute the request
        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['error' => 'cURL Error: ' . $error];
        }

        // Get the HTTP response code and headers
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseHeaderSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responseHeaders = substr($response, 0, $responseHeaderSize);
        $responseBody = substr($response, $responseHeaderSize);

        curl_close($ch);

        // Decode the response body
        $responseData = json_decode($responseBody, true);

        // Return the structured response
        return [
            'HTTP_Status_Code' => $httpcode,
            'Response_Headers' => $responseHeaders,
            'Response_Body' => $responseData
        ];
    }
}
