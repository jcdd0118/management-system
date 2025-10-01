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
require_once '../assets/includes/group_functions.php';

$email = $_SESSION['email'];

// Fetch user details
$userQuery = $conn->prepare("SELECT id, role, first_name, last_name FROM users WHERE email = ?");
$userQuery->bind_param("s", $email);
$userQuery->execute();
$userResult = $userQuery->get_result();
$user = $userResult->fetch_assoc();
$userQuery->close();

// Ensure user has panelist role (supports multi-role)
if (!isset($_SESSION['user_data']) || !hasRole($_SESSION['user_data'], 'panelist')) {
    header("Location: ../users/login.php?error=unauthorized_access");
    exit();
}

// Ensure active role reflects Panelist when visiting panelist pages
if (!isset($_SESSION['active_role']) || $_SESSION['active_role'] !== 'panelist') {
    $_SESSION['active_role'] = 'panelist';
    $_SESSION['role'] = 'panelist';
}

$panelistId = $user['id'];

// Get dashboard statistics
// Title Defense Stats
$titleDefenseStats = [
    'pending_grading' => 0,
    'graded' => 0,
    'total' => 0
];

$titleDefenseStmt = $conn->prepare(
    "SELECT 
        CASE 
            WHEN EXISTS (
                SELECT 1 FROM panelist_grades pg 
                WHERE pg.panelist_id = ? AND pg.defense_type = 'title' AND pg.defense_id = td.id
            ) THEN 'graded'
            ELSE 'pending_grading'
        END as grading_status,
        COUNT(*) as count
     FROM title_defense td
     INNER JOIN project_working_titles pw ON td.project_id = pw.id
     INNER JOIN users u ON td.submitted_by = u.id
     INNER JOIN students s ON u.id = s.user_id
     LEFT JOIN panel_assignments pa ON (
         (pa.group_id IS NOT NULL AND s.group_id = pa.group_id)
         OR (pa.group_id IS NULL AND s.group_code = pa.group_code)
     )
     WHERE pa.panelist_id = ? AND pa.status = 'active'
     GROUP BY grading_status"
);
$titleDefenseStmt->bind_param("ii", $panelistId, $panelistId);
$titleDefenseStmt->execute();
$tdResult = $titleDefenseStmt->get_result();
while ($row = $tdResult->fetch_assoc()) {
	$titleDefenseStats[$row['grading_status']] = $row['count'];
	$titleDefenseStats['total'] += $row['count'];
}
$titleDefenseStmt->close();

// Final Defense Stats
$finalDefenseStats = [
    'pending_grading' => 0,
    'graded' => 0,
    'total' => 0
];

$finalDefenseStmt = $conn->prepare(
    "SELECT 
        CASE 
            WHEN EXISTS (
                SELECT 1 FROM panelist_grades pg 
                WHERE pg.panelist_id = ? AND pg.defense_type = 'final' AND pg.defense_id = fd.id
            ) THEN 'graded'
            ELSE 'pending_grading'
        END as grading_status,
        COUNT(*) as count
     FROM final_defense fd
     INNER JOIN project_working_titles pw ON fd.project_id = pw.id
     INNER JOIN users u ON fd.submitted_by = u.id
     INNER JOIN students s ON u.id = s.user_id
     LEFT JOIN panel_assignments pa ON (
         (pa.group_id IS NOT NULL AND s.group_id = pa.group_id)
         OR (pa.group_id IS NULL AND s.group_code = pa.group_code)
     )
     WHERE pa.panelist_id = ? AND pa.status = 'active'
     GROUP BY grading_status"
);
$finalDefenseStmt->bind_param("ii", $panelistId, $panelistId);
$finalDefenseStmt->execute();
$fdResult = $finalDefenseStmt->get_result();
while ($row = $fdResult->fetch_assoc()) {
	$finalDefenseStats[$row['grading_status']] = $row['count'];
	$finalDefenseStats['total'] += $row['count'];
}
$finalDefenseStmt->close();

// Recent Title Defense submissions (last 10)
$recentTitleStmt = $conn->prepare(
    "SELECT td.id,
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM panelist_grades pg 
                    WHERE pg.panelist_id = ? AND pg.defense_type = 'title' AND pg.defense_id = td.id
                ) THEN 'graded'
                ELSE 'pending_grading'
            END as grading_status,
            td.date_submitted, td.scheduled_date,
            pw.project_title, s.group_id, s.year_section, s.group_code,
            g.group_name
     FROM title_defense td
     INNER JOIN project_working_titles pw ON td.project_id = pw.id
     INNER JOIN users u ON td.submitted_by = u.id
     INNER JOIN students s ON u.id = s.user_id
     LEFT JOIN groups g ON s.group_id = g.id
     LEFT JOIN panel_assignments pa ON (
         (pa.group_id IS NOT NULL AND s.group_id = pa.group_id)
         OR (pa.group_id IS NULL AND s.group_code = pa.group_code)
     )
     WHERE pa.panelist_id = ? AND pa.status = 'active'
     ORDER BY td.date_submitted DESC
     LIMIT 10"
);
$recentTitleStmt->bind_param("ii", $panelistId, $panelistId);
$recentTitleStmt->execute();
$recentTitleResult = $recentTitleStmt->get_result();

// Recent Final Defense submissions (last 10)
$recentFinalStmt = $conn->prepare(
    "SELECT fd.id,
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM panelist_grades pg 
                    WHERE pg.panelist_id = ? AND pg.defense_type = 'final' AND pg.defense_id = fd.id
                ) THEN 'graded'
                ELSE 'pending_grading'
            END as grading_status,
            fd.date_submitted, fd.scheduled_date,
            pw.project_title, s.group_id, s.year_section, s.group_code,
            g.group_name
     FROM final_defense fd
     INNER JOIN project_working_titles pw ON fd.project_id = pw.id
     INNER JOIN users u ON fd.submitted_by = u.id
     INNER JOIN students s ON u.id = s.user_id
     LEFT JOIN groups g ON s.group_id = g.id
     LEFT JOIN panel_assignments pa ON (
         (pa.group_id IS NOT NULL AND s.group_id = pa.group_id)
         OR (pa.group_id IS NULL AND s.group_code = pa.group_code)
     )
     WHERE pa.panelist_id = ? AND pa.status = 'active'
     ORDER BY fd.date_submitted DESC
     LIMIT 10"
);
$recentFinalStmt->bind_param("ii", $panelistId, $panelistId);
$recentFinalStmt->execute();
$recentFinalResult = $recentFinalStmt->get_result();

// Upcoming scheduled defenses (next 7 days)
$upcomingStmt = $conn->prepare(
    "(SELECT 'title' as type, td.id, td.scheduled_date, pw.project_title, s.group_code
       FROM title_defense td
       INNER JOIN project_working_titles pw ON td.project_id = pw.id
       INNER JOIN users u ON td.submitted_by = u.id
       INNER JOIN students s ON u.id = s.user_id
       INNER JOIN panel_assignments pa ON s.group_code = pa.group_code
       WHERE td.status = 'approved'
         AND pa.panelist_id = ? AND pa.status = 'active'
         AND td.scheduled_date >= NOW()
         AND td.scheduled_date <= DATE_ADD(NOW(), INTERVAL 7 DAY))
     UNION ALL
     (SELECT 'final' as type, fd.id, fd.scheduled_date, pw.project_title, s.group_code
       FROM final_defense fd
       INNER JOIN project_working_titles pw ON fd.project_id = pw.id
       INNER JOIN users u ON fd.submitted_by = u.id
       INNER JOIN students s ON u.id = s.user_id
       INNER JOIN panel_assignments pa ON s.group_code = pa.group_code
       WHERE fd.status = 'approved'
         AND pa.panelist_id = ? AND pa.status = 'active'
         AND fd.scheduled_date >= NOW()
         AND fd.scheduled_date <= DATE_ADD(NOW(), INTERVAL 7 DAY))
     ORDER BY scheduled_date ASC
     LIMIT 10"
);
$upcomingStmt->bind_param("ii", $panelistId, $panelistId);
$upcomingStmt->execute();
$upcomingResult = $upcomingStmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Captrack Vault Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <style>
        .dashboard-welcome {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stats-icon.pending { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #d97514; }
        .stats-icon.approved { background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); color: #155724; }
        .stats-icon.rejected { background: linear-gradient(135deg, #f8d7da 0%, #f1b0b7 100%); color: #721c24; }
        .stats-icon.total { background: linear-gradient(135deg, #cce5ff 0%, #b3d9ff 100%); color: #004085; }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stats-label {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .section-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-title i {
            color: #667eea;
        }
        
        .recent-item {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        
        .recent-item:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transform: translateX(5px);
        }
        
        .status-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge.pending { background-color: #fff3cd; color: #856404; }
        .status-badge.approved { background-color: #d4edda; color: #155724; }
        .status-badge.rejected { background-color: #f8d7da; color: #721c24; }
        
        .upcoming-defense {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 3px 10px rgba(240, 147, 251, 0.3);
        }
        
        .quick-action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .stats-card-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .stats-card-link:hover {
            text-decoration: none;
            color: inherit;
        }
        
        .main-content {
            background-color: #f8fafc;
            min-height: 100vh;
        }
        
        .container {
            padding-top: 2rem;
        }
        
        @media (max-width: 768px) {
            .dashboard-welcome {
                padding: 1.5rem;
            }
            
            .stats-number {
                font-size: 2rem;
            }
            
            .stats-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <?php include '../assets/includes/navbar.php'; ?>
    
    <div class="container">
        <!-- Welcome Section -->
        <div class="dashboard-welcome">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="mb-2">Welcome back, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>!</h2>
                    <p class="mb-0 opacity-90">Here's an overview of your panel activities and recent submissions.</p>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-4">
                <h5 class="section-title">
                    <i class="bi bi-clipboard-check"></i>
                    Title Defense Overview
                </h5>
                <div class="row">
                    <div class="col-6 col-lg-4 mb-3">
                        <a href="title_defense_list.php?grading_status=pending_grading" class="stats-card-link">
                            <div class="stats-card">
                                <div class="stats-icon pending">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div class="stats-number text-warning"><?php echo $titleDefenseStats['pending_grading']; ?></div>
                                <div class="stats-label">Pending Grading</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-lg-4 mb-3">
                        <a href="title_defense_list.php?grading_status=graded" class="stats-card-link">
                            <div class="stats-card">
                                <div class="stats-icon approved">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div class="stats-number text-success"><?php echo $titleDefenseStats['graded']; ?></div>
                                <div class="stats-label">Graded</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-lg-4 mb-3">
                        <a href="title_defense_list.php" class="stats-card-link">
                            <div class="stats-card">
                                <div class="stats-icon total">
                                    <i class="bi bi-collection"></i>
                                </div>
                                <div class="stats-number text-primary"><?php echo $titleDefenseStats['total']; ?></div>
                                <div class="stats-label">Total</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <h5 class="section-title">
                    <i class="bi bi-award"></i>
                    Final Defense Overview
                </h5>
                <div class="row">
                    <div class="col-6 col-lg-4 mb-3">
                        <a href="final_defense_list.php?grading_status=pending_grading" class="stats-card-link">
                            <div class="stats-card">
                                <div class="stats-icon pending">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div class="stats-number text-warning"><?php echo $finalDefenseStats['pending_grading']; ?></div>
                                <div class="stats-label">Pending Grading</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-lg-4 mb-3">
                        <a href="final_defense_list.php?grading_status=graded" class="stats-card-link">
                            <div class="stats-card">
                                <div class="stats-icon approved">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div class="stats-number text-success"><?php echo $finalDefenseStats['graded']; ?></div>
                                <div class="stats-label">Graded</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-lg-4 mb-3">
                        <a href="final_defense_list.php" class="stats-card-link">
                            <div class="stats-card">
                                <div class="stats-icon total">
                                    <i class="bi bi-collection"></i>
                                </div>
                                <div class="stats-number text-primary"><?php echo $finalDefenseStats['total']; ?></div>
                                <div class="stats-label">Total</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities and Upcoming Defenses -->
        <div class="row">
            <!-- Upcoming Defenses -->
            <div class="col-lg-4 mb-4">
                <h5 class="section-title">
                    <i class="bi bi-calendar-event"></i>
                    Upcoming Defenses
                </h5>
                <div class="bg-white rounded-3 p-3" style="box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
                    <?php if ($upcomingResult && $upcomingResult->num_rows > 0): ?>
                        <?php while ($upcoming = $upcomingResult->fetch_assoc()): ?>
                            <a href="<?php echo $upcoming['type'] === 'title' ? 'title_defense_file.php?id=' . $upcoming['id'] : 'final_defense_file.php?id=' . $upcoming['id']; ?>" class="text-decoration-none text-reset">
                            <div class="upcoming-defense">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-light text-dark"><?php echo ucfirst($upcoming['type']); ?> Defense</span>
                                    <small class="opacity-75"><?php 
                                        $display = isset($upcoming['group_name']) && isset($upcoming['year_section']) && !empty($upcoming['group_name']) && !empty($upcoming['year_section'])
                                            ? computeGroupDisplayCode($upcoming['year_section'], $upcoming['group_name'])
                                            : (isset($upcoming['group_code']) ? $upcoming['group_code'] : 'N/A');
                                        echo htmlspecialchars($display);
                                    ?></small>
                                </div>
                                <h6 class="mb-2"><?php echo htmlspecialchars($upcoming['project_title']); ?></h6>
                                <p class="mb-0 small opacity-90">
                                    <i class="bi bi-clock me-1"></i>
                                    <?php echo date("M d, Y - h:i A", strtotime($upcoming['scheduled_date'])); ?>
                                </p>
                            </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">No upcoming defenses scheduled</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Title Defense Submissions -->
            <div class="col-lg-4 mb-4">
                <h5 class="section-title">
                    <i class="bi bi-clock-history"></i>
                    Recent Title Defenses
                </h5>
                <div class="bg-white rounded-3 p-3" style="box-shadow: 0 5px 15px rgba(0,0,0,0.08); max-height: 400px; overflow-y: auto;">
                    <?php if ($recentTitleResult && $recentTitleResult->num_rows > 0): ?>
                        <?php while ($recent = $recentTitleResult->fetch_assoc()): ?>
                            <a href="title_defense_file.php?id=<?php echo $recent['id']; ?>" class="text-decoration-none text-reset">
                            <div class="recent-item">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="status-badge <?php echo $recent['grading_status'] === 'graded' ? 'approved' : 'pending'; ?>">
                                        <?php echo $recent['grading_status'] === 'graded' ? 'Graded' : 'Pending Grading'; ?>
                                    </span>
                                    <small class="text-muted"><?php 
                                        $display = isset($recent['group_name']) && isset($recent['year_section']) && !empty($recent['group_name']) && !empty($recent['year_section'])
                                            ? computeGroupDisplayCode($recent['year_section'], $recent['group_name'])
                                            : (isset($recent['group_code']) ? $recent['group_code'] : 'N/A');
                                        echo htmlspecialchars($display);
                                    ?></small>
                                </div>
                                <h6 class="mb-1" style="font-size: 0.9rem;"><?php echo htmlspecialchars($recent['project_title']); ?></h6>
                                <small class="text-muted">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    <?php echo date("M d, Y", strtotime($recent['date_submitted'])); ?>
                                </small>
                            </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">No recent submissions</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Final Defense Submissions -->
            <div class="col-lg-4 mb-4">
                <h5 class="section-title">
                    <i class="bi bi-clock-history"></i>
                    Recent Final Defenses
                </h5>
                <div class="bg-white rounded-3 p-3" style="box-shadow: 0 5px 15px rgba(0,0,0,0.08); max-height: 400px; overflow-y: auto;">
                    <?php if ($recentFinalResult && $recentFinalResult->num_rows > 0): ?>
                        <?php while ($recent = $recentFinalResult->fetch_assoc()): ?>
                            <a href="final_defense_file.php?id=<?php echo $recent['id']; ?>" class="text-decoration-none text-reset">
                            <div class="recent-item">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="status-badge <?php echo $recent['grading_status'] === 'graded' ? 'approved' : 'pending'; ?>">
                                        <?php echo $recent['grading_status'] === 'graded' ? 'Graded' : 'Pending Grading'; ?>
                                    </span>
                                    <small class="text-muted"><?php 
                                        $display = isset($recent['group_name']) && isset($recent['year_section']) && !empty($recent['group_name']) && !empty($recent['year_section'])
                                            ? computeGroupDisplayCode($recent['year_section'], $recent['group_name'])
                                            : (isset($recent['group_code']) ? $recent['group_code'] : 'N/A');
                                        echo htmlspecialchars($display);
                                    ?></small>
                                </div>
                                <h6 class="mb-1" style="font-size: 0.9rem;"><?php echo htmlspecialchars($recent['project_title']); ?></h6>
                                <small class="text-muted">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    <?php echo date("M d, Y", strtotime($recent['date_submitted'])); ?>
                                </small>
                            </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">No recent submissions</p>
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
    // Add some interactive features
    document.addEventListener('DOMContentLoaded', function() {
        // Add hover effects to stats cards
        const statsCards = document.querySelectorAll('.stats-card');
        statsCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.borderLeft = '4px solid #667eea';
            });
            card.addEventListener('mouseleave', function() {
                this.style.borderLeft = 'none';
            });
        });

        // Auto-refresh page every 5 minutes to keep data current
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutes
    });
</script>

</body>
</html>