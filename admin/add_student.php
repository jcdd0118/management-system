<?php
session_start();
include '../config/database.php';
require_once '../assets/includes/year_section_functions.php';
require_once '../assets/includes/group_functions.php';

function add_student($data, $conn) {
    // Extract and sanitize inputs
    $last_name = trim($data['last_name']);
    $first_name = trim($data['first_name']);
    $middle_name = trim($data['middle_name']);
    $email = trim($data['email']);
    $password = trim($data['password']);
    $gender = $data['gender'];
    $year_section = $data['year_section'];
    $group_code = trim($data['group_code']);
    $role = 'student';

    // Validate required fields
    if (empty($last_name) || empty($first_name) || empty($email) || empty($password) || empty($gender) || empty($year_section) || empty($group_code)) {
        return "All fields are required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email format.";
    }

    // Validate group_code pattern (optional)
    if (!preg_match("/^[3-4][AB]-G\d+$/", $group_code)) {
        return "Group code format is invalid. Use format like 3B-G1 or 4A-G2.";
    }

    // Validate year_section exists in database
    if (!yearSectionExists($conn, $year_section)) {
        return "Invalid year section. Please select a valid year section.";
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
    $stmt = $conn->prepare("INSERT INTO users (last_name, first_name, middle_name, email, password, gender, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'verified')");
    if (!$stmt) {
        return "Database error: " . $conn->error;
    }
    $stmt->bind_param("sssssss", $last_name, $first_name, $middle_name, $email, $hashed_password, $gender, $role);

    if (!$stmt->execute()) {
        $stmt->close();
        return "Failed to add user: " . $stmt->error;
    }
    $user_id = $stmt->insert_id;
    $stmt->close();

    // Resolve/create group_id from group_code + year_section
    $group_id = null;
    if (preg_match('/^(?<year>[34])(?<letter>[A-Z])\-(?<gname>G\d+)$/', $group_code, $m)) {
        $yearLevel = (int)$m['year'];
        $sectionLetter = $m['letter'];
        $groupName = $m['gname'];
        $cohortYear = null;
        $ensured = ensureGroupId($conn, $groupName, $year_section, $cohortYear, $yearLevel, $sectionLetter);
        if (!empty($ensured)) { $group_id = (int)$ensured; }
    }

    // Insert student (with group_id if resolved)
    $stmt = $conn->prepare("INSERT INTO students (user_id, first_name, middle_name, last_name, email, gender, year_section, group_code, group_id, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'verified')");
    if (!$stmt) {
        return "Database error: " . $conn->error;
    }
    // Types: i (user_id), s s s s s s (strings), s (group_code), i (group_id), s (role)
    $stmt->bind_param("isssssssis", $user_id, $first_name, $middle_name, $last_name, $email, $gender, $year_section, $group_code, $group_id, $role);

    if (!$stmt->execute()) {
        $stmt->close();
        return "Failed to add student: " . $stmt->error;
    }
    $stmt->close();

    return true; // success
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $result = add_student($_POST, $conn);

    if ($result === true) {
        // Notify admin about new student addition
        require_once '../assets/includes/notification_functions.php';
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        createNotification(
            $conn,
            $_SESSION['user_id'],
            'Student Added',
            'A new student "' . htmlspecialchars($first_name . ' ' . $last_name) . '" has been added to the system.',
            'info',
            null,
            'new_student'
        );
        
        $_SESSION['success_message'] = "Student added successfully.";
        header("Location: student_list.php");
        exit();
    } else {
        // Pass error message via session
        $_SESSION['old_input'] = $_POST;
        $_SESSION['error_message'] = $result;
        header("Location: student_list.php");
        exit();
    }
}
?>
