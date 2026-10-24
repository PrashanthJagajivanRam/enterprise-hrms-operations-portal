<?php
/**
 * REST API Endpoint: Attendance Logs
 * 
 * Consumed by native Android client and authorized services.
 * Features:
 * - API Key authentication
 * - Explicit HTTP status codes (200, 400, 401, 404, 500)
 * - Prepared SQL statements to mitigate SQL injection risks
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// API Key Verification (Configure via environment variable or secure config)
$valid_api_key = getenv('PORTAL_API_KEY') ?: 'DEMO_SECURE_API_KEY_CHANGE_ME';
$provided_key = $_GET['key'] ?? '';

if (empty($provided_key) || !hash_equals($valid_api_key, $provided_key)) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized access: invalid or missing API key"]);
    exit;
}

// Parameter Validation
if (empty($_GET['employee_number'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required parameter: employee_number"]);
    exit;
}

$employee_number = trim($_GET['employee_number']);

// Database Connection Bootstrap (Sanitized Mock/Config)
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'db_user';
$db_pass = getenv('DB_PASS') ?: 'db_password';
$db_name = getenv('DB_NAME') ?: 'hrms_database';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failure"]);
    exit;
}

// 1. Resolve employee profile securely using prepared statement
$user_stmt = $conn->prepare("SELECT id, email, full_name, designation FROM users WHERE employee_number = ? AND is_active = 1 LIMIT 1");
$user_stmt->bind_param("s", $employee_number);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Active employee profile not found"]);
    $user_stmt->close();
    $conn->close();
    exit;
}

$employee = $user_result->fetch_assoc();
$user_stmt->close();

// 2. Retrieve attendance logs for the resolved identity
$logs_stmt = $conn->prepare(
    "SELECT punch_time, punch_type, latitude, longitude, device_type, verification_mode 
     FROM attendance_logs 
     WHERE user_email = ? 
     ORDER BY punch_time DESC 
     LIMIT 50"
);
$logs_stmt->bind_param("s", $employee['email']);
$logs_stmt->execute();
$logs_result = $logs_stmt->get_result();
$attendance_logs = $logs_result->fetch_all(MYSQLI_ASSOC);
$logs_stmt->close();
$conn->close();

// 3. Return structured JSON payload
http_response_code(200);
echo json_encode([
    "status" => "success",
    "data" => [
        "employee_number" => $employee_number,
        "full_name"       => $employee['full_name'],
        "designation"     => $employee['designation'],
        "total_records"   => count($attendance_logs),
        "logs"            => $attendance_logs
    ]
], JSON_PRETTY_PRINT);
?>
