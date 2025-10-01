<?php
// Start the session
session_start();

// Check if the user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../users/login.php");
    exit();
}

include '../config/database.php';

// Determine if student can access Submit Manuscript (group-aware: any groupmate's project approved by Grammarian)
$can_access_submit_manuscript = false;
$current_project_id = null;
if (isset($_SESSION['email'])) {
    $email = $_SESSION['email'];

    // Get the latest project from any member in the same group_code
    $projStmt = $conn->prepare(
        "SELECT pw.id
         FROM project_working_titles pw
         WHERE pw.submitted_by IN (
           SELECT s2.email FROM students s2
           WHERE s2.group_code = (
             SELECT s.group_code FROM students s WHERE s.email = ? LIMIT 1
           )
         ) OR pw.submitted_by = ?
         ORDER BY pw.id DESC
         LIMIT 1"
    );
    if ($projStmt) {
        $projStmt->bind_param("ss", $email, $email);
        $projStmt->execute();
        $projRes = $projStmt->get_result();
        if ($projRow = $projRes->fetch_assoc()) {
            $current_project_id = (int)$projRow['id'];
        }
        $projStmt->close();
    }

    if ($current_project_id) {
        // Check if grammarian has approved the manuscript review for that project
        $manuStmt = $conn->prepare("SELECT status FROM manuscript_reviews WHERE project_id = ? ORDER BY id DESC LIMIT 1");
        if ($manuStmt) {
            $manuStmt->bind_param("i", $current_project_id);
            $manuStmt->execute();
            $manuRes = $manuStmt->get_result();
            if ($manuRow = $manuRes->fetch_assoc()) {
                if ($manuRow['status'] === 'approved') {
                    $can_access_submit_manuscript = true;
                }
            }
            $manuStmt->close();
        }
    }
}

// Check if group already has a submission (one capstone per group)
$groupSubmissionCount = 0;
if (isset($_SESSION['email'])) {
    $emailForGroup = $_SESSION['email'];
    $query = "
        SELECT COUNT(*) AS submission_count
        FROM capstone c
        WHERE c.user_id IN (
            SELECT u.id
            FROM users u
            INNER JOIN students s ON u.email = s.email
            WHERE s.group_code = (
                SELECT s2.group_code FROM students s2 WHERE s2.email = ? LIMIT 1
            )
        )
    ";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $emailForGroup);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $groupSubmissionCount = (int)mysqli_fetch_assoc($result)['submission_count'];
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Captrack Vault - Submit Capstone</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <style>
        .author-form-group {
            border: 1px solid #dee2e6;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            position: relative;
        }
        .remove-author {
            position: absolute;
            top: 5px;
            right: 5px;
            cursor: pointer;
            color: #dc3545;
        }
        .remove-author:hover {
            color: #a71d2a;
        }
        .required-label {
            font-size: 0.75rem;
            color: #dc3545;
        }
    
    </style>
</head>
<body>


<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <?php include '../assets/includes/navbar.php'; ?>

    <!-- Form Container -->
    <div class="container mt-4">
        <?php if (!$can_access_submit_manuscript): ?>
            <div class="alert alert-warning shadow-sm">
                <h5 class="mb-2"><i class="bi bi-info-circle"></i> Complete all steps first</h5>
                <p class="mb-2">You need to finish Steps 1–4 (Project Title, Title Defense, Final Defense, and Grammarian approval) before submitting your final manuscript.</p>
                <div class="small text-muted">Tip: Go back to your workflow and ensure the Grammarian has approved your manuscript.</div>
            </div>
        <?php elseif ($groupSubmissionCount > 0): ?>
            <div class="card shadow-sm p-4">
                <h4 class="mb-3"><i class="bi bi-file-text-fill"></i> Submission Status</h4>
                <p>Your group has already submitted a capstone project. Only one submission is allowed per group.</p>
                <a href="my_projects.php" class="btn btn-primary"><i class="bi bi-eye"></i> View Your Submission</a>
            </div>
        <?php else: ?>
            <h4 class="mb-3"><i class="bi bi-file-text-fill"></i> Submit Capstone Manuscript</h4>
            <div class="card shadow-sm p-4">
                <form action="process_capstone.php" method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" id="title"
                                value="<?= isset($_SESSION['old_input']['title']) ? htmlspecialchars($_SESSION['old_input']['title']) : '' ?>"
                                required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Authors</label>
                            <div id="authors-container">
                                <!-- Initial author form will be added by JavaScript -->
                            </div>
                            <button type="button" class="btn btn-primary mt-2" id="add-author-btn"><i class="bi bi-plus"></i> Add Author</button>
                            <input type="hidden" name="authors" id="authors-input">
                        </div>
                        <div class="col-md-6">
                            <label for="year" class="form-label">Year</label>
                            <input type="number" name="year" class="form-control" id="year"
                                value="<?= isset($_SESSION['old_input']['year']) ? htmlspecialchars($_SESSION['old_input']['year']) : date('Y') ?>"
                                min="1900" max="<?= date('Y') ?>" required>
                        </div>
                        <div class="col-md-12">
                            <label for="abstract" class="form-label">Abstract</label>
                            <textarea name="abstract" class="form-control" id="abstract" rows="5" required><?= isset($_SESSION['old_input']['abstract']) ? htmlspecialchars($_SESSION['old_input']['abstract']) : '' ?></textarea>
                        </div>
                        <div class="col-md-12">
                            <label for="keywords" class="form-label">Keywords</label>
                            <input type="text" name="keywords" class="form-control" id="keywords"
                                value="<?= isset($_SESSION['old_input']['keywords']) ? htmlspecialchars($_SESSION['old_input']['keywords']) : '' ?>"
                                required>
                            <small class="text-muted">Separate keywords with commas (e.g., AI, Machine Learning, Data Science)</small>
                        </div>
                        <div class="col-md-12">
                            <label for="document" class="form-label">Document (PDF)</label>
                            <input type="file" name="document" class="form-control" id="document" accept=".pdf" required>
                            <small class="text-muted">Upload a PDF file (max 50MB)</small>
                        </div>
                        <div class="col-md-12">
                            <div id="form-error-message" class="alert alert-danger d-none"></div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Submit Capstone</button>
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='student_dashboard.php'">Cancel</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Success/Error Message Handling -->
<?php
$errorMessage = isset($_SESSION['error_message']) ? json_encode($_SESSION['error_message']) : 'null';
unset($_SESSION['error_message']);
unset($_SESSION['old_input']);
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var errorMsg = <?= $errorMessage ?>;
    var errorDiv = document.getElementById('form-error-message');

    if (errorMsg && errorDiv) {
        errorDiv.textContent = errorMsg;
        errorDiv.classList.remove('d-none');
        errorDiv.style.opacity = 1;

        setTimeout(function() {
            var fadeEffect = setInterval(function () {
                if (!errorDiv.style.opacity) {
                    errorDiv.style.opacity = 1;
                }
                if (errorDiv.style.opacity > 0) {
                    errorDiv.style.opacity -= 0.05;
                } else {
                    clearInterval(fadeEffect);
                    errorDiv.classList.add('d-none');
                }
            }, 50);
        }, 3000);
    }

    // Load old authors if available
    var oldAuthors = <?= json_encode(isset($_SESSION['old_input']['authors']) ? json_decode($_SESSION['old_input']['authors'], true) : []) ?>;
    if (oldAuthors.length > 0) {
        oldAuthors.forEach(addAuthorForm);
    } else {
        addAuthorForm(); // Add initial author form
    }

    // Add Author Button Click Handler
    var addButton = document.getElementById('add-author-btn');
    if (addButton) {
        addButton.addEventListener('click', function () {
            console.log('Add Author button clicked');
            addAuthorForm();
        });
    } else {
        console.error('Add Author button not found');
    }

    // Function to add a new author form
    function addAuthorForm(data = {}) {
        if (document.querySelectorAll('.author-form-group').length >= 10) {
            alert('Maximum 10 authors allowed.');
            return;
        }
        var container = document.getElementById('authors-container');
        if (!container) {
            console.error('Authors container not found');
            return;
        }
        var formGroup = document.createElement('div');
        formGroup.className = 'author-form-group row g-2';
        formGroup.innerHTML = `
            <div class="col-md-4">
                <label class="form-label">First Name</label>
                <small class="required-label">required</small>
                <input type="text" class="form-control author-first-name" value="${data.firstName || ''}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Middle Name</label>
                <input type="text" class="form-control author-middle-name" value="${data.middleName || ''}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Last Name</label>
                <small class="required-label">required</small>
                <input type="text" class="form-control author-last-name" value="${data.lastName || ''}" required>
            </div>
            <div class="col-md-1">
                <label class="form-label">Suffix</label>
                <input type="text" class="form-control author-suffix" value="${data.suffix || ''}">
            </div>
            <div class="col-md-1">
                <i class="bi bi-trash remove-author" style="font-size: 1.5rem; margin-top: 0.2rem;"></i>
            </div>
        `;

        container.appendChild(formGroup);

        // Add remove event listener
        formGroup.querySelector('.remove-author').addEventListener('click', function () {
            formGroup.remove();
            updateAuthorsInput();
        });

        updateAuthorsInput();
    }

    // Function to update the hidden authors input with JSON
    function updateAuthorsInput() {
        var authorForms = document.querySelectorAll('.author-form-group');
        var authors = [];
        authorForms.forEach(function (form) {
            var author = {
                firstName: form.querySelector('.author-first-name').value,
                middleName: form.querySelector('.author-middle-name').value || null,
                lastName: form.querySelector('.author-last-name').value,
                suffix: form.querySelector('.author-suffix').value || null
            };
            authors.push(author);
        });
        document.getElementById('authors-input').value = JSON.stringify(authors);
    }

    // Update authors input on any change
    document.getElementById('authors-container').addEventListener('input', updateAuthorsInput);
});
</script>

<!-- Success Message Handling -->
<?php
$successMessage = isset($_SESSION['success_message']) ? json_encode($_SESSION['success_message']) : 'null';
unset($_SESSION['success_message']);
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var successMsg = <?= $successMessage ?>;
    if (successMsg) {
        var alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-success position-fixed start-50 translate-middle-x mt-3 px-4 py-3 shadow';
        alertDiv.style.top = '0';
        alertDiv.style.zIndex = '1055';
        alertDiv.innerHTML = successMsg;
        document.body.appendChild(alertDiv);
        setTimeout(function () {
            alertDiv.remove();
            window.location.href = 'my_projects.php';
        }, 3000);
    }
});
</script>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
</body>
</html>