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

// Ensure the file has a .pdf extension
if (strtolower(substr($file, -4)) !== '.pdf') {
    $file .= '.pdf';
}

// Construct the file path
$file_path = '../assets/uploads/capstone/' . $file;

// Log the file path and existence check
error_log('Requested file: ' . $file . ' at ' . date('Y-m-d H:i:s'));
error_log('Checking file path: ' . $file_path);

// Check if file exists
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path . ' at ' . date('Y-m-d H:i:s'));
    // Try without .pdf extension
    $file_no_ext = str_replace('.pdf', '', $file);
    $file_path_no_ext = '../assets/uploads/capstone/' . $file_no_ext;
    error_log('Trying without .pdf: ' . $file_path_no_ext);
    if (!file_exists($file_path_no_ext)) {
        error_log('File without .pdf not found: ' . $file_path_no_ext);
        ob_end_clean();
        die('File not found.');
    } else {
        $file_path = $file_path_no_ext;
        $file = $file_no_ext . '.pdf'; // Ensure download uses .pdf
    }
}

// Validate file path to prevent directory traversal
$real_file_path = realpath($file_path);
$real_upload_dir = realpath('../assets/uploads/capstone');
error_log('Real file path: ' . ($real_file_path ? $real_file_path : 'false'));
error_log('Real upload dir: ' . ($real_upload_dir ? $real_upload_dir : 'false'));
if ($real_file_path === false || strpos($real_file_path, $real_upload_dir) !== 0) {
    error_log('Invalid file path attempted: ' . $file_path . ' at ' . date('Y-m-d H:i:s'));
    ob_end_clean();
    die('Invalid file path.');
}

// Basic PDF content check (first 4 bytes should be %PDF)
$handle = @fopen($file_path, 'rb');
if ($handle !== false) {
    $header = fread($handle, 4);
    fclose($handle);
    if ($header !== '%PDF') {
        error_log('File is not a valid PDF: ' . $file_path . ' at ' . date('Y-m-d H:i:s'));
        ob_end_clean();
        die('File is not a valid PDF.');
    }
} else {
    error_log('Failed to read file: ' . $file_path . ' at ' . date('Y-m-d H:i:s'));
    ob_end_clean();
    die('File cannot be read.');
}

// Validate file against capstone table
$sql = 'SELECT id, document_path, status FROM capstone WHERE document_path = ? OR document_path = ? OR document_path LIKE ? OR document_path LIKE ? AND status = \'verified\'';
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log('Prepare failed at ' . date('Y-m-d H:i:s') . ': ' . $conn->error);
    ob_end_clean();
    die('An error occurred while validating the file. Please try again.');
}

// Prepare parameters for database query
$file_no_ext = str_replace('.pdf', '', $file);
$like_file = '%/' . $file;
$like_file_no_ext = '%/' . $file_no_ext;
error_log('Database query checking: ' . $file . ', ' . $file_no_ext . ', ' . $like_file . ', ' . $like_file_no_ext);
$stmt->bind_param('ssss', $file, $file_no_ext, $like_file, $like_file_no_ext);
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

// Set headers to force download with .pdf extension
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Log successful download
error_log('Downloading file: ' . $file_path . ' at ' . date('Y-m-d H:i:s'));

// Clear output buffer and output the file
ob_end_clean();
readfile($file_path);
exit;
?>