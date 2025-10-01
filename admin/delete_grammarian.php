<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/login.php");
    exit();
}

// Database connection
include '../config/database.php';

// Function to delete a grammarian
function delete_grammarian($id, $conn) {
    // Validate ID
    if (!is_numeric($id) || $id <= 0) {
        return "Invalid grammarian ID.";
    }

    // Check if grammarian exists and has role 'grammarian'
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'grammarian'");
    if (!$stmt) {
        return "Database error: " . $conn->error;
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        return "Grammarian not found.";
    }
    $stmt->close();

    // Delete the grammarian
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'grammarian'");
    if (!$stmt) {
        return "Database error: " . $conn->error;
    }
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $stmt->close();
        return "Grammarian deleted successfully.";
    } else {
        $error = "Failed to delete grammarian: " . $stmt->error;
        $stmt->close();
        return $error;
    }
}

// Handle deletion
$message = "";
$error = "";

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $result = delete_grammarian($id, $conn);
    
    if (strpos($result, "successfully") !== false) {
        $message = $result;
    } else {
        $error = $result;
    }
} else {
    $error = "No grammarian ID provided.";
}

// Redirect back to users page with message
$redirect_url = "users.php";
if ($message) {
    $redirect_url .= "?success=" . urlencode($message);
} elseif ($error) {
    $redirect_url .= "?error=" . urlencode($error);
}

header("Location: " . $redirect_url);
exit();
?>
