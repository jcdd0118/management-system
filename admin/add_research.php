<?php
// Start the session
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../users/login.php");
    exit();
}

// Database connection
require '../config/database.php';

// Initialize error message
$_SESSION['error_message'] = '';
$_SESSION['old_input'] = $_POST; // Store form inputs for repopulating on error

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and validate inputs
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $author = isset($_POST['author']) ? trim($_POST['author']) : '';
    $year = isset($_POST['year']) ? trim($_POST['year']) : '';
    $abstract = isset($_POST['abstract']) ? trim($_POST['abstract']) : '';
    $keywords = isset($_POST['keywords']) ? trim($_POST['keywords']) : '';
    $user_id = isset($_POST['user_id']) && trim($_POST['user_id']) != '' ? trim($_POST['user_id']) : null;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';

    // Validate inputs
    $errors = array();
    if (empty($title)) {
        $errors[] = "Title is required.";
    }
    if (empty($author)) {
        $errors[] = "Author is required.";
    }
    if (empty($year) || !is_numeric($year) || $year < 1900 || $year > date('Y')) {
        $errors[] = "Valid year is required.";
    }
    if (empty($abstract)) {
        $errors[] = "Abstract is required.";
    }
    if (empty($keywords)) {
        $errors[] = "Keywords are required.";
    }
    if (!in_array($status, array('verified', 'not verified'))) {
        $errors[] = "Invalid status selected.";
    }

    // Handle file upload
    $document_path = null;
    if (isset($_FILES['document']) && $_FILES['document']['error'] != UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['document'];
        $allowed_types = array('application/pdf');
        $max_size = 50 * 1024 * 1024; // 50MB

        // Validate file
        if ($file['error'] != UPLOAD_ERR_OK) {
            $errors[] = "Error uploading file.";
        } elseif (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Only PDF files are allowed.";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "File size exceeds 50MB limit.";
        } else {
            // Define upload directory
            $upload_dir = '../assets/uploads/capstone/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Generate unique file name
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $unique_name = uniqid('research_') . '.' . $file_extension;
            $document_path = $upload_dir . $unique_name;

            // Move the uploaded file
            if (!move_uploaded_file($file['tmp_name'], $document_path)) {
                $errors[] = "Failed to upload file.";
            }
        }
    } else {
        $errors[] = "Document file is required.";
    }

    // If there are errors, redirect back with error message
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode(' ', $errors);
        header("Location: research_list.php?show_modal=true");
        exit();
    }

    // Prepare and execute the insert query
    $query = "INSERT INTO capstone (title, author, year, abstract, keywords, document_path, user_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);

    if ($stmt) {
        // Bind parameters (PHP 5 requires explicit binding)
        $bind_result = mysqli_stmt_bind_param($stmt, "ssisssss", 
            $title, 
            $author, 
            $year, 
            $abstract, 
            $keywords, 
            $document_path, 
            $user_id, 
            $status
        );

        if ($bind_result === false) {
            $_SESSION['error_message'] = "Failed to bind parameters.";
            header("Location: research_list.php?show_modal=true");
            exit();
        }

        // Execute the query
        if (mysqli_stmt_execute($stmt)) {
            // Add notification for admin about new research addition
            require_once '../assets/includes/notification_functions.php';
            $research_id = mysqli_insert_id($conn);
            
            createNotification(
                $conn,
                $_SESSION['user_id'],
                'Research Added',
                'A new research paper "' . htmlspecialchars($title) . '" has been added to the system.',
                'info',
                $research_id,
                'new_research'
            );
            
            $_SESSION['success_message'] = "Research added successfully!";
            $_SESSION['add_success'] = true;
            unset($_SESSION['old_input']); // Clear old input data
        } else {
            $_SESSION['error_message'] = "Failed to add research. Please try again.";
            header("Location: research_list.php?show_modal=true");
            exit();
        }

        // Close statement
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error_message'] = "Database error. Please try again.";
        header("Location: research_list.php?show_modal=true");
        exit();
    }

    // Close database connection
    mysqli_close($conn);

    // Redirect to research list with success flag
    header("Location: research_list.php?add=success");
    exit();
} else {
    // If not a POST request, redirect back
    header("Location: research_list.php");
    exit();
}
?>