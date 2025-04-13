#!/usr/bin/env php
<?php
/**
 * MySQL to Kintone Data Migration Tool
 * 
 * This script synchronizes data between MySQL database and Kintone.
 * It supports both creating new records and updating existing ones
 * based on unique identifiers.
 */

loadEnv(__DIR__ . '/.env');
error_reporting(E_ALL);
ini_set('display_errors', '1');

/**
 * Load environment variables from .env file
 * 
 * @param string $path Path to .env file
 * @throws Exception if .env file is not found
 */
function loadEnv($path)
{
    if (!file_exists($path)) {
        throw new Exception(".env file not found: $path");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Ignore comment lines
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!empty($name)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

// Write message to console only
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $formatted_message = "[$timestamp] $message";
    echo $formatted_message . "\n";
}

// Database connection parameters
$db_config = [
    'host' => $_ENV['MYSQL_SERVERNAME'],
    'username' => $_ENV['MYSQL_USERNAME'],
    'password' => $_ENV['MYSQL_PASSWORD'],
    'database' => $_ENV['MYSQL_DBNAME']
];

// Kintone configuration
$kintone_domain = $_ENV['KINTONE_DOMAIN'];

// Establish database connection
$conn = new mysqli(
    $db_config['host'],
    $db_config['username'],
    $db_config['password'],
    $db_config['database']
);

if ($conn->connect_error) {
    logMessage("Database connection failed: " . $conn->connect_error);
    exit(1);
}

/**
 * Validate date format (YYYY-MM-DD)
 * 
 * @param string $date Date string to validate
 * @return bool True if valid, false otherwise
 */
function validateDateFormat($date)
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Normalize Yes/No values to consistent format
 * 
 * @param string $value Input value to normalize
 * @return string|null Normalized value ('Yes', 'No', or null)
 */
function normalizeYesNo($value)
{
    $value = strtoupper(trim($value));
    return ($value === 'YES') ? 'Yes' : (($value === 'NO') ? 'No' : null);
}

/**
 * Send request to Kintone API
 * 
 * @param string $url API endpoint URL
 * @param string $method HTTP method (GET, POST, PUT)
 * @param string $api_token Kintone API token
 * @param array|null $data Request data (for POST/PUT)
 * @return array Array containing [HTTP status code, Response data]
 */
function sendKintoneRequest($url, $method, $api_token, $data = null)
{
    $headers = [
        "X-Cybozu-API-Token: $api_token"
    ];

    // Add Content-Type only for POST and PUT requests
    if (in_array($method, ['POST', 'PUT'])) {
        $headers[] = "Content-Type: application/json";
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$http_code, json_decode($response, true)];
}

// Function to build record data with field mapping
function buildRecordData($row, $mysql_fields, $kintone_fields)
{
    $record_data = [];

    foreach ($mysql_fields as $index => $mysql_field) {
        $kintone_field = $kintone_fields[$index];

        if ($mysql_field === "LiveDate") {
            $record_data[$kintone_field] = ["value" => validateDateFormat($row[$mysql_field]) ? $row[$mysql_field] : null];
        } elseif ($mysql_field === "IsClec") {
            $record_data[$kintone_field] = ["value" => normalizeYesNo($row[$mysql_field])];
        } else {
            $record_data[$kintone_field] = ["value" => $row[$mysql_field]];
        }
    }

    return $record_data;
}

// Main function to process tables
function processTable($mysql_table, $mysql_fields, $mysql_query, $kintone_fields, $app_id, $api_token)
{
    global $conn, $kintone_domain;

    // Retrieve data from MySQL
    $sql = $mysql_query;
    logMessage("$sql");

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $id = $row['Id'];
            $record_data = buildRecordData($row, $mysql_fields, $kintone_fields);

            // Check if Id exists in Kintone
            $kintone_check_url = "https://$kintone_domain/k/v1/records.json?app=$app_id&query=Id%20%3D%20$id";
            list($http_code, $response_data) = sendKintoneRequest($kintone_check_url, 'GET', $api_token);

            if ($http_code !== 200) {
                logMessage("Error in checking request for table $mysql_table (HTTP code: $http_code): " . $response_data['message']);
                continue;
            }

            // Prepare for registration or update
            if (empty($response_data['records'])) {
                $data = [
                    "app" => $app_id,
                    "record" => $record_data
                ];
                $kintone_post_url = "https://$kintone_domain/k/v1/record.json";
                $method = 'POST';
            } else {
                unset($record_data['Id']); // Remove Id for updates
                $data = [
                    "app" => $app_id,
                    "updateKey" => [
                        "field" => "Id",
                        "value" => $id
                    ],
                    "record" => $record_data
                ];
                $kintone_post_url = "https://$kintone_domain/k/v1/record.json";
                $method = 'PUT';
            }

            // Log combined data message
            $status_message = (empty($response_data['records'])) ? "Registering" : "Updating";

            // Send data to Kintone for registration or update
            list($http_code, $response_data) = sendKintoneRequest($kintone_post_url, $method, $api_token, $data);

            if ($http_code === 200) {
                logMessage("Success for table $mysql_table: Id=$id");
            } else {
                logMessage("Error for table $mysql_table (HTTP code: $http_code): " . $response_data['message'] . " Id=$id");
            }
        }
    } else {
        logMessage("No matching records found in MySQL for table $mysql_table.");
    }
}

// Process each table
$apps = explode(',', $_ENV['APPS']);

foreach ($apps as $app) {
    $mysql_table = $_ENV[$app . '_MYSQL_TABLE'];
    $mysql_fields = explode(',', $_ENV[$app . '_MYSQL_FIELDS']);
    $mysql_query = $_ENV[$app . '_MYSQL_QUERY'];
    $kintone_fields = explode(',', $_ENV[$app . '_KINTONE_FIELDS']);
    $app_id = $_ENV[$app . '_KINTONE_APP_ID'];
    $api_token = $_ENV[$app . '_KINTONE_API_TOKEN'];

    processTable($mysql_table, $mysql_fields, $mysql_query, $kintone_fields, $app_id, $api_token);
}

// Close MySQL connection
$conn->close();

?>
