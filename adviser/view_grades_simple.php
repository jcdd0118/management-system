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
    header("Location: " . ($defenseType === 'final' ? 'final_defense_list.php' : 'title_defense_list.php') . "?error=invalid_id");
    exit();
}

$projectId = $defense['project_id'];

// Resolve group code from the student who submitted this defense (more reliable)
$groupCode = '';
$groupCodeQuery = "
    SELECT s.group_code
    FROM {$defenseTable} d
    INNER JOIN users u ON d.submitted_by = u.id
    LEFT JOIN students s ON u.id = s.user_id
    WHERE d.id = ?
";
$gcStmt = $conn->prepare($groupCodeQuery);
if ($gcStmt) {
    $gcStmt->bind_param("i", $defenseId);
    $gcStmt->execute();
    $gcRes = $gcStmt->get_result();
    if ($gcRow = $gcRes->fetch_assoc()) {
        $groupCode = isset($gcRow['group_code']) ? $gcRow['group_code'] : '';
    }
    $gcStmt->close();
}

// Get project info
$projectQuery = "SELECT * FROM project_working_titles WHERE id = ?";
$projectStmt = $conn->prepare($projectQuery);
$projectStmt->bind_param("i", $projectId);
$projectStmt->execute();
$projectResult = $projectStmt->get_result();
$project = $projectResult->fetch_assoc();
$projectStmt->close();

if (!$project) {
    echo "<h2>Error: Project not found</h2>";
    exit();
}

// Get all students in the group
$studentsQuery = "
    SELECT u.id, u.first_name, u.last_name, u.email
    FROM users u
    INNER JOIN students s ON u.id = s.user_id
    WHERE s.group_code = ?
    ORDER BY u.first_name, u.last_name";
$studentsStmt = $conn->prepare($studentsQuery);
// Prefer group code resolved from defense submitter; fallback to project table
$effectiveGroupCode = !empty($groupCode) ? $groupCode : (isset($project['group_code']) ? $project['group_code'] : '');
$studentsStmt->bind_param("s", $effectiveGroupCode);
$studentsStmt->execute();
$studentsResult = $studentsStmt->get_result();
$students = [];
while ($student = $studentsResult->fetch_assoc()) {
    $students[] = $student;
}
$studentsStmt->close();

// Get all panelist grades for this defense
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
$panelistGrades = [];
while ($grade = $gradesResult->fetch_assoc()) {
    $panelistGrades[$grade['panelist_id']][$grade['student_id']] = $grade;
}
$gradesStmt->close();

// Get all panelist group grades for this defense
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
$panelistGroupGrades = [];
while ($groupGrade = $groupGradesResult->fetch_assoc()) {
    $panelistGroupGrades[$groupGrade['panelist_id']] = $groupGrade;
}
$groupGradesStmt->close();

// Get all panelists who have graded this defense
$panelistsQuery = "
    SELECT DISTINCT u.id, u.first_name, u.last_name
    FROM panelist_grades pg
    INNER JOIN users u ON pg.panelist_id = u.id
    WHERE pg.defense_type = ? AND pg.defense_id = ?
    UNION
    SELECT DISTINCT u.id, u.first_name, u.last_name
    FROM panelist_group_grades pgg
    INNER JOIN users u ON pgg.panelist_id = u.id
    WHERE pgg.defense_type = ? AND pgg.defense_id = ?
    ORDER BY first_name, last_name";
$panelistsStmt = $conn->prepare($panelistsQuery);
$panelistsStmt->bind_param("sisi", $defenseType, $defenseId, $defenseType, $defenseId);
$panelistsStmt->execute();
$panelistsResult = $panelistsStmt->get_result();
$panelists = [];
while ($panelist = $panelistsResult->fetch_assoc()) {
    $panelists[] = $panelist;
}
$panelistsStmt->close();

// Debug: Let's see what we have
echo "<!-- DEBUG INFO -->";
echo "<!-- Students count: " . count($students) . " -->";
echo "<!-- Panelists count: " . count($panelists) . " -->";
echo "<!-- Individual grades count: " . count($panelistGrades) . " -->";
echo "<!-- Group grades count: " . count($panelistGroupGrades) . " -->";

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Captrack Vault Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <link href="../assets/css/project.css" rel="stylesheet">
    <style>
        .grades-table {
            font-size: 0.9rem;
        }
        .grades-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .panelist-name {
            font-weight: 600;
            color: #495057;
        }
        .student-name {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .grade-value {
            font-weight: 600;
            font-size: 1.1rem;
        }
        .remarks-text {
            font-size: 0.8rem;
            max-width: 200px;
            word-wrap: break-word;
        }
        .no-grades {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
    </style>
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <?php include '../assets/includes/navbar.php'; ?>
    <div class="container">
        <a href="<?php echo $defenseType === 'final' ? 'final_defense_list.php' : 'title_defense_list.php'; ?>" class="back-button-creative">
            <i class="bi bi-arrow-left-circle" style="font-size: 1.2rem;"></i>
            Back to List
        </a>
        
        <h4>Panelist Grades - <?php echo ucfirst($defenseType); ?> Defense</h4>
        
        <!-- Project Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-info-circle"></i> Project Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <p><strong>Project Title:</strong> <?php echo htmlspecialchars($project['project_title']); ?></p>
                        <p><strong>Group Code:</strong> <?php echo htmlspecialchars($effectiveGroupCode); ?></p>
                        <p><strong>Project Proponents:</strong></p>
                        <ul>
                            <?php if (!empty($project['proponent_1'])): ?>
                                <li><?php echo htmlspecialchars($project['proponent_1']); ?></li>
                            <?php endif; ?>
                            <?php if (!empty($project['proponent_2'])): ?>
                                <li><?php echo htmlspecialchars($project['proponent_2']); ?></li>
                            <?php endif; ?>
                            <?php if (!empty($project['proponent_3'])): ?>
                                <li><?php echo htmlspecialchars($project['proponent_3']); ?></li>
                            <?php endif; ?>
                            <?php if (!empty($project['proponent_4'])): ?>
                                <li><?php echo htmlspecialchars($project['proponent_4']); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?php echo $defense['status'] === 'approved' ? 'success' : ($defense['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                <?php echo ucfirst($defense['status']); ?>
                            </span>
                        </p>
                        <?php if ($defense['scheduled_date']): ?>
                            <p><strong>Scheduled:</strong><br>
                            <?php echo date("F d, Y h:i A", strtotime($defense['scheduled_date'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($panelists)): ?>
            <div class="card">
                <div class="card-body no-grades">
                    <i class="bi bi-clipboard-x" style="font-size: 3rem; color: #dee2e6;"></i>
                    <h5 class="mt-3">No Grades Submitted Yet</h5>
                    <p>Panelists have not submitted their grades for this <?php echo $defenseType; ?> defense.</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Panelist Grades -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-people-fill"></i> Panelist Grades</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered grades-table">
                            <thead>
                                <tr>
                                    <th>Panelist</th>
                                    <th class="text-center">Group Grade</th>
                                    <th class="text-center">Group Remarks</th>
                                    <?php foreach ($students as $student): ?>
                                        <th class="text-center"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($panelists as $panelist): ?>
                                    <tr>
                                        <td class="panelist-name">
                                            <?php echo htmlspecialchars($panelist['first_name'] . ' ' . $panelist['last_name']); ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if (isset($panelistGroupGrades[$panelist['id']])): ?>
                                                <span class="grade-value text-primary">
                                                    <?php echo number_format($panelistGroupGrades[$panelist['id']]['group_grade'], 2); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="remarks-text">
                                            <?php if (isset($panelistGroupGrades[$panelist['id']]) && !empty($panelistGroupGrades[$panelist['id']]['group_remarks'])): ?>
                                                <?php echo htmlspecialchars($panelistGroupGrades[$panelist['id']]['group_remarks']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php foreach ($students as $student): ?>
                                            <td class="text-center">
                                                <?php 
                                                // Debug: Check if data exists
                                                $hasGrade = isset($panelistGrades[$panelist['id']][$student['id']]);
                                                if ($hasGrade) {
                                                    $gradeData = $panelistGrades[$panelist['id']][$student['id']];
                                                }
                                                ?>
                                                <?php if ($hasGrade): ?>
                                                    <div>
                                                        <span class="grade-value text-success">
                                                            <?php echo number_format($gradeData['individual_grade'], 2); ?>
                                                        </span>
                                                    </div>
                                                    <?php if (!empty($gradeData['remarks'])): ?>
                                                        <div class="student-name mt-1">
                                                            <small><?php echo htmlspecialchars($gradeData['remarks']); ?></small>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="student-name mt-1">
                                                            <small class="text-muted">No remarks</small>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                    <!-- Debug: Panelist ID: <?php echo $panelist['id']; ?>, Student ID: <?php echo $student['id']; ?> -->
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-graph-up"></i> Grade Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Group Grade Averages</h6>
                            <?php if (!empty($panelistGroupGrades)): ?>
                                <?php
                                $groupGrades = array_column($panelistGroupGrades, 'group_grade');
                                $avgGroupGrade = array_sum($groupGrades) / count($groupGrades);
                                ?>
                                <p><strong>Average:</strong> <?php echo number_format($avgGroupGrade, 2); ?></p>
                                <p><strong>Range:</strong> <?php echo number_format(min($groupGrades), 2); ?> - <?php echo number_format(max($groupGrades), 2); ?></p>
                            <?php else: ?>
                                <p class="text-muted">No group grades available</p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6>Individual Grade Averages</h6>
                            <?php foreach ($students as $student): ?>
                                <?php
                                $studentGrades = [];
                                foreach ($panelistGrades as $panelistId => $grades) {
                                    if (isset($grades[$student['id']])) {
                                        $studentGrades[] = $grades[$student['id']]['individual_grade'];
                                    }
                                }
                                if (!empty($studentGrades)) {
                                    $avgGrade = array_sum($studentGrades) / count($studentGrades);
                                    echo '<p><strong>' . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . ':</strong> ' . number_format($avgGrade, 2) . '</p>';
                                } else {
                                    echo '<p><strong>' . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . ':</strong> <span class="text-muted">No grades</span></p>';
                                }
                                ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
</body>
</html>
