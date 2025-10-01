<?php
// Start the session
session_start();

require_once '../assets/includes/role_functions.php';

// Check if the user is logged in and has adviser role (supports multi-role)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_data']) || !hasRole($_SESSION['user_data'], 'adviser')) {
    header("Location: ../users/login.php");
    exit();
}

// Ensure active role reflects Adviser when visiting adviser pages
if (!isset($_SESSION['active_role']) || $_SESSION['active_role'] !== 'adviser') {
    $_SESSION['active_role'] = 'adviser';
    $_SESSION['role'] = 'adviser'; // maintain compatibility with code using $_SESSION['role']
}

// Database connection
include '../config/database.php';
require_once '../assets/includes/year_section_functions.php';

// Get the number of entries to show from the dropdown
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$offset = ($page - 1) * $limit;

// Build the SQL query with search functionality
$query = "SELECT * FROM students WHERE 1=1";
$params = [];
if (!empty($search)) {
    $query .= " AND (CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ? 
                OR year_section LIKE ? 
                OR group_code LIKE ?)";
    $search_param = "%" . $search . "%";
    $params = [$search_param, $search_param, $search_param];
}
$query .= " ORDER BY id DESC LIMIT ? OFFSET ?";
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

// Get total number of students for pagination
$total_query = "SELECT COUNT(id) as total_students FROM students WHERE 1=1";
if (!empty($search)) {
    $total_query .= " AND (CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ? 
                     OR year_section LIKE ? 
                     OR group_code LIKE ?)";
}
$total_stmt = mysqli_prepare($conn, $total_query);
if (!empty($search)) {
    mysqli_stmt_bind_param($total_stmt, "sss", $search_param, $search_param, $search_param);
}
mysqli_stmt_execute($total_stmt);
$total_result = mysqli_stmt_get_result($total_stmt);
$total_row = mysqli_fetch_assoc($total_result);
$total_students = $total_row['total_students'];

// Calculate total pages for pagination
$total_pages = ceil($total_students / $limit);

// Close statements and connection
mysqli_stmt_close($stmt);
mysqli_stmt_close($total_stmt);
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student List - Captrack Vault</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">

    <?php include '../assets/includes/navbar.php'; ?>

    <!-- Student List -->
    <h4 class="mb-3">Student List</h4>

    <!-- Improved Mobile Layout -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 gap-2">
        <!-- Show Entries - Left aligned on desktop, full width on mobile -->
        <div class="d-flex align-items-center">
            <span class="me-2">Show</span>
            <select id="showEntries" class="form-select" style="min-width: 80px; max-width: 100px;" onchange="updateTable(1, this.value)">
                <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
            </select>
            <span class="ms-2">entries</span>
        </div>

        <!-- Search Bar - Responsive width -->
        <div class="flex-grow-1 mx-md-3" style="max-width: 400px;">
            <form action="student_list.php" method="GET">
                <div class="input-group" style="border-radius: 10px; overflow: hidden; border: 1px solid #ddd; background:white;">
                    <span class="input-group-text bg-transparent border-0 px-3">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input 
                        type="search" 
                        id="searchInputDesktop"
                        name="search"
                        class="form-control bg-transparent border-0 shadow-none" 
                        placeholder="Search students or group code..." 
                        aria-label="Search"
                        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                    >
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle bg-white">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="sortable" data-sort="name">Name <i class="bi bi-sort-alpha-down"></i></th>
                    <th scope="col" class="text-center sortable" data-sort="year">Year <i class="bi bi-sort-numeric-down"></i></th>
                    <th scope="col" class="text-center sortable" data-sort="name">Group Code <i class="bi bi-sort-numeric-down"></i></th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody id="studentTableBody">
                <?php
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $full_name = "{$row['last_name']}, {$row['first_name']} {$row['middle_name']}";
                            $group_code = !empty($row['group_code']) ? $row['group_code'] : '';
                            echo "<tr>
                                    <td>$full_name</td>
                                    <td class='text-center'>{$row['year_section']}</td>
                                    <td class='text-center'>$group_code</td>
                                    <td class='text-center'>
                                        <button class='btn btn-sm btn-info' onclick='viewStudent(" .
                                        json_encode($row['id']) . "," .
                                        json_encode($row['first_name']) . "," .
                                        json_encode($row['middle_name']) . "," .
                                        json_encode($row['last_name']) . "," .
                                        json_encode($row['gender']) . "," .
                                        json_encode($row['year_section']) . "," .
                                        json_encode($group_code) . "," .
                                        json_encode($row['email']) .
                                        ")'>
                                            <i class='bi bi-eye'></i> View
                                        </button>
                                    </td>
                                </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' class='text-center'>No students found.</td></tr>";
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
                <label class="form-label">Email</label>
                <input type="email" class="form-control" id="emailView" name="email" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Group Code</label>
                <input
                    type="text"
                    class="form-control"
                    id="groupCodeView"
                    name="group_code"
                    pattern="^[3-4][ABCD]-G\d+$"
                    title="Format example: 3A-G1, 4B-G3"
                    readonly
                    required
                >
            </div>
          </div>
        </div>
        <div class="modal-footer px-4 py-3">
          <button type="button" id="editButton" class="btn btn-warning"></button>
          <button type="submit" id="saveButton" class="btn btn-success d-none">Update</button>
          <button type="button" id="cancelButton" class="btn btn-secondary d-none">Cancel</button>
          <button type="button" id="closeButton" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
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
        Group code updated successfully!
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var successModal = new bootstrap.Modal(document.getElementById('successModal'), { backdrop: 'static' });
    successModal.show();
    // Clear URL parameters when success modal is shown
    window.history.replaceState({}, document.title, window.location.pathname);
  });
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Real-time search functionality
    const searchInputs = [document.getElementById('searchInputMobile'), document.getElementById('searchInputDesktop')];
    let currentSearchTerm = '<?php echo isset($_GET['search']) ? addslashes($_GET['search']) : ''; ?>';

    // Debounce function to limit AJAX requests
    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Update table function
    function updateTable(page, limit, search = currentSearchTerm) {
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
                        const group_code = student.group_code || '';
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${student.full_name}</td>
                            <td class="text-center">${student.year_section}</td>
                            <td class="text-center">${group_code}</td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-info" onclick='viewStudent(${JSON.stringify(student.id)}, ${JSON.stringify(student.first_name)}, ${JSON.stringify(student.middle_name)}, ${JSON.stringify(student.last_name)}, ${JSON.stringify(student.gender)}, ${JSON.stringify(student.year_section)}, ${JSON.stringify(group_code)}, ${JSON.stringify(student.email)})'>
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center">No students found.</td></tr>';
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
                
                // Update URL
                const url = new URL(window.location);
                url.searchParams.set('page', data.current_page);
                url.searchParams.set('limit', data.limit);
                url.searchParams.set('search', search);
                window.history.pushState({}, '', url);
            })
            .catch(error => {
                console.error('Error fetching students:', error);
                document.getElementById('studentTableBody').innerHTML = '<tr><td colspan="4" class="text-center">Error loading students.</td></tr>';
                document.getElementById('pagination').innerHTML = '';
            });
    }

    // Event delegation for pagination
    document.getElementById('pagination').addEventListener('click', function(e) {
        const target = e.target.closest('a.page-link');
        if (target) {
            e.preventDefault();
            const page = parseInt(target.getAttribute('data-page'));
            const limit = parseInt(target.getAttribute('data-limit'));
            if (!isNaN(page) && !isNaN(limit)) {
                updateTable(page, limit);
            }
        }
    });

    // Search input handling
    searchInputs.forEach(input => {
        if (input) {
            input.addEventListener('input', debounce(function() {
                currentSearchTerm = this.value.trim();
                updateTable(1, document.getElementById('showEntries').value, currentSearchTerm);
            }, 300));
        }
    });

    // Show Entries dropdown
    document.getElementById('showEntries').addEventListener('change', function() {
        updateTable(1, this.value, currentSearchTerm);
    });
});

// Global variable to store original group code
let originalGroupCode = '';

function viewStudent(id, first, middle, last, gender, year_section, group_code, email) {
  // Normalize gender value to match select options
  const normalizedGender = gender.charAt(0).toUpperCase() + gender.slice(1).toLowerCase();

  // Store original group code
  originalGroupCode = group_code || '';

  // Fill inputs
  document.getElementById('studentId').value = id;
  document.getElementById('firstNameView').value = first;
  document.getElementById('middleNameView').value = middle;
  document.getElementById('lastNameView').value = last;
  document.getElementById('genderView').value = normalizedGender;
  document.getElementById('yearSectionView').value = year_section;
  document.getElementById('groupCodeView').value = group_code || '';
  document.getElementById('emailView').value = email || '';

  // Ensure group code is read-only initially
  document.getElementById('groupCodeView').setAttribute('readonly', true);

  // Set button text based on group_code
  const editButton = document.getElementById('editButton');
  editButton.textContent = group_code ? 'Edit Group Code' : 'Add Group Code';

  // Reset button visibility
  document.getElementById('editButton').classList.remove('d-none');
  document.getElementById('saveButton').classList.add('d-none');
  document.getElementById('cancelButton').classList.add('d-none');
  document.getElementById('closeButton').classList.remove('d-none');

  // Show modal
  var modal = new bootstrap.Modal(document.getElementById('viewStudentModal'));
  modal.show();
}

document.addEventListener('DOMContentLoaded', function () {
  const editBtn = document.getElementById('editButton');
  const saveBtn = document.getElementById('saveButton');
  const cancelBtn = document.getElementById('cancelButton');
  const closeBtn = document.getElementById('closeButton');
  const groupCodeInput = document.getElementById('groupCodeView');

  editBtn.addEventListener('click', () => {
    groupCodeInput.removeAttribute('readonly');
    editBtn.classList.add('d-none');
    saveBtn.classList.remove('d-none');
    cancelBtn.classList.remove('d-none');
    closeBtn.classList.add('d-none');
  });

  cancelBtn.addEventListener('click', () => {
    // Restore original group code
    groupCodeInput.value = originalGroupCode;
    groupCodeInput.setAttribute('readonly', true);
    saveBtn.classList.add('d-none');
    cancelBtn.classList.add('d-none');
    editBtn.classList.remove('d-none');
    closeBtn.classList.remove('d-none');
    // Reset button text in case group code was empty
    editBtn.textContent = originalGroupCode ? 'Edit Group Code' : 'Add Group Code';
  });
});
</script>
<script src="../assets/js/sortable.js"></script>
<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
</body>
</html>