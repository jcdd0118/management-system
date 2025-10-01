<?php
// Start the session
session_start();

// Check if the user is logged in and is an admin (multi-role aware)
require_once '../assets/includes/role_functions.php';
if (!isset($_SESSION['user_data']) || !hasRole($_SESSION['user_data'], 'admin')) {
	header("Location: ../users/login.php?error=unauthorized_access");
	exit();
}

// Database connection
include '../config/database.php';

// Get pagination, search, and sorting parameters
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Validate sort column
$allowed_sorts = ['id', 'title', 'author', 'year', 'status'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'id';
}

// Validate order
if (!in_array(strtoupper($order), ['ASC', 'DESC'])) {
    $order = 'DESC';
}

$offset = ($page - 1) * $limit;

// Build the SQL query with search functionality
$query = "SELECT * FROM capstone WHERE 1=1";
$params = [];
if (!empty($search)) {
    $query .= " AND (title LIKE ? OR author LIKE ? OR keywords LIKE ?)";
    $search_param = "%" . $search . "%";
    $params = [$search_param, $search_param, $search_param];
}
$query .= " ORDER BY $sort $order LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

// Prepare and execute the query
$stmt = mysqli_prepare($conn, $query);
if (!empty($search)) {
    mysqli_stmt_bind_param($stmt, "sssii", ...$params);
} else {
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get total number of research entries for pagination
$total_query = "SELECT COUNT(id) as total_research FROM capstone WHERE 1=1";
if (!empty($search)) {
    $total_query .= " AND (title LIKE ? OR author LIKE ? OR keywords LIKE ?)";
}
$total_stmt = mysqli_prepare($conn, $total_query);
if (!empty($search)) {
    mysqli_stmt_bind_param($total_stmt, "sss", $search_param, $search_param, $search_param);
}
mysqli_stmt_execute($total_stmt);
$total_result = mysqli_stmt_get_result($total_stmt);
$total_row = mysqli_fetch_assoc($total_result);
$total_research = $total_row['total_research'];

// Calculate total pages for pagination
$total_pages = ceil($total_research / $limit);

// Close statements and connection
mysqli_stmt_close($stmt);
mysqli_stmt_close($total_stmt);
mysqli_close($conn);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Research List | Captrack Vault</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<style>
    @media (max-width: 768px) {
        table.table td:nth-child(2) {
            max-width: 100px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
    
    .author-group {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        background-color: #f8f9fa;
    }
    
    .author-header {
        display: flex;
        justify-content: between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .author-number {
        font-weight: 600;
        color: #495057;
    }
    
    .delete-author {
        background: none;
        border: none;
        color: #dc3545;
        font-size: 18px;
        cursor: pointer;
        padding: 0;
        margin-left: auto;
    }
    
    .delete-author:hover {
        color: #c82333;
    }
    
    .add-author-btn {
        background: #28a745;
        border: none;
        color: white;
        padding: 8px 15px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .add-author-btn:hover {
        background: #218838;
    }
</style>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">

    <?php include '../assets/includes/navbar.php'; ?>

    <!-- Research List -->
    <h4 class="mb-3">Research List</h4>

        <!-- Mobile View Controls -->
        <div class="d-block d-md-none mb-3">
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
                    <button class="btn btn-success btn-sm" onclick="exportResearch()">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#importResearchModal">
                        <i class="bi bi-upload"></i> Import
                    </button>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addResearchModal">
                        <i class="bi bi-person-plus-fill"></i> Add Research
                    </button>
                </div>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-12">
                    <div class="input-group" style="border-radius: 10px; overflow: hidden; border: 1px solid #ddd; background:white;">
                        <span class="input-group-text bg-transparent border-0 px-2">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input 
                            type="search" 
                            id="searchInputMobile"
                            name="search"
                            class="form-control bg-transparent border-0 shadow-none" 
                            placeholder="Search research..." 
                            aria-label="Search"
                            value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                        >
                    <button class="btn btn-outline-secondary border-0" type="button" onclick="clearSearch()" title="Clear search">
                        <i class="bi bi-x"></i>
                    </button>
                    </div>
            </div>
        </div>
    </div>

    <!-- Desktop View Controls -->
    <div class="d-none d-md-flex justify-content-between align-items-center mb-3">
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
        
        <!-- Desktop Search Bar -->
        <div class="flex-grow-1 mx-4" style="max-width: 400px;">
                <div class="input-group" style="border-radius: 10px; overflow: hidden; border: 1px solid #ddd; background:white;">
                    <span class="input-group-text bg-transparent border-0 px-2">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input 
                        type="search" 
                        id="searchInputDesktop"
                        name="search"
                        class="form-control bg-transparent border-0 shadow-none" 
                        placeholder="Search research..." 
                        aria-label="Search"
                        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                    >
                <button class="btn btn-outline-secondary border-0" type="button" onclick="clearSearch()" title="Clear search">
                    <i class="bi bi-x"></i>
                </button>
                </div>
        </div>
        
        <div class="d-flex gap-2">
            <button class="btn btn-success btn-sm" onclick="exportResearch()">
                <i class="bi bi-download"></i> Export Excel
            </button>
            <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#importResearchModal">
                <i class="bi bi-upload"></i> Import Excel
            </button>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addResearchModal">
                <i class="bi bi-person-plus-fill"></i> Add Research
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle bg-white">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="sortable" data-sort="title" style="cursor: pointer;">
                        Title 
                        <i class="bi bi-sort-alpha-down" id="sort-title"></i>
                    </th>
                    <th scope="col" class="text-center sortable" data-sort="author" style="cursor: pointer;">
                        Author 
                        <i class="bi bi-sort-alpha-down" id="sort-author"></i>
                    </th>
                    <th scope="col" class="text-center sortable" data-sort="year" style="cursor: pointer;">
                        Year 
                        <i class="bi bi-sort-numeric-down" id="sort-year"></i>
                    </th>
                    <th scope="col" class="text-center sortable" data-sort="status" style="cursor: pointer;">
                        Status 
                        <i class="bi bi-sort-alpha-down" id="sort-status"></i>
                    </th>
                    <th scope="col" class="text-center">Action</th>
                </tr>
            </thead>
            <tbody id="researchTableBody">
                <?php
                if (mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $statusLower = strtolower(trim($row['status'] ?: 'nonverified'));
                        if ($statusLower === 'verified') {
                            $statusBadge = "<span class='badge bg-success'>Verified</span>";
                        } elseif ($statusLower === 'rejected') {
                            $statusBadge = "<span class='badge bg-danger'>Rejected</span>";
                        } else {
                            $statusBadge = "<span class='badge bg-warning text-dark'>Not Verified</span>";
                        }
                        // Extract display author from special format if present
                        $displayAuthor = $row['author'];
                        if (strpos($row['author'], 'STUDENT_DATA:') === 0) {
                            $parts = explode('|DISPLAY:', $row['author']);
                            if (count($parts) === 2) {
                                $displayAuthor = $parts[1];
                            }
                        }
                        
                        echo "<tr>
                                <td>" . htmlspecialchars($row['title']) . "</td>
                                <td>" . htmlspecialchars($displayAuthor) . "</td>
                                <td class='text-center'>" . htmlspecialchars($row['year']) . "</td>
                                <td class='text-center'>" . $statusBadge . "</td>
                                <td class='text-center'>
                                    <div class='dropdown'>
                                        <button class='btn btn-secondary btn-sm dropdown-toggle' type='button' data-bs-toggle='dropdown' aria-expanded='false'>
                                            Action
                                        </button>
                                        <ul class='dropdown-menu dropdown-menu-end'>
                                            <li>
                                                <button class='dropdown-item view-research-btn' 
                                                    data-id='" . htmlspecialchars($row['id'], ENT_QUOTES) . "'
                                                    data-title='" . htmlspecialchars($row['title'], ENT_QUOTES) . "'
                                                    data-author='" . htmlspecialchars($row['author'], ENT_QUOTES) . "'
                                                    data-year='" . htmlspecialchars($row['year'], ENT_QUOTES) . "'
                                                    data-abstract='" . htmlspecialchars($row['abstract'], ENT_QUOTES) . "'
                                                    data-keywords='" . htmlspecialchars($row['keywords'], ENT_QUOTES) . "'
                                                    data-document-path='" . htmlspecialchars($row['document_path'], ENT_QUOTES) . "'
                                                    data-user-id='" . htmlspecialchars($row['user_id'], ENT_QUOTES) . "'
                                                    data-status='" . htmlspecialchars($row['status'] ?: 'nonverified', ENT_QUOTES) . "'>
                                                    <i class='bi bi-eye'></i> View
                                                </button>
                                            </li>
                                            <li>
                                                <button class='dropdown-item' data-bs-toggle='modal' data-bs-target='#confirmDeleteModal' 
                                                    data-id='{$row['id']}' data-title='" . htmlspecialchars($row['title'], ENT_QUOTES) . "'>
                                                    <i class='bi bi-trash'></i> Delete
                                                </button>
                                            </li>";
                        if (strtolower(trim($row['status'])) !== 'verified') {
                            echo "<li>
                                    <button class='dropdown-item' data-bs-toggle='modal' data-bs-target='#verifyResearchModal' 
                                        data-id='" . $row['id'] . "' data-title='" . htmlspecialchars($row['title'], ENT_QUOTES) . "'>
                                        <i class='bi bi-check-circle'></i> Verify
                                    </button>
                                </li>";
                            // Show Reject only if not yet verified
                            echo "<li>
                                    <button class='dropdown-item' data-bs-toggle='modal' data-bs-target='#rejectResearchModal' 
                                        data-id='" . $row['id'] . "' data-title='" . htmlspecialchars($row['title'], ENT_QUOTES) . "'>
                                        <i class='bi bi-x-circle'></i> Reject
                                    </button>
                                </li>";
                        }
                        echo "</ul></div></td></tr>";
                    }
                } else {
                    echo "<tr><td colspan='5' class='text-center'>No research found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-end mt-3">
        <nav>
            <ul class="pagination mb-0" id="pagination">
                <?php
                    $prev_page = max($page - 1, 1);
                    $next_page = min($page + 1, $total_pages);
                    $search_url = !empty($search) ? '&search=' . urlencode($search) : '';
                    $sort_url = '&sort=' . urlencode($sort) . '&order=' . urlencode($order);
                ?>
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="javascript:void(0)" data-page="<?php echo $prev_page; ?>" data-limit="<?php echo $limit; ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                        <a class="page-link" href="javascript:void(0)" data-page="<?php echo $i; ?>" data-limit="<?php echo $limit; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="javascript:void(0)" data-page="<?php echo $next_page; ?>" data-limit="<?php echo $limit; ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
</div>

<!-- Add Success Modal -->
<div class="modal fade" id="addSuccessModal" tabindex="-1" aria-labelledby="addSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addSuccessModalLabel">
                    <i class="bi bi-check-circle-fill"></i> Add Successful
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3 text-center">
                <div class="mb-3">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                </div>
                <h6 class="mb-3">Research Added Successfully!</h6>
                <div class="alert alert-success d-flex align-items-center" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <div>
                        <span id="addedResearchMessage"><?php echo isset($_SESSION['success_message']) ? $_SESSION['success_message'] : 'The research has been added.'; ?></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer px-4 py-3 justify-content-center">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Research Modal -->
<div class="modal fade" id="addResearchModal" tabindex="-1" aria-labelledby="addResearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 shadow-sm">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addResearchModalLabel"><i class="bi bi-plus-circle-fill"></i> Add Research</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="add_research.php" method="POST" enctype="multipart/form-data" id="addResearchForm">
                <div class="modal-body px-4 py-3">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" id="title" required>
                        </div>
                        
                        <!-- Authors Section -->
                        <div class="col-md-12">
                            <label class="form-label">Authors</label>
                            <div id="authorsContainer">
                                <!-- First author (template) -->
                                <div class="author-group" data-author-index="0">
                                    <div class="author-header">
                                        <span class="author-number">Author 1</span>
                                        <button type="button" class="delete-author d-none" onclick="removeAuthor(0)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-3">
                                            <label class="form-label">First Name *</label>
                                            <input type="text" name="authors[0][first_name]" class="form-control author-first-name" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Middle Name</label>
                                            <input type="text" name="authors[0][middle_name]" class="form-control">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Last Name *</label>
                                            <input type="text" name="authors[0][last_name]" class="form-control author-last-name" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Suffix</label>
                                            <select name="authors[0][suffix]" class="form-control">
                                                <option value="">None</option>
                                                <option value="Jr.">Jr.</option>
                                                <option value="Sr.">Sr.</option>
                                                <option value="II">II</option>
                                                <option value="III">III</option>
                                                <option value="IV">IV</option>
                                                <option value="V">V</option>
                                                <option value="PhD">PhD</option>
                                                <option value="MD">MD</option>
                                                <option value="DDS">DDS</option>
                                                <option value="EdD">EdD</option>
                                                <option value="JD">JD</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="add-author-btn mt-2" onclick="addAuthor()">
                                <i class="bi bi-plus"></i> Add Another Author
                            </button>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="year" class="form-label">Year</label>
                            <input type="number" name="year" class="form-control" id="year" required>
                        </div>
                        <div class="col-md-12">
                            <label for="abstract" class="form-label">Abstract</label>
                            <textarea name="abstract" class="form-control" id="abstract" rows="4" required></textarea>
                        </div>
                        <div class="col-md-12">
                            <label for="keywords" class="form-label">Keywords</label>
                            <input type="text" name="keywords" class="form-control" id="keywords" required>
                            <small class="text-muted">Separate keywords with commas (e.g., AI, Machine Learning, Data Science)</small>
                        </div>
                        <div class="col-md-12">
                            <label for="document" class="form-label">Document (PDF)</label>
                            <input type="file" name="document" class="form-control" id="document" accept=".pdf" required>
                        </div>
                        <div class="col-md-12">
                            <label for="user_id" class="form-label">User ID (Optional)</label>
                            <input type="number" name="user_id" class="form-control" id="user_id">
                        </div>
                        <div class="col-md-12">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" class="form-control" id="status" required>
                                <option value="nonverified">Not Verified</option>
                                <option value="verified">Verified</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <div id="modal-error-message" class="alert alert-danger d-none"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer px-4 py-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Research</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="confirmDeleteModalLabel">
                    <i class="bi bi-exclamation-triangle-fill"></i> Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <p class="mb-3">Are you sure you want to delete this research?</p>
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div>
                        <strong>Research:</strong> <span id="deleteResearchTitle"></span>
                        <br>
                        <small class="text-muted">This action cannot be undone.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer px-4 py-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete Research</a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Success Modal -->
<div class="modal fade" id="deleteSuccessModal" tabindex="-1" aria-labelledby="deleteSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="deleteSuccessModalLabel">
                    <i class="bi bi-check-circle-fill"></i> Delete Successful
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3 text-center">
                <div class="mb-3">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                </div>
                <h6 class="mb-3">Research Deleted Successfully!</h6>
                <div class="alert alert-success d-flex align-items-center" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <div>
                        <strong>Deleted Research:</strong> 
                        <span id="deletedResearchTitle"><?php echo isset($_SESSION['deleted_research_title']) ? $_SESSION['deleted_research_title'] : ''; ?></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer px-4 py-3 justify-content-center">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Update Success Modal -->
<div class="modal fade" id="updateSuccessModal" tabindex="-1" aria-labelledby="updateSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="updateSuccessModalLabel">
                    <i class="bi bi-check-circle-fill"></i> Update Successful
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3 text-center">
                <div class="mb-3">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                </div>
                <h6 class="mb-3">Research Updated Successfully!</h6>
                <div class="alert alert-success d-flex align-items-center" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <div>
                        <strong>Updated Research:</strong> 
                        <span id="updatedResearchTitle"><?php echo isset($_SESSION['success_message']) ? $_SESSION['success_message'] : 'Research has been updated successfully.'; ?></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer px-4 py-3 justify-content-center">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Verify Research Modal -->
<div class="modal fade" id="verifyResearchModal" tabindex="-1" aria-labelledby="verifyResearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="verifyResearchModalLabel">
                    <i class="bi bi-check-circle-fill"></i> Verify Research
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <input type="hidden" id="verifyResearchId">
                <div class="mb-3">
                    <label class="form-label">Research</label>
                    <div class="alert alert-light border" id="verifyResearchTitle"></div>
                </div>
                <div class="alert alert-success d-flex align-items-center" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <div>
                        This will mark the research as Verified and notify the student. The research will be available in the repository.
                    </div>
                </div>
            </div>
            <div class="modal-footer px-4 py-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmVerifyBtn">
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    <span class="btn-text">Verify Research</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Research Modal -->
<div class="modal fade" id="rejectResearchModal" tabindex="-1" aria-labelledby="rejectResearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="rejectResearchModalLabel">
                    <i class="bi bi-x-circle-fill"></i> Reject Research
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="reject_research.php" method="POST">
                <div class="modal-body px-4 py-3">
                    <input type="hidden" name="id" id="rejectResearchId">
                    <div class="mb-3">
                        <label class="form-label">Research</label>
                        <div class="alert alert-light border" id="rejectResearchTitle"></div>
                    </div>
                    <div class="mb-3">
                        <label for="rejectRemarks" class="form-label">Remarks/Comments to student</label>
                        <textarea class="form-control" id="rejectRemarks" name="remarks" rows="4" placeholder="Provide reason for rejection..." required></textarea>
                    </div>
                    <div class="alert alert-warning d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div>
                            This will mark the manuscript as Rejected and notify the student.
                        </div>
                    </div>
                </div>
                <div class="modal-footer px-4 py-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark">Reject</button>
                </div>
            </form>
        </div>
    </div>
    
</div>

<!-- Import Research Modal -->
<div class="modal fade" id="importResearchModal" tabindex="-1" aria-labelledby="importResearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 shadow-sm">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="importResearchModalLabel"><i class="bi bi-upload"></i> Import Research from Excel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="import_research.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body px-4 py-3">
                    <div class="mb-3">
                        <label for="excel_file" class="form-label">Select Excel File (.xls or .csv)</label>
                        <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xls,.csv" required>
                        <div class="form-text">
                            <strong>Expected format:</strong><br>
                            ID, Title, Author, Year, Abstract, Keywords, Document Path, User ID, Status<br>
                            <small class="text-muted">Note: ID column will be ignored, Document Path and User ID are optional</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="pdf_zip" class="form-label">Optional ZIP of PDFs</label>
                        <input type="file" class="form-control" id="pdf_zip" name="pdf_zip" accept=".zip">
                        <div class="form-text">
                            Upload a ZIP containing PDF files. The importer will link PDFs by:
                            <ul class="mb-0">
                                <li>Exact filename match to the CSV Document Path (filename only), or</li>
                                <li>Best-effort guess: <em>title_with_underscores.pdf</em> (e.g., "My Title" -> my_title.pdf)</li>
                            </ul>
                            Files will be extracted under <code>uploads/imported_research_YYYYMMDD_HHMMSS/</code>.
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Import Guidelines:</h6>
                        <ul class="mb-0">
                            <li>First row should contain headers</li>
                            <li>Year must be a valid year (1900 to current year + 5)</li>
                            <li>User ID must exist in students table if provided</li>
                            <li>Status must be 'verified' or 'nonverified' (defaults to 'nonverified')</li>
                            <li>All fields except Document Path and User ID are required</li>
                            <li>When providing a ZIP, set Document Path to the PDF filename (e.g., paper1.pdf) or leave blank to use title-based matching.</li>
                        </ul>
                        <hr>
                        <a href="download_template.php?type=research" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-download"></i> Download Template
                        </a>
                    </div>
                </div>
                <div class="modal-footer px-4 py-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Import Research</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Research Modal -->
<div class="modal fade" id="viewResearchModal" tabindex="-1" aria-labelledby="viewResearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-file-text-fill"></i> View Research</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="viewResearchForm" method="POST" action="update_research.php">
                <div class="modal-body px-4 py-3">
                    <input type="hidden" name="id" id="researchId">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" id="titleView" name="title" readonly>
                        </div>
                        
                        <!-- Authors Section for View Modal - DYNAMIC -->
                        <div class="col-md-12">
                            <label class="form-label">Authors</label>
                            <div id="viewAuthorsContainer">
                                <!-- First author (template) -->
                                <div class="author-group" data-view-author-index="0">
                                    <div class="author-header">
                                        <span class="author-number">Author 1</span>
                                        <button type="button" class="delete-author d-none" onclick="removeAuthorView(0)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-3">
                                            <label class="form-label">First Name *</label>
                                            <input type="text" name="view_authors[0][first_name]" class="form-control author-first-name" required readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Middle Name</label>
                                            <input type="text" name="view_authors[0][middle_name]" class="form-control" readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Last Name *</label>
                                            <input type="text" name="view_authors[0][last_name]" class="form-control author-last-name" required readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Suffix</label>
                                            <select name="view_authors[0][suffix]" class="form-control" disabled>
                                                <option value="">None</option>
                                                <option value="Jr.">Jr.</option>
                                                <option value="Sr.">Sr.</option>
                                                <option value="II">II</option>
                                                <option value="III">III</option>
                                                <option value="IV">IV</option>
                                                <option value="V">V</option>
                                                <option value="PhD">PhD</option>
                                                <option value="MD">MD</option>
                                                <option value="DDS">DDS</option>
                                                <option value="EdD">EdD</option>
                                                <option value="JD">JD</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="add-author-btn mt-2" onclick="addAuthorView()">
                                <i class="bi bi-plus"></i> Add Another Author
                            </button>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Year</label>
                            <input type="number" class="form-control" id="yearView" name="year" readonly>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Abstract</label>
                            <textarea class="form-control" id="abstractView" name="abstract" rows="4" readonly></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Keywords</label>
                            <input type="text" class="form-control" id="keywordsView" name="keywords" readonly>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Document</label>
                            <a id="documentView" href="#" target="_blank" class="form-control text-primary" style="display: inline-block;"></a>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">User ID</label>
                            <input type="number" class="form-control" id="userIdView" name="user_id" readonly>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Status</label>
                            <select class="form-control" id="statusView" name="status" disabled>
                                <option value="verified">Verified</option>
                                <option value="nonverified">Not Verified</option>
                                <option value="rejected">Rejected</option>
                            </select>
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

<script>
// Export research function
function exportResearch() {
    window.location.href = 'export_research.php';
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Update table function using AJAX
function updateTable(page, limit, search = '', sort = '', order = '') {
    const searchParam = search || document.getElementById('searchInputMobile')?.value || document.getElementById('searchInputDesktop')?.value || '';
    const sortParam = sort || '<?php echo $sort; ?>';
    const orderParam = order || '<?php echo $order; ?>';
    
    fetch(`search_research.php?page=${page}&limit=${limit}&search=${encodeURIComponent(searchParam)}&sort=${sortParam}&order=${orderParam}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            const tbody = document.getElementById('researchTableBody');
            const pagination = document.getElementById('pagination');
            tbody.innerHTML = '';

            if (data.research.length > 0) {
                data.research.forEach(research => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${research.title}</td>
                        <td>${research.author}</td>
                        <td class="text-center">${research.year}</td>
                        <td class="text-center">
                            <span class="badge bg-${research.status_class} ${research.status_class === 'warning' ? 'text-dark' : ''}">
                                ${research.status_badge}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Action
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button class="dropdown-item view-research-btn" 
                                            data-id="${research.id}"
                                            data-title="${research.title.replace(/'/g, "\\'")}"
                                            data-author="${(research.author_raw || research.author).replace(/'/g, "\\'")}"
                                            data-year="${research.year}"
                                            data-abstract="${research.abstract.replace(/'/g, "\\'")}"
                                            data-keywords="${research.keywords.replace(/'/g, "\\'")}"
                                            data-document-path="${research.document_path.replace(/'/g, "\\'")}"
                                            data-user-id="${research.user_id}"
                                            data-status="${research.status}">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" 
                                            data-id="${research.id}" data-title="${research.title.replace(/'/g, "\\'")}">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </li>
                                    ${research.status !== 'verified' ? `
                                        <li>
                                            <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#verifyResearchModal" 
                                                data-id="${research.id}" data-title="${research.title.replace(/'/g, "\\'")}">
                                                <i class="bi bi-check-circle"></i> Verify
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#rejectResearchModal" 
                                                data-id="${research.id}" data-title="${research.title.replace(/'/g, "\\'")}">
                                                <i class="bi bi-x-circle"></i> Reject
                                            </button>
                                        </li>` : ''}
                                </ul>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No research found.</td></tr>';
            }

            // Update pagination
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

            // Update sort icons
            updateSortIcons(sortParam, orderParam);
            
            // Update JavaScript sort state variables
            currentSort = sortParam;
            currentOrder = orderParam;

            // Update URL without page reload
            const url = new URL(window.location);
            url.searchParams.set('page', data.current_page);
            url.searchParams.set('limit', data.limit);
            if (searchParam.trim()) {
                url.searchParams.set('search', searchParam);
            } else {
                url.searchParams.delete('search');
            }
            url.searchParams.set('sort', sortParam);
            url.searchParams.set('order', orderParam);
            window.history.pushState({}, '', url);
        })
        .catch(error => {
            console.error('Error fetching research:', error);
            document.getElementById('researchTableBody').innerHTML = '<tr><td colspan="5" class="text-center">Error loading research.</td></tr>';
            document.getElementById('pagination').innerHTML = '';
        });
}

// Sort table function
function sortTable(column) {
    let newOrder = 'ASC';
    if (currentSort === column && currentOrder === 'ASC') {
        newOrder = 'DESC';
    }
    
    // Update the current sort state
    currentSort = column;
    currentOrder = newOrder;
    
    // Get current search value from input
    const currentSearch = document.getElementById('searchInputMobile')?.value || document.getElementById('searchInputDesktop')?.value || '';
    updateTable(1, <?php echo $limit; ?>, currentSearch, column, newOrder);
}

// Clear search function
function clearSearch() {
    const searchInputs = document.querySelectorAll('#searchInputMobile, #searchInputDesktop');
    searchInputs.forEach(input => {
        input.value = '';
    });
    updateTable(1, <?php echo $limit; ?>, '', currentSort, currentOrder);
}

// Update sort icons
function updateSortIcons(sort, order) {
    const sortIcons = {
        'title': document.getElementById('sort-title'),
        'author': document.getElementById('sort-author'),
        'year': document.getElementById('sort-year'),
        'status': document.getElementById('sort-status')
    };
    
    Object.keys(sortIcons).forEach(column => {
        const icon = sortIcons[column];
        if (icon) {
            if (column === sort) {
                if (column === 'year') {
                    icon.className = order === 'ASC' ? 'bi bi-sort-numeric-up' : 'bi bi-sort-numeric-down';
                } else {
                    icon.className = order === 'ASC' ? 'bi bi-sort-alpha-up' : 'bi bi-sort-alpha-down';
                }
            } else {
                if (column === 'year') {
                    icon.className = 'bi bi-sort-numeric-down';
                } else {
                    icon.className = 'bi bi-sort-alpha-down';
                }
            }
        }
    });
}

// Show import results if available
<?php if (isset($_GET['import']) && $_GET['import'] == 'completed' && isset($_SESSION['import_results'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    const results = <?= json_encode($_SESSION['import_results']); ?>;
    
    let message = `Import completed!<br>`;
    message += `✅ Successfully imported: ${results.success_count} research entries<br>`;
    if (results.error_count > 0) {
        message += `❌ Errors: ${results.error_count} rows<br>`;
        if (results.errors.length > 0) {
            message += `<small>Errors:<br>${results.errors.slice(0, 5).join('<br>')}</small>`;
            if (results.errors.length > 5) {
                message += `<br><small>... and ${results.errors.length - 5} more errors</small>`;
            }
        }
    }
    
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-info position-fixed start-50 translate-middle-x mt-3 px-4 py-3 shadow';
    alertDiv.style.top = '0';
    alertDiv.style.zIndex = '1055';
    alertDiv.innerHTML = message;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(function() {
        alertDiv.remove();
    }, 8000);
});
<?php 
unset($_SESSION['import_results']);
endif; 
?>

// Show delete success modal if deletion was successful
<?php if (isset($_SESSION['delete_success']) && $_SESSION['delete_success']): ?>
document.addEventListener('DOMContentLoaded', function() {
    const deleteSuccessModal = new bootstrap.Modal(document.getElementById('deleteSuccessModal'));
    deleteSuccessModal.show();
});
<?php 
    // Clear the session variables after showing
    unset($_SESSION['delete_success']);
    unset($_SESSION['deleted_research_title']);
endif; 
?>

// Show update success modal if update was successful
<?php if (isset($_GET['update']) && $_GET['update'] == 'success'): ?>
document.addEventListener('DOMContentLoaded', function() {
    const updateSuccessModal = new bootstrap.Modal(document.getElementById('updateSuccessModal'));
    updateSuccessModal.show();
    
    // Clean the URL by removing the update parameter
    const url = new URL(window.location);
    url.searchParams.delete('update');
    window.history.replaceState({}, '', url);
});
<?php 
    // Clear the session message after showing
    unset($_SESSION['success_message']);
endif; 
?>

// Show add success modal if add was successful
<?php if (isset($_GET['add']) && $_GET['add'] == 'success'): ?>
document.addEventListener('DOMContentLoaded', function() {
    const addSuccessModal = new bootstrap.Modal(document.getElementById('addSuccessModal'));
    addSuccessModal.show();
    // Clean the URL by removing the add parameter
    const url = new URL(window.location);
    url.searchParams.delete('add');
    window.history.replaceState({}, '', url);
});
<?php 
    unset($_SESSION['success_message']);
endif; 
?>

// Show add form with errors if validation failed
<?php if (isset($_GET['show_modal']) && $_GET['show_modal'] == 'true'): ?>
document.addEventListener('DOMContentLoaded', function() {
    const addModalEl = document.getElementById('addResearchModal');
    const addModal = new bootstrap.Modal(addModalEl);
    const errorBox = document.getElementById('modal-error-message');
    const errMsg = '<?php echo isset($_SESSION['error_message']) ? addslashes($_SESSION['error_message']) : 'Please fix the errors below.'; ?>';
    if (errorBox) {
        errorBox.classList.remove('d-none');
        errorBox.textContent = errMsg;
    }

    // Repopulate fields
    const oldInput = <?php echo isset($_SESSION['old_input']) ? json_encode($_SESSION['old_input']) : 'null'; ?>;
    if (oldInput) {
        if (oldInput.title) document.getElementById('title').value = oldInput.title;
        if (oldInput.year) document.getElementById('year').value = oldInput.year;
        if (oldInput.abstract) document.getElementById('abstract').value = oldInput.abstract;
        if (oldInput.keywords) document.getElementById('keywords').value = oldInput.keywords;
        if (oldInput.user_id) document.getElementById('user_id').value = oldInput.user_id;
        if (oldInput.status) document.getElementById('status').value = oldInput.status;

        // Authors
        if (oldInput.authors) {
            // Reset to one author block first
            const container = document.getElementById('authorsContainer');
            container.innerHTML = '';
            authorCount = 0;
            oldInput.authors.forEach(function(a, idx) {
                addAuthor();
                const group = document.querySelector('[data-author-index="' + idx + '"]');
                if (group) {
                    if (a.first_name) group.querySelector('input[name*="[first_name]"]').value = a.first_name;
                    if (a.middle_name) group.querySelector('input[name*="[middle_name]"]').value = a.middle_name;
                    if (a.last_name) group.querySelector('input[name*="[last_name]"]').value = a.last_name;
                    if (a.suffix !== undefined) group.querySelector('select[name*="[suffix]"]').value = a.suffix;
                }
            });
            updateDeleteButtons();
        }
    }

    addModal.show();

    // Clean the URL by removing the show_modal parameter
    const url = new URL(window.location);
    url.searchParams.delete('show_modal');
    window.history.replaceState({}, '', url);
});
<?php 
    unset($_SESSION['error_message']);
    // keep old_input for another view if desired; optionally clear: unset($_SESSION['old_input']);
endif; 
?>

// Show error message if update failed
<?php if (isset($_GET['update']) && $_GET['update'] == 'error'): ?>
document.addEventListener('DOMContentLoaded', function() {
    const errorMessage = '<?php echo isset($_SESSION['error_message']) ? addslashes($_SESSION['error_message']) : 'Failed to update research.'; ?>';
    showErrorMessage(errorMessage);
    
    // Clean the URL by removing the update parameter
    const url = new URL(window.location);
    url.searchParams.delete('update');
    window.history.replaceState({}, '', url);
});
<?php 
    // Clear the session message after showing
    unset($_SESSION['error_message']);
endif; 
?>

// Your existing JavaScript code continues here...
let authorCount = 1;
let viewAuthorCount = 0;

// Track current sort state
let currentSort = '<?php echo $sort; ?>';
let currentOrder = '<?php echo $order; ?>';

// Add new author function for Add Modal
function addAuthor() {
    const container = document.getElementById('authorsContainer');
    const newAuthorHtml = `
        <div class="author-group" data-author-index="${authorCount}">
            <div class="author-header">
                <span class="author-number">Author ${authorCount + 1}</span>
                <button type="button" class="delete-author" onclick="removeAuthor(${authorCount})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label">First Name *</label>
                    <input type="text" name="authors[${authorCount}][first_name]" class="form-control author-first-name" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="authors[${authorCount}][middle_name]" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Last Name *</label>
                    <input type="text" name="authors[${authorCount}][last_name]" class="form-control author-last-name" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Suffix</label>
                    <select name="authors[${authorCount}][suffix]" class="form-control">
                        <option value="">None</option>
                        <option value="Jr.">Jr.</option>
                        <option value="Sr.">Sr.</option>
                        <option value="II">II</option>
                        <option value="III">III</option>
                        <option value="IV">IV</option>
                        <option value="V">V</option>
                        <option value="PhD">PhD</option>
                        <option value="MD">MD</option>
                        <option value="DDS">DDS</option>
                        <option value="EdD">EdD</option>
                        <option value="JD">JD</option>
                    </select>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', newAuthorHtml);
    authorCount++;
    updateDeleteButtons();
}

// Remove author function for Add Modal
function removeAuthor(index) {
    const authorGroup = document.querySelector(`[data-author-index="${index}"]`);
    if (authorGroup) {
        authorGroup.remove();
        updateDeleteButtons();
        renumberAuthors();
    }
}

// Update delete button visibility
function updateDeleteButtons() {
    const authorGroups = document.querySelectorAll('#authorsContainer .author-group');
    const deleteButtons = document.querySelectorAll('#authorsContainer .delete-author');
    
    deleteButtons.forEach((btn, index) => {
        if (authorGroups.length > 1) {
            btn.classList.remove('d-none');
        } else {
            btn.classList.add('d-none');
        }
    });
}

// Renumber authors after deletion
function renumberAuthors() {
    const authorGroups = document.querySelectorAll('#authorsContainer .author-group');
    authorGroups.forEach((group, index) => {
        const authorNumber = group.querySelector('.author-number');
        authorNumber.textContent = `Author ${index + 1}`;
        
        // Update input names
        const inputs = group.querySelectorAll('input, select');
        inputs.forEach(input => {
            const name = input.getAttribute('name');
            if (name) {
                const newName = name.replace(/\[\d+\]/, `[${index}]`);
                input.setAttribute('name', newName);
            }
        });
        
        // Update data attribute
        group.setAttribute('data-author-index', index);
        
        // Update delete button onclick
        const deleteBtn = group.querySelector('.delete-author');
        if (deleteBtn) {
            deleteBtn.setAttribute('onclick', `removeAuthor(${index})`);
        }
    });
    
    // Reset author count
    authorCount = authorGroups.length;
}

// Add new author function for View Modal
function addAuthorView() {
    const container = document.getElementById('viewAuthorsContainer');
    const newAuthorHtml = `
        <div class="author-group" data-view-author-index="${viewAuthorCount}">
            <div class="author-header">
                <span class="author-number">Author ${viewAuthorCount + 1}</span>
                <button type="button" class="delete-author" onclick="removeAuthorView(${viewAuthorCount})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label">First Name *</label>
                    <input type="text" name="view_authors[${viewAuthorCount}][first_name]" class="form-control author-first-name" required readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="view_authors[${viewAuthorCount}][middle_name]" class="form-control" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Last Name *</label>
                    <input type="text" name="view_authors[${viewAuthorCount}][last_name]" class="form-control author-last-name" required readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Suffix</label>
                    <select name="view_authors[${viewAuthorCount}][suffix]" class="form-control" disabled>
                        <option value="">None</option>
                        <option value="Jr.">Jr.</option>
                        <option value="Sr.">Sr.</option>
                        <option value="II">II</option>
                        <option value="III">III</option>
                        <option value="IV">IV</option>
                        <option value="V">V</option>
                        <option value="PhD">PhD</option>
                        <option value="MD">MD</option>
                        <option value="DDS">DDS</option>
                        <option value="EdD">EdD</option>
                        <option value="JD">JD</option>
                    </select>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', newAuthorHtml);
    viewAuthorCount++;
    updateViewDeleteButtons();
}

// Remove author function for View Modal
function removeAuthorView(index) {
    const authorGroup = document.querySelector(`[data-view-author-index="${index}"]`);
    if (authorGroup) {
        authorGroup.remove();
        updateViewDeleteButtons();
        renumberViewAuthors();
    }
}

// Update delete button visibility for View Modal
function updateViewDeleteButtons() {
    const authorGroups = document.querySelectorAll('#viewAuthorsContainer .author-group');
    const deleteButtons = document.querySelectorAll('#viewAuthorsContainer .delete-author');
    
    deleteButtons.forEach((btn, index) => {
        if (authorGroups.length > 1) {
            btn.classList.remove('d-none');
        } else {
            btn.classList.add('d-none');
        }
    });
}

// Renumber authors after deletion for View Modal
function renumberViewAuthors() {
    const authorGroups = document.querySelectorAll('#viewAuthorsContainer .author-group');
    authorGroups.forEach((group, index) => {
        const authorNumber = group.querySelector('.author-number');
        authorNumber.textContent = `Author ${index + 1}`;
        
        // Update input names
        const inputs = group.querySelectorAll('input, select');
        inputs.forEach(input => {
            const name = input.getAttribute('name');
            if (name) {
                const newName = name.replace(/\[\d+\]/, `[${index}]`);
                input.setAttribute('name', newName);
            }
        });
        
        // Update data attribute
        group.setAttribute('data-view-author-index', index);
        
        // Update delete button onclick
        const deleteBtn = group.querySelector('.delete-author');
        if (deleteBtn) {
            deleteBtn.setAttribute('onclick', `removeAuthorView(${index})`);
        }
    });
    
    // Reset view author count
    viewAuthorCount = authorGroups.length;
}

// Form submission handler to combine authors into single field - CORRECTED
document.getElementById('addResearchForm').addEventListener('submit', function(e) {
    const authorGroups = document.querySelectorAll('#authorsContainer .author-group');
    const authorsArray = [];
    
    authorGroups.forEach(group => {
        const firstName = group.querySelector('input[name*="[first_name]"]').value.trim();
        const middleName = group.querySelector('input[name*="[middle_name]"]').value.trim();
        const lastName = group.querySelector('input[name*="[last_name]"]').value.trim();
        const suffix = group.querySelector('select[name*="[suffix]"]').value;
        
        if (firstName || lastName) { // Allow submission even if only first name or last name
            let fullName = '';
            if (firstName) fullName += firstName;
            if (middleName) fullName += (fullName ? ` ${middleName}` : middleName);
            if (lastName) fullName += (fullName ? ` ${lastName}` : lastName);
            if (suffix) fullName += (fullName ? ` ${suffix}` : suffix);
            
            authorsArray.push(fullName.trim());
        }
    });

    // Create hidden input with combined authors
    const existingAuthorInput = document.querySelector('input[name="author"]');
    if (existingAuthorInput) {
        existingAuthorInput.remove();
    }
    
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'author';
    hiddenInput.value = authorsArray.join(', ');
    this.appendChild(hiddenInput);
});

// Form submission handler for view modal to combine authors
document.getElementById('viewResearchForm').addEventListener('submit', function(e) {
    const authorGroups = document.querySelectorAll('#viewAuthorsContainer .author-group');
    const authorsArray = [];
    const authorsJsonArray = [];
    
    authorGroups.forEach(group => {
        const firstName = group.querySelector('input[name*="[first_name]"]').value.trim();
        const middleName = group.querySelector('input[name*="[middle_name]"]').value.trim();
        const lastName = group.querySelector('input[name*="[last_name]"]').value.trim();
        const suffix = group.querySelector('select[name*="[suffix]"]').value;
        
        if (firstName || lastName) { // Allow submission even if only first name or last name
            let fullName = '';
            if (firstName) fullName += firstName;
            if (middleName) fullName += (fullName ? ` ${middleName}` : middleName);
            if (lastName) fullName += (fullName ? ` ${lastName}` : lastName);
            if (suffix) fullName += (fullName ? ` ${suffix}` : suffix);
            
            authorsArray.push(fullName.trim());
            
            // Also create JSON structure for student data format
            authorsJsonArray.push({
                firstName: firstName,
                middleName: middleName,
                lastName: lastName,
                suffix: suffix
            });
        }
    });

    // Create hidden input with combined authors
    const existingAuthorInput = document.querySelector('#viewResearchForm input[name="author"]');
    if (existingAuthorInput) {
        existingAuthorInput.remove();
    }
    
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'author';
    
    // Check if this was originally student data by looking at the original author data
    if (originalAuthorData && originalAuthorData.startsWith('STUDENT_DATA:')) {
        // Maintain student data format using compact format
        let compactData = "STUDENT_DATA:";
        authorsJsonArray.forEach(author => {
            compactData += author.firstName + "|" + (author.middleName || '') + "|" + author.lastName + "|" + (author.suffix || '') + "@@";
        });
        compactData = compactData.replace(/@@$/, '') + "|DISPLAY:" + authorsArray.join(', ');
        hiddenInput.value = compactData;
    } else {
        // Regular format for admin-entered data
        hiddenInput.value = authorsArray.join(', ');
    }
    
    this.appendChild(hiddenInput);
});

// Global variable to store original author data
let originalAuthorData = '';

// Function to populate view modal with author data - UPDATED FOR DYNAMIC AUTHORS
function viewResearch(id, title, author, year, abstract, keywords, document_path, user_id, status) {
    // Reset view modal
    document.getElementById('viewResearchForm').reset();
    
    // Store original author data for form submission
    originalAuthorData = author;
    
    // Clear existing authors
    const viewAuthorsContainer = document.getElementById('viewAuthorsContainer');
    viewAuthorsContainer.innerHTML = '';
    viewAuthorCount = 0;
    
    // Fill basic fields with null/undefined checks
    document.getElementById('researchId').value = id || '';
    document.getElementById('titleView').value = title || '';
    document.getElementById('yearView').value = year || '';
    document.getElementById('abstractView').value = abstract || '';
    document.getElementById('keywordsView').value = keywords || '';
    const documentLink = document.getElementById('documentView');
    function resolveDocUrl(p){
        if (!p) return '#';
        if (p.startsWith('http://') || p.startsWith('https://') || p.startsWith('/')) return p;
        // Ensure links work from admin/* by going up one level
        return '../' + p;
    }
    documentLink.href = resolveDocUrl(document_path);
    documentLink.textContent = document_path ? 'View Document' : 'No Document';
    document.getElementById('userIdView').value = user_id || '';
    document.getElementById('statusView').value = status || 'nonverified';
    
    // Parse and populate authors
    if (author && author.trim()) {
        // Check if this is student data with special format
        if (author.startsWith('STUDENT_DATA:')) {
            // Extract the compact data
            const parts = author.split('|DISPLAY:');
            if (parts.length === 2) {
                const compactData = parts[0].replace('STUDENT_DATA:', '');
                try {
                    // Parse compact format: firstName1|middleName1|lastName1|suffix1@@firstName2|middleName2|lastName2|suffix2@@
                    const authorStrings = compactData.split('@@').filter(s => s.trim());
                    authorStrings.forEach((authorString, index) => {
                        if (index === 0) {
                            // Use the first author group that's already there
                            addAuthorView();
                        } else {
                            addAuthorView();
                        }
                        
                        // Parse the author string: firstName|middleName|lastName|suffix
                        const authorParts = authorString.split('|');
                        const authorGroup = document.querySelector(`[data-view-author-index="${index}"]`);
                        if (authorGroup && authorParts.length >= 3) {
                            authorGroup.querySelector('input[name*="[first_name]"]').value = authorParts[0] || '';
                            authorGroup.querySelector('input[name*="[middle_name]"]').value = authorParts[1] || '';
                            authorGroup.querySelector('input[name*="[last_name]"]').value = authorParts[2] || '';
                            authorGroup.querySelector('select[name*="[suffix]"]').value = authorParts[3] || '';
                        }
                    });
                } catch (e) {
                    console.error('Error parsing student author data:', e);
                    // Fall back to regular parsing
                    parseAuthorsRegular(author);
                }
            } else {
                parseAuthorsRegular(author);
            }
        } else {
            // Regular parsing for admin-entered data
            parseAuthorsRegular(author);
        }
    } else {
        // If no authors, ensure we have at least one empty author group
        addAuthorView();
    }
    
    function parseAuthorsRegular(authorString) {
        const authors = authorString.split(',').map(a => a.trim()).filter(a => a);
        authors.forEach((authorName, index) => {
            if (index === 0) {
                // Use the first author group that's already there
                addAuthorView();
            } else {
                addAuthorView();
            }
            
            // Parse the author name into parts
            const nameParts = parseAuthorName(authorName);
            const authorGroup = document.querySelector(`[data-view-author-index="${index}"]`);
            if (authorGroup) {
                authorGroup.querySelector('input[name*="[first_name]"]').value = nameParts.firstName || '';
                authorGroup.querySelector('input[name*="[middle_name]"]').value = nameParts.middleName || '';
                authorGroup.querySelector('input[name*="[last_name]"]').value = nameParts.lastName || '';
                authorGroup.querySelector('select[name*="[suffix]"]').value = nameParts.suffix || '';
            }
        });
    }
    
    // Lock all inputs and selects
    document.querySelectorAll('#viewResearchForm input, #viewResearchForm textarea, #viewResearchForm select').forEach(el => {
        if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
            el.setAttribute('readonly', true);
        }
        if (el.tagName === 'SELECT') {
            el.setAttribute('disabled', true);
        }
    });
    
    // Hide add author button in view mode
    const addAuthorBtn = document.querySelector('#viewAuthorsContainer + .add-author-btn');
    if (addAuthorBtn) {
        addAuthorBtn.style.display = 'none';
    }
    
    // Hide delete buttons in view mode
    const deleteButtons = document.querySelectorAll('#viewAuthorsContainer .delete-author');
    deleteButtons.forEach(btn => {
        btn.classList.add('d-none');
    });
    
    // Reset buttons visibility
    document.getElementById('editButton').classList.remove('d-none');
    document.getElementById('saveButton').classList.add('d-none');
    document.getElementById('cancelButton').classList.add('d-none');
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('viewResearchModal'));
    modal.show();
}

// Helper function to parse author name into parts
function parseAuthorName(fullName) {
    const parts = fullName.trim().split(' ');
    const result = {
        firstName: '',
        middleName: '',
        lastName: '',
        suffix: ''
    };
    
    if (parts.length === 0) return result;
    
    // Common suffixes
    const suffixes = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V', 'PhD', 'MD', 'DDS', 'EdD', 'JD'];
    
    // Check if last part is a suffix
    if (suffixes.includes(parts[parts.length - 1])) {
        result.suffix = parts[parts.length - 1];
        parts.pop();
    }
    
    if (parts.length === 1) {
        result.firstName = parts[0];
    } else if (parts.length === 2) {
        result.firstName = parts[0];
        result.lastName = parts[1];
    } else if (parts.length === 3) {
        // For exactly 3 parts, we need to be smart about parsing
        // This could be either:
        // 1. "John Carl Dizon" where "John Carl" is first name (from student form)
        // 2. "John Carl Dizon" where "John" is first, "Carl" is middle, "Dizon" is last
        
        // Since we can't be 100% sure, we'll use a conservative approach:
        // Assume it's from student form (firstname + lastname) unless we have strong evidence otherwise
        // This is safer because student forms are more common in this system
        
        // Only treat as traditional format if the middle part is clearly a common middle name
        // AND the first part is a very common single first name
        const veryCommonSingleFirstNames = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Lisa', 'Robert', 'Mary', 'James', 'Jennifer'];
        const veryCommonMiddleNames = ['Marie', 'Ann', 'Lee', 'Lynn', 'Jean', 'Grace', 'Rose', 'Jane', 'Ruth', 'Helen'];
        
        if (veryCommonSingleFirstNames.includes(parts[0]) && veryCommonMiddleNames.includes(parts[1])) {
            // Likely traditional format: first + middle + last
            result.firstName = parts[0];
            result.middleName = parts[1];
            result.lastName = parts[2];
        } else {
            // Likely from student form: firstname + lastname
            result.firstName = parts[0] + ' ' + parts[1];
            result.lastName = parts[2];
        }
    } else if (parts.length >= 4) {
        // For 4+ parts, assume first part is first name, last part is last name, middle parts are middle name
        result.firstName = parts[0];
        result.lastName = parts[parts.length - 1];
        result.middleName = parts.slice(1, -1).join(' ');
    }
    
    return result;
}

// Edit/Cancel functionality for view modal - UPDATED
document.addEventListener('DOMContentLoaded', function() {
    const editBtn = document.getElementById('editButton');
    const saveBtn = document.getElementById('saveButton');
    const cancelBtn = document.getElementById('cancelButton');
    const form = document.getElementById('viewResearchForm');

    editBtn.addEventListener('click', () => {
        // Enable all inputs and selects
        form.querySelectorAll('input, textarea, select').forEach(el => {
            el.removeAttribute('readonly');
            el.removeAttribute('disabled');
        });
        
        // Show add author button when in edit mode
        const addAuthorBtn = document.querySelector('#viewAuthorsContainer + .add-author-btn');
        if (addAuthorBtn) {
            addAuthorBtn.style.display = 'block';
        }
        
        // Show delete buttons for authors when in edit mode
        const deleteButtons = document.querySelectorAll('#viewAuthorsContainer .delete-author');
        deleteButtons.forEach(btn => {
            if (document.querySelectorAll('#viewAuthorsContainer .author-group').length > 1) {
                btn.classList.remove('d-none');
            }
        });
        
        editBtn.classList.add('d-none');
        saveBtn.classList.remove('d-none');
        cancelBtn.classList.remove('d-none');
    });

    cancelBtn.addEventListener('click', () => {
        // Close and reopen modal to reset state
        const modal = bootstrap.Modal.getInstance(document.getElementById('viewResearchModal'));
        modal.hide();
    });
    
    // Handle delete confirmation modal
    const confirmDeleteModal = document.getElementById('confirmDeleteModal');
    
    if (confirmDeleteModal) {
        confirmDeleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const researchId = button.getAttribute('data-id');
            const researchTitle = button.getAttribute('data-title');
            
            document.getElementById('deleteResearchTitle').textContent = researchTitle;
            document.getElementById('confirmDeleteBtn').href = `delete_research.php?id=${researchId}`;
        });
    }

    // Handle verify modal
    const verifyModal = document.getElementById('verifyResearchModal');
    if (verifyModal) {
        verifyModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const researchId = button.getAttribute('data-id');
            const researchTitle = button.getAttribute('data-title');
            document.getElementById('verifyResearchId').value = researchId;
            document.getElementById('verifyResearchTitle').textContent = researchTitle;
        });
    }

    // Handle reject modal
    const rejectModal = document.getElementById('rejectResearchModal');
    if (rejectModal) {
        rejectModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const researchId = button.getAttribute('data-id');
            const researchTitle = button.getAttribute('data-title');
            document.getElementById('rejectResearchId').value = researchId;
            document.getElementById('rejectResearchTitle').textContent = researchTitle;
            document.getElementById('rejectRemarks').value = '';
        });
    }
});

// Initialize delete buttons on page load
document.addEventListener('DOMContentLoaded', function() {
    updateDeleteButtons();
    
    // Add event listeners for view research buttons
    document.addEventListener('click', function(e) {
        if (e.target.closest('.view-research-btn')) {
            e.preventDefault();
            const button = e.target.closest('.view-research-btn');
            const id = button.getAttribute('data-id');
            const title = button.getAttribute('data-title');
            const author = button.getAttribute('data-author');
            const year = button.getAttribute('data-year');
            const abstract = button.getAttribute('data-abstract');
            const keywords = button.getAttribute('data-keywords');
            const documentPath = button.getAttribute('data-document-path');
            const userId = button.getAttribute('data-user-id');
            const status = button.getAttribute('data-status');
            
            viewResearch(id, title, author, year, abstract, keywords, documentPath, userId, status);
        }
    });
    
    // Search inputs with debouncing
    const searchInputs = [document.getElementById('searchInputMobile'), document.getElementById('searchInputDesktop')];
    let currentSearchTerm = '<?php echo isset($_GET['search']) ? addslashes($_GET['search']) : ''; ?>';

    searchInputs.forEach(input => {
        if (input) {
            input.addEventListener('input', debounce(() => {
                currentSearchTerm = input.value.trim();
                updateTable(1, document.getElementById('showEntries')?.value || document.getElementById('showEntriesMobile')?.value || 10, currentSearchTerm, currentSort, currentOrder);
            }, 300));
        }
    });

    // Show entries
    const showEntriesSelect = document.getElementById('showEntries');
    const showEntriesMobileSelect = document.getElementById('showEntriesMobile');
    
    if (showEntriesSelect) {
        showEntriesSelect.addEventListener('change', () => updateTable(1, showEntriesSelect.value, currentSearchTerm, currentSort, currentOrder));
    }
    if (showEntriesMobileSelect) {
        showEntriesMobileSelect.addEventListener('change', () => updateTable(1, showEntriesMobileSelect.value, currentSearchTerm, currentSort, currentOrder));
    }

    // Pagination
    document.getElementById('pagination').addEventListener('click', e => {
        const target = e.target.closest('a.page-link');
        if (target) {
            e.preventDefault();
            const page = parseInt(target.getAttribute('data-page'));
            const limit = parseInt(target.getAttribute('data-limit'));
            if (!isNaN(page) && !isNaN(limit)) {
                updateTable(page, limit, currentSearchTerm, currentSort, currentOrder);
            }
        }
    });
    
    // Add sorting event listeners
    const sortableHeaders = document.querySelectorAll('.sortable');
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function(e) {
            e.preventDefault();
            const column = this.getAttribute('data-sort');
            sortTable(column);
        });
    });
    
    // Initialize sort icons on page load
    updateSortIcons('<?php echo $sort; ?>', '<?php echo $order; ?>');
});

// AJAX verification function
document.getElementById('confirmVerifyBtn').addEventListener('click', function() {
    const researchId = document.getElementById('verifyResearchId').value;
    const button = this;
    const spinner = button.querySelector('.spinner-border');
    const btnText = button.querySelector('.btn-text');
    
    // Show loading state
    spinner.classList.remove('d-none');
    btnText.textContent = 'Verifying...';
    button.disabled = true;
    
    // Make AJAX request
    fetch('api/verify_research.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${researchId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            showSuccessMessage(data.message);
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('verifyResearchModal'));
            modal.hide();
            
            // Refresh the table to show updated status
            const currentSearch = document.getElementById('searchInputMobile')?.value || document.getElementById('searchInputDesktop')?.value || '';
            updateTable(1, document.getElementById('showEntries')?.value || document.getElementById('showEntriesMobile')?.value || 10, currentSearch);
        } else {
            // Show error message
            showErrorMessage(data.message || 'Failed to verify research');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('An error occurred while verifying the research');
    })
    .finally(() => {
        // Reset button state
        spinner.classList.add('d-none');
        btnText.textContent = 'Verify Research';
        button.disabled = false;
    });
});

// Helper function to show success messages
function showSuccessMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success position-fixed start-50 translate-middle-x mt-3 px-4 py-3 shadow';
    alertDiv.style.top = '0';
    alertDiv.style.zIndex = '1055';
    alertDiv.innerHTML = `<i class="bi bi-check-circle-fill me-2"></i>${message}`;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(function() {
        alertDiv.remove();
    }, 5000);
}

// Helper function to show error messages
function showErrorMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger position-fixed start-50 translate-middle-x mt-3 px-4 py-3 shadow';
    alertDiv.style.top = '0';
    alertDiv.style.zIndex = '1055';
    alertDiv.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-2"></i>${message}`;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(function() {
        alertDiv.remove();
    }, 5000);
}

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
</body>
</html>