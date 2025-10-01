<?php
// Start the session
session_start();

// Check if the user is logged in and has appropriate role
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has one of the allowed roles (not admin or student)
$allowed_roles = ['adviser', 'dean', 'faculty', 'grammarian', 'panelist'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: login.php?error=unauthorized_access");
    exit();
}

// Database connection (assuming a config file exists)
require_once '../config/database.php';
require_once '../assets/includes/author_functions.php';

// Fetch bookmarked research papers
$user_id = $_SESSION['user_id'];
$query = "SELECT b.id AS bookmark_id, c.id AS research_id, c.title, c.author, c.year, c.abstract, c.keywords, c.document_path
          FROM bookmarks b
          JOIN capstone c ON b.research_id = c.id
          WHERE b.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$bookmarks = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Bookmarks - Captrack Vault</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <style>
        /* Position the toast in the top-right corner */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1055;
        }
    </style>
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <?php include '../assets/includes/navbar.php'; ?>

    <!-- Toast Notification Container -->
    <div class="toast-container">
        <?php if (isset($_GET['success'])): ?>
            <div class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="2000">
                <div class="d-flex">
                    <div class="toast-body">
                        <?php echo htmlspecialchars($_GET['success']); ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="2000">
                <div class="d-flex">
                    <div class="toast-body">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="container-fluid py-4">
        <h2 class="mb-4">My Bookmarked Research</h2>

        <?php if (empty($bookmarks)): ?>
            <div class="alert alert-info" role="alert">
                You have no bookmarked research papers.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th scope="col">Title</th>
                            <th scope="col">Author</th>
                            <th scope="col">Year</th>
                            <th scope="col">Abstract</th>
                            <th scope="col">Keywords</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookmarks as $bookmark): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($bookmark['title']); ?></td>
                                <td><?php echo htmlspecialchars(parseAuthorData($bookmark['author'])); ?></td>
                                <td><?php echo htmlspecialchars($bookmark['year']); ?></td>
                                <td><?php echo htmlspecialchars(substr($bookmark['abstract'], 0, 100)) . (strlen($bookmark['abstract']) > 100 ? '...' : ''); ?></td>
                                <td><?php echo htmlspecialchars($bookmark['keywords']); ?></td>
                                <td>
                                    <a href="<?php echo htmlspecialchars($bookmark['document_path']); ?>" class="btn btn-sm btn-primary" target="_blank">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <a href="remove_bookmark.php?id=<?php echo $bookmark['bookmark_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this bookmark?');">
                                        <i class="bi bi-trash"></i> Remove
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    // Initialize and show toasts, then clean the URL
    document.addEventListener('DOMContentLoaded', function () {
        const toasts = document.querySelectorAll('.toast');
        if (toasts.length > 0) {
            toasts.forEach(toast => {
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();
            });
            // Clean the URL after 2 seconds (after toast hides)
            setTimeout(() => {
                const cleanUrl = window.location.pathname; // e.g., /users/bookmark.php
                history.replaceState(null, '', cleanUrl);
            }, 2000);
        }
    });
</script>
</body>
</html>
<?php
// Close the connection at the very end, if necessary
$conn->close();
?>
