<?php
// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Adjust path based on your file structure
require_once dirname(__DIR__) . '/phpmailer/src/Exception.php';
require_once dirname(__DIR__) . '/phpmailer/src/PHPMailer.php';
require_once dirname(__DIR__) . '/phpmailer/src/SMTP.php';

function sendOTPEmail($to_email, $to_name, $otp) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings - GAMIT ANG GMAIL
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@gmail.com';  // CHANGE THIS TO YOUR EMAIL
        $mail->Password   = 'your-app-password';     // CHANGE THIS TO YOUR APP PASSWORD
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Optional: Disable SSL verification for testing (remove in production)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom('your-email@gmail.com', 'Thesis Management System');
        $mail->addAddress($to_email, $to_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset OTP - Thesis Management System';
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { text-align: center; border-bottom: 2px solid #dc2626; padding-bottom: 20px; margin-bottom: 20px; }
                .header h1 { color: #dc2626; margin: 0; font-size: 24px; }
                .otp-code { font-size: 32px; font-weight: bold; color: #dc2626; text-align: center; padding: 20px; background: #fef2f2; border-radius: 8px; letter-spacing: 5px; margin: 20px 0; }
                .footer { text-align: center; font-size: 12px; color: #999; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Thesis Management System</h1>
                    <p>Password Reset Request</p>
                </div>
                <p>Hello <strong>" . htmlspecialchars($to_name) . "</strong>,</p>
                <p>We received a request to reset your password. Use the OTP code below to proceed:</p>
                <div class='otp-code'>" . $otp . "</div>
                <p>This OTP is valid for <strong>15 minutes</strong>.</p>
                <p>If you did not request this, please ignore this email.</p>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Thesis Management System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Your OTP for password reset is: " . $otp . "\n\nThis OTP is valid for 15 minutes.";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error for debugging
        error_log("Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>