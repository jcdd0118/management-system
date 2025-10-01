<?php
session_start();
include '../config/database.php';

// Get defense type and ID from URL
$defenseType = isset($_GET['type']) ? $_GET['type'] : 'title';
$defenseId = isset($_GET['id']) ? $_GET['id'] : null;

echo "<h2>Debug Remarks</h2>";
echo "<p>Defense Type: " . htmlspecialchars($defenseType) . "</p>";
echo "<p>Defense ID: " . htmlspecialchars($defenseId) . "</p>";

if (!$defenseId) {
    echo "<p style='color: red;'>No defense ID provided</p>";
    exit();
}

// Get all panelist grades with full details
$gradesQuery = "
    SELECT pg.*, u.first_name, u.last_name
    FROM panelist_grades pg
    INNER JOIN users u ON pg.panelist_id = u.id
    WHERE pg.defense_type = ? AND pg.defense_id = ?
    ORDER BY u.first_name, u.last_name";
$gradesStmt = $conn->prepare($gradesQuery);
$gradesStmt->bind_param("si", $defenseType, $defenseId);
$gradesStmt->execute();
$gradesResult = $gradesStmt->get_result();

echo "<h3>Individual Grades:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Panelist</th><th>Student ID</th><th>Grade</th><th>Remarks</th><th>Remarks Length</th></tr>";

while ($grade = $gradesResult->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($grade['first_name'] . ' ' . $grade['last_name']) . "</td>";
    echo "<td>" . $grade['student_id'] . "</td>";
    echo "<td>" . $grade['individual_grade'] . "</td>";
    echo "<td>" . htmlspecialchars($grade['remarks']) . "</td>";
    echo "<td>" . strlen($grade['remarks']) . "</td>";
    echo "</tr>";
}
echo "</table>";

$gradesStmt->close();

// Get all panelist group grades
$groupGradesQuery = "
    SELECT pgg.*, u.first_name, u.last_name
    FROM panelist_group_grades pgg
    INNER JOIN users u ON pgg.panelist_id = u.id
    WHERE pgg.defense_type = ? AND pgg.defense_id = ?
    ORDER BY u.first_name, u.last_name";
$groupGradesStmt = $conn->prepare($groupGradesQuery);
$groupGradesStmt->bind_param("si", $defenseType, $defenseId);
$groupGradesStmt->execute();
$groupGradesResult = $groupGradesStmt->get_result();

echo "<h3>Group Grades:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Panelist</th><th>Group Code</th><th>Grade</th><th>Remarks</th><th>Remarks Length</th></tr>";

while ($groupGrade = $groupGradesResult->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($groupGrade['first_name'] . ' ' . $groupGrade['last_name']) . "</td>";
    echo "<td>" . htmlspecialchars($groupGrade['group_code']) . "</td>";
    echo "<td>" . $groupGrade['group_grade'] . "</td>";
    echo "<td>" . htmlspecialchars($groupGrade['group_remarks']) . "</td>";
    echo "<td>" . strlen($groupGrade['group_remarks']) . "</td>";
    echo "</tr>";
}
echo "</table>";

$groupGradesStmt->close();

$conn->close();
?>
