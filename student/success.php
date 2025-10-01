<?php
// Start the session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    // If not logged in or not an student, redirect to the login page or another page
    header("Location: ../users/login.php");
    exit(); // Stop further execution
}

// Check if project_id is set
if (!isset($_GET['project_id'])) {
    header("Location: submit_research.php?error=no_project_found");
    exit;
}
$projectId = $_GET['project_id'];

// Function to check if any research data exists in session (copied from original)
function hasResearchData() {
    $researchFields = [
        'research_title', 'date', 'members', 'background', 'problem_statement',
        'objectives', 'significance', 'scope', 'definition', 'conceptual_text',
        'conceptual_image', 'introduction', 'relevance', 'introduction_ch3',
        'research_design', 'research_participants', 'research_locale',
        'software_dev_methodology', 'respondents', 'research_instrument',
        'data_gathering_procedures', 'system_development_tools'
    ];
    
    foreach ($researchFields as $field) {
        if (isset($_SESSION[$field]) && !empty($_SESSION[$field])) {
            return true;
        }
    }
    return false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Captrack Vault Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <style>
        a.btn {
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px;
            display: inline-block;
            text-decoration: none;
        }
        a.btn:hover {
            background-color: #0056b3;
        }
        /* Looks disabled but keeps hover events so the title tooltip appears */
        a.disabled-note {
            opacity: .65;
            cursor: not-allowed;
        }
        /* Responsive layout for success page */
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        @media (max-width: 576px) {
            .form-container {
                padding: 16px;
            }
            p.success {
                font-size: 16px;
            }
            a.btn {
                width: 100%;
                margin: 6px 0;
                font-size: 14px;
                padding: 10px;
                text-align: center;
            }
        }
        p.success {
            color: green;
            font-size: 18px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">

    <?php include '../assets/includes/navbar.php'; ?>
    <div class="form-container">
        <p class="success">Data saved successfully!</p>
        
        <div class="actions">
            <?php if (hasResearchData()): ?>
                <a href="#" class="btn disabled-note" title="Under maintenance use Export to PDF instead" onclick="return false;">Export to Word</a>
                <a href="export-documentation.php?export=pdf" class="btn">Export to PDF</a>
            <?php endif; ?>
            <a href="research-documentation.php?project_id=<?php echo $projectId; ?>" class="btn">Back to Edit</a>
            <a href="title-defense.php?project_id=<?php echo isset($_GET['project_id']) ? $_GET['project_id'] : ''; ?>" class="btn">Next</a>
        </div>
    </div>

</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
</body>
</html>
