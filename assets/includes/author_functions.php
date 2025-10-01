<?php
// Author parsing functions for handling compact format

/**
 * Parse author data from compact format
 * @param string $authorString The author string from database
 * @return string The display-friendly author string
 */
function parseAuthorData($authorString) {
    if (strpos($authorString, 'STUDENT_DATA:') === 0) {
        // Extract display author from special format
        $parts = explode('|DISPLAY:', $authorString);
        if (count($parts) === 2) {
            return $parts[1]; // Return the display string
        }
    }
    // Return original string if not in special format
    return $authorString;
}

/**
 * Parse author data and return as array for detailed display
 * @param string $authorString The author string from database
 * @return array Array of author data
 */
function parseAuthorDataDetailed($authorString) {
    if (strpos($authorString, 'STUDENT_DATA:') === 0) {
        // Extract compact data
        $parts = explode('|DISPLAY:', $authorString);
        if (count($parts) === 2) {
            $compactData = $parts[0];
            $displayData = $parts[1];
            
            // Remove STUDENT_DATA: prefix
            $compactData = str_replace('STUDENT_DATA:', '', $compactData);
            
            // Parse compact format: firstName1|middleName1|lastName1|suffix1@@firstName2|middleName2|lastName2|suffix2@@
            $authorStrings = explode('@@', $compactData);
            $authorStrings = array_filter($authorStrings, function($s) { return trim($s); });
            
            $authors = [];
            foreach ($authorStrings as $authorString) {
                $authorParts = explode('|', $authorString);
                if (count($authorParts) >= 3) {
                    $authors[] = [
                        'firstName' => isset($authorParts[0]) ? $authorParts[0] : '',
                        'middleName' => isset($authorParts[1]) ? $authorParts[1] : '',
                        'lastName' => isset($authorParts[2]) ? $authorParts[2] : '',
                        'suffix' => isset($authorParts[3]) ? $authorParts[3] : ''
                    ];
                }
            }
            
            return $authors;
        }
    }
    
    // Return single author if not in special format
    return [['firstName' => $authorString, 'middleName' => '', 'lastName' => '', 'suffix' => '']];
}
?>
