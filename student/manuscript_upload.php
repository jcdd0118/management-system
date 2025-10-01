<?php
// Start the session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../users/login.php");
    exit();
}

include '../config/database.php';

$email = $_SESSION['email'];

// Get user details
$userQuery = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
$userQuery->bind_param("s", $email);
$userQuery->execute();
$userResult = $userQuery->get_result();
$user = $userResult->fetch_assoc();
$userQuery->close();

$userId = $user['id'];

// Fetch current student's group code for permission checks (allow groupmates)
$currentGroupCode = null;
$groupStmt = $conn->prepare("SELECT group_code FROM students WHERE user_id = ? LIMIT 1");
$groupStmt->bind_param("i", $userId);
$groupStmt->execute();
$groupRes = $groupStmt->get_result();
if ($groupRow = $groupRes->fetch_assoc()) {
	$currentGroupCode = isset($groupRow['group_code']) ? $groupRow['group_code'] : null;
}
$groupStmt->close();

// Get project_id from GET
if (!isset($_GET['project_id'])) {
    header("Location: title-defense.php?error=no_project_found");
    exit();
}

$projectId = $_GET['project_id'];

// Check if final defense is approved
$finalDefenseQuery = $conn->prepare("SELECT status FROM final_defense WHERE project_id = ?");
$finalDefenseQuery->bind_param("i", $projectId);
$finalDefenseQuery->execute();
$finalDefenseResult = $finalDefenseQuery->get_result();
$finalDefense = $finalDefenseResult->fetch_assoc();
$finalDefenseQuery->close();

if (!$finalDefense || $finalDefense['status'] !== 'approved') {
    header("Location: final-defense.php?project_id=$projectId&error=final_defense_not_approved");
    exit();
}

// Fetch project details
$projectQuery = $conn->prepare("SELECT project_title FROM project_working_titles WHERE id = ?");
$projectQuery->bind_param("i", $projectId);
$projectQuery->execute();
$projectResult = $projectQuery->get_result();
$project = $projectResult->fetch_assoc();
$projectQuery->close();

// Check if manuscript review already exists (project-wide)
$manuscriptQuery = $conn->prepare("SELECT * FROM manuscript_reviews WHERE project_id = ? ORDER BY id DESC LIMIT 1");
$manuscriptQuery->bind_param("i", $projectId);
$manuscriptQuery->execute();
$manuscriptResult = $manuscriptQuery->get_result();
$manuscript = $manuscriptResult->fetch_assoc();
$manuscriptQuery->close();

// Get fresh data from database to avoid caching issues (project-wide)
$directQuery = $conn->prepare("SELECT * FROM manuscript_reviews WHERE project_id = ? ORDER BY id DESC LIMIT 1");
$directQuery->bind_param("i", $projectId);
$directQuery->execute();
$directResult = $directQuery->get_result();
$directData = $directResult->fetch_assoc();
$directQuery->close();

// Handle file submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only allow submission if no manuscript has been submitted before
    if (isset($_POST['submit_manuscript']) && !$manuscript) {
        if (!empty($_FILES['manuscript_file']['name'])) {
            $fileTmp = $_FILES['manuscript_file']['tmp_name'];
            $fileName = $_FILES['manuscript_file']['name'];
            $fileType = $_FILES['manuscript_file']['type'];

            // Validate PDF
            if ($fileType === 'application/pdf' && strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'pdf') {
                $filePath = "../assets/uploads/manuscripts/" . time() . "_manuscript_" . $fileName;

                if (move_uploaded_file($fileTmp, $filePath)) {
                    // Insert or update manuscript review record
                    if ($manuscript) {
                        $query = "UPDATE manuscript_reviews SET manuscript_file = ?, status = 'pending', date_submitted = NOW() WHERE id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("si", $filePath, $manuscript['id']);
                    } else {
                        $query = "INSERT INTO manuscript_reviews (project_id, student_id, manuscript_file, status) VALUES (?, ?, ?, 'pending')";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("iis", $projectId, $userId, $filePath);
                    }
                    
                    if ($stmt->execute()) {
                        // Add notification for manuscript submission
                        require_once '../assets/includes/notification_functions.php';
                        createNotification(
                            $conn, 
                            $userId, 
                            'Manuscript Submitted', 
                            'Your manuscript for project "' . htmlspecialchars($project['project_title']) . '" has been submitted for grammar review.',
                            'info',
                            $projectId,
                            'manuscript_submission'
                        );
                        
                        header("Location: manuscript_upload.php?project_id=$projectId&success=1");
                        exit();
                    } else {
                        $error = "Failed to save manuscript to database.";
                    }
                    $stmt->close();
                } else {
                    $error = "Failed to upload manuscript file.";
                }
            } else {
                $error = "Only PDF files are allowed.";
            }
        } else {
            $error = "Please upload a manuscript file.";
        }
    }

    // Handle edit manuscript (replace existing file)
    // Allow the original submitter OR any groupmate (same group_code) to edit/replace the manuscript
    if (isset($_POST['edit_manuscript']) && $manuscript) {
        $canEditManuscript = false;
        if (isset($manuscript['student_id']) && (int)$manuscript['student_id'] === (int)$userId) {
            $canEditManuscript = true;
        } else {
            // Compare group codes between current user and the original submitter
            $submitterGroupCode = null;
            if (isset($manuscript['student_id'])) {
                $submitterStmt = $conn->prepare("SELECT group_code FROM students WHERE user_id = ? LIMIT 1");
                $submitterStmt->bind_param("i", $manuscript['student_id']);
                $submitterStmt->execute();
                $submitterRes = $submitterStmt->get_result();
                if ($submitterRow = $submitterRes->fetch_assoc()) {
                    $submitterGroupCode = isset($submitterRow['group_code']) ? $submitterRow['group_code'] : null;
                }
                $submitterStmt->close();
            }
            if (!empty($currentGroupCode) && !empty($submitterGroupCode) && $currentGroupCode === $submitterGroupCode) {
                $canEditManuscript = true;
            }
        }

        if ($canEditManuscript) {
        if (!empty($_FILES['manuscript_file']['name'])) {
            $fileTmp = $_FILES['manuscript_file']['tmp_name'];
            $fileName = $_FILES['manuscript_file']['name'];
            $fileType = $_FILES['manuscript_file']['type'];

            // Validate PDF
            if ($fileType === 'application/pdf' && strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'pdf') {
                // Delete old file if it exists
                if (isset($manuscript['manuscript_file']) && file_exists($manuscript['manuscript_file'])) {
                    unlink($manuscript['manuscript_file']);
                }

                $filePath = "../assets/uploads/manuscripts/" . time() . "_manuscript_" . $fileName;

                if (move_uploaded_file($fileTmp, $filePath)) {
                    // Update manuscript review record - reset status to pending for reupload
                    $query = "UPDATE manuscript_reviews SET manuscript_file = ?, status = 'pending', date_submitted = NOW(), grammarian_notes = NULL, date_reviewed = NULL, reviewed_by = NULL WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("si", $filePath, $manuscript['id']);
                    
                    if ($stmt->execute()) {
                        header("Location: manuscript_upload.php?project_id=$projectId&edit_success=1");
                        exit();
                    } else {
                        $error = "Failed to update manuscript in database.";
                    }
                    $stmt->close();
                } else {
                    $error = "Failed to upload manuscript file.";
                }
            } else {
                $error = "Only PDF files are allowed.";
            }
        } else {
            $error = "Please upload a manuscript file.";
        }
        } else {
            $error = "You don't have permission to update this manuscript.";
        }
    }

    // Handle manual file linking
    if (isset($_POST['link_file']) && isset($_POST['manuscript_id']) && isset($_POST['selected_file']) && !empty($_POST['selected_file'])) {
        $manuscriptId = $_POST['manuscript_id'];
        $selectedFile = $_POST['selected_file'];
        $filePath = "../assets/uploads/manuscripts/" . $selectedFile;
        
        // Verify the file exists
        $absolutePath = realpath(__DIR__ . '/' . $filePath);
        if ($absolutePath && file_exists($absolutePath)) {
            // Update the database
            $query = "UPDATE manuscript_reviews SET manuscript_file = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $filePath, $manuscriptId);
            
            if ($stmt->execute()) {
                header("Location: manuscript_upload.php?project_id=$projectId&link_success=1");
                exit();
            } else {
                $error = "Failed to link file to manuscript.";
            }
            $stmt->close();
        } else {
            $error = "Selected file does not exist.";
        }
    }

    // Handle cancel/withdraw manuscript submission
    // Only the original submitter can cancel the manuscript
    if (isset($_POST['cancel_manuscript']) && $manuscript && isset($manuscript['student_id']) && (int)$manuscript['student_id'] === (int)$userId) {
        // Delete file if it exists
        if (isset($manuscript['manuscript_file']) && file_exists($manuscript['manuscript_file'])) {
            unlink($manuscript['manuscript_file']);
        }

        // Delete the entire manuscript record to allow fresh submission
        $query = "DELETE FROM manuscript_reviews WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $manuscript['id']);
        if ($stmt->execute()) {
            header("Location: manuscript_upload.php?project_id=$projectId&cancel_success=1");
            exit();
        } else {
            $error = "Failed to cancel manuscript submission.";
        }
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manuscript Upload - Captrack Vault</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <style>
        .manuscript-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        .form-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .file-preview {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            margin: 1rem 0;
            background: #f8f9fa;
        }
        .file-preview.has-file {
            border-color: #28a745;
            background: #d4edda;
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-under_review { background-color: #d1ecf1; color: #0c5460; }
        .status-approved { background-color: #d4edda; color: #155724; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .btn-submit {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 600;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
            color: white;
        }
        .btn-unsubmit {
            background: linear-gradient(45deg, #dc3545, #c82333);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 600;
        }
        .btn-unsubmit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
            color: white;
        }
        .notice {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 600px;
            background-color: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            opacity: 1;
            transition: opacity 0.5s ease;
        }
        .auto-hide {
            opacity: 1;
            transition: opacity 0.5s ease;
        }
        .auto-hide.fade-out {
            opacity: 0;
        }
        
        /* Mobile Responsive Styles - Scoped to manuscript upload page */
        @media (max-width: 768px) {
            .manuscript-header {
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }
            .manuscript-header h2 {
                font-size: 1.5rem;
            }
            .manuscript-header .col-md-4 {
                text-align: center !important;
                margin-top: 1rem;
            }
            .manuscript-header .col-md-4 i {
                font-size: 2rem !important;
            }
            .form-container {
                padding: 1.5rem;
                margin: 0 0.5rem;
            }
            .file-preview {
                padding: 1.5rem 1rem;
            }
            .file-preview i {
                font-size: 2rem !important;
            }
            .btn-submit, .btn-unsubmit {
                padding: 0.75rem 1.5rem;
                font-size: 0.9rem;
            }
            .status-badge {
                padding: 0.4rem 0.8rem;
                font-size: 0.75rem;
            }
            .alert {
                margin-bottom: 1rem;
            }
            /* Only affect flex containers within the form-container */
            .form-container .d-flex.justify-content-between {
                flex-direction: column;
                align-items: flex-start !important;
            }
            .form-container .d-flex.justify-content-between .btn {
                margin-top: 0.5rem;
                align-self: flex-end;
            }
        }
        
        @media (max-width: 576px) {
            .manuscript-header {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            .manuscript-header h2 {
                font-size: 1.25rem;
            }
            .manuscript-header p {
                font-size: 0.9rem;
            }
            .form-container {
                padding: 1rem;
                margin: 0 0.25rem;
            }
            .file-preview {
                padding: 1rem;
            }
            .file-preview i {
                font-size: 1.5rem !important;
            }
            .btn-submit, .btn-unsubmit {
                padding: 0.6rem 1.2rem;
                font-size: 0.85rem;
            }
            .form-container .btn-group-vertical .btn {
                margin-bottom: 0.5rem;
            }
            .notice {
                width: 95%;
                font-size: 14px;
                padding: 10px 15px;
                max-width: 100%;
                top: 10px;
            }
            .form-container .col-md-8, .form-container .col-md-4 {
                margin-bottom: 1rem;
            }
            .form-container .form-select {
                width: 100% !important;
                margin-bottom: 0.5rem;
            }
            .form-container .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
        }
        
        @media (max-width: 400px) {
            .manuscript-header {
                padding: 0.75rem;
            }
            .form-container {
                padding: 0.75rem;
            }
            .file-preview {
                padding: 0.75rem;
            }
            .btn-submit, .btn-unsubmit {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
            .notice {
                font-size: 12px;
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <?php include '../assets/includes/navbar.php'; ?>
    <?php include '../assets/includes/progress-bar.php'; ?>

    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="manuscript-header">
            <div class="row align-items-center">
                <div class="col-md-8 col-12">
                    <h2 class="mb-2">
                        <i class="bi bi-file-text me-2"></i>
                        Manuscript Upload
                    </h2>
                    <p class="mb-1">
                        <strong>Project:</strong> <?php echo htmlspecialchars($project['project_title']); ?>
                    </p>
                    <p class="mb-0">
                        Upload your final manuscript for grammar review by the grammarian.
                    </p>
                </div>
                <div class="col-md-4 col-12 text-md-end text-center">
                    <i class="bi bi-check-circle" style="font-size: 3rem; opacity: 0.8;"></i>
                </div>
            </div>
        </div>

        <div class="form-container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="notice">Manuscript uploaded successfully!</div>
            <?php endif; ?>

            <?php if (isset($_GET['cancel_success'])): ?>
                <div class="notice">Manuscript submission cancelled successfully! You can now submit a new manuscript.</div>
            <?php endif; ?>

            <?php if (isset($_GET['edit_success'])): ?>
                <div class="notice">Manuscript updated successfully!</div>
            <?php endif; ?>

            <?php if (isset($_GET['link_success'])): ?>
                <div class="notice">File linked to manuscript successfully!</div>
            <?php endif; ?>

            <?php if ($manuscript): ?>
                
                <?php
                // Auto-fix: If manuscript record exists but no file path, try to link to existing file
                if ($manuscript && (!isset($manuscript['manuscript_file']) || empty($manuscript['manuscript_file']))) {
                    $manuscriptsDir = realpath(__DIR__ . '/../assets/uploads/manuscripts');
                    if ($manuscriptsDir && is_dir($manuscriptsDir)) {
                        $files = scandir($manuscriptsDir);
                        $pdfFiles = array_filter($files, function($file) {
                            return $file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'pdf';
                        });
                        
                        if (!empty($pdfFiles)) {
                            // Sort by modification time, get the most recent
                            $fileTimes = array();
                            foreach ($pdfFiles as $file) {
                                $fileTimes[$file] = filemtime($manuscriptsDir . '/' . $file);
                            }
                            arsort($fileTimes);
                            $mostRecentFile = key($fileTimes);
                            
                            // Update the database with the file path
                            $filePath = "../assets/uploads/manuscripts/" . $mostRecentFile;
                            $updateQuery = "UPDATE manuscript_reviews SET manuscript_file = ? WHERE id = ?";
                            $updateStmt = $conn->prepare($updateQuery);
                            $updateStmt->bind_param("si", $filePath, $manuscript['id']);
                            
                            if ($updateStmt->execute()) {
                                // Refresh the manuscript data
                                $manuscript['manuscript_file'] = $filePath;
                                echo '<div class="alert alert-success auto-hide">✅ Auto-linked manuscript to file: ' . htmlspecialchars($mostRecentFile) . '</div>';
                            }
                            $updateStmt->close();
                        }
                    }
                }
                ?>
                
                <!-- Manual File Link Option -->
                <?php if ($manuscript && (!isset($manuscript['manuscript_file']) || empty($manuscript['manuscript_file']))): ?>
                    <div class="alert alert-warning">
                        <h6>Manual File Link</h6>
                        <p>If the auto-link didn't work, you can manually link your manuscript to one of the files:</p>
                        <form method="POST" class="d-flex flex-column flex-md-row gap-2">
                            <input type="hidden" name="manuscript_id" value="<?php echo $manuscript['id']; ?>">
                            <select name="selected_file" class="form-select flex-grow-1">
                                <option value="">Select a file...</option>
                                <?php
                                $manuscriptsDir = realpath(__DIR__ . '/../assets/uploads/manuscripts');
                                if ($manuscriptsDir && is_dir($manuscriptsDir)) {
                                    $files = scandir($manuscriptsDir);
                                    $pdfFiles = array_filter($files, function($file) {
                                        return $file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'pdf';
                                    });
                                    foreach ($pdfFiles as $file) {
                                        echo '<option value="' . htmlspecialchars($file) . '">' . htmlspecialchars($file) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                            <button type="submit" name="link_file" class="btn btn-primary btn-sm">Link File</button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <!-- Manuscript Record Exists -->
                <div class="row">
                    <div class="col-md-8 col-12">
                        <h4 class="mb-3">Current Status</h4>
                        <?php 
                        $hasFile = false;
                        if (isset($manuscript['manuscript_file']) && !empty($manuscript['manuscript_file'])) {
                            // Convert relative path to absolute path for checking
                            $filePath = $manuscript['manuscript_file'];
                            if (strpos($filePath, '../') === 0) {
                                // If it's a relative path, convert to absolute
                                $absolutePath = realpath(__DIR__ . '/' . $filePath);
                                $hasFile = $absolutePath && file_exists($absolutePath);
                                
                                // Debug: Let's also try a different approach
                                $alternativePath = str_replace('../', '', $filePath);
                                $alternativeAbsolute = realpath(__DIR__ . '/' . $alternativePath);
                                if (!$hasFile && $alternativeAbsolute && file_exists($alternativeAbsolute)) {
                                    $hasFile = true;
                                    $filePath = $alternativeAbsolute; // Update the path for display
                                }
                            } else {
                                $hasFile = file_exists($filePath);
                            }
                        }
                        ?>
                        <div class="file-preview <?php echo $hasFile ? 'has-file' : ''; ?>">
                            <?php if ($hasFile): ?>
                                <i class="bi bi-file-pdf text-danger" style="font-size: 3rem;"></i>
                                <h5 class="mt-2">Manuscript PDF</h5>
                                <p class="text-muted">Your manuscript has been uploaded</p>
                                <div class="d-flex flex-wrap gap-2 justify-content-center">
                                    <a href="<?php echo htmlspecialchars($manuscript['manuscript_file']); ?>" 
                                       target="_blank" class="btn btn-primary">
                                        <i class="bi bi-eye me-1"></i>
                                        <span class="d-none d-sm-inline">View Manuscript</span>
                                        <span class="d-sm-none">View</span>
                                    </a>
                                    <a href="<?php echo htmlspecialchars($manuscript['manuscript_file']); ?>" 
                                       download class="btn btn-outline-primary">
                                        <i class="bi bi-download me-1"></i>
                                        <span class="d-none d-sm-inline">Download</span>
                                        <span class="d-sm-none">Download</span>
                                    </a>
                                </div>
                            <?php else: ?>
                                <i class="bi bi-file-plus text-muted" style="font-size: 3rem;"></i>
                                <h5 class="mt-2">No File Available</h5>
                                <p class="text-muted">
                                    <?php if (isset($manuscript['manuscript_file']) && !empty($manuscript['manuscript_file'])): ?>
                                        File path exists in database but file is missing from server<br>
                                        <small class="text-info">Path: <?php echo htmlspecialchars($manuscript['manuscript_file']); ?></small><br>
                                        <?php 
                                        $debugPath = $manuscript['manuscript_file'];
                                        if (strpos($debugPath, '../') === 0) {
                                            $debugAbsolutePath = realpath(__DIR__ . '/' . $debugPath);
                                            echo '<small class="text-warning">Absolute Path: ' . htmlspecialchars($debugAbsolutePath ?: 'Could not resolve') . '</small><br>';
                                            echo '<small class="text-warning">File exists: ' . ($debugAbsolutePath && file_exists($debugAbsolutePath) ? 'YES' : 'NO') . '</small>';
                                        }
                                        ?>
                                    <?php else: ?>
                                        Manuscript record exists but no file has been uploaded
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 col-12">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0">Review Status</h4>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise me-1"></i>
                                <span class="d-none d-sm-inline">Refresh</span>
                            </button>
                        </div>
                        <div class="text-center">
                            <span class="status-badge status-<?php echo isset($directData['status']) ? $directData['status'] : 'pending'; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', isset($directData['status']) ? $directData['status'] : 'pending')); ?>
                            </span>
                            <?php if (isset($manuscript['date_submitted']) && !empty($manuscript['date_submitted'])): ?>
                                <p class="mt-3 text-muted">
                                    <small>Submitted: <?php echo date('M d, Y', strtotime($manuscript['date_submitted'])); ?></small>
                                </p>
                            <?php endif; ?>
                        </div>

                        <?php if (isset($directData['grammarian_notes']) && !empty($directData['grammarian_notes'])): ?>
                            <div class="mt-3">
                                <h6><i class="bi bi-chat-text me-2"></i>Grammarian Feedback:</h6>
                                <div class="alert alert-info">
                                    <div class="d-flex align-items-start">
                                        <i class="bi bi-info-circle me-2 mt-1"></i>
                                        <div>
                                            <?php echo nl2br(htmlspecialchars($directData['grammarian_notes'])); ?>
                                            <?php if (isset($directData['date_reviewed']) && !empty($directData['date_reviewed'])): ?>
                                                <hr class="my-2">
                                                <small class="text-muted">
                                                    <i class="bi bi-clock me-1"></i>
                                                    Reviewed on: <?php echo date('M d, Y \a\t g:i A', strtotime($directData['date_reviewed'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif (isset($directData['status']) && in_array($directData['status'], ['rejected', 'approved']) && isset($directData['date_reviewed'])): ?>
                            <div class="mt-3">
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Review Complete</strong><br>
                                    <small>Your manuscript has been reviewed but no detailed feedback was provided by the grammarian.</small>
                                </div>
                            </div>
                        <?php elseif (isset($directData['status']) && $directData['status'] === 'under_review'): ?>
                            <div class="mt-3">
                                <div class="alert alert-warning">
                                    <i class="bi bi-hourglass-split me-2"></i>
                                    <strong>Review in Progress</strong><br>
                                    <small>Your manuscript is being reviewed by the grammarian. Feedback will appear here once the review is complete.</small>
                                </div>
                            </div>
                        <?php elseif (isset($directData['status']) && $directData['status'] === 'rejected'): ?>
                            <div class="mt-3">
                                <div class="alert alert-danger">
                                    <i class="bi bi-x-circle me-2"></i>
                                    <strong>Manuscript Rejected</strong><br>
                                    <small>Your manuscript has been rejected. Please check the feedback below and resubmit a revised version.</small>
                                </div>
                            </div>
                        <?php elseif (isset($directData['status']) && $directData['status'] === 'approved'): ?>
                            <div class="mt-3">
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle me-2"></i>
                                    <strong>Manuscript Approved</strong><br>
                                    <small>Congratulations! Your manuscript has been approved by the grammarian.</small>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($directData['grammarian_reviewed_file']) && !empty($directData['grammarian_reviewed_file'])): ?>
                            <div class="mt-3">
                                <h6>Reviewed Manuscript:</h6>
                                <a href="<?php echo htmlspecialchars($directData['grammarian_reviewed_file']); ?>" 
                                   target="_blank" class="btn btn-success btn-sm">
                                    <i class="bi bi-download me-1"></i>
                                    Download Reviewed Version
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if (!isset($directData['status']) || $directData['status'] !== 'approved'): ?>
                            <div class="mt-3">
                                <!-- Edit Form -->
                                <form method="POST" enctype="multipart/form-data" class="mb-3">
                                    <div class="mb-3">
                                        <label for="edit_manuscript_file" class="form-label">
                                            <i class="bi bi-pencil me-2"></i>
                                            <?php if (isset($directData['status']) && $directData['status'] === 'rejected'): ?>
                                                Reupload Revised Manuscript
                                            <?php else: ?>
                                                Replace Manuscript File
                                            <?php endif; ?>
                                        </label>
                                        <input type="file" class="form-control" id="edit_manuscript_file" name="manuscript_file" 
                                               accept="application/pdf">
                                        <div class="form-text">
                                            <i class="bi bi-info-circle me-1"></i>
                                            <?php if (isset($directData['status']) && $directData['status'] === 'rejected'): ?>
                                                Upload a revised version of your manuscript based on the grammarian's feedback
                                            <?php else: ?>
                                                Select a new PDF file to replace the current manuscript
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <button type="submit" name="edit_manuscript" class="btn btn-warning w-100 mb-2">
                                        <i class="bi bi-upload me-1"></i>
                                        <?php if (isset($directData['status']) && $directData['status'] === 'rejected'): ?>
                                            Reupload Manuscript
                                        <?php else: ?>
                                            Update Manuscript
                                        <?php endif; ?>
                                    </button>
                                </form>

                                <!-- Cancel Button -->
                                <form method="POST" onsubmit="return confirm('Are you sure you want to cancel your manuscript submission? This will delete your current submission and allow you to submit a new one. This action cannot be undone.');">
                                    <button type="submit" name="cancel_manuscript" class="btn btn-unsubmit w-100">
                                        <i class="bi bi-x-circle me-1"></i>
                                        Cancel Submission
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="notice">
                    <?php 
                    $status = isset($manuscript['status']) ? $manuscript['status'] : 'pending';
                    if ($status === 'approved') {
                        echo "🎉 Congratulations! Your manuscript has been approved by the grammarian!";
                    } elseif ($status === 'under_review') {
                        echo "📝 Your manuscript is currently under review by the grammarian. Please wait for feedback.";
                    } elseif ($status === 'rejected') {
                        echo "⚠️ Your manuscript needs revision. Please check the grammarian's feedback and cancel your submission to upload a new version.";
                    } else {
                        echo "✅ Your manuscript has been submitted for grammar review. Please wait for the grammarian's feedback.";
                    }
                    ?>
                </div>

            <?php else: ?>
                <!-- Upload Form -->
                <h4 class="mb-3">Upload Your Manuscript</h4>
                <p class="text-muted mb-4">
                    Please upload your final manuscript in PDF format. The grammarian will review it for grammar, 
                    spelling, and language improvements. <strong>Note: You can only submit once. If you need to make changes, 
                    you must cancel your submission first.</strong>
                </p>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="manuscript_file" class="form-label">
                            <i class="bi bi-file-pdf me-2"></i>
                            Manuscript File (PDF only)
                        </label>
                        <input type="file" class="form-control" id="manuscript_file" name="manuscript_file" 
                               accept="application/pdf" required>
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>
                            Only PDF files are accepted. Maximum file size: 10MB
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="submit_manuscript" class="btn btn-submit">
                            <i class="bi bi-upload me-2"></i>
                            Upload Manuscript
                        </button>
                    </div>
                </form>
            <?php endif; ?>

			<!-- Navigation -->
			<div class="row mt-4">
				<div class="col-12 text-end">
					<?php if (isset($directData['status']) && $directData['status'] === 'approved'): ?>
						<a href="submit_manuscript.php?project_id=<?php echo $projectId; ?>" class="btn btn-success">
							Next
							<i class="bi bi-arrow-right ms-2"></i>
						</a>
					<?php endif; ?>
				</div>
			</div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Select all elements with class 'notice'
        const notices = document.querySelectorAll('.notice');
        
        notices.forEach(notice => {
            // Set a timeout to fade out and remove the notice after 3 seconds
            setTimeout(() => {
                notice.style.opacity = '0';
                setTimeout(() => {
                    notice.remove();
                }, 500); // Wait for the fade-out transition to complete
            }, 3000); // 3-second delay before starting the fade-out
        });
        
        // Handle auto-hide messages (faster fade out)
        const autoHideMessages = document.querySelectorAll('.auto-hide');
        autoHideMessages.forEach(message => {
            // Auto-hide after 2 seconds
            setTimeout(() => {
                message.classList.add('fade-out');
                setTimeout(() => {
                    message.remove();
                }, 500); // Wait for the fade-out transition to complete
            }, 2000); // 2-second delay for auto-hide messages
        });

        // Clean URL params after showing success messages
        const url = new URL(window.location);
        const hadParams = url.searchParams.has('success') || url.searchParams.has('edit_success') || url.searchParams.has('cancel_success') || url.searchParams.has('link_success');
        if (hadParams) {
            setTimeout(() => {
                url.searchParams.delete('success');
                url.searchParams.delete('edit_success');
                url.searchParams.delete('cancel_success');
                url.searchParams.delete('link_success');
                window.history.replaceState({}, document.title, url.pathname + '?' + url.searchParams.toString());
            }, 800);
        }
    });
</script>
</body>
</html>
