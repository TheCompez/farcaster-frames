<?php

header('Content-Type: application/json');

// Define the path to the JSON file
$jsonFilePath = '../../blocked_users.json';

// API Key for authentication (example key)
$apiKey = "cd7216b44ce34ac3591e5fc5b6fe143277d1efba"; // SHA1 Generated Hash

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw POST data
    $postData = file_get_contents("php://input");
    $requestData = json_decode($postData, true);

    // Check if the API key is provided and valid
    $providedApiKey = isset($requestData['apiKey']) ? $requestData['apiKey'] : '';

    if ($providedApiKey !== $apiKey) {
        // Unauthorized access if API key is incorrect
        http_response_code(401);
        echo json_encode(array("error" => "Unauthorized"));
        exit;
    }

    // Check if the JSON file exists
    if (!file_exists($jsonFilePath)) {
        // File not found error
        http_response_code(500);
        echo json_encode(array("error" => "Internal Server Error", "message" => "JSON file not found"));
        exit;
    }

    // Read the JSON file
    $jsonContent = file_get_contents($jsonFilePath);
    if ($jsonContent === false) {
        // Error reading the file
        http_response_code(500);
        echo json_encode(array("error" => "Internal Server Error", "message" => "Error reading JSON file"));
        exit;
    }

    // Decode the JSON content
    $data = json_decode($jsonContent, true);
    if ($data === null) {
        // Error decoding the JSON
        http_response_code(500);
        echo json_encode(array("error" => "Internal Server Error", "message" => "Error decoding JSON file"));
        exit;
    }

    // Prepare the response
    $response = array(
        "users" => isset($data['users']) ? $data['users'] : array()
    );
    echo json_encode($response);
} else {
    // Handle other HTTP methods
    http_response_code(405); // Method Not Allowed
    echo json_encode(array("error" => "Method Not Allowed"));
}
?>
