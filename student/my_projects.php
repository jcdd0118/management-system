<?php
session_start();

// Check if the user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../users/login.php");
    exit();
}

include '../config/database.php';
include '../assets/includes/author_functions.php';

// Fetch the latest capstone submission from any member in the student's group_code
$email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$submission = null;
$submitterUserId = $_SESSION['user_id'];

$query = "
    SELECT c.*, u.first_name, u.last_name, u.email AS submitter_email, u.id AS submitter_user_id
    FROM capstone c
    INNER JOIN users u ON c.user_id = u.id
    WHERE c.user_id IN (
        SELECT u2.id
        FROM users u2
        INNER JOIN students s2 ON u2.email = s2.email
        WHERE s2.group_code = (
            SELECT s.group_code FROM students s WHERE s.email = ? LIMIT 1
        )
    )
    ORDER BY c.id DESC
    LIMIT 1
";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    $submission = mysqli_fetch_assoc($result);
    if ($submission) {
        $submitterUserId = (int)$submission['submitter_user_id'];
    }
}
mysqli_stmt_close($stmt);

// Fetch latest rejection remarks via notifications if submission exists
$latestRejectionRemarks = '';
if ($submission && isset($submission['id'])) {
    $notifSql = "SELECT message FROM notifications WHERE user_id = ? AND related_type = 'capstone' AND related_id = ? AND title = 'Research Rejected' ORDER BY created_at DESC LIMIT 1";
    $notifStmt = mysqli_prepare($conn, $notifSql);
    mysqli_stmt_bind_param($notifStmt, "ii", $submitterUserId, $submission['id']);
    mysqli_stmt_execute($notifStmt);
    $notifRes = mysqli_stmt_get_result($notifStmt);
    if ($notifRes && ($notif = mysqli_fetch_assoc($notifRes))) {
        $msg = $notif['message'];
        $pos = stripos($msg, 'Remarks:');
        if ($pos !== false) {
            $remarksText = trim(substr($msg, $pos + strlen('Remarks:')));
            $latestRejectionRemarks = $remarksText;
        } else {
            $latestRejectionRemarks = $msg; // fallback to full message
        }
    }
    mysqli_stmt_close($notifStmt);
}

// Remove the automatic redirect - let the page handle both cases
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Captrack Vault - My Projects</title>
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

    <div class="container mt-4">
        <h4 class="mb-3"><i class="bi bi-file-text-fill"></i>Capstone Manuscript</h4>
        
        <?php if (!$submission): ?>
            <!-- No submission found -->
            <div class="card shadow-sm p-4">
                <div class="text-center">
                    <i class="bi bi-file-text" style="font-size: 4rem; color: #6c757d;"></i>
                    <h5 class="mt-3">No Submissions Yet</h5>
                    <p class="text-muted">You haven't submitted any capstone project yet.</p>
                    <a href="submit_manuscript.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Submit Your First Project
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Show existing submission -->
            <div class="card shadow-sm p-4">
                <div class="row g-3">
                    <div class="col-md-12">
                        <h6 class="text-muted mb-1">Submitted By</h6>
                        <?php 
                        $submitterFirst = isset($submission['first_name']) ? $submission['first_name'] : '';
                        $submitterLast = isset($submission['last_name']) ? $submission['last_name'] : '';
                        $submitterEmailSafe = isset($submission['submitter_email']) ? $submission['submitter_email'] : '';
                        ?>
                        <p><?php echo htmlspecialchars(trim($submitterFirst . ' ' . $submitterLast)); ?> (<?php echo htmlspecialchars($submitterEmailSafe); ?>)</p>
                    </div>
                    <div class="col-md-12">
                        <h5>Title</h5>
                        <p><?php echo htmlspecialchars($submission['title']); ?></p>
                    </div>
                    <div class="col-md-12">
                        <h5>Authors</h5>
                        <p><?php echo htmlspecialchars(parseAuthorData($submission['author'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h5>Year</h5>
                        <p><?php echo htmlspecialchars($submission['year']); ?></p>
                    </div>
                    <div class="col-md-12">
                        <h5>Abstract</h5>
                        <p><?php echo nl2br(htmlspecialchars($submission['abstract'])); ?></p>
                    </div>
                    <div class="col-md-12">
                        <h5>Keywords</h5>
                        <p><?php echo htmlspecialchars($submission['keywords']); ?></p>
                    </div>
                    <div class="col-md-12">
                        <h5>Document</h5>
                        <p><a href="<?php echo htmlspecialchars($submission['document_path']); ?>" target="_blank" class="btn btn-outline-primary">
                            <i class="bi bi-file-pdf"></i> View PDF
                        </a></p>
                    </div>
                    <div class="col-md-12">
                        <h5>Status</h5>
                        <?php
                        $status = strtolower(trim(isset($submission['status']) ? $submission['status'] : ''));
                        $status_class = '';
                        $status_text = 'Pending';
                        switch ($status) {
                            case 'nonverified':
                            case 'not verified':
                            case 'pending':
                            case '':
                                $status_class = 'status-pending';
                                $status_text = 'Pending';
                                break;
                            case 'verified':
                            case 'approved':
                                $status_class = 'status-approved';
                                $status_text = 'Approved';
                                break;
                            case 'reject':
                            case 'rejected':
                                $status_class = 'status-rejected';
                                $status_text = 'Rejected';
                                break;
                            default:
                                $status_class = 'status-pending';
                                $status_text = 'Pending';
                                break;
                        }
                        ?>
                        <span class="status <?php echo $status_class; ?>"><?php echo htmlspecialchars($status_text); ?></span>
                        <?php if ($status === 'rejected'): ?>
                            <div class="mt-2">
                                <small class="text-muted d-block">Remarks:</small>
                                <div class="border rounded p-2 bg-light">
                                    <?php echo nl2br(htmlspecialchars($latestRejectionRemarks !== '' ? $latestRejectionRemarks : 'No remarks provided.')); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
</body>
</html>
<?php
// Close the connection at the end of the script
if (isset($conn)) {
    mysqli_close($conn);
}
?>