<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include '../config/database.php';

// Include FPDF library
require '../fpdf/fpdf.php';

// Check if the user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: ../users/login.php");
    exit();
}

// Get the project ID from the URL
if (!isset($_GET['project_id'])) {
    echo "Project ID is required.";
    exit();
}
$projectId = $_GET['project_id'];

// Fetch the project details
$query = "SELECT pw.*, pa.faculty_approval, pa.adviser_approval, pa.dean_approval 
          FROM project_working_titles pw 
          LEFT JOIN project_approvals pa ON pw.id = pa.project_id 
          WHERE pw.id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("SQL Error: " . $conn->error);
}

$stmt->bind_param("i", $projectId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("No project found for ID: " . htmlspecialchars($projectId));
}

$project = $result->fetch_assoc();
$stmt->close();
$conn->close();


$gender = isset($project['gender']) ? $project['gender'] : null;
$titlePrefix = "Mr./Ms."; // Default

if ($gender === 'male') {
    $titlePrefix = "Mr.";
} elseif ($gender === 'female') {
    $titlePrefix = "Ms.";
}

// Extract data for placeholders
$dateApproved = date("F d, Y"); // Gets the current date
$focalPerson = isset($project['focal_person']) ? $project['focal_person'] : 'Focal Person, Not Set';
$projectTitle = isset($project['project_title']) ? $project['project_title'] : 'No Title Provided';
$proponents = array_filter([
    $project['proponent_1'],
    $project['proponent_2'],
    $project['proponent_3'],
    $project['proponent_4']
]);

// Extract the last name dynamically from the `focal_person` field
$focalLastName = "N/A";
if (strpos($focalPerson, ',') !== false) {
    $parts = explode(',', $focalPerson);
    $focalLastName = trim($parts[0]); // Extract Last Name
}

// Create a new FPDF instance
$pdf = new FPDF('P', 'mm', 'A4'); // 'P' for Portrait, 'mm' for millimeters, 'A4' for paper size

// Configure for edge-to-edge header/footer and single page
$pdf->SetAutoPageBreak(false);
$pdf->SetMargins(0, 0, 0);
$pdf->AddPage();

// Page dimensions
$pageWidth = 210;   // A4 width in mm
$pageHeight = 297;  // A4 height in mm

// Header/Footer assets and heights (tweak heights if needed)
$headerPath = '../assets/img/header.png';
$footerPath = '../assets/img/footer.png';
$headerHeight = 40; // mm
$footerHeight = 25; // mm

// Draw header full width at top with no margin
if (file_exists($headerPath)) {
	$pdf->Image($headerPath, 0, 0, $pageWidth, $headerHeight);
}

// Draw footer full width at bottom with no margin
if (file_exists($footerPath)) {
	$pdf->Image($footerPath, 0, $pageHeight - $footerHeight, $pageWidth, $footerHeight);
}

// Define content area between header and footer
$contentLeft = 20;   // inner left padding for content
$contentRight = 20;  // inner right padding for content
$contentTop = $headerHeight + 6;  // space below header for content
$contentBottom = $pageHeight - $footerHeight - 6; // space above footer

// Set font and margins constrained to the content area
$pdf->SetFont('Times', '', 12);
$pdf->SetLeftMargin($contentLeft);
$pdf->SetRightMargin($contentRight);

// Start content at top-left of content area
$pdf->SetXY($contentLeft, $contentTop);

// Add the date
$pdf->Cell(0, 6, $dateApproved, 0, 1, 'L');
$pdf->Ln(2);

// Add the Dean's Name and Address
$pdf->Cell(0, 5, "Dr. Fernand T. Layug", 0, 1, 'L');
$pdf->Cell(0, 6, "Dean, College of Computer Studies", 0, 1, 'L');
$pdf->Cell(0, 5, "Santa Rita College of Pampanga", 0, 1, 'L');
$pdf->Ln(6);

// Add Salutation
$pdf->Cell(0, 6, "Dear " . $titlePrefix . " " . $focalLastName . ",", 0, 1, 'L');
$pdf->Ln(2);

// Ensure we don't overflow into footer
if ($pdf->GetY() > $contentBottom) {
	$pdf->SetY($contentBottom);
}

// Add Introduction Paragraphs
$pdf->MultiCell(0, 5, "Greetings from the College of Computer Studies!\n\n"
	. "Innovation is one of the top priorities of companies to keep abreast of recent trends in the industry, "
	. "particularly in the field of Information Technology (IT). Organizations and industries are advancing "
	. "their services and procedures with the aid of computer technology to enhance customer relations and "
	. "to facilitate sound decision-making.\n\n"
	. "As a requirement for the degree Bachelor of Science in Information Systems, the BSIS 3rd Year students "
	. "are required to undergo training to come up with software innovations and IT solutions to improve transactional procedures. "
	. "Moreover, the students are required to present a capstone project that is expected to be a substantial piece of work "
	. "where its completion will demonstrate the competency, problem-solving, and critical thinking skills of the students.");
$pdf->Ln(2);

// Add Project Title
$pdf->MultiCell(0, 5, "Proposed Study: " . $projectTitle, 0, 'J');
$pdf->Ln(2);

// Add Proponents Section
$pdf->Cell(0, 5, "In view thereof, may we request your kind approval to allow the following students:", 0, 1, 'L');
$pdf->Ln(2);
if (count($proponents) > 0) {
	foreach ($proponents as $index => $proponent) {
		$pdf->Cell(0, 5, ($index + 1) . ". " . $proponent, 0, 1, 'C');
	}
} else {
	$pdf->Cell(0, 8, "No proponents listed.", 0, 1, 'C');
}
$pdf->Ln(2);

// Add Closing Paragraph
$pdf->MultiCell(0, 5, "to conduct interviews with key personnel and to gather pertinent records pertaining to this matter. "
	. "Rest assured that the collected information will be treated with the utmost confidentiality.\n\n"
	. "With immense appreciation, we thank you for your support in this meaningful endeavor.");
$pdf->Ln(6);

// Add Respectfully Yours Section
$pdf->Cell(0, 6, "Respectfully yours,", 0, 1, 'L');
$pdf->Ln(10);

// Add Dean's Signature
$pdf->Cell(0, 0, "___________________", 0, 1, 'L');
$pdf->SetFont('Times', 'B', 12);
$pdf->Cell(0, 8, "Dr. Fernand T. Layug", 0, 1, 'L');
$pdf->SetFont('Times', 'I', 12);
$pdf->Cell(0, 0, "Dean, College of Computer Studies", 0, 1, 'L');

// Ensure footer is not overlapped by content
if ($pdf->GetY() > ($contentBottom - 2)) {
	$pdf->SetY($contentBottom - 2);
}

// Output the PDF
$pdf->Output('D', 'Research_Project_Letter.pdf'); // 'D' forces download
?>