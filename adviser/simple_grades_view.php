<?php
session_start();
include '../config/database.php';
require_once '../assets/includes/role_functions.php';

$email = $_SESSION['email'];

// Authorize as adviser using multi-role support
if (!isset($_SESSION['user_data']) || !hasRole($_SESSION['user_data'], 'adviser')) {
    header("Location: ../users/login.php?error=unauthorized_access");
    exit();
}

$adviserId = $_SESSION['user_id'];

// Get defense type and ID from URL
$defenseType = isset($_GET['type']) ? $_GET['type'] : 'title';
$defenseId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$defenseId || !in_array($defenseType, ['title', 'final'])) {
    header("Location: " . ($defenseType === 'final' ? 'final_defense_list.php' : 'title_defense_list.php') . "?error=missing_id");
    exit();
}

// Simple query to get defense info
$defenseTable = $defenseType === 'final' ? 'final_defense' : 'title_defense';
$query = "SELECT * FROM $defenseTable WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $defenseId);
$stmt->execute();
$result = $stmt->get_result();
$defense = $result->fetch_assoc();
$stmt->close();

if (!$defense) {
    echo "<h2>Error: Defense not found</h2>";
    echo "<p>Defense ID: $defenseId</p>";
    echo "<p>Defense Type: $defenseType</p>";
    echo "<p>Table: $defenseTable</p>";
    exit();
}

echo "<h2>Defense Found!</h2>";
echo "<p>Defense ID: " . $defense['id'] . "</p>";
echo "<p>Project ID: " . $defense['project_id'] . "</p>";
echo "<p>Status: " . $defense['status'] . "</p>";

// Get project info
$projectQuery = "SELECT * FROM project_working_titles WHERE id = ?";
$projectStmt = $conn->prepare($projectQuery);
$projectStmt->bind_param("i", $defense['project_id']);
$projectStmt->execute();
$projectResult = $projectStmt->get_result();
$project = $projectResult->fetch_assoc();
$projectStmt->close();

if (!$project) {
    echo "<h2>Error: Project not found</h2>";
    exit();
}

echo "<h2>Project Found!</h2>";
echo "<p>Project Title: " . htmlspecialchars($project['project_title']) . "</p>";
echo "<p>Noted By: " . $project['noted_by'] . "</p>";
echo "<p>Adviser ID: " . $adviserId . "</p>";

if ($project['noted_by'] != $adviserId) {
    echo "<h2>Error: Unauthorized</h2>";
    echo "<p>You are not authorized to view this project.</p>";
    exit();
}

// Get panelist grades
$gradesQuery = "SELECT * FROM panelist_grades WHERE defense_type = ? AND defense_id = ?";
$gradesStmt = $conn->prepare($gradesQuery);
$gradesStmt->bind_param("si", $defenseType, $defenseId);
$gradesStmt->execute();
$gradesResult = $gradesStmt->get_result();
$grades = [];
while ($grade = $gradesResult->fetch_assoc()) {
    $grades[] = $grade;
}
$gradesStmt->close();

echo "<h2>Panelist Grades</h2>";
echo "<p>Found " . count($grades) . " grades</p>";

foreach ($grades as $grade) {
    echo "<p>Panelist ID: " . $grade['panelist_id'] . ", Student ID: " . $grade['student_id'] . ", Grade: " . $grade['individual_grade'] . "</p>";
}

$conn->close();
?>
