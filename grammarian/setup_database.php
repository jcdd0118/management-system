<?php
// Database setup script for grammarian functionality
include '../config/database.php';

echo "<h2>Grammarian Database Setup</h2>";

// Create manuscript_reviews table
$sql = "CREATE TABLE IF NOT EXISTS manuscript_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    student_id INT NOT NULL,
    manuscript_file VARCHAR(500) DEFAULT NULL,
    grammarian_reviewed_file VARCHAR(500) DEFAULT NULL,
    status ENUM('pending', 'under_review', 'approved', 'rejected') DEFAULT 'pending',
    grammarian_notes TEXT DEFAULT NULL,
    date_submitted TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_reviewed TIMESTAMP NULL DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES project_working_titles(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "<p style='color: green;'>✅ Table 'manuscript_reviews' created successfully or already exists.</p>";
} else {
    echo "<p style='color: red;'>❌ Error creating table: " . $conn->error . "</p>";
}

// Check if grammarian users exist
$checkRole = "SELECT COUNT(*) as count FROM users WHERE role = 'grammarian' OR roles LIKE '%grammarian%'";
$result = $conn->query($checkRole);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    echo "<p style='color: orange;'>⚠️ No grammarian users found. You can add a grammarian user through the admin panel.</p>";
} else {
    echo "<p style='color: green;'>✅ Found " . $row['count'] . " grammarian user(s).</p>";
}

// Check if upload directories exist
$manuscriptsDir = "../assets/uploads/manuscripts";
$reviewsDir = "../assets/uploads/grammarian_reviews";

if (!is_dir($manuscriptsDir)) {
    if (mkdir($manuscriptsDir, 0755, true)) {
        echo "<p style='color: green;'>✅ Created manuscripts upload directory.</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create manuscripts directory.</p>";
    }
} else {
    echo "<p style='color: green;'>✅ Manuscripts upload directory exists.</p>";
}

if (!is_dir($reviewsDir)) {
    if (mkdir($reviewsDir, 0755, true)) {
        echo "<p style='color: green;'>✅ Created grammarian reviews upload directory.</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create grammarian reviews directory.</p>";
    }
} else {
    echo "<p style='color: green;'>✅ Grammarian reviews upload directory exists.</p>";
}

echo "<br><h3>Setup Complete!</h3>";
echo "<p><a href='home.php' class='btn btn-primary'>Go to Grammarian Dashboard</a></p>";
echo "<p><a href='../admin/users.php' class='btn btn-secondary'>Manage Users</a></p>";

$conn->close();
?>
