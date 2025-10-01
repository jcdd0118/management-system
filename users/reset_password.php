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

// Check if token is provided in the URL
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    $error_message = "Invalid or missing reset token.";
} else {
    // Validate token and check expiration
    $stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $error_message = "Invalid or expired reset token.";
    } else {
        $reset_data = $result->fetch_assoc();
        $email = $reset_data['email'];
        $expires_at = strtotime($reset_data['expires_at']);
        $current_time = time();

        if ($current_time > $expires_at) {
            $error_message = "This reset link has expired.";
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Handle form submission
            $password = trim($_POST['password']);
            $confirm_password = trim($_POST['confirm_password']);

            // Validate passwords
            if (empty($password) || empty($confirm_password)) {
                $error_message = "Please enter and confirm your new password.";
            } elseif ($password !== $confirm_password) {
                $error_message = "Passwords do not match.";
            } else {
                // Hash the new password
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                // Start transaction
                $conn->begin_transaction();
                try {
                    // Update password in users table
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                    $stmt->bind_param("ss", $hashed_password, $email);
                    if (!$stmt->execute()) {
                        throw new Exception("Error updating password: " . $conn->error);
                    }

                    // Delete the used reset token
                    $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                    $stmt->bind_param("s", $token);
                    if (!$stmt->execute()) {
                        throw new Exception("Error deleting reset token: " . $conn->error);
                    }

                    // Commit transaction
                    $conn->commit();

                    // Send confirmation email
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
                        $mail->Subject = 'Password Reset Successful';
                        $mail->Body = "Dear User,<br><br>Your password has been successfully reset. You can now log in with your new password.<br><br>If you did not initiate this change, please contact support immediately.<br><br>Thank you!";
                        $mail->AltBody = "Dear User,\n\nYour password has been successfully reset. You can now log in with your new password.\n\nIf you did not initiate this change, please contact support immediately.\n\nThank you!";

                        $mail->send();
                        $success_message = "Your password has been reset successfully. You will be redirected to the login page.";
                        $_SESSION['success_message'] = $success_message;
                    } catch (Exception $e) {
                        $error_message = "Password reset successful, but failed to send confirmation email: {$mail->ErrorInfo}";
                        $_SESSION['success_message'] = $error_message;
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Error resetting password: " . $e->getMessage();
                }
            }
        }
    }
    $stmt->close();
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
    <title>Reset Password | Research Routing System</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/register.css">
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
        <h2>Reset Password</h2>
        <?php if (empty($error_message) || $_SERVER['REQUEST_METHOD'] === 'POST') { ?>
            <p>Enter your new password below.</p>
            <form method="POST" action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="password" class="required">New Password</label>
                        <input type="password" class="form-control" name="password" placeholder="Enter new password" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="confirm_password" class="required">Confirm Password</label>
                        <input type="password" class="form-control" name="confirm_password" placeholder="Re-enter password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Reset Password</button>
            </form>
        <?php } else { ?>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        <?php } ?>

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

        // Show success popup and redirect to login.php if reset is successful
        <?php if ($show_success_popup) { ?>
            document.getElementById('successMessage').innerText = "<?php echo $success_message; ?>";
            document.getElementById('successPopup').classList.add('show');
            setTimeout(function() {
                document.getElementById('successPopup').classList.remove('show');
                window.location.href = 'login.php';
            }, 2500);
        <?php } ?>
    </script>
</body>
</html>