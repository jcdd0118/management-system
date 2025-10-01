<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/login.php");
    exit();
}

// Database connection
include '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $first = $_POST['first_name'];
    $middle = $_POST['middle_name'];
    $last = $_POST['last_name'];
    $email = $_POST['email'];
    $gender = $_POST['gender'];

    $stmt = $conn->prepare("UPDATE users SET first_name=?, middle_name=?, last_name=?, email=?, gender=? WHERE id=? AND role='grammarian'");
    $stmt->bind_param("sssssi", $first, $middle, $last, $email, $gender, $id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Grammarian updated successfully.";
        header("Location: users.php?update=success");
        header("Cache-Control: no-cache, must-revalidate");
        exit();
    } else {
        $_SESSION['error_message'] = "Failed to update grammarian.";
        header("Location: users.php?update=error");
        exit();
    }
}
?>
