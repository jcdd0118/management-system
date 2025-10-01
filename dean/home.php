<?php
// Start the session
session_start();
date_default_timezone_set('Asia/Manila');
include '../config/database.php';
require_once '../assets/includes/role_functions.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_data'])) {
    header("Location: ../users/login.php");
    exit();
}

$currentUser = $_SESSION['user_data'];

// Check if user has dean role (allows multi-role access)
if (!hasRole($currentUser, 'dean')) {
    header("Location: ../users/login.php?error=unauthorized_access");
    exit();
}

// Set active role if not set or ensure dean role is active
if (!isset($_SESSION['active_role']) || $_SESSION['active_role'] !== 'dean') {
    $_SESSION['active_role'] = 'dean';
    $_SESSION['role'] = 'dean'; // For compatibility
}

// Get dean information
$dean_id = $_SESSION['user_id'];
$deanQuery = $conn->prepare("SELECT * FROM users WHERE id = ?");
$deanQuery->bind_param("i", $dean_id);
$deanQuery->execute();
$dean = $deanQuery->get_result()->fetch_assoc();

// Dashboard Statistics
$stats = [];

// Total Projects in System
$totalProjectsQuery = $conn->prepare("SELECT COUNT(*) as total FROM project_working_titles");
$totalProjectsQuery->execute();
$stats['total_projects'] = $totalProjectsQuery->get_result()->fetch_assoc()['total'];

// Projects Awaiting Dean Approval (Faculty and Adviser already approved)
$pendingDeanQuery = $conn->prepare("
    SELECT COUNT(*) as pending_dean
    FROM project_working_titles pwt
    JOIN project_approvals pa ON pwt.id = pa.project_id
    WHERE pa.faculty_approval = 'approved' 
    AND pa.adviser_approval = 'approved' 
    AND (pa.dean_approval = 'pending' OR pa.dean_approval IS NULL)
");
$pendingDeanQuery->execute();
$stats['pending_dean'] = $pendingDeanQuery->get_result()->fetch_assoc()['pending_dean'];

// Dean Approved Projects
$deanApprovedQuery = $conn->prepare("
    SELECT COUNT(*) as dean_approved
    FROM project_approvals
    WHERE dean_approval = 'approved'
");
$deanApprovedQuery->execute();
$stats['dean_approved'] = $deanApprovedQuery->get_result()->fetch_assoc()['dean_approved'];

// Total Faculty Members
$facultyQuery = $conn->prepare("SELECT COUNT(*) as total_faculty FROM users WHERE role = 'faculty'");
$facultyQuery->execute();
$stats['total_faculty'] = $facultyQuery->get_result()->fetch_assoc()['total_faculty'];

// Total Active Students
$studentsQuery = $conn->prepare("SELECT COUNT(*) as total_students FROM students WHERE status = 'verified'");
$studentsQuery->execute();
$stats['total_students'] = $studentsQuery->get_result()->fetch_assoc()['total_students'];

// Recent Project Submissions (Last 7 days)
$recentSubmissionsQuery = $conn->prepare("
    SELECT COUNT(*) as recent_submissions
    FROM project_working_titles
    WHERE date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$recentSubmissionsQuery->execute();
$stats['recent_submissions'] = $recentSubmissionsQuery->get_result()->fetch_assoc()['recent_submissions'];

// Projects by Year Section Distribution
$yearSectionQuery = $conn->prepare("
    SELECT s.year_section, COUNT(*) as count
    FROM project_working_titles pwt
    JOIN users u ON pwt.submitted_by = u.email
    JOIN students s ON u.email = s.email
    GROUP BY s.year_section
    ORDER BY s.year_section
");

if ($yearSectionQuery === false) {
    die("Prepare failed: " . $conn->error); // Check if prepare failed
}

$yearSectionQuery->execute();
$result = $yearSectionQuery->get_result();

if ($result === false) {
    die("Query failed: " . $yearSectionQuery->error); // Check for execution errors
}

$yearSectionData = [];
while ($row = $result->fetch_assoc()) {
    $yearSectionData[$row['year_section']] = $row['count'];
}

// Approval Pipeline Status
$pipelineQuery = $conn->prepare("
    SELECT 
        SUM(CASE WHEN pa.faculty_approval = 'pending' OR pa.faculty_approval IS NULL THEN 1 ELSE 0 END) as faculty_pending,
        SUM(CASE WHEN pa.faculty_approval = 'approved' AND (pa.adviser_approval = 'pending' OR pa.adviser_approval IS NULL) THEN 1 ELSE 0 END) as adviser_pending,
        SUM(CASE WHEN pa.faculty_approval = 'approved' AND pa.adviser_approval = 'approved' AND (pa.dean_approval = 'pending' OR pa.dean_approval IS NULL) THEN 1 ELSE 0 END) as dean_pending,
        SUM(CASE WHEN pa.dean_approval = 'approved' THEN 1 ELSE 0 END) as fully_approved
    FROM project_working_titles pwt
    LEFT JOIN project_approvals pa ON pwt.id = pa.project_id
");
$pipelineQuery->execute();
$pipelineData = $pipelineQuery->get_result()->fetch_assoc();

// Recent Activities for Dean Review
$recentActivitiesQuery = $conn->prepare("
    SELECT 
        pwt.project_title, 
        pwt.proponent_1, 
        pwt.date_created,
        pa.dean_approval,
        pa.faculty_approval,
        pa.adviser_approval
    FROM project_working_titles pwt
    LEFT JOIN project_approvals pa ON pwt.id = pa.project_id
    WHERE pa.faculty_approval = 'approved' AND pa.adviser_approval = 'approved'
    ORDER BY pwt.date_created DESC
    LIMIT 8
");
$recentActivitiesQuery->execute();
$recentActivities = $recentActivitiesQuery->get_result();

// Monthly Project Submissions Trend (Last 6 months)
$monthlyTrendQuery = $conn->prepare("
    SELECT 
        DATE_FORMAT(date_created, '%Y-%m') as month,
        COUNT(*) as submissions
    FROM project_working_titles 
    WHERE date_created >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date_created, '%Y-%m')
    ORDER BY month ASC
");

if ($monthlyTrendQuery === false) {
    die("Prepare failed: " . $conn->error);
}

$monthlyTrendQuery->execute();
$result = $monthlyTrendQuery->get_result();

if ($result === false) {
    die("Query failed: " . $monthlyTrendQuery->error);
}

$monthlyTrendData = [];
while ($row = $result->fetch_assoc()) {
    $monthlyTrendData[] = [
        'month' => date('M Y', strtotime($row['month'] . '-01')),
        'submissions' => $row['submissions']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dean Dashboard - Captrack Vault</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --dean-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --danger-gradient: linear-gradient(135deg, #fc466b 0%, #3f5efb 100%);
            --secondary-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --card-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }

        .dashboard-header {
            background: var(--dean-gradient);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 30px 30px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .dashboard-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }

        .welcome-text {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .subtitle-text {
            font-size: 1.2rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .stat-card {
            background: white;
            border-radius: 25px;
            padding: 2.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.4s ease;
            border: none;
            overflow: hidden;
            position: relative;
            height: 100%;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--dean-gradient);
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--hover-shadow);
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

        .stat-card.success::before { background: var(--success-gradient); }
        .stat-card.warning::before { background: var(--warning-gradient); }
        .stat-card.info::before { background: var(--info-gradient); }
        .stat-card.danger::before { background: var(--danger-gradient); }
        .stat-card.secondary::before { background: var(--secondary-gradient); }

        .stat-number {
            font-size: 3.5rem;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 1.1rem;
            font-weight: 500;
            margin-top: 0.8rem;
        }

        .stat-icon {
            font-size: 3.5rem;
            opacity: 0.1;
            position: absolute;
            right: 2rem;
            top: 2rem;
        }

        .quick-action-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            border: none;
            display: block;
            height: 100%;
        }

        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
            text-decoration: none;
            color: inherit;
        }

        .quick-action-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
        }

        .action-primary { background: var(--dean-gradient); color: white; }
        .action-success { background: var(--success-gradient); color: white; }
        .action-warning { background: var(--warning-gradient); color: white; }
        .action-info { background: var(--info-gradient); color: white; }
        .action-danger { background: var(--danger-gradient); color: white; }
        .action-secondary { background: var(--secondary-gradient); color: #2c3e50; }

        .section-card {
            background: white;
            border-radius: 25px;
            box-shadow: var(--card-shadow);
            border: none;
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 2rem;
            border-bottom: 1px solid #dee2e6;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .activity-item {
            padding: 2rem;
            border-bottom: 1px solid #f8f9fa;
            transition: all 0.2s ease;
        }

        .activity-item:hover {
            background-color: #f8f9fa;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.8rem;
            font-size: 1.1rem;
        }

        .activity-meta {
            color: #7f8c8d;
            font-size: 0.95rem;
        }

        .approval-pipeline {
            padding: 2rem;
        }

        .pipeline-step {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .pipeline-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-right: 1rem;
        }

        .pipeline-faculty { background: var(--warning-gradient); color: white; }
        .pipeline-adviser { background: var(--info-gradient); color: white; }
        .pipeline-dean { background: var(--dean-gradient); color: white; }
        .pipeline-approved { background: var(--success-gradient); color: white; }

        .status-badge {
            padding: 0.4rem 1.2rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-pending { background: linear-gradient(135deg, #ffeaa7, #fdcb6e); color: #e17055; }
        .badge-approved { background: linear-gradient(135deg, #55efc4, #00b894); color: white; }
        .badge-rejected { background: linear-gradient(135deg, #fd79a8, #e84393); color: white; }
        .badge-awaiting { background: linear-gradient(135deg, #a29bfe, #6c5ce7); color: white; }

        .trend-chart {
            padding: 2rem;
            height: 300px;
        }

        .chart-bar {
            display: flex;
            align-items: end;
            height: 200px;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .bar {
            flex: 1;
            background: var(--dean-gradient);
            border-radius: 8px 8px 0 0;
            min-height: 20px;
            position: relative;
            transition: all 0.3s ease;
        }

        .bar:hover {
            opacity: 0.8;
            transform: scaleY(1.05);
        }

        .bar-label {
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.8rem;
            color: #7f8c8d;
        }

        .bar-value {
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.9rem;
            font-weight: 600;
            color: #2c3e50;
        }

        @media (max-width: 768px) {
            .welcome-text { font-size: 2rem; }
            .stat-number { font-size: 2.8rem; }
            .dashboard-header { padding: 2rem 0; }
            .stat-card { padding: 2rem; }
        }
    </style>
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <?php include '../assets/includes/navbar.php'; ?>
    
    <!-- Role indicator if user has multiple roles -->
    <?php if (count(getUserRoles($currentUser)) > 1): ?>
        <div class="container mt-2">
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Multi-Role Access:</strong> You are currently viewing the Dean interface. 
                You can switch roles using the dropdown in the sidebar.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="welcome-text">
                        <i class="bi bi-mortarboard-fill me-3"></i>
                        Dean's Dashboard
                    </h1>
                    <p class="subtitle-text">Overseeing institutional research excellence and academic governance</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div class="text-white-75 mt-2">
                        <i class="bi bi-person-badge me-2"></i>
                        Dr. <?php echo htmlspecialchars($dean['first_name'] . ' ' . $dean['last_name']); ?>
                        <?php if (count(getUserRoles($currentUser)) > 1): ?>
                            <br><small class="opacity-75">
                                <i class="bi bi-people me-1"></i>
                                Available Roles: <?php echo implode(', ', array_map('getRoleDisplayName', getUserRoles($currentUser))); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Executive Summary Cards -->
        <div class="row g-4 mb-5">
            <div class="col-lg-2 col-md-4 col-sm-6">
                <a href="review_project.php" class="stat-card-link">
                    <div class="stat-card">
                        <i class="bi bi-files stat-icon"></i>
                        <div class="stat-number"><?php echo $stats['total_projects']; ?></div>
                        <div class="stat-label">Total Projects</div>
                    </div>
                </a>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <a href="review_project.php?status=pending" class="stat-card-link">
                    <div class="stat-card warning">
                        <i class="bi bi-exclamation-triangle-fill stat-icon"></i>
                        <div class="stat-number"><?php echo $stats['pending_dean']; ?></div>
                        <div class="stat-label">Awaiting Review</div>
                    </div>
                </a>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <a href="review_project.php?status=approved" class="stat-card-link">
                    <div class="stat-card success">
                        <i class="bi bi-check2-circle stat-icon"></i>
                        <div class="stat-number"><?php echo $stats['dean_approved']; ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                </a>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <a href="" class="stat-card-link">
                    <div class="stat-card info">
                        <i class="bi bi-people-fill stat-icon"></i>
                        <div class="stat-number"><?php echo $stats['total_faculty']; ?></div>
                        <div class="stat-label">Faculty</div>
                    </div>
                </a>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <a href="" class="stat-card-link">
                    <div class="stat-card secondary">
                        <i class="bi bi-mortarboard stat-icon"></i>
                        <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                        <div class="stat-label">Students</div>
                    </div>
                </a>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <a href="review_project.php?filter=recent" class="stat-card-link">
                    <div class="stat-card danger">
                        <i class="bi bi-clock-history stat-icon"></i>
                        <div class="stat-number"><?php echo $stats['recent_submissions']; ?></div>
                        <div class="stat-label">This Week</div>
                    </div>
                </a>
            </div>
        </div>


        <!-- Main Dashboard Content -->
        <div class="row g-4">
            <!-- Approval Pipeline -->
            <div class="col-lg-4">
                <div class="section-card h-100">
                    <div class="section-header">
                        <h4 class="section-title">
                            <i class="bi bi-diagram-3-fill me-2"></i>
                            Approval Pipeline
                        </h4>
                    </div>
                    <div class="approval-pipeline">
                        <div class="pipeline-step">
                            <div class="pipeline-icon pipeline-faculty">
                                <i class="bi bi-person-check"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold">Faculty Review</div>
                                <div class="text-muted"><?php echo isset($pipelineData['faculty_pending']) ? $pipelineData['faculty_pending'] : 0; ?> pending</div>
                            </div>
                        </div>
                        <div class="pipeline-step">
                            <div class="pipeline-icon pipeline-adviser">
                                <i class="bi bi-person-workspace"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold">Adviser Review</div>
                                <div class="text-muted"><?php echo isset($pipelineData['adviser_pending']) ? $pipelineData['adviser_pending'] : 0; ?> pending</div>
                            </div>
                        </div>
                        <div class="pipeline-step">
                            <div class="pipeline-icon pipeline-dean">
                                <i class="bi bi-mortarboard-fill"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold">Dean Approval</div>
                                <div class="text-muted"><?php echo isset($pipelineData['dean_pending']) ? $pipelineData['dean_pending'] : 0; ?> pending</div>
                            </div>
                        </div>
                        <div class="pipeline-step">
                            <div class="pipeline-icon pipeline-approved">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold">Fully Approved</div>
                                <div class="text-muted"><?php echo isset($pipelineData['fully_approved']) ? $pipelineData['fully_approved'] : 0; ?> completed</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Projects for Dean Review -->
            <div class="col-lg-8">
                <div class="section-card h-100">
                    <div class="section-header">
                        <h4 class="section-title">
                            <i class="bi bi-list-check me-2"></i>
                            Projects Awaiting Dean Review
                        </h4>
                    </div>
                    <?php if ($recentActivities->num_rows > 0): ?>
                        <?php while ($activity = $recentActivities->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="activity-title">
                                            <?php echo htmlspecialchars($activity['project_title']); ?>
                                        </div>
                                        <div class="activity-meta">
                                            <i class="bi bi-person me-1"></i>
                                            <?php echo htmlspecialchars($activity['proponent_1']); ?> •
                                            <i class="bi bi-calendar me-1"></i>
                                            <?php echo date('M j, Y', strtotime($activity['date_created'])); ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <?php if ($activity['dean_approval'] == 'approved'): ?>
                                            <span class="status-badge badge-approved">Approved</span>
                                        <?php elseif ($activity['dean_approval'] == 'rejected'): ?>
                                            <span class="status-badge badge-rejected">Rejected</span>
                                        <?php else: ?>
                                            <span class="status-badge badge-awaiting">Awaiting Review</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="activity-item text-center py-5">
                            <i class="bi bi-clipboard-check display-4 text-muted mb-3"></i>
                            <h5 class="text-muted">All caught up!</h5>
                            <p class="text-muted mb-0">No projects currently awaiting dean review.</p>
                        </div>
                    <?php endif; ?>
                    <div class="section-header border-top">
                        <a href="review_project.php" class="btn btn-outline-primary">
                            <i class="bi bi-eye me-2"></i>View All Projects
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Row -->
        <div class="row g-4 mt-2">
            <!-- Submission Trends -->
            <div class="col-lg-8">
                <div class="section-card">
                    <div class="section-header">
                        <h4 class="section-title">
                            <i class="bi bi-graph-up me-2"></i>
                            Project Submission Trends
                        </h4>
                    </div>
                    <div class="trend-chart">
                        <?php if (!empty($monthlyTrendData)): ?>
                            <div class="chart-bar">
                                <?php 
                                $maxSubmissions = max(array_column($monthlyTrendData, 'submissions'));
                                foreach ($monthlyTrendData as $data): 
                                    $height = $maxSubmissions > 0 ? ($data['submissions'] / $maxSubmissions) * 100 : 0;
                                ?>
                                    <div class="bar" style="height: <?php echo $height; ?>%;">
                                        <span class="bar-value"><?php echo $data['submissions']; ?></span>
                                        <span class="bar-label"><?php echo $data['month']; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-bar-chart display-4 text-muted mb-3"></i>
                                <h5 class="text-muted">No trend data available</h5>
                                <p class="text-muted mb-0">Submission trends will appear as data becomes available.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Year Section Distribution -->
            <div class="col-lg-4">
                <div class="section-card h-100">
                    <div class="section-header">
                        <h4 class="section-title">
                            <i class="bi bi-pie-chart-fill me-2"></i>
                            Student Distribution
                        </h4>
                    </div>
                    <div class="p-4">
                        <?php if (!empty($yearSectionData)): ?>
                        <?php foreach ($yearSectionData as $section => $count): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="fw-semibold"><?php echo htmlspecialchars($section); ?></div>
                                    <div class="badge bg-primary rounded-pill px-3 py-2">
                                        <?php echo $count; ?> students
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-people display-4 text-muted mb-3"></i>
                                <h5 class="text-muted">No data available</h5>
                                <p class="text-muted mb-0">Student distribution will appear once projects are submitted.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="row mt-5">
            <div class="col text-center text-muted">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Captrack Vault. All rights reserved.</p>
                <small>Empowering research leadership and academic oversight.</small>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
