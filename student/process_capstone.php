<?php
session_start();

// Check if the user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../users/login.php");
    exit();
}

include '../config/database.php';

// Initialize session for old input
$_SESSION['old_input'] = $_POST;

// Validate form inputs
if (empty($_POST['title']) || empty($_POST['year']) || empty($_POST['abstract']) || empty($_POST['keywords']) || empty($_FILES['document']['name'])) {
    $_SESSION['error_message'] = "All fields are required.";
    header("Location: submit_manuscript.php");
    exit();
}

// Validate year
$year = (int)$_POST['year'];
$current_year = (int)date('Y');
if ($year < 1900 || $year > $current_year) {
    $_SESSION['error_message'] = "Year must be between 1900 and $current_year.";
    header("Location: submit_manuscript.php");
    exit();
}

// Validate and handle file upload
$upload_dir = "../assets/uploads/capstone/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$file = $_FILES['document'];
$file_name = basename($file['name']);
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
$file_size = $file['size'];
$max_size = 50 * 1024 * 1024; // 50MB

if ($file_ext !== 'pdf') {
    $_SESSION['error_message'] = "Only PDF files are allowed.";
    header("Location: submit_manuscript.php");
    exit();
}

if ($file_size > $max_size) {
    $_SESSION['error_message'] = "File size exceeds 50MB limit.";
    header("Location: submit_manuscript.php");
    exit();
}

$unique_file_name = time() . '_' . preg_replace("/[^A-Za-z0-9]/", "_", $file_name);
$document_path = $upload_dir . $unique_file_name;

if (!move_uploaded_file($file['tmp_name'], $document_path)) {
    $_SESSION['error_message'] = "Failed to upload file.";
    header("Location: submit_manuscript.php");
    exit();
}

// Parse authors from JSON
$authors_json = isset($_POST['authors']) ? $_POST['authors'] : '[]';
$authors = json_decode($authors_json, true);
if (json_last_error() !== JSON_ERROR_NONE || empty($authors) || !is_array($authors)) {
    $_SESSION['error_message'] = "Invalid author data.";
    unlink($document_path);
    header("Location: submit_manuscript.php");
    exit();
}

$author_names = [];
foreach ($authors as $author) {
    if (empty($author['firstName']) || empty($author['lastName'])) {
        $_SESSION['error_message'] = "Each author must have a first name and last name.";
        unlink($document_path);
        header("Location: submit_manuscript.php");
        exit();
    }
    $full_name = trim("{$author['firstName']} {$author['middleName']} {$author['lastName']} {$author['suffix']}");
    $full_name = preg_replace('/\s+/', ' ', $full_name);
    $author_names[] = $full_name;
}
$author_string = implode(', ', array_filter($author_names));

// Store the original author data in a compact format that can be parsed back
// Format: STUDENT_DATA:firstName1|middleName1|lastName1|suffix1@@firstName2|middleName2|lastName2|suffix2@@|DISPLAY:displayString
$author_data_compact = "STUDENT_DATA:";
foreach ($authors as $author) {
    $author_data_compact .= $author['firstName'] . "|" . ($author['middleName'] ?: '') . "|" . $author['lastName'] . "|" . ($author['suffix'] ?: '') . "@@";
}
$author_data_compact = rtrim($author_data_compact, "@") . "|DISPLAY:" . $author_string;
$author_string = $author_data_compact;

// Verify the session user exists in `users` (to satisfy FK)
$user_id = (int)$_SESSION['user_id'];
$check_user_query = "SELECT id FROM users WHERE id = ? LIMIT 1";
$check_user_stmt = mysqli_prepare($conn, $check_user_query);
mysqli_stmt_bind_param($check_user_stmt, "i", $user_id);
mysqli_stmt_execute($check_user_stmt);
$check_user_result = mysqli_stmt_get_result($check_user_stmt);
if (!$check_user_result || mysqli_num_rows($check_user_result) === 0) {
    $_SESSION['error_message'] = "Your account could not be found. Please log out and log in again, or contact support.";
    mysqli_stmt_close($check_user_stmt);
    unlink($document_path);
    header("Location: submit_manuscript.php");
    exit();
}
mysqli_stmt_close($check_user_stmt);

// Optional: Verify a linked student profile exists
$check_student_query = "SELECT id FROM students WHERE user_id = ? LIMIT 1";
$check_student_stmt = mysqli_prepare($conn, $check_student_query);
mysqli_stmt_bind_param($check_student_stmt, "i", $user_id);
mysqli_stmt_execute($check_student_stmt);
$check_student_result = mysqli_stmt_get_result($check_student_stmt);
if (!$check_student_result || mysqli_num_rows($check_student_result) === 0) {
    $_SESSION['error_message'] = "No student profile linked to your account. Please contact the administrator.";
    mysqli_stmt_close($check_student_stmt);
    unlink($document_path);
    header("Location: submit_manuscript.php");
    exit();
}
mysqli_stmt_close($check_student_stmt);

// Enforce one capstone per group (server-side guard)
$group_has_submission = false;
if (isset($_SESSION['email'])) {
    $emailForGroup = $_SESSION['email'];
    $group_check_sql = "
        SELECT COUNT(*) AS c
        FROM capstone c
        WHERE c.user_id IN (
            SELECT u.id
            FROM users u
            INNER JOIN students s ON u.email = s.email
            WHERE s.group_code = (
                SELECT s2.group_code FROM students s2 WHERE s2.email = ? LIMIT 1
            )
        )
    ";
    $group_check_stmt = mysqli_prepare($conn, $group_check_sql);
    mysqli_stmt_bind_param($group_check_stmt, "s", $emailForGroup);
    mysqli_stmt_execute($group_check_stmt);
    $group_check_res = mysqli_stmt_get_result($group_check_stmt);
    if ($group_check_res) {
        $row = mysqli_fetch_assoc($group_check_res);
        $group_has_submission = ((int)$row['c']) > 0;
    }
    mysqli_stmt_close($group_check_stmt);
}

if ($group_has_submission) {
    $_SESSION['error_message'] = "Your group already has a capstone submission. Only one submission is allowed per group.";
    unlink($document_path);
    header("Location: submit_manuscript.php");
    exit();
}

// Insert into capstone table
$query = "INSERT INTO capstone (title, author, year, abstract, keywords, document_path, user_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $query);
$status = 'nonverified';
mysqli_stmt_bind_param($stmt, "ssisssis", $_POST['title'], $author_string, $year, $_POST['abstract'], $_POST['keywords'], $document_path, $user_id, $status);
$success = mysqli_stmt_execute($stmt);

if ($success) {
    // Add notification for successful capstone submission
    require_once '../assets/includes/notification_functions.php';
    createNotification(
        $conn, 
        $user_id, 
        'Capstone Project Submitted', 
        'Your capstone project "' . htmlspecialchars($_POST['title']) . '" has been submitted successfully and is under review.',
        'success',
        $conn->insert_id,
        'capstone'
    );
    
    // Notify admin about new research submission
    $admin_query = "SELECT id FROM users WHERE role = 'admin' LIMIT 1";
    $admin_result = mysqli_query($conn, $admin_query);
    if ($admin_result && mysqli_num_rows($admin_result) > 0) {
        $admin_row = mysqli_fetch_assoc($admin_result);
        $admin_id = $admin_row['id'];
        
        createNotification(
            $conn,
            $admin_id,
            'New Research Submitted',
            'A new research paper "' . htmlspecialchars($_POST['title']) . '" has been submitted and requires verification.',
            'info',
            $conn->insert_id,
            'new_research'
        );
    }
    
    $_SESSION['success_message'] = "Capstone project submitted successfully!";
    unset($_SESSION['old_input']);
} else {
    $_SESSION['error_message'] = "Failed to submit capstone project. " . mysqli_error($conn);
    unlink($document_path); // Remove uploaded file on failure
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

header("Location: submit_manuscript.php");
exit();
?>