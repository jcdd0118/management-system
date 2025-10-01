<?php
session_start();

require_once '../config/database.php';

// Configuration for the admin account to import
$adminEmail = 'admin@gmail.com';
$adminPlainPassword = 'qwe12345';
$adminRole = 'admin';

// Optional profile fields
$firstName = 'Admin';
$middleName = '';
$lastName = 'User';
$gender = 'male';
$rolesCsv = 'admin';
$status = 'verified';

header('Content-Type: text/plain');

// Check if users table exists (basic guard)
$checkTable = $conn->query("SHOW TABLES LIKE 'users'");
if (!$checkTable || $checkTable->num_rows === 0) {
	echo "Error: 'users' table not found. Import your database schema first.\n";
	exit;
}

// Check if admin already exists
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
if (!$stmt) {
	echo "Prepare failed: " . $conn->error . "\n";
	exit;
}
$stmt->bind_param('s', $adminEmail);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
	$stmt->close();
	echo "Admin account already exists for {$adminEmail}. No changes made.\n";
	$conn->close();
	exit;
}
$stmt->close();

// Hash the password
$hashedPassword = password_hash($adminPlainPassword, PASSWORD_BCRYPT);
if ($hashedPassword === false) {
	echo "Failed to hash password.\n";
	$conn->close();
	exit;
}

// Try inserting with extended column set first (including roles and status)
$insertSql = 'INSERT INTO users (first_name, middle_name, last_name, email, password, gender, role, roles, status) VALUES (?,?,?,?,?,?,?,?,?)';
$ins = $conn->prepare($insertSql);

if ($ins) {
	$ins->bind_param('sssssssss', $firstName, $middleName, $lastName, $adminEmail, $hashedPassword, $gender, $adminRole, $rolesCsv, $status);
	if ($ins->execute()) {
		echo "Success: Admin account created (users.id={$ins->insert_id}) for {$adminEmail}.\n";
		$ins->close();
		$conn->close();
		echo "Important: Delete this file (admin/import_admin.php) after running.\n";
		exit;
	}
	$ins->close();
}

// Fallback: insert with minimal columns if roles/status columns are not present in schema
$fallbackSql = 'INSERT INTO users (last_name, first_name, middle_name, email, password, gender, role) VALUES (?,?,?,?,?,?,?)';
$fallback = $conn->prepare($fallbackSql);
if ($fallback) {
	$fallback->bind_param('sssssss', $lastName, $firstName, $middleName, $adminEmail, $hashedPassword, $gender, $adminRole);
	if ($fallback->execute()) {
		echo "Success (fallback): Admin account created (users.id={$fallback->insert_id}) for {$adminEmail}.\n";
		$fallback->close();
		$conn->close();
		echo "Important: Delete this file (admin/import_admin.php) after running.\n";
		exit;
	} else {
		echo "Insert failed: " . $conn->error . "\n";
	}
	$fallback->close();
} else {
	echo "Prepare failed (fallback): " . $conn->error . "\n";
}

$conn->close();
?>


