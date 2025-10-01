<?php
// setup_groups_migration.php
// One-time migration to introduce groups table and link students/panel_assignments by group_id

require_once __DIR__ . '/../config/database.php';

function columnExists($conn, $table, $column) {
	$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
	$stmt->bind_param('ss', $table, $column);
	$stmt->execute();
	$res = $stmt->get_result();
	$row = $res->fetch_assoc();
	$stmt->close();
	return ((int)$row['c']) > 0;
}

function tableExists($conn, $table) {
	$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
	$stmt->bind_param('s', $table);
	$stmt->execute();
	$res = $stmt->get_result();
	$row = $res->fetch_assoc();
	$stmt->close();
	return ((int)$row['c']) > 0;
}

echo "Starting groups migration...\n";

// 1) Create groups table if not exists
$createGroupsSql = "CREATE TABLE IF NOT EXISTS groups (
	id INT AUTO_INCREMENT PRIMARY KEY,
	group_name VARCHAR(50) NOT NULL,
	year_level TINYINT NOT NULL,
	section_letter CHAR(1) NOT NULL,
	year_section VARCHAR(10) NOT NULL,
	cohort_year INT NULL,
	is_active TINYINT(1) NOT NULL DEFAULT 1,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY ux_group_unique (group_name, cohort_year, year_section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($createGroupsSql)) {
	echo "Failed to create groups table: " . $conn->error . "\n";
	exit(1);
}

// 2) Add group_id to students
if (!columnExists($conn, 'students', 'group_id')) {
	if (!$conn->query("ALTER TABLE students ADD COLUMN group_id INT NULL AFTER group_code, ADD INDEX idx_students_group_id (group_id)")) {
		echo "Failed to add students.group_id: " . $conn->error . "\n";
		exit(1);
	}
}

// 3) Add group_id to panel_assignments
if (tableExists($conn, 'panel_assignments') && !columnExists($conn, 'panel_assignments', 'group_id')) {
	if (!$conn->query("ALTER TABLE panel_assignments ADD COLUMN group_id INT NULL AFTER group_code, ADD INDEX idx_panel_assignments_group_id (group_id)")) {
		echo "Failed to add panel_assignments.group_id: " . $conn->error . "\n";
		exit(1);
	}
}

// 4) Backfill groups from existing students.group_code
// Expect legacy codes like 3A-G1 or 4B-G2
$result = $conn->query("SELECT DISTINCT group_code, year_section FROM students WHERE group_code IS NOT NULL AND group_code <> ''");
$insertGroupStmt = $conn->prepare("INSERT INTO groups (group_name, year_level, section_letter, year_section, cohort_year, is_active) VALUES (?, ?, ?, ?, ?, 1)");
$findGroupStmt = $conn->prepare("SELECT id FROM groups WHERE group_name = ? AND year_section = ? AND (cohort_year IS NULL OR cohort_year = ?) LIMIT 1");

$created = 0; $linkedStudents = 0; $linkedAssignments = 0;
while ($row = $result->fetch_assoc()) {
	$groupCode = $row['group_code'];
	$yearSection = $row['year_section']; // e.g., 3A or 4B

	if (!preg_match('/^(?<year>[34])(?<letter>[A-Z])\-G(?<num>\d+)$/', $groupCode, $m)) {
		continue; // skip unexpected formats
	}
	$yearLevel = (int)$m['year'];
	$sectionLetter = $m['letter'];
	$groupName = 'G' . $m['num'];

	$cohortYear = null; // unknown historically; can be populated later

	// Find or create group
	$findGroupStmt->bind_param('ssi', $groupName, $yearSection, $cohortYear);
	$findGroupStmt->execute();
	$gr = $findGroupStmt->get_result();
	if ($gr && $gr->num_rows > 0) {
		$groupId = (int)$gr->fetch_assoc()['id'];
	} else {
		$insertGroupStmt->bind_param('isisi', $groupName, $yearLevel, $sectionLetter, $yearSection, $cohortYear);
		if ($insertGroupStmt->execute()) {
			$groupId = $insertGroupStmt->insert_id;
			$created++;
		} else {
			continue;
		}
	}

	// Update students for this legacy code
	$upd = $conn->prepare("UPDATE students SET group_id = ? WHERE group_code = ?");
	$upd->bind_param('is', $groupId, $groupCode);
	$upd->execute();
	$linkedStudents += $upd->affected_rows;
	$upd->close();

	// Update panel_assignments for this legacy code
	if (tableExists($conn, 'panel_assignments')) {
		$upa = $conn->prepare("UPDATE panel_assignments SET group_id = ? WHERE group_code = ?");
		$upa->bind_param('is', $groupId, $groupCode);
		$upa->execute();
		$linkedAssignments += $upa->affected_rows;
		$upa->close();
	}
}

// 5) Add foreign keys (best-effort; ignore if fail)
@$conn->query("ALTER TABLE students ADD CONSTRAINT fk_students_group_id FOREIGN KEY (group_id) REFERENCES groups(id)");
if (tableExists($conn, 'panel_assignments')) {
	@$conn->query("ALTER TABLE panel_assignments ADD CONSTRAINT fk_panel_assignments_group_id FOREIGN KEY (group_id) REFERENCES groups(id)");
}

echo "Groups created: {$created}\n";
echo "Students linked: {$linkedStudents}\n";
echo "Panel assignments linked: {$linkedAssignments}\n";
echo "Groups migration completed.\n";

?>


