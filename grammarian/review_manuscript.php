<?php
session_start();
include '../config/database.php';
require_once '../assets/includes/role_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/login.php");
    exit();
}

// Authorize as grammarian using multi-role support
if (!isset($_SESSION['user_data']) || !hasRole($_SESSION['user_data'], 'grammarian')) {
    header("Location: ../users/login.php?error=unauthorized_access");
    exit();
}

// Ensure active role reflects Grammarian when visiting grammarian pages
if (!isset($_SESSION['active_role']) || $_SESSION['active_role'] !== 'grammarian') {
    $_SESSION['active_role'] = 'grammarian';
    $_SESSION['role'] = 'grammarian';
}

$grammarianId = $_SESSION['user_id'];

// Get manuscript ID from URL
if (!isset($_GET['id'])) {
    header("Location: home.php?error=missing_id");
    exit();
}
$manuscriptId = $_GET['id'];

// Fetch manuscript details
$query = "
    SELECT mr.*, pw.project_title, u.first_name, u.last_name, s.group_code, u.email
    FROM manuscript_reviews mr
    INNER JOIN project_working_titles pw ON mr.project_id = pw.id
    INNER JOIN users u ON mr.student_id = u.id
    LEFT JOIN students s ON u.id = s.user_id
    WHERE mr.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $manuscriptId);
$stmt->execute();
$result = $stmt->get_result();
$manuscript = $result->fetch_assoc();
$stmt->close();

if (!$manuscript) {
    header("Location: home.php?error=invalid_id");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    
    if (isset($_POST['submit_review'])) {
        $status = $_POST['status'];
        $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
        
        
        // Test: Try a simple update first with explicit TEXT handling
        if (!empty($notes)) {
            $testQuery = "UPDATE manuscript_reviews SET grammarian_notes = ? WHERE id = ?";
            $testStmt = $conn->prepare($testQuery);
            if ($testStmt) {
                $testStmt->bind_param("si", $notes, $manuscriptId);
                if ($testStmt->execute()) {
                    // Verify immediately
                    $verifyQuery = "SELECT grammarian_notes FROM manuscript_reviews WHERE id = ?";
                    $verifyStmt = $conn->prepare($verifyQuery);
                    $verifyStmt->bind_param("i", $manuscriptId);
                    $verifyStmt->execute();
                    $verifyResult = $verifyStmt->get_result();
                    $verifyData = $verifyResult->fetch_assoc();
                    $verifyStmt->close();
                } else {
                }
                $testStmt->close();
            } else {
            }
        }
        
        // Handle file upload if provided
        $reviewedFile = null;
        if (!empty($_FILES['reviewed_file']['name'])) {
            $fileName = $_FILES['reviewed_file']['name'];
            $fileTmp = $_FILES['reviewed_file']['tmp_name'];
            $fileType = $_FILES['reviewed_file']['type'];
            
            // Validate PDF
            if ($fileType === 'application/pdf' && strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'pdf') {
                $filePath = "../assets/uploads/grammarian_reviews/" . time() . "_reviewed_" . $fileName;
                
                if (move_uploaded_file($fileTmp, $filePath)) {
                    $reviewedFile = $filePath;
                } else {
                    $error = "Failed to upload reviewed file.";
                }
            } else {
                $error = "Only PDF files are allowed.";
            }
        }
        
        if (!isset($error)) {
            // Skip the complex query if we already updated notes in the test
            if (empty($notes)) {
                // Update manuscript review without notes
                $updateQuery = "UPDATE manuscript_reviews SET 
                    status = ?, 
                    reviewed_by = ?,
                    date_reviewed = NOW()";
                
                $params = [$status, $grammarianId];
                $types = "si";
                
                if ($reviewedFile) {
                    $updateQuery .= ", grammarian_reviewed_file = ?";
                    $params[] = $reviewedFile;
                    $types .= "s";
                }
                
                $updateQuery .= " WHERE id = ?";
                $params[] = $manuscriptId;
                $types .= "i";
            } else {
                // Update manuscript review with notes (but notes already updated in test)
                $updateQuery = "UPDATE manuscript_reviews SET 
                    status = ?, 
                    reviewed_by = ?,
                    date_reviewed = NOW()";
                
                $params = [$status, $grammarianId];
                $types = "si";
                
                if ($reviewedFile) {
                    $updateQuery .= ", grammarian_reviewed_file = ?";
                    $params[] = $reviewedFile;
                    $types .= "s";
                }
                
                $updateQuery .= " WHERE id = ?";
                $params[] = $manuscriptId;
                $types .= "i";
            }
            
            $updateStmt = $conn->prepare($updateQuery);
            if ($updateStmt) {
                $updateStmt->bind_param($types, ...$params);
                
            } else {
            }
            
            if ($updateStmt && $updateStmt->execute()) {
                
                // Verify the update by querying the record again
                $verifyQuery = "SELECT grammarian_notes FROM manuscript_reviews WHERE id = ?";
                $verifyStmt = $conn->prepare($verifyQuery);
                $verifyStmt->bind_param("i", $manuscriptId);
                $verifyStmt->execute();
                $verifyResult = $verifyStmt->get_result();
                $verifyData = $verifyResult->fetch_assoc();
                $verifyStmt->close();
                
                // Create notification for student
                require_once '../assets/includes/notification_functions.php';
                $message = "Your manuscript has been " . $status . " by the grammarian.";
                if ($notes) {
                    $message .= " Notes: " . $notes;
                }
                
                createNotification(
                    $conn,
                    $manuscript['student_id'],
                    'Manuscript Review Complete',
                    $message,
                    $status === 'approved' ? 'success' : 'info',
                    $manuscript['project_id'],
                    'manuscript_review'
                );
                
                header("Location: review_manuscript.php?id=$manuscriptId&success=1");
                exit();
            } else {
                $error = "Failed to update manuscript review.";
            }
            $updateStmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Manuscript - Grammarian</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <style>
        .manuscript-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
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
        .review-form {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 600;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../assets/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <?php include '../assets/includes/navbar.php'; ?>

        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="manuscript-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2"><?php echo htmlspecialchars($manuscript['project_title']); ?></h2>
                        <p class="mb-1">
                            <i class="bi bi-person me-2"></i>
                            <?php echo htmlspecialchars($manuscript['first_name'] . ' ' . $manuscript['last_name']); ?>
                            <?php if ($manuscript['group_code']): ?>
                                <span class="badge bg-light text-dark ms-2"><?php echo htmlspecialchars($manuscript['group_code']); ?></span>
                            <?php endif; ?>
                        </p>
                        <p class="mb-0">
                            <i class="bi bi-envelope me-2"></i>
                            <?php echo htmlspecialchars($manuscript['email']); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="status-badge status-<?php echo $manuscript['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $manuscript['status'])); ?>
                        </span>
                        <p class="mt-2 mb-0">
                            <small>Submitted: <?php echo date('M d, Y', strtotime($manuscript['date_submitted'])); ?></small>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Error/Success Messages -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success auto-hide">
                    <i class="bi bi-check-circle me-2"></i>
                    Review submitted successfully! 
                    <small class="d-block mt-1">
                        Status: <?php echo htmlspecialchars($manuscript['status']); ?> | 
                        Notes Length: <?php echo strlen($manuscript['grammarian_notes'] ?: ''); ?> characters
                    </small>
                </div>
            <?php endif; ?>


            <div class="row">
                <!-- Manuscript File -->
                <div class="col-md-6">
                    <div class="review-form">
                        <h4 class="mb-3">
                            <i class="bi bi-file-text me-2"></i>
                            Student Manuscript
                        </h4>
                        
                        <?php if ($manuscript['manuscript_file']): ?>
                            <div class="file-preview has-file">
                                <i class="bi bi-file-pdf text-danger" style="font-size: 3rem;"></i>
                                <h5 class="mt-2">Manuscript PDF</h5>
                                <p class="text-muted">Click to view the manuscript</p>
                                <a href="<?php echo htmlspecialchars($manuscript['manuscript_file']); ?>" 
                                   target="_blank" class="btn btn-primary">
                                    <i class="bi bi-eye me-1"></i>
                                    View Manuscript
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="file-preview">
                                <i class="bi bi-file-text text-muted" style="font-size: 3rem;"></i>
                                <h5 class="mt-2">No manuscript uploaded</h5>
                                <p class="text-muted">The student has not uploaded a manuscript yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Review Form -->
                <div class="col-md-6">
                    <div class="review-form">
                        <h4 class="mb-3">
                            <i class="bi bi-pencil-square me-2"></i>
                            Grammar Review
                        </h4>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="status" class="form-label">Review Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="under_review" <?php echo $manuscript['status'] === 'under_review' ? 'selected' : ''; ?>>
                                        Under Review
                                    </option>
                                    <option value="approved" <?php echo $manuscript['status'] === 'approved' ? 'selected' : ''; ?>>
                                        Approved
                                    </option>
                                    <option value="rejected" <?php echo $manuscript['status'] === 'rejected' ? 'selected' : ''; ?>>
                                        Rejected
                                    </option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">
                                    Grammar Notes & Feedback 
                                    <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" id="notes" name="notes" rows="4" 
                                    placeholder="Provide detailed grammar feedback and suggestions..." required><?php echo htmlspecialchars($manuscript['grammarian_notes']); ?></textarea>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>Important:</strong> Students need feedback to improve their work. Please provide constructive comments.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="reviewed_file" class="form-label">Upload Reviewed Manuscript (Optional)</label>
                                <input type="file" class="form-control" id="reviewed_file" name="reviewed_file" 
                                    accept="application/pdf">
                                <div class="form-text">Upload the corrected manuscript with grammar improvements.</div>
                            </div>

                            <?php if ($manuscript['grammarian_reviewed_file']): ?>
                                <div class="mb-3">
                                    <label class="form-label">Previously Uploaded Review</label>
                                    <div class="file-preview has-file">
                                        <i class="bi bi-file-pdf text-success" style="font-size: 2rem;"></i>
                                        <p class="mt-2 mb-2">Grammarian Review File</p>
                                        <a href="<?php echo htmlspecialchars($manuscript['grammarian_reviewed_file']); ?>" 
                                           target="_blank" class="btn btn-success btn-sm">
                                            <i class="bi bi-download me-1"></i>
                                            Download
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="d-grid">
                                <button type="submit" name="submit_review" class="btn btn-submit" onclick="return confirmReview()">
                                    <i class="bi bi-check-circle me-2"></i>
                                    Submit Review
                                </button>
                            </div>
                            
                        </form>
                    </div>
                </div>
            </div>


            <!-- Back Button -->
            <div class="row mt-4">
                <div class="col-12">
                    <a href="home.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
function confirmReview() {
    const status = document.getElementById('status').value;
    const notes = document.getElementById('notes').value.trim();
    
    if (status === 'rejected' && !notes) {
        const proceed = confirm('You are rejecting the manuscript but haven\'t provided any feedback. Are you sure you want to proceed without giving feedback to the student?');
        if (!proceed) {
            document.getElementById('notes').focus();
            return false;
        }
    }
    
    if (status === 'approved' && !notes) {
        const proceed = confirm('You are approving the manuscript but haven\'t provided any feedback. Are you sure you want to proceed?');
        if (!proceed) {
            return false;
        }
    }
    
    return confirm('Are you sure you want to submit this review? This action cannot be undone.');
}

// Auto-hide success messages and clean URL
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide success messages
    const autoHideElements = document.querySelectorAll('.auto-hide');
    autoHideElements.forEach(function(element) {
        setTimeout(function() {
            element.style.opacity = '0';
            element.style.transition = 'opacity 0.5s ease-out';
            setTimeout(function() {
                element.remove();
            }, 500);
        }, 3000); // Hide after 3 seconds
    });
    
    // Clean URL if success parameter is present
    if (window.location.search.includes('success=')) {
        setTimeout(function() {
            const url = new URL(window.location);
            url.searchParams.delete('success');
            window.history.replaceState({}, document.title, url.pathname + url.search);
        }, 1000); // Clean URL after 1 second
    }
});
</script>
</body>
</html>
