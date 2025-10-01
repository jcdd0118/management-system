<?php
// Start the session
session_start();

// Include the database connection
include '../config/database.php';

// Check if the form has been submitted via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if the email and password are provided
    if (isset($_POST['email']) && isset($_POST['password'])) {
        // Get login data
        $email = $_POST['email'];
        $password = $_POST['password'];

        // Query the database to find the user by email - UPDATED TO INCLUDE ROLES
        $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        // Check if user exists
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Check if the user is verified
            if ($user['status'] !== 'verified') {
                $error_message = "Your account is not verified yet. Please wait for the administrator to verify your details.";
            } else {
                // Verify the password
                if (password_verify($password, $user['password'])) {
                    // Set session variables for user - UPDATED FOR MULTI-ROLE
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['roles'] = isset($user['roles']) ? $user['roles'] : $user['role']; // Store all roles
                    $_SESSION['avatar'] = !empty($user['avatar']) ? $user['avatar'] : 'avatar.png';
                    $_SESSION['user_data'] = $user; // Store full user data for role functions
                    $_SESSION['active_role'] = $user['role']; // Set initial active role

                    // If the user is a student, fetch and store year_section in session
                    if ($user['role'] === 'student') {
                        $student_sql = "SELECT year_section FROM students WHERE user_id = ? LIMIT 1";
                        $student_stmt = $conn->prepare($student_sql);
                        $student_stmt->bind_param("i", $user['id']);
                        $student_stmt->execute();
                        $student_result = $student_stmt->get_result();
                        if ($student_result->num_rows > 0) {
                            $student = $student_result->fetch_assoc();
                            $_SESSION['year_section'] = isset($student['year_section']) ? $student['year_section'] : '';
                        } else {
                            $_SESSION['year_section'] = '';
                        }
                        $student_stmt->close();
                    }

                    // Check if the user is an admin
                    if ($user['role'] === 'admin') {
                        header("Location: ../admin/dashboard.php");
                        exit();
                    } else {
                        // Redirect based on the user role
                        switch ($user['role']) {
                            case 'faculty':
                                header("Location: ../faculty/home.php");
                                break;
                            case 'student':
                                header("Location: ../student/home.php");
                                break;
                            case 'adviser':
                                header("Location: ../adviser/home.php");
                                break;
                            case 'dean':
                                header("Location: ../dean/home.php");
                                break;
                            case 'panelist':
                                header("Location: ../panel/home.php");
                                break;
                            case 'grammarian':
                                header("Location: ../grammarian/home.php");
                                break;
                            default:
                                $error_message = "Invalid user role.";
                                break;
                        }
                        exit();
                    }
                } else {
                    // Incorrect password
                    $error_message = "Invalid password.";
                }
            }
        } else {
            // No user found with the provided email
            $error_message = "No user found with this email.";
        }

        // Close the prepared statement
        $stmt->close();
    } else {
        // If email or password is not set in the form
        $error_message = "Please enter both email and password.";
    }
}

// Close the database connection
$conn->close();

// Check if there's a success message to display
$show_success_popup = isset($_SESSION['success_message']) && !empty($_SESSION['success_message']);
$success_message = $show_success_popup ? htmlspecialchars($_SESSION['success_message']) : '';

// Clear the success message after displaying it
if ($show_success_popup) {
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/login.css">
    <title>Login</title>
</head>
<body>  
    <!-- Error Popup -->
    <div id="errorPopup" class="error-popup">
        <p id="errorMessage"></p>
    </div>

    <div id="successPopup" class="success-popup">
        <p id="successMessage"></p>
    </div>

    <div class="login-wrapper">
        <!-- Left Section -->
        <div class="left-section">
            <div class="left-content">
                <img src="../assets/img/captrack.png" alt="SRC Logo">
                <h2>CapTrack Vault</h2>
            </div>
        </div>

        <!-- Right Section -->
        <div class="right-section">
            <div class="login-form">
                <h1>Login</h1>
                <form method="POST" action="login.php">
                    <div class="input-container">
                        <input type="email" name="email" id="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        <label for="email">Email</label>
                    </div>
                    
                    <div class="input-container">
                        <input type="password" name="password" id="password" required>
                        <label for="password">Password</label>
                    </div>
                    
                    <div class="fogotpassword-container">
                        <p class="forgot-password-link">
                            <a href="forgot_password.php">Forgot Password?</a>
                        </p>
                    </div>
                    
                    <button type="submit">Login</button>
                    
                    <p class="register-link">
                        <br>Don't have an account? <a href="register.php">Register here</a>.
                    </p>
                </form>
            </div>
        </div>
    </div>

<script>
    // Enhanced input handling for floating labels
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"], select');
        
        inputs.forEach(input => {
            // Check if input has content on page load (including pre-filled values)
            checkInputContent(input);
            
            // Add event listeners for input changes
            input.addEventListener('input', function() {
                checkInputContent(this);
            });
            
            input.addEventListener('change', function() {
                checkInputContent(this);
            });
            
            input.addEventListener('blur', function() {
                checkInputContent(this);
            });
        });
    });

    function checkInputContent(input) {
        if (input.value.trim() !== '' && input.value !== '') {
            input.classList.add('has-content');
        } else {
            input.classList.remove('has-content');
        }
    }

    // Show the error popup if there's an error message from PHP
    function showError(message) {
        document.getElementById('errorMessage').innerText = message;
        document.getElementById('errorPopup').classList.add('show');
        setTimeout(function() {
            document.getElementById('errorPopup').classList.remove('show');
        }, 3000);
    }

    // Show the success popup
    function showSuccess(message) {
        document.getElementById('successMessage').innerText = message;
        document.getElementById('successPopup').classList.add('show');
        setTimeout(function() {
            document.getElementById('successPopup').classList.remove('show');
        }, 3000);
    }

    // Actually call the functions with PHP data
    <?php if (isset($error_message)) { ?>
        showError("<?php echo addslashes($error_message); ?>");
    <?php } ?>

    <?php if ($show_success_popup) { ?>
        showSuccess("<?php echo addslashes($success_message); ?>");
    <?php } ?>
</script>
</body>
</html>