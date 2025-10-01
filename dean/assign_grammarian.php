<?php
session_start();
include '../config/database.php';
include '../assets/includes/notification_functions.php';
require_once '../assets/includes/role_functions.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check login and dean access using multi-role session
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

// Fetch grammarian members (support multi-role users via roles column if present)
$hasRolesColumn = false;
$rolesColumnCheck = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'roles'");
if ($rolesColumnCheck) {
    $checkRow = $rolesColumnCheck->fetch_assoc();
    $hasRolesColumn = ((int)$checkRow['c']) > 0;
    $rolesColumnCheck->close();
}

if ($hasRolesColumn) {
    $sql = "SELECT id, first_name, last_name FROM users 
            WHERE LOWER(COALESCE(role,'')) = 'grammarian' 
               OR (roles IS NOT NULL AND roles <> '' AND FIND_IN_SET('grammarian', REPLACE(LOWER(roles), ' ', '')) > 0)
            ORDER BY first_name, last_name";
    $grammarianQuery = $conn->prepare($sql);
} else {
    $grammarianQuery = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'grammarian' ORDER BY first_name, last_name");
}

$grammarianQuery->execute();
$grammarianResult = $grammarianQuery->get_result();
$grammarianMembers = [];
while ($row = $grammarianResult->fetch_assoc()) {
    $grammarianMembers[] = $row;
}

// Handle assignment submission
$notification = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_grammarian'])) {
    $manuscriptId = $_POST['manuscript_id'];
    $grammarianId = $_POST['grammarian_id'];
    
    // Get manuscript details for notification
    $manuscriptQuery = $conn->prepare("
        SELECT mr.id, mr.project_id, mr.student_id, pw.project_title, u.first_name, u.last_name, u.email
        FROM manuscript_reviews mr
        INNER JOIN project_working_titles pw ON mr.project_id = pw.id
        INNER JOIN users u ON mr.student_id = u.id
        WHERE mr.id = ?
    ");
    $manuscriptQuery->bind_param("i", $manuscriptId);
    $manuscriptQuery->execute();
    $manuscriptResult = $manuscriptQuery->get_result();
    $manuscriptData = $manuscriptResult->fetch_assoc();
    
    // Get grammarian name for notification
    $grammarianNameQuery = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $grammarianNameQuery->bind_param("i", $grammarianId);
    $grammarianNameQuery->execute();
    $grammarianNameResult = $grammarianNameQuery->get_result();
    $grammarianData = $grammarianNameResult->fetch_assoc();
    $grammarianName = $grammarianData['first_name'] . ' ' . $grammarianData['last_name'];
    
    // Update the manuscript with assigned grammarian
    $updateQuery = $conn->prepare("UPDATE manuscript_reviews SET reviewed_by = ?, status = 'under_review' WHERE id = ?");
    $updateQuery->bind_param("ii", $grammarianId, $manuscriptId);
    
    if ($updateQuery->execute()) {
        // Notify the student
        createNotification(
            $conn, 
            $manuscriptData['student_id'], 
            'Grammarian Assigned', 
            'A grammarian (' . htmlspecialchars($grammarianName) . ') has been assigned to review your manuscript for project "' . htmlspecialchars($manuscriptData['project_title']) . '".',
            'info',
            $manuscriptData['project_id'],
            'manuscript_review'
        );
        
        // Also notify the grammarian about the assignment
        createNotification(
            $conn, 
            $grammarianId, 
            'New Manuscript Assignment', 
            'You have been assigned to review the manuscript for project "' . htmlspecialchars($manuscriptData['project_title']) . '" by ' . htmlspecialchars($manuscriptData['first_name'] . ' ' . $manuscriptData['last_name']) . '.',
            'info',
            $manuscriptData['project_id'],
            'manuscript_review'
        );
        
        $notification = 'Grammarian assigned successfully!';
    } else {
        $notification = 'Error assigning grammarian: ' . $conn->error;
    }
}

// Fetch manuscripts without assigned grammarians
$unassignedQuery = $conn->prepare("
    SELECT 
        mr.id,
        mr.project_id,
        mr.student_id,
        mr.manuscript_file,
        mr.status,
        mr.date_submitted,
        pw.project_title,
        u.first_name,
        u.last_name,
        u.email,
        s.group_code
    FROM manuscript_reviews mr
    INNER JOIN project_working_titles pw ON mr.project_id = pw.id
    INNER JOIN users u ON mr.student_id = u.id
    LEFT JOIN students s ON u.id = s.user_id
    WHERE mr.reviewed_by IS NULL AND mr.manuscript_file IS NOT NULL
    ORDER BY mr.date_submitted DESC
");
$unassignedQuery->execute();
$unassignedResult = $unassignedQuery->get_result();

// Fetch recently assigned manuscripts for reference
$recentlyAssignedQuery = $conn->prepare("
    SELECT 
        mr.id,
        mr.project_id,
        mr.status,
        mr.date_submitted,
        pw.project_title,
        u.first_name,
        u.last_name,
        CONCAT(g.first_name, ' ', g.last_name) as grammarian_name
    FROM manuscript_reviews mr
    INNER JOIN project_working_titles pw ON mr.project_id = pw.id
    INNER JOIN users u ON mr.student_id = u.id
    INNER JOIN users g ON mr.reviewed_by = g.id
    WHERE mr.reviewed_by IS NOT NULL
    ORDER BY mr.date_submitted DESC
    LIMIT 10
");
$recentlyAssignedQuery->execute();
$recentlyAssignedResult = $recentlyAssignedQuery->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Grammarian - Captrack Vault</title>
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
        
        .student-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .assignment-form {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .grammarian-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            background: white;
        }
        
        .grammarian-select:focus {
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
                <i class="bi bi-pencil-square-fill me-3"></i>
                Grammarian Assignment
            </h1>
            <p class="page-subtitle">Assign grammarians to review student manuscripts</p>
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
            <!-- Unassigned Manuscripts -->
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="bi bi-file-text-check me-2"></i>Manuscripts Awaiting Assignment</h3>
                    <span class="badge bg-warning text-dark fs-6 px-3 py-2">
                        <?php echo $unassignedResult->num_rows; ?> pending
                    </span>
                </div>
                
                <?php if ($unassignedResult->num_rows > 0): ?>
                    <?php while ($manuscript = $unassignedResult->fetch_assoc()): ?>
                        <div class="assignment-card">
                            <div class="assignment-header">
                                <div class="project-title">
                                    <?php echo htmlspecialchars($manuscript['project_title']); ?>
                                </div>
                                <div class="project-meta">
                                    <span><i class="bi bi-calendar me-1"></i><?php echo date('M j, Y', strtotime($manuscript['date_submitted'])); ?></span>
                                    <span><i class="bi bi-file-pdf me-1"></i>Manuscript Available</span>
                                    <span class="status-badge status-<?php echo $manuscript['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $manuscript['status'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="assignment-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="student-info">
                                            <h6><i class="bi bi-person me-2"></i>Student Information:</h6>
                                            <p class="mb-1"><strong><?php echo htmlspecialchars($manuscript['first_name'] . ' ' . $manuscript['last_name']); ?></strong></p>
                                            <p class="mb-1"><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($manuscript['email']); ?></p>
                                            <?php if ($manuscript['group_code']): ?>
                                                <p class="mb-0"><i class="bi bi-people me-1"></i>Group: <?php echo htmlspecialchars($manuscript['group_code']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <a href="<?php echo htmlspecialchars($manuscript['manuscript_file']); ?>" 
                                               target="_blank" class="btn btn-outline-primary">
                                                <i class="bi bi-eye me-1"></i>View Manuscript
                                            </a>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="assignment-form">
                                            <form method="POST">
                                                <input type="hidden" name="manuscript_id" value="<?php echo $manuscript['id']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">
                                                        <i class="bi bi-pencil-square me-2"></i>Assign Grammarian:
                                                    </label>
                                                    <select name="grammarian_id" class="grammarian-select" required>
                                                        <option value="">Select a grammarian...</option>
                                                        <?php foreach ($grammarianMembers as $grammarian): ?>
                                                            <option value="<?php echo $grammarian['id']; ?>">
                                                                <?php echo htmlspecialchars($grammarian['first_name'] . ' ' . $grammarian['last_name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <button type="submit" name="assign_grammarian" class="assign-btn">
                                                    <i class="bi bi-check-circle me-2"></i>Assign Grammarian
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
                            <p>All submitted manuscripts have been assigned grammarians.</p>
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
                                    <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($recent['first_name'] . ' ' . $recent['last_name']); ?><br>
                                    <i class="bi bi-arrow-right me-1"></i><?php echo htmlspecialchars($recent['grammarian_name']); ?><br>
                                    <i class="bi bi-calendar me-1"></i><?php echo date('M j, Y', strtotime($recent['date_submitted'])); ?><br>
                                    <span class="status-badge status-<?php echo $recent['status']; ?> mt-1">
                                        <?php echo ucfirst(str_replace('_', ' ', $recent['status'])); ?>
                                    </span>
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
                
                <!-- Grammarian Summary -->
                <div class="recent-assignments mt-4">
                    <div class="recent-header">
                        <i class="bi bi-people me-2"></i>Available Grammarians
                    </div>
                    <?php if (count($grammarianMembers) > 0): ?>
                        <?php foreach ($grammarianMembers as $grammarian): ?>
                            <div class="recent-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($grammarian['first_name'] . ' ' . $grammarian['last_name']); ?></strong>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">Grammarian</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="recent-item text-center text-muted py-4">
                            <i class="bi bi-exclamation-triangle display-4 mb-2"></i>
                            <p class="mb-0">No grammarians available</p>
                            <small>Add grammarians through the admin panel</small>
                        </div>
                    <?php endif; ?>
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
        const select = this.querySelector('select[name="grammarian_id"]');
        if (!select.value) {
            e.preventDefault();
            alert('Please select a grammarian to assign for manuscript review.');
            select.focus();
        }
    });
});
</script>
</body>
</html>
