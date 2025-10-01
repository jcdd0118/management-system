<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/login.php");
    exit();
}

// Database connection
include '../config/database.php';

// Function to delete a panelist
function delete_panelist($id, $conn) {
    // Validate ID
    if (!is_numeric($id) || $id <= 0) {
        return "Invalid panelist ID.";
    }

    // Check if panelist exists and has role 'panelist'
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'panelist'");
    if (!$stmt) {
        return "Database error: " . $conn->error;
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        return "Panelist not found or invalid role.";
    }
    $stmt->close();

    // Delete panelist
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    if (!$stmt) {
        return "Database error: " . $conn->error;
    }
    $stmt->bind_param("i", $id);

    if (!$stmt->execute()) {
        $stmt->close();
        return "Failed to delete panelist: " . $stmt->error;
    }
    $stmt->close();

    return true; // Success
}

// Handle the delete request
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $result = delete_panelist($id, $conn);

    // Close the database connection
    mysqli_close($conn);

    if ($result === true) {
        $_SESSION['success_message'] = "Panelist deleted successfully.";
        header("Location: panelist_list.php");
        exit();
    } else {
        $_SESSION['error_message'] = $result;
        header("Location: panelist_list.php");
        exit();
    }
} else {
    // No ID provided
    $_SESSION['error_message'] = "No panelist ID provided.";
    header("Location: panelist_list.php");
    exit();
}
?>