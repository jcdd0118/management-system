<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
	header("Location: ../users/login.php");
	exit();
}

require_once '../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$first = trim(isset($_POST['first_name']) ? $_POST['first_name'] : '');
	$middle = trim(isset($_POST['middle_name']) ? $_POST['middle_name'] : '');
	$last = trim(isset($_POST['last_name']) ? $_POST['last_name'] : '');
	$email = trim(isset($_POST['email']) ? $_POST['email'] : '');
	$password = isset($_POST['password']) ? $_POST['password'] : '';
	$gender = isset($_POST['gender']) ? $_POST['gender'] : '';
	$primaryRole = strtolower(trim(isset($_POST['primary_role']) ? $_POST['primary_role'] : ''));
	$rolesInput = trim(isset($_POST['roles']) ? $_POST['roles'] : ''); // comma-separated

	// Normalize roles list
	$roles = array_filter(array_map(function($r){ return strtolower(trim($r)); }, explode(',', $rolesInput)));
	// Ensure primary role is included in roles
	if ($primaryRole !== '' && !in_array($primaryRole, $roles)) { $roles[] = $primaryRole; }
	// Students should not be added here (handled in student_list)
	if ($primaryRole === 'student' || in_array('student', $roles, true)) {
		$error = 'Use Add Student for student accounts.';
	} elseif ($first === '' || $last === '' || $email === '' || $password === '' || $gender === '' || $primaryRole === '') {
		$error = 'All fields are required.';
	} else {
		// Check if email exists in users or students
		$exists = false;
		$check1 = $conn->prepare("SELECT id FROM users WHERE email = ?");
		$check1->bind_param('s', $email);
		$check1->execute();
		$check1->store_result();
		if ($check1->num_rows > 0) { $exists = true; }
		$check1->close();
		if (!$exists) {
			$check2 = $conn->prepare("SELECT id FROM students WHERE email = ?");
			$check2->bind_param('s', $email);
			$check2->execute();
			$check2->store_result();
			if ($check2->num_rows > 0) { $exists = true; }
			$check2->close();
		}

		if ($exists) {
			$error = 'Email already exists.';
		} else {
			$hashed = password_hash($password, PASSWORD_BCRYPT);
			$rolesCsv = implode(', ', array_unique($roles));
			$ins = $conn->prepare("INSERT INTO users (first_name, middle_name, last_name, email, password, gender, role, roles, status) VALUES (?,?,?,?,?,?,?,?,?)");
			$status = 'verified';
			$ins->bind_param('sssssssss', $first, $middle, $last, $email, $hashed, $gender, $primaryRole, $rolesCsv, $status);
			if ($ins->execute()) {
				$success = 'User added successfully and automatically verified.';
			} else {
				$error = 'Failed to add user.';
			}
			$ins->close();
		}
	}
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Add User - Captrack Vault</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
	<link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
<?php include '../assets/includes/sidebar.php'; ?>
<div class="main-content">
	<?php include '../assets/includes/navbar.php'; ?>
	<h4 class="mb-3">Add User</h4>

	<?php if ($error): ?>
		<div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
	<?php endif; ?>
	<?php if ($success): ?>
		<div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
	<?php endif; ?>

	<div class="card">
		<div class="card-body">
			<form method="POST" action="">
				<div class="row g-3">
					<div class="col-md-4">
						<label class="form-label">First Name</label>
						<input type="text" name="first_name" class="form-control" required>
					</div>
					<div class="col-md-4">
						<label class="form-label">Middle Name</label>
						<input type="text" name="middle_name" class="form-control">
					</div>
					<div class="col-md-4">
						<label class="form-label">Last Name</label>
						<input type="text" name="last_name" class="form-control" required>
					</div>
					<div class="col-md-6">
						<label class="form-label">Email</label>
						<input type="email" name="email" class="form-control" required>
					</div>
					<div class="col-md-6">
						<label class="form-label">Password</label>
						<input type="password" name="password" class="form-control" required>
					</div>
					<div class="col-md-6">
						<label class="form-label">Gender</label>
						<select name="gender" class="form-select" required>
							<option value="" disabled selected>Choose...</option>
							<option value="Male">Male</option>
							<option value="Female">Female</option>
						</select>
					</div>
					<div class="col-md-6">
						<label class="form-label">Primary Role</label>
						<select name="primary_role" class="form-select" required>
							<option value="" disabled selected>Choose...</option>
							<option value="faculty">Capstone Adviser</option>
							<option value="adviser">Capstone Professor</option>
							<option value="panelist">Panelist</option>
							<option value="grammarian">Grammarian</option>
							<option value="dean">Dean</option>
							<option value="admin">Admin</option>
						</select>
					</div>
					<div class="col-12">
						<label class="form-label">Roles (comma-separated, e.g., adviser, dean)</label>
						<input type="text" name="roles" class="form-control" placeholder="adviser, dean">
						<small class="text-muted">Multiple roles are allowed (except student). Use role keys (adviser=Capstone Professor, faculty=Capstone Adviser, grammarian=Grammarian). Primary role is from the dropdown.</small>
					</div>
				</div>
				<div class="mt-3">
					<button type="submit" class="btn btn-primary">Add User</button>
					<a href="users.php" class="btn btn-secondary">Back to Users</a>
				</div>
			</form>
		</div>
	</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
</body>
</html>


