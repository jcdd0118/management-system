<?php
// Start the session
session_start();
date_default_timezone_set('Asia/Manila');
// Check if the user is logged in

require_once '../assets/includes/role_functions.php';
// Authorize as admin using multi-role support
if (!isset($_SESSION['user_data']) || !hasRole($_SESSION['user_data'], 'admin')) {
    header("Location: ../users/login.php?error=unauthorized_access");
    exit();
}

// Database connection
include '../config/database.php';
require_once '../assets/includes/year_section_functions.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if connection is established
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch student data from the students table
$studentData = [
    'verified' => 0,
    'nonverified' => 0,
    'total' => 0
];

// Query for verified students
$verifiedResult = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'verified'");
if ($verifiedResult === false) {
    error_log("Verified students query failed: " . $conn->error);
} elseif ($verifiedResult->num_rows > 0) {
    $studentData['verified'] = $verifiedResult->fetch_assoc()['count'];
}

// Query for nonverified students with debugging
$unverifiedResult = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'nonverified'");
if ($unverifiedResult === false) {
    error_log("Nonverified students query failed: " . $conn->error);
} elseif ($unverifiedResult->num_rows > 0) {
    $studentData['nonverified'] = $unverifiedResult->fetch_assoc()['count'];
} else {
    // Debug: Check actual status values in students table
    $debugResult = $conn->query("SELECT status, COUNT(*) as count FROM students GROUP BY status");
    if ($debugResult) {
        $statusCounts = [];
        while ($row = $debugResult->fetch_assoc()) {
            $statusCounts[$row['status']] = $row['count'];
        }
        error_log("Status counts in students table: " . json_encode($statusCounts));
    } else {
        error_log("Debug query for students failed: " . $conn->error);
    }
}

$studentData['total'] = $studentData['verified'] + $studentData['nonverified'];

// Fetch study data from the capstone table
$studyData = [
    'verified' => 0,
    'nonverified' => 0,
    'total' => 0
];

// Query for verified studies
$verifiedStudyResult = $conn->query("SELECT COUNT(*) as count FROM capstone WHERE status = 'verified'");
if ($verifiedStudyResult === false) {
    error_log("Verified studies query failed: " . $conn->error);
} elseif ($verifiedStudyResult->num_rows > 0) {
    $studyData['verified'] = $verifiedStudyResult->fetch_assoc()['count'];
}

// Query for nonverified studies
$unverifiedStudyResult = $conn->query("SELECT COUNT(*) as count FROM capstone WHERE status = 'nonverified'");
if ($unverifiedStudyResult === false) {
    error_log("Nonverified studies query failed: " . $conn->error);
} elseif ($unverifiedStudyResult->num_rows > 0) {
    $studyData['nonverified'] = $unverifiedStudyResult->fetch_assoc()['count'];
} else {
    // Debug: Check actual status values in capstone table
    $debugResult = $conn->query("SELECT status, COUNT(*) as count FROM capstone GROUP BY status");
    if ($debugResult) {
        $statusCounts = [];
        while ($row = $debugResult->fetch_assoc()) {
            $statusCounts[$row['status']] = $row['count'];
        }
        error_log("Status counts in capstone table: " . json_encode($statusCounts));
    } else {
        error_log("Debug query for capstone failed: " . $conn->error);
    }
}

$studyData['total'] = $studyData['verified'] + $studyData['nonverified'];

// Fetch year-wise data for verified students only
$yearData = [
    '3rd_year' => 0,
    '4th_year' => 0
];

// Query to count verified students by year, using dynamic year sections
$yearSections = getActiveYearSections($conn);
$yearSectionCodes = array_column($yearSections, 'year_section');

if (!empty($yearSectionCodes)) {
	$yearSectionPlaceholders = implode(',', array_fill(0, count($yearSectionCodes), '?'));

	$yearStmt = $conn->prepare("SELECT LEFT(year_section, 1) as year, COUNT(*) as count 
	                              FROM students 
	                              WHERE status = 'verified' 
	                              AND year_section IN ({$yearSectionPlaceholders}) 
	                              GROUP BY LEFT(year_section, 1)");
	if ($yearStmt) {
		$types = str_repeat('s', count($yearSectionCodes));
		$yearStmt->bind_param($types, ...$yearSectionCodes);
		$yearStmt->execute();
		$yearResult = $yearStmt->get_result();
		if ($yearResult !== false) {
			while ($row = $yearResult->fetch_assoc()) {
				$yearKey = $row['year'] . 'rd_year';
				if ($row['year'] == '4') {
					$yearKey = '4th_year';
				}
				$yearData[$yearKey] = $row['count'];
			}
		} else {
			error_log("Year-wise verified students get_result failed: " . $conn->error);
		}
		$yearStmt->close();
	} else {
		error_log("Year-wise verified students prepare failed: " . $conn->error);
	}

	// Debug: Log year_section counts
	$debugYearResult = $conn->query("SELECT year_section, COUNT(*) as count 
	                                    FROM students 
	                                    WHERE status = 'verified' 
	                                    GROUP BY year_section");
	if ($debugYearResult) {
		$yearCounts = [];
		while ($row = $debugYearResult->fetch_assoc()) {
			$yearCounts[$row['year_section']] = $row['count'];
		}
		error_log("Year-section counts for verified students: " . json_encode($yearCounts));
	} else {
		error_log("Debug year-section query failed: " . $conn->error);
	}
} else {
	// No active year sections configured; keep defaults at 0 to avoid str_repeat error
}

// Close the database connection
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin | Captrack Vault</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }
        .stat-card.verified {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .stat-card.unverified {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
        }
        .stat-card.studies {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .stat-card-link:hover {
            text-decoration: none;
            color: inherit;
        }
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            height: 400px;
        }
        .filter-btn {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
        }
        .filter-btn.active, .filter-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .activity-item {
            padding: 1rem;
            border-left: 4px solid #667eea;
            background: #f8f9fa;
            margin-bottom: 0.5rem;
            border-radius: 0 8px 8px 0;
        }
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        /* Responsive Styles */
        @media (max-width: 768px) {
            .page-header {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            .page-header .d-flex {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 1rem;
            }
            .page-header h2 {
                font-size: 1.5rem;
            }
            .filter-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.875rem;
            }
            .stat-card {
                padding: 1rem;
                min-height: 120px;
            }
            .stat-card h3 {
                font-size: 1.5rem;
            }
            .stat-card::before {
                width: 60px;
                height: 60px;
                transform: translate(20px, -20px);
            }
            .chart-container {
                height: 300px;
                padding: 1rem;
            }
            .chart-container h5 {
                font-size: 1rem;
                margin-bottom: 1rem !important;
            }
            .activity-item {
                padding: 0.75rem;
            }
        }
        
        @media (max-width: 576px) {
            .page-header {
                padding: 0.75rem;
            }
            .page-header h2 {
                font-size: 1.25rem;
            }
            .page-header p {
                font-size: 0.875rem;
            }
            .d-flex.gap-2 {
                flex-wrap: wrap;
                gap: 0.5rem !important;
            }
            .filter-btn {
                font-size: 0.75rem;
                padding: 0.35rem 0.7rem;
            }
            .stat-card {
                padding: 0.75rem;
                min-height: 100px;
            }
            .stat-card h3 {
                font-size: 1.25rem;
            }
            .stat-card p {
                font-size: 0.875rem;
            }
            .stat-card small {
                font-size: 0.75rem;
            }
            .stat-card i.fs-1 {
                font-size: 1.5rem !important;
            }
            .chart-container {
                height: 280px;
                padding: 0.75rem;
            }
            .chart-container h5 {
                font-size: 0.9rem;
            }
            .activity-item {
                padding: 0.5rem;
            }
            .activity-item p {
                font-size: 0.875rem;
            }
        }
        
        @media (min-width: 992px) {
            .chart-container {
                height: 450px;
            }
        }
        
        @media (min-width: 1200px) {
            .chart-container {
                height: 500px;
            }
        }
        
        /* Additional responsive utilities */
        .text-responsive {
            font-size: clamp(0.875rem, 2vw, 1rem);
        }
        
        .card-responsive {
            min-height: clamp(100px, 15vw, 140px);
        }
        
        /* Ensure charts are responsive */
        canvas {
            max-width: 100% !important;
            height: auto !important;
        }
        
        /* Responsive grid adjustments */
        @media (max-width: 991px) {
            .col-lg-8, .col-lg-4 {
                margin-bottom: 1rem;
            }
        }
        
        @media (max-width: 767px) {
            .col-md-6 {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../assets/includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <?php include '../assets/includes/navbar.php'; ?>
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-2"><i class="bi bi-graph-up text-primary"></i> Analytics Dashboard</h2>
                    <p class="text-muted mb-0">Comprehensive overview of research verification status and student activities</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary filter-btn active" data-filter="all">
                        <i class="bi bi-grid"></i> All Data
                    </button>
                    <button class="btn btn-outline-primary filter-btn" data-filter="students">
                        <i class="bi bi-people"></i> Students
                    </button>
                    <button class="btn btn-outline-primary filter-btn" data-filter="studies">
                        <i class="bi bi-book"></i> Studies
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4" id="stats-row">
            <div class="col-lg-3 col-md-6 mb-4 student-card">
                <a href="student_list.php?status=verified" class="stat-card-link">
                    <div class="stat-card verified">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="mb-1"><?php echo $studentData['verified']; ?></h3>
                                <p class="mb-0">Verified Students</p>
                                <small class="opacity-75">
                                    <?php echo $studentData['total'] ? round(($studentData['verified'] / $studentData['total']) * 100, 1) : 0; ?>% of total
                                </small>
                            </div>
                            <i class="bi bi-check-circle fs-1 opacity-75"></i>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 mb-4 student-card">
                <a href="student_list.php?status=nonverified" class="stat-card-link">
                    <div class="stat-card unverified">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="mb-1"><?php echo $studentData['nonverified']; ?></h3>
                                <p class="mb-0">Unverified Students</p>
                                <small class="opacity-75">
                                    <?php echo $studentData['total'] ? round(($studentData['nonverified'] / $studentData['total']) * 100, 1) : 0; ?>% of total
                                </small>
                            </div>
                            <i class="bi bi-clock fs-1 opacity-75"></i>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 mb-4 study-card">
                <a href="research_list.php?status=verified" class="stat-card-link">
                    <div class="stat-card studies">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="mb-1"><?php echo $studyData['verified']; ?></h3>
                                <p class="mb-0">Verified Studies</p>
                                <small class="opacity-75">
                                    <?php echo $studyData['total'] ? round(($studyData['verified'] / $studyData['total']) * 100, 1) : 0; ?>% approved
                                </small>
                            </div>
                            <i class="bi bi-journal-check fs-1 opacity-75"></i>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 mb-4 student-card">
                <a href="student_list.php" class="stat-card-link">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="mb-1"><?php echo $studentData['total']; ?></h3>
                                <p class="mb-0">Total Students</p>
                                <small class="opacity-75">
                                    Active registrations
                                </small>
                            </div>
                            <i class="bi bi-people fs-1 opacity-75"></i>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4" id="charts-row">
            <!-- Student Verification Chart -->
            <div class="col-lg-6 mb-4 student-chart" id="student-chart-container">
                <div class="chart-container">
                    <h5 class="mb-3"><i class="bi bi-pie-chart text-primary"></i> Student Verification Status</h5>
                    <canvas id="studentChart"></canvas>
                </div>
            </div>
            
            <!-- Study Verification Chart -->
            <div class="col-lg-6 mb-4 study-chart" id="study-chart-container">
                <div class="chart-container">
                    <h5 class="mb-3"><i class="bi bi-bar-chart text-primary"></i> Study Verification Status</h5>
                    <canvas id="studyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Year-wise Analysis and Recent Activities -->
        <div class="row mb-4">
            <!-- Year-wise Student Status -->
            <div class="col-lg-8 mb-4 student-chart" id="year-chart-container">
                <div class="chart-container">
                    <h5 class="mb-3"><i class="bi bi-graph-up text-primary"></i> Year-wise Verified Students</h5>
                    <canvas id="yearChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    
    <script>
        // Chart configurations
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            }
        };

        // Student Verification Pie Chart
        const studentCtx = document.getElementById('studentChart').getContext('2d');
        new Chart(studentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Verified', 'Nonverified'],
                datasets: [{
                    data: [<?php echo $studentData['verified']; ?>, <?php echo $studentData['nonverified']; ?>],
                    backgroundColor: ['#11998e', '#ff9a9e'],
                    borderWidth: 0,
                    cutout: '60%'
                }]
            },
            options: {
                ...chartOptions,
                plugins: {
                    ...chartOptions.plugins,
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Study Verification Bar Chart
        const studyCtx = document.getElementById('studyChart').getContext('2d');
        new Chart(studyCtx, {
            type: 'bar',
            data: {
                labels: ['Verified', 'Nonverified'],
                datasets: [{
                    label: 'Studies',
                    data: [<?php echo $studyData['verified']; ?>, <?php echo $studyData['nonverified']; ?>],
                    backgroundColor: ['#667eea', '#fecfef'],
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                ...chartOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f0f0f0'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Year-wise Bar Chart (Verified Students Only)
        const yearCtx = document.getElementById('yearChart').getContext('2d');
        new Chart(yearCtx, {
            type: 'bar',
            data: {
                labels: ['3rd Year Students', '4th Year Students'],
                datasets: [{
                    // label: 'Verified Students', // Removed this line
                    data: [<?php echo $yearData['3rd_year']; ?>, <?php echo $yearData['4th_year']; ?>],
                    backgroundColor: ['#11998e', '#f06c64'],
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                ...chartOptions,
                plugins: {
                    legend: {
                        display: false // Hides legend if no label is present
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f0f0f0'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });


        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active class from all buttons
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                // Add active class to clicked button
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                
                // Get DOM elements
                const statsRow = document.getElementById('stats-row');
                const studentCards = statsRow.querySelectorAll('.student-card');
                const studyCards = statsRow.querySelectorAll('.study-card');
                const studentChart = document.getElementById('student-chart-container');
                const studyChart = document.getElementById('study-chart-container');
                const yearChart = document.getElementById('year-chart-container');
                
                // Reset visibility
                statsRow.classList.remove('d-none');
                studentCards.forEach(card => card.classList.remove('d-none'));
                studyCards.forEach(card => card.classList.remove('d-none'));
                studentChart.classList.remove('d-none');
                studyChart.classList.remove('d-none');
                yearChart.classList.remove('d-none');
                
                // Apply filter
                if (filter === 'students') {
                    studyCards.forEach(card => card.classList.add('d-none'));
                    studyChart.classList.add('d-none');
                } else if (filter === 'studies') {
                    studentCards.forEach(card => card.classList.add('d-none'));
                    studentChart.classList.add('d-none');
                    yearChart.classList.add('d-none');
                }
                // For 'all', keep everything visible (no additional changes needed)
                
                console.log('Filter applied:', filter);
            });
        });

        // Add some animation on load
        window.addEventListener('load', function() {
            const cards = document.querySelectorAll('.stat-card, .chart-container');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>