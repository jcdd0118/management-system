<?php
session_start();
include '../config/database.php';

// Include PHPMailer for sending emails
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
require '../PHPMailer/src/Exception.php';

// Initialize error and success messages
$error_message = "";
$success_message = "";
$remaining_seconds = 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    // Validate email
    if (empty($email)) {
        $error_message = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        // Check if email exists in users table
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error_message = "No user found with this email.";
        } else {
            // Check for recent reset request (within 1 minute)
            $stmt = $conn->prepare("SELECT created_at FROM password_resets WHERE email = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $last_request_time = strtotime($row['created_at']);
                $current_time = time();
                $time_diff = $current_time - $last_request_time;

                if ($time_diff < 60) { // 60 seconds = 1 minute
                    $remaining_seconds = 60 - $time_diff;
                    $error_message = "Please wait $remaining_seconds seconds before requesting another reset link.";
                    $stmt->close();
                } else {
                    // Proceed with generating a new reset token
                    $reset_token = bin2hex(openssl_random_pseudo_bytes(16));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour
                    $created_at = date('Y-m-d H:i:s'); // Current timestamp

                    // Store reset token in database
                    $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $email, $reset_token, $expires_at, $created_at);

                    if ($stmt->execute()) {
                        // Send reset email
                        $mail = new PHPMailer(true);
                        try {
                            // Server settings
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = 'srcresearchvault@gmail.com';
                            $mail->Password = 'efqrgzgwgcctwmnb';
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = 587;

                            // Recipients
                            $mail->setFrom('srcresearchvault@gmail.com', 'Santa Rita College');
                            $mail->addAddress($email);

                            // Content
                            $mail->isHTML(true);
                            $mail->Subject = 'Password Reset Request';
                            $mail->Body = "Dear User,<br><br>Please click the following link to reset your password:<br><a href='http://localhost/management_system/users/reset_password.php?token=$reset_token'>Reset Password</a><br><br>This link will expire in 1 hour.<br><br>Thank you!";
                            $mail->AltBody = "Dear User,\n\nPlease visit the following link to reset your password:\nhttp://localhost/management_system/users/reset_password.php?token=$reset_token\n\nThis link will expire in 1 hour.\n\nThank you!";

                            $mail->send();
                            $success_message = "A password reset link has been sent to your email.";
                            $_SESSION['success_message'] = $success_message;
                        } catch (Exception $e) {
                            $error_message = "Error sending reset email: {$mail->ErrorInfo}";
                        }
                    } else {
                        $error_message = "Error generating reset token. Please try again.";
                    }
                    $stmt->close();
                }
            } else {
                // No previous reset request, proceed with generating a new one
                $reset_token = bin2hex(openssl_random_pseudo_bytes(16));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour
                $created_at = date('Y-m-d H:i:s'); // Current timestamp

                // Store reset token in database
                $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $email, $reset_token, $expires_at, $created_at);

                if ($stmt->execute()) {
                    // Send reset email
                    $mail = new PHPMailer(true);
                    try {
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'srcresearchvault@gmail.com';
                        $mail->Password = 'efqrgzgwgcctwmnb';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;

                        // Recipients
                        $mail->setFrom('srcresearchvault@gmail.com', 'Santa Rita College');
                        $mail->addAddress($email);

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Password Reset Request';
                        $mail->Body = "Dear User,<br><br>Please click the following link to reset your password:<br><a href='http://localhost/management_system/users/reset_password.php?token=$reset_token'>Reset Password</a><br><br>This link will expire in 1 hour.<br><br>Thank you!";
                        $mail->AltBody = "Dear User,\n\nPlease visit the following link to reset your password:\nhttp://localhost/management_system/users/reset_password.php?token=$reset_token\n\nThis link will expire in 1 hour.\n\nThank you!";

                        $mail->send();
                        $success_message = "A password reset link has been sent to your email.";
                        $_SESSION['success_message'] = $success_message;
                    } catch (Exception $e) {
                        $error_message = "Error sending reset email: {$mail->ErrorInfo}";
                    }
                } else {
                    $error_message = "Error generating reset token. Please try again.";
                }
                $stmt->close();
            }
        }
    }
}

$conn->close();

// Check if there's a success message to display
$show_success_popup = isset($_SESSION['success_message']) && !empty($_SESSION['success_message']);
$success_message = $show_success_popup ? htmlspecialchars($_SESSION['success_message']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Research Routing System</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/register.css">
    <style>
        #timer {
            color: #dc3545;
            font-weight: bold;
            margin-top: 10px;
            display: none;
        }
    </style>
</head>
<body>
    <!-- Error Popup -->
    <div id="errorPopup" class="error-popup">
        <p id="errorMessage"><?php echo htmlspecialchars($error_message); ?></p>
    </div>

    <!-- Success Popup -->
    <div id="successPopup" class="success-popup">
        <p id="successMessage"><?php echo $success_message; ?></p>
    </div>

    <div class="register-wrapper">
        <h2>Forgot Password</h2>
        <p>Enter your email address to receive a password reset link.</p>

        <form method="POST" action="forgot_password.php">
            <div class="form-group">
                <label for="email" class="required">Email</label>
                <input type="email" class="form-control" name="email" placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>
            <button type="submit" class="btn btn-primary" id="submitButton">Send Reset Link</button>
            <div id="timer"></div>
        </form>

        <div class="footer-link">
            <a href="login.php">Back to Login</a>
        </div>
    </div>

    <script>
        // Show error popup if there's an error message
        <?php if (!empty($error_message)) { ?>
            document.getElementById('errorMessage').innerText = "<?php echo htmlspecialchars($error_message); ?>";
            document.getElementById('errorPopup').classList.add('show');
            setTimeout(function() {
                document.getElementById('errorPopup').classList.remove('show');
            }, 2500);
        <?php } ?>

        // Show success popup if there's a success message
        <?php if ($show_success_popup) { ?>
            document.getElementById('successMessage').innerText = "<?php echo $success_message; ?>";
            document.getElementById('successPopup').classList.add('show');
            setTimeout(function() {
                document.getElementById('successPopup').classList.remove('show');
                <?php unset($_SESSION['success_message']); // Clear the session message ?>
            }, 2500);
        <?php } ?>

        // Timer for reset request cooldown
        <?php if ($remaining_seconds > 0) { ?>
            let timeLeft = <?php echo $remaining_seconds; ?>;
            const timerElement = document.getElementById('timer');
            const submitButton = document.getElementById('submitButton');
            
            // Disable submit button
            submitButton.disabled = true;
            timerElement.style.display = 'block';
            
            const countdown = setInterval(() => {
                timerElement.innerText = `Please wait ${timeLeft} seconds before requesting again`;
                timeLeft--;
                
                if (timeLeft < 0) {
                    clearInterval(countdown);
                    timerElement.style.display = 'none';
                    timerElement.innerText = '';
                    submitButton.disabled = false;
                }
            }, 1000);
        <?php } ?>
    </script>
</body>
</html>