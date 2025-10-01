<?php
session_start();
include '../config/database.php';
include '../config/email.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize error message
$error_message = "";

// Clear success message and type on page load (unless verification is successful)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['code'])) {
    unset($_SESSION['success_message']);
    unset($_SESSION['success_type']);
}

// Function to send verification email (unchanged)
function sendVerificationEmail($email, $first_name, $verification_code, &$error_message) {
    require '../PHPMailer/src/PHPMailer.php';
    require '../PHPMailer/src/SMTP.php';
    require '../PHPMailer/src/Exception.php';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email Address';
        $mail->Body = "Dear $first_name,<br><br>Please verify your email address by clicking this link: <a href='" . BASE_URL . "/users/verify.php?code=$verification_code'>Verify Email</a><br><br>Alternatively, enter the following code manually:<br><strong>$verification_code</strong><br><br>Thank you!";
        $mail->AltBody = "Dear $first_name,\n\nPlease verify your email address by visiting this link: " . BASE_URL . "/users/verify.php?code=$verification_code\n\nAlternatively, enter the following code manually:\n$verification_code\n\nThank you!";

        $mail->send();
        $_SESSION['last_email_sent'] = time();
        return true;
    } catch (Exception $e) {
        $error_message = "Error sending verification email: {$mail->ErrorInfo}";
        return false;
    }
}

// Function to handle verification (unchanged)
function verifyUser($conn, $code, &$error_message) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM pending_users WHERE verification_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error_message = "Invalid or expired verification code.";
            error_log("Verification failed: No matching code found for '$code'");
            $conn->rollback();
            return false;
        }

        $user = $result->fetch_assoc();
        error_log("User found: " . print_r($user, true));
        $email = $user['email'];

        $stmt = $conn->prepare("INSERT INTO users (last_name, first_name, middle_name, email, password, gender, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $user['last_name'], $user['first_name'], $user['middle_name'], $user['email'], $user['password'], $user['gender'], $user['role']);
        if (!$stmt->execute()) {
            $error_message = "Error inserting into users: " . $conn->error;
            $conn->rollback();
            return false;
        }
        $user_id = $stmt->insert_id;

        $stmt = $conn->prepare("INSERT INTO students (user_id, first_name, middle_name, last_name, email, gender, year_section, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $user_id, $user['first_name'], $user['middle_name'], $user['last_name'], $user['email'], $user['gender'], $user['year_section'], $user['role']);
        if (!$stmt->execute()) {
            $error_message = "Error inserting into students: " . $conn->error;
            $conn->rollback();
            return false;
        }

        $stmt = $conn->prepare("DELETE FROM pending_users WHERE email = ?");
        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            $error_message = "Error deleting from pending_users: " . $conn->error;
            $conn->rollback();
            return false;
        }

        // Notify admin about new student registration
        $admin_query = "SELECT id FROM users WHERE role = 'admin' LIMIT 1";
        $admin_result = mysqli_query($conn, $admin_query);
        if ($admin_result && mysqli_num_rows($admin_result) > 0) {
            $admin_row = mysqli_fetch_assoc($admin_result);
            $admin_id = $admin_row['id'];
            
            require_once '../assets/includes/notification_functions.php';
            createNotification(
                $conn,
                $admin_id,
                'New Student Registration',
                'A new student "' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '" has registered and requires verification.',
                'info',
                $user_id,
                'new_student'
            );
        }

        $conn->commit();
        $_SESSION['success_message'] = "Your email has been verified successfully. Please wait for the administrator to verify your account.";
        $_SESSION['success_type'] = 'verify';
        unset($_SESSION['email']);
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Verification failed: " . $e->getMessage();
        return false;
    }
}

// Handle GET request for automatic verification
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['code'])) {
    $verification_code = trim($_GET['code']);
    if (!empty($verification_code)) {
        verifyUser($conn, $verification_code, $error_message);
    } else {
        $error_message = "No verification code provided.";
    }
}

// Handle form submission for manual verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verification_code'])) {
    $verification_code = trim($_POST['verification_code']);
    
    if (empty($verification_code)) {
        $error_message = "Please enter a verification code.";
    } else {
        verifyUser($conn, $verification_code, $error_message);
    }
}

// Handle resend code request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_code'])) {
    if (isset($_SESSION['email']) && isset($_SESSION['first_name'])) {
        function generateVerificationCode($length = 6) {
            $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[rand(0, strlen($characters) - 1)];
            }
            return $code;
        }
        
        $new_verification_code = generateVerificationCode();
        
        $stmt = $conn->prepare("UPDATE pending_users SET verification_code = ? WHERE email = ?");
        $stmt->bind_param("ss", $new_verification_code, $_SESSION['email']);
        
        if ($stmt->execute()) {
            if (sendVerificationEmail($_SESSION['email'], $_SESSION['first_name'], $new_verification_code, $error_message)) {
                $_SESSION['verification_code'] = $new_verification_code;
                $_SESSION['last_email_sent'] = time();
                $_SESSION['success_message'] = "A new verification code has been sent to your email.";
                $_SESSION['success_type'] = 'resend';
            }
        } else {
            $error_message = "Error updating verification code.";
        }
        $stmt->close();
    } else {
        $error_message = "No email found to resend verification code.";
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
    <title>Email Verification | Research Routing System</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/register.css">
    <style>
        .resend-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        .resend-link.disabled {
            pointer-events: none;
            color: grey;
            text-decoration: none;
        }
        #countdown {
            font-size: 14px;
            color: #555;
        }
    </style>
</head>
<body>
    <div id="errorPopup" class="error-popup">
        <p id="errorMessage"><?php echo htmlspecialchars($error_message); ?></p>
    </div>  
    
    <div id="successPopup" class="success-popup">
        <p id="successMessage"><?php echo $success_message; ?></p>
    </div>

    <div class="register-wrapper">
        <h2>Email Verification</h2>
        <p>Please enter the verification code sent to your email.</p>

        <form method="POST" action="verify.php" id="verifyForm">
            <div class="form-group">
                <label for="verification_code" class="required">Verification Code</label>
                <input type="text" class="form-control" name="verification_code" placeholder="Enter verification code" required>
            </div>
            <div class="resend-container">
                <a href="#" id="resendLink" class="resend-link">Resend Code</a>
                <span id="countdown" style="display: none;"></span>
            </div>
            <button type="submit" class="btn btn-primary">Verify</button>
        </form>

        <div class="footer-link">
            <a href="register.php">Back to Registration</a>
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

        // Show success popup and redirect to login.php only for verification
        <?php if ($show_success_popup) { ?>
            document.getElementById('successMessage').innerText = "<?php echo $success_message; ?>";
            document.getElementById('successPopup').classList.add('show');
            <?php if (isset($_SESSION['success_type']) && $_SESSION['success_type'] === 'verify') { ?>
                setTimeout(function() {
                    window.location.href = 'login.php'; // Redirect only for verification
                }, 1000);
            <?php } else { ?>
                setTimeout(function() {
                    document.getElementById('successPopup').classList.remove('show'); // Hide popup for resend
                }, 1000);
            <?php } ?>
        <?php } ?>

        // Handle resend link and countdown
        document.getElementById('resendLink').addEventListener('click', function(e) {
            e.preventDefault();
            const resendLink = document.getElementById('resendLink');
            const countdownElement = document.getElementById('countdown');
            
            resendLink.classList.add('disabled');
            
            let timeLeft = 60;
            countdownElement.style.display = 'inline';
            countdownElement.innerText = `Resend available in ${timeLeft}s`;

            const countdownInterval = setInterval(() => {
                timeLeft--;
                countdownElement.innerText = `Resend available in ${timeLeft}s`;
                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    resendLink.classList.remove('disabled');
                    countdownElement.style.display = 'none';
                }
            }, 1000);

            const formData = new FormData();
            formData.append('resend_code', 'true');
            
            fetch('verify.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                window.location.reload(); // Reload to show success message
            })
            .catch(error => {
                console.error('Error resending code:', error);
                document.getElementById('errorMessage').innerText = 'Error resending verification code.';
                document.getElementById('errorPopup').classList.add('show');
                setTimeout(() => {
                    document.getElementById('errorPopup').classList.remove('show');
                }, 2500);
            });
        });

        // Check if resend link should be disabled on page load
        <?php if (isset($_SESSION['last_email_sent'])) { ?>
            const lastSent = <?php echo $_SESSION['last_email_sent']; ?>;
            const currentTime = Math.floor(Date.now() / 1000);
            const timeDiff = currentTime - lastSent;
            if (timeDiff < 60) {
                const resendLink = document.getElementById('resendLink');
                const countdownElement = document.getElementById('countdown');
                let timeLeft = 60 - timeDiff;
                resendLink.classList.add('disabled');
                countdownElement.style.display = 'inline';
                countdownElement.innerText = `Resend available in ${timeLeft}s`;

                const countdownInterval = setInterval(() => {
                    timeLeft--;
                    countdownElement.innerText = `Resend available in ${timeLeft}s`;
                    if (timeLeft <= 0) {
                        clearInterval(countdownInterval);
                        resendLink.classList.remove('disabled');
                        countdownElement.style.display = 'none';
                    }
                }, 1000);
            }
        <?php } ?>
    </script>
</body>
</html>