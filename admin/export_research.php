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

// Get all research
$query = "SELECT * FROM capstone ORDER BY id DESC";
$result = mysqli_query($conn, $query);

// Set headers for CSV file download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="research_export_' . date('Y-m-d_H-i-s') . '.csv"');
header('Cache-Control: max-age=0');

// Generate CSV content
echo "ID,Title,Author,Year,Abstract,Keywords,Document Path,User ID,Status,Created At\n";

// Add research data
while ($row = mysqli_fetch_assoc($result)) {
    // Parse author data to extract display format
    $author_display = $row['author'];
    if (strpos($row['author'], 'STUDENT_DATA:') === 0) {
        // Extract display part from STUDENT_DATA format
        $parts = explode('|DISPLAY:', $row['author']);
        if (count($parts) === 2) {
            $author_display = $parts[1];
        }
    }
    
    // Escape CSV values and handle commas/quotes
    $csv_row = array(
        $row['id'],
        '"' . str_replace('"', '""', $row['title']) . '"',
        '"' . str_replace('"', '""', $author_display) . '"',
        $row['year'],
        '"' . str_replace('"', '""', $row['abstract']) . '"',
        '"' . str_replace('"', '""', $row['keywords']) . '"',
        '"' . str_replace('"', '""', $row['document_path']) . '"',
        $row['user_id'],
        '"' . str_replace('"', '""', $row['status']) . '"',
        '"' . str_replace('"', '""', isset($row['created_at']) ? $row['created_at'] : '') . '"'
    );
    echo implode(',', $csv_row) . "\n";
}

mysqli_close($conn);
exit();
?>
