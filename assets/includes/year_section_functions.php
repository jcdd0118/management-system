<?php
// year_section_functions.php
// Helper functions for managing year sections

/**
 * Get all active year sections from database
 * @param mysqli $conn Database connection
 * @return array Array of year sections
 */
function getActiveYearSections($conn) {
    $result = $conn->query("SELECT * FROM year_sections WHERE is_active = 1 ORDER BY year_level, section_letter");
    $sections = [];
    
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row;
    }
    
    return $sections;
}

/**
 * Get all year sections (active and inactive) from database
 * @param mysqli $conn Database connection
 * @return array Array of year sections
 */
function getAllYearSections($conn) {
    $result = $conn->query("SELECT * FROM year_sections ORDER BY year_level, section_letter");
    $sections = [];
    
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row;
    }
    
    return $sections;
}

/**
 * Get year section by ID
 * @param mysqli $conn Database connection
 * @param int $id Year section ID
 * @return array|null Year section data or null if not found
 */
function getYearSectionById($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM year_sections WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

/**
 * Get year section by year_section code
 * @param mysqli $conn Database connection
 * @param string $year_section Year section code (e.g., '3A', '4B')
 * @return array|null Year section data or null if not found
 */
function getYearSectionByCode($conn, $year_section) {
    $stmt = $conn->prepare("SELECT * FROM year_sections WHERE year_section = ?");
    $stmt->bind_param("s", $year_section);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

/**
 * Check if year section exists
 * @param mysqli $conn Database connection
 * @param string $year_section Year section code
 * @return bool True if exists, false otherwise
 */
function yearSectionExists($conn, $year_section) {
    $stmt = $conn->prepare("SELECT id FROM year_sections WHERE year_section = ?");
    $stmt->bind_param("s", $year_section);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

/**
 * Generate year section options HTML for select dropdown
 * @param mysqli $conn Database connection
 * @param string $selected_value Currently selected value
 * @param bool $active_only Whether to show only active year sections
 * @return string HTML options
 */
function generateYearSectionOptions($conn, $selected_value = '', $active_only = true) {
    $sections = $active_only ? getActiveYearSections($conn) : getAllYearSections($conn);
    $options = '<option value="" disabled' . (empty($selected_value) ? ' selected' : '') . '></option>';
    
    foreach ($sections as $section) {
        $selected = ($selected_value === $section['year_section']) ? ' selected' : '';
        $options .= '<option value="' . htmlspecialchars($section['year_section']) . '"' . $selected . '>' . 
                   htmlspecialchars($section['display_name']) . '</option>';
    }
    
    return $options;
}

/**
 * Get year sections grouped by year level
 * @param mysqli $conn Database connection
 * @param bool $active_only Whether to show only active year sections
 * @return array Year sections grouped by year level
 */
function getYearSectionsByLevel($conn, $active_only = true) {
    $sections = $active_only ? getActiveYearSections($conn) : getAllYearSections($conn);
    $grouped = [];
    
    foreach ($sections as $section) {
        $grouped[$section['year_level']][] = $section;
    }
    
    return $grouped;
}

/**
 * Validate year section format
 * @param string $year_section Year section to validate
 * @return bool True if valid format, false otherwise
 */
function validateYearSectionFormat($year_section) {
    return preg_match('/^[3-4][A-Z]$/', $year_section);
}

/**
 * Get next available section letter for a year level
 * @param mysqli $conn Database connection
 * @param int $year_level Year level (3 or 4)
 * @return string Next available section letter
 */
function getNextSectionLetter($conn, $year_level) {
    $stmt = $conn->prepare("SELECT section_letter FROM year_sections WHERE year_level = ? ORDER BY section_letter");
    $stmt->bind_param("i", $year_level);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $used_letters = [];
    while ($row = $result->fetch_assoc()) {
        $used_letters[] = $row['section_letter'];
    }
    
    // Find next available letter
    for ($i = ord('A'); $i <= ord('Z'); $i++) {
        $letter = chr($i);
        if (!in_array($letter, $used_letters)) {
            return $letter;
        }
    }
    
    return 'A'; // Fallback
}
?>
