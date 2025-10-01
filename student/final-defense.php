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

// Restrict access: only 4th year students can access final defense
$isFourthYear = true; // default allow if record missing
$ys = $conn->prepare("SELECT year_section FROM students WHERE user_id = ? LIMIT 1");
if ($ys) {
    $ys->bind_param("i", $userId);
    $ys->execute();
    $ys_res = $ys->get_result();
    if ($ys_row = $ys_res->fetch_assoc()) {
        $year_section = isset($ys_row['year_section']) ? $ys_row['year_section'] : '';
        $isFourthYear = (strlen($year_section) > 0 && substr($year_section, 0, 1) === '4');
    }
    $ys->close();
}
if (!$isFourthYear) {
    header("Location: title-defense.php?project_id=" . (isset($_GET['project_id']) ? intval($_GET['project_id']) : '') . "&error=final_defense_only_for_fourth_year");
    exit();
}

// Get project_id from GET
if (!isset($_GET['project_id'])) {
    header("Location: title-defense.php?error=no_project_found");
    exit();
}

$projectId = $_GET['project_id'];

// Fetch final defense details
$query = "SELECT * FROM final_defense WHERE project_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $projectId);
$stmt->execute();
$result = $stmt->get_result();
$finalDefense = $result->fetch_assoc();
$stmt->close();

// Fetch project details
$projectQuery = $conn->prepare("SELECT project_title FROM project_working_titles WHERE id = ?");
$projectQuery->bind_param("i", $projectId);
$projectQuery->execute();
$projectResult = $projectQuery->get_result();
$project = $projectResult->fetch_assoc();
$projectQuery->close();

// If no final defense record, create a placeholder
if (!$finalDefense) {
    $finalDefense = [
        'final_defense_pdf' => null,
        'status' => 'pending',
        'scheduled_date' => null,
        'remarks' => null
    ];
}

// Handle file submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_files']) && empty($finalDefense['final_defense_pdf'])) {
        if (!empty($_FILES['final_defense_file']['name'])) {
            $finalTmp = $_FILES['final_defense_file']['tmp_name'];
            $finalName = $_FILES['final_defense_file']['name'];

            // Validate PDF
            $allowedTypes = ['application/pdf'];
            $finalType = $_FILES['final_defense_file']['type'];

            if (
                !in_array($finalType, $allowedTypes) ||
                strtolower(pathinfo($finalName, PATHINFO_EXTENSION)) !== 'pdf'
            ) {
                $error = "Only PDF files are allowed.";
            } else {
                $finalPath = "../assets/uploads/final_defense/" . time() . "_final_" . $finalName;

                if (move_uploaded_file($finalTmp, $finalPath)) {
                    // Insert into final_defense table
                    $query = "INSERT INTO final_defense (project_id, submitted_by, final_defense_pdf, status) VALUES (?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("iis", $projectId, $userId, $finalPath);
                    if ($stmt->execute()) {
                        // Add notification for final defense submission
                        require_once '../assets/includes/notification_functions.php';
                        createNotification(
                            $conn, 
                            $userId, 
                            'Final Defense Submitted', 
                            'Your final defense for project "' . htmlspecialchars($project['project_title']) . '" has been submitted and is under review.',
                            'info',
                            $projectId,
                            'final_defense'
                        );
                        
                        header("Location: final-defense.php?project_id=$projectId&success=1");
                        exit();
                    } else {
                        $error = "Failed to save file to database.";
                    }
                    $stmt->close();
                } else {
                    $error = "Failed to upload file.";
                }
            }
        } else {
            $error = "Please upload the final defense PDF.";
        }
    }

    // Handle unsubmit
    if (isset($_POST['unsubmit_files']) && !empty($finalDefense['final_defense_pdf'])) {
        // Delete file if it exists
        if (!empty($finalDefense['final_defense_pdf']) && file_exists($finalDefense['final_defense_pdf'])) {
            unlink($finalDefense['final_defense_pdf']);
        }

        // Delete from database
        $query = "DELETE FROM final_defense WHERE project_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $projectId);
        if ($stmt->execute()) {
            header("Location: final-defense.php?project_id=$projectId&unsubmit_success=1");
            exit();
        } else {
            $error = "Failed to unsubmit file.";
        }
        $stmt->close();
    }

    // Handle edit file (UPDATE EXISTING FILE)
    if (isset($_POST['edit_file']) && !empty($finalDefense['final_defense_pdf'])) {
        if (!empty($_FILES['new_final_defense_file']['name'])) {
            // Handle common PHP upload errors early
            if (isset($_FILES['new_final_defense_file']['error']) && $_FILES['new_final_defense_file']['error'] !== UPLOAD_ERR_OK) {
                $phpError = $_FILES['new_final_defense_file']['error'];
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
                $finalTmp = $_FILES['new_final_defense_file']['tmp_name'];
                $finalName = $_FILES['new_final_defense_file']['name'];
                $finalSize = isset($_FILES['new_final_defense_file']['size']) ? (int)$_FILES['new_final_defense_file']['size'] : 0;

                // Validate PDF
                $allowedTypes = ['application/pdf'];
                $finalType = $_FILES['new_final_defense_file']['type'];

                if (
                    !in_array($finalType, $allowedTypes) ||
                    strtolower(pathinfo($finalName, PATHINFO_EXTENSION)) !== 'pdf'
                ) {
                    $error = "Only PDF files are allowed.";
                } else if ($finalSize <= 0) {
                    $error = "Uploaded file is empty or failed to transfer.";
                } else {
                    // Delete old file
                    if (file_exists($finalDefense['final_defense_pdf'])) {
                        @unlink($finalDefense['final_defense_pdf']);
                    }

                    // Upload new file
                    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $finalName);
                    $finalPath = "../assets/uploads/final_defense/" . time() . "_final_" . $safeName;
                    if (is_uploaded_file($finalTmp) && move_uploaded_file($finalTmp, $finalPath)) {
                        // Update database
                        $query = "UPDATE final_defense SET final_defense_pdf = ? WHERE project_id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("si", $finalPath, $projectId);
                        if ($stmt->execute()) {
                            header("Location: final-defense.php?project_id=$projectId&edit_success=1");
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
        /* Form layout */
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        label {
            font-size: 1rem;
            color: #333;
        }

        input[type="file"] {
            padding: 10px;
            font-size: 1rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            background-color: #f8f8f8;
            cursor: pointer;
        }

        /* Centered floating notification styles */
        .notice {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%; /* Wide but not full-screen */
            max-width: 600px; /* Limit maximum width for larger screens */
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

        /* Responsive adjustments for smaller screens */
        @media (max-width: 576px) {
            .notice {
                width: 75%; /* Slightly wider on mobile for better fit */
                font-size: 14px; /* Smaller font for mobile */
                padding: 10px 15px; /* Reduced padding for mobile */
                max-width: 100%; /* Ensure it fits within viewport */
            }
        }
        
        ul {
            padding: 0;
            list-style: none;
        }
        ul li {
            margin-bottom: 10px;
        }
        a {
            color: #007bff;
            text-decoration: none;
            transition: color 0.3s;
        }
        a:hover {
            color: #0056b3;
        }

        .btn-submit, .btn-unsubmit {
            width: 100px;
            max-width: 300px;
            font-weight: bold;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            padding: 10px 5px;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }
        .btn-submit {
            background-color: #2F8D46;
        }
        .btn-submit:hover {
            background-color: green;
        }
        .btn-unsubmit {
            background-color: #dc3545;
        }
        .btn-unsubmit:hover {
            background-color: #c82333;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        /* Make file inputs responsive on mobile */
@media (max-width: 576px) {
    input[type="file"] {
        font-size: 14px;
        padding: 8px;
        width: 100%; /* full width on small screens */
    }

    label {
        font-size: 14px;
        display: block;
        width: 100%;
        word-wrap: break-word;
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

    <div class="form-container">
        <h2>Phase 3: Final Defense</h2>
        <p><strong>Project Title:</strong> <?php echo htmlspecialchars($project['project_title']); ?></p>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <div class="notice">Files uploaded successfully!</div>
        <?php endif; ?>
        <?php if (isset($_GET['unsubmit_success'])): ?>
            <div class="notice">Files unsubmitted successfully!</div>
        <?php endif; ?>
        <?php if (isset($_GET['edit_success'])): ?>
            <div class="notice">Document updated successfully!</div>
        <?php endif; ?>

        <?php if ($finalDefense['final_defense_pdf']): ?>
            <p><strong>Status:</strong> <span class="status <?php echo $finalDefense['status']; ?>">
                <?php echo ucfirst($finalDefense['status']); ?>
            </span></p>
            <?php if ($finalDefense['scheduled_date']): ?>
                <p><strong>Defense Schedule:</strong> <?php echo date("F d, Y h:i A", strtotime($finalDefense['scheduled_date'])); ?></p>
            <?php endif; ?>
            <p><strong>File:</strong>
                <a href="<?php echo htmlspecialchars($finalDefense['final_defense_pdf']); ?>" target="_blank">Final Defense PDF</a>
            </p>        
            <?php if ($finalDefense['remarks']): ?>
                <div class="alert alert-info mt-3">
                    <strong>Capstone Professor's Remarks:</strong><br>
                    <?php echo nl2br(htmlspecialchars($finalDefense['remarks'])); ?>
                </div>
            <?php endif; ?>        

            <?php 
            // Show unsubmit button only if not scheduled and not approved
            $canUnsubmit = false;
            if ($finalDefense['status'] !== 'approved' && empty($finalDefense['scheduled_date'])) {
                $canUnsubmit = true;
            }
            
            // Show edit button if scheduled but not approved and scheduled date hasn't passed
            $canEdit = false;
            if (!empty($finalDefense['scheduled_date']) && $finalDefense['status'] !== 'approved') {
                $scheduledDateTime = strtotime($finalDefense['scheduled_date']);
                $currentTime = time();
                if ($currentTime < $scheduledDateTime) {
                    $canEdit = true;
                }
            }
            
            if ($canUnsubmit): ?>
                <!-- Unsubmit Button to Trigger Modal -->
                <button type="button" class="btn-unsubmit" data-bs-toggle="modal" data-bs-target="#unsubmitModal">Unsubmit</button>

                <!-- Unsubmit Confirmation Modal -->
                <div class="modal fade" id="unsubmitModal" tabindex="-1" aria-labelledby="unsubmitModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="unsubmitModalLabel">Confirm Unsubmit</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                Are you sure you want to unsubmit your files? This action will delete all uploaded files and cannot be undone.
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <form method="POST" style="display: inline;">
                                    <button type="submit" name="unsubmit_files" class="btn btn-danger">Unsubmit</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($canEdit): ?>
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editModal">Edit Document</button>
                
                <!-- Edit Confirmation Modal -->
                <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editModalLabel">Edit Final Defense Document</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>You can upload a new version of your final defense document. The old file will be replaced.</p>
                                <form method="POST" enctype="multipart/form-data" id="editForm">
                                    <div class="mb-3">
                                        <label for="new_final_defense_file" class="form-label">New Final Defense PDF:</label>
                                        <input type="file" class="form-control" name="new_final_defense_file" id="new_final_defense_file" accept="application/pdf" required>
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

            <div class="notice">
                <?php 
                if ($finalDefense['status'] === 'approved') {
                    if (!empty($finalDefense['scheduled_date'])) {
                        $scheduledDateTime = strtotime($finalDefense['scheduled_date']);
                        $currentTime = time();
                        if ($currentTime < $scheduledDateTime) {
                            echo "Your final defense has been approved and scheduled! You can still edit your document before the scheduled date.";
                        } else {
                            echo "Congratulations on finishing your final defense! 🎉 You can now proceed to upload your manuscript for grammar review.";
                        }
                    } else {
                        echo "Congratulations on finishing your final defense! 🎉 You can now proceed to upload your manuscript for grammar review.";
                    }
                } elseif ($finalDefense['scheduled_date']) {
                    echo "Your final defense has been scheduled! You can still edit your document before the scheduled date.";
                } else {
                    echo "✅ Thank you for submitting your final defense document! Please wait for the schedule.";
                }
                ?>
            </div>

            <?php if ($finalDefense['status'] === 'approved'): ?>
                <div class="mt-3 text-center">
                    <a href="manuscript_upload.php?project_id=<?php echo $projectId; ?>" class="btn btn-success btn-lg">
                        <i class="bi bi-file-text me-2"></i>
                        Upload Manuscript for Grammar Review
                    </a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <form method="POST" enctype="multipart/form-data">
                <label>Final Defense PDF: <input type="file" name="final_defense_file" accept="application/pdf" required></label>
                <button type="submit" name="submit_files" class="btn-submit">Submit</button>
            </form>
        <?php endif; ?>
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