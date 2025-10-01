<?php
session_start();
include '../config/database.php';
include '../assets/includes/notification_functions.php';
require_once '../assets/includes/role_functions.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check login and dean access via multi-role
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

// Fetch faculty members (support multi-role users via roles column if present)
$hasRolesColumn = false;
$rolesColumnCheck = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'roles'");
if ($rolesColumnCheck) {
    $checkRow = $rolesColumnCheck->fetch_assoc();
    $hasRolesColumn = ((int)$checkRow['c']) > 0;
    $rolesColumnCheck->close();
}

if ($hasRolesColumn) {
    $sql = "SELECT id, first_name, last_name FROM users 
            WHERE LOWER(COALESCE(role,'')) = 'faculty' 
               OR (roles IS NOT NULL AND roles <> '' AND FIND_IN_SET('faculty', REPLACE(LOWER(roles), ' ', '')) > 0)
            ORDER BY first_name, last_name";
    $facultyQuery = $conn->prepare($sql);
} else {
    $facultyQuery = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'faculty' ORDER BY first_name, last_name");
}

$facultyQuery->execute();
$facultyResult = $facultyQuery->get_result();
$facultyMembers = [];
while ($row = $facultyResult->fetch_assoc()) {
    $facultyMembers[] = $row;
}

// Handle assignment submission
$notification = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_adviser'])) {
    $projectId = $_POST['project_id'];
    $facultyId = $_POST['faculty_id'];
    
    // Get project details for notification
    $projectQuery = $conn->prepare("SELECT project_title, submitted_by FROM project_working_titles WHERE id = ?");
    $projectQuery->bind_param("i", $projectId);
    $projectQuery->execute();
    $projectResult = $projectQuery->get_result();
    $projectData = $projectResult->fetch_assoc();
    
    // Get faculty name for notification
    $facultyNameQuery = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $facultyNameQuery->bind_param("i", $facultyId);
    $facultyNameQuery->execute();
    $facultyNameResult = $facultyNameQuery->get_result();
    $facultyData = $facultyNameResult->fetch_assoc();
    $facultyName = $facultyData['first_name'] . ' ' . $facultyData['last_name'];
    
    // Update the project with assigned Capstone adviser
    $updateQuery = $conn->prepare("UPDATE project_working_titles SET noted_by = ? WHERE id = ?");
    $updateQuery->bind_param("ii", $facultyId, $projectId);
    
    if ($updateQuery->execute()) {
        // ADD NOTIFICATION HERE - Notify the student
        $studentQuery = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $studentQuery->bind_param("s", $projectData['submitted_by']);
        $studentQuery->execute();
        $studentResult = $studentQuery->get_result();
        $studentData = $studentResult->fetch_assoc();
        
        if ($studentData) {
            createNotification(
                $conn, 
                $studentData['id'], 
                'Capstone Adviser Assigned', 
                'A capstone adviser (' . htmlspecialchars($facultyName) . ') has been assigned to your project "' . htmlspecialchars($projectData['project_title']) . '".',
                'info',
                $projectId,
                'project'
            );
        }
        
        // Also notify the faculty member about the assignment
        createNotification(
            $conn, 
            $facultyId, 
            'New Project Assignment', 
            'You have been assigned as capstone adviser for the project "' . htmlspecialchars($projectData['project_title']) . '".',
            'info',
            $projectId,
            'project'
        );
        
        $notification = 'Capstone adviser assigned successfully!';
    } else {
        $notification = 'Error assigning capstone adviser: ' . $conn->error;
    }
}

// Fetch projects without assigned Capstone advisers
$unassignedQuery = $conn->prepare("
    SELECT 
        pwt.id,
        pwt.project_title,
        pwt.proponent_1,
        pwt.proponent_2,
        pwt.proponent_3,
        pwt.proponent_4,
        pwt.beneficiary,
        pwt.date_created,
        pwt.submitted_by,
        pwt.noted_by
    FROM project_working_titles pwt
    WHERE pwt.noted_by IS NULL
        AND pwt.archived = 0
        AND pwt.version = (
            SELECT MAX(pwt2.version)
            FROM project_working_titles pwt2
            WHERE pwt2.submitted_by = pwt.submitted_by
                AND pwt2.project_title = pwt.project_title
                AND pwt2.archived = 0
        )
    ORDER BY pwt.date_created DESC
");
$unassignedQuery->execute();
$unassignedResult = $unassignedQuery->get_result();

// Fetch recently assigned projects for reference
$recentlyAssignedQuery = $conn->prepare("
    SELECT 
        pwt.id,
        pwt.project_title,
        pwt.proponent_1,
        pwt.date_created,
        CONCAT(u.first_name, ' ', u.last_name) as adviser_name
    FROM project_working_titles pwt
    JOIN users u ON pwt.noted_by = u.id
    WHERE pwt.noted_by IS NOT NULL
    ORDER BY pwt.date_created DESC
    LIMIT 10
");
$recentlyAssignedQuery->execute();
$recentlyAssignedResult = $recentlyAssignedQuery->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Capstone Adviser - Captrack Vault</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <style>
        .assignment-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .assignment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .assignment-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .assignment-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(255, 255, 255, 0.2);
        }
        
        .project-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
            line-height: 1.4;
        }
        
        .project-meta {
            font-size: 0.9rem;
            opacity: 0.9;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .assignment-body {
            padding: 25px;
        }
        
        .proponents-list {
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
        }
        
        .proponents-list li {
            padding: 8px 15px;
            background: #f8f9fa;
            margin-bottom: 5px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            font-weight: 500;
        }
        
        .assignment-form {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .faculty-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            background: white;
        }
        
        .faculty-select:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .assign-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .assign-btn:hover {
            background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
            color: white;
        }
        
        .recent-assignments {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .recent-header {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            padding: 20px;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .recent-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f8f9fa;
            transition: background-color 0.2s ease;
        }
        
        .recent-item:hover {
            background-color: #f8f9fa;
        }
        
        .recent-item:last-child {
            border-bottom: none;
        }
        
        .recent-project-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .recent-meta {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            padding: 15px 20px;
            border-radius: 8px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideIn 0.4s ease, fadeOut 0.5s ease 3.5s forwards;
        }
        
        .notification.success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .notification.error {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeOut {
            to { opacity: 0; transform: translateY(-20px); }
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
        }
        
        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        @media (max-width: 768px) {
            .assignment-body {
                padding: 20px;
            }
            
            .project-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .page-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<div class="main-content">
    <?php include '../assets/includes/navbar.php'; ?>
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1 class="page-title">
                <i class="bi bi-person-plus-fill me-3"></i>
                Capstone Adviser Assignment
            </h1>
            <p class="page-subtitle">Assign capstone advisers to student research projects</p>
        </div>
    </div>
    
    <?php if ($notification): ?>
        <div class="notification <?php echo strpos($notification, 'Error') !== false ? 'error' : 'success'; ?>">
            <i class="bi bi-<?php echo strpos($notification, 'Error') !== false ? 'exclamation-circle' : 'check-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($notification); ?>
        </div>
    <?php endif; ?>

    <div class="container">
        <div class="row">
            <!-- Unassigned Projects -->
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="bi bi-clipboard-check me-2"></i>Projects Awaiting Assignment</h3>
                    <span class="badge bg-warning text-dark fs-6 px-3 py-2">
                        <?php echo $unassignedResult->num_rows; ?> pending
                    </span>
                </div>
                
                <?php if ($unassignedResult->num_rows > 0): ?>
                    <?php while ($project = $unassignedResult->fetch_assoc()): ?>
                        <div class="assignment-card">
                            <div class="assignment-header">
                                <div class="project-title">
                                    <?php echo htmlspecialchars($project['project_title']); ?>
                                </div>
                                <div class="project-meta">
                                    <span><i class="bi bi-calendar me-1"></i><?php echo date('M j, Y', strtotime($project['date_created'])); ?></span>
                                    <span><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($project['submitted_by']); ?></span>
                                </div>
                            </div>
                            <div class="assignment-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-people me-2"></i>Proponents:</h6>
                                        <ul class="proponents-list">
                                            <?php
                                            $proponents = array_filter([
                                                $project['proponent_1'],
                                                $project['proponent_2'],
                                                $project['proponent_3'],
                                                $project['proponent_4']
                                            ]);
                                            foreach ($proponents as $proponent) {
                                                echo '<li>' . htmlspecialchars($proponent) . '</li>';
                                            }
                                            ?>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-building me-2"></i>Beneficiary:</h6>
                                        <p class="mb-3"><?php echo htmlspecialchars($project['beneficiary']); ?></p>
                                        
                                        <div class="assignment-form">
                                            <form method="POST">
                                                <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">
                                                        <i class="bi bi-person-workspace me-2"></i>Assign Capstone Adviser:
                                                    </label>
                                                    <select name="faculty_id" class="faculty-select" required>
                                                        <option value="">Select a faculty member...</option>
                                                        <?php foreach ($facultyMembers as $faculty): ?>
                                                            <option value="<?php echo $faculty['id']; ?>">
                                                                <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <button type="submit" name="assign_adviser" class="assign-btn">
                                                    <i class="bi bi-check-circle me-2"></i>Assign Adviser
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="assignment-card">
                        <div class="empty-state">
                            <i class="bi bi-check-circle-fill text-success"></i>
                            <h4>All Caught Up!</h4>
                            <p>All submitted projects have been assigned capstone advisers.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recently Assigned -->
            <div class="col-lg-4">
                <div class="recent-assignments">
                    <div class="recent-header">
                        <i class="bi bi-clock-history me-2"></i>Recent Assignments
                    </div>
                    <?php if ($recentlyAssignedResult->num_rows > 0): ?>
                        <?php while ($recent = $recentlyAssignedResult->fetch_assoc()): ?>
                            <div class="recent-item">
                                <div class="recent-project-title">
                                    <?php echo htmlspecialchars(strlen($recent['project_title']) > 50 ? substr($recent['project_title'], 0, 50) . '...' : $recent['project_title']); ?>
                                </div>
                                <div class="recent-meta">
                                    <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($recent['proponent_1']); ?><br>
                                    <i class="bi bi-arrow-right me-1"></i><?php echo htmlspecialchars($recent['adviser_name']); ?><br>
                                    <i class="bi bi-calendar me-1"></i><?php echo date('M j, Y', strtotime($recent['date_created'])); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="recent-item text-center text-muted py-4">
                            <i class="bi bi-inbox display-4 mb-2"></i>
                            <p class="mb-0">No assignments yet</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Faculty Summary -->
                <div class="recent-assignments mt-4">
                    <div class="recent-header">
                        <i class="bi bi-people me-2"></i>Available Faculty
                    </div>
                    <?php foreach ($facultyMembers as $faculty): ?>
                        <div class="recent-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?></strong>
                                </div>
                                <span class="badge bg-primary rounded-pill">Faculty</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-hide notifications
document.addEventListener('DOMContentLoaded', function() {
    const notification = document.querySelector('.notification');
    if (notification) {
        setTimeout(() => {
            notification.style.display = 'none';
        }, 4000);
    }
});

// Form validation
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const select = this.querySelector('select[name="faculty_id"]');
        if (!select.value) {
            e.preventDefault();
            alert('Please select a faculty member to assign as capstone adviser.');
            select.focus();
        }
    });
});
</script>
</body>
</html>