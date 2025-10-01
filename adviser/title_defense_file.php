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

// Get title defense ID from URL
if (!isset($_GET['id'])) {
    header("Location: title_defense_list.php?error=missing_id");
    exit();
}
$titleDefenseId = $_GET['id'];

// Fetch title defense details with group_code
$query = "
    SELECT td.*, pw.project_title, s.group_code
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

$scheduledDate = $titleDefense['scheduled_date'];
$originalScheduledDate = $scheduledDate; // keep original to detect changes
$currentStatus = $titleDefense['status'];
$groupCode = isset($titleDefense['group_code']) ? $titleDefense['group_code'] : 'N/A'; // Fallback to 'N/A' if group_code is not set

// In the POST handling section, replace the existing code with:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'];
    $remarks = $_POST['remarks'];
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
        $query = "UPDATE title_defense SET status = ?, remarks = ?, scheduled_date = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssi", $status, $remarks, $scheduledDate, $titleDefenseId);
        
        if ($stmt->execute()) {
            // ADD NOTIFICATIONS HERE
            // Get the project owner (student who submitted)
            $ownerQuery = "SELECT td.project_id, u.id as user_id 
                           FROM title_defense td 
                           INNER JOIN project_working_titles pw ON td.project_id = pw.id 
                           INNER JOIN users u ON pw.submitted_by = u.email 
                           WHERE td.id = ?";
            $ownerStmt = $conn->prepare($ownerQuery);
            $ownerStmt->bind_param("i", $titleDefenseId);
            $ownerStmt->execute();
            $ownerResult = $ownerStmt->get_result();
            
            if ($ownerData = $ownerResult->fetch_assoc()) {
                $user_id = $ownerData['user_id'];
                $project_id = $ownerData['project_id'];
                
                // Notification for scheduling (when status changes to approved and date is set)
                if ($status === 'approved' && !empty($scheduledDate)) {
                    createNotification(
                        $conn, 
                        $user_id, 
                        'Title Defense Scheduled', 
                        'Your title defense has been scheduled for ' . date('F d, Y \\a\\t g:i A', strtotime($scheduledDate)) . '.',
                        'info',
                        $project_id,
                        'title_defense'
                    );
                    
                    createNotification(
                        $conn, 
                        $user_id, 
                        'Title Defense Approved!', 
                        'Congratulations! Your title defense has been approved. You can now proceed to final defense.',
                        'success',
                        $project_id,
                        'title_defense'
                    );
                } elseif ($status === 'rejected') {
                    createNotification(
                        $conn, 
                        $user_id, 
                        'Title Defense Requires Revision', 
                        'Your title defense requires revision. Please check the advisor comments and resubmit.',
                        'warning',
                        $project_id,
                        'title_defense'
                    );
                } elseif ($status === 'pending') {
                    createNotification(
                        $conn, 
                        $user_id, 
                        'Title Defense Under Review', 
                        'Your title defense is being reviewed by the advisor. Please wait for feedback.',
                        'info',
                        $project_id,
                        'title_defense'
                    );
                }
            }
            $ownerStmt->close();
        }
        $stmt->close();

        header("Location: title_defense_list.php?success=updated");
        exit();
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
        <h4>Review Title Defense</h4>
        <p><strong>Project Title:</strong> <?php echo htmlspecialchars($titleDefense['project_title']); ?></p>
        <p><strong>Submitted By:</strong> <?php echo htmlspecialchars($groupCode); ?></p>
        <p><strong>Status:</strong> <span class="status <?php echo $currentStatus; ?>">
            <?php echo ucfirst($currentStatus); ?>
        </span></p>

        <?php if (isset($error)): ?>
            <div class="error" style="background-color:#f8d7da;color:#721c24;padding:10px;border-radius:5px;margin-bottom:15px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <p><strong>Uploaded File:</strong>
        <a href="<?php echo htmlspecialchars($titleDefense['pdf_file']); ?>" target="_blank">View Title Defense PDF</a></p>

        <form method="POST" id="titleDefenseForm">
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
                $remarks = isset($titleDefense['remarks']) ? trim($titleDefense['remarks']) : '';
                echo !empty($remarks) ? htmlspecialchars($remarks) : '';
            ?></textarea>
            <br>
            <div class="button-group">
                <?php if ($currentStatus === 'approved' || $currentStatus === 'rejected'): ?>
                    <button type="button" class="btn btn-primary edit-button-creative" onclick="enableEditing()">
                        <i class="bi bi-pencil-square" style="font-size: 1.2rem;"></i>
                        Edit
                    </button>
                    <button type="submit" class="btn btn-success submit-button-creative" style="display: none;">
                        <i class="bi bi-check-circle" style="font-size: 1.2rem;"></i>
                        Submit
                    </button>
                    <button type="button" class="btn btn-secondary cancel-button-creative" style="display: none;" onclick="disableEditing()">
                        <i class="bi bi-x-circle" style="font-size: 1.2rem;"></i>
                        Cancel
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn btn-success submit-button-creative">
                        <i class="bi bi-check-circle" style="font-size: 1.2rem;"></i>
                        Submit
                    </button>
                    <button type="button" class="btn btn-secondary cancel-button-creative" style="display: none;" onclick="disableEditing()">
                        <i class="bi bi-x-circle" style="font-size: 1.2rem;"></i>
                        Cancel
                    </button>
                <?php endif; ?>
                <a href="view_panelist_grades.php?type=title&id=<?php echo $titleDefenseId; ?>" class="btn btn-info">
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
    var form = document.getElementById('titleDefenseForm');
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