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

// Get all students
$query = "SELECT * FROM students ORDER BY id DESC";
$result = mysqli_query($conn, $query);

// Set headers for CSV file download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="students_export_' . date('Y-m-d_H-i-s') . '.csv"');
header('Cache-Control: max-age=0');

// Generate CSV content
echo "ID,First Name,Middle Name,Last Name,Email,Gender,Year & Section,Group Code,Status,Created At\n";

// Add student data
while ($row = mysqli_fetch_assoc($result)) {
    // Escape CSV values and handle commas/quotes
    $csv_row = array(
        $row['id'],
        '"' . str_replace('"', '""', $row['first_name']) . '"',
        '"' . str_replace('"', '""', $row['middle_name']) . '"',
        '"' . str_replace('"', '""', $row['last_name']) . '"',
        '"' . str_replace('"', '""', $row['email']) . '"',
        '"' . str_replace('"', '""', $row['gender']) . '"',
        '"' . str_replace('"', '""', $row['year_section']) . '"',
        '"' . str_replace('"', '""', $row['group_code']) . '"',
        '"' . str_replace('"', '""', $row['status']) . '"',
        '"' . str_replace('"', '""', isset($row['created_at']) ? $row['created_at'] : '') . '"'
    );
    echo implode(',', $csv_row) . "\n";
}

mysqli_close($conn);
exit();
?>
