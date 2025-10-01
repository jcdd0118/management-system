<?php
session_start();
include '../config/database.php';
require_once '../assets/includes/role_functions.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check login and dean role using multi-role session data
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

$deanId = $_SESSION['user_id']; // Get the dean's ID

// Set default status filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Query to fetch projects based on status filter - only latest versions
$query = "
    SELECT 
        p.id AS project_id, 
        p.project_title, 
        p.proponent_1, 
        p.proponent_2, 
        p.proponent_3, 
        p.proponent_4, 
        p.version,
        pa.dean_approval
    FROM project_working_titles p
    INNER JOIN project_approvals pa ON p.id = pa.project_id
    WHERE pa.faculty_approval = 'approved'
      AND pa.adviser_approval = 'approved'
      AND (p.archived = 0 OR p.archived IS NULL)
      ORDER BY p.id DESC
      ";

// Add status filter for the dean's approval
if ($statusFilter !== 'all') {
    $query = "
        SELECT 
            p.id AS project_id, 
            p.project_title, 
            p.proponent_1, 
            p.proponent_2, 
            p.proponent_3, 
            p.proponent_4, 
            p.version,
            pa.dean_approval
        FROM project_working_titles p
        INNER JOIN project_approvals pa ON p.id = pa.project_id
        WHERE pa.faculty_approval = 'approved'
          AND pa.adviser_approval = 'approved'
          AND (p.archived = 0 OR p.archived IS NULL)
          AND pa.dean_approval = ?
        ORDER BY p.id DESC
    ";
}
$projectsQuery = $conn->prepare($query);

// Bind status filter parameter if necessary
if ($statusFilter !== 'all') {
    $projectsQuery->bind_param("s", $statusFilter);
}

if (!$projectsQuery) {
    die("Error preparing query for fetching projects: " . $conn->error);
}

$projectsQuery->execute();
$projectsResult = $projectsQuery->get_result();
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
    <h4>Projects for Review</h4>

    <!-- Filter Buttons -->
    <div class="filter-buttons d-none d-md-flex">
        <a href="review_project.php?status=all" class="<?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All</a>
        <a href="review_project.php?status=pending" class="<?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">Pending</a>
        <a href="review_project.php?status=approved" class="<?php echo $statusFilter === 'approved' ? 'active' : ''; ?>">Approved</a>
        <a href="review_project.php?status=rejected" class="<?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
    </div>

    <!-- Filter Dropdown for Mobile -->
    <div class="filter-dropdown d-md-none">
        <div class="dropdown">
            <button class="btn dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <?php echo ucfirst($statusFilter) ?: 'All'; ?>
            </button>
            <ul class="dropdown-menu" aria-labelledby="filterDropdown">
                <li><a class="dropdown-item <?php echo $statusFilter === 'all' ? 'active' : ''; ?>" href="review_project.php?status=all">All</a></li>
                <li><a class="dropdown-item <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>" href="review_project.php?status=pending">Pending</a></li>
                <li><a class="dropdown-item <?php echo $statusFilter === 'approved' ? 'active' : ''; ?>" href="review_project.php?status=approved">Approved</a></li>
                <li><a class="dropdown-item <?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>" href="review_project.php?status=rejected">Rejected</a></li>
            </ul>
        </div>
    </div>

    <!-- Projects Table -->
    <?php if ($projectsResult->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Project Title</th>
                        <th>Version</th>
                        <th>Proponents</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $projectsResult->fetch_assoc()): ?>
                        <tr>
                            <td class="truncate" title="<?php echo htmlspecialchars($row['project_title']); ?>">
                                <?php echo htmlspecialchars($row['project_title']); ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info">v<?php echo htmlspecialchars($row['version']); ?></span>
                            </td>
                            <td class="proponent-truncate" title="<?php
                                // Array to hold non-empty proponent names
                                $proponents = array_filter([
                                    htmlspecialchars($row['proponent_1']),
                                    htmlspecialchars($row['proponent_2']),
                                    htmlspecialchars($row['proponent_3']),
                                    htmlspecialchars($row['proponent_4'])
                                ], function($value) { return $value !== ''; });
                                // Join for tooltip
                                echo implode(', ', $proponents);
                            ?>">
                                <?php
                                // On mobile, show first proponent + "et al."; on desktop, show all with <br>
                                if (count($proponents) > 0) {
                                    echo '<span class="d-md-none">' . (count($proponents) > 1 ? htmlspecialchars($proponents[0]) . ' et al.' : htmlspecialchars($proponents[0])) . '</span>';
                                    echo '<span class="d-none d-md-block">' . implode('<br>', $proponents) . '</span>';
                                } else {
                                    echo 'None';
                                }
                                ?>
                            </td>
                            <td class="text-center">
                                <span class="badge 
                                    <?php 
                                    if ($row['dean_approval'] == 'approved') {
                                        echo 'bg-success';
                                    } elseif ($row['dean_approval'] == 'pending') {
                                        echo 'bg-warning';
                                    } elseif ($row['dean_approval'] == 'rejected') {
                                        echo 'bg-danger';
                                    }
                                    ?>">
                                    <?php echo ucfirst(htmlspecialchars($row['dean_approval'])); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a class="btn" href="working_title_form.php?project_id=<?php echo $row['project_id']; ?>">Review</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>No projects found for the selected filter.</p>
    <?php endif; ?>
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
    });
</script>
</body>
</html> 