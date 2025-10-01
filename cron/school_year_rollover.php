<?php
// school_year_rollover.php
// Usage: Run at the start of every school year to graduate 4th years and promote 3rd years

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../assets/includes/year_section_functions.php';

// Helper: add columns if not exist
function ensureGraduationColumns($conn) {
    $columns = [];
    $res = $conn->query("SHOW COLUMNS FROM students");
    while ($row = $res->fetch_assoc()) { $columns[$row['Field']] = true; }
    if (!isset($columns['is_graduated'])) {
        $conn->query("ALTER TABLE students ADD COLUMN is_graduated TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
    }
    if (!isset($columns['graduated_at'])) {
        $conn->query("ALTER TABLE students ADD COLUMN graduated_at DATETIME NULL DEFAULT NULL AFTER is_graduated");
    }
    if (!isset($columns['ready_to_graduate'])) {
        $conn->query("ALTER TABLE students ADD COLUMN ready_to_graduate TINYINT(1) NOT NULL DEFAULT 0 AFTER graduated_at");
    }
}

ensureGraduationColumns($conn);

// Get active year sections
$yearSections = getActiveYearSections($conn);
$thirdYearSections = [];
$fourthYearSections = [];

foreach ($yearSections as $section) {
    if ($section['year_level'] == 3) {
        $thirdYearSections[] = $section['year_section'];
    } elseif ($section['year_level'] == 4) {
        $fourthYearSections[] = $section['year_section'];
    }
}

// Check current status
$currentStats = [];
$result = $conn->query("SELECT year_section, COUNT(*) as count FROM students GROUP BY year_section");
while ($row = $result->fetch_assoc()) {
    $currentStats[$row['year_section']] = $row['count'];
}

echo "Before rollover:\n";
foreach ($currentStats as $year => $count) {
    echo "- {$year}: {$count} students\n";
}

// 1) Graduate only 4th year students explicitly marked ready_to_graduate
if (!empty($fourthYearSections)) {
    $fourthYearPlaceholders = str_repeat('?,', count($fourthYearSections) - 1) . '?';
    $graduateSql = "UPDATE students SET is_graduated = 1, graduated_at = IF(graduated_at IS NULL, NOW(), graduated_at)
                    WHERE year_section IN ({$fourthYearPlaceholders}) AND ready_to_graduate = 1 AND is_graduated = 0";
    
    $stmt = $conn->prepare($graduateSql);
    $stmt->bind_param(str_repeat('s', count($fourthYearSections)), ...$fourthYearSections);
    $stmt->execute();
    $graduatedCount = $stmt->affected_rows;
    echo "Graduated {$graduatedCount} 4th year students.\n";
    $stmt->close();
}

// 2) Promote 3rd years to 4th, keeping section the same letter
if (!empty($thirdYearSections)) {
    foreach ($thirdYearSections as $thirdYearSection) {
        // Find corresponding 4th year section with same letter
        $sectionLetter = substr($thirdYearSection, 1); // Get letter part (A, B, etc.)
        $fourthYearSection = '4' . $sectionLetter;
        
        // Check if the 4th year section exists
        if (yearSectionExists($conn, $fourthYearSection)) {
            $promoteSql = "UPDATE students SET year_section = ? WHERE year_section = ?";
            $stmt = $conn->prepare($promoteSql);
            $stmt->bind_param("ss", $fourthYearSection, $thirdYearSection);
            $stmt->execute();
            $promotedCount = $stmt->affected_rows;
            echo "Promoted {$promotedCount} students from {$thirdYearSection} to {$fourthYearSection}.\n";
            $stmt->close();

            // Also update legacy group_code to reflect new year (e.g., 3B-G1 -> 4B-G1), only if column exists
            $hasGroupCode = false;
            if ($chk = $conn->query("SHOW COLUMNS FROM students LIKE 'group_code'")) {
                $hasGroupCode = ($chk->num_rows > 0);
                $chk->close();
            }
            if ($hasGroupCode) {
                // Broader update: bump any legacy group_code starting with '3' to '4'
                $updateGroupCodeSql = "UPDATE students 
                                       SET group_code = CONCAT('4', SUBSTR(group_code, 2))
                                       WHERE LEFT(group_code,1)='3' AND is_graduated = 0";
                if ($conn->query($updateGroupCodeSql)) {
                    $updatedCodes = $conn->affected_rows;
                    echo "Updated {$updatedCodes} legacy group codes (3*-G# -> 4*-G#).\n";
                }
            }
        } else {
            echo "Warning: No corresponding 4th year section found for {$thirdYearSection}.\n";
        }
    }
}

// Final stats
$finalStats = [];
$result = $conn->query("SELECT year_section, COUNT(*) as count FROM students GROUP BY year_section");
while ($row = $result->fetch_assoc()) {
    $finalStats[$row['year_section']] = $row['count'];
}

echo "After rollover:\n";
foreach ($finalStats as $year => $count) {
    echo "- {$year}: {$count} students\n";
}

echo "School year rollover completed.\n";
?>