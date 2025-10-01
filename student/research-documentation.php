<?php
// Start the session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    // If not logged in or not an student, redirect to the login page or another page
    header("Location: ../users/login.php");
    exit(); // Stop further execution
}

// Set default current_step if not set
if (!isset($_SESSION['current_step'])) {
    $_SESSION['current_step'] = 1;
}

include '../config/database.php';

// Check if project_id is set in the URL
if (!isset($_GET['project_id'])) {
    header("Location: submit_research.php?error=no_project_found");
    exit;
}
$projectId = $_GET['project_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save the current step
    $_SESSION['current_step'] = isset($_POST['current_step']) ? $_POST['current_step'] : 1;

    // Title Page Data
    $_SESSION['research_title'] = $_POST['research_title'];
    $_SESSION['date'] = $_POST['date'];
    $_SESSION['members'] = array_filter([
        $_POST['member_1'],
        $_POST['member_2'],
        $_POST['member_3'],
        $_POST['member_4'],
    ]);

    // Chapter 1 Data
    $_SESSION['background'] = $_POST['background'];
    $_SESSION['problem_statement'] = $_POST['problem_statement'];
    $_SESSION['objectives'] = $_POST['objectives'];
    $_SESSION['significance'] = $_POST['significance'];
    $_SESSION['scope'] = $_POST['scope'];
    $_SESSION['definition'] = $_POST['definition'];

    // Conceptual Framework removed

    // Chapter 2 Data
    $_SESSION['introduction'] = $_POST['introduction'];
    $_SESSION['relevance'] = $_POST['relevance'];

    // Chapter 3 Data
    $_SESSION['introduction_ch3'] = $_POST['introduction_ch3'];
    $_SESSION['research_design'] = $_POST['research_design'];
    $_SESSION['research_participants'] = $_POST['research_participants'];
    $_SESSION['research_locale'] = $_POST['research_locale'];
    $_SESSION['software_dev_methodology'] = $_POST['software_dev_methodology'];
    $_SESSION['respondents'] = $_POST['respondents'];
    $_SESSION['research_instrument'] = $_POST['research_instrument'];
    $_SESSION['data_gathering_procedures'] = $_POST['data_gathering_procedures'];
    $_SESSION['system_development_tools'] = $_POST['system_development_tools'];

    // Redirect to stay on the same step with a success message
    header("Location: success.php?project_id=$projectId");
    exit;
}

// Function to check if any research data exists in session
function hasResearchData() {
    $researchFields = [
        'research_title', 'date', 'members', 'background', 'problem_statement',
        'objectives', 'significance', 'scope', 'definition',
        'introduction', 'relevance', 'introduction_ch3',
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
        h2, h3 {
            text-align: center;
        }
        .form-step {
            display: none;
        }
        .form-step.active {
            display: block;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button.button-doc {
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        button.button-doc:hover {
            background-color: #0056b3;
        }
        .btn-container {
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>

<?php include '../assets/includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">

    <?php include '../assets/includes/navbar.php'; ?>

        <div class="form-container">
        <h2>Research Documentation</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="current_step" id="current_step" value="<?php echo isset($_SESSION['current_step']) ? $_SESSION['current_step'] : 1; ?>">

			<!-- Title Page -->
            <div class="form-step <?php echo (isset($_SESSION['current_step']) && $_SESSION['current_step'] == 1) ? 'active' : ''; ?>" id="step-1">
                <h3>Title Page</h3>
				<div class="form-group">
					<div class="input-container">
						<input type="text" name="research_title" class="form-control" required value="<?php echo htmlspecialchars(isset($_SESSION['research_title']) ? $_SESSION['research_title'] : ''); ?>">
						<label>Research Title</label>
					</div>
				</div>
				<div class="form-group">
					<div class="input-container">
						<input type="text" name="date" class="form-control" required value="<?php echo htmlspecialchars(isset($_SESSION['date']) ? $_SESSION['date'] : ''); ?>">
						<label>Date (e.g. March 2025)</label>
					</div>
				</div>
				<div class="form-group">
					<div class="input-container">
						<input type="text" name="member_1" class="form-control" placeholder="Member 1" value="<?php echo htmlspecialchars(isset($_SESSION['members'][0]) ? $_SESSION['members'][0] : ''); ?>">
						<label>Member 1</label>
					</div>
					<div class="input-container">
						<input type="text" name="member_2" class="form-control" placeholder="Member 2" value="<?php echo htmlspecialchars(isset($_SESSION['members'][1]) ? $_SESSION['members'][1] : ''); ?>">
						<label>Member 2</label>
					</div>
					<div class="input-container">
						<input type="text" name="member_3" class="form-control" placeholder="Member 3" value="<?php echo htmlspecialchars(isset($_SESSION['members'][2]) ? $_SESSION['members'][2] : ''); ?>">
						<label>Member 3</label>
					</div>
					<div class="input-container">
						<input type="text" name="member_4" class="form-control" placeholder="Member 4" value="<?php echo htmlspecialchars(isset($_SESSION['members'][3]) ? $_SESSION['members'][3] : ''); ?>">
						<label>Member 4</label>
					</div>
				</div>
				<div class="btn-container">
				<button type="button" class="button-doc" onclick="window.location.href='research-file.php?project_id=<?php echo htmlspecialchars($projectId); ?>'">Back</button>
                <button type="button" class="button-doc" onclick="nextStep(2)">Next</button>
            </div>
			</div>
            
			<!-- Chapter 1 -->
            <div class="form-step <?php echo (isset($_SESSION['current_step']) && $_SESSION['current_step'] == 2) ? 'active' : ''; ?>" id="step-2">
                <h3>Chapter 1</h3>
				<div class="form-group">
					<div class="input-container">
						<textarea name="background" class="form-control" placeholder="Background"><?php echo htmlspecialchars(isset($_SESSION['background']) ? $_SESSION['background'] : ''); ?></textarea>
						<label>Background</label>
					</div>
				</div>
				<div class="form-group">
					<div class="input-container">
						<textarea name="problem_statement" class="form-control" placeholder="Problem Statement"><?php echo htmlspecialchars(isset($_SESSION['problem_statement']) ? $_SESSION['problem_statement'] : ''); ?></textarea>
						<label>Problem Statement</label>
					</div>
				</div>
				<div class="form-group">
					<div class="input-container">
						<textarea name="objectives" class="form-control" placeholder="Objectives"><?php echo htmlspecialchars(isset($_SESSION['objectives']) ? $_SESSION['objectives'] : ''); ?></textarea>
						<label>Objectives</label>
					</div>
				</div>
				<div class="form-group">
					<div class="input-container">
						<textarea name="significance" class="form-control" placeholder="Significance"><?php echo htmlspecialchars(isset($_SESSION['significance']) ? $_SESSION['significance'] : ''); ?></textarea>
						<label>Significance</label>
					</div>
				</div>
				<div class="form-group">
					<div class="input-container">
						<textarea name="scope" class="form-control" placeholder="Scope"><?php echo htmlspecialchars(isset($_SESSION['scope']) ? $_SESSION['scope'] : ''); ?></textarea>
						<label>Scope</label>
					</div>
				</div>
				<div class="form-group">
					<div class="input-container">
						<textarea name="definition" class="form-control" placeholder="Definition of Terms"><?php echo htmlspecialchars(isset($_SESSION['definition']) ? $_SESSION['definition'] : ''); ?></textarea>
						<label>Definition of Terms</label>
					</div>
				</div>
				
				<div class="btn-container">
                    <button type="button" class="button-doc" onclick="prevStep(1)">Previous</button>
                    <button type="button" class="button-doc" onclick="nextStep(3)">Next</button>
                </div>
            </div>
            
			<!-- Chapter 2 -->
            <div class="form-step <?php echo (isset($_SESSION['current_step']) && $_SESSION['current_step'] == 3) ? 'active' : ''; ?>" id="step-3">
                <h3>Chapter 2</h3>
				<div class="form-group">
					<div class="input-container">
						<textarea name="introduction" class="form-control" placeholder="Introduction"><?php echo htmlspecialchars(isset($_SESSION['introduction']) ? $_SESSION['introduction'] : ''); ?></textarea>
						<label>Introduction</label>
					</div>
				</div>
				<div class="form-group">
					<div class="input-container">
						<textarea name="relevance" class="form-control" placeholder="Relevance"><?php echo htmlspecialchars(isset($_SESSION['relevance']) ? $_SESSION['relevance'] : ''); ?></textarea>
						<label>Relevance</label>
					</div>
				</div>
                <div class="btn-container">
                    <button type="button" class="button-doc" onclick="prevStep(2)">Previous</button>
                    <button type="button" class="button-doc" onclick="nextStep(4)">Next</button>
                </div>
            </div>
            
			<!-- Chapter 3 -->
            <div class="form-step <?php echo (isset($_SESSION['current_step']) && $_SESSION['current_step'] == 4) ? 'active' : ''; ?>" id="step-4">
                <h3>Chapter 3</h3>
				<div class="form-group">
					<div class="input-container">
						<textarea name="introduction_ch3" class="form-control" placeholder="Introduction"><?php echo htmlspecialchars(isset($_SESSION['introduction_ch3']) ? $_SESSION['introduction_ch3'] : ''); ?></textarea>
						<label>Introduction</label>
					</div>
				</div>
				<div class="form-group">
					<div class="input-container">
						<textarea name="research_design" class="form-control" placeholder="Research Design"><?php echo htmlspecialchars(isset($_SESSION['research_design']) ? $_SESSION['research_design'] : ''); ?></textarea>
						<label>Research Design</label>
					</div>
				</div>
				<div class="form-group">
					<div class="input-container">
						<textarea name="research_participants" class="form-control" placeholder="Research Participants"><?php echo htmlspecialchars(isset($_SESSION['research_participants']) ? $_SESSION['research_participants'] : ''); ?></textarea>
						<label>Research Participants</label>
					</div>
				</div>
				<div class="form-group">
					<div class="input-container">
						<textarea name="research_locale" class="form-control" placeholder="Research Locale"><?php echo htmlspecialchars(isset($_SESSION['research_locale']) ? $_SESSION['research_locale'] : ''); ?></textarea>
						<label>Research Locale</label>
					</div>
				</div>
				<div class="form-group">
					<div class="input-container">
						<textarea name="software_dev_methodology" class="form-control" placeholder="Software Development Methodology"><?php echo htmlspecialchars(isset($_SESSION['software_dev_methodology']) ? $_SESSION['software_dev_methodology'] : ''); ?></textarea>
						<label>Software Development Methodology</label>
					</div>
				</div>
				<div class="form-group">
					<div class="input-container">
						<textarea name="respondents" class="form-control" placeholder="Respondents"><?php echo htmlspecialchars(isset($_SESSION['respondents']) ? $_SESSION['respondents'] : ''); ?></textarea>
						<label>Respondents</label>
					</div>
				</div>
				<div class="form-group">
					<div class="input-container">
						<textarea name="research_instrument" class="form-control" placeholder="Research Instrument"><?php echo htmlspecialchars(isset($_SESSION['research_instrument']) ? $_SESSION['research_instrument'] : ''); ?></textarea>
						<label>Research Instrument</label>
					</div>
				</div>
				<div class="form-group">
					<div class="input-container">
						<textarea name="data_gathering_procedures" class="form-control" placeholder="Data Gathering Procedures"><?php echo htmlspecialchars(isset($_SESSION['data_gathering_procedures']) ? $_SESSION['data_gathering_procedures'] : ''); ?></textarea>
						<label>Data Gathering Procedures</label>
					</div>
				</div>
				<div class="form-group">
					<div class="input-container">
						<textarea name="system_development_tools" class="form-control" placeholder="System Development Tools"><?php echo htmlspecialchars(isset($_SESSION['system_development_tools']) ? $_SESSION['system_development_tools'] : ''); ?></textarea>
						<label>System Development Tools</label>
					</div>
				</div>
                <div class="btn-container">
                    <button type="button" class="button-doc" onclick="prevStep(3)">Previous</button>
                    <button type="submit" class="button-doc">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Scripts -->
<script>
		function nextStep(step) {
			document.querySelector('.form-step.active').classList.remove('active');
			document.getElementById('step-' + step).classList.add('active');
			document.getElementById('current_step').value = step;
		}
		function prevStep(step) {
			document.querySelector('.form-step.active').classList.remove('active');
			document.getElementById('step-' + step).classList.add('active');
			document.getElementById('current_step').value = step;
		}

		// Floating label behavior for inputs and textareas
		document.addEventListener('DOMContentLoaded', function() {
			const fields = document.querySelectorAll('input[type="text"], textarea, select');
			fields.forEach(function(field) {
				// Initialize state on load
				updateHasContent(field);
				// Update on input/change/blur
				field.addEventListener('input', function() { updateHasContent(this); });
				field.addEventListener('change', function() { updateHasContent(this); });
				field.addEventListener('blur', function() { updateHasContent(this); });
			});
		});

		function updateHasContent(el) {
			if (el.type === 'file') return;
			if (el.value && el.value.trim() !== '') {
				el.classList.add('has-content');
			} else {
				el.classList.remove('has-content');
			}
		}
	</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
</body>
</html>
