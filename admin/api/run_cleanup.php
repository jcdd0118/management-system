<?php
// Log errors instead of displaying them (avoid HTML in JSON response)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
if (!ini_get('error_log')) {
    ini_set('error_log', __DIR__ . '/cleanup_error.log');
}
session_start();
header('Content-Type: application/json');

// Global output buffering + shutdown to convert fatals/die/echo to JSON
ob_start();
register_shutdown_function(function () {
    $lastError = error_get_last();
    $buffer = ob_get_contents();
    if ($lastError && in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $lastError['message']]);
    } elseif (!headers_sent()) {
        $trim = trim($buffer);
        if ($trim !== '' && !(strlen($trim) > 0 && ($trim[0] === '{' || $trim[0] === '['))) {
            http_response_code(500);
            header('Content-Type: application/json');
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => strip_tags($trim)]);
        } else {
            ob_end_flush();
        }
    } else {
        ob_end_flush();
    }
});
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';

$password = isset($_POST['password']) ? $_POST['password'] : '';

// Verify admin password (avoid get_result for hosts without mysqlnd)
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ? AND role = 'admin'");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($hashedPassword);
$hasRow = $stmt->fetch();
$stmt->close();
if (!$hasRow || !password_verify($password, $hashedPassword)) {
    echo json_encode(['success' => false, 'message' => 'Invalid password']);
    exit;
}

// Run the cleanup script with error capture
$ok = true;
$error = '';
$output = '';
// Create nested buffer for the cleanup script itself
$localLevel = ob_get_level();
try {
    // Enable mysqli exceptions to catch DB errors
    if (function_exists('mysqli_report')) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }
    // Prepare trace file path
    if (!defined('CLEANUP_TRACE_FILE')) {
        define('CLEANUP_TRACE_FILE', __DIR__ . '/cleanup_trace.log');
    }
    @file_put_contents(CLEANUP_TRACE_FILE, date('c') . " run_cleanup: starting include\n", FILE_APPEND);
    ob_start();
    require '../../cron/cleanup_graduated_accounts.php';
    $output = ob_get_clean();
    @file_put_contents(CLEANUP_TRACE_FILE, date('c') . " run_cleanup: finished include\n", FILE_APPEND);
} catch (Throwable $t) {
    while (ob_get_level() > $localLevel) { ob_end_clean(); }
    $ok = false;
    $error = $t->getMessage();
    error_log('run_cleanup: ' . $t->getMessage());
    @file_put_contents(CLEANUP_TRACE_FILE, date('c') . " run_cleanup: exception: " . $t->getMessage() . "\n", FILE_APPEND);
}

if ($ok) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => trim($output)]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Cleanup failed', 'error' => $error]);
}
?>

