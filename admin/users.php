<?php
session_start();

require_once '../assets/includes/role_functions.php';
if (!isset($_SESSION['user_data']) || !hasRole($_SESSION['user_data'], 'admin')) {
	header("Location: ../users/login.php?error=unauthorized_access");
	exit();
}

require_once '../config/database.php';

// Filters
$limit = isset($_GET['limit']) ? max(5, (int)$_GET['limit']) : 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$roleFilter = isset($_GET['role']) ? trim($_GET['role']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$offset = ($page - 1) * $limit;

// Sorting params (match research_list pattern)
$allowedSorts = ['name','email','gender'];
$sort = isset($_GET['sort']) && in_array(strtolower($_GET['sort']), $allowedSorts, true)
	? strtolower($_GET['sort']) : 'name';
$order = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'desc' : 
         (isset($_GET['dir']) && strtolower($_GET['dir']) === 'desc' ? 'desc' : 'asc');

// Detect if users.roles column exists
$hasRolesColumn = false;
if ($res = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'roles'")) {
	$row = $res->fetch_assoc();
	$hasRolesColumn = ((int)$row['c']) > 0;
	$res->close();
}

// Build users query (exclude students entirely)
// Normalize to common columns: id, full_name, email, gender, roles

$whereUsers = [];
$paramsUsers = [];
$typesUsers = '';

if (!empty($roleFilter)) {
	if ($hasRolesColumn) {
		$whereUsers[] = "(role = ? OR (roles IS NOT NULL AND roles <> '' AND FIND_IN_SET(?, REPLACE(roles, ' ', ''))))";
		$paramsUsers[] = $roleFilter;
		$paramsUsers[] = $roleFilter;
		$typesUsers .= 'ss';
	} else {
		$whereUsers[] = "role = ?";
		$paramsUsers[] = $roleFilter;
		$typesUsers .= 's';
	}
}

if (!empty($search)) {
	$whereUsers[] = "(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name,'')) LIKE ? OR email LIKE ?)";
	$like = "%$search%";
	$paramsUsers[] = $like; $paramsUsers[] = $like; $typesUsers .= 'ss';
}

$whereUsers[] = $hasRolesColumn
    ? "(LOWER(COALESCE(role,'')) <> 'student' AND (roles IS NULL OR roles = '' OR FIND_IN_SET('student', REPLACE(LOWER(roles), ' ', '')) = 0))"
    : "LOWER(COALESCE(role,'')) <> 'student'";

$whereUsersSql = count($whereUsers) ? ('WHERE ' . implode(' AND ', $whereUsers)) : '';

$usersSql = $hasRolesColumn
	? "SELECT id,
			CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name,'')) AS full_name,
			first_name, middle_name, last_name, email, gender,
			CASE WHEN (roles IS NULL OR roles = '') THEN role ELSE roles END AS roles,
			'users' AS source
	   FROM users $whereUsersSql"
	: "SELECT id,
			CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name,'')) AS full_name,
			first_name, middle_name, last_name, email, gender,
			role AS roles,
			'users' AS source
	   FROM users $whereUsersSql";

// No students are included in this view anymore

// Count totals for pagination (users only)
$countUsersSql = "SELECT COUNT(*) AS c FROM users $whereUsersSql";

// Prepare counts
$total = 0;
// Users count
if ($stmt = $conn->prepare($countUsersSql)) {
	if (!empty($typesUsers)) { $stmt->bind_param($typesUsers, ...$paramsUsers); }
	$stmt->execute();
	$res = $stmt->get_result();
	$row = $res->fetch_assoc();
	$total += (int)$row['c'];
	$stmt->close();
}

$total_pages = max(1, (int)ceil($total / $limit));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

// Fetch the page of unified results using UNION ALL then ORDER and LIMIT
// For performance, we fetch full sets then limit in PHP if DB union with bound params is complex. Given likely small data, acceptable.

$users = [];

// Fetch users
if ($stmt = $conn->prepare($usersSql)) {
	if (!empty($typesUsers)) { $stmt->bind_param($typesUsers, ...$paramsUsers); }
	$stmt->execute();
	$res = $stmt->get_result();
	while ($r = $res->fetch_assoc()) { $users[] = $r; }
	$stmt->close();
}

// Sort merged list according to sort/order
usort($users, function($a, $b) use ($sort, $order) {
	$mult = ($order === 'desc') ? -1 : 1;
	if ($sort === 'email') {
		return $mult * strcasecmp((string)$a['email'], (string)$b['email']);
	}
	if ($sort === 'gender') {
		return $mult * strcasecmp((string)$a['gender'], (string)$b['gender']);
	}
	// default: name
	return $mult * strcasecmp((string)$a['full_name'], (string)$b['full_name']);
});

// Paginate in PHP
$pagedUsers = array_slice($users, $offset, $limit);

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Users - Captrack Vault</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
	<link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
<?php include '../assets/includes/sidebar.php'; ?>
<div class="main-content">
	<?php include '../assets/includes/navbar.php'; ?>
	<h4 class="mb-3">Users</h4>

	<!-- Floating Success/Error/Info Messages -->
	<?php if (isset($_SESSION['success_message'])): ?>
		<div class="floating-alert floating-alert-success alert-dismissible fade show" role="alert">
			<div class="floating-alert-content">
				<?= $_SESSION['success_message']; ?>
			</div>
			<button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
		<?php unset($_SESSION['success_message']); ?>
	<?php endif; ?>

	<?php if (isset($_SESSION['error_message'])): ?>
		<div class="floating-alert floating-alert-error alert-dismissible fade show" role="alert">
			<div class="floating-alert-content">
				<?= $_SESSION['error_message']; ?>
			</div>
			<button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
		<?php unset($_SESSION['error_message']); ?>
	<?php endif; ?>

	<?php if (isset($_SESSION['info_message'])): ?>
		<div class="floating-alert floating-alert-info alert-dismissible fade show" role="alert">
			<div class="floating-alert-content">
				<?= $_SESSION['info_message']; ?>
			</div>
			<button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
		<?php unset($_SESSION['info_message']); ?>
	<?php endif; ?>

	<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
		<div class="d-flex align-items-center gap-2">
			<label class="me-1">Show</label>
			<select id="showEntries" class="form-select form-select-sm" style="width:auto;">
				<option value="10" <?= $limit==10?'selected':''; ?>>10</option>
				<option value="25" <?= $limit==25?'selected':''; ?>>25</option>
				<option value="50" <?= $limit==50?'selected':''; ?>>50</option>
				<option value="100" <?= $limit==100?'selected':''; ?>>100</option>
			</select>
			<div class="input-group" style="max-width:320px;">
				<span class="input-group-text bg-transparent border-0 px-2"><i class="bi bi-search text-muted"></i></span>
				<input type="search" id="searchInput" class="form-control bg-white" placeholder="Search name or email" value="<?= htmlspecialchars($search); ?>">
				<button class="btn btn-outline-secondary border-0" type="button" onclick="clearSearch()" title="Clear search">
					<i class="bi bi-x"></i>
				</button>
			</div>
			<select id="roleFilter" class="form-select form-select-sm" style="width:auto;">
				<option value="" <?= $roleFilter===''?'selected':''; ?>>All roles</option>
                <!-- Student option removed; students managed in a separate page -->
				<option value="faculty" <?= $roleFilter==='faculty'?'selected':''; ?>>Capstone Adviser</option>
				<option value="adviser" <?= $roleFilter==='adviser'?'selected':''; ?>>Capstone Professor</option>
				<option value="panelist" <?= $roleFilter==='panelist'?'selected':''; ?>>Panelist</option>
				<option value="grammarian" <?= $roleFilter==='grammarian'?'selected':''; ?>>Grammarian</option>
				<option value="dean" <?= $roleFilter==='dean'?'selected':''; ?>>Dean</option>
				<option value="admin" <?= $roleFilter==='admin'?'selected':''; ?>>Admin</option>
			</select>
		</div>
		<div>
			<a href="add_user.php" class="btn btn-primary btn-sm"><i class="bi bi-person-plus-fill"></i> Add User</a>
		</div>
	</div>

	<div class="table-responsive">
		<table class="table table-bordered table-hover align-middle bg-white">
			<thead class="table-light">
				<tr>
					<th class="sortable" data-sort="name" style="cursor: pointer;">Name
						<i class="bi bi-sort-alpha-down" id="sort-name"></i>
					</th>
					<th class="sortable" data-sort="email" style="cursor: pointer;">Email
						<i class="bi bi-sort-alpha-down" id="sort-email"></i>
					</th>
					<th class="text-center sortable" data-sort="gender" style="cursor: pointer;">Gender
						<i class="bi bi-sort-alpha-down" id="sort-gender"></i>
					</th>
					<th class="text-center">Roles</th>
					<th class="text-center">Action</th>
				</tr>
			</thead>
			<tbody id="usersTableBody">
				<?php if (count($pagedUsers) > 0): foreach ($pagedUsers as $u): ?>
				<tr>
					<td><?= htmlspecialchars($u['full_name']); ?></td>
					<td><?= htmlspecialchars($u['email']); ?></td>
					<td class="text-center"><?= htmlspecialchars($u['gender']); ?></td>
					<td class="text-center">
                    <?php 
                            $roles = array_filter(array_map('trim', explode(',', $u['roles'])));
                            if (empty($roles)) { $roles = []; }
                            foreach ($roles as $r) {
                                $label = $r;
                                if (strtolower($r) === 'faculty') { $label = 'Capstone Adviser'; }
                                if (strtolower($r) === 'adviser') { $label = 'Capstone Professor'; }
                                if (strtolower($r) === 'grammarian') { $label = 'Grammarian'; }
                                echo '<span class="badge bg-secondary me-1">' . htmlspecialchars($label) . '</span>';
                            }
                        ?>
					</td>
					<td class="text-center">
						<div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-secondary btn-sm" title="View" 
                            onclick="viewUser(event, <?= (int)$u['id']; ?>, 
                            'users', 
							'<?= isset($u['first_name']) ? htmlspecialchars(addslashes($u['first_name'])) : ''; ?>', 
							'<?= isset($u['middle_name']) ? htmlspecialchars(addslashes($u['middle_name'])) : ''; ?>', 
							'<?= isset($u['last_name']) ? htmlspecialchars(addslashes($u['last_name'])) : ''; ?>', 
							'<?= isset($u['email']) ? htmlspecialchars(addslashes($u['email'])) : ''; ?>', 
							'<?= isset($u['gender']) ? htmlspecialchars(addslashes($u['gender'])) : ''; ?>', 
							'<?= isset($u['roles']) ? htmlspecialchars(addslashes($u['roles'])) : ''; ?>');">
								<i class="bi bi-eye"></i>
							</button>
							<?php 
                            $isAdminRole = (stripos($u['roles'], 'admin') !== false);
							?>
							<button type="button" class="btn btn-danger btn-sm" title="Delete" 
								<?= $isAdminRole ? 'disabled' : '' ?> 
								data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" 
								data-id="<?= (int)$u['id']; ?>" 
                                data-source="users" 
								data-roles="<?= isset($u['roles']) ? htmlspecialchars($u['roles']) : ''; ?>" 
								data-name="<?= isset($u['full_name']) ? htmlspecialchars($u['full_name']) : ''; ?>">
								<i class="bi bi-trash"></i>
							</button>
						</div>
					</td>
				</tr>
				<?php endforeach; else: ?>
					<tr><td colspan="5" class="text-center">No users found.</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Pagination -->
	<nav class="d-flex justify-content-end">
		<ul class="pagination mb-0" id="pagination">
			<li class="page-item <?= $page<=1?'disabled':''; ?>"><a class="page-link" href="#" data-page="<?= max(1,$page-1); ?>">Previous</a></li>
			<?php for ($i=1; $i<=$total_pages; $i++): ?>
				<li class="page-item <?= $page==$i?'active':''; ?>"><a class="page-link" href="#" data-page="<?= $i; ?>"><?= $i; ?></a></li>
			<?php endfor; ?>
			<li class="page-item <?= $page>=$total_pages?'disabled':''; ?>"><a class="page-link" href="#" data-page="<?= min($total_pages,$page+1); ?>">Next</a></li>
		</ul>
	</nav>
</div>

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content rounded-4">
			<div class="modal-header bg-info text-white">
				<h5 class="modal-title" id="viewUserModalLabel"><i class="bi bi-person-lines-fill"></i> View User</h5>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<form id="viewUserForm" method="POST" action="update_user.php">
				<div class="modal-body px-4 py-3">
					<input type="hidden" name="id" id="userId">
					<input type="hidden" name="source" id="userSource">
					<div class="row g-3">
						<div class="col-md-4">
							<label class="form-label">First Name <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="firstNameView" name="first_name" readonly>
						</div>
						<div class="col-md-4">
							<label class="form-label">Middle Name</label>
							<input type="text" class="form-control" id="middleNameView" name="middle_name" readonly>
						</div>
						<div class="col-md-4">
							<label class="form-label">Last Name <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="lastNameView" name="last_name" readonly>
						</div>
						<div class="col-md-6">
							<label class="form-label">Email</label>
							<input type="email" class="form-control" id="emailView" name="email" readonly>
						</div>
						<div class="col-md-6">
							<label class="form-label">Gender <span class="text-danger">*</span></label>
							<select class="form-select" id="genderView" name="gender" disabled>
								<option value="Male">Male</option>
								<option value="Female">Female</option>
							</select>
						</div>
						<div class="col-md-6">
							<label class="form-label">Roles</label>
							<div id="rolesBadgeView" class="mb-2"></div>
							<div id="rolesEditor" class="d-none">
								<div class="d-flex flex-wrap gap-2">
									<button type="button" class="btn btn-sm" data-role="faculty"></button>
									<button type="button" class="btn btn-sm" data-role="adviser"></button>
									<button type="button" class="btn btn-sm" data-role="panelist"></button>
									<button type="button" class="btn btn-sm" data-role="grammarian"></button>
									<button type="button" class="btn btn-sm" data-role="dean"></button>
								</div>
							</div>
							<input type="hidden" id="rolesInputHidden" name="roles" value="">
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content shadow">
			<div class="modal-header bg-danger text-white">
				<h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Delete</h5>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				Are you sure you want to delete <strong id="deleteUserName"></strong>?
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<a href="#" class="btn btn-danger" id="confirmDeleteBtn">Yes, Delete</a>
			</div>
		</div>
	</div>
</div>

<script>
// Auto-hide floating alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const floatingAlerts = document.querySelectorAll('.floating-alert');
    
    floatingAlerts.forEach(function(alert) {
        // Auto-hide after 5 seconds
        setTimeout(function() {
            if (alert && alert.parentNode) {
                alert.style.animation = 'slideOutRight 0.4s ease-in forwards';
                setTimeout(function() {
                    if (alert && alert.parentNode) {
                        alert.remove();
                    }
                }, 400);
            }
        }, 5000);
        
        const closeBtn = alert.querySelector('.btn-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                alert.style.animation = 'slideOutRight 0.4s ease-in forwards';
                setTimeout(function() {
                    if (alert && alert.parentNode) {
                        alert.remove();
                    }
                }, 400);
            });
        }
    });
});

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Update table function using AJAX
function updateTable(page, limit, search = '', sort = '', order = '', role = '') {
    const searchParam = search || document.getElementById('searchInput')?.value || '';
    const sortParam = sort || currentSort;
    const orderParam = order || currentOrder;
    const roleParam = role || document.getElementById('roleFilter')?.value || '';
    
    // Update global state
    if (sort) currentSort = sort;
    if (order) currentOrder = order;
    if (page) currentPage = page;
    if (limit) currentLimit = limit;
    
    console.log('updateTable called:', { page, limit, search: searchParam, sort: sortParam, order: orderParam, role: roleParam });
    console.log('Global state after update:', { currentSort, currentOrder, currentPage, currentLimit });
    
    fetch(`search_users.php?page=${page}&limit=${limit}&search=${encodeURIComponent(searchParam)}&sort=${sortParam}&order=${orderParam}&role=${encodeURIComponent(roleParam)}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            const tbody = document.getElementById('usersTableBody');
            const pagination = document.getElementById('pagination');
            tbody.innerHTML = '';

            // Check for error response
            if (data.error) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">' + data.error + '</td></tr>';
                pagination.innerHTML = '';
                return;
            }

            if (data.users && data.users.length > 0) {
                data.users.forEach(user => {
                    const row = document.createElement('tr');
                    
                    // Build roles badges
                    let rolesHtml = '';
                    if (user.roles_array && user.roles_array.length > 0) {
                        user.roles_array.forEach(role => {
                            let label = role;
                            if (role.toLowerCase() === 'faculty') label = 'Capstone Adviser';
                            if (role.toLowerCase() === 'adviser') label = 'Capstone Professor';
                            if (role.toLowerCase() === 'grammarian') label = 'Grammarian';
                            rolesHtml += `<span class="badge bg-secondary me-1">${label}</span>`;
                        });
                    }
                    
                    // Check if user has admin role
                    const isAdminRole = user.roles_array && user.roles_array.some(r => r.toLowerCase() === 'admin');
                    
                    row.innerHTML = `
                        <td>${user.full_name}</td>
                        <td>${user.email}</td>
                        <td class="text-center">${user.gender}</td>
                        <td class="text-center">${rolesHtml}</td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-2">
                                <button type="button" class="btn btn-secondary btn-sm" title="View" 
                                onclick="viewUser(event, ${user.id}, 
                                'users', 
                                '${user.first_name.replace(/'/g, "\\'")}', 
                                '${user.middle_name.replace(/'/g, "\\'")}', 
                                '${user.last_name.replace(/'/g, "\\'")}', 
                                '${user.email.replace(/'/g, "\\'")}', 
                                '${user.gender.replace(/'/g, "\\'")}', 
                                '${user.roles.replace(/'/g, "\\'")}');">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button type="button" class="btn btn-danger btn-sm" title="Delete" 
                                    ${isAdminRole ? 'disabled' : ''} 
                                    data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" 
                                    data-id="${user.id}" 
                                    data-source="users" 
                                    data-roles="${user.roles.replace(/'/g, "\\'")}" 
                                    data-name="${user.full_name.replace(/'/g, "\\'")}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No users found.</td></tr>';
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
            updateSortIcons(currentSort, currentOrder);

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
            url.searchParams.set('role', roleParam);
            window.history.pushState({}, '', url);
        })
        .catch(error => {
            console.error('Error fetching users:', error);
            document.getElementById('usersTableBody').innerHTML = '<tr><td colspan="5" class="text-center">Error loading users.</td></tr>';
            document.getElementById('pagination').innerHTML = '';
        });
}

// Sort table function
function sortTable(column) {
    let newOrder = 'asc';
    if (currentSort === column && currentOrder === 'asc') {
        newOrder = 'desc';
    }
    
    console.log('Sorting:', { column, currentSort, currentOrder, newOrder });
    
    // Get current search and role values from inputs
    const currentSearch = document.getElementById('searchInput')?.value || '';
    const currentRole = document.getElementById('roleFilter')?.value || '';
    const currentLimit = document.getElementById('showEntries')?.value || 10;
    updateTable(1, currentLimit, currentSearch, column, newOrder, currentRole);
}

// Clear search function
function clearSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.value = '';
    }
    const currentRole = document.getElementById('roleFilter')?.value || '';
    const currentLimit = document.getElementById('showEntries')?.value || 10;
    updateTable(1, currentLimit, '', currentSort, currentOrder, currentRole);
}

// Update sort icons
function updateSortIcons(sort, order) {
    const sortIcons = {
        'name': document.getElementById('sort-name'),
        'email': document.getElementById('sort-email'),
        'gender': document.getElementById('sort-gender')
    };
    
    Object.keys(sortIcons).forEach(column => {
        const icon = sortIcons[column];
        if (icon) {
            if (column === sort) {
                icon.className = order === 'asc' ? 'bi bi-sort-alpha-up' : 'bi bi-sort-alpha-down';
            } else {
                icon.className = 'bi bi-sort-alpha-down';
            }
        }
    });
}

// Global helpers and user functions (keeping existing code)
let originalUserData = {};

// Global state for sorting and pagination
let currentSort = '<?php echo $sort; ?>';
let currentOrder = '<?php echo $order; ?>';
let currentPage = <?php echo $page; ?>;
let currentLimit = <?php echo $limit; ?>;

function getRoleLabel(role){
	switch((role||'').toLowerCase()){
		case 'faculty': return 'Capstone Adviser';
		case 'adviser': return 'Capstone Professor';
		case 'panelist': return 'Panelist';
		case 'grammarian': return 'Grammarian';
		case 'dean': return 'Dean';
		case 'admin': return 'Admin';
		default: return role ? (role.charAt(0).toUpperCase() + role.slice(1)) : '';
	}
}

function updateRolesHiddenInput(rolesSet){
	const input = document.getElementById('rolesInputHidden');
	if (!input) return;
	const arr = Array.from(rolesSet);
	input.value = arr.join(', ');
}

function renderRolesBadges(rolesSet){
	const container = document.getElementById('rolesBadgeView');
	if (!container) return;
	container.innerHTML = '';
	if (rolesSet.size === 0){
		const span = document.createElement('span');
		span.className = 'text-muted';
		span.textContent = 'No roles assigned';
		container.appendChild(span);
		return;
	}
	Array.from(rolesSet).forEach(r => {
		const badge = document.createElement('span');
		badge.className = 'badge bg-secondary me-1';
		badge.textContent = getRoleLabel(r);
		container.appendChild(badge);
	});
}

function setupRolesEditor(rolesSet){
	const editor = document.getElementById('rolesEditor');
	if (!editor) return;
	const buttons = editor.querySelectorAll('button[data-role]');
	buttons.forEach(btn => {
		const role = (btn.getAttribute('data-role')||'').toLowerCase();
		function refresh(){
			const active = rolesSet.has(role);
			btn.classList.toggle('btn-success', !active);
			btn.classList.toggle('btn-outline-danger', active);
			btn.innerHTML = (active ? '<i class="bi bi-dash"></i> ' : '<i class="bi bi-plus"></i> ') + getRoleLabel(role);
		}
		refresh();
		btn.onclick = function(){
			if (rolesSet.has(role)) {
				rolesSet.delete(role);
			} else {
				rolesSet.add(role);
			}
			updateRolesHiddenInput(rolesSet);
			renderRolesBadges(rolesSet);
			refresh();
		};
	});
}

function viewUser(ev, id, source, firstName, middleName, lastName, email, gender, roles) {
	if (ev && ev.preventDefault) ev.preventDefault();
	if (ev && ev.stopPropagation) ev.stopPropagation();
	
	// Reset form
	document.getElementById('viewUserForm').reset();

	// Store original
	originalUserData = { id, source, firstName, middleName, lastName, email, gender, roles };

	// Fill form fields
	document.getElementById('userId').value = id;
	document.getElementById('userSource').value = source;
	document.getElementById('firstNameView').value = firstName || '';
	document.getElementById('middleNameView').value = middleName || '';
	document.getElementById('lastNameView').value = lastName || '';
	document.getElementById('emailView').value = email || '';
	
	// Normalize gender casing to match option values
	const g = (gender || '').toString();
	const normalizedGender = g.charAt(0).toUpperCase() + g.slice(1).toLowerCase();
	document.getElementById('genderView').value = normalizedGender;

	// Roles UI setup
	const rolesString = (roles || '').toString();
	const rolesSet = new Set(
		rolesString
			.split(',')
			.map(r => r.trim().toLowerCase())
			.filter(r => r.length > 0)
	);

	updateRolesHiddenInput(rolesSet);
	renderRolesBadges(rolesSet);
	setupRolesEditor(rolesSet);

	// Set all fields to readonly/disabled by default
	document.getElementById('firstNameView').setAttribute('readonly', true);
	document.getElementById('middleNameView').setAttribute('readonly', true);
	document.getElementById('lastNameView').setAttribute('readonly', true);
	document.getElementById('emailView').setAttribute('readonly', true);
	document.getElementById('genderView').setAttribute('disabled', true);

	// Show badges, hide editor initially
	document.getElementById('rolesBadgeView').classList.remove('d-none');
	document.getElementById('rolesEditor').classList.add('d-none');

	// Reset buttons
	document.getElementById('editButton').classList.remove('d-none');
	document.getElementById('saveButton').classList.add('d-none');
	document.getElementById('cancelButton').classList.add('d-none');

	// Show modal
	const modalEl = document.getElementById('viewUserModal');
	if (modalEl) {
		new bootstrap.Modal(modalEl).show();
	}
	return false;
}

document.addEventListener('DOMContentLoaded', ()=>{
	// Search inputs with debouncing
	const searchInput = document.getElementById('searchInput');
	let currentSearchTerm = '<?php echo isset($_GET['search']) ? addslashes($_GET['search']) : ''; ?>';
	let currentRoleFilter = '<?php echo isset($_GET['role']) ? addslashes($_GET['role']) : ''; ?>';

	if (searchInput) {
		searchInput.addEventListener('input', debounce(() => {
			currentSearchTerm = searchInput.value.trim();
			const currentLimit = document.getElementById('showEntries')?.value || 10;
			const currentRole = document.getElementById('roleFilter')?.value || '';
			updateTable(1, currentLimit, currentSearchTerm, currentSort, currentOrder, currentRole);
		}, 300));
	}

	// Show entries
	const showEntriesSelect = document.getElementById('showEntries');
	if (showEntriesSelect) {
		showEntriesSelect.addEventListener('change', () => {
			const currentSearch = document.getElementById('searchInput')?.value || '';
			const currentRole = document.getElementById('roleFilter')?.value || '';
			updateTable(1, showEntriesSelect.value, currentSearch, currentSort, currentOrder, currentRole);
		});
	}

	// Role filter
	const roleFilter = document.getElementById('roleFilter');
	if (roleFilter) {
		roleFilter.addEventListener('change', () => {
			const currentSearch = document.getElementById('searchInput')?.value || '';
			const currentLimit = document.getElementById('showEntries')?.value || 10;
			updateTable(1, currentLimit, currentSearch, currentSort, currentOrder, roleFilter.value);
		});
	}

	// Pagination
	const pagination = document.getElementById('pagination');
	if (pagination) {
		pagination.addEventListener('click', e => {
			const target = e.target.closest('a.page-link');
			if (target) {
				e.preventDefault();
				const page = parseInt(target.getAttribute('data-page'));
				const limit = parseInt(target.getAttribute('data-limit'));
				if (!isNaN(page) && !isNaN(limit)) {
					const currentSearch = document.getElementById('searchInput')?.value || '';
					const currentRole = document.getElementById('roleFilter')?.value || '';
					updateTable(page, limit, currentSearch, currentSort, currentOrder, currentRole);
				}
			}
		});
	}
	
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
	updateSortIcons(currentSort, currentOrder);

	// Edit/View modal controls (keeping existing code)
	const editBtn = document.getElementById('editButton');
	const saveBtn = document.getElementById('saveButton');
	const cancelBtn = document.getElementById('cancelButton');

	if (editBtn) {
		editBtn.addEventListener('click', () => {
            const source = document.getElementById('userSource').value;
            const isStudent = false;
			
			// Enable editing for basic fields
			document.getElementById('firstNameView').removeAttribute('readonly');
			document.getElementById('middleNameView').removeAttribute('readonly');
			document.getElementById('lastNameView').removeAttribute('readonly');
			document.getElementById('emailView').removeAttribute('readonly');
			document.getElementById('genderView').removeAttribute('disabled');
			
			// Switch roles to editor mode
			document.getElementById('rolesBadgeView').classList.add('d-none');
			document.getElementById('rolesEditor').classList.remove('d-none');

			// Toggle buttons
			editBtn.classList.add('d-none');
			saveBtn.classList.remove('d-none');
			cancelBtn.classList.remove('d-none');
		});
	}

	if (cancelBtn) {
		cancelBtn.addEventListener('click', () => {
			// Restore original data
			document.getElementById('userId').value = originalUserData.id;
			document.getElementById('userSource').value = originalUserData.source;
			document.getElementById('firstNameView').value = originalUserData.firstName || '';
			document.getElementById('middleNameView').value = originalUserData.middleName || '';
			document.getElementById('lastNameView').value = originalUserData.lastName || '';
			document.getElementById('emailView').value = originalUserData.email || '';
			const rolesStringRestore = (originalUserData.roles || '').toString();
			const rolesSetRestore = new Set(
				rolesStringRestore
					.split(',')
					.map(r => r.trim().toLowerCase())
					.filter(r => r.length > 0)
			);
			updateRolesHiddenInput(rolesSetRestore);
			renderRolesBadges(rolesSetRestore);
			setupRolesEditor(rolesSetRestore);
			
			const g2 = (originalUserData.gender || '').toString();
			const normalized2 = g2.charAt(0).toUpperCase() + g2.slice(1).toLowerCase();
			document.getElementById('genderView').value = normalized2;

			// Disable editing
			document.getElementById('firstNameView').setAttribute('readonly', true);
			document.getElementById('middleNameView').setAttribute('readonly', true);
			document.getElementById('lastNameView').setAttribute('readonly', true);
			document.getElementById('emailView').setAttribute('readonly', true);
			document.getElementById('genderView').setAttribute('disabled', true);

			// Switch roles back to badges-only
			document.getElementById('rolesBadgeView').classList.remove('d-none');
			document.getElementById('rolesEditor').classList.add('d-none');

			// Toggle buttons
			saveBtn.classList.add('d-none');
			cancelBtn.classList.add('d-none');
			editBtn.classList.remove('d-none');
		});
	}

	// Delete modal setup
	const confirmDeleteModal = document.getElementById('confirmDeleteModal');
	if (confirmDeleteModal) {
		confirmDeleteModal.addEventListener('show.bs.modal', (event) => {
			const button = event.relatedTarget;
			const id = button.getAttribute('data-id');
			const source = button.getAttribute('data-source');
			const roles = (button.getAttribute('data-roles') || '').toLowerCase();
			const name = button.getAttribute('data-name');

			document.getElementById('deleteUserName').textContent = name;
			
			let href = '#';
            if (roles.includes('dean')) href = `delete_dean.php?id=${id}`;
            else if (roles.includes('panelist')) href = `delete_panelist.php?id=${id}`;
            else if (roles.includes('adviser')) href = `delete_adviser.php?id=${id}`;
            else if (roles.includes('grammarian')) href = `delete_grammarian.php?id=${id}`;
            else if (roles.includes('faculty')) href = `delete_faculty.php?id=${id}`;
            else href = `delete_faculty.php?id=${id}`;
			document.getElementById('confirmDeleteBtn').href = href;
		});
	}
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
</body>
</html>


