<?php
// Start the session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    // If not logged in or not an student, redirect to the login page or another page
    header("Location: ../users/login.php");
    exit(); // Stop further execution
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
        .options-container {
            text-align: center;
            margin-top: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .options-container h2 {
            display: inline-flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .options-container a {
            display: inline-block;
            padding: 15px 30px;
            margin: 10px;
            background-color: #2F8D46;
            color: white;
            text-decoration: none;
            font-weight: bold;
            border-radius: 5px;
        }

        .options-container a:hover {
            background-color: green;
        }

        /* Style for the question mark */
        .question-mark {
            font-size: 15px; /* Smaller font size */
            cursor: help;
            margin-left: 10px;
            position: relative;
            width: 18px; /* Fixed width for the circle */
            height: 18px; /* Fixed height for the circle */
            line-height: 22px; /* Center the question mark vertically */
            text-align: center; /* Center the question mark horizontally */
            border: 2px solid #333; /* Circle border */
            border-radius: 50%; /* Makes it a circle */
            display: inline-flex; /* Flex to center content */
            justify-content: center;
            align-items: center;
        }

/* Tooltip style - Horizontal Rectangle */
        .question-mark .tooltip {
            display: none;
            position: absolute;
            top: 35px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: white;
            padding: 10px 20px; /* Increased padding for a wider, flatter look */
            border-radius: 8px; /* Slightly rounded corners for a modern rectangle */
            font-size: 14px;
            width: 350px; /* Fixed width for a horizontal rectangle */
            z-index: 100;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            opacity: 0;
            transition: opacity 0.2s ease-in-out;
            word-wrap: break-word; /* Wrap long words if needed */
        }

        .question-mark:hover .tooltip {
            display: block;
            opacity: 1;
        }
        /* Responsive adjustments */
@media (max-width: 400px) {
    .question-mark .tooltip {
        width: auto; /* Auto size for smaller screens */
        padding: 10px 15px;
        font-size: 13px;
        left: 50%;
        transform: translateX(-50%);
    }
}

/* Flexbox for buttons */
.buttons-container {
    display: flex;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap; /* Allow wrapping if needed */
}

/* Responsive behavior for smaller screens */
@media (max-width: 600px) {
    .buttons-container {
        flex-direction: column;
        align-items: center;
    }
}
        
    </style>
    
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">

    <?php include '../assets/includes/navbar.php'; ?>
    

    <div class="options-container">
        <h2>
            Choose an Option
            <span class="question-mark">
                ?
                <div class="tooltip">
                If you already have a research document with the correct format, choose "Submit a .pdf format Research Paper."
                <br><br>
                If the document is not yet in the proper format, choose "Automation for the Research Documentation" to automatically format and document it.
                </div>
            </span>
        </h2>
		<div class="buttons-container">
            <a href="title-defense.php?project_id=<?php echo isset($_GET['project_id']) ? $_GET['project_id'] : ''; ?>">
                Submit a .pdf format Research Paper
            </a>
			<a id="automationLink" href="research-documentation.php?project_id=<?php echo isset($_GET['project_id']) ? $_GET['project_id'] : ''; ?>">
                Automation for the Research Documentation
            </a>
        </div>
    </div>

</div>

    <!-- Disclaimer Modal -->
    <div class="modal fade" id="automationDisclaimerModal" tabindex="-1" aria-labelledby="automationDisclaimerLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="automationDisclaimerLabel">Before you continue</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="line-height:1.6;">
                    This automation is intended to help structure your research documentation, but it may not perfectly match your department's required format. Please review and fix formatting issues and content quality. Do not rely solely on this tool.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="btnUnderstand" class="btn btn-primary">I understand</button>
                </div>
            </div>
        </div>
    </div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    (function() {
        var automationLink = document.getElementById('automationLink');
        if (!automationLink) return;

        var pendingHref = automationLink.getAttribute('href');
        var modalEl = document.getElementById('automationDisclaimerModal');
        var modal = new bootstrap.Modal(modalEl);
        var btnUnderstand = document.getElementById('btnUnderstand');

        automationLink.addEventListener('click', function(e) {
            e.preventDefault();
            // Re-read href in case project_id changes dynamically
            pendingHref = automationLink.getAttribute('href');
            modal.show();
        });

        btnUnderstand.addEventListener('click', function() {
            modal.hide();
            // Slight delay to allow modal to close smoothly
            setTimeout(function() {
                window.location.href = pendingHref;
            }, 150);
        });
    })();
</script>
</body>
</html>
