<?php
// Start output buffering to prevent early output issues
ob_start();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection not required for export; using session data only

// Include required libraries (PDF only)
require '../fpdf/fpdf.php'; // FPDF

// Utility: fully clear all output buffers to avoid corrupting binary downloads
function clearAllOutputBuffers() {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

// Define research details from session
$research_title = isset($_SESSION['research_title']) ? $_SESSION['research_title'] : "N/A";
$date = isset($_SESSION['date']) ? $_SESSION['date'] : "N/A";
$members = isset($_SESSION['members']) ? implode(", ", $_SESSION['members']) : "N/A";

// Define chapters
$GLOBALS['chapters'] = [
    'CHAPTER I' => [
        'background' => 'Background of the Study',
        'problem_statement' => 'Statement of the Problem',
        'objectives' => 'Objectives of the Study',
        'significance' => 'Significance of the Study',
        'scope' => 'Scope and Delimitations',
        'definition' => 'Definition of Terms'
    ],
    'CHAPTER II' => [
        'introduction' => 'Introduction',
        'relevance' => 'Relevance of the Study'
    ],
    'CHAPTER III' => [
        'introduction_ch3' => 'Introduction',
        'research_design' => 'Research Design',
        'research_participants' => 'Research Participants',
        'research_locale' => 'Research Locale',
        'software_dev_methodology' => 'Software Development Methodology',
        'respondents' => 'Respondents of the Study',
        'research_instrument' => 'The Research Instrument',
        'data_gathering_procedures' => 'Data Gathering Procedures',
        'system_development_tools' => 'System Development Tools'
    ]
];


	// Word export implementation using PHPWord
	function exportToWord() {
		clearAllOutputBuffers();
		$debug = isset($_GET['debug']) && $_GET['debug'] == '1';
		if ($debug) {
			@ini_set('display_errors', '1');
			@error_reporting(E_ALL);
		} else {
			@ini_set('display_errors', '0');
			@error_reporting(0);
		}

		// Lazy-load PHPWord (library is at project root: PHPWord-master)
		// Require modern PHP for bundled PHPWord (uses type hints)
		if (version_compare(PHP_VERSION, '7.2', '<')) {
			clearAllOutputBuffers();
			header('Content-Type: text/plain');
			echo 'This DOCX export requires PHP 7.2+ (current: ' . PHP_VERSION . '). Please upgrade PHP in XAMPP or use a PHPWord version compatible with your PHP.';
			exit;
		}

		try {
			// Resolve PHPWord autoloader from either project root or local student folder
			$rootPhpWord = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PHPWord-master' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'PhpWord' . DIRECTORY_SEPARATOR . 'Autoloader.php';
			$localPhpWord = __DIR__ . DIRECTORY_SEPARATOR . 'PHPWord-master' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'PhpWord' . DIRECTORY_SEPARATOR . 'Autoloader.php';
			if (file_exists($rootPhpWord)) {
				require_once $rootPhpWord;
			} elseif (file_exists($localPhpWord)) {
				require_once $localPhpWord;
			} else {
				throw new \RuntimeException('PHPWord Autoloader not found at ' . $rootPhpWord . ' or ' . $localPhpWord);
			}
			\PhpOffice\PhpWord\Autoloader::register();
		} catch (\Throwable $e) {
			clearAllOutputBuffers();
			header('Content-Type: text/plain');
			echo 'Autoloader error: ' . $e->getMessage();
			exit;
		}

		global $research_title, $date, $members;
		$chapters = isset($GLOBALS['chapters']) ? $GLOBALS['chapters'] : [];

		$phpWord = new \PhpOffice\PhpWord\PhpWord();
		$phpWord->setDefaultFontName('Times New Roman');
		$phpWord->setDefaultFontSize(12);

		// Build short title (first 5 words, uppercase)
		$titleWords = preg_split('/\s+/', (string)$research_title);
		$shortTitle = strtoupper(implode(' ', array_slice($titleWords, 0, 5)) . (count($titleWords) > 5 ? '...' : ''));

		// Title page section (no header/footer)
		$titleSection = $phpWord->addSection([
			'orientation' => 'portrait',
			'pageSizeW' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(8.5),
			'pageSizeH' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(11),
			'marginLeft' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(1.5),
			'marginRight' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(1),
			'marginTop' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(1),
			'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(1)
		]);

		// Content section with header and footer
		$contentSection = $phpWord->addSection([
			'orientation' => 'portrait',
			'pageSizeW' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(8.5),
			'pageSizeH' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(11),
			'marginLeft' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(1.5),
			'marginRight' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(1),
			'marginTop' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(1),
			'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(1)
		]);

		// Header with left short title and right page number; simple line
		$header = $contentSection->addHeader();
		$headerTable = $header->addTable(['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 0]);
		$headerTable->addRow();
		$headerTable->addCell(6000)->addText($shortTitle, ['bold' => false], ['alignment' => 'left']);
		$headerTable->addCell(3000)->addPreserveText('{PAGE}', ['bold' => false], ['alignment' => 'right']);
		$header->addLine(['weight' => 1, 'width' => 431, 'height' => 0, 'color' => '000000']);

		// Footer with simple line and centered text
		$footer = $contentSection->addFooter();
		// Add top border line
        $footer->addLine(['weight' => 1, 'width' => 431, 'height' => 0, 'color' => '000000']);
		// Footer text on single line with minimal spacing
		$footer->addText('SANTA RITA COLLEGE OF PAMPANGA      COLLEGE OF COMPUTER STUDIES', ['alignment' => 'center'], ['spaceBefore' => 0, 'spaceAfter' => 0]);

		// Styles
		$paragraphCentered = ['alignment' => 'center', 'lineHeight' => 2.0]; // Double spacing for title page
		$paragraphJustified = ['alignment' => 'both', 'lineHeight' => 2.0, 'indentation' => ['firstLine' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(0.5)]];
		$paragraphHeading = ['alignment' => 'center', 'spaceBefore' => 0, 'spaceAfter' => 0, 'lineHeight' => 2.0];
		$chapterHeading = ['alignment' => 'center', 'spaceBefore' => 0, 'spaceAfter' => 0, 'lineHeight' => 2.0];
		$sectionHeading = ['alignment' => 'center', 'spaceBefore' => 0, 'spaceAfter' => 0, 'lineHeight' => 2.0];
		$titleStyle = ['alignment' => 'center', 'lineHeight' => 1.0]; // Single spacing for title only
		
		// Add paragraph style with proper indentation
		$phpWord->addParagraphStyle('indentedParagraph', [
			'alignment' => 'both',
			'lineHeight' => 2.0,
			'indentation' => ['firstLine' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(0.5)]
		]);
		
		// Add a style for regular paragraphs with indentation
		$phpWord->addParagraphStyle('paragraph', [
			'alignment' => 'both',
			'lineHeight' => 2.0,
			'indentation' => ['firstLine' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(0.5)]
		]);

		// Title page (no header/footer)
		$titleSection->addText($research_title, ['bold' => true], $titleStyle); // Bold title with single spacing
		$titleSection->addTextBreak(2); // Add space after title
		$titleSection->addText('A Capstone Project', [], $paragraphCentered);
		$titleSection->addText('Presented to the', [], $paragraphCentered);
		$titleSection->addText('Faculty of the College of Computer Studies', [], $paragraphCentered);
		$titleSection->addText('Santa Rita College of Pampanga', [], $paragraphCentered);
		$titleSection->addTextBreak(2); // Add space after presentation info
		$titleSection->addText('In Partial Fulfillment', [], $paragraphCentered);
		$titleSection->addText('of the Requirements for the Degree', [], $paragraphCentered);
		$titleSection->addText('BACHELOR OF SCIENCE IN INFORMATION SYSTEM', [], $paragraphCentered);
		$titleSection->addTextBreak(2); // Add more space before authors
		$titleSection->addText('By:', [], $paragraphCentered);
		$membersArray = array_map('trim', explode(',', (string)$members));
		foreach ($membersArray as $m) {
			if ($m !== '') {
				$titleSection->addText($m, [], $paragraphCentered);
			}
		}
        $titleSection->addTextBreak(2); // Add space after presentation info
		$titleSection->addText($date, [], $paragraphCentered);

		// Content pages per chapter (with header/footer)
		$chapterIndex = 1;
		foreach ($chapters as $chapterTitle => $fields) {
			if ($chapterIndex > 1) {
				$contentSection->addPageBreak();
			}

			$contentSection->addText(strtoupper($chapterTitle), ['bold' => true], $chapterHeading);

			if ($chapterIndex === 1) {
				$contentSection->addText('INTRODUCTION', ['bold' => true], $sectionHeading);
			}
			if ($chapterIndex === 2) {
				$contentSection->addText('REVIEW OF RELATED LITERATURE AND STUDIES', ['bold' => true], $sectionHeading);
			}
			if ($chapterIndex === 3) {
				$contentSection->addText('RESEARCH DESIGN AND METHODOLOGY', ['bold' => true], $sectionHeading);
			}

			foreach ($fields as $key => $label) {
				// Add proper spacing before section heading
				$contentSection->addText(strtoupper($label), ['bold' => true], ['alignment' => 'left', 'lineHeight' => 2.0]);
				$text = isset($_SESSION[$key]) ? (string)$_SESSION[$key] : 'N/A';
				$text = preg_replace("/[ \t]+/", ' ', $text);
				$text = preg_replace("/ *\n */", "\n", $text);
				// Clean up special characters (removed due to encoding issues)
				$paragraphs = explode("\n", $text);
				foreach ($paragraphs as $p) {
					if ($p === '') { $contentSection->addTextBreak(0); continue; }
					// Add manual indentation using spaces
					$indentedText = "             " . $p; // 8 spaces for 0.5 inch indentation
					$contentSection->addText($indentedText, [], ['alignment' => 'both', 'lineHeight' => 2.0]);
				}
			}

			$chapterIndex++;
		}

		// Preflight checks
		if (!class_exists('ZipArchive')) {
			clearAllOutputBuffers();
			header('Content-Type: text/plain');
			echo "Required PHP extension missing: ZipArchive (php_zip). Enable it in php.ini and restart Apache.";
			exit;
		}

		// Force download
		header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
		header('Content-Disposition: attachment; filename="research_document.docx"');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Expires: 0');
		header('Pragma: public');

		try {
			$writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
			$writer->save('php://output');
		} catch (\Throwable $e) {
			clearAllOutputBuffers();
			header('Content-Type: text/plain');
			echo 'Failed to generate DOCX: ' . $e->getMessage();
		}
		exit;
	}

class CustomPDF extends FPDF {
    private $research_title_short;
    private $is_title_page = false;
    
    function __construct($research_title, $orientation='P', $unit='in', $size='Letter') {
        parent::__construct($orientation, $unit, $size);
        
        // Get first 5 words of title and convert to uppercase
        $title_words = explode(' ', $research_title);
        
        // Get first 5 words
        $short_title = implode(' ', array_slice($title_words, 0, 5));
        
        // If there are more than 5 words, append '...'
        if (count($title_words) > 5) {
            $short_title .= '...';
        }
        
        // Set the short title in uppercase
        $this->research_title_short = strtoupper($short_title);
    }

    function Header() {
        // Skip header for title page (page 1) or when title page flag is set
        if ($this->is_title_page || $this->PageNo() == 1) {
            return;
        }
        
        // Set font
        $this->SetFont('Times', '', 12);
    
        // Define margins and page width
        $pageWidth = $this->GetPageWidth();
        $marginLeft = 1.5;   // Custom left margin
        $marginRight = 1;    // Custom right margin
        $contentWidth = $pageWidth - $marginLeft - $marginRight; // The width for content
    
        // Set the Y-position explicitly for all pages to ensure consistent header height
        $this->SetY(0.5); // Position 1 inch from top of the page (adjust as needed)
    
        // Set X position for the left content
        $this->SetX($marginLeft);  // Start at the custom left margin
    
        // Print the title (first 5 words) for all pages
        $this->Cell(0, 0.24, $this->research_title_short, 0, 0, 'L');
    
        // Calculate the width of the page number dynamically to properly align it to the right
        $pageNumberWidth = $this->GetStringWidth($this->PageNo());
    
        // Set X position for the right-aligned page number (adjust dynamically)
        $this->SetX($pageWidth - $marginRight - $pageNumberWidth);  // Offset by the page number width
    
        // Always print page number (on the right side)
        $this->Cell(0, 0.24, $this->PageNo(), 0, 1, 'R');
    
        // Move to the next line after the title and page number
        $this->Ln(0.10);
    
        // Underline the header (same for all pages)
        $this->SetLineWidth(0.01);
        $this->Line($marginLeft, $this->GetY(), $pageWidth - $marginRight, $this->GetY());
    
        // Move to the next line after the underline
        $this->Ln(-0.24);
        
    
        // Ensure that Y is adjusted correctly after the underline, so content starts below the header
        $this->SetY($this->GetY() + 0.24);
    }

    // Footer
    function Footer() {
        // Skip footer for title page (page 1) or when title page flag is set
        if ($this->is_title_page || $this->PageNo() == 1) {
            return;
        }
        
        $this->SetY(-0.75); // Position from bottom (within 1 inch margin)
        $this->SetFont('Times', '', 12);
        $pageWidth = $this->GetPageWidth();
        $marginLeft = 1.5;
        $marginRight = 1;
        $contentWidth = $pageWidth - $marginLeft - $marginRight;
        
        // Line above footer
        $this->SetLineWidth(0.01);
        $this->Line($marginLeft, $this->GetY(), $pageWidth - $marginRight, $this->GetY());
        $this->Ln(0.12);
        
        // Footer text (centered)
        $this->Cell($contentWidth, 0.24, 'SANTA RITA COLLEGE OF PAMPANGA	       COLLEGE OF COMPUTER STUDIES', 0, 1, 'C');
    }
    
    // Method to set title page flag
    function SetTitlePage($is_title_page) {
        $this->is_title_page = $is_title_page;
    }
}

// Word export removed

function exportToPDF() {
    clearAllOutputBuffers(); // Clean previous output to avoid header issues
    @ini_set('display_errors', '0');
    @error_reporting(0);

    global $research_title, $date, $members, $chapters;
    $pdf = new CustomPDF($research_title, 'P', 'in', 'Letter'); // Use custom class
    
    // Set title page flag for the first page
    $pdf->SetTitlePage(true);
    $pdf->AddPage();
    
    // Set margins (1 inch all sides except left which is 1.5 inch)
    $pdf->SetMargins(1.5, 1, 1);
    $pdf->SetAutoPageBreak(true, 1);

    // Title Page (all centered)
    $pdf->SetFont('Times', '', 12);
    
    // Add double spacing effect by increasing line height
    $lineHeight = 0.48; // Approximately double spacing in inches (12pt * 2)
    
    // Center the content
    $pageWidth = $pdf->GetPageWidth();
    $marginLeft = 1.5;
    $marginRight = 1;
    $contentWidth = $pageWidth - $marginLeft - $marginRight;
    
    // Add spacing to account for where header would normally be (approximately 1.5 inches from top)
    $pdf->SetY(1.5);
    
    // Research Title - Bold with single line spacing
    $pdf->SetFont('Times', 'B', 12); // Bold font
    $pdf->MultiCell($contentWidth, 0.24, $research_title, 0, 'C'); // Single line spacing (0.24 inches)
    $pdf->Ln(0.24); // Single line spacing after title
    
    // Reset to regular font for rest of title page
    $pdf->SetFont('Times', '', 12);
    
    $pdf->Cell($contentWidth, $lineHeight, 'A Capstone Project', 0, 1, 'C');
    $pdf->Cell($contentWidth, $lineHeight, 'Presented to the', 0, 1, 'C');
    $pdf->Cell($contentWidth, $lineHeight, 'Faculty of the College of Computer Studies', 0, 1, 'C');
    $pdf->Cell($contentWidth, $lineHeight, 'Santa Rita College of Pampanga', 0, 1, 'C');
    $pdf->Ln($lineHeight);
    
    $pdf->Cell($contentWidth, $lineHeight, 'In Partial Fulfillment', 0, 1, 'C');
    $pdf->Cell($contentWidth, $lineHeight, 'of the Requirements for the Degree', 0, 1, 'C');
    $pdf->Cell($contentWidth, $lineHeight, 'BACHELOR OF SCIENCE IN INFORMATION SYSTEM', 0, 1, 'C');
    $pdf->Ln($lineHeight);
    
    $pdf->Cell($contentWidth, $lineHeight, "By:", 0, 1, 'C');
    
    // Split members by comma and display each on a new line
    $members_array = explode(',', $members);
    foreach ($members_array as $member) {
        $pdf->Cell($contentWidth, $lineHeight, trim($member), 0, 1, 'C');

    }
    $pdf->Ln($lineHeight);
    $pdf->Cell($contentWidth, $lineHeight, $date, 0, 1, 'C');

    // Start new page for content after title page
    $pdf->SetTitlePage(false); // Reset title page flag for content pages
    $pdf->AddPage();

    // Use core Times font only to avoid custom font loading issues
    $pdf->SetFont('Times', '', 12);
    $pdf->Ln(0); // Add some initial spacing from top
    // Content pages
    $chapterCount = 1;
    foreach ($chapters as $chapter_title => $fields) {
        // Add a new page for each chapter except the first one
        if ($chapterCount > 1) {
            $pdf->AddPage();
            $pdf->Ln(0); // Add some initial spacing from top
        }

        // CHAPTER Title (Always centered)
        $pdf->SetFont('Times', 'B', 12);
        $pdf->Cell(0, $lineHeight, strtoupper($chapter_title), 0, 1, 'C');
        $pdf->Ln(-0.1); // Add some initial spacing from top

        // INTRODUCTION only for CHAPTER I
        if ($chapterCount == 1) {
            $pdf->SetFont('Times', 'B', 12);
            $pdf->Cell(0, $lineHeight, 'INTRODUCTION', 0, 1, 'C');
            $pdf->Ln(0);
        }

        // REVIEW OF RELATED LITERATURE AND STUDIES only for CHAPTER II
        if ($chapterCount == 2) {
            $pdf->SetFont('Times', 'B', 12);
            $pdf->Cell(0, $lineHeight, 'REVIEW OF RELATED LITERATURE AND STUDIES', 0, 1, 'C');
            $pdf->Ln(0);
        }

        // RESEARCH DESIGN AND METHODOLOGY only for CHAPTER III
        if ($chapterCount == 3) {
            $pdf->SetFont('Times', 'B', 12);
            $pdf->Cell(0, $lineHeight, 'RESEARCH DESIGN AND METHODOLOGY', 0, 1, 'C');
            $pdf->Ln(0);
        }

        // Inside exportToPDF function, within the foreach loop for $chapters
        foreach ($fields as $key => $label) {
            $pdf->SetFont('Times', 'B', 12);
            $pdf->Cell(0, $lineHeight, strtoupper($label), 0, 1);
            $pdf->SetFont('Times', '', 12);
            $text = isset($_SESSION[$key]) ? $_SESSION[$key] : "N/A";
            $text = preg_replace("/[ \t]+/", " ", $text);
            $text = preg_replace("/ *\n */", "\n", $text);
            // Clean up special characters (removed due to encoding issues)
            $text = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $text);
            $paragraphs = explode("\n", $text);
            foreach ($paragraphs as $index => $paragraph) {
                if (trim($paragraph) !== '') {
                    // Add proper indentation for first line of each paragraph
                    $pdf->SetX(1.5); // Reset to left margin
                    // Add indentation by using spaces (0.5 inch = approximately 6 spaces at 12pt font)
                    $indentedText = "      " . $paragraph; // 6 spaces for 0.5 inch indentation
                    $pdf->MultiCell(0, $lineHeight, $indentedText);
                } else {
                    $pdf->Ln($lineHeight);
                }
            }
        }
        $chapterCount++;
    }


    // Set headers to force download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="research_document.pdf"');
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Expires: 0");
    header("Pragma: public");

    // Output to browser
    $pdf->Output('D', 'research_document.pdf');
    exit;
}

// ✅ Handle export request
if (isset($_GET['export'])) {
    if ($_GET['export'] == 'pdf') {
        exportToPDF();
		} elseif ($_GET['export'] == 'word') {
			exportToWord();
    }
}
