<?php
// Start the session
session_start();
date_default_timezone_set('Asia/Manila');
include '../config/database.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // If not logged in, redirect to the login page
    header("Location: ../users/login.php");
    exit(); // Stop further execution
}

// Ensure active role reflects Faculty when visiting faculty pages
if (!isset($_SESSION['active_role']) || $_SESSION['active_role'] !== 'faculty') {
    $_SESSION['active_role'] = 'faculty';
    $_SESSION['role'] = 'faculty';
}

// Get faculty information
$faculty_id = $_SESSION['user_id'];
$facultyQuery = $conn->prepare("SELECT * FROM users WHERE id = ?");
$facultyQuery->bind_param("i", $faculty_id);
$facultyQuery->execute();
$faculty = $facultyQuery->get_result()->fetch_assoc();

// Dashboard Statistics
$stats = [];

// Total Advisory Groups
$advisoryQuery = $conn->prepare("
    SELECT COUNT(DISTINCT s.group_code) as total_groups 
    FROM project_working_titles pwt
    JOIN users u ON pwt.submitted_by = u.email
    JOIN students s ON u.email = s.email
    WHERE pwt.noted_by = ?
");
$advisoryQuery->bind_param("i", $faculty_id);
$advisoryQuery->execute();
$stats['advisory_groups'] = $advisoryQuery->get_result()->fetch_assoc()['total_groups'];

// Projects for Review
$reviewQuery = $conn->prepare("
    SELECT COUNT(*) as pending_reviews
    FROM project_working_titles pwt
    LEFT JOIN project_approvals pa ON pwt.id = pa.project_id
    WHERE pwt.noted_by = ? AND (pa.faculty_approval = 'pending' OR pa.faculty_approval IS NULL)
");
$reviewQuery->bind_param("i", $faculty_id);
$reviewQuery->execute();
$stats['pending_reviews'] = $reviewQuery->get_result()->fetch_assoc()['pending_reviews'];

// Approved Projects
$approvedQuery = $conn->prepare("
    SELECT COUNT(*) as approved_projects
    FROM project_working_titles pwt
    JOIN project_approvals pa ON pwt.id = pa.project_id
    WHERE pwt.noted_by = ? AND pa.faculty_approval = 'approved'
");
$approvedQuery->bind_param("i", $faculty_id);
$approvedQuery->execute();
$stats['approved_projects'] = $approvedQuery->get_result()->fetch_assoc()['approved_projects'];

// Total Students Under Advisory
$studentsQuery = $conn->prepare("
    SELECT COUNT(DISTINCT s.id) as total_students
    FROM project_working_titles pwt
    JOIN users u ON pwt.submitted_by = u.email
    JOIN students s ON u.email = s.email
    WHERE pwt.noted_by = ?
");
$studentsQuery->bind_param("i", $faculty_id);
$studentsQuery->execute();
$stats['total_students'] = $studentsQuery->get_result()->fetch_assoc()['total_students'];

// Recent Activities (Last 5 project submissions)
$recentQuery = $conn->prepare("
    SELECT pwt.id AS project_id, pwt.project_title, pwt.proponent_1, pwt.date_created, pa.faculty_approval
    FROM project_working_titles pwt
    LEFT JOIN project_approvals pa ON pwt.id = pa.project_id
    WHERE pwt.noted_by = ?
    ORDER BY pwt.date_created DESC
    LIMIT 5
");
$recentQuery->bind_param("i", $faculty_id);
$recentQuery->execute();
$recentActivities = $recentQuery->get_result();

// Project Status Distribution
$statusQuery = $conn->prepare("
    SELECT 
        COALESCE(pa.faculty_approval, 'pending') as status,
        COUNT(*) as count
    FROM project_working_titles pwt
    LEFT JOIN project_approvals pa ON pwt.id = pa.project_id
    WHERE pwt.noted_by = ?
    GROUP BY COALESCE(pa.faculty_approval, 'pending')
");
$statusQuery->bind_param("i", $faculty_id);
$statusQuery->execute();
$statusDistribution = $statusQuery->get_result();
$statusData = [];
while ($row = $statusDistribution->fetch_assoc()) {
    $statusData[$row['status']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Faculty Dashboard - Captrack Vault</title>
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
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }

        .dashboard-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
            box-shadow: var(--card-shadow);
        }

        .welcome-text {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .subtitle-text {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
            position: relative;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .stat-card:hover {
            transform: translateY(-5px);
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

        .stat-card.success::before {
            background: var(--success-gradient);
        }

        .stat-card.warning::before {
            background: var(--warning-gradient);
        }

        .stat-card.info::before {
            background: var(--info-gradient);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 1rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .stat-icon {
            font-size: 3rem;
            opacity: 0.1;
            position: absolute;
            right: 1.5rem;
            top: 1.5rem;
        }

        .quick-action-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            border: none;
            display: block;
        }

        .quick-action-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--hover-shadow);
            text-decoration: none;
            color: inherit;
        }

        .quick-action-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .action-primary {
            background: var(--primary-gradient);
            color: white;
        }

        .action-success {
            background: var(--success-gradient);
            color: white;
        }

        .action-warning {
            background: var(--warning-gradient);
            color: white;
        }

        .action-info {
            background: var(--info-gradient);
            color: white;
        }

        .section-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: none;
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #dee2e6;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .activity-item {
            padding: 1.5rem 2rem;
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
            margin-bottom: 0.5rem;
        }

        .activity-meta {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 0.35rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-pending {
            background: linear-gradient(135deg, #ffeaa7, #fdcb6e);
            color: #e17055;
        }

        .badge-approved {
            background: linear-gradient(135deg, #55efc4, #00b894);
            color: white;
        }

        .badge-rejected {
            background: linear-gradient(135deg, #fd79a8, #e84393);
            color: white;
        }

        .chart-container {
            padding: 2rem;
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .progress-ring {
            width: 200px;
            height: 200px;
        }

        .progress-circle {
            transform: rotate(-90deg);
            transform-origin: center;
        }

        .chart-center-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }

        .chart-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .chart-label {
            color: #7f8c8d;
            font-size: 1rem;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .welcome-text {
                font-size: 1.8rem;
            }
            
            .stat-number {
                font-size: 2.5rem;
            }
            
            .dashboard-header {
                padding: 1.5rem 0;
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
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <h1 class="welcome-text">
                        Welcome back, <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?>!
                    </h1>
                    <p class="subtitle-text">
                        Here's what's happening with your research projects today.
                    </p>
                </div>
            </div>
        </div>
    </div>


    <div class="container">
        <!-- Statistics Cards -->
        <div class="row g-4 mb-5">
            <div class="col-lg-3 col-md-6">
                <a href="my_advisory.php" class="stat-card-link">
                    <div class="stat-card">
                        <i class="bi bi-people-fill stat-icon"></i>
                        <div class="stat-number"><?php echo $stats['advisory_groups']; ?></div>
                        <div class="stat-label">Advisory Groups</div>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="review_project.php?status=pending" class="stat-card-link">
                    <div class="stat-card warning">
                        <i class="bi bi-clock-history stat-icon"></i>
                        <div class="stat-number"><?php echo $stats['pending_reviews']; ?></div>
                        <div class="stat-label">Pending Reviews</div>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="review_project.php?status=approved" class="stat-card-link">
                    <div class="stat-card success">
                        <i class="bi bi-check-circle-fill stat-icon"></i>
                        <div class="stat-number"><?php echo $stats['approved_projects']; ?></div>
                        <div class="stat-label">Approved Projects</div>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="my_advisory.php" class="stat-card-link">
                    <div class="stat-card info">
                        <i class="bi bi-person-graduation stat-icon"></i>
                        <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                        <div class="stat-label">Total Advisees</div>
                    </div>
                </a>
            </div>
        </div>


        <!-- Main Content Area -->
        <div class="row g-4">
            <!-- Recent Activities -->
            <div class="col-lg-8">
                <div class="section-card">
                    <div class="section-header">
                        <h4 class="section-title">Recent Project Activities</h4>
                    </div>
                    <?php if ($recentActivities->num_rows > 0): ?>
                        <?php while ($activity = $recentActivities->fetch_assoc()): ?>
                            <a href="working_title_form.php?project_id=<?php echo $activity['project_id']; ?>" class="activity-item d-block text-decoration-none text-reset">
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
                                    <div>
                                        <span class="status-badge badge-<?php echo $activity['faculty_approval'] ?: 'pending'; ?>">
                                            <?php echo ucfirst($activity['faculty_approval'] ?: 'Pending'); ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="activity-item text-center py-5">
                            <i class="bi bi-inbox display-4 text-muted mb-3"></i>
                            <h5 class="text-muted">No recent activities</h5>
                            <p class="text-muted mb-0">Project activities will appear here once students submit their proposals.</p>
                        </div>
                    <?php endif; ?>
                    <div class="section-header border-top">
                        <a href="review_project.php" class="btn btn-outline-primary">View All Projects <i class="bi bi-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>

            <!-- Project Status Overview -->
            <div class="col-lg-4">
                <div class="section-card">
                    <div class="section-header">
                        <h4 class="section-title">Project Status Overview</h4>
                    </div>
                    <div class="chart-container position-relative">
                        <?php 
                        $total = array_sum($statusData);
                        if ($total > 0):
                            $pending = isset($statusData['pending']) ? $statusData['pending'] : 0;
                            $approved = isset($statusData['approved']) ? $statusData['approved'] : 0;
                            $rejected = isset($statusData['rejected']) ? $statusData['rejected'] : 0;
                            $pendingPercent = ($pending / $total) * 100;
                            $approvedPercent = ($approved / $total) * 100;
                        ?>
                            <svg class="progress-ring" viewBox="0 0 200 200">
                                <circle cx="100" cy="100" r="80" fill="none" stroke="#e9ecef" stroke-width="20"/>
                                <circle 
                                    cx="100" cy="100" r="80" 
                                    fill="none" 
                                    stroke="#f39c12" 
                                    stroke-width="20"
                                    stroke-dasharray="<?php echo $pendingPercent * 5.02; ?> 502"
                                    class="progress-circle"/>
                                <circle 
                                    cx="100" cy="100" r="80" 
                                    fill="none" 
                                    stroke="#27ae60" 
                                    stroke-width="20"
                                    stroke-dasharray="<?php echo $approvedPercent * 5.02; ?> 502"
                                    stroke-dashoffset="-<?php echo $pendingPercent * 5.02; ?>"
                                    class="progress-circle"/>
                            </svg>
                            <div class="chart-center-text">
                                <div class="chart-number"><?php echo $total; ?></div>
                                <div class="chart-label">Total Projects</div>
                            </div>
                        <?php else: ?>
                            <div class="text-center">
                                <i class="bi bi-pie-chart display-4 text-muted mb-3"></i>
                                <h5 class="text-muted">No data available</h5>
                                <p class="text-muted mb-0">Project statistics will appear here once students submit proposals.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($total > 0): ?>
                        <div class="px-3 pb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="bi bi-circle-fill text-warning me-2"></i>Pending</span>
                                <span class="fw-bold"><?php echo $pending; ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="bi bi-circle-fill text-success me-2"></i>Approved</span>
                                <span class="fw-bold"><?php echo $approved; ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span><i class="bi bi-circle-fill text-danger me-2"></i>Rejected</span>
                                <span class="fw-bold"><?php echo $rejected; ?></span>
                            </div>
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
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth hover animations
    const cards = document.querySelectorAll('.stat-card, .quick-action-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // Animate numbers on load
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach(number => {
        const target = parseInt(number.textContent);
        let current = 0;
        const increment = target / 20;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            number.textContent = Math.floor(current);
        }, 50);
    });
});
</script>
</body>
</html>