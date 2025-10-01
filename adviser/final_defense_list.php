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
$adviserId = $_SESSION['user_id'];

// Set default status filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Base query for title defense submissions
$query = "
    SELECT fd.id, fd.project_id, fd.final_defense_pdf, fd.status, fd.scheduled_date,
        pw.project_title, u.first_name, u.last_name, s.group_code
    FROM final_defense fd
    INNER JOIN project_working_titles pw ON fd.project_id = pw.id
    INNER JOIN users u ON fd.submitted_by = u.id
    LEFT JOIN students s ON u.id = s.user_id
  ";

// Modify query based on status filter
if ($statusFilter !== 'all') {
    $query .= " WHERE fd.status = ?";
}
$query .= " ORDER BY fd.status ASC, fd.date_submitted DESC";

$submissionsQuery = $conn->prepare($query);
if ($statusFilter !== 'all') {
    $submissionsQuery->bind_param("s", $statusFilter);
}
if (!$submissionsQuery) {
    die("Error preparing query for fetching submissions: " . $conn->error);
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
            <a href="final_defense_list.php?status=pending" class="<?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="final_defense_list.php?status=approved" class="<?php echo $statusFilter === 'approved' ? 'active' : ''; ?>">Approved</a>
            <a href="final_defense_list.php?status=rejected" class="<?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
        </div>

        <!-- Filter Dropdown for Mobile -->
        <div class="filter-dropdown d-md-none">
            <div class="dropdown">
                <button class="btn dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php echo ucfirst($statusFilter) ?: 'All'; ?>
                </button>
                <ul class="dropdown-menu" aria-labelledby="filterDropdown">
                    <li><a class="dropdown-item <?php echo $statusFilter === 'all' ? 'active' : ''; ?>" href="final_defense_list.php?status=all">All</a></li>
                    <li><a class="dropdown-item <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>" href="final_defense_list.php?status=pending">Pending</a></li>
                    <li><a class="dropdown-item <?php echo $statusFilter === 'approved' ? 'active' : ''; ?>" href="final_defense_list.php?status=approved">Approved</a></li>
                    <li><a class="dropdown-item <?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>" href="final_defense_list.php?status=rejected">Rejected</a></li>
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
                            <th scope="col" class="text-center sortable" data-sort="status">Status <i class="bi bi-sort-alpha-down"></i></th>
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
                                    <span class="badge bg-<?php echo htmlspecialchars($row['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a class="btn btn-primary btn-sm me-2" href="final_defense_file.php?id=<?php echo $row['id']; ?>">Review</a>
                                    <a class="btn btn-info btn-sm" href="view_grades_simple.php?type=final&id=<?php echo $row['id']; ?>">View Grades</a>
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
                        Title defense updated successfully!
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
        if (urlParams.get('success') === 'updated') {
            const toastElement = document.getElementById('successToast');
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