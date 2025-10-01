<?php
// Start the session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // If not logged in, redirect to the login page
    header("Location: ../users/login.php");
    exit(); // Stop further execution
}

include '../config/database.php';
include '../assets/includes/notification_functions.php';
require_once '../assets/includes/role_functions.php';


$email = $_SESSION['email'];

// Fetch user role
$userQuery = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
$userQuery->bind_param("s", $email);
$userQuery->execute();
$userResult = $userQuery->get_result();
$user = $userResult->fetch_assoc();
$userQuery->close();

// Authorize as panelist using multi-role support
if (!isset($_SESSION['user_data']) || !hasRole($_SESSION['user_data'], 'panelist')) {
    header("Location: ../users/login.php?error=unauthorized_access");
    exit();
}

$panelistId = $user['id'];

// Get title defense ID from URL
if (!isset($_GET['id'])) {
    header("Location: title_defense_list.php?error=missing_id");
    exit();
}
$titleDefenseId = $_GET['id'];

// Fetch title defense details with group_code and project details
$query = "
    SELECT td.*, pw.project_title, pw.proponent_1, pw.proponent_2, pw.proponent_3, pw.proponent_4, s.group_code
    FROM title_defense td
    INNER JOIN project_working_titles pw ON td.project_id = pw.id
    INNER JOIN users u ON td.submitted_by = u.id
    LEFT JOIN students s ON u.id = s.user_id
    WHERE td.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $titleDefenseId);
$stmt->execute();
$result = $stmt->get_result();
$titleDefense = $result->fetch_assoc();
$stmt->close();

if (!$titleDefense) {
    header("Location: title_defense_list.php?error=invalid_id");
    exit();
}

$projectId = $titleDefense['project_id'];
$groupCode = isset($titleDefense['group_code']) ? $titleDefense['group_code'] : 'N/A';

// Get all students in the same group
$studentsQuery = "
    SELECT u.id, u.first_name, u.last_name, u.email
    FROM users u
    INNER JOIN students s ON u.id = s.user_id
    WHERE s.group_code = ?
    ORDER BY u.first_name, u.last_name";
$studentsStmt = $conn->prepare($studentsQuery);
$studentsStmt->bind_param("s", $groupCode);
$studentsStmt->execute();
$studentsResult = $studentsStmt->get_result();
$students = [];
while ($student = $studentsResult->fetch_assoc()) {
    $students[] = $student;
}
$studentsStmt->close();

// Get existing grades for this panelist and defense
$existingGradesQuery = "
    SELECT student_id, individual_grade, remarks
    FROM panelist_grades
    WHERE panelist_id = ? AND defense_type = 'title' AND defense_id = ?";
$gradesStmt = $conn->prepare($existingGradesQuery);
$gradesStmt->bind_param("ii", $panelistId, $titleDefenseId);
$gradesStmt->execute();
$gradesResult = $gradesStmt->get_result();
$existingGrades = [];
while ($grade = $gradesResult->fetch_assoc()) {
    $existingGrades[$grade['student_id']] = $grade;
}
$gradesStmt->close();

// Get existing group grade for this panelist and defense
$existingGroupGradeQuery = "
    SELECT group_grade, group_remarks
    FROM panelist_group_grades
    WHERE panelist_id = ? AND defense_type = 'title' AND defense_id = ?";
$groupGradeStmt = $conn->prepare($existingGroupGradeQuery);
$groupGradeStmt->bind_param("ii", $panelistId, $titleDefenseId);
$groupGradeStmt->execute();
$groupGradeResult = $groupGradeStmt->get_result();
$existingGroupGrade = $groupGradeResult->fetch_assoc();
$groupGradeStmt->close();

// Check if grades have already been submitted
$hasGrades = !empty($existingGrades) || !empty($existingGroupGrade);

// Handle POST request for grading
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupGrade = isset($_POST['group_grade']) ? floatval($_POST['group_grade']) : null;
    $groupRemarks = isset($_POST['group_remarks']) ? trim($_POST['group_remarks']) : '';
    
    // Validate group grade
    if ($groupGrade !== null && ($groupGrade < 0 || $groupGrade > 100)) {
        $error = 'Group grade must be between 0 and 100.';
    }
    
    // Process individual student grades
    $individualGrades = [];
    foreach ($students as $student) {
        $studentId = $student['id'];
        $grade = isset($_POST['grade_' . $studentId]) ? floatval($_POST['grade_' . $studentId]) : null;
        $remarks = isset($_POST['remarks_' . $studentId]) ? trim($_POST['remarks_' . $studentId]) : '';
        
        if ($grade !== null && ($grade < 0 || $grade > 100)) {
            $error = 'Individual grades must be between 0 and 100.';
            break;
        }
        
        if ($grade !== null) {
            $individualGrades[] = [
                'student_id' => $studentId,
                'grade' => $grade,
                'remarks' => $remarks
            ];
        }
    }
    
    if (!isset($error)) {
        $conn->begin_transaction();
        
        try {
            // Insert or update individual grades
            foreach ($individualGrades as $gradeData) {
                $insertGradeQuery = "
                    INSERT INTO panelist_grades (panelist_id, student_id, defense_type, defense_id, project_id, individual_grade, remarks)
                    VALUES (?, ?, 'title', ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    individual_grade = VALUES(individual_grade),
                    remarks = VALUES(remarks),
                    updated_at = CURRENT_TIMESTAMP";
                
                $gradeStmt = $conn->prepare($insertGradeQuery);
                $gradeStmt->bind_param("iiiids", $panelistId, $gradeData['student_id'], $titleDefenseId, $projectId, $gradeData['grade'], $gradeData['remarks']);
                $gradeStmt->execute();
                $gradeStmt->close();
            }
            
            // Insert or update group grade
            if ($groupGrade !== null) {
                $insertGroupGradeQuery = "
                    INSERT INTO panelist_group_grades (panelist_id, group_code, defense_type, defense_id, project_id, group_grade, group_remarks)
                    VALUES (?, ?, 'title', ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    group_grade = VALUES(group_grade),
                    group_remarks = VALUES(group_remarks),
                    updated_at = CURRENT_TIMESTAMP";
                
                $groupGradeStmt = $conn->prepare($insertGroupGradeQuery);
                $groupGradeStmt->bind_param("isiids", $panelistId, $groupCode, $titleDefenseId, $projectId, $groupGrade, $groupRemarks);
                $groupGradeStmt->execute();
                $groupGradeStmt->close();
            }
            
            $conn->commit();
            
            // Send notification to adviser about grades submitted
            $adviserQuery = "
                SELECT u.id as adviser_id
                FROM project_working_titles pw
                INNER JOIN users u ON pw.noted_by = u.id
                WHERE pw.id = ?";
            $adviserStmt = $conn->prepare($adviserQuery);
            $adviserStmt->bind_param("i", $projectId);
            $adviserStmt->execute();
            $adviserResult = $adviserStmt->get_result();
            
            if ($adviserData = $adviserResult->fetch_assoc()) {
                createNotification(
                    $conn,
                    $adviserData['adviser_id'],
                    'Panelist Grades Submitted',
                    'Panelist grades have been submitted for ' . htmlspecialchars($titleDefense['project_title']) . ' (Group: ' . htmlspecialchars($groupCode) . ').',
                    'info',
                    $projectId,
                    'title_defense'
                );
            }
            $adviserStmt->close();
            
            $successMessage = $hasGrades ? 'grades_updated' : 'grades_submitted';
            header("Location: title_defense_list.php?success=$successMessage");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to save grades. Please try again.";
        }
    }
}

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
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <?php include '../assets/includes/navbar.php'; ?>
    <div class="container">
        <a href="title_defense_list.php" class="back-button-creative">
            <i class="bi bi-arrow-left-circle" style="font-size: 1.2rem;"></i>
            Back to List
        </a>
        <h4>Grade Title Defense</h4>
        <p><strong>Project Title:</strong> <?php echo htmlspecialchars($titleDefense['project_title']); ?></p>
        <p><strong>Group Code:</strong> <?php echo htmlspecialchars($groupCode); ?></p>
        <p><strong>Project Proponents:</strong></p>
        <ul>
            <?php if (!empty($titleDefense['proponent_1'])): ?>
                <li><?php echo htmlspecialchars($titleDefense['proponent_1']); ?></li>
            <?php endif; ?>
            <?php if (!empty($titleDefense['proponent_2'])): ?>
                <li><?php echo htmlspecialchars($titleDefense['proponent_2']); ?></li>
            <?php endif; ?>
            <?php if (!empty($titleDefense['proponent_3'])): ?>
                <li><?php echo htmlspecialchars($titleDefense['proponent_3']); ?></li>
            <?php endif; ?>
            <?php if (!empty($titleDefense['proponent_4'])): ?>
                <li><?php echo htmlspecialchars($titleDefense['proponent_4']); ?></li>
            <?php endif; ?>
        </ul>

        <p><strong>Uploaded File:</strong>
        <a href="<?php echo htmlspecialchars($titleDefense['pdf_file']); ?>" target="_blank">View Title Defense PDF</a></p>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" id="gradingForm">
            <!-- Group Grade Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-people-fill"></i> Group Grade</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="group_grade" class="form-label">Group Grade (0-100):</label>
                            <input type="number" name="group_grade" id="group_grade" class="form-control" 
                                   min="0" max="100" step="0.01" 
                                   value="<?php echo isset($existingGroupGrade['group_grade']) ? $existingGroupGrade['group_grade'] : ''; ?>"
                                   <?php echo $hasGrades ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <label for="group_remarks" class="form-label">Group Remarks:</label>
                            <textarea name="group_remarks" id="group_remarks" class="form-control" rows="3" 
                                      placeholder="Add your remarks for the group performance..." <?php echo $hasGrades ? 'disabled' : ''; ?>><?php echo isset($existingGroupGrade['group_remarks']) ? htmlspecialchars($existingGroupGrade['group_remarks']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Individual Student Grades Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-person-fill"></i> Individual Student Grades</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($students)): ?>
                        <p class="text-muted">No students found for this group.</p>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <div class="row mb-3 border-bottom pb-3">
                                <div class="col-md-4">
                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                </div>
                                <div class="col-md-3">
                                    <label for="grade_<?php echo $student['id']; ?>" class="form-label">Grade (0-100):</label>
                                    <input type="number" name="grade_<?php echo $student['id']; ?>" 
                                           id="grade_<?php echo $student['id']; ?>" class="form-control" 
                                           min="0" max="100" step="0.01"
                                           value="<?php echo isset($existingGrades[$student['id']]['individual_grade']) ? $existingGrades[$student['id']]['individual_grade'] : ''; ?>"
                                           <?php echo $hasGrades ? 'disabled' : ''; ?>>
                                </div>
                                <div class="col-md-5">
                                    <label for="remarks_<?php echo $student['id']; ?>" class="form-label">Remarks:</label>
                                    <textarea name="remarks_<?php echo $student['id']; ?>" 
                                              id="remarks_<?php echo $student['id']; ?>" class="form-control" rows="2" 
                                              placeholder="Add individual remarks..." <?php echo $hasGrades ? 'disabled' : ''; ?>><?php echo isset($existingGrades[$student['id']]['remarks']) ? htmlspecialchars($existingGrades[$student['id']]['remarks']) : ''; ?></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="button-group">
                <?php if ($hasGrades): ?>
                    <button type="button" class="btn btn-primary edit-button-creative" onclick="enableEditing()">
                        <i class="bi bi-pencil-square" style="font-size: 1.2rem;"></i>
                        Edit Grades
                    </button>
                    <button type="submit" class="btn btn-success submit-button-creative" style="display: none;">
                        <i class="bi bi-check-circle" style="font-size: 1.2rem;"></i>
                        Update Grades
                    </button>
                    <button type="button" class="btn btn-secondary cancel-button-creative" style="display: none;" onclick="disableEditing()">
                        <i class="bi bi-x-circle" style="font-size: 1.2rem;"></i>
                        Cancel
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn btn-success submit-button-creative">
                        <i class="bi bi-check-circle" style="font-size: 1.2rem;"></i>
                        Submit Grades
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='title_defense_list.php'">
                        <i class="bi bi-x-circle" style="font-size: 1.2rem;"></i>
                        Cancel
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
function enableEditing() {
    // Enable all form inputs
    document.getElementById('group_grade').disabled = false;
    document.getElementById('group_remarks').disabled = false;
    
    // Enable all individual grade inputs
    const gradeInputs = document.querySelectorAll('input[type="number"][name^="grade_"]');
    gradeInputs.forEach(input => {
        input.disabled = false;
    });
    
    // Enable all individual remarks
    const remarksInputs = document.querySelectorAll('textarea[name^="remarks_"]');
    remarksInputs.forEach(input => {
        input.disabled = false;
    });
    
    // Show/hide buttons
    document.querySelector('.edit-button-creative').style.display = 'none';
    document.querySelector('.submit-button-creative').style.display = 'inline-block';
    document.querySelector('.cancel-button-creative').style.display = 'inline-block';
}

function disableEditing() {
    // Disable all form inputs
    document.getElementById('group_grade').disabled = true;
    document.getElementById('group_remarks').disabled = true;
    
    // Disable all individual grade inputs
    const gradeInputs = document.querySelectorAll('input[type="number"][name^="grade_"]');
    gradeInputs.forEach(input => {
        input.disabled = true;
    });
    
    // Disable all individual remarks
    const remarksInputs = document.querySelectorAll('textarea[name^="remarks_"]');
    remarksInputs.forEach(input => {
        input.disabled = true;
    });
    
    // Show/hide buttons
    document.querySelector('.edit-button-creative').style.display = 'inline-block';
    document.querySelector('.submit-button-creative').style.display = 'none';
    document.querySelector('.cancel-button-creative').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('gradingForm');
    const groupGradeInput = document.getElementById('group_grade');
    
    // Clamp numeric inputs to 0-100 and block invalid characters
    const clampToRange = (input) => {
        if (input.value === '') return;
        let val = parseFloat(input.value);
        if (isNaN(val)) {
            input.value = '';
            return;
        }
        if (val > 100) val = 100;
        if (val < 0) val = 0;
        input.value = val;
    };
    
    const numberInputs = [
        groupGradeInput,
        ...document.querySelectorAll('input[type="number"][name^="grade_"]')
    ];
    
    numberInputs.forEach(input => {
        if (!input) return;
        input.addEventListener('input', () => clampToRange(input));
        input.addEventListener('change', () => clampToRange(input));
        input.addEventListener('blur', () => clampToRange(input));
        input.addEventListener('keydown', (e) => {
            // Prevent scientific notation and +/-
            if (['e','E','+','-'].includes(e.key)) {
                e.preventDefault();
            }
        });
    });
    
    form.addEventListener('submit', function(e) {
        let hasGrades = false;
        
        // Check if group grade is provided
        if (groupGradeInput.value && groupGradeInput.value.trim() !== '') {
            hasGrades = true;
        }
        
        // Check if any individual grades are provided
        const gradeInputs = document.querySelectorAll('input[type="number"][name^="grade_"]');
        gradeInputs.forEach(input => {
            if (input.value && input.value.trim() !== '') {
                hasGrades = true;
            }
        });
        
        if (!hasGrades) {
            e.preventDefault();
            alert('Please provide at least one grade (group grade or individual grades).');
            return false;
        }
        
        // Confirm submission
        if (!confirm('Are you sure you want to submit these grades? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>
</body>
</html>