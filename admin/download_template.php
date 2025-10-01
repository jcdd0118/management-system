<?php
// Start the session
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../users/login.php");
    exit();
}

$type = isset($_GET['type']) ? $_GET['type'] : '';

if ($type === 'students') {
    // Set headers for CSV file download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="students_template.csv"');
    header('Cache-Control: max-age=0');
    
    // Generate CSV content
    echo "ID,First Name,Middle Name,Last Name,Email,Gender,Year Section,Group Code,Status\n";
    echo ",John,Michael,Doe,john.doe@example.com,Male,3A,3A-G1,verified\n";
    echo ",Jane,,Smith,jane.smith@example.com,Female,4B,4B-G2,verified\n";
    
} elseif ($type === 'research') {
    // Set headers for CSV file download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="research_template.csv"');
    header('Cache-Control: max-age=0');
    
    // Generate CSV content
    echo "ID,Title,Author,Year,Abstract,Keywords,Document Path,User ID,Status\n";
    echo ",Sample Research Study,John Doe,2024,This is a sample abstract for the research paper that demonstrates the import functionality.,research sample study,uploads/research_sample.pdf,,verified\n";
    echo ",Advanced Data Analysis,Jane Smith,2023,Another sample abstract for demonstration purposes showing how research data can be imported.,analysis data results,,,verified\n";
    
} else {
    header("Location: ../users/login.php");
    exit();
}

exit();
?>
