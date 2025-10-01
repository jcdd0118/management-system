<?php
// group_functions.php

/**
 * Ensure a group exists by its parts and return its id.
 * If not present, create it (best-effort).
 */
function ensureGroupId($conn, $groupName, $yearSection, $cohortYear, $yearLevel, $sectionLetter) {
	$stmt = $conn->prepare("SELECT id FROM groups WHERE group_name = ? AND year_section = ? AND (cohort_year IS NULL OR cohort_year = ?) LIMIT 1");
	$stmt->bind_param('ssi', $groupName, $yearSection, $cohortYear);
	$stmt->execute();
	$res = $stmt->get_result();
	if ($res && $res->num_rows > 0) {
		$id = (int)$res->fetch_assoc()['id'];
		$stmt->close();
		return $id;
	}
	$stmt->close();

    $ins = $conn->prepare("INSERT INTO groups (group_name, year_level, section_letter, year_section, cohort_year, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $ins->bind_param('sisss', $groupName, $yearLevel, $sectionLetter, $yearSection, $cohortYear);
	if ($ins->execute()) {
		$id = $ins->insert_id;
		$ins->close();
		return $id;
	}
	$ins->close();
    return null;
}

/**
 * Compute display code like "4A-G1" from parts.
 */
function computeGroupDisplayCode($yearSection, $groupName) {
	return $yearSection . '-' . $groupName;
}

/**
 * Fetch display code for a student by user_id using group_id linkage.
 */
function getStudentGroupDisplayByUserId($conn, $userId) {
	$sql = "SELECT s.year_section, g.group_name FROM students s LEFT JOIN groups g ON s.group_id = g.id WHERE s.user_id = ? LIMIT 1";
	$stmt = $conn->prepare($sql);
	$stmt->bind_param('i', $userId);
	$stmt->execute();
	$res = $stmt->get_result();
	if ($res && $res->num_rows > 0) {
		$row = $res->fetch_assoc();
		$stmt->close();
		if (!empty($row['group_name']) && !empty($row['year_section'])) {
			return computeGroupDisplayCode($row['year_section'], $row['group_name']);
		}
	}
	$stmt->close();
	return null;
}

?>


