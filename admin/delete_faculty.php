<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/login.php");
    exit();
}

// Database connection
include '../config/database.php';

// Function to delete a faculty
function delete_faculty($id, $conn) {
    // Validate ID
    if (!is_numeric($id) || $id <= 0) {
        return "Invalid faculty ID.";
    }

    // Check if faculty exists and has role 'faculty'
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'faculty'");
    if (!$stmt) {
        return "Database error: " . $conn->error;
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        return "Faculty not found or invalid role.";
    }
    $stmt->close();

    // Delete faculty
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    if (!$stmt) {
        return "Database error: " . $conn->error;
    }
    $stmt->bind_param("i", $id);

    if (!$stmt->execute()) {
        $stmt->close();
        return "Failed to delete faculty: " . $stmt->error;
    }
    $stmt->close();

    return true; // Success
}

// Handle the delete request
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $result = delete_faculty($id, $conn);

    // Close the database connection
    mysqli_close($conn);

    if ($result === true) {
        $_SESSION['success_message'] = "Faculty deleted successfully.";
        header("Location: faculty_list.php");
        exit();
    } else {
        $_SESSION['error_message'] = $result;
        header("Location: faculty_list.php");
        exit();
    }
} else {
    // No ID provided
    $_SESSION['error_message'] = "No faculty ID provided.";
    header("Location: faculty_list.php");
    exit();
}
?>