<?php
session_start();

// Database connection
include '../config/database.php';
include '../assets/includes/email_functions.php';

// Get the student ID from the URL
$student_id = isset($_GET['id']) ? $_GET['id'] : '';

// Verify student and update status in both tables
if ($student_id) {
    // First, get student information before updating
    $get_student_query = "SELECT s.first_name, s.middle_name, s.last_name, s.email, s.user_id FROM students s WHERE s.id = ?";
    $stmt_student_info = $conn->prepare($get_student_query);
    $stmt_student_info->bind_param("i", $student_id);
    $stmt_student_info->execute();
    $student_result = $stmt_student_info->get_result();
    $student_info = $student_result->fetch_assoc();
    
    if (!$student_info) {
        $_SESSION['error_message'] = "Student not found.";
        header("Location: student_list.php");
        exit();
    }
    
    // Update status in the students table
    $update_student_query = "UPDATE students SET status = 'verified' WHERE id = ?";
    $stmt = $conn->prepare($update_student_query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    // Get user_id from students table to update users table
    $get_user_query = "SELECT user_id FROM students WHERE id = ?";
    $stmt_user = $conn->prepare($get_user_query);
    $stmt_user->bind_param("i", $student_id);
    $stmt_user->execute();
    $result = $stmt_user->get_result();
    $user = $result->fetch_assoc();
    $user_id = $user['user_id'];

    // Update status in the users table
    if ($user_id) {
        $update_user_query = "UPDATE users SET status = 'verified' WHERE id = ?";
        $stmt_user_update = $conn->prepare($update_user_query);
        $stmt_user_update->bind_param("i", $user_id);
        $stmt_user_update->execute();
    }

    // Send email notification to the student
    $student_name = trim($student_info['first_name'] . ' ' . $student_info['middle_name'] . ' ' . $student_info['last_name']);
    $student_email = $student_info['email'];
    
    $email_sent = false;
    if (!empty($student_email) && filter_var($student_email, FILTER_VALIDATE_EMAIL)) {
        $email_sent = sendStudentVerificationEmail($student_email, $student_name);
    }

    // Close the database connections
    $stmt->close();
    $stmt_user->close();
    $stmt_user_update->close();
    $stmt_student_info->close();
    mysqli_close($conn);

    // Set success message and redirect back to the student list page
    if ($email_sent) {
        $_SESSION['success_message'] = "Student verified successfully and notification email sent!";
    } else {
        $_SESSION['success_message'] = "Student verified successfully, but email notification could not be sent.";
    }
    header("Location: student_list.php");
    exit();
} else {
    // Set error message and redirect back to the student list page
    $_SESSION['error_message'] = "Invalid student ID.";
    header("Location: student_list.php");
    exit();
}
?>
