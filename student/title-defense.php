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
$isFaculty = ($user['role'] === 'faculty');

// Determine if student is 4th year (used to control messaging)
$isFourthYear = true; // default allow if unknown
$ysStmt = $conn->prepare("SELECT year_section FROM students WHERE user_id = ? LIMIT 1");
if ($ysStmt) {
	$ysStmt->bind_param("i", $userId);
	$ysStmt->execute();
	$ysRes = $ysStmt->get_result();
	if ($ysRow = $ysRes->fetch_assoc()) {
		$ysCode = isset($ysRow['year_section']) ? $ysRow['year_section'] : '';
		$isFourthYear = (strlen($ysCode) > 0 && substr($ysCode, 0, 1) === '4');
	}
	$ysStmt->close();
}

// Get project_id from GET
if (!isset($_GET['project_id'])) {
    header("Location: submit_research.php?error=no_project_found");
    exit();
}
date_default_timezone_set('Asia/Manila');
$projectId = $_GET['project_id'];

// Fetch title defense details
$query = "SELECT * FROM title_defense WHERE project_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $projectId);
$stmt->execute();
$result = $stmt->get_result();
$titleDefense = $result->fetch_assoc();
$stmt->close();

// Fetch project details
$projectQuery = $conn->prepare("SELECT project_title FROM project_working_titles WHERE id = ?");
$projectQuery->bind_param("i", $projectId);
$projectQuery->execute();
$projectResult = $projectQuery->get_result();
$project = $projectResult->fetch_assoc();
$projectQuery->close();

// If no title defense record, create a placeholder
if (!$titleDefense) {
    $titleDefense = [
        'pdf_file' => null,
        'status' => 'pending',
        'scheduled_date' => null,
        'remarks' => null
    ];
}

// Handle file submission (ONLY ONCE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_file']) && empty($titleDefense['pdf_file'])) {
        if (!empty($_FILES['title_defense_file']['name'])) {
            $fileTmpPath = $_FILES['title_defense_file']['tmp_name'];
            $fileName = $_FILES['title_defense_file']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Validate PDF
            if ($fileExtension !== 'pdf') {
                $error = "Only PDF files are allowed.";
            } else {
                $uploadPath = "../assets/uploads/title_defense/" . time() . "_" . $fileName;
                move_uploaded_file($fileTmpPath, $uploadPath);

                // Insert into title_defense table
                $query = "INSERT INTO title_defense (project_id, submitted_by, pdf_file, status) VALUES (?, ?, ?, 'pending')";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("iis", $projectId, $userId, $uploadPath);
                $stmt->execute();
                $stmt->close();

                // Add notification for title defense submission
                require_once '../assets/includes/notification_functions.php';
                createNotification(
                    $conn, 
                    $userId, 
                    'Title Defense Submitted', 
                    'Your title defense for project "' . htmlspecialchars($project['project_title']) . '" has been submitted and is under review.',
                    'info',
                    $projectId,
                    'title_defense'
                );

                header("Location: title-defense.php?project_id=$projectId&success=1");
                exit();
            }
        } else {
            $error = "Please select a PDF file.";
        }
    }

    // Handle unsubmit (DELETE FROM DATABASE)
    if (isset($_POST['unsubmit_file']) && !empty($titleDefense['pdf_file'])) {
        unlink($titleDefense['pdf_file']);
        $query = "DELETE FROM title_defense WHERE project_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $projectId);
        $stmt->execute();
        $stmt->close();

        header("Location: title-defense.php?project_id=$projectId");
        exit();
    }

    // Handle edit file (UPDATE EXISTING FILE)
    if (isset($_POST['edit_file']) && !empty($titleDefense['pdf_file'])) {
        if (!empty($_FILES['new_title_defense_file']['name'])) {
            // Handle common PHP upload errors early
            if (isset($_FILES['new_title_defense_file']['error']) && $_FILES['new_title_defense_file']['error'] !== UPLOAD_ERR_OK) {
                $phpError = $_FILES['new_title_defense_file']['error'];
                if ($phpError === UPLOAD_ERR_INI_SIZE || $phpError === UPLOAD_ERR_FORM_SIZE) {
                    $error = "File is too large. Please upload a smaller PDF.";
                } else if ($phpError === UPLOAD_ERR_PARTIAL) {
                    $error = "File upload was incomplete. Please try again.";
                } else if ($phpError === UPLOAD_ERR_NO_FILE) {
                    $error = "No file uploaded.";
                } else {
                    $error = "Upload failed with error code: " . intval($phpError);
                }
            } else {
                $fileTmpPath = $_FILES['new_title_defense_file']['tmp_name'];
                $fileName = $_FILES['new_title_defense_file']['name'];
                $fileSize = isset($_FILES['new_title_defense_file']['size']) ? (int)$_FILES['new_title_defense_file']['size'] : 0;
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                // Validate PDF
                if ($fileExtension !== 'pdf') {
                    $error = "Only PDF files are allowed.";
                } else if ($fileSize <= 0) {
                    $error = "Uploaded file is empty or failed to transfer.";
                } else {
                    // Delete old file
                    if (file_exists($titleDefense['pdf_file'])) {
                        @unlink($titleDefense['pdf_file']);
                    }

                    // Upload new file
                    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName);
                    $uploadPath = "../assets/uploads/title_defense/" . time() . "_" . $safeName;
                    if (is_uploaded_file($fileTmpPath) && move_uploaded_file($fileTmpPath, $uploadPath)) {
                        // Update database
                        $query = "UPDATE title_defense SET pdf_file = ? WHERE project_id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("si", $uploadPath, $projectId);
                        if ($stmt->execute()) {
                            header("Location: title-defense.php?project_id=$projectId&edit_success=1");
                            exit();
                        } else {
                            $error = "Failed to update file in database.";
                        }
                        $stmt->close();
                    } else {
                        $error = "Failed to upload new file. It may exceed the server limit.";
                    }
                }
            }
        } else {
            $error = "Please select a new PDF file.";
        }
    }
}
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
    <style>
    /* File Upload Label Styling */
    .file-upload {
        display: inline-block;
        padding: 12px 20px;
        border: 2px dashed #6c757d;
        border-radius: 8px;
        background-color: #f8f9fa;
        color: #495057;
        font-family: 'Inter', sans-serif;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-bottom: 15px;
        text-align: center;
    }

    .file-upload:hover {
        border-color: #007bff;
        background-color: #e9ecef;
        color: #007bff;
    }

    .file-upload input[type="file"] {
        display: none;
    }

    .btn-submit {
        width: 100px;
        max-width: 300px;
        font-weight: bold;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        padding: 10px 5px;
        background-color: #2F8D46;
        text-decoration: none; /* Remove underline */
        display: inline-block; /* Ensure consistent button behavior */
        margin-top: 10px; /* Add spacing above button */
    }
    .btn-submit:hover {
        background-color: green;
    }

    #next {
        width: auto !important;
    }

    .file-name {
        margin-top: 10px;
        color: #495057;
        font-family: 'Inter', sans-serif;
        font-size: 14px;
    }

    .alert {
        margin-top: 15px;
    }
    
    </style>
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">

    <?php include '../assets/includes/navbar.php'; ?>
    <?php include '../assets/includes/progress-bar.php'; ?>

    <div class="form-container">
        <h2>Phase 2: Title Defense</h2>
        <p><strong>Project Title:</strong> <?php echo htmlspecialchars($project['project_title']); ?></p>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['edit_success'])): ?>
            <div class="alert alert-success">Document updated successfully!</div>
        <?php endif; ?>

        <?php if ($titleDefense['pdf_file']): ?>
            <p><strong>Status:</strong> 
                <span class="status <?php echo $titleDefense['status']; ?>">
                    <?php echo ucfirst($titleDefense['status']); ?>
                </span>
            </p>

            <?php if ($titleDefense['scheduled_date']): ?>
                <p><strong>Defense Schedule:</strong> 
                    <?php echo date("F d, Y h:i A", strtotime($titleDefense['scheduled_date'])); ?>
                </p>
            <?php endif; ?>

            <p><strong>File:</strong> 
                <a href="<?php echo htmlspecialchars($titleDefense['pdf_file']); ?>" target="_blank" style="text-decoration: none;">Title Defense PDF</a>
            </p>

            <?php if ($titleDefense['remarks']): ?>
                <div class="alert alert-info mt-3">
                    <strong>Capstone Professor's Remarks:</strong><br>
                    <?php echo nl2br(htmlspecialchars($titleDefense['remarks'])); ?>
                </div>
            <?php endif; ?>

            <?php 
            // Show unsubmit button only if not scheduled and not approved
            $canUnsubmit = false;
            if ($titleDefense['status'] !== 'approved' && empty($titleDefense['scheduled_date'])) {
                $canUnsubmit = true;
            }
            
            // Show edit button if scheduled but not approved and scheduled date hasn't passed
            $canEdit = false;
            if (!empty($titleDefense['scheduled_date']) && $titleDefense['status'] !== 'approved') {
                $scheduledDateTime = strtotime($titleDefense['scheduled_date']);
                $currentTime = time();
                if ($currentTime < $scheduledDateTime) {
                    $canEdit = true;
                }
            }
            
            if ($canUnsubmit): ?>
                <form method="POST">
                    <button type="submit" name="unsubmit_file" class="btn btn-danger">Unsubmit</button>
                </form>
            <?php endif; ?>
            
            <?php if ($canEdit): ?>
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editModal">Edit Document</button>
                
                <!-- Edit Confirmation Modal -->
                <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editModalLabel">Edit Title Defense Document</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>You can upload a new version of your title defense document. The old file will be replaced.</p>
                                <form method="POST" enctype="multipart/form-data" id="editForm">
                                    <div class="mb-3">
                                        <label for="new_title_defense_file" class="form-label">New Title Defense PDF:</label>
                                        <input type="file" class="form-control" name="new_title_defense_file" id="new_title_defense_file" accept="application/pdf" required>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" form="editForm" name="edit_file" class="btn btn-warning">Update Document</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Notice based on status -->
            <div class="notice">
                <?php if ($titleDefense['status'] === 'approved'): ?>
                    <?php if (!empty($titleDefense['scheduled_date'])): ?>
                        <?php 
                        $scheduledDateTime = strtotime($titleDefense['scheduled_date']);
                        $currentTime = time();
                        if ($currentTime < $scheduledDateTime): ?>
                            Your title defense has been approved and scheduled! You can still edit your document before the scheduled date.
                        <?php else: ?>
                            <?php echo $isFourthYear ? 'You can proceed to submitting your final defense document.' : 'Final Defense becomes available when you reach 4th year.'; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php echo $isFourthYear ? 'You can proceed to submitting your final defense document.' : 'Final Defense becomes available when you reach 4th year.'; ?>
                    <?php endif; ?>
                <?php elseif (!empty($titleDefense['scheduled_date'])): ?>
                    <?php 
                    $scheduledDateTime = strtotime($titleDefense['scheduled_date']);
                    $currentTime = time();
                    if ($currentTime < $scheduledDateTime): ?>
                        Your title defense has been scheduled! You can still edit your document before the scheduled date.
                    <?php else: ?>
                        Your title defense schedule has passed. Editing is no longer available.
                    <?php endif; ?>
                <?php else: ?>
                    Thank you for submitting your title defense document! Please wait for the schedule.
                <?php endif; ?>
            </div>

            <!-- Show Next button if approved, past schedule, and student is 4th year -->
            <?php if ($titleDefense['status'] === 'approved' && !empty($titleDefense['scheduled_date'])): ?>
                <?php
                    $scheduledDateTime = strtotime($titleDefense['scheduled_date']);
                    $currentTime = time();
                    // Check student's year (only allow next step for 4th year)
                    $allowNext = false;
                    if (isset($_SESSION['user_id'])) {
                        $ys = $conn->prepare("SELECT year_section FROM students WHERE user_id = ? LIMIT 1");
                        if ($ys) {
                            $ys->bind_param("i", $_SESSION['user_id']);
                            $ys->execute();
                            $ys_res = $ys->get_result();
                            if ($ys_row = $ys_res->fetch_assoc()) {
                                $year_section = isset($ys_row['year_section']) ? $ys_row['year_section'] : '';
                                $allowNext = (strlen($year_section) > 0 && substr($year_section, 0, 1) === '4');
                            }
                            $ys->close();
                        }
                    }
                    if ($currentTime >= $scheduledDateTime && $allowNext): ?>
                        <a href="final-defense.php?project_id=<?php echo $projectId; ?>" class="btn-submit" id="next">Next: Final Defense</a>
                    <?php endif; ?>
            <?php endif; ?>

        <?php else: ?>
            <form method="POST" enctype="multipart/form-data" id="titleDefenseForm">
                <label class="file-upload" id="fileUploadLabel">
                    <input type="file" name="title_defense_file" id="titleDefenseFile" accept="application/pdf">
                    Click to upload PDF
                </label>
                <p> Please upload your title defense document. </p>
                <div class="file-name" id="fileNameDisplay"></div>
                <button type="submit" name="submit_file" class="btn-submit">Submit</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('titleDefenseFile');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        const form = document.getElementById('titleDefenseForm');

        // Display selected file name
        fileInput.addEventListener('change', function() {
            if (fileInput.files.length > 0) {
                fileNameDisplay.textContent = `Selected file: ${fileInput.files[0].name}`;
            } else {
                fileNameDisplay.textContent = '';
            }
        });

        // Validate form submission
        form.addEventListener('submit', function(event) {
            if (!fileInput.files.length) {
                event.preventDefault();
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger';
                alertDiv.textContent = 'Please select a PDF file before submitting.';
                form.insertAdjacentElement('beforebegin', alertDiv);
                setTimeout(() => alertDiv.remove(), 3000);
            }
        });

        // Auto-dismiss success alert after 3 seconds (with fade-out)
        const successAlert = document.querySelector('.alert.alert-success');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.transition = 'opacity 0.5s ease';
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.remove(), 500);
            }, 3000);
        }

        // Auto-dismiss .notice elements after 3 seconds (match final-defense behavior)
        const notices = document.querySelectorAll('.notice');
        notices.forEach(notice => {
            setTimeout(() => {
                notice.style.transition = 'opacity 0.5s ease';
                notice.style.opacity = '0';
                setTimeout(() => notice.remove(), 500);
            }, 3000);
        });
    });
</script>
<script>
    // Clean URL by removing success flags after edit while keeping project_id
    (function() {
        const url = new URL(window.location.href);
        if (url.searchParams.has('edit_success')) {
            const projectId = url.searchParams.get('project_id');
            const cleaned = new URL(window.location.origin + window.location.pathname);
            if (projectId) {
                cleaned.searchParams.set('project_id', projectId);
            }
            window.history.replaceState(null, '', cleaned.toString());
        }
    })();
</script>
</body>
</html>