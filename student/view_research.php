<?php
session_start();
ob_start();
require_once '../config/database.php';
require_once '../assets/includes/author_functions.php';

if (!$conn) {
    error_log('Database connection failed at ' . date('Y-m-d H:i:s') . ': ' . mysqli_connect_error());
    ob_end_clean();
    die('Database connection failed. Please try again later.');
}

// Get the research ID from the URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    ob_end_clean();
    die('Invalid research ID.');
}

// Fetch research details
$sql = 'SELECT title, author, year, abstract, keywords, document_path 
        FROM capstone 
        WHERE id = ? AND status = \'verified\'';
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log('Prepare failed at ' . date('Y-m-d H:i:s') . ': ' . $conn->error);
    ob_end_clean();
    die('An error occurred while fetching the research. Please try again.');
}

$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$research = $result->fetch_assoc();
$stmt->close();

if (!$research) {
    ob_end_clean();
    die('Research not found or not verified.');
}

// Check if the research is bookmarked by the current user
$is_bookmarked = false;
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student') {
    $user_id = (int)$_SESSION['user_id'];
    $sql = 'SELECT id FROM bookmarks WHERE user_id = ? AND research_id = ?';
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ii', $user_id, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $is_bookmarked = $result->num_rows > 0;
        $stmt->close();
    } else {
        error_log('Bookmark check prepare failed: ' . $conn->error);
    }
}

// Function to format author names in APA style
function formatAuthorAPA($author) {
    if (empty($author)) {
        return 'Unknown Author';
    }

    $authors = array_map('trim', explode(',', $author));
    $formattedAuthors = array();

    foreach ($authors as $authorName) {
        $nameParts = explode(' ', trim($authorName));
        $count = count($nameParts);
        
        if ($count === 0) {
            continue;
        }

        $lastName = array_pop($nameParts);
        $initials = array();
        foreach ($nameParts as $name) {
            if (!empty($name)) {
                $initials[] = strtoupper(substr($name, 0, 1)) . '.';
            }
        }
        $formattedAuthors[] = $lastName . ', ' . implode(' ', $initials);
    }

    $count = count($formattedAuthors);
    if ($count === 0) {
        return 'Unknown Author';
    } elseif ($count === 1) {
        return $formattedAuthors[0];
    } elseif ($count === 2) {
        return $formattedAuthors[0] . ', & ' . $formattedAuthors[1];
    } else {
        return implode(', ', array_slice($formattedAuthors, 0, -1)) . ', & ' . end($formattedAuthors);
    }
}

// Function to generate a citation in APA format
function generateCitation($research) {
    $authors = formatAuthorAPA($research['author']);
    $year = htmlspecialchars($research['year']);
    $title = htmlspecialchars($research['title']);
    return $authors . ' (' . $year . '). ' . $title . '. CCS Research Repository.';
}

$citation = generateCitation($research);

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($research['title']); ?> - CCS Research Repository</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <?php include '../assets/includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include '../assets/includes/navbar.php'; ?>

        <div class="container my-4">
            <h1 class="fw-bold"><?php echo htmlspecialchars($research['title']); ?></h1>
            <p class="text-muted mb-1">
                <strong>Author:</strong> <?php echo htmlspecialchars(parseAuthorData($research['author'])); ?> |
                <strong>Year:</strong> <?php echo htmlspecialchars($research['year']); ?>
            </p>

            <div class="my-3">
                <?php if ($research['document_path']): ?>
                    <a href="view_pdf.php?file=<?php echo urlencode($research['document_path']); ?>" class="btn btn-sm btn-primary me-2" target="_blank">
                        <i class="bi bi-eye"></i> View PDF
                    </a>
                    <a href="download.php?file=<?php echo urlencode($research['document_path']); ?>" class="btn btn-sm btn-outline-primary me-2">
                        <i class="bi bi-download"></i> Download PDF
                    </a>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#citationModal">
                    <i class="bi bi-quote"></i> Cite
                </button>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student'): ?>
                    <button id="bookmark-btn" class="btn btn-sm <?php echo $is_bookmarked ? 'btn-success' : 'btn-outline-success'; ?>" 
                            onclick="bookmarkResearch(<?php echo $id; ?>, this)">
                        <i class="bi <?php echo $is_bookmarked ? 'bi-bookmark-fill' : 'bi-bookmark'; ?>"></i>
                        <span><?php echo $is_bookmarked ? 'Bookmarked' : 'Bookmark'; ?></span>
                    </button>
                <?php else: ?>
                    <button class="btn btn-sm btn-outline-success" disabled title="Please log in as a student to bookmark">
                        <i class="bi bi-bookmark"></i> <span>Bookmark</span>
                    </button>
                <?php endif; ?>
            </div>

            <h4>Abstract</h4>
            <p><?php echo htmlspecialchars($research['abstract']); ?></p>

            <h4>Keywords</h4>
            <p><?php echo htmlspecialchars($research['keywords']); ?></p>
        </div>

        <!-- Citation Modal -->
        <div class="modal fade" id="citationModal" tabindex="-1" aria-labelledby="citationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="citationModalLabel">APA Citation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p id="citationText"><?php echo htmlspecialchars($citation); ?></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="copyCitation()">Copy to Clipboard</button>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function copyCitation() {
        var citation = document.getElementById('citationText').textContent;
        var textarea = document.createElement('textarea');
        textarea.value = citation;
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            alert('Citation copied to clipboard!');
        } catch (err) {
            console.error('Failed to copy citation:', err);
            alert('Failed to copy citation.');
        }
        document.body.removeChild(textarea);
    }

    function bookmarkResearch(id, button) {
        console.log('Bookmarking research ID:', id);
        if (!button) {
            console.error('Button is null');
            alert('An error occurred: Button reference is missing.');
            return;
        }
        var isBookmarked = button.classList.contains('btn-success');
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/bookmark.php', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                console.log('Response status:', xhr.status);
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        console.log('Response data:', data);
                        if (data.success) {
                            var icon = button.querySelector('i');
                            var span = button.querySelector('span');
                            if (!icon || !span) {
                                console.error('Icon or span element not found');
                                alert('An error occurred while updating the button.');
                                return;
                            }
                            if (data.action === 'added') {
                                button.classList.remove('btn-outline-success');
                                button.classList.add('btn-success');
                                icon.classList.remove('bi-bookmark');
                                icon.classList.add('bi-bookmark-fill');
                                span.textContent = 'Bookmarked';
                                alert('Research bookmarked successfully!');
                            } else if (data.action === 'removed') {
                                button.classList.remove('btn-success');
                                button.classList.add('btn-outline-success');
                                icon.classList.remove('bi-bookmark-fill');
                                icon.classList.add('bi-bookmark');
                                span.textContent = 'Bookmark';
                                alert('Bookmark removed successfully!');
                            }
                        } else {
                            alert(data.message || 'Failed to update bookmark.');
                        }
                    } catch (err) {
                        console.error('JSON parse error:', err);
                        alert('An error occurred while bookmarking: ' + err.message);
                    }
                } else {
                    console.error('Bookmark error: HTTP status ' + xhr.status);
                    alert('An error occurred while bookmarking: HTTP status ' + xhr.status);
                }
            }
        };
        xhr.onerror = function() {
            console.error('Bookmark error: Network error');
            alert('An error occurred while bookmarking: Network error');
        };
        xhr.send(JSON.stringify({ research_id: id, action: isBookmarked ? 'remove' : 'add' }));
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>