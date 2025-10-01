<?php
// Start the session
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../users/login.php");
    exit();
}

// Database connection
include '../config/database.php';
require_once '../assets/includes/year_section_functions.php';
require_once '../assets/includes/group_functions.php';

$success_count = 0;
$error_count = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    // Check if file was uploaded successfully
    if ($file['error'] == UPLOAD_ERR_OK) {
        $file_path = $file['tmp_name'];
        
        // Read the Excel file (CSV format)
        if (($handle = fopen($file_path, "r")) !== FALSE) {
            $row_count = 0;
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row_count++;
                
                // Skip header row
                if ($row_count == 1) {
                    continue;
                }
                
                // Validate required fields
                if (count($data) < 8) {
                    $errors[] = "Row $row_count: Insufficient data columns";
                    $error_count++;
                    continue;
                }
                
                $first_name = trim($data[1]);
                $middle_name = trim($data[2]);
                $last_name = trim($data[3]);
                $email = trim($data[4]);
                $gender = trim($data[5]);
                $year_section = trim($data[6]);
                $group_code = trim($data[7]);
                $status = isset($data[8]) ? trim($data[8]) : 'not verified';
                
                // Validate required fields
                if (empty($first_name) || empty($last_name) || empty($email) || empty($year_section) || empty($group_code)) {
                    $errors[] = "Row $row_count: Missing required fields (First Name, Last Name, Email, Year Section, Group Code)";
                    $error_count++;
                    continue;
                }
                
                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Row $row_count: Invalid email format";
                    $error_count++;
                    continue;
                }
                
                // Check if email already exists in users table (authoritative for login)
                $check_email_users = "SELECT id FROM users WHERE email = ?";
                $stmt_check_users = mysqli_prepare($conn, $check_email_users);
                mysqli_stmt_bind_param($stmt_check_users, "s", $email);
                mysqli_stmt_execute($stmt_check_users);
                $result_check_users = mysqli_stmt_get_result($stmt_check_users);
                if (mysqli_num_rows($result_check_users) > 0) {
                    $errors[] = "Row $row_count: Email already exists in users";
                    $error_count++;
                    mysqli_stmt_close($stmt_check_users);
                    continue;
                }
                mysqli_stmt_close($stmt_check_users);
                
                // Validate gender
                if (!in_array($gender, ['Male', 'Female'])) {
                    $errors[] = "Row $row_count: Invalid gender (must be Male or Female)";
                    $error_count++;
                    continue;
                }
                
                // Validate year_section exists in database
                if (!yearSectionExists($conn, $year_section)) {
                    $errors[] = "Row $row_count: Invalid year section '$year_section' (not found in system)";
                    $error_count++;
                    continue;
                }
                
                // Validate group_code format
                if (!preg_match('/^[3-4][AB]-G\d+$/', $group_code)) {
                    $errors[] = "Row $row_count: Invalid group code format (must be like 3A-G1, 4B-G2)";
                    $error_count++;
                    continue;
                }
                
                // Validate status
                if (!in_array($status, ['verified', 'not verified'])) {
                    $status = 'not verified';
                }
                
                // Generate a default password and create corresponding user account
                $default_password_hash = password_hash('password123', PASSWORD_BCRYPT);

                // Normalize status for both tables; make accounts verified so they can log in immediately
                $final_status = ($status === 'verified') ? 'verified' : 'verified';

                // Insert into users table first
                $insert_user_sql = "INSERT INTO users (last_name, first_name, middle_name, email, password, gender, role, status) VALUES (?, ?, ?, ?, ?, ?, 'student', ?)";
                $stmt_user = mysqli_prepare($conn, $insert_user_sql);
                if (!$stmt_user) {
                    $errors[] = "Row $row_count: Database error (prepare users) - " . mysqli_error($conn);
                    $error_count++;
                    continue;
                }
                mysqli_stmt_bind_param($stmt_user, "sssssss", $last_name, $first_name, $middle_name, $email, $default_password_hash, $gender, $final_status);
                if (!mysqli_stmt_execute($stmt_user)) {
                    $errors[] = "Row $row_count: Database error (insert users) - " . mysqli_error($conn);
                    $error_count++;
                    mysqli_stmt_close($stmt_user);
                    continue;
                }
                $new_user_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt_user);

                // Determine group_id from group_code/year_section (create group if needed)
                $groupId = null;
                if (preg_match('/^(?<year>[34])(?<letter>[A-Z])\-(?<gname>G\d+)$/', $group_code, $m)) {
                    $yearLevel = (int)$m['year'];
                    $sectionLetter = $m['letter'];
                    $groupName = $m['gname']; // e.g., G1
                    $cohortYear = null; // not provided in import
                    $ensuredId = ensureGroupId($conn, $groupName, $year_section, $cohortYear, $yearLevel, $sectionLetter);
                    if (!empty($ensuredId)) { $groupId = (int)$ensuredId; }
                }

                // Insert into students table linked via user_id and optional group_id
                $insert_student_sql = "INSERT INTO students (user_id, first_name, middle_name, last_name, email, gender, year_section, group_code, group_id, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'student', ?, NOW())";
                $stmt_student = mysqli_prepare($conn, $insert_student_sql);
                if (!$stmt_student) {
                    // cleanup created user to avoid orphan
                    mysqli_query($conn, "DELETE FROM users WHERE id = " . (int)$new_user_id);
                    $errors[] = "Row $row_count: Database error (prepare students) - " . mysqli_error($conn);
                    $error_count++;
                    continue;
                }
                mysqli_stmt_bind_param($stmt_student, "isssssssis", $new_user_id, $first_name, $middle_name, $last_name, $email, $gender, $year_section, $group_code, $groupId, $final_status);
                if (mysqli_stmt_execute($stmt_student)) {
                    $success_count++;
                } else {
                    // cleanup created user to avoid orphan
                    mysqli_query($conn, "DELETE FROM users WHERE id = " . (int)$new_user_id);
                    $errors[] = "Row $row_count: Database error (insert students) - " . mysqli_error($conn);
                    $error_count++;
                }
                mysqli_stmt_close($stmt_student);
            }
            
            fclose($handle);
        } else {
            $errors[] = "Could not read the uploaded file";
            $error_count++;
        }
    } else {
        $errors[] = "File upload error: " . $file['error'];
        $error_count++;
    }
}

mysqli_close($conn);

// Store results in session for display
$_SESSION['import_results'] = [
    'success_count' => $success_count,
    'error_count' => $error_count,
    'errors' => $errors
];

// Redirect back to student list
header("Location: student_list.php?import=completed");
exit();
?>
