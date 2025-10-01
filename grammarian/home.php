<?php
session_start();
include '../config/database.php';
require_once '../assets/includes/role_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/login.php");
    exit();
}

// Authorize as grammarian using multi-role support
if (!isset($_SESSION['user_data']) || !hasRole($_SESSION['user_data'], 'grammarian')) {
    header("Location: ../users/login.php?error=unauthorized_access");
    exit();
}

// Ensure active role reflects Grammarian when visiting grammarian pages
if (!isset($_SESSION['active_role']) || $_SESSION['active_role'] !== 'grammarian') {
    $_SESSION['active_role'] = 'grammarian';
    $_SESSION['role'] = 'grammarian';
}

$grammarianId = $_SESSION['user_id'];

// Set default status filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Query to fetch manuscript reviews
$query = "
    SELECT mr.*, pw.project_title, u.first_name, u.last_name, s.group_code
    FROM manuscript_reviews mr
    INNER JOIN project_working_titles pw ON mr.project_id = pw.id
    INNER JOIN users u ON mr.student_id = u.id
    LEFT JOIN students s ON u.id = s.user_id
";

// Modify query based on status filter
if ($statusFilter !== 'all') {
    $query .= " WHERE mr.status = ?";
}
$query .= " ORDER BY mr.status ASC, mr.date_submitted DESC";

$manuscriptsQuery = $conn->prepare($query);
if ($statusFilter !== 'all') {
    $manuscriptsQuery->bind_param("s", $statusFilter);
}
$manuscriptsQuery->execute();
$manuscripts = $manuscriptsQuery->get_result();

// Get statistics
$statsQuery = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM manuscript_reviews
");
$statsQuery->execute();
$stats = $statsQuery->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grammarian Dashboard - Captrack Vault</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stats-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }
        .stats-card p {
            margin: 0;
            opacity: 0.9;
        }
        .manuscript-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        .manuscript-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
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
        .filter-buttons {
            margin-bottom: 2rem;
        }
        .filter-btn {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .btn-review {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        .btn-review:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <?php include '../assets/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <?php include '../assets/includes/navbar.php'; ?>

        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h3 mb-0">
                        <i class="bi bi-pencil-square me-2"></i>
                        Grammarian Dashboard
                    </h1>
                    <p class="text-muted">Review and provide grammar feedback on student manuscripts</p>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Manuscripts</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                        <h3><?php echo $stats['pending']; ?></h3>
                        <p>Pending Review</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                        <h3><?php echo $stats['under_review']; ?></h3>
                        <p>Under Review</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card" style="background: linear-gradient(135deg, #d299c2 0%, #fef9d7 100%);">
                        <h3><?php echo $stats['approved']; ?></h3>
                        <p>Approved</p>
                    </div>
                </div>
            </div>

            <!-- Filter Buttons -->
            <div class="filter-buttons">
                <a href="?status=all" class="btn btn-outline-primary filter-btn <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">
                    All Manuscripts
                </a>
                <a href="?status=pending" class="btn btn-outline-warning filter-btn <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">
                    Pending
                </a>
                <a href="?status=under_review" class="btn btn-outline-info filter-btn <?php echo $statusFilter === 'under_review' ? 'active' : ''; ?>">
                    Under Review
                </a>
                <a href="?status=approved" class="btn btn-outline-success filter-btn <?php echo $statusFilter === 'approved' ? 'active' : ''; ?>">
                    Approved
                </a>
                <a href="?status=rejected" class="btn btn-outline-danger filter-btn <?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>">
                    Rejected
                </a>
            </div>

            <!-- Manuscripts List -->
            <div class="row">
                <div class="col-12">
                    <?php if ($manuscripts->num_rows > 0): ?>
                        <?php while ($manuscript = $manuscripts->fetch_assoc()): ?>
                            <div class="manuscript-card">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($manuscript['project_title']); ?></h5>
                                        <p class="text-muted mb-1">
                                            <i class="bi bi-person me-1"></i>
                                            <?php echo htmlspecialchars($manuscript['first_name'] . ' ' . $manuscript['last_name']); ?>
                                            <?php if ($manuscript['group_code']): ?>
                                                <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($manuscript['group_code']); ?></span>
                                            <?php endif; ?>
                                        </p>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar me-1"></i>
                                            Submitted: <?php echo date('M d, Y', strtotime($manuscript['date_submitted'])); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <span class="status-badge status-<?php echo $manuscript['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $manuscript['status'])); ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <?php if ($manuscript['status'] === 'pending' || $manuscript['status'] === 'under_review'): ?>
                                            <a href="review_manuscript.php?id=<?php echo $manuscript['id']; ?>" class="btn btn-review">
                                                <i class="bi bi-eye me-1"></i>
                                                Review
                                            </a>
                                        <?php else: ?>
                                            <a href="review_manuscript.php?id=<?php echo $manuscript['id']; ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-eye me-1"></i>
                                                View
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-file-text"></i>
                            <h4>No manuscripts found</h4>
                            <p>There are no manuscripts matching your current filter.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
