<?php
// Email notification functions
require_once '../config/email.php';
require_once '../PHPMailer/src/PHPMailer.php';
require_once '../PHPMailer/src/SMTP.php';
require_once '../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send student verification notification email
 * @param string $student_email Student's email address
 * @param string $student_name Student's full name
 * @return bool True if email sent successfully, false otherwise
 */
function sendStudentVerificationEmail($student_email, $student_name) {
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
        $mail->addAddress($student_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Account Verification Complete - CapTrack Vault SRC';
        
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
                <h2 style='color: #28a745; margin: 0; text-align: center;'>Account Verified Successfully!</h2>
            </div>
            
            <p>Dear <strong>$student_name</strong>,</p>
            
            <p>Great news! Your account has been successfully verified by the administrator.</p>
            
            <div style='background-color: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <p style='margin: 0;'><strong>What this means:</strong></p>
                <ul style='margin: 10px 0;'>
                    <li>You can now access all features of the CapTrack Vault SRC system</li>
                    <li>You can submit research projects and documentation</li>
                    <li>You can participate in title and final defense processes</li>
                </ul>
            </div>
            
            <p>You can now log in to your account and start using the system:</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . BASE_URL . "/users/login.php' style='background-color: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Login to Your Account</a>
            </div>
            
            <p>If you have any questions or need assistance, please don't hesitate to contact the system administrator.</p>
            
            <hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>
            
            <p style='color: #666; font-size: 12px; margin: 0;'>
                This is an automated message from CapTrack Vault SRC. Please do not reply to this email.
            </p>
        </div>";
        
        $mail->AltBody = "Dear $student_name,\n\nGreat news! Your account has been successfully verified by the administrator.\n\nWhat this means:\n- You can now access all features of the CapTrack Vault SRC system\n- You can submit research projects and documentation\n- You can participate in title and final defense processes\n- All system notifications will be sent to this email address\n\nYou can now log in to your account: " . BASE_URL . "/users/login.php\n\nIf you have any questions or need assistance, please don't hesitate to contact the system administrator.\n\nThis is an automated message from CapTrack Vault SRC. Please do not reply to this email.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send general notification email
 * @param string $recipient_email Recipient's email address
 * @param string $recipient_name Recipient's name
 * @param string $subject Email subject
 * @param string $message Email message content
 * @return bool True if email sent successfully, false otherwise
 */
function sendNotificationEmail($recipient_email, $recipient_name, $subject, $message) {
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
        $mail->addAddress($recipient_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
                <h2 style='color: #007bff; margin: 0; text-align: center;'>CapTrack Vault SRC</h2>
            </div>
            
            <p>Dear <strong>$recipient_name</strong>,</p>
            
            <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                $message
            </div>
            
            <hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>
            
            <p style='color: #666; font-size: 12px; margin: 0;'>
                This is an automated message from CapTrack Vault SRC. Please do not reply to this email.
            </p>
        </div>";
        
        $mail->AltBody = "Dear $recipient_name,\n\n$message\n\nThis is an automated message from CapTrack Vault SRC. Please do not reply to this email.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}
?>
