<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Research Repository</title>
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

    <div class="d-flex justify-content-center align-items-center bg-light flex-column" style="height: 300px;">
        <h1 class="fw-bold mb-4 text-center" style="font-family: 'Inter', sans-serif;">CCS Research Repository</h1>
        <form action="search-result.php" method="GET" class="d-flex w-100 justify-content-center" style="max-width: 600px;">
            <input 
                type="text" 
                name="query" 
                class="form-control form-control-lg me-2" 
                placeholder="Search research projects..." 
                required
            >
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-search"></i>
            </button>
        </form>
    </div>

    <!-- Quick Access to Bookmarks -->
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <a href="bookmark.php" class="btn btn-outline-primary btn-lg w-100">
                    <i class="bi bi-bookmark-heart me-2"></i>
                    View My Bookmarks
                </a>
            </div>
        </div>
    </div>

</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
</body>
</html>
