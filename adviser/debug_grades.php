<?php
session_start();
include '../config/database.php';

// Get defense type and ID from URL
$defenseType = isset($_GET['type']) ? $_GET['type'] : 'title';
$defenseId = isset($_GET['id']) ? $_GET['id'] : null;

echo "<h2>Debug Information</h2>";
echo "<p>Defense Type: " . htmlspecialchars($defenseType) . "</p>";
echo "<p>Defense ID: " . htmlspecialchars($defenseId) . "</p>";

if (!$defenseId) {
    echo "<p style='color: red;'>No defense ID provided</p>";
    exit();
}

$defenseTable = $defenseType === 'final' ? 'final_defense' : 'title_defense';
echo "<p>Defense Table: " . htmlspecialchars($defenseTable) . "</p>";

// Check if defense exists
$query = "SELECT * FROM $defenseTable WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $defenseId);
$stmt->execute();
$result = $stmt->get_result();
$defense = $result->fetch_assoc();
$stmt->close();

if (!$defense) {
    echo "<p style='color: red;'>Defense not found in $defenseTable table</p>";
    exit();
}

echo "<p style='color: green;'>Defense found!</p>";
echo "<p>Project ID: " . $defense['project_id'] . "</p>";

// Check if project exists
$projectQuery = "SELECT * FROM project_working_titles WHERE id = ?";
$projectStmt = $conn->prepare($projectQuery);
$projectStmt->bind_param("i", $defense['project_id']);
$projectStmt->execute();
$projectResult = $projectStmt->get_result();
$project = $projectResult->fetch_assoc();
$projectStmt->close();

if (!$project) {
    echo "<p style='color: red;'>Project not found</p>";
    exit();
}

echo "<p style='color: green;'>Project found!</p>";
echo "<p>Project Title: " . htmlspecialchars($project['project_title']) . "</p>";
echo "<p>Noted By: " . $project['noted_by'] . "</p>";

// Check if there are any panelist grades
$gradesQuery = "SELECT COUNT(*) as count FROM panelist_grades WHERE defense_type = ? AND defense_id = ?";
$gradesStmt = $conn->prepare($gradesQuery);
$gradesStmt->bind_param("si", $defenseType, $defenseId);
$gradesStmt->execute();
$gradesResult = $gradesStmt->get_result();
$gradesCount = $gradesResult->fetch_assoc();
$gradesStmt->close();

echo "<p>Panelist Grades Count: " . $gradesCount['count'] . "</p>";

$conn->close();
?>
