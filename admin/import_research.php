<?php
// Start the session
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../users/login.php");
    exit();
}

// Database connection
include '../config/database.php';

$success_count = 0;
$error_count = 0;
$errors = [];

// Optional: handle a ZIP of PDFs to auto-link
$extracted_base = null;
$available_files = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    // If a ZIP of PDFs was also uploaded, extract it to a dated folder under uploads
    if (isset($_FILES['pdf_zip']) && is_array($_FILES['pdf_zip']) && $_FILES['pdf_zip']['error'] === UPLOAD_ERR_OK) {
        $zipTmp = $_FILES['pdf_zip']['tmp_name'];
        $zip = new ZipArchive();
        if ($zip->open($zipTmp) === TRUE) {
            // Extract into assets/uploads/capstone (root-relative from web root)
            $uploadsWebBase = 'assets/uploads/capstone';
            $targetDir = realpath(__DIR__ . '/../assets/uploads/capstone');
            if ($targetDir === false) {
                // Try to create the directory if it doesn't exist
                $tryBase = __DIR__ . '/../assets/uploads/capstone';
                @mkdir($tryBase, 0777, true);
                $targetDir = realpath($tryBase);
            }
            if ($targetDir !== false) {
                $subdir = $targetDir . DIRECTORY_SEPARATOR . 'imported_research_' . date('Ymd_His');
                if (!is_dir($subdir)) { @mkdir($subdir, 0777, true); }
                if (is_dir($subdir) && $zip->extractTo($subdir)) {
                    $extracted_base = $subdir;
                    // Build filename map (lowercase basename => relative path)
                    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($subdir, FilesystemIterator::SKIP_DOTS));
                    foreach ($rii as $file) {
                        if ($file->isFile()) {
                            $basename = strtolower($file->getBasename());
                            // Relative part inside the extracted folder
                            $inside = substr($file->getPathname(), strlen($subdir) + 1);
                            $inside = str_replace(['\\', '//'], '/', $inside);
                            // Web path stored in DB
                            $relWeb = $uploadsWebBase . '/' . basename($subdir) . '/' . $inside;
                            $relWeb = str_replace(['\\', '//'], '/', $relWeb);
                            $available_files[$basename] = $relWeb;
                        }
                    }
                } else {
                    $errors[] = 'Failed to extract ZIP of PDFs.';
                    $error_count++;
                }
            } else {
                $errors[] = 'Uploads directory not found for ZIP extraction.';
                $error_count++;
            }
            $zip->close();
        } else {
            $errors[] = 'Invalid ZIP file (could not open).';
            $error_count++;
        }
    }
    $file = $_FILES['excel_file'];
    
    // Check if file was uploaded successfully
    if ($file['error'] == UPLOAD_ERR_OK) {
        $file_path = $file['tmp_name'];
        
        // Read the Excel file (CSV format)
        if (($handle = fopen($file_path, "r")) !== FALSE) {
            $row_count = 0;
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row_count++;
                
                // Skip header row
                if ($row_count == 1) {
                    continue;
                }
                
                // Validate required fields
                if (count($data) < 6) {
                    $errors[] = "Row $row_count: Insufficient data columns";
                    $error_count++;
                    continue;
                }
                
                $title = trim($data[1]);
                $author = trim($data[2]);
                $year = trim($data[3]);
                $abstract = trim($data[4]);
                $keywords = trim($data[5]);
                $document_path = isset($data[6]) ? trim($data[6]) : '';
                $user_id = isset($data[7]) ? trim($data[7]) : null;
                $status = isset($data[8]) ? trim($data[8]) : 'nonverified';
                
                // Convert simple author format to STUDENT_DATA format if it's not already in that format
                if (!empty($author) && strpos($author, 'STUDENT_DATA:') !== 0) {
                    // Parse comma-separated authors and convert to STUDENT_DATA format
                    $authors = explode(',', $author);
                    $author_data_compact = "STUDENT_DATA:";
                    $display_names = [];
                    
                    foreach ($authors as $authorName) {
                        $authorName = trim($authorName);
                        if (!empty($authorName)) {
                            // Parse name into parts (simple parsing - assumes "First Middle Last" format)
                            $nameParts = explode(' ', $authorName);
                            $firstName = isset($nameParts[0]) ? $nameParts[0] : '';
                            $lastName = end($nameParts) ? end($nameParts) : '';
                            $middleName = '';
                            
                            // If more than 2 parts, middle name is everything in between
                            if (count($nameParts) > 2) {
                                $middleName = implode(' ', array_slice($nameParts, 1, -1));
                            }
                            
                            $author_data_compact .= $firstName . "|" . $middleName . "|" . $lastName . "|@@";
                            $display_names[] = $authorName;
                        }
                    }
                    
                    $author_data_compact = rtrim($author_data_compact, "@") . "|DISPLAY:" . implode(', ', $display_names);
                    $author = $author_data_compact;
                }
                
                // Validate required fields
                if (empty($title) || empty($author) || empty($year) || empty($abstract) || empty($keywords)) {
                    $errors[] = "Row $row_count: Missing required fields (Title, Author, Year, Abstract, Keywords)";
                    $error_count++;
                    continue;
                }
                
                // Validate year
                if (!is_numeric($year) || $year < 1900 || $year > date('Y') + 5) {
                    $errors[] = "Row $row_count: Invalid year";
                    $error_count++;
                    continue;
                }
                
                // Validate user_id if provided
                if (!empty($user_id) && !is_numeric($user_id)) {
                    $errors[] = "Row $row_count: Invalid user ID (must be numeric)";
                    $error_count++;
                    continue;
                }
                
                // Map/validate user_id: FK targets users.id
                $resolved_user_id = null;
                if (!empty($user_id)) {
                    $uid = (int)$user_id;
                    // Try users.id
                    $checkUser = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
                    $checkUser->bind_param('i', $uid);
                    $checkUser->execute();
                    $userRes = $checkUser->get_result();
                    if ($userRes && $userRes->num_rows > 0) {
                        $resolved_user_id = $uid;
                    } else {
                        // Maybe CSV provided students.id → map to users via students.user_id
                        $mapStmt = $conn->prepare("SELECT user_id FROM students WHERE id = ? LIMIT 1");
                        $mapStmt->bind_param('i', $uid);
                        $mapStmt->execute();
                        $mapRes = $mapStmt->get_result();
                        if ($mapRes && $mapRes->num_rows > 0) {
                            $rowMap = $mapRes->fetch_assoc();
                            $resolved_user_id = (int)$rowMap['user_id'];
                        } else {
                            $errors[] = "Row $row_count: User ID not found in users or students";
                            $error_count++;
                            $mapStmt->close();
                            $checkUser->close();
                            continue;
                        }
                        $mapStmt->close();
                    }
                    $checkUser->close();
                }
                
                // Validate status
                if (!in_array($status, ['verified', 'nonverified'])) {
                    $status = 'nonverified';
                }
                
                // If ZIP provided, try to resolve document_path by filename
                if (empty($document_path) && !empty($available_files)) {
                    // Try to guess from title: replace spaces with underscores and add .pdf
                    $guess = strtolower(preg_replace('/\s+/', '_', $title)) . '.pdf';
                    if (isset($available_files[$guess])) {
                        $document_path = $available_files[$guess];
                    }
                }
                if (!empty($available_files) && !empty($document_path) && !preg_match('/\//', $document_path)) {
                    // document_path looks like a filename; map it from extracted set
                    $key = strtolower(basename($document_path));
                    if (isset($available_files[$key])) {
                        $document_path = $available_files[$key];
                    }
                }

                // Insert research
                $insert_query = "INSERT INTO capstone (title, author, year, abstract, keywords, document_path, user_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = mysqli_prepare($conn, $insert_query);
                // Use resolved_user_id (may be null)
                mysqli_stmt_bind_param($stmt, "ssisssss", $title, $author, $year, $abstract, $keywords, $document_path, $resolved_user_id, $status);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_count++;
                } else {
                    $errors[] = "Row $row_count: Database error - " . mysqli_error($conn);
                    $error_count++;
                }
                
                mysqli_stmt_close($stmt);
            }
            
            fclose($handle);
        } else {
            $errors[] = "Could not read the uploaded file";
            $error_count++;
        }
    } else {
        $errors[] = "File upload error: " . $file['error'];
        $error_count++;
    }
}

mysqli_close($conn);

// Store results in session for display
$_SESSION['import_results'] = [
    'success_count' => $success_count,
    'error_count' => $error_count,
    'errors' => $errors
];

// Redirect back to research list
header("Location: research_list.php?import=completed");
exit();
?>
