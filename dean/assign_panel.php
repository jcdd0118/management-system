<?php
session_start();
include '../config/database.php';
include '../assets/includes/notification_functions.php';
require_once '../assets/includes/role_functions.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check login and dean access using session user_data
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

$deanId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle form submission for assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'assign') {
            $panelistId = $_POST['panelist_id'];
            $groupCode = $_POST['group_code'];
            
            // Check if assignment already exists
            $checkQuery = $conn->prepare("SELECT id FROM panel_assignments WHERE panelist_id = ? AND group_code = ?");
            $checkQuery->bind_param("is", $panelistId, $groupCode);
            $checkQuery->execute();
            $checkResult = $checkQuery->get_result();
            
            if ($checkResult->num_rows > 0) {
                $message = "This panelist is already assigned to this group.";
                $messageType = "warning";
            } else {
                // Insert new assignment
                $insertQuery = $conn->prepare("INSERT INTO panel_assignments (panelist_id, group_code, assigned_by) VALUES (?, ?, ?)");
                $insertQuery->bind_param("isi", $panelistId, $groupCode, $deanId);
                
                if ($insertQuery->execute()) {
                    // ADD NOTIFICATION HERE - Get students in the group and notify them
                    $studentsQuery = $conn->prepare("
                        SELECT DISTINCT u.id as user_id 
                        FROM students s
                        INNER JOIN users u ON s.email = u.email 
                        WHERE s.group_code = ?
                    ");
                    $studentsQuery->bind_param("s", $groupCode);
                    $studentsQuery->execute();
                    $studentsResult = $studentsQuery->get_result();
                    
                    // Get panelist name
                    $panelistQuery = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                    $panelistQuery->bind_param("i", $panelistId);
                    $panelistQuery->execute();
                    $panelistResult = $panelistQuery->get_result();
                    $panelist = $panelistResult->fetch_assoc();
                    $panelistName = $panelist['first_name'] . ' ' . $panelist['last_name'];
                    
                    // Notify all students in the group
                    while ($student = $studentsResult->fetch_assoc()) {
                        createNotification(
                            $conn, 
                            $student['user_id'], 
                            'Panel Member Assigned', 
                            'A panel member (' . htmlspecialchars($panelistName) . ') has been assigned to your group (' . htmlspecialchars($groupCode) . ').',
                            'info',
                            null,
                            'panel_assignment'
                        );
                    }
                    
                    // Notify the panelist about their assignment
                    createNotification(
                        $conn,
                        $panelistId,
                        'Panel Assignment',
                        'You have been assigned as a panel member for group ' . htmlspecialchars($groupCode) . '.',
                        'info',
                        null,
                        'panel_assignment'
                    );
                    
                    $message = "Panel assignment successful!";
                    $messageType = "success";
                } else {
                    $message = "Error creating assignment: " . $conn->error;
                    $messageType = "error";
                }
            }
        } elseif ($_POST['action'] === 'remove') {
            $assignmentId = $_POST['assignment_id'];
            
            // Get assignment details before deletion for notification
            $assignmentQuery = $conn->prepare("
                SELECT pa.group_code, u.first_name, u.last_name 
                FROM panel_assignments pa
                INNER JOIN users u ON pa.panelist_id = u.id
                WHERE pa.id = ?
            ");
            $assignmentQuery->bind_param("i", $assignmentId);
            $assignmentQuery->execute();
            $assignmentResult = $assignmentQuery->get_result();
            $assignmentData = $assignmentResult->fetch_assoc();
            
            $deleteQuery = $conn->prepare("DELETE FROM panel_assignments WHERE id = ?");
            $deleteQuery->bind_param("i", $assignmentId);
            
            if ($deleteQuery->execute()) {
                // ADD NOTIFICATION HERE - Notify students about panel removal
                if ($assignmentData) {
                    $studentsQuery = $conn->prepare("
                        SELECT DISTINCT u.id as user_id 
                        FROM students s
                        INNER JOIN users u ON s.email = u.email 
                        WHERE s.group_code = ?
                    ");
                    $studentsQuery->bind_param("s", $assignmentData['group_code']);
                    $studentsQuery->execute();
                    $studentsResult = $studentsQuery->get_result();
                    
                    $panelistName = $assignmentData['first_name'] . ' ' . $assignmentData['last_name'];
                    
                    // Notify all students in the group
                    while ($student = $studentsResult->fetch_assoc()) {
                        createNotification(
                            $conn, 
                            $student['user_id'], 
                            'Panel Member Removed', 
                            'Panel member (' . htmlspecialchars($panelistName) . ') has been removed from your group (' . htmlspecialchars($assignmentData['group_code']) . ').',
                            'warning',
                            null,
                            'panel_assignment'
                        );
                    }
                }
                
                $message = "Assignment removed successfully!";
                $messageType = "success";
            } else {
                $message = "Error removing assignment: " . $conn->error;
                $messageType = "error";
            }
        }
    }
}

// Get all panelists
// Detect if users.roles column exists
$hasRolesColumn = false;
$rolesColCheck = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'roles'");
if ($rolesColCheck) {
    $row = $rolesColCheck->fetch_assoc();
    $hasRolesColumn = ((int)$row['c']) > 0;
    $rolesColCheck->close();
}

if ($hasRolesColumn) {
    // Include users whose primary role is panelist OR whose roles list contains panelist (case-insensitive, ignoring spaces)
    $panelistsSql = "SELECT id, first_name, last_name, email FROM users WHERE LOWER(role) = 'panelist' OR FIND_IN_SET('panelist', REPLACE(LOWER(COALESCE(roles,'')), ' ', '')) > 0 ORDER BY first_name, last_name";
} else {
    // Fallback for schema without roles column
    $panelistsSql = "SELECT id, first_name, last_name, email FROM users WHERE LOWER(role) = 'panelist' ORDER BY first_name, last_name";
}

$panelistsQuery = $conn->prepare($panelistsSql);
$panelistsQuery->execute();
$panelists = $panelistsQuery->get_result();

// Get all unique group codes from students
$groupsQuery = $conn->prepare("SELECT DISTINCT group_code FROM students WHERE group_code IS NOT NULL AND group_code != '' ORDER BY group_code");
$groupsQuery->execute();
$groups = $groupsQuery->get_result();

// Get existing assignments
$assignmentsQuery = $conn->prepare("
    SELECT pa.id, pa.group_code, pa.assigned_date, 
           u.first_name, u.last_name, u.email 
    FROM panel_assignments pa 
    INNER JOIN users u ON pa.panelist_id = u.id 
    WHERE pa.status = 'active' 
    ORDER BY pa.group_code, u.first_name, u.last_name
");
$assignmentsQuery->execute();
$assignments = $assignmentsQuery->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Panel - Captrack Vault</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --danger-gradient: linear-gradient(135deg, #fc466b 0%, #3f5efb 100%);
            --card-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }

        .page-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 30px 30px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            line-height: 1.2;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 300;
            margin-bottom: 0;
            line-height: 1.4;
        }

        /* Responsive Design */
        @media (max-width: 1199.98px) {
            .page-header h1 {
                font-size: 2.2rem;
            }
            
            .page-header p {
                font-size: 1rem;
            }
        }

        @media (max-width: 991.98px) {
            .page-header {
                padding: 1.5rem 0;
                text-align: center;
            }
            
            .page-header h1 {
                font-size: 2rem;
                margin-bottom: 1rem;
            }
            
            .page-header p {
                font-size: 0.95rem;
                margin-bottom: 1.5rem;
            }
            
            .page-header::before {
                width: 150px;
                height: 150px;
            }
            
            .page-header::after {
                width: 120px;
                height: 120px;
            }
        }

        @media (max-width: 767.98px) {
            .page-header {
                padding: 1.25rem 0;
                margin-bottom: 1.5rem;
                border-radius: 0 0 20px 20px;
            }
            
            .page-header h1 {
                font-size: 1.75rem;
                margin-bottom: 0.75rem;
            }
            
            .page-header h1 i {
                font-size: 1.5rem;
                margin-right: 0.5rem !important;
            }
            
            .page-header p {
                font-size: 0.9rem;
                margin-bottom: 1rem;
                padding: 0 1rem;
            }
        }

        @media (max-width: 575.98px) {
            .page-header {
                padding: 1rem 0;
                margin-bottom: 1rem;
                border-radius: 0 0 15px 15px;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }
            
            .page-header h1 i {
                font-size: 1.25rem;
                margin-right: 0.25rem !important;
            }
            
            .page-header p {
                font-size: 0.85rem;
                margin-bottom: 0.75rem;
                padding: 0 0.5rem;
                line-height: 1.3;
            }
            
            .page-header::before,
            .page-header::after {
                display: none;
            }
        }

        /* Extra small devices adjustments */
        @media (max-width: 359.98px) {
            .page-header h1 {
                font-size: 1.35rem;
            }
            
            .page-header p {
                font-size: 0.8rem;
            }
            
        }

        /* Rest of the existing styles remain the same */
        .card-modern {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: none;
            transition: all 0.3s ease;
        }

        .card-modern:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-modern {
            border-radius: 12px;
            font-weight: 600;
            padding: 0.75rem 2rem;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary-modern {
            background: var(--primary-gradient);
            color: white;
        }

        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-danger-modern {
            background: var(--danger-gradient);
            color: white;
        }

        .btn-danger-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(252, 70, 107, 0.4);
            color: white;
        }

        .assignment-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .assignment-card:hover {
            transform: translateX(5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .group-badge {
            background: var(--primary-gradient);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .alert-modern {
            border-radius: 15px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
        }

        .alert-success { background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); color: #155724; }
        .alert-warning { background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%); color: #856404; }
        .alert-danger { background: linear-gradient(135deg, #f8d7da 0%, #f1b0b7 100%); color: #721c24; }

        .table-modern {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .table-modern th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #2c3e50;
            font-weight: 600;
            border: none;
            padding: 1.25rem;
        }

        .table-modern td {
            border: none;
            padding: 1rem 1.25rem;
            vertical-align: middle;
        }

        .table-modern tbody tr {
            transition: all 0.2s ease;
        }

        .table-modern tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* Responsive table adjustments */
        @media (max-width: 767.98px) {
            .table-modern th,
            .table-modern td {
                padding: 0.75rem 0.5rem;
                font-size: 0.9rem;
            }
            
            .table-modern th {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 575.98px) {
            .table-modern th,
            .table-modern td {
                padding: 0.5rem 0.25rem;
                font-size: 0.8rem;
            }
            
            .table-modern th {
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <?php include '../assets/includes/navbar.php'; ?>
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="mb-2">
                        <i class="bi bi-people-fill me-3"></i>
                        Panel Assignment Management
                    </h1>
                    <p class="mb-0 opacity-90"></p>
                </div>
                <div class="col-lg-4 text-lg-end">
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : $messageType; ?> alert-modern" role="alert">
                <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'x-circle'); ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Assignment Form -->
            <div class="col-lg-6 mb-4">
                <div class="card-modern p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-plus-circle me-2 text-primary"></i>
                        Create New Assignment
                    </h4>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="assign">
                        
                        <div class="mb-3">
                            <label for="panelist_id" class="form-label fw-semibold">
                                <i class="bi bi-person-check me-2"></i>Select Panelist
                            </label>
                            <select name="panelist_id" id="panelist_id" class="form-select" required>
                                <option value="" disabled selected>Choose a panelist...</option>
                                <?php while ($panelist = $panelists->fetch_assoc()): ?>
                                    <option value="<?php echo $panelist['id']; ?>">
                                        <?php echo htmlspecialchars($panelist['first_name'] . ' ' . $panelist['last_name']); ?>
                                        (<?php echo htmlspecialchars($panelist['email']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="group_code" class="form-label fw-semibold">
                                <i class="bi bi-people me-2"></i>Select Group
                            </label>
                            <select name="group_code" id="group_code" class="form-select" required>
                                <option value="" disabled selected>Choose a group...</option>
                                <?php while ($group = $groups->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($group['group_code']); ?>">
                                        <?php echo htmlspecialchars($group['group_code']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary-modern btn-modern w-100">
                            <i class="bi bi-plus-circle me-2"></i>
                            Assign Panel
                        </button>
                    </form>
                </div>
            </div>

            <!-- Current Assignments -->
            <div class="col-lg-6 mb-4">
                <div class="card-modern p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-list-check me-2 text-primary"></i>
                        Current Assignments
                    </h4>
                    
                    <div style="max-height: 260px; overflow-y: auto;">
                        <?php if ($assignments->num_rows > 0): ?>
                            <?php 
                            $assignments->data_seek(0); // Reset pointer
                            $currentGroup = '';
                            while ($assignment = $assignments->fetch_assoc()): 
                                if ($currentGroup !== $assignment['group_code']):
                                    if ($currentGroup !== '') echo '</div>';
                                    $currentGroup = $assignment['group_code'];
                            ?>
                                <div class="mb-3">
                                    <div class="group-badge mb-2">
                                        <i class="bi bi-people-fill me-2"></i>
                                        Group: <?php echo htmlspecialchars($assignment['group_code']); ?>
                                    </div>
                            <?php endif; ?>
                            
                            <div class="assignment-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="bi bi-person-badge me-2"></i>
                                            <?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <i class="bi bi-envelope me-1"></i>
                                            <?php echo htmlspecialchars($assignment['email']); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar me-1"></i>
                                            Assigned: <?php echo date('M d, Y', strtotime($assignment['assigned_date'])); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this assignment?');">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                            <button type="submit" class="btn btn-danger-modern btn-sm">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-4 text-muted mb-3"></i>
                                <h5 class="text-muted">No assignments yet</h5>
                                <p class="text-muted mb-0">Start by creating your first panel assignment above.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Table -->
        <?php if ($assignments->num_rows > 0): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="table-modern">
                        <table class="table table-modern mb-0">
                            <thead>
                                <tr>
                                    <th>Group Code</th>
                                    <th>Panelist</th>
                                    <th>Email</th>
                                    <th>Assigned Date</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $assignments->data_seek(0); // Reset pointer
                                while ($assignment = $assignments->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td>
                                            <span class="fw-semibold text-primary">
                                                <?php echo htmlspecialchars($assignment['group_code']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['email']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($assignment['assigned_date'])); ?></td>
                                        <td class="text-center">
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this assignment?');">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="bi bi-trash me-1"></i>Remove
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert-modern');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    });
</script>

</body>
</html>