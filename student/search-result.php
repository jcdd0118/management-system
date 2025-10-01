<?php
ob_start();
require_once '../config/database.php';
require_once '../assets/includes/author_functions.php';
if (!$conn) {
    error_log("Database connection failed at " . date('Y-m-d H:i:s') . ": " . mysqli_connect_error());
    ob_end_clean();
    die("Database connection failed. Please try again later.");
}

// Load dictionary file
function loadDictionary($file = 'dictionary.txt') {
    if (!file_exists($file)) return [];
    $words = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return array_map('strtolower', $words);
}

// Suggest correction using Levenshtein
function suggestCorrection($term, $dictionary) {
    $words = explode(' ', strtolower(trim($term)));
    $corrected = [];

    foreach ($words as $word) {
        $closest = $word;
        $shortest = -1;

        foreach ($dictionary as $dictWord) {
            $lev = levenshtein($word, $dictWord);
            if ($lev == 0) {
                $closest = $word;
                break;
            }
            if ($lev < $shortest || $shortest < 0) {
                $closest = $dictWord;
                $shortest = $lev;
            }
        }

        $corrected[] = $closest;
    }

    return implode(' ', $corrected);
}

// Initialize variables
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$original_query = $query;
$selected_year = isset($_GET['year']) ? $_GET['year'] : '';
$error_message = '';
$results = [];
$suggestions = [];
$corrected_query = '';
$spell_corrected = false;
$dictionary = loadDictionary();
// Check if user has explicitly chosen a query
$user_chosen = isset($_GET['chosen']) && $_GET['chosen'] === '1';

// Get available years for the dropdown
$years_sql = "SELECT DISTINCT year FROM capstone WHERE status = 'verified' ORDER BY year DESC";
$years_result = $conn->query($years_sql);
$available_years = [];
if ($years_result) {
    while ($row = $years_result->fetch_assoc()) {
        $available_years[] = $row['year'];
    }
}

// Validate query
if (empty($query)) {
    $error_message = "Please enter a search query.";
} elseif (strlen($query) > 255 || preg_match('/[;{}]/', $query)) {
    error_log("Suspicious query at " . date('Y-m-d H:i:s') . ": $query");
    $error_message = "Invalid search query. Avoid using special characters like ; or {}.";
} else {
    $corrected_query = suggestCorrection($query, $dictionary);
    $spell_corrected = $corrected_query !== $query && !$user_chosen; // Only set if not user-chosen
    $search_query = $spell_corrected ? $corrected_query : $query;

    $searchTerms = [];
    $queryParts = [];
    $currentTerm = '';
    $inQuotes = false;

    for ($i = 0; $i < strlen($search_query); $i++) {
        if ($search_query[$i] === '"') {
            if ($inQuotes) {
                if ($currentTerm !== '') {
                    $queryParts[] = $currentTerm;
                    $currentTerm = '';
                }
                $inQuotes = false;
            } else {
                $inQuotes = true;
            }
        } elseif ($search_query[$i] === ' ' && !$inQuotes && $currentTerm !== '') {
            $queryParts[] = $currentTerm;
            $currentTerm = '';
        } else {
            $currentTerm .= $search_query[$i];
        }
    }
    if ($currentTerm !== '') {
        $queryParts[] = $currentTerm;
    }

    foreach ($queryParts as $part) {
        $part = $conn->real_escape_string(trim($part, '"'));
        if ($part !== '') {
            $searchTerms[] = '+' . $part;
        }
    }
    $searchQuery = implode(' ', $searchTerms);

    // Modify the SQL to include year filter
    $year_condition = '';
    $bind_params = 'ss';
    $bind_values = [$searchQuery, $searchQuery];
    
    if (!empty($selected_year)) {
        $year_condition = ' AND year = ?';
        $bind_params = 'sss';
        $bind_values[] = $selected_year;
    }

    $sql = "SELECT *, 
                   MATCH(title, author, abstract, keywords) AGAINST(? IN BOOLEAN MODE) AS relevance 
            FROM capstone 
            WHERE status = 'verified' 
            AND MATCH(title, author, abstract, keywords) AGAINST(? IN BOOLEAN MODE)
            $year_condition
            ORDER BY relevance DESC LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Prepare failed at " . date('Y-m-d H:i:s') . ": " . $conn->error);
        ob_end_clean();
        die("An error occurred while processing your search. Please try again.");
    }
    
    $stmt->bind_param($bind_params, ...$bind_values);
    $stmt->execute();
    $result = $stmt->get_result();
    $results = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Suggest keywords if no results
if (empty($error_message) && count($results) === 0) {
    $broad_query = implode(' ', array_map(function($term) {
        return $term[0] === '+' ? substr($term, 1) : $term;
    }, $searchTerms));
    
    $year_condition_suggestions = '';
    $bind_params_suggestions = 's';
    $bind_values_suggestions = [$broad_query];
    
    if (!empty($selected_year)) {
        $year_condition_suggestions = ' AND year = ?';
        $bind_params_suggestions = 'ss';
        $bind_values_suggestions[] = $selected_year;
    }
    
    $sql = "SELECT DISTINCT keywords 
            FROM capstone 
            WHERE status = 'verified' 
            AND MATCH(title, author, abstract, keywords) AGAINST(? IN NATURAL LANGUAGE MODE)
            $year_condition_suggestions 
            LIMIT 5";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Prepare failed for suggestions at " . date('Y-m-d H:i:s') . ": " . $conn->error);
        $error_message = "An error occurred while fetching suggestions. Please try again.";
    } else {
        $stmt->bind_param($bind_params_suggestions, ...$bind_values_suggestions);
        if ($stmt->execute()) {
            $keyword_result = $stmt->get_result();
            if ($keyword_result === false) {
                error_log("Query failed for suggestions at " . date('Y-m-d H:i:s') . ": " . $stmt->error);
                $error_message = "An error occurred while fetching suggestions. Please try again.";
            } else {
                while ($row = $keyword_result->fetch_assoc()) {
                    $suggestions = array_merge($suggestions, explode(',', $row['keywords']));
                }
                $suggestions = array_unique(array_map('trim', $suggestions));
            }
        } else {
            error_log("Execute failed for suggestions at " . date('Y-m-d H:i:s') . ": " . $stmt->error);
            $error_message = "An error occurred while fetching suggestions. Please try again.";
        }
        $stmt->close();
    }
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Results - CCS Research Repository</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <style>
        .list-group-item h5 a:hover {
            text-decoration: underline !important;
        }
    </style>
</head>
<body>
    <?php include '../assets/includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include '../assets/includes/navbar.php'; ?>

        <!-- Compact Search Bar with Filter -->
        <div class="container mt-3">
            <form action="search-result.php" method="GET" class="row g-2 mb-3">
                <div class="col-md-8">
                    <input 
                        type="text" 
                        name="query" 
                        id="search-input"
                        class="form-control" 
                        placeholder="Search research projects..." 
                        value="<?php echo htmlspecialchars($spell_corrected ? $corrected_query : $original_query); ?>"
                        required
                    >
                </div>
                <div class="col-md-2">
                    <select name="year" class="form-select">
                        <option value="">All Years</option>
                        <?php foreach ($available_years as $year): ?>
                            <option value="<?php echo htmlspecialchars($year); ?>" 
                                    <?php echo ($selected_year == $year) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
            </form>
        </div>

        <div class="container my-4">
            <?php if ($spell_corrected): ?>
                <div class="alert alert-info" role="alert" id="spellcheck-alert">
                    <span>Showing results for <a href="#" class="alert-link corrected-query" data-query="<?php echo htmlspecialchars($corrected_query); ?>"><strong><?php echo htmlspecialchars($corrected_query); ?></strong></a></span><br>
                    <small>Search instead for <a href="#" class="alert-link original-query" data-query="<?php echo htmlspecialchars($original_query); ?>"><?php echo htmlspecialchars($original_query); ?></a></small>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php elseif (count($results) > 0): ?>
                <div class="list-group">
                    <?php foreach ($results as $result): ?>
                        <div class="list-group-item mb-3 border rounded">
                            <h5 class="fw-bold mb-1">
                                <a href="view_research.php?id=<?php echo urlencode($result['id']); ?>" class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars($result['title']); ?>
                                </a>
                            </h5>
                            <p class="text-muted mb-1">
                                <strong>Author:</strong> <?php echo htmlspecialchars(parseAuthorData($result['author'])); ?> |
                                <strong>Year:</strong> <?php echo htmlspecialchars($result['year']); ?>
                            </p>
                            <p class="mb-2"><?php echo htmlspecialchars(substr($result['abstract'], 0, 200)) . (strlen($result['abstract']) > 200 ? '...' : ''); ?></p>
                            <p class="text-muted mb-2"><strong>Keywords:</strong> <?php echo htmlspecialchars($result['keywords']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    No results found for "<?php echo htmlspecialchars($spell_corrected ? $corrected_query : $query); ?>"
                    <?php if (!empty($selected_year)): ?>
                        in year <?php echo htmlspecialchars($selected_year); ?>
                    <?php endif; ?>.
                    <?php if (!empty($suggestions)): ?>
                        Try these keywords: <?php echo implode(', ', array_map('htmlspecialchars', array_slice($suggestions, 0, 5))); ?>.
                    <?php else: ?>
                        Please try different keywords or check your spelling.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle click on corrected query or original query
        document.querySelectorAll('.corrected-query, .original-query').forEach(function(element) {
            element.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove the spellcheck alert
                const alert = document.querySelector('#spellcheck-alert');
                if (alert) {
                    alert.remove();
                } else {
                    console.warn('Spellcheck alert not found.');
                }

                // Update the search input and submit the form
                const searchInput = document.querySelector('#search-input');
                if (searchInput) {
                    searchInput.value = this.dataset.query;
                    console.log('Search input updated to:', this.dataset.query);

                    const searchForm = searchInput.closest('form');
                    if (searchForm) {
                        // Add hidden input for chosen flag
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'chosen';
                        hiddenInput.value = '1';
                        searchForm.appendChild(hiddenInput);
                        
                        console.log('Submitting form with query:', this.dataset.query, 'and chosen=1');
                        searchForm.submit();
                    } else {
                        console.warn('Search form not found. Falling back to URL redirect.');
                        window.location.href = '?query=' + encodeURIComponent(this.dataset.query) + '&chosen=1';
                    }
                } else {
                    console.error('Search input with ID "search-input" not found.');
                    // Fallback to URL redirect
                    window.location.href = '?query=' + encodeURIComponent(this.dataset.query) + '&chosen=1';
                }
            });
        });
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>