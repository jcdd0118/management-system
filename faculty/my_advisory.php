<?php
// Start the session
session_start();
include '../config/database.php';
require_once '../assets/includes/role_functions.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: ../users/login.php");
    exit();
}

// Authorize as faculty using multi-role support
if (!isset($_SESSION['user_data']) || !hasRole($_SESSION['user_data'], 'faculty')) {
    header("Location: ../users/login.php?error=unauthorized_access");
    exit();
}

// Ensure active role reflects Faculty when visiting faculty pages
if (!isset($_SESSION['active_role']) || $_SESSION['active_role'] !== 'faculty') {
    $_SESSION['active_role'] = 'faculty';
    $_SESSION['role'] = 'faculty';
}

$adviser_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

// Fetch projects where the faculty is the adviser
$projectsQuery = $conn->prepare("
    SELECT pwt.*, u.email AS submitted_by_email
    FROM project_working_titles pwt
    JOIN users u ON pwt.submitted_by = u.email
    WHERE pwt.noted_by = ?
");
$projectsQuery->bind_param("i", $adviser_id);
$projectsQuery->execute();
$projectsResult = $projectsQuery->get_result();

$groups = [];
while ($project = $projectsResult->fetch_assoc()) {
    // Get group code from the student who submitted the project
    $studentQuery = $conn->prepare("
        SELECT group_code, year_section
        FROM students
        WHERE email = ?
    ");
    $studentQuery->bind_param("s", $project['submitted_by']);
    $studentQuery->execute();
    $studentResult = $studentQuery->get_result();
    $student = $studentResult->fetch_assoc();

    if ($student) {
        $group_code = $student['group_code'];
        $year_section = $student['year_section'];

        // Fetch all students with the same group code
        $membersQuery = $conn->prepare("
            SELECT first_name, middle_name, last_name
            FROM students
            WHERE group_code = ?
        ");
        $membersQuery->bind_param("s", $group_code);
        $membersQuery->execute();
        $membersResult = $membersQuery->get_result();

        $members = [];
        while ($member = $membersResult->fetch_assoc()) {
            $middle_initial = !empty($member['middle_name']) ? strtoupper(substr($member['middle_name'], 0, 1)) . '.' : '';
            $members[] = $member['last_name'] . ', ' . $member['first_name'] . ' ' . $middle_initial;
        }

        // Store group details
        $groups[] = [
            'group_code' => $group_code,
            'year_section' => $year_section,
            'members' => $members,
            'project_id' => $project['id']
        ];

        $membersQuery->close();
        $studentQuery->close();
    }
}
$projectsQuery->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Advisory - Captrack Vault</title>
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
        <h4>Advisory Students</h4>
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>Members</th>
                        <th scope="col" class="text-center sortable" data-sort="name">Group Code <i class="bi bi-sort-numeric-down"></i></th>
                        <th class="text-center">Year and Section</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($groups)): ?>
                        <tr>
                            <td colspan="4" class="text-center">No advisory groups found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($groups as $group): ?>
                            <tr>
                                <td>
                                    <ul class="members-list">
                                        <?php foreach ($group['members'] as $member): ?>
                                            <li><?php echo htmlspecialchars($member); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                                <td class="text-center"><?php echo htmlspecialchars($group['group_code']); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($group['year_section']); ?></td>
                                <td class="text-center">
                                    <a href="review_project.php?project_id=<?php echo $group['project_id']; ?>" class="btn btn-primary btn-sm action-button">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script src="../assets/js/sortable.js"></script>
</body>
</html>