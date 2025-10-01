<?php
// Start the session
session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if the user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../users/login.php");
    exit();
}

include '../config/database.php';

// Define constants
define('MAX_SUBMISSIONS', 3);

// Generate or verify CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}

// Fetch student data (including guide preference)
$email = isset($_SESSION['email']) ? $_SESSION['email'] : $_SESSION['user_id'];
$studentQuery = $conn->prepare("SELECT show_research_guide FROM students WHERE email = ? OR id = ?");
$studentQuery->bind_param("ss", $email, $email);
$studentQuery->execute();
$studentResult = $studentQuery->get_result();
$student = $studentResult->fetch_assoc();
$studentQuery->close();

// Check if student wants to see the guide
$showGuide = true;
if ($student) {
    $showGuide = isset($student['show_research_guide']) ? (bool)$student['show_research_guide'] : true;
}

// Fetch user data for role check
$userQuery = $conn->prepare("SELECT role FROM users WHERE email = ? OR id = ?");
$userQuery->bind_param("ss", $email, $email);
$userQuery->execute();
$userResult = $userQuery->get_result();
$user = $userResult->fetch_assoc();
$userQuery->close();

// Handle AJAX request to update guide preference
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_guide_preference') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit();
    }
    
    $dontShow = isset($_POST['dont_show']) && $_POST['dont_show'] === 'true' ? 0 : 1;
    $updateQuery = $conn->prepare("UPDATE students SET show_research_guide = ? WHERE email = ? OR id = ?");
    $updateQuery->bind_param("iss", $dontShow, $email, $email);
    
    if ($updateQuery->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    $updateQuery->close();
    exit();
}

// Handle AJAX request to get version history
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_version_history') {
    header('Content-Type: application/json');
    
    // Debug: Log the request
    error_log("Version history request received. POST data: " . print_r($_POST, true));
    
    if (!isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF token mismatch");
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit();
    }
    
    $projectId = intval($_POST['project_id']);
    
    // Get the submitted_by value for this request
    $email = isset($_SESSION['email']) ? $_SESSION['email'] : $_SESSION['user_id'];
    $submittedBy = $email;
    
    try {
        error_log("Looking for project ID: $projectId, submitted by: $submittedBy");
        
    // Get all versions of this project (including archived ones), allowing access if the
    // submitter is the current user or someone within the same group_code
    $permQuery = $conn->prepare("
        SELECT pw.submitted_by, pw.project_title
        FROM project_working_titles pw
        WHERE pw.id = ? AND (
            pw.submitted_by = ?
            OR pw.submitted_by IN (
                SELECT email FROM students WHERE group_code = (
                    SELECT group_code FROM students WHERE email = ? LIMIT 1
                )
            )
        )
    ");
    if (!$permQuery) {
        throw new Exception("Database error: " . $conn->error);
    }
    $permQuery->bind_param("iss", $projectId, $submittedBy, $submittedBy);
    $permQuery->execute();
    $permResult = $permQuery->get_result();
    
    $versions = [];
    if ($permRow = $permResult->fetch_assoc()) {
        $projectTitle = $permRow['project_title'];
        $submitterEmail = $permRow['submitted_by'];
        
        $historyQuery = $conn->prepare("
            SELECT pw.*, pa.faculty_approval, pa.faculty_comments, pa.adviser_approval, pa.adviser_comments, pa.dean_approval, pa.dean_comments 
            FROM project_working_titles pw
            LEFT JOIN project_approvals pa ON pw.id = pa.project_id
            WHERE pw.project_title = ?
              AND pw.submitted_by IN (
                SELECT email FROM students WHERE group_code = (
                    SELECT group_code FROM students WHERE email = ? LIMIT 1
                )
              )
            ORDER BY pw.version ASC
        ");
        if (!$historyQuery) {
            throw new Exception("Database error: " . $conn->error);
        }
        $historyQuery->bind_param("ss", $projectTitle, $submittedBy);
        $historyQuery->execute();
        $historyResult = $historyQuery->get_result();
        while ($row = $historyResult->fetch_assoc()) {
            $versions[] = $row;
        }
        $historyQuery->close();
    }
    $permQuery->close();
        
        echo json_encode(['success' => true, 'versions' => $versions, 'debug' => ['projectId' => $projectId, 'submittedBy' => $submittedBy, 'count' => count($versions)]]);
        
    } catch (Exception $e) {
        error_log("Exception in version history: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}


// Check if the user is an admin
if ($user['role'] === 'admin') {
    session_destroy();
    header("Location: ../users/login.php?error=admin_access_denied");
    exit();
}

// Fetch faculty names for display only
$facultyNames = [];
// Support multi-role users: include primary faculty or users whose roles list includes 'faculty'
$hasRolesColumn = false;
$rolesColCheck = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'roles'");
if ($rolesColCheck) {
    $row = $rolesColCheck->fetch_assoc();
    $hasRolesColumn = ((int)$row['c']) > 0;
    $rolesColCheck->close();
}

if ($hasRolesColumn) {
    $facultyQuery = "SELECT id, first_name, last_name FROM users WHERE LOWER(role) = 'faculty' OR FIND_IN_SET('faculty', REPLACE(LOWER(COALESCE(roles,'')), ' ', '')) > 0";
} else {
    $facultyQuery = "SELECT id, first_name, last_name FROM users WHERE LOWER(role) = 'faculty'";
}
$facultyResult = $conn->query($facultyQuery);
if (!$facultyResult) {
    error_log("Capstone Adviser query error: " . $conn->error);
    $errorMessage = "Error fetching Capstone Adviser data. Please try again later.";
} else {
    while ($row = $facultyResult->fetch_assoc()) {
        $facultyNames[$row['id']] = $row['first_name'] . ' ' . $row['last_name'];
    }
}

// Check submission count - show only latest (non-archived) versions
$submittedBy = $email;
$query = "SELECT pw.*, pa.faculty_approval, pa.faculty_comments, pa.adviser_approval, pa.adviser_comments, pa.dean_approval, pa.dean_comments 
          FROM project_working_titles pw
          LEFT JOIN project_approvals pa ON pw.id = pa.project_id
          WHERE (
            pw.submitted_by IN (
              SELECT email FROM students WHERE group_code = (
                SELECT group_code FROM students WHERE email = ? LIMIT 1
              )
            )
            OR pw.submitted_by = ?
          )
          AND (pw.archived = 0 OR pw.archived IS NULL)
          ORDER BY pw.id DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $submittedBy, $submittedBy);
$stmt->execute();
$result = $stmt->get_result();

$submissions = [];
while ($row = $result->fetch_assoc()) {
    $submissions[] = $row;
}
$stmt->close();

$isSubmitted = count($submissions) >= MAX_SUBMISSIONS;

// Set the current project ID for progress bar (latest submission)
$currentProjectId = !empty($submissions) ? $submissions[0]['id'] : null;

// Assign faculty names to submissions
foreach ($submissions as &$submission) {
    $submission['faculty_name'] = isset($facultyNames[$submission['noted_by']]) 
        ? $facultyNames[$submission['noted_by']] 
        : "Not Assigned";
}
unset($submission);

// Initialize form variables to retain input values
$proponent1 = '';
$proponent2 = '';
$proponent3 = '';
$proponent4 = '';
$projectTitle = '';
$beneficiary = '';
$focalPerson = '';
$focalPersonGender = '';
$position = '';
$address = '';
$editMode = false;
$editProjectId = null;

// Handle edit mode - load existing data (allow groupmates via group_code)
// Only enter edit mode when explicit edit_id is provided
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $editProjectId = intval($_GET['edit_id']);
    
    // Allow if submitter is current user OR in same group_code
    $editQuery = $conn->prepare("SELECT * FROM project_working_titles WHERE id = ? AND (submitted_by = ? OR submitted_by IN (SELECT email FROM students WHERE group_code = (SELECT group_code FROM students WHERE email = ? LIMIT 1)))");
    $editQuery->bind_param("iss", $editProjectId, $submittedBy, $submittedBy);
    $editQuery->execute();
    $editResult = $editQuery->get_result();
    
    if ($editRow = $editResult->fetch_assoc()) {
        $editMode = true;
        $proponent1 = $editRow['proponent_1'];
        $proponent2 = $editRow['proponent_2'];
        $proponent3 = $editRow['proponent_3'];
        $proponent4 = $editRow['proponent_4'];
        $projectTitle = $editRow['project_title'];
        $beneficiary = $editRow['beneficiary'];
        $focalPerson = $editRow['focal_person'];
        $focalPersonGender = $editRow['gender'];
        $position = $editRow['position'];
        $address = $editRow['address'];
    } else {
        $errorMessage = "Project not found or cannot be edited.";
    }
    $editQuery->close();
}

// Handle edit submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_submission') {
    $projectId = intval($_POST['project_id']);
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = "Invalid CSRF token. Please try again.";
    } else {
        // Allow edit if project submitter is current user or in same group
        $checkQuery = $conn->prepare("SELECT id, version FROM project_working_titles WHERE id = ? AND (submitted_by = ? OR submitted_by IN (SELECT email FROM students WHERE group_code = (SELECT group_code FROM students WHERE email = ? LIMIT 1)))");
        $checkQuery->bind_param("iss", $projectId, $submittedBy, $submittedBy);
        $checkQuery->execute();
        $checkResult = $checkQuery->get_result();
        
        if ($checkResult->num_rows > 0) {
            $projectData = $checkResult->fetch_assoc();
            $currentVersion = isset($projectData['version']) ? $projectData['version'] : 1;
            
            // Check version limit (max 5 versions)
            if ($currentVersion >= 5) {
                $errorMessage = "Maximum version limit reached (5 versions). Cannot create more versions.";
            } else {
                // Redirect to edit form with project ID
                header("Location: " . $_SERVER['PHP_SELF'] . "?edit_id=" . $projectId);
                exit();
            }
        } else {
            $errorMessage = "Project not found or cannot be edited.";
        }
        $checkQuery->close();
    }
}

// Handle delete submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_submission') {
    $projectId = intval($_POST['project_id']);
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = "Invalid CSRF token. Please try again.";
    } else {
        // Allow delete if submitter is current user or in same group and has no adviser yet
        $checkQuery = $conn->prepare("SELECT id FROM project_working_titles WHERE id = ? AND (submitted_by = ? OR submitted_by IN (SELECT email FROM students WHERE group_code = (SELECT group_code FROM students WHERE email = ? LIMIT 1))) AND noted_by IS NULL");
        $checkQuery->bind_param("iss", $projectId, $submittedBy, $submittedBy);
        $checkQuery->execute();
        $checkResult = $checkQuery->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Delete from project_approvals first (due to foreign key)
            $deleteApprovalQuery = $conn->prepare("DELETE FROM project_approvals WHERE project_id = ?");
            $deleteApprovalQuery->bind_param("i", $projectId);
            $deleteApprovalQuery->execute();
            $deleteApprovalQuery->close();
            
            // Delete from project_working_titles
            $deleteQuery = $conn->prepare("DELETE FROM project_working_titles WHERE id = ?");
            $deleteQuery->bind_param("i", $projectId);
            
            if ($deleteQuery->execute()) {
                $_SESSION['success_message'] = "Project deleted successfully.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $errorMessage = "Error deleting the project. Please try again.";
            }
            $deleteQuery->close();
        } else {
            $errorMessage = "Project not found or cannot be deleted.";
        }
        $checkQuery->close();
    }
}

// Handle update submission (edit form submission) - Create new version
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_submission') {
    $projectId = intval($_POST['project_id']);
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = "Invalid CSRF token. Please try again.";
    } else {
        // Allow update if project submitter is current user or in same group
        $checkQuery = $conn->prepare("SELECT id, version, noted_by FROM project_working_titles WHERE id = ? AND (submitted_by = ? OR submitted_by IN (SELECT email FROM students WHERE group_code = (SELECT group_code FROM students WHERE email = ? LIMIT 1)))");
        $checkQuery->bind_param("iss", $projectId, $submittedBy, $submittedBy);
        $checkQuery->execute();
        $checkResult = $checkQuery->get_result();
        
        if ($checkResult->num_rows > 0) {
            $projectData = $checkResult->fetch_assoc();
            $currentVersion = isset($projectData['version']) ? $projectData['version'] : 1;
            $hasAdviser = !empty($projectData['noted_by']);
            
            // Check version limit (max 5 versions)
            if ($currentVersion >= 5) {
                $errorMessage = "Maximum version limit reached (5 versions). Cannot create more versions.";
            } else {
                // Capture form inputs
                $proponent1 = isset($_POST['proponents'][0]) ? trim($_POST['proponents'][0]) : '';
                $proponent2 = isset($_POST['proponents'][1]) ? trim($_POST['proponents'][1]) : '';
                $proponent3 = isset($_POST['proponents'][2]) ? trim($_POST['proponents'][2]) : '';
                $proponent4 = isset($_POST['proponents'][3]) ? trim($_POST['proponents'][3]) : '';
                $projectTitle = isset($_POST['project_title']) ? trim($_POST['project_title']) : '';
                $beneficiary = isset($_POST['beneficiary']) ? trim($_POST['beneficiary']) : '';
                $focalPerson = isset($_POST['focal_person']) ? trim($_POST['focal_person']) : '';
                $focalPersonGender = isset($_POST['gender']) ? $_POST['gender'] : '';
                $position = isset($_POST['position']) ? trim($_POST['position']) : '';
                $address = isset($_POST['address']) ? trim($_POST['address']) : '';

                // Validation
                if (empty($proponent1)) {
                    $errorMessage = "Proponent 1 is required.";
                } elseif (empty($projectTitle)) {
                    $errorMessage = "Project title is required.";
                } elseif (empty($beneficiary)) {
                    $errorMessage = "Beneficiary is required.";
                } elseif (empty($focalPerson)) {
                    $errorMessage = "Focal person is required.";
                } elseif (!in_array($focalPersonGender, ['male', 'female'])) {
                    $errorMessage = "Please select a valid gender.";
                } elseif (empty($position)) {
                    $errorMessage = "Position is required.";
                } elseif (empty($address)) {
                    $errorMessage = "Address is required.";
                } else {
                    // Create new version instead of updating
                    $newVersion = $currentVersion + 1;
                    
                    // Archive the previous version (hide from adviser but keep in database)
                    $archiveStmt = $conn->prepare("UPDATE project_working_titles SET archived = 1 WHERE id = ?");
                    $archiveStmt->bind_param("i", $projectId);
                    $archiveStmt->execute();
                    $archiveStmt->close();
                    
                    // Insert new version
                    $insertStmt = $conn->prepare(
                        "INSERT INTO project_working_titles (proponent_1, proponent_2, proponent_3, proponent_4, project_title, beneficiary, focal_person, gender, position, address, submitted_by, version, noted_by, archived) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $archived = 0; // New version is not archived
                    $insertStmt->bind_param("sssssssssssisi", $proponent1, $proponent2, $proponent3, $proponent4, 
                        $projectTitle, $beneficiary, $focalPerson, $focalPersonGender, $position, $address, $submittedBy, $newVersion, $projectData['noted_by'], $archived);

                    if ($insertStmt->execute()) {
                        $newProjectId = $conn->insert_id;
                        
                        // Copy approval data from previous version
                        $copyApprovalStmt = $conn->prepare(
                            "INSERT INTO project_approvals (project_id, faculty_approval, faculty_comments, adviser_approval, adviser_comments, dean_approval, dean_comments)
                            SELECT ?, faculty_approval, faculty_comments, adviser_approval, adviser_comments, dean_approval, dean_comments
                            FROM project_approvals WHERE project_id = ?"
                        );
                        $copyApprovalStmt->bind_param("ii", $newProjectId, $projectId);
                        $copyApprovalStmt->execute();
                        $copyApprovalStmt->close();
                        
                        // Send notification to capstone adviser if assigned
                        if ($hasAdviser) {
                            require_once '../assets/includes/notification_functions.php';
                            $adviserId = $projectData['noted_by'];
                            createNotification(
                                $conn, 
                                $adviserId, 
                                'New Project Version Available', 
                                'Student has submitted version ' . $newVersion . ' of project "' . htmlspecialchars($projectTitle) . '". Please review the updated version.',
                                'info',
                                $newProjectId,
                                'project'
                            );
                        }
                        
                        $_SESSION['success_message'] = "Project version " . $newVersion . " created successfully!" . ($hasAdviser ? " Your capstone adviser has been notified." : "");
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        $errorMessage = "Error creating new version. Please try again.";
                        error_log("Database error: " . $insertStmt->error, 0);
                    }
                    $insertStmt->close();
                }
            }
        } else {
            $errorMessage = "Project not found or cannot be updated.";
        }
        $checkQuery->close();
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$isSubmitted && !isset($_POST['action'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = "Invalid CSRF token. Please try again.";
    } else {
        // Capture form inputs to retain them
        $proponent1 = isset($_POST['proponents'][0]) ? trim($_POST['proponents'][0]) : '';
        $proponent2 = isset($_POST['proponents'][1]) ? trim($_POST['proponents'][1]) : '';
        $proponent3 = isset($_POST['proponents'][2]) ? trim($_POST['proponents'][2]) : '';
        $proponent4 = isset($_POST['proponents'][3]) ? trim($_POST['proponents'][3]) : '';
        $projectTitle = isset($_POST['project_title']) ? trim($_POST['project_title']) : '';
        $beneficiary = isset($_POST['beneficiary']) ? trim($_POST['beneficiary']) : '';
        $focalPerson = isset($_POST['focal_person']) ? trim($_POST['focal_person']) : '';
        $focalPersonGender = isset($_POST['gender']) ? $_POST['gender'] : '';
        $position = isset($_POST['position']) ? trim($_POST['position']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';

        // Simplified validation (removed noted_by validation)
        if (empty($proponent1)) {
            $errorMessage = "Proponent 1 is required.";
        } elseif (empty($projectTitle)) {
            $errorMessage = "Project title is required.";
        } elseif (empty($beneficiary)) {
            $errorMessage = "Beneficiary is required.";
        } elseif (empty($focalPerson)) {
            $errorMessage = "Focal person is required.";
        } elseif (!in_array($focalPersonGender, ['male', 'female'])) {
            $errorMessage = "Please select a valid gender.";
        } elseif (empty($position)) {
            $errorMessage = "Position is required.";
        } elseif (empty($address)) {
            $errorMessage = "Address is required.";
        } else {
            // Insert without noted_by (will be NULL initially) - Version 1
            $stmt = $conn->prepare(
                "INSERT INTO project_working_titles (proponent_1, proponent_2, proponent_3, proponent_4, project_title, beneficiary, focal_person, gender, position, address, submitted_by, version)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $version = 1;
            $stmt->bind_param("sssssssssssi", $proponent1, $proponent2, $proponent3, $proponent4, $projectTitle, $beneficiary, $focalPerson, $focalPersonGender, $position, $address, $submittedBy, $version);

            if ($stmt->execute()) {
                $projectId = $conn->insert_id;
                $approvalStmt = $conn->prepare(
                    "INSERT INTO project_approvals (project_id, faculty_approval, faculty_comments)
                    VALUES (?, ?, ?)"
                );
                $facultyApproval = 'pending';
                $facultyComments = '';
                $approvalStmt->bind_param("iss", $projectId, $facultyApproval, $facultyComments);
                $approvalStmt->execute();
                $approvalStmt->close();
                
                // Add notification for successful submission
                require_once '../assets/includes/notification_functions.php';
                $user_id = $_SESSION['user_id'];
                createNotification(
                    $conn, 
                    $user_id, 
                    'Project Submitted Successfully', 
                    'Your project "' . htmlspecialchars($projectTitle) . '" has been submitted and is waiting for dean to assign capstone adviser.',
                    'success',
                    $projectId,
                    'project'
                );
                
                // Notify dean about new project submission that needs adviser assignment
                $dean_query = "SELECT id FROM users WHERE role = 'dean' LIMIT 1";
                $dean_result = mysqli_query($conn, $dean_query);
                if ($dean_result && mysqli_num_rows($dean_result) > 0) {
                    $dean_row = mysqli_fetch_assoc($dean_result);
                    $dean_id = $dean_row['id'];
                    
                    createNotification(
                        $conn,
                        $dean_id,
                        'New Project Submission',
                        'A new project "' . htmlspecialchars($projectTitle) . '" has been submitted and requires capstone adviser assignment.',
                        'info',
                        $projectId,
                        'project_assignment'
                    );
                }
                
                $_SESSION['success_message'] = "Project submitted successfully! Waiting for dean to assign capstone adviser.";
                $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32)); // Regenerate CSRF token
                $submissionCount = count($submissions) + 1;
                header("Location: " . $_SERVER['PHP_SELF']); // Always redirect to history container after submission
                exit();
            } else {
                $errorMessage = "Error submitting the form. Please try again.";
                error_log("Database error: " . $stmt->error, 0);
            }
            $stmt->close();
        }
    }
}

// Determine if the form should be shown
$showForm = (isset($_GET['show_form']) && $_GET['show_form'] == 1 && !$isSubmitted) || $editMode;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Captrack Vault Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../assets/css/submit_research.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'no_project_found'): ?>
    <div class="floating-alert alert alert-danger" id="floatingAlert">
        No project found. Please submit your research first.
    </div>
    <?php endif; ?>

    <?php include '../assets/includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include '../assets/includes/navbar.php'; ?>
        <?php 
        // Set project_id for progress bar
        if (isset($currentProjectId)) {
            $_GET['project_id'] = $currentProjectId;
        }
        include '../assets/includes/progress-bar.php'; 
        ?>
        
        <div id="form-container" class="form-container <?php echo $showForm ? 'active' : ''; ?>">
            <?php if (isset($_SESSION['success_message']) && $showForm): ?>
                <div class="alert alert-success" id="successAlert"><?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <?php if (isset($errorMessage) && $showForm): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>
            <?php if (!$isSubmitted): ?>
                <h2 class="text-center mb-4"><?php echo $editMode ? 'Edit Project' : 'Project Working Title Form'; ?></h2>
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <?php if ($editMode): ?>
                        <input type="hidden" name="action" value="update_submission">
                        <input type="hidden" name="project_id" value="<?php echo $editProjectId; ?>">
                    <?php endif; ?>
                    
                    <!-- Proponents Section -->
                    <div class="form-section">
                        <h5 class="mb-3">Proponents/Researchers - <small class="text-muted">Format: LASTNAME, FIRSTNAME MI</small></h5>
                        <div class="proponents-group">
                            <div class="floating-label">
                                <input type="text" class="form-control" name="proponents[]" value="<?php echo htmlspecialchars($proponent1); ?>" placeholder=" " required>
                                <label>Researcher 1 (Leader of the Group)</label>
                            </div>
                            <div class="floating-label optional">
                                <input type="text" class="form-control" name="proponents[]" value="<?php echo htmlspecialchars($proponent2); ?>" placeholder=" ">
                                <label>Researcher 2</label>
                            </div>
                            <div class="floating-label optional">
                                <input type="text" class="form-control" name="proponents[]" value="<?php echo htmlspecialchars($proponent3); ?>" placeholder=" ">
                                <label>Researcher 3</label>
                            </div>
                            <div class="floating-label optional">
                                <input type="text" class="form-control" name="proponents[]" value="<?php echo htmlspecialchars($proponent4); ?>" placeholder=" ">
                                <label>Researcher 4</label>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Project Title Section -->
                    <div class="form-section">
                        <div class="floating-label">
                            <textarea class="form-control" name="project_title" id="project_title" rows="3" placeholder=" " required><?php echo htmlspecialchars($projectTitle); ?></textarea>
                            <label>Proposed Project Title</label>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Beneficiary Section -->
                    <div class="form-section">
                        <h5 class="mb-3">Research Beneficiary</h5>
                        <div class="floating-label">
                            <input type="text" class="form-control" name="beneficiary" value="<?php echo htmlspecialchars($beneficiary); ?>" placeholder=" " required>
                            <label>Beneficiary (Company/Organization)</label>
                        </div>
                        
                    <!-- Focal Person and Gender Group -->
                    <div class="focal-person-group">
                        <div class="focal-person-input">
                            <div class="floating-label">
                                <input type="text" class="form-control" name="focal_person" value="<?php echo htmlspecialchars($focalPerson); ?>" placeholder=" " required>
                                <label>Focal Person (Format: Lastname, Firstname)</label>
                            </div>
                        </div>
                        <div class="gender-options">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="gender" id="male" value="male" <?php echo $focalPersonGender === 'male' ? 'checked' : ''; ?> required>
                                <label class="form-check-label" for="male">Male</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="gender" id="female" value="female" <?php echo $focalPersonGender === 'female' ? 'checked' : ''; ?> required>
                                <label class="form-check-label" for="female">Female</label>
                            </div>
                        </div>
                    </div>
                        
                        <div class="floating-label">
                            <input type="text" class="form-control" name="position" value="<?php echo htmlspecialchars($position); ?>" placeholder=" " required>
                            <label>Position</label>
                        </div>
                        <div class="floating-label">
                            <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($address); ?>" placeholder=" " required>
                            <label>Address</label>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Your capstone adviser will be assigned by the dean after submission.
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $editMode ? 'Update Project' : 'Submit Form'; ?>
                        </button>
                        <?php if ($editMode): ?>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="history-container <?php echo !$showForm ? 'active' : ''; ?>">
            <?php if (isset($_SESSION['success_message']) && !$showForm): ?>
                <div class="alert alert-success" id="successAlert"><?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <?php if (isset($errorMessage) && !$showForm): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>
            <?php if ($isSubmitted): ?>
                <div class="alert alert-info text-center">
                    You have reached the maximum of <?php echo MAX_SUBMISSIONS; ?> submissions. No further submissions are allowed.
                </div>
            <?php endif; ?>
            <?php if (!empty($submissions)): ?>
                <h2 class="text-center mb-4">Group Submissions</h2>
                <?php foreach ($submissions as $submission): ?>
                <div class="submission-entry">
                    <div class="submission-header">
                        <h3>
                            <?php echo htmlspecialchars($submission['project_title']); ?>
                            <span class="version-badge">Version <?php echo isset($submission['version']) ? $submission['version'] : 1; ?></span>
                        </h3>
                        <div class="submission-actions">
                            <?php 
                            $currentVersion = isset($submission['version']) ? $submission['version'] : 1;
                            $canEdit = $currentVersion < 5;
                            
                            // Check if project is fully approved by faculty, adviser, and dean
                            $isFullyApproved = ($submission['faculty_approval'] === 'approved' && 
                                               $submission['adviser_approval'] === 'approved' && 
                                               $submission['dean_approval'] === 'approved');
                            
                            // Hide edit button if fully approved
                            $canEdit = $canEdit && !$isFullyApproved;
                            ?>
                            <button type="button" class="btn btn-sm btn-outline-info" onclick="showVersionHistory(<?php echo $submission['id']; ?>, '<?php echo htmlspecialchars($submission['project_title'], ENT_QUOTES); ?>')">
                                <i class="bi bi-clock-history"></i> History
                            </button>
                            <?php if ($canEdit): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editSubmission(<?php echo $submission['id']; ?>)">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                            <?php endif; ?>
                            <?php if (empty($submission['noted_by'])): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteSubmission(<?php echo $submission['id']; ?>, '<?php echo htmlspecialchars($submission['project_title'], ENT_QUOTES); ?>')">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            <?php endif; ?>
                            <?php if (!$canEdit): ?>
                                <?php if ($isFullyApproved): ?>
                                    <span class="text-success small">Project fully approved - <br>editing disabled</span>
                                <?php else: ?>
                                    <span class="text-muted small">Max versions reached (5)</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="proponents-container">
                        <strong>Proponents:</strong>
                        <ul class="proponents-list">
                            <?php
                            $proponents = array_filter([
                                htmlspecialchars($submission['proponent_1']),
                                htmlspecialchars($submission['proponent_2']),
                                htmlspecialchars($submission['proponent_3']),
                                htmlspecialchars($submission['proponent_4'])
                            ], 'strlen');
                            foreach ($proponents as $proponent) {
                                echo '<li>' . $proponent . '</li>';
                            }
                            ?>
                        </ul>
                    </div>
                    <p><strong>Beneficiary:</strong> <?php echo htmlspecialchars($submission['beneficiary']); ?></p>
                    <p><strong>Focal Person:</strong> <?php echo htmlspecialchars($submission['focal_person']); ?></p>
                    <p><strong>Position:</strong> <?php echo htmlspecialchars($submission['position']); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($submission['address']); ?></p>
                    <p><strong>Capstone Adviser:</strong> 
                        <span class="<?php echo empty($submission['noted_by']) ? 'text-warning' : 'text-success'; ?>">
                            <?php echo empty($submission['noted_by']) ? 'Awaiting Assignment' : htmlspecialchars($submission['faculty_name']); ?>
                        </span>
                    </p>
                    
                    <?php if (!empty($submission['noted_by'])): ?>
                        <p><strong>Capstone Adviser Approval Status:</strong> 
                            <span class="status <?php echo $submission['faculty_approval']; ?>">
                                <?php echo $submission['faculty_approval'] === 'approved' ? 'Approved' : ucfirst($submission['faculty_approval']); ?>
                            </span>
                        </p>
                        <?php if (!empty($submission['faculty_comments'])): ?>
                            <div class="alert alert-info mt-3">
                                <strong>Capstone Adviser Remarks:</strong><br>
                                <?php echo nl2br(htmlspecialchars($submission['faculty_comments'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($submission['faculty_approval'] === 'approved'): ?>
                            <p><strong>Capstone Professor Approval Status:</strong> 
                                <span class="status <?php echo $submission['adviser_approval']; ?>">
                                    <?php echo $submission['adviser_approval'] === 'approved' ? 'Approved' : ($submission['adviser_approval'] === 'pending' ? 'Awaiting Adviser Approval' : 'Rejected'); ?>
                                </span>
                            </p>
                            <?php if (!empty($submission['adviser_comments'])): ?>
                                <div class="alert alert-info mt-3">
                                    <strong>Capstone Professor Remarks:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($submission['adviser_comments'])); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($submission['adviser_approval'] === 'approved'): ?>
                                <p><strong>Dean Approval Status:</strong> 
                                    <span class="status <?php echo $submission['dean_approval']; ?>">
                                        <?php echo $submission['dean_approval'] === 'approved' ? 'Approved' : ($submission['dean_approval'] === 'pending' ? 'Awaiting Dean Approval' : 'Rejected'); ?>
                                    </span>
                                </p>
                                <?php if (!empty($submission['dean_comments'])): ?>
                                    <div class="alert alert-info mt-3">
                                        <strong>Dean Remarks:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($submission['dean_comments'])); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($submission['dean_approval'] === 'approved'): ?>
                                    <div class="text-center mb-3">
                                        <form action="generate_pdf.php" method="GET" class="d-inline">
                                            <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($submission['id']); ?>">
                                            <button type="submit" class="download-letter-btn">Download Letter</button>
                                        </form>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <form id="nextPhaseForm">
                                            <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($submission['id']); ?>">
                                            <button type="button" id="nextPhaseButton" class="next-phase-btn" onclick="loadNextPhase(this)">Next</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <p><strong>Status:</strong> <span class="status-pending">Awaiting Capstone Adviser Approval</span></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p><strong>Status:</strong> <span class="status-warning">Awaiting Capstone Adviser Assignment</span></p>
                    <?php endif; ?>
                    <hr>
                </div>
                <?php endforeach; ?>
                <p class="note">Thank you for your submissions. Please wait for further updates.</p>
                <?php 
                // Check if any project is fully approved
                $hasFullyApprovedProject = false;
                foreach ($submissions as $submission) {
                    if ($submission['faculty_approval'] === 'approved' && 
                        $submission['adviser_approval'] === 'approved' && 
                        $submission['dean_approval'] === 'approved') {
                        $hasFullyApprovedProject = true;
                        break;
                    }
                }
                
                // Show submit another project button only if under limit AND no project is fully approved
                if (count($submissions) < MAX_SUBMISSIONS && !$hasFullyApprovedProject): ?>
                    <div class="text-center mt-4">
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>?show_form=1" class="btn btn-success next-phase-btn">Submit Another Project</a>
                    </div>
                <?php elseif ($hasFullyApprovedProject): ?>
                    <div class="text-center mt-4">
                        <p class="text-success">You have a fully approved project. No additional submissions allowed.</p>
                    </div>
                <?php endif; ?>
            <?php elseif (!$showForm): ?>
                <div class="text-center mt-4">
                    <p>You have not submitted any projects yet.
                        <i class="bi bi-question-circle help-icon" onclick="showGuideModal()" title="Show guide"></i>
                    </p>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>?show_form=1" class="btn btn-success next-phase-btn">Submit a Project</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

<!-- Guide Modal -->
<div id="guideModal" class="guide-modal">
    <div class="guide-modal-content">
        <div class="guide-modal-header">
            <h4><i class="bi bi-lightbulb me-2"></i>Welcome to Research Title Submission</h4>
            <button class="guide-close" onclick="closeGuideModal()">&times;</button>
        </div>
        <div class="guide-modal-body">
            <div class="guide-step">
                <div class="guide-step-number">1</div>
                <div class="guide-step-content">
                    <h5>Fill Out the Project Form</h5>
                    <p>Click "Submit a Project" to access the project working title form. Fill in all required fields including proponents, project title, and beneficiary details.</p>
                </div>
            </div>
            
            <div class="guide-step">
                <div class="guide-step-number">2</div>
                <div class="guide-step-content">
                    <h5>Wait for Capstone Adviser Assignment</h5>
                    <p>After submission, the dean will assign a capstone adviser to your project. You'll be notified once this is completed.</p>
                </div>
            </div>
            
            <div class="guide-step">
                <div class="guide-step-number">3</div>
                <div class="guide-step-content">
                    <h5>Monitor Approval Process</h5>
                    <p>Your project will go through a three-stage approval process: Capstone Adviser → Capstone Professor → Dean. Track the status here and view comments from reviewers.</p>
                </div>
            </div>
            
            <div class="guide-step">
                <div class="guide-step-number">4</div>
                <div class="guide-step-content">
                    <h5>Proceed to Next Phase</h5>
                    <p>Once fully approved, you can proceed to the next phase by clicking the "Next" button to upload your research files and continue with your project.</p>
                </div>
            </div>
        </div>
        <div class="guide-modal-footer">
            <label class="dont-show-again">
                <input type="checkbox" id="dontShowAgain"> 
                <span>Don't show this again</span>
            </label>
            <button class="guide-got-it" id="gotItBtn" onclick="closeGuideModal()">
                <span class="btn-text">Got it!</span>
                <span class="btn-loading" style="display: none;">
                    <i class="bi bi-hourglass-split"></i> Saving...
                </span>
            </button>
        </div>
    </div>
</div>

<!-- Version History Modal -->
<div id="versionHistoryModal" class="guide-modal">
    <div class="guide-modal-content">
        <div class="guide-modal-header">
            <h4><i class="bi bi-clock-history me-2"></i>Version History</h4>
            <button class="guide-close" onclick="closeVersionHistoryModal()">&times;</button>
        </div>
        <div class="guide-modal-body">
            <div id="versionHistoryContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading version history...</p>
                </div>
            </div>
        </div>
        <div class="guide-modal-footer">
            <button class="guide-got-it" onclick="closeVersionHistoryModal()">Close</button>
        </div>
    </div>
</div>

    <?php $conn->close(); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        // Bootstrap form validation with custom gender validation
        (function () {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    const gender = form.querySelector('input[name="gender"]:checked');
                    const genfoLabel = form.querySelector('#genfo');
                    if (!gender) {
                        event.preventDefault();
                        event.stopPropagation();
                        genfoLabel.style.color = 'red';
                        genfoLabel.textContent = 'Please select a gender';
                    } else {
                        genfoLabel.style.color = '';
                        genfoLabel.textContent = '(Gender of the focal person)';
                    }
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();

        function loadNextPhase(button) {
            const projectID = button.closest('form').querySelector('input[name="project_id"]').value;
            window.location.href = "research-file.php?project_id=" + encodeURIComponent(projectID);
        }

        function editSubmission(projectId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo $_SERVER['PHP_SELF']; ?>';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'edit_submission';
            form.appendChild(actionInput);
            
            const projectInput = document.createElement('input');
            projectInput.type = 'hidden';
            projectInput.name = 'project_id';
            projectInput.value = projectId;
            form.appendChild(projectInput);
            
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>';
            form.appendChild(csrfInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        function deleteSubmission(projectId, projectTitle) {
            if (confirm('Are you sure you want to delete the project "' + projectTitle + '"? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?php echo $_SERVER['PHP_SELF']; ?>';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_submission';
                form.appendChild(actionInput);
                
                const projectInput = document.createElement('input');
                projectInput.type = 'hidden';
                projectInput.name = 'project_id';
                projectInput.value = projectId;
                form.appendChild(projectInput);
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>';
                form.appendChild(csrfInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        function showVersionHistory(projectId, projectTitle) {
            document.getElementById('versionHistoryModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Reset content
            document.getElementById('versionHistoryContent').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading version history...</p>
                </div>
            `;
            
            // Fetch version history
            const formData = new FormData();
            formData.append('action', 'get_version_history');
            formData.append('project_id', projectId);
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text(); // Get as text first to see what we're getting
            })
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed data:', data);
                    if (data.success) {
                        displayVersionHistory(data.versions, projectTitle);
                    } else {
                        document.getElementById('versionHistoryContent').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Error loading version history: ${data.message || 'Unknown error'}
                            </div>
                        `;
                    }
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    document.getElementById('versionHistoryContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Invalid response from server. Raw response: ${text.substring(0, 200)}...
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                document.getElementById('versionHistoryContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Error loading version history: ${error.message}
                    </div>
                `;
            });
        }

        function displayVersionHistory(versions, projectTitle) {
            let html = `<h5 class="mb-3">${projectTitle}</h5>`;
            
            if (versions.length === 0) {
                html += '<p class="text-muted">No version history found.</p>';
            } else {
                versions.forEach((version, index) => {
                    const isArchived = version.archived == 1;
                    const isLatest = index === versions.length - 1;
                    
                    html += `
                        <div class="version-entry ${isArchived ? 'archived' : ''} ${isLatest ? 'latest' : ''}">
                            <div class="version-header">
                                <h6>
                                    Version ${version.version || 1}
                                    ${isLatest ? '<span class="badge bg-success ms-2">Current</span>' : ''}
                                    ${isArchived ? '<span class="badge bg-secondary ms-2">Archived</span>' : ''}
                                </h6>
                                <small class="text-muted">Submitted: ${(() => {
                                    const dateFields = [version.date_created, version.date_submitted, version.created_at, version.submitted_at, version.timestamp];
                                    for (let dateField of dateFields) {
                                        if (dateField) {
                                            const date = new Date(dateField);
                                            if (!isNaN(date.getTime())) {
                                                return date.toLocaleDateString();
                                            }
                                        }
                                    }
                                    return 'Date not available';
                                })()}</small>
                            </div>
                            <div class="version-content">
                                <p><strong>Proponents:</strong></p>
                                <ul>
                                    ${[version.proponent_1, version.proponent_2, version.proponent_3, version.proponent_4]
                                        .filter(p => p && p.trim() !== '')
                                        .map(p => `<li>${p}</li>`).join('')}
                                </ul>
                                <p><strong>Beneficiary:</strong> ${version.beneficiary}</p>
                                <p><strong>Focal Person:</strong> ${version.focal_person} (${version.gender})</p>
                                <p><strong>Position:</strong> ${version.position}</p>
                                <p><strong>Address:</strong> ${version.address}</p>
                            </div>
                        </div>
                    `;
                });
            }
            
            document.getElementById('versionHistoryContent').innerHTML = html;
        }

        function closeVersionHistoryModal() {
            document.getElementById('versionHistoryModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

    </script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const alertBox = document.getElementById('floatingAlert');
    if (alertBox) {
        setTimeout(() => {
            alertBox.remove();
        }, 4000); 
    }
    
    // Auto-hide success message after 5 seconds
    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.transition = 'opacity 0.5s ease-out';
            successAlert.style.opacity = '0';
            setTimeout(() => {
                successAlert.remove();
            }, 500);
        }, 3000);
    }
});
</script>
<script>
// Guide Modal Functions
function showGuideModal() {
    document.getElementById('guideModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeGuideModal() {
    const dontShow = document.getElementById('dontShowAgain').checked;
    const gotItBtn = document.getElementById('gotItBtn');
    const btnText = gotItBtn.querySelector('.btn-text');
    const btnLoading = gotItBtn.querySelector('.btn-loading');
    
    if (dontShow) {
        // Show loading state
        gotItBtn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline';
        
        // Save preference to database via AJAX
        const formData = new FormData();
        formData.append('action', 'update_guide_preference');
        formData.append('dont_show', 'true');
        formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
        
        fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Failed to save preference:', data.message);
                alert('Failed to save preference. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error saving preference:', error);
            alert('Error saving preference. Please try again.');
        })
        .finally(() => {
            // Reset button state and close modal
            gotItBtn.disabled = false;
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
            document.getElementById('guideModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        });
    } else {
        // Close modal immediately if not saving preference
        document.getElementById('guideModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Check if guide should be shown on page load
document.addEventListener('DOMContentLoaded', function() {
    const shouldShowGuide = <?php echo $showGuide ? 'true' : 'false'; ?>;
    const hasNoSubmissions = <?php echo empty($submissions) && !$showForm ? 'true' : 'false'; ?>;
    
    if (hasNoSubmissions && shouldShowGuide) {
        // Show guide after a short delay for better UX
        setTimeout(() => {
            showGuideModal();
        }, 200);
    }
});

// Close modal when clicking outside
document.getElementById('guideModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeGuideModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('guideModal').style.display === 'block') {
            closeGuideModal();
        }
        if (document.getElementById('versionHistoryModal').style.display === 'block') {
            closeVersionHistoryModal();
        }
    }
});

// Close version history modal when clicking outside
document.getElementById('versionHistoryModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeVersionHistoryModal();
    }
});
</script>
<script>
// Enhanced floating label functionality
document.addEventListener('DOMContentLoaded', function() {
    // Handle floating labels for inputs that already have values on page load
    const floatingInputs = document.querySelectorAll('.floating-label input, .floating-label textarea');
    
    floatingInputs.forEach(input => {
        // Check if input has value on load
        if (input.value.trim() !== '') {
            input.classList.add('has-value');
        }
        
        // Add event listeners for focus/blur/input events
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
            if (this.value.trim() !== '') {
                this.classList.add('has-value');
            } else {
                this.classList.remove('has-value');
            }
        });
        
        input.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                this.classList.add('has-value');
            } else {
                this.classList.remove('has-value');
            }
        });
    });
});

// Updated form validation (removed gender note handling)
(function () {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            const gender = form.querySelector('input[name="gender"]:checked');
            if (!gender) {
                event.preventDefault();
                event.stopPropagation();
                // You can add visual feedback for gender selection error here if needed
                const genderContainer = form.querySelector('.gender-options');
                genderContainer.style.borderColor = '#dc3545';
                setTimeout(() => {
                    genderContainer.style.borderColor = '#ddd';
                }, 3000);
            }
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>
</body>
</html>