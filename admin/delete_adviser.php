<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/login.php");
    exit();
}

// Database connection
include '../config/database.php';

// Function to delete an adviser
function delete_adviser($id, $conn) {
    // Validate ID
    if (!is_numeric($id) || $id <= 0) {
        return "Invalid adviser ID.";
    }

    // Check if adviser exists and has role 'adviser'
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'adviser'");
    if (!$stmt) {
        return "Database error: " . $conn->error;
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        return "Adviser not found or invalid role.";
    }
    $stmt->close();

    // Delete adviser
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    if (!$stmt) {
        return "Database error: " . $conn->error;
    }
    $stmt->bind_param("i", $id);

    if (!$stmt->execute()) {
        $stmt->close();
        return "Failed to delete adviser: " . $stmt->error;
    }
    $stmt->close();

    return true; // Success
}

// Handle the delete request
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $result = delete_adviser($id, $conn);

    // Close the database connection
    mysqli_close($conn);

    if ($result === true) {
        $_SESSION['success_message'] = "Adviser deleted successfully.";
        header("Location: adviser_list.php");
        exit();
    } else {
        $_SESSION['error_message'] = $result;
        header("Location: adviser_list.php");
        exit();
    }
} else {
    // No ID provided
    $_SESSION['error_message'] = "No adviser ID provided.";
    header("Location: adviser_list.php");
    exit();
}
?>