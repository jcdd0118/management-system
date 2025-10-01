<?php
session_start();
header('Content-Type: application/json');

// Ensure clean JSON responses even if warnings/notices occur
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function($e) {
    $buffer = ob_get_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'error' => $e->getMessage(),
        'output' => $buffer
    ]);
    exit;
});

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';
require_once '../../assets/includes/year_section_functions.php';

$password = isset($_POST['password']) ? $_POST['password'] : '';

// Verify admin password
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ? AND role = 'admin'");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row || !password_verify($password, $row['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid password']);
    exit;
}

try {
    // Helper: add columns if not exist
    function ensureGraduationColumns($conn) {
        $columns = [];
        $res = $conn->query("SHOW COLUMNS FROM students");
        while ($row = $res->fetch_assoc()) { 
            $columns[$row['Field']] = true; 
        }
        
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

    // Count students before rollover
    $beforeCount = [];
    $result = $conn->query("SELECT year_section, COUNT(*) as count FROM students WHERE is_graduated = 0 GROUP BY year_section");
    while ($row = $result->fetch_assoc()) {
        $beforeCount[$row['year_section']] = $row['count'];
    }

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

    $graduatedCount = 0;
    $promotedCount = 0;

    // 1) Graduate 4th year students marked as ready_to_graduate
    if (!empty($fourthYearSections)) {
        $fourthYearPlaceholders = str_repeat('?,', count($fourthYearSections) - 1) . '?';
        $graduateSql = "UPDATE students SET is_graduated = 1, graduated_at = IF(graduated_at IS NULL, NOW(), graduated_at)
                        WHERE year_section IN ({$fourthYearPlaceholders}) AND ready_to_graduate = 1 AND is_graduated = 0";
        
        $stmt = $conn->prepare($graduateSql);
        $stmt->bind_param(str_repeat('s', count($fourthYearSections)), ...$fourthYearSections);
        $stmt->execute();
        $graduatedCount = $stmt->affected_rows;
        $stmt->close();
    }

    // 2) Promote 3rd years to 4th years
    if (!empty($thirdYearSections)) {
        foreach ($thirdYearSections as $thirdYearSection) {
            // Find corresponding 4th year section with same letter
            $sectionLetter = substr($thirdYearSection, 1); // Get letter part (A, B, etc.)
            $fourthYearSection = '4' . $sectionLetter;
            
            // Check if the 4th year section exists
            if (yearSectionExists($conn, $fourthYearSection)) {
                $promoteSql = "UPDATE students SET year_section = ? WHERE year_section = ? AND is_graduated = 0";
                $stmt = $conn->prepare($promoteSql);
                $stmt->bind_param("ss", $fourthYearSection, $thirdYearSection);
                $stmt->execute();
                $promotedCount += $stmt->affected_rows;
                $stmt->close();

                // Also update legacy group_code to reflect new year (e.g., 3B-G1 -> 4B-G1)
                // Only if column group_code exists
                $hasGroupCode = false;
                if ($chk = $conn->query("SHOW COLUMNS FROM students LIKE 'group_code'")) {
                    $hasGroupCode = ($chk->num_rows > 0);
                    $chk->close();
                }
                if ($hasGroupCode) {
                    // Broader update: bump any legacy group_code starting with '3' to '4'
                    // Keeps suffix intact (e.g., 3X-G12 -> 4X-G12)
                    $updateGroupCodeSql = "UPDATE students 
                                           SET group_code = CONCAT('4', SUBSTR(group_code, 2))
                                           WHERE LEFT(group_code,1)='3' AND is_graduated = 0";
                    $conn->query($updateGroupCodeSql);
                }
            }
        }
    }

    // Count students after rollover
    $afterCount = [];
    $result = $conn->query("SELECT year_section, COUNT(*) as count FROM students WHERE is_graduated = 0 GROUP BY year_section");
    while ($row = $result->fetch_assoc()) {
        $afterCount[$row['year_section']] = $row['count'];
    }

    $message = "Rollover completed successfully! ";
    $message .= "Promoted {$promotedCount} students from 3rd to 4th year. ";
    $message .= "Graduated {$graduatedCount} 4th year students. ";
    
    if ($promotedCount == 0 && $graduatedCount == 0) {
        $message .= "Note: No students were promoted or graduated. Make sure you have 3rd year students and 4th year students marked as 'ready to graduate'.";
    }

    $buffer = ob_get_clean();
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'details' => [
            'promoted' => $promotedCount,
            'graduated' => $graduatedCount,
            'before' => $beforeCount,
            'after' => $afterCount
        ],
        'output' => $buffer
    ]);

} catch (Exception $e) {
    $buffer = ob_get_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'output' => $buffer]);
}
?>