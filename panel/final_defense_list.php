<?php
session_start();
include '../config/database.php';
require_once '../assets/includes/role_functions.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/login.php");
    exit();
}

$email = $_SESSION['email'];

// Authorize as panelist using multi-role support
if (!isset($_SESSION['user_data']) || !hasRole($_SESSION['user_data'], 'panelist')) {
    header("Location: ../users/login.php?error=unauthorized_access");
    exit();
}

// Determine panelist user id from session
$user = $_SESSION['user_data'];

$panelistId = isset($user['id']) ? (int)$user['id'] : (int)$_SESSION['user_id'];

// Ensure active role reflects Panelist when visiting panel pages
if (!isset($_SESSION['active_role']) || $_SESSION['active_role'] !== 'panelist') {
    $_SESSION['active_role'] = 'panelist';
    $_SESSION['role'] = 'panelist';
}

// Set default status filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Modified query to only show submissions from assigned groups with grading status
$query = "
    SELECT fd.id, fd.project_id, fd.final_defense_pdf, fd.status, fd.scheduled_date,
        pw.project_title, u.first_name, u.last_name, s.group_code,
        CASE 
            WHEN EXISTS (
                SELECT 1 FROM panelist_grades pg 
                WHERE pg.panelist_id = ? AND pg.defense_type = 'final' AND pg.defense_id = fd.id
            ) THEN 'graded'
            ELSE 'pending_grading'
        END as grading_status
    FROM final_defense fd
    INNER JOIN project_working_titles pw ON fd.project_id = pw.id
    INNER JOIN users u ON fd.submitted_by = u.id
    INNER JOIN students s ON u.id = s.user_id
    INNER JOIN panel_assignments pa ON s.group_code = pa.group_code
    WHERE pa.panelist_id = ? AND pa.status = 'active'
  ";

// Modify query based on status filter
if ($statusFilter !== 'all') {
    if ($statusFilter === 'graded') {
        $query .= " AND EXISTS (
            SELECT 1 FROM panelist_grades pg 
            WHERE pg.panelist_id = ? AND pg.defense_type = 'final' AND pg.defense_id = fd.id
        )";
    } elseif ($statusFilter === 'pending_grading') {
        $query .= " AND NOT EXISTS (
            SELECT 1 FROM panelist_grades pg 
            WHERE pg.panelist_id = ? AND pg.defense_type = 'final' AND pg.defense_id = fd.id
        )";
    } else {
        $query .= " AND fd.status = ?";
    }
}
$query .= " ORDER BY grading_status ASC, fd.id DESC";

$submissionsQuery = $conn->prepare($query);
if (!$submissionsQuery) {
    die('Query prepare failed: ' . $conn->error);
}
if ($statusFilter === 'graded' || $statusFilter === 'pending_grading') {
    $submissionsQuery->bind_param("iii", $panelistId, $panelistId, $panelistId);
} elseif ($statusFilter !== 'all') {
    $submissionsQuery->bind_param("iis", $panelistId, $panelistId, $statusFilter);
} else {
    $submissionsQuery->bind_param("ii", $panelistId, $panelistId);
}
$submissionsQuery->execute();
$submissions = $submissionsQuery->get_result();

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
        <h4>Final Defense Submissions</h4>

        <!-- Filter Buttons -->
        <div class="filter-buttons d-none d-md-flex">
            <a href="final_defense_list.php?status=all" class="<?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="final_defense_list.php?status=pending_grading" class="<?php echo $statusFilter === 'pending_grading' ? 'active' : ''; ?>">Pending Grading</a>
            <a href="final_defense_list.php?status=graded" class="<?php echo $statusFilter === 'graded' ? 'active' : ''; ?>">Graded</a>
        </div>

        <!-- Filter Dropdown for Mobile -->
        <div class="filter-dropdown d-md-none">
            <div class="dropdown">
                <button class="btn dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php 
                    $filterLabels = [
                        'all' => 'All',
                        'pending_grading' => 'Pending Grading',
                        'graded' => 'Graded'
                    ];
                    echo isset($filterLabels[$statusFilter]) ? $filterLabels[$statusFilter] : 'All';
                    ?>
                </button>
                <ul class="dropdown-menu" aria-labelledby="filterDropdown">
                    <li><a class="dropdown-item <?php echo $statusFilter === 'all' ? 'active' : ''; ?>" href="final_defense_list.php?status=all">All</a></li>
                    <li><a class="dropdown-item <?php echo $statusFilter === 'pending_grading' ? 'active' : ''; ?>" href="final_defense_list.php?status=pending_grading">Pending Grading</a></li>
                    <li><a class="dropdown-item <?php echo $statusFilter === 'graded' ? 'active' : ''; ?>" href="final_defense_list.php?status=graded">Graded</a></li>
                </ul>
            </div>
        </div>

        <!-- Submissions Table -->
        <?php if ($submissions->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Project Title</th>
                            <th scope="col" class="text-center sortable" data-sort="name">Group Code <i class="bi bi-sort-numeric-down"></i></th>
                            <th class="text-center">Schedule Date</th>
                            <th scope="col" class="text-center sortable" data-sort="status">Grading Status <i class="bi bi-sort-alpha-down"></i></th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $submissions->fetch_assoc()): ?>
                            <tr>
                                <td class="truncate" title="<?php echo htmlspecialchars($row['project_title']); ?>">
                                    <?php echo htmlspecialchars($row['project_title']); ?>
                                </td>
                                <td class="text-center"><?php echo htmlspecialchars(isset($row['group_code']) ? $row['group_code'] : 'N/A'); ?></td>
                                <td class="text-center"><?php echo $row['scheduled_date'] ? date("F d, Y h:i A", strtotime($row['scheduled_date'])) : "Not Scheduled"; ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['grading_status'] === 'graded' ? 'success' : 'warning'; ?>">
                                        <?php echo $row['grading_status'] === 'graded' ? 'Graded' : 'Pending Grading'; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a class="btn" href="final_defense_file.php?id=<?php echo $row['id']; ?>">
                                        <?php echo $row['grading_status'] === 'graded' ? 'View Grades' : 'Grade'; ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No submissions found for the selected filter.</p>
        <?php endif; ?>

        <!-- Success Toast -->
        <div class="toast-container position-fixed top-0 end-0 p-3">
            <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        Grades submitted successfully!
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const dropdownButton = document.getElementById('filterDropdown');
        const dropdownItems = document.querySelectorAll('.filter-dropdown .dropdown-item');

        dropdownItems.forEach(item => {
            item.addEventListener('click', function () {
                dropdownButton.textContent = this.textContent;
            });
        });
        
        // Check for success query parameter and show toast
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        if (success === 'grades_submitted' || success === 'grades_updated') {
            const toastElement = document.getElementById('successToast');
            const toastBody = toastElement.querySelector('.toast-body');
            
            // Update message based on action
            if (success === 'grades_updated') {
                toastBody.textContent = 'Grades updated successfully!';
            } else {
                toastBody.textContent = 'Grades submitted successfully!';
            }
            
            const toast = new bootstrap.Toast(toastElement, {
                autohide: true,
                delay: 2000 // 2 seconds
            });
            toast.show();
            // Clean up the URL to remove the success parameter
            const status = urlParams.get('status') || 'all';
            window.history.replaceState({}, document.title, window.location.pathname + '?status=' + status);
        }
    });
</script>
<script src="../assets/js/sortable.js"></script>
</body>
</html>