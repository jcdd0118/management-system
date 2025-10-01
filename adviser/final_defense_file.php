<?php
// Start the session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/login.php");
    exit();
}

include '../config/database.php';
include '../assets/includes/notification_functions.php';
require_once '../assets/includes/role_functions.php';

$email = $_SESSION['email'];

// Authorize as adviser using multi-role support
if (!isset($_SESSION['user_data']) || !hasRole($_SESSION['user_data'], 'adviser')) {
    header("Location: ../users/login.php?error=unauthorized_access");
    exit();
}

// Ensure active role reflects Adviser when visiting adviser pages
if (!isset($_SESSION['active_role']) || $_SESSION['active_role'] !== 'adviser') {
    $_SESSION['active_role'] = 'adviser';
    $_SESSION['role'] = 'adviser'; // maintain compatibility with code using $_SESSION['role']
}

$adviserId = $_SESSION['user_id'];

// Get final defense ID from URL
if (!isset($_GET['id'])) {
    header("Location: final_defense_list.php?error=missing_id");
    exit();
}
$finalDefenseId = $_GET['id'];

// Fetch final defense details with group_code
$query = "
    SELECT fd.*, pw.project_title, s.group_code, u.email
    FROM final_defense fd
    INNER JOIN project_working_titles pw ON fd.project_id = pw.id
    INNER JOIN users u ON fd.submitted_by = u.id
    LEFT JOIN students s ON u.id = s.user_id
    WHERE fd.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $finalDefenseId);
$stmt->execute();
$result = $stmt->get_result();
$finalDefense = $result->fetch_assoc();
$stmt->close();

if (!$finalDefense) {
    header("Location: final_defense_list.php?error=invalid_id");
    exit();
}

$scheduledDate = isset($finalDefense['scheduled_date']) ? $finalDefense['scheduled_date'] : '';
$originalScheduledDate = $scheduledDate; // keep original to detect changes
$currentStatus = isset($finalDefense['status']) ? $finalDefense['status'] : 'pending';
$groupCode = isset($finalDefense['group_code']) ? htmlspecialchars($finalDefense['group_code']) : 'N/A';

// In the POST handling section, replace the existing code with:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = isset($_POST['status']) ? $_POST['status'] : 'pending';
    $remarks = isset($_POST['remarks']) ? $_POST['remarks'] : '';
    $newScheduledDate = isset($_POST['scheduled_date']) && $_POST['scheduled_date'] !== '' ? $_POST['scheduled_date'] : null;

    // Only block past dates if adviser is changing the schedule to a new value
    if (!empty($newScheduledDate)) {
        $newTs = strtotime($newScheduledDate);
        $origTs = $originalScheduledDate ? strtotime($originalScheduledDate) : null;
        $isChanged = ($origTs === null) || ($newTs !== $origTs);
        if ($isChanged && $newTs !== false && $newTs < time()) {
            $error = 'Scheduled date/time cannot be in the past.';
        }
    }

    // Use the new value for saving
    $scheduledDate = $newScheduledDate;

    if (!isset($error)) {
        $query = "UPDATE final_defense SET status = ?, remarks = ?, scheduled_date = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssi", $status, $remarks, $scheduledDate, $finalDefenseId);
        
        if ($stmt->execute()) {
            // ADD NOTIFICATIONS HERE
            // Get the project owner (student who submitted)
            $ownerQuery = "SELECT fd.project_id, u.id as user_id 
                           FROM final_defense fd 
                           INNER JOIN project_working_titles pw ON fd.project_id = pw.id 
                           INNER JOIN users u ON pw.submitted_by = u.email 
                           WHERE fd.id = ?";
            $ownerStmt = $conn->prepare($ownerQuery);
            $ownerStmt->bind_param("i", $finalDefenseId);
            $ownerStmt->execute();
            $ownerResult = $ownerStmt->get_result();
            
            if ($ownerData = $ownerResult->fetch_assoc()) {
                $user_id = $ownerData['user_id'];
                $project_id = $ownerData['project_id'];
                
                // Notification for scheduling and approval
                if ($status === 'approved' && !empty($scheduledDate)) {
                    createNotification(
                        $conn, 
                        $user_id, 
                        'Final Defense Scheduled', 
                        'Your final defense has been scheduled for ' . date('F d, Y \\a\\t g:i A', strtotime($scheduledDate)) . '.',
                        'info',
                        $project_id,
                        'final_defense'
                    );
                    
                    createNotification(
                        $conn, 
                        $user_id, 
                        'Final Defense Approved!', 
                        'Congratulations! You have successfully completed your final defense!',
                        'success',
                        $project_id,
                        'final_defense'
                    );
                } elseif ($status === 'rejected') {
                    createNotification(
                        $conn, 
                        $user_id, 
                        'Final Defense Requires Revision', 
                        'Your final defense requires revision. Please check the advisor comments and resubmit.',
                        'warning',
                        $project_id,
                        'final_defense'
                    );
                } elseif ($status === 'pending') {
                    createNotification(
                        $conn, 
                        $user_id, 
                        'Final Defense Under Review', 
                        'Your final defense is being reviewed by the advisor. Please wait for feedback.',
                        'info',
                        $project_id,
                        'final_defense'
                    );
                }
            }
            $ownerStmt->close();
        } else {
            $error = "Failed to update final defense details.";
        }
        $stmt->close();

        if (!isset($error)) {
            header("Location: final_defense_list.php?success=updated");
            exit();
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
    <style>
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <?php include '../assets/includes/navbar.php'; ?>
    <div class="container">
        <a href="final_defense_list.php" class="back-button-creative">
            <i class="bi bi-arrow-left-circle" style="font-size: 1.2rem;"></i>
            Back to List
        </a>
        <h4>Review Final Defense</h4>
        <p><strong>Project Title:</strong> <?php echo htmlspecialchars($finalDefense['project_title']); ?></p>
        <p><strong>Submitted By:</strong> <?php echo $groupCode; ?></p>
        <p><strong>Status:</strong> <span class="status <?php echo $currentStatus; ?>">
            <?php echo ucfirst($currentStatus); ?>
        </span></p>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <p><strong>Uploaded File:</strong>
            <?php if ($finalDefense['final_defense_pdf'] && file_exists($finalDefense['final_defense_pdf'])): ?>
                <a href="<?php echo htmlspecialchars($finalDefense['final_defense_pdf']); ?>" target="_blank">View Final Defense PDF</a>
            <?php else: ?>
                Not uploaded
            <?php endif; ?>
        </p>

        <form method="POST" id="finalDefenseForm">
            <div class="row">
                <div class="col-12 col-md-6 mb-3 mb-md-0">
                    <label for="status" class="form-label">Approval Status:</label>
                    <select name="status" id="status" class="form-select approval-status" <?php echo ($currentStatus === 'approved' || $currentStatus === 'rejected') ? 'disabled' : ''; ?> required>
                        <option value="pending" <?php echo ($currentStatus === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo ($currentStatus === 'approved') ? 'selected' : ''; ?>>Approve</option>
                        <option value="rejected" <?php echo ($currentStatus === 'rejected') ? 'selected' : ''; ?>>Reject</option>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label for="scheduled_date" class="form-label">Schedule Defense:</label>
                    <input type="datetime-local" name="scheduled_date" id="scheduled_date" class="form-control schedule-input" value="<?php echo $scheduledDate ? date('Y-m-d\\TH:i', strtotime($scheduledDate)) : ''; ?>" <?php echo ($currentStatus === 'approved' || $currentStatus === 'rejected') ? 'disabled' : ''; ?>>
                </div>
            </div>
            <br>
            <label for="remarks" class="form-label">Remarks:</label>
            <textarea name="remarks" id="remarks" class="form-control remarks-textarea" rows="4" placeholder="Add your remarks here..." <?php echo ($currentStatus === 'approved' || $currentStatus === 'rejected') ? 'disabled' : ''; ?>><?php
                $remarks = isset($finalDefense['remarks']) ? trim($finalDefense['remarks']) : '';
                echo !empty($remarks) ? htmlspecialchars($remarks) : '';
            ?></textarea>
            <br>
            <div class="button-group">
                <?php if ($currentStatus === 'approved' || $currentStatus === 'rejected'): ?>
                    <button type="button" class="btn btn-primary edit-button-creative" onclick="enableEditing()">
                        <i class="bi bi-pencil-square" style="font-size: 1.2rem;"></i>
                        Edit
                    </button>
                    <button type="submit" class="btn btn-success submit-button-creative" style="display: none;" formnovalidate>
                        <i class="bi bi-check-circle" style="font-size: 1.2rem;"></i>
                        Submit
                    </button>
                    <button type="button" class="btn btn-secondary cancel-button-creative" style="display: none;" onclick="disableEditing()">
                        <i class="bi bi-x-circle" style="font-size: 1.2rem;"></i>
                        Cancel
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn btn-success submit-button-creative" formnovalidate>
                        <i class="bi bi-check-circle" style="font-size: 1.2rem;"></i>
                        Submit
                    </button>
                    <button type="button" class="btn btn-secondary cancel-button-creative" style="display: none;" onclick="disableEditing()">
                        <i class="bi bi-x-circle" style="font-size: 1.2rem;"></i>
                        Cancel
                    </button>
                <?php endif; ?>
                <a href="view_panelist_grades.php?type=final&id=<?php echo $finalDefenseId; ?>" class="btn btn-info">
                    <i class="bi bi-clipboard-data" style="font-size: 1.2rem;"></i>
                    View Panelist Grades
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
function enableEditing() {
    document.getElementById('status').disabled = false;
    document.getElementById('scheduled_date').disabled = false;
    document.getElementById('remarks').disabled = false;
    document.querySelector('.edit-button-creative').style.display = 'none';
    document.querySelector('.submit-button-creative').style.display = 'inline-block';
    document.querySelector('.cancel-button-creative').style.display = 'inline-block';
}

function disableEditing() {
    document.getElementById('status').disabled = true;
    document.getElementById('scheduled_date').disabled = true;
    document.getElementById('remarks').disabled = true;
    document.querySelector('.edit-button-creative').style.display = 'inline-block';
    document.querySelector('.submit-button-creative').style.display = 'none';
    document.querySelector('.cancel-button-creative').style.display = 'none';
}

// Block selecting new past dates, but allow submitting unchanged original past value
document.addEventListener('DOMContentLoaded', function() {
    var input = document.getElementById('scheduled_date');
    var form = document.getElementById('finalDefenseForm');
    if (!input) return;
    input.dataset.original = input.value || '';

    var setMinToNow = function() {
        var now = new Date();
        var pad = function(n){ return n < 10 ? '0' + n : n; };
        var local = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
        input.min = local;
    };
    input.addEventListener('focus', setMinToNow);
    input.addEventListener('click', setMinToNow);

    if (form) {
        form.addEventListener('submit', function() {
            if ((input.dataset.original || '') === (input.value || '')) {
                input.removeAttribute('min');
            }
        });
    }
});
</script>
</body>
</html>