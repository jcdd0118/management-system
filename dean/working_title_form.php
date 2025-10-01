<?php
// Start session
session_start();
include '../config/database.php';
include '../assets/includes/notification_functions.php';
require_once '../assets/includes/role_functions.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check login and dean access via multi-role session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_data'])) {
    header("Location: ../users/login.php");
    exit();
}
if (!hasRole($_SESSION['user_data'], 'dean')) {
    header("Location: ../users/login.php?error=unauthorized_access");
    exit();
}

// Ensure active role is dean
if (!isset($_SESSION['active_role']) || $_SESSION['active_role'] !== 'dean') {
    $_SESSION['active_role'] = 'dean';
    $_SESSION['role'] = 'dean';
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}

// Fetch the project ID from the request
if (!isset($_GET['project_id'])) {
    die("Invalid request: Missing project ID.");
}
$projectID = $_GET['project_id'];

// Fetch the project details
$projectQuery = $conn->prepare("
    SELECT 
        p.*, 
        pa.dean_approval, 
        pa.dean_comments 
    FROM project_working_titles p
    LEFT JOIN project_approvals pa ON p.id = pa.project_id
    WHERE p.id = ?
");
$projectQuery->bind_param("i", $projectID);
$projectQuery->execute();
$projectResult = $projectQuery->get_result();
if ($projectResult->num_rows === 0) {
    die("No project found with the given ID.");
}
$project = $projectResult->fetch_assoc();

// Handle form submission (approval/rejection)
$notification = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $project['dean_approval'] !== 'approved') {
    $approvalStatus = $_POST['approval_status'];
    $comments = $_POST['comments'];

    // Check if an approval record exists for the project
    $checkApprovalQuery = $conn->prepare("
        SELECT project_id FROM project_approvals WHERE project_id = ?
    ");
    $checkApprovalQuery->bind_param("i", $projectID);
    $checkApprovalQuery->execute();
    $checkApprovalResult = $checkApprovalQuery->get_result();

    if ($checkApprovalResult->num_rows > 0) {
        // Update the existing approval record
        $approvalQuery = $conn->prepare("
            UPDATE project_approvals 
            SET dean_approval = ?, dean_comments = ? 
            WHERE project_id = ?
        ");
        $approvalQuery->bind_param("ssi", $approvalStatus, $comments, $projectID);
    } else {
        // Insert a new approval record
        $approvalQuery = $conn->prepare("
            INSERT INTO project_approvals (project_id, dean_approval, dean_comments)
            VALUES (?, ?, ?)
        ");
        $approvalQuery->bind_param("iss", $projectID, $approvalStatus, $comments);
    }

    if ($approvalQuery->execute()) {
        // ADD NOTIFICATIONS HERE
        // Get the project owner (student who submitted)
        $ownerQuery = "SELECT u.id as user_id 
                       FROM project_working_titles pw 
                       INNER JOIN users u ON pw.submitted_by = u.email 
                       WHERE pw.id = ?";
        $ownerStmt = $conn->prepare($ownerQuery);
        $ownerStmt->bind_param("i", $projectID);
        $ownerStmt->execute();
        $ownerResult = $ownerStmt->get_result();
        
        if ($ownerData = $ownerResult->fetch_assoc()) {
            $user_id = $ownerData['user_id'];
            
            if ($approvalStatus === 'approved') {
                createNotification(
                    $conn, 
                    $user_id, 
                    'Project Fully Approved!', 
                    'Congratulations! Your project "' . htmlspecialchars($project['project_title']) . '" has been fully approved by the dean. You can now proceed to the next phase.',
                    'success',
                    $projectID,
                    'project'
                );
                $notification = 'Project approved.';
            } elseif ($approvalStatus === 'rejected') {
                createNotification(
                    $conn, 
                    $user_id, 
                    'Project Rejected by Dean', 
                    'Your project "' . htmlspecialchars($project['project_title']) . '" has been rejected by the dean. Please check the comments and revise accordingly.',
                    'danger',
                    $projectID,
                    'project'
                );
                $notification = 'Project rejected. The student will be notified.';
            } else {
                createNotification(
                    $conn, 
                    $user_id, 
                    'Project Under Dean Review', 
                    'Your project "' . htmlspecialchars($project['project_title']) . '" is being reviewed by the dean. Please wait for feedback.',
                    'info',
                    $projectID,
                    'project'
                );
                $notification = 'Project set to pending with comments.';
            }
        }
        $ownerStmt->close();
        
        // Refresh the project data after update
        $projectQuery->execute();
        $projectResult = $projectQuery->get_result();
        $project = $projectResult->fetch_assoc();
    } else {
        $notification = 'Error processing approval: ' . $conn->error;
    }
    $approvalQuery->close();
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
    <link href="../assets/css/project.css" rel="stylesheet">
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">

    <?php include '../assets/includes/navbar.php'; ?>
    <div class="container">
    <a href="review_project.php" class="back-button-creative">
        <i class="bi bi-arrow-left-circle" style="font-size: 1.2rem;"></i>
        Back to List
    </a>

        <?php if ($notification): ?>
            <div class="notification <?php echo strpos($notification, 'Error') !== false ? 'error' : ''; ?>">
                <?php echo htmlspecialchars($notification); ?>
            </div>
        <?php endif; ?>

        <h4><?php echo htmlspecialchars($project['project_title']); ?> 
            <span class="badge bg-info ms-2">Version <?php echo htmlspecialchars($project['version']); ?></span>
            <button type="button" class="btn btn-outline-secondary btn-sm ms-2" onclick="showVersionHistory(<?php echo $projectID; ?>)">
                <i class="bi bi-clock-history"></i> View History
            </button>
        </h4>
        <div class="proponents-container">
            <strong>Proponents:</strong>
            <ul class="proponents-list">
                <?php
                $proponents = array_filter([
                    htmlspecialchars($project['proponent_1']),
                    htmlspecialchars($project['proponent_2']),
                    htmlspecialchars($project['proponent_3']),
                    htmlspecialchars($project['proponent_4'])
                ], 'strlen'); // Remove empty values
                foreach ($proponents as $proponent) {
                    echo '<li>' . $proponent . '</li>';
                }
                ?>
            </ul>
        </div>
        <p><strong>Beneficiary:</strong> <?php echo htmlspecialchars($project['beneficiary']); ?></p>
        <p><strong>Focal Person:</strong> <?php echo htmlspecialchars($project['focal_person']); ?></p>
        <p><strong>Position:</strong> <?php echo htmlspecialchars($project['position']); ?></p>
        <p><strong>Address:</strong> <?php echo htmlspecialchars($project['address']); ?></p>
        <hr>

        <?php if ($project['dean_approval'] === 'approved'): ?>
            <p><strong>Status:</strong> <span style="color: green; font-weight: bold;">Approved</span></p>
            <p><strong>Comments:</strong> <?php echo nl2br(htmlspecialchars($project['dean_comments'])); ?></p>
        <?php else: ?>
            <p><strong>Status:</strong> <span style="color: <?php echo $project['dean_approval'] === 'rejected' ? 'red' : 'orange'; ?>; font-weight: bold;">
                <?php echo ucfirst(isset($project['dean_approval']) ? $project['dean_approval'] : 'Pending'); ?>
            </span></p>
            <form method="POST">
                <label for="approval_status" class="form-label">Approval Status:</label>
                <select name="approval_status" id="approval_status" class="approval-status" required>
                    <option value="" disabled <?php echo !$project['dean_approval'] ? 'selected' : ''; ?>>Select an option</option>
                    <option value="pending" <?php echo $project['dean_approval'] === 'pending' ? 'selected' : ''; ?>>Re-Pending with Comment</option>
                    <option value="approved" <?php echo $project['dean_approval'] === 'approved' ? 'selected' : ''; ?>>Approve</option>
                    <option value="rejected" <?php echo $project['dean_approval'] === 'rejected' ? 'selected' : ''; ?>>Reject</option>
                </select>
                <br><br>
                <label for="comments" class="form-label">Comments:</label>
                <textarea name="comments" id="comments" class="comments-textarea" placeholder="Add your comments here..."><?php echo htmlspecialchars(isset($project['dean_comments']) ? $project['dean_comments'] : ''); ?></textarea>
                <br><br>
                <button type="submit" class="submit-button-creative">
                    <i class="bi bi-check-circle" style="font-size: 1.2rem;"></i>
                    Submit
                </button>
            </form>
        <?php endif; ?>
    </div>

</div>

<!-- Version History Modal -->
<div class="modal fade" id="versionHistoryModal" tabindex="-1" aria-labelledby="versionHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="versionHistoryModalLabel">Project Version History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="versionHistoryContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    // Automatically remove notification after 2 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const notification = document.querySelector('.notification');
        if (notification) {
            setTimeout(() => {
                notification.style.display = 'none';
            }, 2000);
        }
    });

    // Version history functionality
    function showVersionHistory(projectId) {
        const modal = new bootstrap.Modal(document.getElementById('versionHistoryModal'));
        const content = document.getElementById('versionHistoryContent');
        
        // Show loading
        content.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        modal.show();
        
        // Fetch version history
        fetch('../assets/includes/get_version_history.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_version_history&project_id=${projectId}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<div class="version-history">';
                data.versions.forEach(version => {
                    const isCurrent = version.id == projectId;
                    html += `
                        <div class="version-item ${isCurrent ? 'current' : ''}">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6>Version ${version.version} ${isCurrent ? '(Current)' : ''}</h6>
                                    <small class="text-muted">Submitted: ${version.date_created}</small>
                                </div>
                                <div>
                                    ${isCurrent ? '<span class="badge bg-primary">Current</span>' : ''}
                                    ${version.archived ? '<span class="badge bg-secondary">Archived</span>' : ''}
                                </div>
                            </div>
                            <div class="version-details mt-2">
                                <p><strong>Title:</strong> ${version.project_title}</p>
                                <p><strong>Proponents:</strong> ${version.proponents}</p>
                                <p><strong>Beneficiary:</strong> ${version.beneficiary}</p>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Error loading version history: ' + data.message + '</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error loading version history: ' + error.message + '</div>';
        });
    }
</script>

<style>
.version-history {
    max-height: 400px;
    overflow-y: auto;
}

.version-item {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    background: #f8f9fa;
}

.version-item.current {
    border-color: #0d6efd;
    background: #e7f1ff;
}

.version-details {
    font-size: 0.9rem;
}

.version-details p {
    margin-bottom: 5px;
}
</style>
</body>
</html>