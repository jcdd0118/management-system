<?php
session_start();
include '../config/database.php';

function add_dean($data, $conn) {
    // Extract and sanitize inputs
    $last_name = trim($data['last_name']);
    $first_name = trim($data['first_name']);
    $middle_name = trim($data['middle_name']);
    $email = trim($data['email']);
    $password = trim($data['password']);
    $gender = $data['gender'];
    $role = 'dean';
    $status = 'verified';

    // Validate required fields
    if (empty($last_name) || empty($first_name) || empty($email) || empty($password) || empty($gender)) {
        return "All fields are required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email format.";
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        return "Database error: " . $conn->error;
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return "Email is already registered.";
    }
    $stmt->close();

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (last_name, first_name, middle_name, email, password, gender, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return "Database error: " . $conn->error;
    }
    $stmt->bind_param("ssssssss", $last_name, $first_name, $middle_name, $email, $hashed_password, $gender, $role, $status);

    if (!$stmt->execute()) {
        $stmt->close();
        return "Failed to add dean: " . $stmt->error;
    }
    $stmt->close();

    return true; // success
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $result = add_dean($_POST, $conn);

    if ($result === true) {
        $_SESSION['success_message'] = "Dean added successfully.";
        header("Location: dean_list.php");
        exit();
    } else {
        // Pass error message via session
        $_SESSION['old_input'] = $_POST;
        $_SESSION['error_message'] = $result;
        header("Location: dean_list.php?show_modal=true");
        exit();
    }
}
?>