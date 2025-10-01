<?php
// Start the session
session_start();
date_default_timezone_set('Asia/Manila');
// Check if the user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../users/login.php");
    exit();
}

include '../config/database.php';
include '../assets/includes/author_functions.php';

$email = $_SESSION['email'];
$userId = $_SESSION['user_id'];

// Determine student year
$isFourthYear = true; // default allow if unknown (non-student contexts won't use this page)
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

// Get user details
$userQuery = $conn->prepare("SELECT first_name, last_name FROM users WHERE email = ?");
$userQuery->bind_param("s", $email);
$userQuery->execute();
$userResult = $userQuery->get_result();
$userInfo = $userResult->fetch_assoc();
$userQuery->close();

$userName = $userInfo['first_name'] . ' ' . $userInfo['last_name'];

// Get project submissions count and status (group-wide by group_code)
$projectQuery = $conn->prepare("
    SELECT 
        pw.id, 
        pw.project_title,
        pw.submitted_by,
        pa.faculty_approval,
        pa.adviser_approval,
        pa.dean_approval
    FROM project_working_titles pw 
    LEFT JOIN project_approvals pa ON pw.id = pa.project_id 
    WHERE pw.submitted_by IN (
        SELECT email FROM students WHERE group_code = (
            SELECT group_code FROM students WHERE email = ? LIMIT 1
        )
    ) OR pw.submitted_by = ?
");
$projectQuery->bind_param("ss", $email, $email);
$projectQuery->execute();
$projectResult = $projectQuery->get_result();
$projects = [];
while ($row = $projectResult->fetch_assoc()) {
    $projects[] = $row;
}
$projectQuery->close();

$totalProjects = count($projects);
$approvedProjects = 0;
$pendingProjects = 0;

foreach ($projects as $project) {
    if ($project['dean_approval'] === 'approved') {
        $approvedProjects++;
    } else {
        $pendingProjects++;
    }
}

// Get capstone submission status
$capstoneQuery = $conn->prepare("SELECT status FROM capstone WHERE user_id = ? LIMIT 1");
$capstoneQuery->bind_param("i", $userId);
$capstoneQuery->execute();
$capstoneResult = $capstoneQuery->get_result();
$capstone = $capstoneResult->fetch_assoc();
$capstoneQuery->close();

$capstoneStatus = $capstone ? $capstone['status'] : 'not_submitted';

// Get defense progress for any approved project in the student's group
$defenseQuery = $conn->prepare("
    SELECT 
        pw.id AS project_id,
        td.status as title_defense_status,
        td.scheduled_date as title_defense_date,
        fd.status as final_defense_status,
        fd.scheduled_date as final_defense_date
    FROM project_working_titles pw
    LEFT JOIN title_defense td ON pw.id = td.project_id
    LEFT JOIN final_defense fd ON pw.id = fd.project_id
    WHERE (
        pw.submitted_by IN (
            SELECT email FROM students WHERE group_code = (
                SELECT group_code FROM students WHERE email = ? LIMIT 1
            )
        ) OR pw.submitted_by = ?
    )
    AND pw.id IN (
        SELECT pa.project_id FROM project_approvals pa 
        WHERE pa.dean_approval = 'approved'
    )
    ORDER BY pw.id DESC
    LIMIT 1
");
$defenseQuery->bind_param("ss", $email, $email);
$defenseQuery->execute();
$defenseResult = $defenseQuery->get_result();
$defense = $defenseResult->fetch_assoc();
$defenseQuery->close();

// Expose current project id for progress bar include
$currentProjectId = (isset($defense['project_id']) && $defense['project_id']) ? (int)$defense['project_id'] : null;

// Grammarian/manuscript review status for current project (if any)
$manuscriptStatus = null;
if ($currentProjectId) {
    $manuStmt = $conn->prepare("SELECT status FROM manuscript_reviews WHERE project_id = ? ORDER BY id DESC LIMIT 1");
    if ($manuStmt) {
        $manuStmt->bind_param("i", $currentProjectId);
        $manuStmt->execute();
        $manuRes = $manuStmt->get_result();
        if ($manuRow = $manuRes->fetch_assoc()) {
            $manuscriptStatus = $manuRow['status'];
        }
        $manuStmt->close();
    }
}

// Check if group already has a capstone submission (one per group)
$groupSubmissionCount = 0;
if (isset($_SESSION['email'])) {
    $emailForGroup = $_SESSION['email'];
    $grpQuery = "
        SELECT COUNT(*) AS submission_count
        FROM capstone c
        WHERE c.user_id IN (
            SELECT u.id
            FROM users u
            INNER JOIN students s ON u.email = s.email
            WHERE s.group_code = (
                SELECT s2.group_code FROM students s2 WHERE s2.email = ? LIMIT 1
            )
        )
    ";
    $grpStmt = mysqli_prepare($conn, $grpQuery);
    if ($grpStmt) {
        mysqli_stmt_bind_param($grpStmt, "s", $emailForGroup);
        mysqli_stmt_execute($grpStmt);
        $grpRes = mysqli_stmt_get_result($grpStmt);
        $groupSubmissionCount = (int)mysqli_fetch_assoc($grpRes)['submission_count'];
        mysqli_stmt_close($grpStmt);
    }
}

// Get bookmarks count
$bookmarkQuery = $conn->prepare("SELECT COUNT(*) as bookmark_count FROM bookmarks WHERE user_id = ?");
$bookmarkQuery->bind_param("i", $userId);
$bookmarkQuery->execute();
$bookmarkResult = $bookmarkQuery->get_result();
$bookmarkCount = $bookmarkResult->fetch_assoc()['bookmark_count'];
$bookmarkQuery->close();

// Get recent research papers (for quick access)
$recentQuery = "SELECT id, title, author, year FROM capstone WHERE status = 'verified' ORDER BY id DESC LIMIT 5";
$recentResult = $conn->query($recentQuery);
$recentPapers = [];
while ($row = $recentResult->fetch_assoc()) {
    $recentPapers[] = $row;
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
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 15px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .stat-card-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .stat-card-link:hover {
            text-decoration: none;
            color: inherit;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .progress-section {
            background: white;
            border-radius: 15px;
            padding: 2.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            height: 100%;
        }
        
        .progress-item {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .progress-item:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .progress-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }
        
        .progress {
            height: 12px;
            border-radius: 6px;
            background-color: #e9ecef;
            overflow: hidden;
        }
        
        .progress-bar {
            transition: width 1s ease-in-out;
            border-radius: 6px;
        }
        
        .recent-papers {
            background: white;
            border-radius: 15px;
            padding: 2.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            height: 100%;
        }
        
        .paper-item {
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
            transition: background 0.3s ease;
        }
        
        .paper-item:last-child {
            border-bottom: none;
        }
        
        .paper-item:hover {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin: 0 -1rem;
        }
        
        .welcome-message {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        
        .current-time {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            line-height: 1;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-not-submitted {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .overall-progress-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            margin-top: 1rem;
        }
        
        .overall-progress-number {
            font-size: 3rem;
            font-weight: bold;
            margin: 1rem 0;
        }
        
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 1.5rem 0;
            }
            
            .stat-card, .progress-section, .recent-papers {
                padding: 1.5rem;
            }
            
            .progress-item {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <?php include '../assets/includes/navbar.php'; ?>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-message mb-2" style="font-size: 2.5rem;">Welcome, <?php echo htmlspecialchars($userName); ?>!</h1>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex align-items-center justify-content-end">
                        <i class="bi bi-mortarboard-fill" style="font-size: 3rem; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php 
        // Set project_id for progress bar
        if (isset($currentProjectId)) {
            $_GET['project_id'] = $currentProjectId;
        }
        include '../assets/includes/progress-bar.php'; 
    ?>

    <div class="container-fluid">
        <!-- Upcoming Events or Notifications -->
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info" style="border-radius: 15px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                    <h5 class="alert-heading">
                        <i class="bi bi-calendar-event me-2"></i>
                        Upcoming Schedule
                    </h5>
                    <?php 
                        $hasTitle = ($defense && $defense['title_defense_date'] && $defense['title_defense_status'] !== 'approved');
                        $hasFinal = ($defense && $defense['final_defense_date'] && $defense['final_defense_status'] !== 'approved');
                    ?>
                    <?php if ($hasTitle): ?>
                        <p class="mb-2">
                            <strong>Title Defense:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($defense['title_defense_date'])); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($hasFinal): ?>
                        <p class="mb-0">
                            <strong>Final Defense:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($defense['final_defense_date'])); ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!$hasTitle && !$hasFinal): ?>
                        <p class="mb-0">No upcoming schedule at the moment.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Research Progress - Now takes up more space -->
            <div class="col-lg-8 mb-4">
                <div class="progress-section">
                    <h4 class="mb-4">
                        <i class="bi bi-graph-up text-success me-2"></i>
                        Research Progress Overview
                    </h4>
                    
                    <!-- Project Working Title Form Status -->
                    <a href="submit_research.php<?php echo isset($currentProjectId) ? ('?project_id='.(int)$currentProjectId) : ''; ?>" class="text-decoration-none d-block" style="cursor:pointer;">
                        <div class="progress-item">
                            <div class="progress-label">
                                <span class="progress-title">
                                    <i class="bi bi-file-text me-2 text-primary"></i>
                                    Project Working Title Form
                                </span>
                                <span class="status-badge status-<?php echo $totalProjects > 0 ? 'approved' : 'not-submitted'; ?>">
                                    <?php echo $totalProjects > 0 ? 'Submitted' : 'Not Submitted'; ?>
                                </span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar <?php echo $totalProjects > 0 ? 'bg-success' : 'bg-secondary'; ?>" 
                                    style="width: <?php echo $totalProjects > 0 ? '100%' : '0%'; ?>">
                                </div>
                            </div>
                        </div>
                    </a>
                    
                    <!-- Title Defense Progress -->
                    <a href="title-defense.php<?php echo isset($currentProjectId) ? ('?project_id='.(int)$currentProjectId) : ''; ?>" class="text-decoration-none d-block" style="cursor:pointer;">
                        <div class="progress-item">
                            <div class="progress-label">
                                <span class="progress-title">
                                    <i class="bi bi-presentation me-2 text-warning"></i>
                                    Title Defense
                                </span>
                                <span class="status-badge status-<?php echo ($defense && $defense['title_defense_status'] === 'approved') ? 'approved' : (($defense && $defense['title_defense_status']) ? 'pending' : 'not-submitted'); ?>">
                                    <?php 
                                    if ($defense && $defense['title_defense_status'] === 'approved') {
                                        echo 'Approved';
                                    } elseif ($defense && $defense['title_defense_status']) {
                                        echo ucfirst($defense['title_defense_status']);
                                    } else {
                                        echo 'Not Started';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar <?php echo ($defense && $defense['title_defense_status'] === 'approved') ? 'bg-success' : (($defense && $defense['title_defense_status']) ? 'bg-warning' : 'bg-secondary'); ?>" 
                                    style="width: <?php echo ($defense && $defense['title_defense_status'] === 'approved') ? '100%' : (($defense && $defense['title_defense_status']) ? '70%' : '0%'); ?>">
                                </div>
                            </div>
                        </div>
                    </a>
                    
                    <!-- Final Defense Progress (only clickable for 4th year) -->
                    <?php $finalHref = "final-defense.php" . (isset($currentProjectId) ? ('?project_id='.(int)$currentProjectId) : ''); ?>
                    <a <?php echo $isFourthYear ? ('href="' . $finalHref . '"') : ''; ?> class="text-decoration-none d-block <?php echo $isFourthYear ? '' : 'disabled'; ?>" style="cursor:<?php echo $isFourthYear ? 'pointer' : 'not-allowed'; ?>;" <?php echo $isFourthYear ? '' : 'title="Available in 4th year"'; ?> >
                        <div class="progress-item">
                            <div class="progress-label">
                                <span class="progress-title">
                                    <i class="bi bi-award me-2 text-info"></i>
                                    Final Defense
                                </span>
                                <span class="status-badge status-<?php echo ($defense && $defense['final_defense_status'] === 'approved') ? 'approved' : (($defense && $defense['final_defense_status']) ? 'pending' : 'not-submitted'); ?>">
                                    <?php 
                                    if ($defense && $defense['final_defense_status'] === 'approved') {
                                        echo 'Approved';
                                    } elseif ($defense && $defense['final_defense_status']) {
                                        echo ucfirst($defense['final_defense_status']);
                                    } else {
                                        echo $isFourthYear ? 'Not Started' : 'Locked (4th year)';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar <?php echo ($defense && $defense['final_defense_status'] === 'approved') ? 'bg-success' : (($defense && $defense['final_defense_status']) ? 'bg-warning' : ($isFourthYear ? 'bg-secondary' : 'bg-secondary')); ?>" 
                                    style="width: <?php echo ($defense && $defense['final_defense_status'] === 'approved') ? '100%' : (($defense && $defense['final_defense_status']) ? '80%' : ($isFourthYear ? '0%' : '0%')); ?>">
                                </div>
                            </div>
                        </div>
                    </a>

                    <!-- Grammarian Review Progress -->
                    <a href="manuscript_upload.php<?php echo isset($currentProjectId) ? ('?project_id='.(int)$currentProjectId) : ''; ?>" class="text-decoration-none d-block" style="cursor:pointer;">
                        <div class="progress-item">
                            <div class="progress-label">
                                <span class="progress-title">
                                    <i class="bi bi-pencil-square me-2 text-primary"></i>
                                    Grammarian
                                </span>
                                <?php 
                                $gramStatusKey = 'not-submitted';
                                $gramLabel = 'Not Started';
                                if ($manuscriptStatus) {
                                    if ($manuscriptStatus === 'approved') { $gramStatusKey = 'approved'; $gramLabel = 'Approved'; }
                                    elseif ($manuscriptStatus === 'under_review') { $gramStatusKey = 'pending'; $gramLabel = 'Under Review'; }
                                    elseif ($manuscriptStatus === 'pending') { $gramStatusKey = 'pending'; $gramLabel = 'Pending'; }
                                    elseif ($manuscriptStatus === 'rejected') { $gramStatusKey = 'rejected'; $gramLabel = 'Rejected'; }
                                }
                                ?>
                                <span class="status-badge status-<?php echo $gramStatusKey; ?>"><?php echo $gramLabel; ?></span>
                            </div>
                            <div class="progress">
                                <?php 
                                $gramClass = 'bg-secondary';
                                $gramWidth = '0%';
                                if ($manuscriptStatus === 'approved') { $gramClass = 'bg-success'; $gramWidth = '100%'; }
                                elseif ($manuscriptStatus === 'under_review') { $gramClass = 'bg-warning'; $gramWidth = '70%'; }
                                elseif ($manuscriptStatus === 'pending') { $gramClass = 'bg-warning'; $gramWidth = '40%'; }
                                elseif ($manuscriptStatus === 'rejected') { $gramClass = 'bg-danger'; $gramWidth = '10%'; }
                                ?>
                                <div class="progress-bar <?php echo $gramClass; ?>" style="width: <?php echo $gramWidth; ?>;"></div>
                            </div>
                        </div>
                    </a>

                    <!-- Submit Manuscript Progress -->
                    <a href="submit_manuscript.php<?php echo isset($currentProjectId) ? ('?project_id='.(int)$currentProjectId) : ''; ?>" class="text-decoration-none d-block" style="cursor:pointer;">
                        <div class="progress-item">
                            <div class="progress-label">
                                <span class="progress-title">
                                    <i class="bi bi-file-earmark-arrow-up me-2 text-success"></i>
                                    Submit Manuscript
                                </span>
                                <?php 
                                $submitStatusKey = 'not-submitted';
                                $submitLabel = 'Locked';
                                if ($groupSubmissionCount > 0) { $submitStatusKey = 'approved'; $submitLabel = 'Submitted'; }
                                elseif ($manuscriptStatus === 'approved') { $submitStatusKey = 'pending'; $submitLabel = 'Ready'; }
                                ?>
                                <span class="status-badge status-<?php echo $submitStatusKey; ?>"><?php echo $submitLabel; ?></span>
                            </div>
                            <div class="progress">
                                <?php 
                                $submitClass = 'bg-secondary';
                                $submitWidth = '0%';
                                if ($groupSubmissionCount > 0) { $submitClass = 'bg-success'; $submitWidth = '100%'; }
                                elseif ($manuscriptStatus === 'approved') { $submitClass = 'bg-warning'; $submitWidth = '50%'; }
                                ?>
                                <div class="progress-bar <?php echo $submitClass; ?>" style="width: <?php echo $submitWidth; ?>;"></div>
                            </div>
                        </div>
                    </a>
                    
                    <!-- Overall Progress Card -->
                    <div class="overall-progress-card">
                        <h5 class="mb-0">
                            <i class="bi bi-trophy me-2"></i>
                            Overall Research Progress
                        </h5>
                        <div class="overall-progress-number">
                            <?php 
                            $overallProgress = 0;
                            if ($totalProjects > 0) $overallProgress += 20; // Project submitted
                            if ($defense && $defense['title_defense_status'] === 'approved') $overallProgress += 20; // Title defense approved
                            if ($defense && $defense['final_defense_status'] === 'approved') $overallProgress += 20; // Final defense approved
                            if ($manuscriptStatus === 'approved') $overallProgress += 20; // Grammarian approved
                            if ($groupSubmissionCount > 0) $overallProgress += 20; // Capstone submitted
                            echo $overallProgress . '%';
                            ?>
                        </div>
                        <p class="mb-0">Complete</p>
                    </div>
                </div>
            </div>

            <!-- Recent Research Papers -->
            <div class="col-lg-4 mb-4">
                <div class="recent-papers">
                    <h4 class="mb-3">
                        <i class="bi bi-journal-text text-info me-2"></i>
                        Recent Research
                    </h4>
                    
                    <?php if (!empty($recentPapers)): ?>
                        <?php foreach ($recentPapers as $paper): ?>
                        <div class="paper-item">
                            <a href="view_research.php?id=<?php echo $paper['id']; ?>" class="text-decoration-none">
                                <h6 class="mb-1"><?php echo htmlspecialchars(substr($paper['title'], 0, 60)) . (strlen($paper['title']) > 60 ? '...' : ''); ?></h6>
                                <small class="text-muted">
                                    by <?php echo htmlspecialchars(parseAuthorData($paper['author'])); ?> 
                                    <span class="badge bg-light text-dark ms-1"><?php echo $paper['year']; ?></span>
                                </small>
                            </a>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3">
                            <a href="research_repository.php" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-arrow-right me-1"></i>Browse All Research
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-journal-x text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2">No research papers available yet.</p>
                            <a href="research_repository.php" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-search me-1"></i>Explore Repository
                            </a>
                        </div>
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
// Add some interactive animations
document.addEventListener('DOMContentLoaded', function() {
    // Animate progress bars
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.transition = 'width 1s ease-in-out';
            bar.style.width = width;
        }, 300);
    });
    
    // Add hover effects to stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
});
</script>
</body>
</html>