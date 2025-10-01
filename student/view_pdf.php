<?php
session_start();
ob_start();
require_once '../config/database.php';

// Check database connection
if (!$conn) {
    error_log('Database connection failed at ' . date('Y-m-d H:i:s') . ': ' . mysqli_connect_error());
    ob_end_clean();
    die('Database connection failed. Please try again later.');
}

// Get the file path from the URL
$file = isset($_GET['file']) ? trim($_GET['file']) : '';
if (empty($file)) {
    error_log('No file specified in URL at ' . date('Y-m-d H:i:s'));
    ob_end_clean();
    die('No file specified.');
}

// Sanitize the file name to prevent directory traversal
$file = basename($file);

// Support both legacy and current storage locations
$candidate_paths = array(
    '../assets/uploads/capstone/' . $file,
    '../uploads/' . $file
);

// Pick the first existing candidate
$file_path = null;
foreach ($candidate_paths as $candidate) {
    if (file_exists($candidate)) {
        $file_path = $candidate;
        break;
    }
}

// Log the file path being checked
error_log('Checking file: ' . $file_path);

// Check if file exists
if (!$file_path || !file_exists($file_path)) {
    error_log('File not found: ' . $file_path . ' at ' . date('Y-m-d H:i:s'));
    ob_end_clean();
    die('File not found.');
}

// Validate file path to prevent directory traversal (allowing known upload roots only)
$real_file_path = realpath($file_path);
$allowed_roots = array(
    realpath('../assets/uploads/capstone'),
    realpath('../uploads')
);
error_log('Real file path: ' . ($real_file_path ? $real_file_path : 'false'));
error_log('Allowed roots: ' . implode(' | ', array_map(function($p){ return $p ? $p : 'false'; }, $allowed_roots)));

$is_allowed = false;
if ($real_file_path !== false) {
    foreach ($allowed_roots as $root) {
        if ($root && strpos($real_file_path, $root) === 0) {
            $is_allowed = true;
            break;
        }
    }
}

if (!$is_allowed) {
    error_log('Invalid file path attempted: ' . $file_path . ' at ' . date('Y-m-d H:i:s'));
    ob_end_clean();
    die('Invalid file path.');
}

// Validate file against capstone table
$sql = 'SELECT id, document_path, status FROM capstone WHERE document_path = ? OR document_path LIKE ? AND status = \'verified\'';
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log('Prepare failed at ' . date('Y-m-d H:i:s') . ': ' . $conn->error);
    ob_end_clean();
    die('An error occurred while validating the file. Please try again.');
}

$like_file = '%/' . $file; // Match paths ending with the file name
$stmt->bind_param('ss', $file, $like_file);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
if ($result->num_rows === 0) {
    error_log('File not verified or not found in database: ' . $file . ' at ' . date('Y-m-d H:i:s'));
    if ($row) {
        error_log('Found but status is: ' . $row['status']);
    }
    ob_end_clean();
    die('File not found or not verified.');
}
$stmt->close();

// Set headers to display PDF in browser
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $file . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Log successful access
error_log('Serving file: ' . $file_path . ' at ' . date('Y-m-d H:i:s'));

// Clear output buffer and output the file
ob_end_clean();
readfile($file_path);
exit;
?>