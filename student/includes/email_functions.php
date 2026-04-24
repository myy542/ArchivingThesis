<?php
// email_functions.php - Separate file for email functions
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../phpmailer/src/Exception.php';
require_once __DIR__ . '/../../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../phpmailer/src/SMTP.php';

function sendCoAuthorInvitationEmail($to_email, $to_name, $inviter_name, $thesis_title, $thesis_id) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mylenesellar13@gmail.com';
        $mail->Password   = 'nxsrrpkdrvzjtgzi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom('mylenesellar13@gmail.com', 'Thesis Management System');
        $mail->addAddress($to_email, $to_name);
        
        $mail->isHTML(true);
        $mail->Subject = 'Thesis Collaboration Invitation';
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { text-align: center; border-bottom: 2px solid #dc2626; padding-bottom: 20px; margin-bottom: 20px; }
                .header h1 { color: #dc2626; margin: 0; font-size: 24px; }
                .btn { display: inline-block; padding: 12px 24px; background: #dc2626; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                .footer { text-align: center; font-size: 12px; color: #999; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Thesis Management System</h1>
                    <p>Co-Author Invitation</p>
                </div>
                <p>Hello <strong>" . htmlspecialchars($to_name) . "</strong>,</p>
                <p><strong>" . htmlspecialchars($inviter_name) . "</strong> has invited you to collaborate as a co-author on the thesis:</p>
                <h3 style='text-align: center; color: #dc2626; margin: 20px 0;'>\"" . htmlspecialchars($thesis_title) . "\"</h3>
                <p>Click the button below to view the thesis and accept the invitation:</p>
                <div style='text-align: center;'>
                    <a href='http://localhost/ArchivingThesis/student/projects.php?thesis_id=" . $thesis_id . "' class='btn'>View Thesis</a>
                </div>
                <p>If the button doesn't work, copy and paste this link into your browser:</p>
                <p style='font-size: 12px; color: #666;'>http://localhost/ArchivingThesis/student/projects.php?thesis_id=" . $thesis_id . "</p>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Thesis Management System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Thesis Collaboration Invitation\n\n" . $inviter_name . " invited you to collaborate on thesis: \"" . $thesis_title . "\"\n\nView here: http://localhost/ArchivingThesis/student/projects.php?thesis_id=" . $thesis_id;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Co-author email error: " . $mail->ErrorInfo);
        return false;
    }
}

function sendPendingInvitationEmail($to_email, $inviter_name, $thesis_title) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mylenesellar13@gmail.com';
        $mail->Password   = 'nxsrrpkdrvzjtgzi';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom('mylenesellar13@gmail.com', 'Thesis Management System');
        $mail->addAddress($to_email);
        
        $mail->isHTML(true);
        $mail->Subject = 'You Have Been Invited as a Co-Author';
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { text-align: center; border-bottom: 2px solid #dc2626; padding-bottom: 20px; margin-bottom: 20px; }
                .header h1 { color: #dc2626; margin: 0; font-size: 24px; }
                .btn { display: inline-block; padding: 12px 24px; background: #dc2626; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                .footer { text-align: center; font-size: 12px; color: #999; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Thesis Management System</h1>
                    <p>You've Been Invited as a Co-Author!</p>
                </div>
                <p>Hello,</p>
                <p><strong>" . htmlspecialchars($inviter_name) . "</strong> has invited you to collaborate as a co-author on a thesis:</p>
                <h3 style='text-align: center; color: #dc2626; margin: 20px 0;'>\"" . htmlspecialchars($thesis_title) . "\"</h3>
                <p>To accept this invitation and collaborate on this thesis, please register an account first:</p>
                <div style='text-align: center;'>
                    <a href='http://localhost/ArchivingThesis/authentication/register.php' class='btn'>Register Now</a>
                </div>
                <p>If you already have an account, you will see the invitation in your dashboard after logging in.</p>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Thesis Management System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Thesis Collaboration Invitation\n\n" . $inviter_name . " invited you to collaborate on thesis: \"" . $thesis_title . "\"\n\nPlease register at: http://localhost/ArchivingThesis/authentication/register.php";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Pending invitation email error: " . $mail->ErrorInfo);
        return false;
    }
}
?>