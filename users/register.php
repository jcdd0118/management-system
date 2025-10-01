<?php
// Start the session
session_start();

// Include the database connection
include '../config/database.php';
include '../config/email.php';
require_once '../assets/includes/year_section_functions.php';

// Include PHPMailer for sending emails
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
require '../PHPMailer/src/Exception.php';

// Function to generate a 6-character verification code
function generateVerificationCode($length = 6) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// Function to send verification email
function sendVerificationEmail($email, $first_name, $verification_code, &$error_message) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email Address';
        $mail->Body = "Dear $first_name,<br><br>Please verify your email address by entering the following code:<br><strong>$verification_code</strong><br><br>Or click this link: <a href='" . BASE_URL . "/users/verify.php?code=$verification_code'>Verify Email</a><br><br>Thank you!";
        $mail->AltBody = "Dear $first_name,\n\nPlease verify your email address by entering the following code:\n$verification_code\n\nOr visit this link: " . BASE_URL . "/users/verify.php?code=$verification_code\n\nThank you!";

        $mail->send();
        $_SESSION['last_email_sent'] = time(); // Store timestamp of email sent
        return true;
    } catch (Exception $e) {
        $error_message = "Error sending verification email: {$mail->ErrorInfo}";
        return false;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve form data
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $gender = $_POST['gender'];
    $year_section = $_POST['year_section'];
    $role = 'student';

    // Validation
    if (empty($last_name) || empty($first_name) || empty($email) || empty($password) || empty($confirm_password) || empty($gender) || empty($year_section)) {
        $error_message = "Fill up the required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $first_name) || !preg_match('/^[a-zA-Z\s]+$/', $last_name)) {
        $error_message = "Name fields should only contain letters and spaces.";
    } elseif (!empty($middle_name) && !preg_match('/^[a-zA-Z\s]+$/', $middle_name)) {
        $error_message = "Middle name should only contain letters and spaces.";
    } else {
        // Check if email already exists in users or pending_users
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? UNION SELECT id FROM pending_users WHERE email = ?");
        if (!$stmt) {
            $error_message = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("ss", $email, $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error_message = "Email is already registered.";
            } else {
                // Generate 6-character verification code
                $verification_code = generateVerificationCode();
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                // Insert into pending_users
                $stmt = $conn->prepare("INSERT INTO pending_users (last_name, first_name, middle_name, email, password, gender, role, year_section, verification_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    $error_message = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param("sssssssss", $last_name, $first_name, $middle_name, $email, $hashed_password, $gender, $role, $year_section, $verification_code);

                    if ($stmt->execute()) {
                        // Send verification email
                        if (sendVerificationEmail($email, $first_name, $verification_code, $error_message)) {
                            $_SESSION['email'] = $email; // Store email for verification
                            $_SESSION['first_name'] = $first_name; // Store first name for resend
                            $_SESSION['verification_code'] = $verification_code; // Store verification code for resend
                            $_SESSION['success_message'] = "Registration successful! Please check your email for the verification code.";
                            header("Location: verify.php");
                            exit();
                        }
                    } else {
                        $error_message = "Error registering user: " . $stmt->error;
                    }
                }
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Research Routing System</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/register.css">
</head>
<body>
    <!-- Error Popup -->
    <div id="errorPopup" class="error-popup">
        <p id="errorMessage"></p>
    </div>

    <div class="register-wrapper">
        <h2>Registration</h2>

        <form method="POST" action="register.php">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <div class="input-container">
                        <input type="text" class="form-control" name="first_name" id="first_name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                        <label for="first_name" class="required">First Name</label>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <div class="input-container">
                        <input type="text" class="form-control" name="middle_name" id="middle_name" value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>">
                        <label for="middle_name">Middle Name</label>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <div class="input-container">
                        <input type="text" class="form-control" name="last_name" id="last_name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                        <label for="last_name" class="required">Last Name</label>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label class="static-label required">Gender</label>
                    <div class="radio-container">
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="gender" value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'male') ? 'checked' : ''; ?> required>
                                Male
                            </label>
                            <label>
                                <input type="radio" name="gender" value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'checked' : ''; ?> required>
                                Female
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group col-md-6">
                    <div class="input-container">
                        <select name="year_section" class="form-control" id="year_section" required>
                            <?php echo generateYearSectionOptions($conn, isset($_POST['year_section']) ? $_POST['year_section'] : ''); ?>
                        </select>
                        <label for="year_section" class="required">Year and Section</label>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-12">
                    <div class="input-container">
                        <input type="email" class="form-control" name="email" id="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        <label for="email" class="required">Email</label>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <div class="input-container">
                        <input type="password" class="form-control" name="password" id="password" required>
                        <label for="password" class="required">Password</label>
                    </div>
                </div>
                <div class="form-group col-md-6">
                    <div class="input-container">
                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                        <label for="confirm_password" class="required">Confirm Password</label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Register</button>

            <p class="terms-text mt-2 text-center">
                By clicking Register, you agree to our 
                <a href="terms.php" target="_blank">Terms</a> and 
                <a href="privacy.php" target="_blank">Privacy Policy</a>.
            </p>
            </form>


        <div class="footer-link">
            Already have an account? <a href="login.php">Login here</a>.
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

    // Password strength indicator (optional enhancement)
    document.getElementById('password').addEventListener('input', function() {
        const password = this.value;
        const strength = calculatePasswordStrength(password);
        // You can add visual feedback here if desired
    });

    function calculatePasswordStrength(password) {
        let strength = 0;
        if (password.length >= 8) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        return strength;
    }

    // Real-time password confirmation check
    document.getElementById('confirm_password').addEventListener('input', function() {
        const password = document.getElementById('password').value;
        const confirmPassword = this.value;
        
        if (confirmPassword !== '' && password !== confirmPassword) {
            this.style.boxShadow = 'inset 5px 5px 10px #cbced1, inset -5px -5px 10px #ffffff, 0 0 10px rgba(244, 67, 54, 0.3)';
        } else if (confirmPassword !== '' && password === confirmPassword) {
            this.style.boxShadow = 'inset 5px 5px 10px #cbced1, inset -5px -5px 10px #ffffff, 0 0 10px rgba(76, 175, 80, 0.3)';
        } else {
            this.style.boxShadow = '';
        }
    });

    // Form validation before submission
    document.querySelector('form').addEventListener('submit', function(e) {
        const firstName = document.getElementById('first_name').value.trim();
        const lastName = document.getElementById('last_name').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const gender = document.querySelector('input[name="gender"]:checked');
        const yearSection = document.getElementById('year_section').value;

        // Clear previous error styling
        document.querySelectorAll('.form-control').forEach(input => {
            input.style.borderColor = '';
        });

        let hasErrors = false;

        // Validate required fields
        if (!firstName || !lastName || !email || !password || !confirmPassword || !gender || !yearSection) {
            showError('Please fill in all required fields.');
            hasErrors = true;
        }

        // Validate name fields (letters and spaces only)
        if (firstName && !/^[a-zA-Z\s]+$/.test(firstName)) {
            document.getElementById('first_name').style.borderColor = '#dc3545';
            showError('First name should only contain letters and spaces.');
            hasErrors = true;
        }

        if (lastName && !/^[a-zA-Z\s]+$/.test(lastName)) {
            document.getElementById('last_name').style.borderColor = '#dc3545';
            showError('Last name should only contain letters and spaces.');
            hasErrors = true;
        }

        // Validate email format
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            document.getElementById('email').style.borderColor = '#dc3545';
            showError('Please enter a valid email address.');
            hasErrors = true;
        }

        // Validate password length
        if (password && password.length < 8) {
            document.getElementById('password').style.borderColor = '#dc3545';
            showError('Password must be at least 8 characters long.');
            hasErrors = true;
        }

        // Validate password match
        if (password !== confirmPassword) {
            document.getElementById('confirm_password').style.borderColor = '#dc3545';
            showError('Passwords do not match.');
            hasErrors = true;
        }

        if (hasErrors) {
            e.preventDefault();
        }
    });

    // Show the error popup if there's an error message from PHP
    function showError(message) {
        document.getElementById('errorMessage').innerText = message;
        document.getElementById('errorPopup').classList.add('show');
        setTimeout(function() {
            document.getElementById('errorPopup').classList.remove('show');
        }, 3000);
    }

    // Actually call the function with PHP data
    <?php if (isset($error_message)) { ?>
        showError("<?php echo addslashes($error_message); ?>");
    <?php } ?>
</script>

</body>
</html>