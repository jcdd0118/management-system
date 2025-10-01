<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../users/login.php");
    exit();
}

// Database connection
require_once '../config/database.php';

// Pagination and search parameters
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$offset = ($page - 1) * $limit;

// Fetch students
$query = "SELECT * FROM students WHERE 1=1";
$params = [];
if (!empty($search)) {
    $query .= " AND (CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ? 
                OR email LIKE ? 
                OR year_section LIKE ? 
                OR group_code LIKE ?)";
    $search_param = "%" . $search . "%";
    $params = [$search_param, $search_param, $search_param, $search_param];
}
$query .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = mysqli_prepare($conn, $query);
if (!empty($search)) {
    mysqli_stmt_bind_param($stmt, "ssssii", ...$params);
} else {
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
}
mysqli_stmt_execute($stmt);
$students = mysqli_stmt_get_result($stmt);

// Get total students for pagination
$total_query = "SELECT COUNT(id) as total_students FROM students WHERE 1=1";
if (!empty($search)) {
    $total_query .= " AND (CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ? 
                     OR email LIKE ? 
                     OR year_section LIKE ? 
                     OR group_code LIKE ?)";
}
$total_stmt = mysqli_prepare($conn, $total_query);
if (!empty($search)) {
    mysqli_stmt_bind_param($total_stmt, "ssss", $search_param, $search_param, $search_param, $search_param);
}
mysqli_stmt_execute($total_stmt);
$total_result = mysqli_stmt_get_result($total_stmt);
$total_row = mysqli_fetch_assoc($total_result);
$total_students = $total_row['total_students'];
$total_pages = ceil($total_students / $limit);

// Close connections
mysqli_stmt_close($stmt);
mysqli_stmt_close($total_stmt);
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student List - Captrack Vault</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <?php include '../assets/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../assets/includes/navbar.php'; ?>
        <h4 class="mb-3">Student List</h4>

        <!-- Controls (Mobile and Desktop) -->
        <div class="controls mb-3">
            <!-- Mobile Controls -->
            <div class="d-block d-md-none">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="d-flex align-items-center">
                            <small class="text-muted me-2">Show</small>
                            <select id="showEntriesMobile" class="form-select form-select-sm" onchange="updateTable(1, this.value)">
                                <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex flex-column gap-1">
                            <button class="btn btn-success btn-sm" onclick="exportStudents()"><i class="bi bi-download"></i> Export</button>
                            <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#importStudentModal"><i class="bi bi-upload"></i> Import</button>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal"><i class="bi bi-person-plus-fill"></i> Add Student</button>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12">
                        <form action="student_list.php" method="GET">
                            <div class="input-group" style="border-radius: 10px; overflow: hidden; border: 1px solid #ddd; background:white;">
                                <span class="input-group-text bg-transparent border-0 px-2"><i class="bi bi-search text-muted"></i></span>
                                <input type="search" id="searchInputMobile" name="search" class="form-control bg-transparent border-0 shadow-none" placeholder="Search students..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Desktop Controls -->
            <div class="d-none d-md-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <span class="me-2">Show</span>
                    <select id="showEntries" class="form-select form-select-sm me-2" style="width: auto;" onchange="updateTable(1, this.value)">
                        <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                    <span>entries</span>
                </div>
                <div class="flex-grow-1 mx-4" style="max-width: 400px;">
                    <form action="student_list.php" method="GET">
                        <div class="input-group" style="border-radius: 10px; overflow: hidden; border: 1px solid #ddd; background:white;">
                            <span class="input-group-text bg-transparent border-0 px-2"><i class="bi bi-search text-muted"></i></span>
                            <input type="search" id="searchInputDesktop" name="search" class="form-control bg-transparent border-0 shadow-none" placeholder="Search students..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </form>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success btn-sm" onclick="exportStudents()"><i class="bi bi-download"></i> Export Excel</button>
                    <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#importStudentModal"><i class="bi bi-upload"></i> Import Excel</button>
                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#runRolloverModal"><i class="bi bi-arrow-repeat"></i> Start Next S.Y.</button>
                    <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#runCleanupModal"><i class="bi bi-trash3"></i> Cleanup Graduated</button>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal"><i class="bi bi-person-plus-fill"></i> Add Student</button>
                </div>
            </div>
        </div>

        <!-- Student Table -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle bg-white">
                <thead class="table-light">
                    <tr>
                        <th class="sortable" data-sort="name">Name <i class="bi bi-sort-alpha-down"></i></th>
                        <th>Email</th>
                        <th class="text-center sortable" data-sort="year">Year <i class="bi bi-sort-numeric-down"></i></th>
                        <th class="text-center sortable" data-sort="status">Status <i class="bi bi-sort-alpha-down"></i></th>
                        <th class="text-center">
                            <div class="d-inline-flex align-items-center gap-1">
                                <span>Ready to Graduate</span>
                                <input type="checkbox" id="readyAllToggle" class="form-check-input" title="Toggle all eligible on this page">
                            </div>
                        </th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="studentTableBody">
                    <?php if (mysqli_num_rows($students) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($students)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars("{$row['last_name']}, {$row['first_name']} {$row['middle_name']}"); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($row['year_section']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'verified' ? 'success' : 'danger'; ?>">
                                        <?php echo $row['status'] === 'verified' ? 'Verified' : 'Not Verified'; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input ready-toggle" data-id="<?php echo $row['id']; ?>" <?php echo $row['ready_to_graduate'] ? 'checked' : ''; ?> <?php echo (isset($row['year_section']) && substr($row['year_section'], 0, 1) === '3') ? 'disabled' : ''; ?> />
                                </td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Action</button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <button class="dropdown-item" onclick="viewStudent('<?php echo addslashes($row['id']); ?>', '<?php echo addslashes($row['first_name']); ?>', '<?php echo addslashes($row['middle_name']); ?>', '<?php echo addslashes($row['last_name']); ?>', '<?php echo addslashes($row['email']); ?>', '<?php echo addslashes($row['gender']); ?>', '<?php echo addslashes($row['year_section']); ?>', '<?php echo addslashes($row['group_code']); ?>')">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars("{$row['last_name']}, {$row['first_name']} {$row['middle_name']}", ENT_QUOTES); ?>">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </li>
                                            <?php if ($row['status'] !== 'verified'): ?>
                                                <li>
                                                    <button class="dropdown-item" onclick="window.location.href='verify_student.php?id=<?php echo $row['id']; ?>'">
                                                        <i class="bi bi-check-circle"></i> Verify
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center">No students found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-end mt-3">
            <nav>
                <ul class="pagination mb-0" id="pagination">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="javascript:void(0)" data-page="<?php echo max($page - 1, 1); ?>" data-limit="<?php echo $limit; ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="javascript:void(0)" data-page="<?php echo $i; ?>" data-limit="<?php echo $limit; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="javascript:void(0)" data-page="<?php echo min($page + 1, $total_pages); ?>" data-limit="<?php echo $limit; ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content rounded-4 shadow-sm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addStudentModalLabel"><i class="bi bi-person-plus-fill"></i> Add Student</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="add_student.php" method="POST">
                    <div class="modal-body px-4 py-3">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="firstName" class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-control" id="firstName" value="<?php echo isset($_SESSION['old_input']['first_name']) ? htmlspecialchars($_SESSION['old_input']['first_name']) : ''; ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="middleName" class="form-label">Middle Name</label>
                                <input type="text" name="middle_name" class="form-control" id="middleName" value="<?php echo isset($_SESSION['old_input']['middle_name']) ? htmlspecialchars($_SESSION['old_input']['middle_name']) : ''; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="lastName" class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control" id="lastName" value="<?php echo isset($_SESSION['old_input']['last_name']) ? htmlspecialchars($_SESSION['old_input']['last_name']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" id="email" value="<?php echo isset($_SESSION['old_input']['email']) ? htmlspecialchars($_SESSION['old_input']['email']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" id="password" required>
                            </div>
                            <div class="col-md-6">
                                <label for="year_section" class="form-label">Year & Section</label>
                                <select class="form-select" id="year_section" name="year_section" required>
                                    <option value="" disabled <?php echo !isset($_SESSION['old_input']['year_section']) ? 'selected' : ''; ?>>Choose...</option>
                                    <option value="3A" <?php echo isset($_SESSION['old_input']['year_section']) && $_SESSION['old_input']['year_section'] == '3A' ? 'selected' : ''; ?>>3A</option>
                                    <option value="3B" <?php echo isset($_SESSION['old_input']['year_section']) && $_SESSION['old_input']['year_section'] == '3B' ? 'selected' : ''; ?>>3B</option>
                                    <option value="4A" <?php echo isset($_SESSION['old_input']['year_section']) && $_SESSION['old_input']['year_section'] == '4A' ? 'selected' : ''; ?>>4A</option>
                                    <option value="4B" <?php echo isset($_SESSION['old_input']['year_section']) && $_SESSION['old_input']['year_section'] == '4B' ? 'selected' : ''; ?>>4B</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label d-block">Gender</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="gender" id="genderMale" value="Male" <?php echo isset($_SESSION['old_input']['gender']) && $_SESSION['old_input']['gender'] == 'Male' ? 'checked' : ''; ?> required>
                                    <label class="form-check-label" for="genderMale">Male</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="gender" id="genderFemale" value="Female" <?php echo isset($_SESSION['old_input']['gender']) && $_SESSION['old_input']['gender'] == 'Female' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="genderFemale">Female</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="group_code" class="form-label">Group (YearSection-Group#)</label>
                                <input type="text" class="form-control" id="group_code" name="group_code" value="<?php echo isset($_SESSION['old_input']['group_code']) ? htmlspecialchars($_SESSION['old_input']['group_code']) : ''; ?>" pattern="^[3-4][ABCD]-G\d+$" required>
                                <small class="text-muted">Format: 3B-G1 or 4A-G2</small>
                            </div>
                            <div class="col-md-6">
                                <div id="modal-error-message" class="alert alert-danger d-none"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer px-4 py-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php unset($_SESSION['old_input']); ?>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete <strong id="studentName"></strong>?<br>This action will also remove the user account.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" class="btn btn-danger" id="confirmDeleteBtn">Yes, Delete</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Student Modal -->
    <div class="modal fade" id="importStudentModal" tabindex="-1" aria-labelledby="importStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 shadow-sm">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="importStudentModalLabel"><i class="bi bi-upload"></i> Import Students from Excel</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="import_students.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body px-4 py-3">
                        <div class="mb-3">
                            <label for="excel_file" class="form-label">Select Excel File (.xls or .csv)</label>
                            <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xls,.csv" required>
                            <div class="form-text">
                                <strong>Expected format:</strong><br>
                                ID, First Name, Middle Name, Last Name, Email, Gender, Year Section, Group Code, Status<br>
                                <small class="text-muted">Note: ID column will be ignored, Status is optional (defaults to 'not verified')</small>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle"></i> Import Guidelines:</h6>
                            <ul class="mb-0">
                                <li>First row should contain headers</li>
                                <li>Gender must be 'Male' or 'Female'</li>
                                <li>Year Section must be '3A', '3B', '4A', or '4B'</li>
                                <li>Group Code format: 3A-G1, 4B-G2, etc.</li>
                                <li>Email must be unique and valid</li>
                                <li>The password of imported students is 'password123'</li>
                            </ul>
                            <hr>
                            <a href="download_template.php?type=students" class="btn btn-outline-primary btn-sm"><i class="bi bi-download"></i> Download Template</a>
                        </div>
                    </div>
                    <div class="modal-footer px-4 py-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">Import Students</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Student Modal -->
    <div class="modal fade" id="viewStudentModal" tabindex="-1" aria-labelledby="viewStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content rounded-4">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-person-lines-fill"></i> View Student</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="viewStudentForm" method="POST" action="update_student.php">
                    <div class="modal-body px-4 py-3">
                        <input type="hidden" name="id" id="studentId">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" id="firstNameView" name="first_name" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middleNameView" name="middle_name" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lastNameView" name="last_name" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" id="emailView" name="email" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Gender</label>
                                <select class="form-control" id="genderView" name="gender" disabled>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Year & Section</label>
                                <select class="form-control" id="yearSectionView" name="year_section" disabled>
                                    <option value="3A">3A</option>
                                    <option value="3B">3B</option>
                                    <option value="4A">4A</option>
                                    <option value="4B">4B</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Group Code</label>
                                <input type="text" class="form-control" id="groupCodeView" name="group_code" readonly pattern="^[3-4][AB]-G\d+$" title="Format example: 3A-G1, 4B-G3">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer px-4 py-3">
                        <button type="button" id="editButton" class="btn btn-warning">Edit</button>
                        <button type="submit" id="saveButton" class="btn btn-success d-none">Update</button>
                        <button type="button" id="cancelButton" class="btn btn-secondary d-none">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rollover Modal -->
    <div class="modal fade" id="runRolloverModal" tabindex="-1" aria-labelledby="runRolloverModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="runRolloverModalLabel"><i class="bi bi-exclamation-triangle-fill"></i> Confirm Start of Next School Year</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Step 1: Acknowledge -->
                    <div id="rolloverStep1">
                        <div class="alert alert-warning">
                            <div class="fw-semibold mb-2">Please read before proceeding:</div>
                            <ul class="mb-0">
                                <li>Promotes all 3rd year students to 4th year.</li>
                                <li>Marks 4th year students with "Ready to Graduate" as graduated.</li>
                                <li>This operation cannot be undone automatically.</li>
                            </ul>
                        </div>
                    </div>
                    <!-- Step 2: Password -->
                    <div id="rolloverStep2" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Enter Admin Password to proceed</label>
                            <input type="password" class="form-control" id="rolloverPassword" placeholder="Password" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <!-- Step 1 footer -->
                    <div id="rolloverFooterStep1" class="w-100 d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="rolloverAcknowledgeBtn" disabled>I Understand (5)</button>
                    </div>
                    <!-- Step 2 footer -->
                    <div id="rolloverFooterStep2" class="w-100 d-none d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmRolloverBtn">Run Rollover</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cleanup Modal -->
    <div class="modal fade" id="runCleanupModal" tabindex="-1" aria-labelledby="runCleanupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="runCleanupModalLabel"><i class="bi bi-exclamation-triangle-fill"></i> Confirm Cleanup Graduated Accounts</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Step 1: Acknowledge -->
                    <div id="cleanupStep1">
                        <div class="alert alert-warning">
                            <div class="fw-semibold mb-2">Please read before proceeding:</div>
                            <ul class="mb-0">
                                <li>Deletes student accounts marked as graduated for more than 3 months.</li>
                                <li>Capstone and manuscript files remain in the repository.</li>
                                <li>This action permanently removes login access for those accounts.</li>
                            </ul>
                        </div>
                    </div>
                    <!-- Step 2: Password -->
                    <div id="cleanupStep2" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Enter Admin Password to proceed</label>
                            <input type="password" class="form-control" id="cleanupPassword" placeholder="Password" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <!-- Step 1 footer -->
                    <div id="cleanupFooterStep1" class="w-100 d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="cleanupAcknowledgeBtn" disabled>I Understand (5)</button>
                    </div>
                    <!-- Step 2 footer -->
                    <div id="cleanupFooterStep2" class="w-100 d-none d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmCleanupBtn">Run Cleanup</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['update']) && $_GET['update'] == 'success'): ?>
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">Success</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Student information saved successfully!
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Import Results Modal -->
    <div class="modal fade" id="importResultsModal" tabindex="-1" aria-labelledby="importResultsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="importResultsModalLabel"><i class="bi bi-check-circle-fill"></i> Import Completed</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="importResultsBody">
                    <!-- populated by JS -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

<script>
// Export students
function exportStudents() {
    window.location.href = 'export_students.php';
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Update table
function updateTable(page, limit, search = '') {
    fetch(`search_students.php?page=${page}&limit=${limit}&search=${encodeURIComponent(search)}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            const tbody = document.getElementById('studentTableBody');
            const pagination = document.getElementById('pagination');
            tbody.innerHTML = '';

            if (data.students.length > 0) {
                        data.students.forEach(student => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${student.full_name}</td>
                        <td>${student.email}</td>
                        <td class="text-center">${student.year_section}</td>
                        <td class="text-center">
                            <span class="badge bg-${student.status === 'verified' ? 'success' : 'danger'}">
                                ${student.status === 'verified' ? 'Verified' : 'Not Verified'}
                            </span>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input ready-toggle" data-id="${student.id}" ${student.ready_to_graduate ? 'checked' : ''} ${String(student.year_section).startsWith('3') ? 'disabled' : ''} />
                        </td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Action</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button class="dropdown-item" onclick="viewStudent('${student.id}', '${student.first_name}', '${student.middle_name}', '${student.last_name}', '${student.email}', '${student.gender}', '${student.year_section}', '${student.group_code}')">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-id="${student.id}" data-name="${student.full_name.replace(/'/g, "\\'")}">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </li>
                                    ${student.status !== 'verified' ? `
                                        <li>
                                            <button class="dropdown-item" onclick="window.location.href='verify_student.php?id=${student.id}'">
                                                <i class="bi bi-check-circle"></i> Verify
                                            </button>
                                        </li>` : ''}
                                </ul>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">No students found.</td></tr>';
            }

            pagination.innerHTML = `
                <li class="page-item ${data.current_page <= 1 ? 'disabled' : ''}">
                    <a class="page-link" href="javascript:void(0)" data-page="${data.current_page - 1}" data-limit="${data.limit}">Previous</a>
                </li>
                ${Array.from({ length: data.total_pages }, (_, i) => i + 1).map(i => `
                    <li class="page-item ${data.current_page === i ? 'active' : ''}">
                        <a class="page-link" href="javascript:void(0)" data-page="${i}" data-limit="${data.limit}">${i}</a>
                    </li>
                `).join('')}
                <li class="page-item ${data.current_page >= data.total_pages ? 'disabled' : ''}">
                    <a class="page-link" href="javascript:void(0)" data-page="${data.current_page + 1}" data-limit="${data.limit}">Next</a>
                </li>
            `;

            const url = new URL(window.location);
            url.searchParams.set('page', data.current_page);
            url.searchParams.set('limit', data.limit);
            if (search.trim()) {
                url.searchParams.set('search', search);
            } else {
                url.searchParams.delete('search');
            }
            window.history.pushState({}, '', url);

            // After re-render, sync header toggle state
            syncReadyAllHeaderState();
        })
        .catch(error => {
            console.error('Error fetching students:', error);
            document.getElementById('studentTableBody').innerHTML = '<tr><td colspan="6" class="text-center">Error loading students.</td></tr>';
            document.getElementById('pagination').innerHTML = '';
        });
}

// Global variable for original form data
let originalFormData = {};

function viewStudent(id, first, middle, last, email, gender, year_section, group_code) {
    document.getElementById('viewStudentForm').reset();
    const normalizedGender = gender.charAt(0).toUpperCase() + gender.slice(1).toLowerCase();

    originalFormData = { id, first_name: first, middle_name: middle, last_name: last, email, gender: normalizedGender, year_section, group_code };

    document.getElementById('studentId').value = id;
    document.getElementById('firstNameView').value = first;
    document.getElementById('middleNameView').value = middle;
    document.getElementById('lastNameView').value = last;
    document.getElementById('emailView').value = email;
    document.getElementById('genderView').value = normalizedGender;
    document.getElementById('yearSectionView').value = year_section;
    document.getElementById('groupCodeView').value = group_code;

    document.querySelectorAll('#viewStudentForm input:not([name="email"]):not([type="hidden"])').forEach(el => el.setAttribute('readonly', true));
    document.querySelectorAll('#viewStudentForm select').forEach(el => el.setAttribute('disabled', true));

    document.getElementById('editButton').classList.remove('d-none');
    document.getElementById('saveButton').classList.add('d-none');
    document.getElementById('cancelButton').classList.add('d-none');

    new bootstrap.Modal(document.getElementById('viewStudentModal')).show();
}

document.addEventListener('DOMContentLoaded', () => {
    // Error handling
    const errorMsg = <?php echo isset($_SESSION['error_message']) ? json_encode($_SESSION['error_message']) : 'null'; ?>;
    const errorDiv = document.getElementById('modal-error-message');
    if (errorMsg && errorDiv) {
        errorDiv.textContent = errorMsg;
        errorDiv.classList.remove('d-none');
        errorDiv.style.opacity = 1;
        new bootstrap.Modal(document.getElementById('addStudentModal')).show();
        setTimeout(() => {
            let opacity = 1;
            const fade = setInterval(() => {
                if (opacity <= 0) {
                    clearInterval(fade);
                    errorDiv.classList.add('d-none');
                } else {
                    errorDiv.style.opacity = opacity -= 0.05;
                }
            }, 50);
        }, 3000);
    }
    <?php unset($_SESSION['error_message']); ?>

    // Show modal if requested
    <?php if (isset($_GET['show_modal'])): ?>
        new bootstrap.Modal(document.getElementById('addStudentModal')).show();
    <?php endif; ?>

    // Success message
    <?php if (isset($_SESSION['success_message'])): ?>
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-success position-fixed start-50 translate-middle-x mt-3 px-4 py-3 shadow';
        alertDiv.style.top = '0';
        alertDiv.style.zIndex = '1055';
        alertDiv.innerHTML = <?php echo json_encode($_SESSION['success_message']); ?>;
        document.body.appendChild(alertDiv);
        setTimeout(() => alertDiv.remove(), 3000);
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    // Success modal for update
    <?php if (isset($_GET['update']) && $_GET['update'] == 'success'): ?>
        document.querySelectorAll('.modal').forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
        });
        new bootstrap.Modal(document.getElementById('successModal'), { backdrop: 'static' }).show();
        // Clean the URL by removing the update parameter
        (function() {
            try {
                const url = new URL(window.location.href);
                url.searchParams.delete('update');
                const newSearch = url.searchParams.toString();
                const cleaned = url.pathname + (newSearch ? '?' + newSearch : '');
                window.history.replaceState({}, document.title, cleaned);
            } catch (e) {
                // Fallback: remove simple ?update=... patterns
                const cleaned = window.location.pathname;
                window.history.replaceState({}, document.title, cleaned);
            }
        })();
    <?php endif; ?>

    // Show Import Results modal when redirected from import
    const importResults = <?php echo isset($_SESSION['import_results']) ? json_encode($_SESSION['import_results']) : 'null'; ?>;
    const showImportResults = <?php echo (isset($_GET['import']) && $_GET['import'] === 'completed') ? 'true' : 'false'; ?>;
    <?php unset($_SESSION['import_results']); ?>

    if (showImportResults && importResults) {
        try {
            const body = document.getElementById('importResultsBody');
            const success = importResults.success_count || 0;
            const errors = importResults.error_count || 0;
            const list = Array.isArray(importResults.errors) ? importResults.errors : [];
            let html = `<div class="mb-2">` +
                       `<div class="d-flex align-items-center gap-2"><span class="badge bg-success">Imported: ${success}</span>` +
                       `<span class="badge bg-danger">Errors: ${errors}</span></div>` +
                       `</div>`;
            if (list.length > 0) {
                html += '<div class="mt-2" style="max-height:220px; overflow:auto;">';
                html += '<ul class="mb-0">' + list.map(e => `<li class="small">${e}</li>`).join('') + '</ul>';
                html += '</div>';
            } else {
                html += '<div>No errors reported.</div>';
            }
            body.innerHTML = html;
            new bootstrap.Modal(document.getElementById('importResultsModal'), { backdrop: 'static' }).show();
        } catch (e) { /* noop */ }

        // Clean the URL by removing the import parameter
        (function() {
            try {
                const url = new URL(window.location.href);
                url.searchParams.delete('import');
                const newSearch = url.searchParams.toString();
                const cleaned = url.pathname + (newSearch ? '?' + newSearch : '');
                window.history.replaceState({}, document.title, cleaned);
            } catch (e) {
                const cleaned = window.location.pathname;
                window.history.replaceState({}, document.title, cleaned);
            }
        })();
    }

    // Search inputs
    const searchInputs = [document.getElementById('searchInputMobile'), document.getElementById('searchInputDesktop')];
    let currentSearchTerm = '<?php echo isset($_GET['search']) ? addslashes($_GET['search']) : ''; ?>';

    searchInputs.forEach(input => {
        if (input) {
            input.addEventListener('input', debounce(() => {
                currentSearchTerm = input.value.trim();
                updateTable(1, document.getElementById('showEntries').value, currentSearchTerm);
            }, 300));
        }
    });

    // Show entries
    document.getElementById('showEntries').addEventListener('change', () => updateTable(1, document.getElementById('showEntries').value, currentSearchTerm));
    document.getElementById('showEntriesMobile')?.addEventListener('change', () => updateTable(1, document.getElementById('showEntriesMobile').value, currentSearchTerm));

    // Pagination
    document.getElementById('pagination').addEventListener('click', e => {
        const target = e.target.closest('a.page-link');
        if (target) {
            e.preventDefault();
            const page = parseInt(target.getAttribute('data-page'));
            const limit = parseInt(target.getAttribute('data-limit'));
            if (!isNaN(page) && !isNaN(limit)) {
                updateTable(page, limit, currentSearchTerm);
            }
        }
    });

    // Ready to graduate toggle
    document.getElementById('studentTableBody').addEventListener('change', e => {
        const checkbox = e.target.closest('.ready-toggle');
        if (!checkbox) return;
        if (checkbox.disabled) return; // ignore disabled checkboxes
        const studentId = checkbox.getAttribute('data-id');
        const ready = checkbox.checked ? 1 : 0;
        fetch('api/toggle_ready_to_graduate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${encodeURIComponent(studentId)}&ready=${ready}`
        }).then(r => r.json()).then(res => {
            if (!res.success) {
                checkbox.checked = !checkbox.checked;
                alert(res.message || 'Failed to update.');
            }
            // Update header indeterminate state after single change
            syncReadyAllHeaderState();
        }).catch(() => {
            checkbox.checked = !checkbox.checked;
            alert('Network error.');
            syncReadyAllHeaderState();
        });
    });

    // Header toggle handler (toggle all eligible on current page)
    const readyAllHeader = document.getElementById('readyAllToggle');
    function getEligibleReadyCheckboxes() {
        return Array.from(document.querySelectorAll('#studentTableBody .ready-toggle'))
            .filter(cb => !cb.disabled);
    }
    function syncReadyAllHeaderState() {
        if (!readyAllHeader) return;
        const boxes = getEligibleReadyCheckboxes();
        if (boxes.length === 0) {
            readyAllHeader.checked = false;
            readyAllHeader.indeterminate = false;
            readyAllHeader.disabled = true;
            return;
        }
        readyAllHeader.disabled = false;
        const checkedCount = boxes.filter(cb => cb.checked).length;
        if (checkedCount === 0) {
            readyAllHeader.checked = false;
            readyAllHeader.indeterminate = false;
        } else if (checkedCount === boxes.length) {
            readyAllHeader.checked = true;
            readyAllHeader.indeterminate = false;
        } else {
            readyAllHeader.checked = false;
            readyAllHeader.indeterminate = true;
        }
    }
    if (readyAllHeader) {
        readyAllHeader.addEventListener('change', () => {
            const targetState = readyAllHeader.checked ? 1 : 0;
            const boxes = getEligibleReadyCheckboxes();
            // Apply UI state immediately for responsiveness
            boxes.forEach(cb => { cb.checked = !!targetState; });
            syncReadyAllHeaderState();
            // Persist each change
            boxes.forEach(cb => {
                const studentId = cb.getAttribute('data-id');
                fetch('api/toggle_ready_to_graduate.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${encodeURIComponent(studentId)}&ready=${targetState}`
                }).then(r => r.json()).then(res => {
                    if (!res.success) {
                        cb.checked = !cb.checked;
                        alert(res.message || 'Failed to update.');
                        syncReadyAllHeaderState();
                    }
                }).catch(() => {
                    cb.checked = !cb.checked;
                    alert('Network error.');
                    syncReadyAllHeaderState();
                });
            });
        });
    }

    // Delete confirmation
    document.getElementById('confirmDeleteModal').addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        document.getElementById('studentName').textContent = button.getAttribute('data-name');
        document.getElementById('confirmDeleteBtn').href = `delete_student.php?id=${button.getAttribute('data-id')}`;
    });

    // Edit/View student
    const editBtn = document.getElementById('editButton');
    const saveBtn = document.getElementById('saveButton');
    const cancelBtn = document.getElementById('cancelButton');
    const form = document.getElementById('viewStudentForm');
    const inputs = form.querySelectorAll('input:not([name="email"]):not([type="hidden"])');
    const selects = form.querySelectorAll('select');

    editBtn.addEventListener('click', () => {
        inputs.forEach(input => input.removeAttribute('readonly'));
        selects.forEach(select => select.removeAttribute('disabled'));
        editBtn.classList.add('d-none');
        saveBtn.classList.remove('d-none');
        cancelBtn.classList.remove('d-none');
    });

    cancelBtn.addEventListener('click', () => {
        document.getElementById('studentId').value = originalFormData.id;
        document.getElementById('firstNameView').value = originalFormData.first_name;
        document.getElementById('middleNameView').value = originalFormData.middle_name;
        document.getElementById('lastNameView').value = originalFormData.last_name;
        document.getElementById('emailView').value = originalFormData.email;
        document.getElementById('genderView').value = originalFormData.gender;
        document.getElementById('yearSectionView').value = originalFormData.year_section;
        document.getElementById('groupCodeView').value = originalFormData.group_code;

        inputs.forEach(input => input.setAttribute('readonly', true));
        selects.forEach(select => select.setAttribute('disabled', true));
        saveBtn.classList.add('d-none');
        cancelBtn.classList.add('d-none');
        editBtn.classList.remove('d-none');
    });

    // Rollover and Cleanup
    const confirmRolloverBtn = document.getElementById('confirmRolloverBtn');
    const confirmCleanupBtn = document.getElementById('confirmCleanupBtn');

    // Two-step confirmation helpers
    function setupTwoStepModal(modalId, step1Id, step2Id, footer1Id, footer2Id, ackBtnId) {
        const modalEl = document.getElementById(modalId);
        const step1 = document.getElementById(step1Id);
        const step2 = document.getElementById(step2Id);
        const footer1 = document.getElementById(footer1Id);
        const footer2 = document.getElementById(footer2Id);
        const ackBtn = document.getElementById(ackBtnId);
        let countdown = 5;
        let timer = null;

        function resetToStep1() {
            // Reset steps
            step1.classList.remove('d-none');
            step2.classList.add('d-none');
            footer1.classList.remove('d-none');
            footer2.classList.add('d-none');
            // Reset button state
            countdown = 5;
            ackBtn.disabled = true;
            ackBtn.textContent = `I Understand (${countdown})`;
            // Start countdown
            if (timer) clearInterval(timer);
            timer = setInterval(() => {
                countdown -= 1;
                if (countdown <= 0) {
                    clearInterval(timer);
                    ackBtn.disabled = false;
                    ackBtn.textContent = 'I Understand';
                } else {
                    ackBtn.textContent = `I Understand (${countdown})`;
                }
            }, 1000);
        }

        function goToStep2() {
            if (timer) clearInterval(timer);
            step1.classList.add('d-none');
            step2.classList.remove('d-none');
            footer1.classList.add('d-none');
            footer2.classList.remove('d-none');
        }

        modalEl.addEventListener('show.bs.modal', resetToStep1);
        modalEl.addEventListener('hidden.bs.modal', () => { if (timer) clearInterval(timer); });
        ackBtn.addEventListener('click', goToStep2);
    }

    // Initialize two-step flows
    setupTwoStepModal('runRolloverModal', 'rolloverStep1', 'rolloverStep2', 'rolloverFooterStep1', 'rolloverFooterStep2', 'rolloverAcknowledgeBtn');
    setupTwoStepModal('runCleanupModal', 'cleanupStep1', 'cleanupStep2', 'cleanupFooterStep1', 'cleanupFooterStep2', 'cleanupAcknowledgeBtn');

// Update the rollover button click handler
if (confirmRolloverBtn) {
    confirmRolloverBtn.addEventListener('click', () => {
        const pwd = document.getElementById('rolloverPassword').value.trim();
        if (!pwd) return alert('Password is required.');
        
        // Disable button during request
        confirmRolloverBtn.disabled = true;
        confirmRolloverBtn.textContent = 'Processing...';
        
        fetch('api/run_rollover.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `password=${encodeURIComponent(pwd)}`
        }).then(r => {
            if (!r.ok) {
                throw new Error(`HTTP ${r.status}: ${r.statusText}`);
            }
            return r.json();
        }).then(res => {
            confirmRolloverBtn.disabled = false;
            confirmRolloverBtn.textContent = 'Run Rollover';
            
            if (res.success) {
                alert(res.message || 'Rollover completed successfully!');
                bootstrap.Modal.getInstance(document.getElementById('runRolloverModal')).hide();
                location.reload();
            } else {
                alert(res.message || 'Rollover failed.');
            }
        }).catch(error => {
            confirmRolloverBtn.disabled = false;
            confirmRolloverBtn.textContent = 'Run Rollover';
            console.error('Rollover error:', error);
            alert('Network error: ' + error.message);
        });
    });
}

// Update the cleanup button click handler
if (confirmCleanupBtn) {
    confirmCleanupBtn.addEventListener('click', () => {
        const pwd = document.getElementById('cleanupPassword').value.trim();
        if (!pwd) return alert('Password is required.');
        
        // Disable button during request
        confirmCleanupBtn.disabled = true;
        confirmCleanupBtn.textContent = 'Processing...';
        
        fetch('api/run_cleanup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `password=${encodeURIComponent(pwd)}`
        }).then(r => {
            if (!r.ok) {
                throw new Error(`HTTP ${r.status}: ${r.statusText}`);
            }
            return r.json();
        }).then(res => {
            confirmCleanupBtn.disabled = false;
            confirmCleanupBtn.textContent = 'Run Cleanup';
            
            if (res.success) {
                alert(res.message || 'Cleanup completed successfully!');
                bootstrap.Modal.getInstance(document.getElementById('runCleanupModal')).hide();
                location.reload();
            } else {
                alert(res.message || 'Cleanup failed.');
            }
        }).catch(error => {
            confirmCleanupBtn.disabled = false;
            confirmCleanupBtn.textContent = 'Run Cleanup';
            console.error('Cleanup error:', error);
            alert('Network error: ' + error.message);
        });
    });
}
});

</script>

<script src="../assets/js/sortable.js"></script>
<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
</body>
</html>