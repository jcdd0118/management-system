<?php
session_start();
require_once '../config/database.php';
require_once '../assets/includes/author_functions.php';

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

$research_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($research_id <= 0) {
    header("Location: research_repository.php?error=Invalid research ID");
    exit();
}

// Fetch research details
$query = "SELECT * FROM capstone WHERE id = ? AND status = 'verified'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $research_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: research_repository.php?error=Research not found");
    exit();
}

$research = $result->fetch_assoc();
$stmt->close();

// Check if already bookmarked
$user_id = $_SESSION['user_id'];
$bookmarkQuery = "SELECT id FROM bookmarks WHERE user_id = ? AND research_id = ?";
$bookmarkStmt = $conn->prepare($bookmarkQuery);
$bookmarkStmt->bind_param("ii", $user_id, $research_id);
$bookmarkStmt->execute();
$isBookmarked = $bookmarkStmt->get_result()->num_rows > 0;
$bookmarkStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($research['title']); ?> - Research Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <?php include '../assets/includes/navbar.php'; ?>

    <div class="container my-4">
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><?php echo htmlspecialchars($research['title']); ?></h4>
                            <div class="d-flex gap-2">
                                <a href="<?php echo htmlspecialchars($research['document_path']); ?>" class="btn btn-primary" target="_blank">
                                    <i class="bi bi-eye"></i> View Document
                                </a>
                                <?php if ($isBookmarked): ?>
                                    <a href="remove_bookmark.php?id=<?php echo $research_id; ?>" class="btn btn-outline-danger">
                                        <i class="bi bi-bookmark-fill"></i> Remove Bookmark
                                    </a>
                                <?php else: ?>
                                    <a href="add_bookmark.php?id=<?php echo $research_id; ?>" class="btn btn-outline-primary">
                                        <i class="bi bi-bookmark-plus"></i> Bookmark
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Author(s):</strong>
                                <p><?php echo htmlspecialchars(parseAuthorData($research['author'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Year:</strong>
                                <p><?php echo htmlspecialchars($research['year']); ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Keywords:</strong>
                            <p><?php echo htmlspecialchars($research['keywords']); ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Abstract:</strong>
                            <p class="text-justify"><?php echo nl2br(htmlspecialchars($research['abstract'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="<?php echo htmlspecialchars($research['document_path']); ?>" class="btn btn-primary" target="_blank">
                                <i class="bi bi-download"></i> Download Document
                            </a>
                            <a href="research_repository.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Repository
                            </a>
                            <a href="bookmark.php" class="btn btn-outline-info">
                                <i class="bi bi-bookmark-heart"></i> View My Bookmarks
                            </a>
                        </div>
                    </div>
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
