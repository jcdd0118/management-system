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

// Fetch all faculty (users with role 'faculty')
$query = "SELECT * FROM users WHERE role = 'faculty' ORDER BY id DESC";
$result = mysqli_query($conn, $query);

// Close connection
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Faculty List | Captrack Vault</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <style>
        .dropdown-menu {
            min-width: auto;
        }
        .dropdown-item {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">

    <?php include '../assets/includes/navbar.php'; ?>

    <!-- Faculty List -->
    <h4 class="mb-3">Faculty List</h4>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex flex-column flex-md-row justify-content-end">
            <button class="btn btn-primary btn-sm d-block d-md-inline w-100 w-md-auto mb-2 mb-md-0" data-bs-toggle="modal" data-bs-target="#addFacultyModal">
                <i class="bi bi-person-plus-fill"></i> Add Faculty
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle bg-white">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="sortable" data-sort="name">Name <i class="bi bi-sort-alpha-down"></i></th>
                    <th>Email</th>
                    <th class="text-center">Gender</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $full_name = "{$row['last_name']}, {$row['first_name']} {$row['middle_name']}";

                            // Start of the row
                            echo "<tr>
                                    <td>{$full_name}</td>
                                    <td>{$row['email']}</td>
                                    <td class='text-center'>{$row['gender']}</td>";

                            // Action dropdown
                            echo "<td class='text-center'>
                                    <div class='dropdown'>
                                        <button class='btn btn-secondary btn-sm dropdown-toggle' type='button' data-bs-toggle='dropdown' aria-expanded='false'>
                                            Action
                                        </button>
                                        <ul class='dropdown-menu dropdown-menu-end'>
                                            <li>
                                                <button class='dropdown-item' onclick='viewFaculty(\"" .
                                                    addslashes($row['id']) . "\", \"" .
                                                    addslashes($row['first_name']) . "\", \"" .
                                                    addslashes($row['middle_name']) . "\", \"" .
                                                    addslashes($row['last_name']) . "\", \"" .
                                                    addslashes($row['email']) . "\", \"" .
                                                    addslashes($row['gender']) .
                                                "\")'>
                                                    <i class='bi bi-eye'></i> View
                                                </button>
                                            </li>
                                            <li>
                                                <button class='dropdown-item' data-bs-toggle='modal' data-bs-target='#confirmDeleteModal' 
                                                    data-id='{$row['id']}' data-name='" . htmlspecialchars($full_name, ENT_QUOTES) . "'>
                                                    <i class='bi bi-trash'></i> Delete
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                  </td></tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' class='text-center'>No faculty found.</td></tr>";
                    }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Faculty Modal -->
<div class="modal fade" id="addFacultyModal" tabindex="-1" aria-labelledby="addFacultyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 shadow-sm">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="addFacultyModalLabel"><i class="bi bi-person-plus-fill"></i> Add Faculty</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="add_faculty.php" method="POST">
        <div class="modal-body px-4 py-3">
          <div class="row g-3">
            <div class="col-md-4">
              <label for="firstName" class="form-label">First Name</label>
              <input type="text" name="first_name" class="form-control" id="firstName"
                value="<?= isset($_SESSION['old_input']['first_name']) ? htmlspecialchars($_SESSION['old_input']['first_name']) : '' ?>" required>
            </div>
            <div class="col-md-4">
              <label for="middleName" class="form-label">Middle Name</label>
              <input type="text" name="middle_name" class="form-control" id="middleName"
                value="<?= isset($_SESSION['old_input']['middle_name']) ? htmlspecialchars($_SESSION['old_input']['middle_name']) : '' ?>">
            </div>
            <div class="col-md-4">
              <label for="lastName" class="form-label">Last Name</label>
              <input type="text" name="last_name" class="form-control" id="lastName"
                value="<?= isset($_SESSION['old_input']['last_name']) ? htmlspecialchars($_SESSION['old_input']['last_name']) : '' ?>" required>
            </div>
            <div class="col-md-6">
              <label for="email" class="form-label">Email</label>
              <input type="email" name="email" class="form-control" id="email"
                value="<?= isset($_SESSION['old_input']['email']) ? htmlspecialchars($_SESSION['old_input']['email']) : '' ?>" required>
            </div>
            <div class="col-md-6">
              <label for="password" class="form-label">Password</label>
              <input type="password" class="form-control" name="password" id="password" required>
            </div>
            <div class="col-md-6">
              <label class="form-label d-block">Gender</label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="gender" id="genderMale" value="Male"
                  <?= isset($_SESSION['old_input']['gender']) && $_SESSION['old_input']['gender'] == 'Male' ? 'checked' : '' ?> required>
                <label class="form-check-label" for="genderMale">Male</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="gender" id="genderFemale" value="Female"
                  <?= isset($_SESSION['old_input']['gender']) && $_SESSION['old_input']['gender'] == 'Female' ? 'checked' : '' ?>>
                <label class="form-check-label" for="genderFemale">Female</label>
              </div>
            </div>
            <div class="col-md-6">
              <div id="modal-error-message" class="alert alert-danger d-none"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer px-4 py-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Faculty</button>
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
        Are you sure you want to delete <strong id="facultyName"></strong>?<br>This action will also remove the user account.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" class="btn btn-danger" id="confirmDeleteBtn">Yes, Delete</a>
      </div>
    </div>
  </div>
</div>

<!-- View Faculty Modal -->
<div class="modal fade" id="viewFacultyModal" tabindex="-1" aria-labelledby="viewFacultyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title"><i class="bi bi-person-lines-fill"></i> View Faculty</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="viewFacultyForm" method="POST" action="update_faculty.php">
        <div class="modal-body px-4 py-3">
          <input type="hidden" name="id" id="facultyId">
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

<?php
$showModal = isset($_GET['show_modal']);
$errorMessage = isset($_SESSION['error_message']) ? json_encode($_SESSION['error_message']) : 'null';
unset($_SESSION['error_message']);
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var errorMsg = <?= $errorMessage ?>;
    var errorDiv = document.getElementById('modal-error-message');

    if (errorMsg && errorDiv) {
        errorDiv.textContent = errorMsg;
        errorDiv.classList.remove('d-none');
        errorDiv.style.opacity = 1;

        // Show the modal
        var addFacultyModal = new bootstrap.Modal(document.getElementById('addFacultyModal'));
        addFacultyModal.show();

        // Fade out the error after 3 seconds
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

    // Reopen modal if explicitly requested
    <?php if ($showModal): ?>
    var addFacultyModal = new bootstrap.Modal(document.getElementById('addFacultyModal'));
    addFacultyModal.show();
    <?php endif; ?>
});
</script>

<?php if (isset($_SESSION['success_message'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var successMessage = <?= json_encode($_SESSION['success_message']); ?>;
    
    // Create and show success alert
    var alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success position-fixed start-50 translate-middle-x mt-3 px-4 py-3 shadow';
    alertDiv.style.top = '0';
    alertDiv.style.zIndex = '1055';
    alertDiv.innerHTML = successMessage;

    document.body.appendChild(alertDiv);

    // Auto remove after 3 seconds
    setTimeout(function () {
        alertDiv.remove();
    }, 3000);
});
</script>
<?php unset($_SESSION['success_message']); endif; ?>

<?php if (isset($_GET['update']) && $_GET['update'] == 'success'): ?>
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="successModalLabel">Success</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Faculty information saved successfully!
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Hide other modals
    document.querySelectorAll('.modal').forEach(modal => {
      var bsModal = bootstrap.Modal.getInstance(modal);
      if (bsModal) bsModal.hide();
    });
    // Show success modal
    var successModalElement = document.getElementById('successModal');
    if (successModalElement) {
      var successModal = new bootstrap.Modal(successModalElement, { backdrop: 'static' });
      successModal.show();
    } else {
      console.error('Success modal element not found');
    }
  });
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const confirmDeleteModal = document.getElementById('confirmDeleteModal');
  const facultyNameEl = document.getElementById('facultyName');
  const confirmBtn = document.getElementById('confirmDeleteBtn');

  confirmDeleteModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const facultyId = button.getAttribute('data-id');
    const facultyName = button.getAttribute('data-name');

    facultyNameEl.textContent = facultyName;
    confirmBtn.href = `delete_faculty.php?id=${facultyId}`;
  });
});
</script>

<script>
// Global variable to store original form data
let originalFormData = {};

function viewFaculty(id, first, middle, last, email, gender) {
  // Reset form to clear old data and reset button states
  document.getElementById('viewFacultyForm').reset();

  // Normalize gender value to match select options
  const normalizedGender = gender.charAt(0).toUpperCase() + gender.slice(1).toLowerCase();

  // Store original data
  originalFormData = {
    id: id,
    first_name: first,
    middle_name: middle,
    last_name: last,
    email: email,
    gender: normalizedGender
  };

  // Fill inputs
  document.getElementById('facultyId').value = id;
  document.getElementById('firstNameView').value = first;
  document.getElementById('middleNameView').value = middle;
  document.getElementById('lastNameView').value = last;
  document.getElementById('emailView').value = email;
  document.getElementById('genderView').value = normalizedGender;

  // Lock all inputs except email
  document.querySelectorAll('#viewFacultyForm input:not([name="email"]):not([type="hidden"])').forEach(el => {
    el.setAttribute('readonly', true);
  });

  // Disable selects
  document.querySelectorAll('#viewFacultyForm select').forEach(el => {
    el.setAttribute('disabled', true);
  });

  // Reset buttons visibility
  document.getElementById('editButton').classList.remove('d-none');
  document.getElementById('saveButton').classList.add('d-none');
  document.getElementById('cancelButton').classList.add('d-none');

  // Show modal
  var modal = new bootstrap.Modal(document.getElementById('viewFacultyModal'));
  modal.show();
}

document.addEventListener('DOMContentLoaded', function () {
  const editBtn = document.getElementById('editButton');
  const saveBtn = document.getElementById('saveButton');
  const cancelBtn = document.getElementById('cancelButton');
  const form = document.getElementById('viewFacultyForm');
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
    // Restore original data
    document.getElementById('facultyId').value = originalFormData.id;
    document.getElementById('firstNameView').value = originalFormData.first_name;
    document.getElementById('middleNameView').value = originalFormData.middle_name;
    document.getElementById('lastNameView').value = originalFormData.last_name;
    document.getElementById('emailView').value = originalFormData.email;
    document.getElementById('genderView').value = originalFormData.gender;

    // Lock inputs and selects again
    inputs.forEach(input => input.setAttribute('readonly', true));
    selects.forEach(select => select.setAttribute('disabled', true));

    // Reset button visibility
    saveBtn.classList.add('d-none');
    cancelBtn.classList.add('d-none');
    editBtn.classList.remove('d-none');
  });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('table');
    const headers = table.querySelectorAll('.sortable');
    let sortDirection = {};

    headers.forEach(header => {
        header.addEventListener('click', () => {
            const sortKey = header.getAttribute('data-sort');
            const isAscending = !sortDirection[sortKey] || sortDirection[sortKey] === 'desc';
            sortDirection[sortKey] = isAscending ? 'asc' : 'desc';

            // Update sort icons
            headers.forEach(h => {
                const icon = h.querySelector('i');
                if (h === header) {
                    icon.className = `bi bi-sort-${sortKey === 'year' ? 'numeric' : 'alpha'}-${isAscending ? 'down' : 'up'}`;
                } else {
                    icon.className = `bi bi-sort-${h.getAttribute('data-sort') === 'year' ? 'numeric' : 'alpha'}-down`;
                }
            });

            // Sort table rows
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const index = Array.from(header.parentElement.children).indexOf(header);

            rows.sort((a, b) => {
                let aValue = a.children[index].textContent.trim();
                let bValue = b.children[index].textContent.trim();

                // Handle numeric sorting for Year column
                if (sortKey === 'year') {
                    aValue = parseInt(aValue) || 0;
                    bValue = parseInt(bValue) || 0;
                    return isAscending ? aValue - bValue : bValue - aValue;
                }

                // Text sorting for other columns
                return isAscending
                    ? aValue.localeCompare(bValue)
                    : bValue.localeCompare(aValue);
            });

            // Rebuild table body
            tbody.innerHTML = '';
            rows.forEach(row => tbody.appendChild(row));
        });
    });
});
</script>
<script src="../assets/js/sortable.js"></script>
<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
</body>
</html>