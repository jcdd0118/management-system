<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../users/login.php");
    exit();
}

// Check for a valid student ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid student ID.';
    header("Location: student_list.php");
    exit();
}

$student_id = intval($_GET['id']);

include '../config/database.php';

// Enable strict error mode for transactions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// First, get the student's details (including linked user_id)
$select_query = "SELECT first_name, last_name, email, user_id FROM students WHERE id = ?";
$stmt = mysqli_prepare($conn, $select_query);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $student_name = $row['first_name'] . ' ' . $row['last_name'];
    $linked_user_id = isset($row['user_id']) ? (int)$row['user_id'] : null;

    // Use transaction to keep integrity
    mysqli_begin_transaction($conn);
    try {
        // Delete from students table first (to avoid FK issues)
        $delete_student_query = "DELETE FROM students WHERE id = ?";
        $delete_student_stmt = mysqli_prepare($conn, $delete_student_query);
        mysqli_stmt_bind_param($delete_student_stmt, "i", $student_id);
        mysqli_stmt_execute($delete_student_stmt);

        // Then delete the linked users row if exists
        if (!is_null($linked_user_id) && $linked_user_id > 0) {
            // Also delete all project working titles and related approvals submitted by this student (by email)
            $student_email_for_projects = $row['email'];
            if (!empty($student_email_for_projects)) {
                // Delete dependent approvals first
                $delete_approvals_stmt = mysqli_prepare($conn, "DELETE pa FROM project_approvals pa INNER JOIN project_working_titles pw ON pa.project_id = pw.id WHERE pw.submitted_by = ?");
                mysqli_stmt_bind_param($delete_approvals_stmt, "s", $student_email_for_projects);
                mysqli_stmt_execute($delete_approvals_stmt);

                // Then delete the project working titles
                $delete_projects_stmt = mysqli_prepare($conn, "DELETE FROM project_working_titles WHERE submitted_by = ?");
                mysqli_stmt_bind_param($delete_projects_stmt, "s", $student_email_for_projects);
                mysqli_stmt_execute($delete_projects_stmt);
            }
            // Preserve capstone records by detaching user reference
            $detach_capstone_query = "UPDATE capstone SET user_id = NULL WHERE user_id = ?";
            $detach_capstone_stmt = mysqli_prepare($conn, $detach_capstone_query);
            mysqli_stmt_bind_param($detach_capstone_stmt, "i", $linked_user_id);
            mysqli_stmt_execute($detach_capstone_stmt);

            $delete_user_query = "DELETE FROM users WHERE id = ?";
            $delete_user_stmt = mysqli_prepare($conn, $delete_user_query);
            mysqli_stmt_bind_param($delete_user_stmt, "i", $linked_user_id);
            mysqli_stmt_execute($delete_user_stmt);
        } else {
            // Fallback: attempt by email if user_id missing
            $student_email = $row['email'];
            if (!empty($student_email)) {
                // Delete project approvals and working titles for this student's submissions (by email)
                $delete_approvals_stmt = mysqli_prepare($conn, "DELETE pa FROM project_approvals pa INNER JOIN project_working_titles pw ON pa.project_id = pw.id WHERE pw.submitted_by = ?");
                mysqli_stmt_bind_param($delete_approvals_stmt, "s", $student_email);
                mysqli_stmt_execute($delete_approvals_stmt);

                $delete_projects_stmt = mysqli_prepare($conn, "DELETE FROM project_working_titles WHERE submitted_by = ?");
                mysqli_stmt_bind_param($delete_projects_stmt, "s", $student_email);
                mysqli_stmt_execute($delete_projects_stmt);

                // Find user id first to detach capstone properly
                $find_user_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
                mysqli_stmt_bind_param($find_user_stmt, "s", $student_email);
                mysqli_stmt_execute($find_user_stmt);
                $user_res = mysqli_stmt_get_result($find_user_stmt);
                if ($user_row = mysqli_fetch_assoc($user_res)) {
                    $uid = (int)$user_row['id'];
                    $detach_capstone_query = "UPDATE capstone SET user_id = NULL WHERE user_id = ?";
                    $detach_capstone_stmt = mysqli_prepare($conn, $detach_capstone_query);
                    mysqli_stmt_bind_param($detach_capstone_stmt, "i", $uid);
                    mysqli_stmt_execute($detach_capstone_stmt);
                }

                $delete_user_query = "DELETE FROM users WHERE email = ?";
                $delete_user_stmt = mysqli_prepare($conn, $delete_user_query);
                mysqli_stmt_bind_param($delete_user_stmt, "s", $student_email);
                mysqli_stmt_execute($delete_user_stmt);
            }
        }

        mysqli_commit($conn);
        $_SESSION['success_message'] = "Student <strong>$student_name</strong> has been removed.";
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $_SESSION['error_message'] = 'Failed to delete student: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = 'Student not found.';
}

mysqli_close($conn);
header("Location: student_list.php");
exit();
?>
