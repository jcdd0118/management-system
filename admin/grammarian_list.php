<?php
// Start the session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // If not logged in, redirect to the login page
    header("Location: ../users/login.php");
    exit(); // Stop further execution
}

// Database connection
include '../config/database.php';

// Fetch all grammarians (users with role 'grammarian')
$query = "SELECT * FROM users WHERE role = 'grammarian' ORDER BY id DESC";
$result = mysqli_query($conn, $query);

// Close connection
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grammarian List - Captrack Vault</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../assets/css/styles.css" rel="stylesheet">
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
                        Grammarian List
                    </h1>
                    <p class="text-muted">Manage grammarian accounts</p>
                </div>
            </div>

            <!-- Add Grammarian Button -->
            <div class="row mb-4">
                <div class="col-12">
                    <a href="add_user.php" class="btn btn-primary">
                        <i class="bi bi-person-plus-fill me-2"></i>
                        Add Grammarian
                    </a>
                </div>
            </div>

            <!-- Grammarian Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Gender</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                                <tr>
                                                    <td><?php echo $row['id']; ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                    <td><?php echo ucfirst($row['gender']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $row['status'] === 'verified' ? 'success' : 'warning'; ?>">
                                                            <?php echo ucfirst($row['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="update_grammarian.php?id=<?php echo $row['id']; ?>" 
                                                               class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <a href="delete_grammarian.php?id=<?php echo $row['id']; ?>" 
                                                               class="btn btn-sm btn-outline-danger"
                                                               onclick="return confirm('Are you sure you want to delete this grammarian?')">
                                                                <i class="bi bi-trash"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-person-x" style="font-size: 3rem; color: #6c757d;"></i>
                                    <h4 class="mt-3">No Grammarians Found</h4>
                                    <p class="text-muted">There are no grammarian accounts in the system.</p>
                                    <a href="add_user.php" class="btn btn-primary">
                                        <i class="bi bi-person-plus-fill me-2"></i>
                                        Add First Grammarian
                                    </a>
                                </div>
                            <?php endif; ?>
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
