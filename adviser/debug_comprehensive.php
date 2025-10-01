<?php
session_start();
include '../config/database.php';

// Get defense type and ID from URL
$defenseType = isset($_GET['type']) ? $_GET['type'] : 'title';
$defenseId = isset($_GET['id']) ? $_GET['id'] : null;

echo "<h2>Comprehensive Debug</h2>";
echo "<p>Defense Type: " . htmlspecialchars($defenseType) . "</p>";
echo "<p>Defense ID: " . htmlspecialchars($defenseId) . "</p>";

if (!$defenseId) {
    echo "<p style='color: red;'>No defense ID provided</p>";
    exit();
}

// Check if defense exists
$defenseTable = $defenseType === 'final' ? 'final_defense' : 'title_defense';
$query = "SELECT * FROM $defenseTable WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $defenseId);
$stmt->execute();
$result = $stmt->get_result();
$defense = $result->fetch_assoc();
$stmt->close();

if (!$defense) {
    echo "<p style='color: red;'>Defense not found</p>";
    exit();
}

echo "<h3>Defense Found:</h3>";
echo "<p>Project ID: " . $defense['project_id'] . "</p>";

// Get project info
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

echo "<h3>Project Found:</h3>";
echo "<p>Group Code: " . htmlspecialchars($project['group_code']) . "</p>";

// Get students in the group
$studentsQuery = "
    SELECT u.id, u.first_name, u.last_name, u.email
    FROM users u
    INNER JOIN students s ON u.id = s.user_id
    WHERE s.group_code = ?
    ORDER BY u.first_name, u.last_name";
$studentsStmt = $conn->prepare($studentsQuery);
$studentsStmt->bind_param("s", $project['group_code']);
$studentsStmt->execute();
$studentsResult = $studentsStmt->get_result();
$students = [];
while ($student = $studentsResult->fetch_assoc()) {
    $students[] = $student;
}
$studentsStmt->close();

echo "<h3>Students in Group:</h3>";
echo "<ul>";
foreach ($students as $student) {
    echo "<li>ID: " . $student['id'] . " - " . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . "</li>";
}
echo "</ul>";

// Get all panelist grades
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
echo "<tr><th>Panelist</th><th>Student ID</th><th>Student Name</th><th>Grade</th><th>Remarks</th></tr>";

$panelistGrades = [];
while ($grade = $gradesResult->fetch_assoc()) {
    $panelistGrades[$grade['panelist_id']][$grade['student_id']] = $grade;
    
    // Find student name
    $studentName = "Unknown";
    foreach ($students as $student) {
        if ($student['id'] == $grade['student_id']) {
            $studentName = $student['first_name'] . ' ' . $student['last_name'];
            break;
        }
    }
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($grade['first_name'] . ' ' . $grade['last_name']) . "</td>";
    echo "<td>" . $grade['student_id'] . "</td>";
    echo "<td>" . htmlspecialchars($studentName) . "</td>";
    echo "<td>" . $grade['individual_grade'] . "</td>";
    echo "<td>" . htmlspecialchars($grade['remarks']) . "</td>";
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
echo "<tr><th>Panelist</th><th>Group Code</th><th>Grade</th><th>Remarks</th></tr>";

$panelistGroupGrades = [];
while ($groupGrade = $groupGradesResult->fetch_assoc()) {
    $panelistGroupGrades[$groupGrade['panelist_id']] = $groupGrade;
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($groupGrade['first_name'] . ' ' . $groupGrade['last_name']) . "</td>";
    echo "<td>" . htmlspecialchars($groupGrade['group_code']) . "</td>";
    echo "<td>" . $groupGrade['group_grade'] . "</td>";
    echo "<td>" . htmlspecialchars($groupGrade['group_remarks']) . "</td>";
    echo "</tr>";
}
echo "</table>";

$groupGradesStmt->close();

// Test the data structure
echo "<h3>Data Structure Test:</h3>";
echo "<h4>Panelist Grades Array:</h4>";
echo "<pre>";
print_r($panelistGrades);
echo "</pre>";

echo "<h4>Panelist Group Grades Array:</h4>";
echo "<pre>";
print_r($panelistGroupGrades);
echo "</pre>";

// Test individual access
echo "<h3>Individual Access Test:</h3>";
foreach ($panelistGrades as $panelistId => $grades) {
    echo "<h4>Panelist ID: $panelistId</h4>";
    foreach ($grades as $studentId => $grade) {
        echo "<p>Student ID: $studentId, Grade: " . $grade['individual_grade'] . ", Remarks: " . htmlspecialchars($grade['remarks']) . "</p>";
    }
}

$conn->close();
?>
