<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../users/login.php");
    exit();
}

require_once '../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $source = isset($_POST['source']) ? trim($_POST['source']) : '';
    $first = trim(isset($_POST['first_name']) ? $_POST['first_name'] : '');
    $middle = trim(isset($_POST['middle_name']) ? $_POST['middle_name'] : '');
    $last = trim(isset($_POST['last_name']) ? $_POST['last_name'] : '');
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
    $roles = trim(isset($_POST['roles']) ? $_POST['roles'] : '');

    // Get user's full name for messages
    $fullName = trim($first . ' ' . (!empty($middle) ? $middle . ' ' : '') . $last);

    // Validate required fields
    if ($id <= 0 || $first === '' || $last === '' || $email === '' || $gender === '' || $source === '') {
        $_SESSION['error_message'] = '<i class="bi bi-exclamation-triangle-fill"></i> All required fields must be filled.';
        header("Location: users.php");
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = '<i class="bi bi-envelope-exclamation-fill"></i> Invalid email format.';
        header("Location: users.php");
        exit();
    }

    // Check if email already exists for other users/students (excluding current record)
    $emailExists = false;
    $conflictUserName = '';
    
    if ($source === 'students') {
        // Check in students table (excluding current record)
        $checkStmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as full_name FROM students WHERE email = ? AND id != ?");
        $checkStmt->bind_param('si', $email, $id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $row = $checkResult->fetch_assoc();
            $emailExists = true;
            $conflictUserName = $row['full_name'];
        }
        $checkStmt->close();

        // Also check in users table
        if (!$emailExists) {
            $checkStmt2 = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE email = ?");
            $checkStmt2->bind_param('s', $email);
            $checkStmt2->execute();
            $checkResult2 = $checkStmt2->get_result();
            if ($checkResult2->num_rows > 0) {
                $row2 = $checkResult2->fetch_assoc();
                $emailExists = true;
                $conflictUserName = $row2['full_name'];
            }
            $checkStmt2->close();
        }
    } else {
        // Check in users table (excluding current record)
        $checkStmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE email = ? AND id != ?");
        $checkStmt->bind_param('si', $email, $id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $row = $checkResult->fetch_assoc();
            $emailExists = true;
            $conflictUserName = $row['full_name'];
        }
        $checkStmt->close();

        // Also check in students table
        if (!$emailExists) {
            $checkStmt2 = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as full_name FROM students WHERE email = ?");
            $checkStmt2->bind_param('s', $email);
            $checkStmt2->execute();
            $checkResult2 = $checkStmt2->get_result();
            if ($checkResult2->num_rows > 0) {
                $row2 = $checkResult2->fetch_assoc();
                $emailExists = true;
                $conflictUserName = $row2['full_name'];
            }
            $checkStmt2->close();
        }
    }

    if ($emailExists) {
        $_SESSION['error_message'] = '<i class="bi bi-envelope-exclamation-fill"></i> Email address <strong>' . htmlspecialchars($email) . '</strong> is already registered to <strong>' . htmlspecialchars($conflictUserName) . '</strong>. Please use a different email address.';
        header("Location: users.php");
        exit();
    }

    // Update based on source (students or users table)
    if ($source === 'students') {
        // Update students table
        $updateStmt = $conn->prepare("UPDATE students SET first_name = ?, middle_name = ?, last_name = ?, email = ?, gender = ? WHERE id = ?");
        $updateStmt->bind_param('sssssi', $first, $middle, $last, $email, $gender, $id);
        
        if ($updateStmt->execute()) {
            if ($updateStmt->affected_rows > 0) {
                $_SESSION['success_message'] = '<i class="bi bi-check-circle-fill"></i> Student <strong>' . htmlspecialchars($fullName) . '</strong> has been updated successfully!';
            } else {
                $_SESSION['info_message'] = '<i class="bi bi-info-circle-fill"></i> No changes were made to student <strong>' . htmlspecialchars($fullName) . '</strong>. The information was already up to date.';
            }
        } else {
            $_SESSION['error_message'] = '<i class="bi bi-exclamation-triangle-fill"></i> Failed to update student <strong>' . htmlspecialchars($fullName) . '</strong>. Database error: ' . htmlspecialchars($updateStmt->error);
        }
        $updateStmt->close();
    } else {
        // Update users table
        // For users table, we need to handle roles properly
        
        // Parse and clean roles
        $rolesArray = array_filter(array_map('trim', explode(',', $roles)));
        $cleanRoles = [];
        
        foreach ($rolesArray as $role) {
            $cleanRole = strtolower(trim($role));
            if (!empty($cleanRole)) {
                $cleanRoles[] = $cleanRole;
            }
        }
        
        // Remove duplicates and create final roles string
        $uniqueRoles = array_unique($cleanRoles);
        $finalRoles = implode(', ', $uniqueRoles);
        
        // Determine primary role (first role in the list)
        $primaryRole = !empty($uniqueRoles) ? reset($uniqueRoles) : 'faculty';
        
        // Check if roles column exists in users table
        $hasRolesColumn = false;
        $checkColumnsResult = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'roles'");
        if ($checkColumnsResult) {
            $row = $checkColumnsResult->fetch_assoc();
            $hasRolesColumn = ((int)$row['c']) > 0;
            $checkColumnsResult->close();
        }
        
        if ($hasRolesColumn) {
            // Update with roles column
            $updateStmt = $conn->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ?, gender = ?, role = ?, roles = ? WHERE id = ?");
            $updateStmt->bind_param('sssssssi', $first, $middle, $last, $email, $gender, $primaryRole, $finalRoles, $id);
        } else {
            // Update without roles column
            $updateStmt = $conn->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ?, gender = ?, role = ? WHERE id = ?");
            $updateStmt->bind_param('ssssssi', $first, $middle, $last, $email, $gender, $primaryRole, $id);
        }
        
        if ($updateStmt->execute()) {
            if ($updateStmt->affected_rows > 0) {
                $roleLabels = [];
                foreach ($uniqueRoles as $role) {
                    switch (strtolower($role)) {
                        case 'faculty': $roleLabels[] = 'Capstone Adviser'; break;
                        case 'adviser': $roleLabels[] = 'Capstone Professor'; break;
                        case 'panelist': $roleLabels[] = 'Panelist'; break;
                        case 'dean': $roleLabels[] = 'Dean'; break;
                        case 'admin': $roleLabels[] = 'Admin'; break;
                        default: $roleLabels[] = ucfirst($role); break;
                    }
                }
                $roleString = implode(', ', $roleLabels);
                $_SESSION['success_message'] = '<i class="bi bi-check-circle-fill"></i> User <strong>' . htmlspecialchars($fullName) . '</strong> has been updated successfully!<br><small class="text-muted">Roles: ' . htmlspecialchars($roleString) . '</small>';
            } else {
                $_SESSION['info_message'] = '<i class="bi bi-info-circle-fill"></i> No changes were made to user <strong>' . htmlspecialchars($fullName) . '</strong>. The information was already up to date.';
            }
        } else {
            $_SESSION['error_message'] = '<i class="bi bi-exclamation-triangle-fill"></i> Failed to update user <strong>' . htmlspecialchars($fullName) . '</strong>. Database error: ' . htmlspecialchars($updateStmt->error);
        }
        $updateStmt->close();
    }
} else {
    $_SESSION['error_message'] = '<i class="bi bi-exclamation-triangle-fill"></i> Invalid request method.';
}

mysqli_close($conn);
header("Location: users.php");
exit();
?>