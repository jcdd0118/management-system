<?php
// Start the session
session_start();
date_default_timezone_set('Asia/Manila');
// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/login.php");
    exit();
}

include '../config/database.php';
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

// Basic user info for greeting
$adviserId = $_SESSION['user_id'];
$userData = $_SESSION['user_data'];
$userName = (isset($userData['first_name']) && isset($userData['last_name']))
    ? ($userData['first_name'] . ' ' . $userData['last_name'])
    : (isset($_SESSION['name']) ? $_SESSION['name'] : 'User');

// Dashboard Statistics Queries

// 1. Total projects awaiting adviser approval
$pendingProjectsQuery = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM project_working_titles p
    INNER JOIN project_approvals pa ON p.id = pa.project_id
    WHERE pa.faculty_approval = 'approved' AND pa.adviser_approval = 'pending'
");
if ($pendingProjectsQuery === false) {
    die("Prepare failed: " . $conn->error);
}
$pendingProjectsQuery->execute();
$pendingProjects = $pendingProjectsQuery->get_result()->fetch_assoc()['count'];
$pendingProjectsQuery->close();

// 2. Total approved projects
$approvedProjectsQuery = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM project_approvals 
    WHERE adviser_approval = 'approved'
");
$approvedProjectsQuery->execute();
$approvedProjects = $approvedProjectsQuery->get_result()->fetch_assoc()['count'];
$approvedProjectsQuery->close();

// 3. Title defense submissions pending review
$pendingTitleDefenseQuery = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM title_defense 
    WHERE status = 'pending'
");
$pendingTitleDefenseQuery->execute();
$pendingTitleDefense = $pendingTitleDefenseQuery->get_result()->fetch_assoc()['count'];
$pendingTitleDefenseQuery->close();

// 4. Final defense submissions pending review
$pendingFinalDefenseQuery = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM final_defense 
    WHERE status = 'pending'
");
$pendingFinalDefenseQuery->execute();
$pendingFinalDefense = $pendingFinalDefenseQuery->get_result()->fetch_assoc()['count'];
$pendingFinalDefenseQuery->close();

// 5. Recent activities - last 5 project approvals
$recentActivitiesQuery = $conn->prepare("
    SELECT p.id as project_id, p.project_title, pa.adviser_approval, p.proponent_1
    FROM project_working_titles p
    INNER JOIN project_approvals pa ON p.id = pa.project_id
    WHERE pa.adviser_approval IS NOT NULL
    LIMIT 5
");
$recentActivitiesQuery->execute();
$recentActivities = $recentActivitiesQuery->get_result();

// 6. Upcoming scheduled defenses (next 7 days)
$upcomingDefensesQuery = $conn->prepare("
    SELECT 'Title Defense' as defense_type, td.id as defense_id, td.scheduled_date, pw.project_title, s.group_code
    FROM title_defense td
    INNER JOIN project_working_titles pw ON td.project_id = pw.id
    INNER JOIN users u ON td.submitted_by = u.id
    LEFT JOIN students s ON u.id = s.user_id
    WHERE td.status = 'approved' AND td.scheduled_date >= NOW() AND td.scheduled_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
    UNION ALL
    SELECT 'Final Defense' as defense_type, fd.id as defense_id, fd.scheduled_date, pw.project_title, s.group_code
    FROM final_defense fd
    INNER JOIN project_working_titles pw ON fd.project_id = pw.id
    INNER JOIN users u ON fd.submitted_by = u.id
    LEFT JOIN students s ON u.id = s.user_id
    WHERE fd.status = 'approved' AND fd.scheduled_date >= NOW() AND fd.scheduled_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
    ORDER BY scheduled_date ASC
    LIMIT 5
");
$upcomingDefensesQuery->execute();
$upcomingDefenses = $upcomingDefensesQuery->get_result();

// 7. Total students
$totalStudentsQuery = $conn->prepare("SELECT COUNT(*) as count FROM students");
$totalStudentsQuery->execute();
$totalStudents = $totalStudentsQuery->get_result()->fetch_assoc()['count'];
$totalStudentsQuery->close();

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
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
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
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .icon-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .icon-success { background: linear-gradient(135deg, #56CCF2, #2F80ED); color: white; }
        .icon-warning { background: linear-gradient(135deg, #FFB75E, #ED8F03); color: white; }
        .icon-info { background: linear-gradient(135deg, #A8EDEA, #FED6E3); color: #333; }
        .icon-danger { background: linear-gradient(135deg, #FF6B6B, #EE5A52); color: white; }
        
        .text-primary-gradient { background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .text-success-gradient { background: linear-gradient(135deg, #56CCF2, #2F80ED); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .text-warning-gradient { background: linear-gradient(135deg, #FFB75E, #ED8F03); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .text-danger-gradient { background: linear-gradient(135deg, #FF6B6B, #EE5A52); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        
        .activity-card, .defense-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .activity-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }
        
        .quick-action-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            color: white;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.25rem;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .welcome-text {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .welcome-subtext {
            opacity: 0.9;
            font-size: 1rem;
        }
        
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 1.5rem 0;
                margin-bottom: 1.5rem;
            }
            
            .welcome-text {
                font-size: 1.5rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }
        
        /* Ensure proper spacing for 5 cards in a row */
        @media (min-width: 1200px) {
            .col-xl-2 {
                flex: 0 0 20%;
                max-width: 20%;
            }
        }
        
        /* Adjust for medium screens to show 3 cards per row */
        @media (min-width: 992px) and (max-width: 1199px) {
            .col-lg-4 {
                flex: 0 0 33.333333%;
                max-width: 33.333333%;
            }
        }
        
        /* Ensure cards maintain consistent height */
        .stat-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .defense-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-left: 4px solid #667eea;
        }
        
        .defense-item:last-child {
            margin-bottom: 0;
        }
        
        .defense-type {
            font-weight: 600;
            color: #667eea;
            font-size: 0.9rem;
        }
        
        .defense-date {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        /* Hover effects for clickable items */
        .activity-item:hover {
            background-color: #f8f9fa;
            border-radius: 8px;
            transition: background-color 0.2s ease;
        }
        
        .defense-item:hover {
            background-color: #e9ecef;
            transition: background-color 0.2s ease;
        }
    </style>
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <?php include '../assets/includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <!-- Welcome Header -->
        <div class="dashboard-header text-center">
            <h1 class="welcome-text">Welcome back, <?php echo htmlspecialchars(explode(' ', $userName)[0]); ?>!</h1>
            <p class="welcome-subtext">Here's an overview of your research management activities</p>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6 mb-4">
                <a href="review_project.php?status=pending" class="stat-card-link">
                    <div class="stat-card">
                        <div class="stat-icon icon-warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <h3 class="stat-number text-warning-gradient"><?php echo $pendingProjects; ?></h3>
                        <p class="stat-label">Pending Project Reviews</p>
                    </div>
                </a>
            </div>
            
            <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6 mb-4">
                <a href="review_project.php?status=approved" class="stat-card-link">
                    <div class="stat-card">
                        <div class="stat-icon icon-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h3 class="stat-number text-success-gradient"><?php echo $approvedProjects; ?></h3>
                        <p class="stat-label">Approved Projects</p>
                    </div>
                </a>
            </div>
            
            <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6 mb-4">
                <a href="title_defense_list.php" class="stat-card-link">
                    <div class="stat-card">
                        <div class="stat-icon icon-primary">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <h3 class="stat-number text-primary-gradient"><?php echo $pendingTitleDefense; ?></h3>
                        <p class="stat-label">Title Defense Reviews</p>
                    </div>
                </a>
            </div>
            
            <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6 mb-4">
                <a href="final_defense_list.php" class="stat-card-link">
                    <div class="stat-card">
                        <div class="stat-icon icon-danger">
                            <i class="bi bi-journal-check"></i>
                        </div>
                        <h3 class="stat-number text-danger-gradient"><?php echo $pendingFinalDefense; ?></h3>
                        <p class="stat-label">Final Defense Reviews</p>
                    </div>
                </a>
            </div>
            
            <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6 mb-4">
                <a href="student_list.php" class="stat-card-link">
                    <div class="stat-card">
                        <div class="stat-icon icon-info">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <h3 class="stat-number text-primary-gradient"><?php echo $totalStudents; ?></h3>
                        <p class="stat-label">Total Students Managed</p>
                    </div>
                </a>
            </div>
        </div>


        <div class="row">
            <!-- Recent Activities -->
            <div class="col-lg-7 mb-4">
                <div class="activity-card">
                    <h5 class="mb-3"><i class="bi bi-activity me-2"></i>Recent Project Reviews</h5>
                    <?php if ($recentActivities->num_rows > 0): ?>
                        <?php while ($activity = $recentActivities->fetch_assoc()): ?>
                            <a href="working_title_form.php?project_id=<?php echo $activity['project_id']; ?>" class="text-decoration-none text-dark">
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars(substr($activity['project_title'], 0, 50)) . (strlen($activity['project_title']) > 50 ? '...' : ''); ?></h6>
                                            <small class="text-muted">by <?php echo htmlspecialchars($activity['proponent_1']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="activity-status status-<?php echo $activity['adviser_approval']; ?>">
                                                <?php echo ucfirst($activity['adviser_approval']); ?>
                                            </span>
                                            <br>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted">No recent activities found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Defenses & Statistics -->
            <div class="col-lg-5 mb-4">
                <!-- Upcoming Defenses -->
                <div class="defense-card">
                    <h5 class="mb-3"><i class="bi bi-calendar-event me-2"></i>Upcoming Defenses</h5>
                    <?php if ($upcomingDefenses->num_rows > 0): ?>
                        <?php while ($defense = $upcomingDefenses->fetch_assoc()): ?>
                            <a href="<?php echo ($defense['defense_type'] === 'Title Defense') ? 'title_defense_file.php?id=' . $defense['defense_id'] : 'final_defense_file.php?id=' . $defense['defense_id']; ?>" class="text-decoration-none text-dark">
                                <div class="defense-item">
                                    <div class="defense-type"><?php echo htmlspecialchars($defense['defense_type']); ?></div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars(substr($defense['project_title'], 0, 40)) . (strlen($defense['project_title']) > 40 ? '...' : ''); ?></h6>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($defense['group_code'] ?: 'N/A'); ?></span>
                                        <small class="defense-date">
                                            <?php echo date('M d, Y \a\t g:i A', strtotime($defense['scheduled_date'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted">No upcoming defenses scheduled.</p>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
</body>
</html>