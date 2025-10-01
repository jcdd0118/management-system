<?php
session_start();
include '../config/database.php';

// Check if user is admin (multi-role aware)
require_once '../assets/includes/role_functions.php';
if (!isset($_SESSION['user_data']) || !hasRole($_SESSION['user_data'], 'admin')) {
	header("Location: ../users/login.php?error=unauthorized_access");
	exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $year_section = trim($_POST['year_section']);
                $display_name = trim($_POST['display_name']);
                $year_level = (int)$_POST['year_level'];
                $section_letter = trim($_POST['section_letter']);
                
                // Validate inputs
                if (empty($year_section) || empty($display_name) || empty($year_level) || empty($section_letter)) {
                    $_SESSION['error_message'] = "All fields are required.";
                } else {
                    // Check if year_section already exists
                    $check_stmt = $conn->prepare("SELECT id FROM year_sections WHERE year_section = ?");
                    $check_stmt->bind_param("s", $year_section);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        $_SESSION['error_message'] = "Year section already exists.";
                    } else {
                        // Insert new year section
                        $insert_stmt = $conn->prepare("INSERT INTO year_sections (year_section, display_name, year_level, section_letter) VALUES (?, ?, ?, ?)");
                        $insert_stmt->bind_param("ssis", $year_section, $display_name, $year_level, $section_letter);
                        
                        if ($insert_stmt->execute()) {
                            $_SESSION['success_message'] = "Year section added successfully.";
                        } else {
                            $_SESSION['error_message'] = "Failed to add year section.";
                        }
                        $insert_stmt->close();
                    }
                    $check_stmt->close();
                }
                break;
                
            case 'edit':
                $id = (int)$_POST['id'];
                $year_section = trim($_POST['year_section']);
                $display_name = trim($_POST['display_name']);
                $year_level = (int)$_POST['year_level'];
                $section_letter = trim($_POST['section_letter']);
                
                // Validate inputs
                if (empty($year_section) || empty($display_name) || empty($year_level) || empty($section_letter)) {
                    $_SESSION['error_message'] = "All fields are required.";
                } else {
                    // Check if year_section already exists (excluding current record)
                    $check_stmt = $conn->prepare("SELECT id FROM year_sections WHERE year_section = ? AND id != ?");
                    $check_stmt->bind_param("si", $year_section, $id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        $_SESSION['error_message'] = "Year section already exists.";
                    } else {
                        // Update year section
                        $update_stmt = $conn->prepare("UPDATE year_sections SET year_section = ?, display_name = ?, year_level = ?, section_letter = ? WHERE id = ?");
                        $update_stmt->bind_param("ssisi", $year_section, $display_name, $year_level, $section_letter, $id);
                        
                        if ($update_stmt->execute()) {
                            $_SESSION['success_message'] = "Year section updated successfully.";
                        } else {
                            $_SESSION['error_message'] = "Failed to update year section.";
                        }
                        $update_stmt->close();
                    }
                    $check_stmt->close();
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                // Check if any students are using this year section
                $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE year_section = (SELECT year_section FROM year_sections WHERE id = ?)");
                $check_stmt->bind_param("i", $id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $student_count = $check_result->fetch_assoc()['count'];
                $check_stmt->close();
                
                if ($student_count > 0) {
                    $_SESSION['error_message'] = "Cannot delete year section. There are {$student_count} students assigned to this year section.";
                } else {
                    // Delete year section
                    $delete_stmt = $conn->prepare("DELETE FROM year_sections WHERE id = ?");
                    $delete_stmt->bind_param("i", $id);
                    
                    if ($delete_stmt->execute()) {
                        $_SESSION['success_message'] = "Year section deleted successfully.";
                    } else {
                        $_SESSION['error_message'] = "Failed to delete year section.";
                    }
                    $delete_stmt->close();
                }
                break;
                
            case 'toggle_status':
                $id = (int)$_POST['id'];
                $is_active = (int)$_POST['is_active'];
                
                $toggle_stmt = $conn->prepare("UPDATE year_sections SET is_active = ? WHERE id = ?");
                $toggle_stmt->bind_param("ii", $is_active, $id);
                
                if ($toggle_stmt->execute()) {
                    $_SESSION['success_message'] = "Year section status updated successfully.";
                } else {
                    $_SESSION['error_message'] = "Failed to update year section status.";
                }
                $toggle_stmt->close();
                break;
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: year_sections.php");
    exit();
}

// Fetch all year sections
$year_sections_result = $conn->query("SELECT * FROM year_sections ORDER BY year_level, section_letter");

// Fetch student counts for each year section
$student_counts = [];
$count_result = $conn->query("SELECT year_section, COUNT(*) as count FROM students GROUP BY year_section");
while ($row = $count_result->fetch_assoc()) {
    $student_counts[$row['year_section']] = $row['count'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Manage Sections - Captrack Vault</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
	<link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <?php include '../assets/includes/sidebar.php'; ?>
    
    <div class="main-content">
    <?php include '../assets/includes/navbar.php'; ?>
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="bi bi-calendar3 text-primary"></i> Manage Year Sections</h2>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addYearSectionModal">
                            <i class="bi bi-plus-circle"></i> Add Year Section
                        </button>
                    </div>

                    <!-- Success/Error Messages -->
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-circle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Year Sections Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-list-ul"></i> Year Sections</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Year Section</th>
                                            <th>Display Name</th>
                                            <th>Year Level</th>
                                            <th>Section Letter</th>
                                            <th>Students</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($section = $year_sections_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($section['year_section']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($section['display_name']); ?></td>
                                                <td><?php echo $section['year_level']; ?></td>
                                                <td><?php echo htmlspecialchars($section['section_letter']); ?></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo isset($student_counts[$section['year_section']]) ? $student_counts[$section['year_section']] : 0; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($section['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                onclick="editYearSection(<?php echo htmlspecialchars(json_encode($section)); ?>)">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-<?php echo $section['is_active'] ? 'warning' : 'success'; ?>" 
                                                                onclick="toggleStatus(<?php echo $section['id']; ?>, <?php echo $section['is_active'] ? 0 : 1; ?>)">
                                                            <i class="bi bi-<?php echo $section['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="deleteYearSection(<?php echo $section['id']; ?>, '<?php echo htmlspecialchars($section['year_section']); ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Year Section Modal -->
    <div class="modal fade" id="addYearSectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Year Section</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="year_section" class="form-label">Year Section Code</label>
                                <input type="text" class="form-control" id="year_section" name="year_section" 
                                       placeholder="e.g., 3A, 4B" pattern="^[3-4][A-Z]$" required>
                                <small class="text-muted">Format: Year + Section (e.g., 3A, 4B)</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="display_name" class="form-label">Display Name</label>
                                <input type="text" class="form-control" id="display_name" name="display_name" 
                                       placeholder="e.g., 3rd Year - Section A" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="year_level" class="form-label">Year Level</label>
                                <select class="form-select" id="year_level" name="year_level" required>
                                    <option value="">Choose...</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="section_letter" class="form-label">Section Letter</label>
                                <input type="text" class="form-control" id="section_letter" name="section_letter" 
                                       placeholder="e.g., A, B" pattern="^[A-Z]$" maxlength="1" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Year Section</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Year Section Modal -->
    <div class="modal fade" id="editYearSectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Year Section</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_year_section" class="form-label">Year Section Code</label>
                                <input type="text" class="form-control" id="edit_year_section" name="year_section" 
                                       pattern="^[3-4][A-Z]$" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_display_name" class="form-label">Display Name</label>
                                <input type="text" class="form-control" id="edit_display_name" name="display_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_year_level" class="form-label">Year Level</label>
                                <select class="form-select" id="edit_year_level" name="year_level" required>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_section_letter" class="form-label">Section Letter</label>
                                <input type="text" class="form-control" id="edit_section_letter" name="section_letter" 
                                       pattern="^[A-Z]$" maxlength="1" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Year Section</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger"></i> Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the year section <strong id="delete_year_section"></strong>?</p>
                        <p class="text-danger"><strong>This action cannot be undone.</strong></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toggle Status Form -->
    <form id="toggleStatusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="id" id="toggle_id">
        <input type="hidden" name="is_active" id="toggle_status">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editYearSection(section) {
            document.getElementById('edit_id').value = section.id;
            document.getElementById('edit_year_section').value = section.year_section;
            document.getElementById('edit_display_name').value = section.display_name;
            document.getElementById('edit_year_level').value = section.year_level;
            document.getElementById('edit_section_letter').value = section.section_letter;
            
            new bootstrap.Modal(document.getElementById('editYearSectionModal')).show();
        }

        function deleteYearSection(id, yearSection) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_year_section').textContent = yearSection;
            
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function toggleStatus(id, newStatus) {
            document.getElementById('toggle_id').value = id;
            document.getElementById('toggle_status').value = newStatus;
            document.getElementById('toggleStatusForm').submit();
        }

        // Auto-generate year section code when year level and section letter are entered
        document.getElementById('year_level').addEventListener('change', generateYearSection);
        document.getElementById('section_letter').addEventListener('input', generateYearSection);
        
        document.getElementById('edit_year_level').addEventListener('change', generateEditYearSection);
        document.getElementById('edit_section_letter').addEventListener('input', generateEditYearSection);

        function generateYearSection() {
            const yearLevel = document.getElementById('year_level').value;
            const sectionLetter = document.getElementById('section_letter').value.toUpperCase();
            
            if (yearLevel && sectionLetter) {
                document.getElementById('year_section').value = yearLevel + sectionLetter;
                document.getElementById('display_name').value = yearLevel + (yearLevel === '3' ? 'rd' : 'th') + ' Year - Section ' + sectionLetter;
            }
        }

        function generateEditYearSection() {
            const yearLevel = document.getElementById('edit_year_level').value;
            const sectionLetter = document.getElementById('edit_section_letter').value.toUpperCase();
            
            if (yearLevel && sectionLetter) {
                document.getElementById('edit_year_section').value = yearLevel + sectionLetter;
                document.getElementById('edit_display_name').value = yearLevel + (yearLevel === '3' ? 'rd' : 'th') + ' Year - Section ' + sectionLetter;
            }
        }
    </script>
</body>
</html>
