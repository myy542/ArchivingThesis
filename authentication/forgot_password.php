<?php
session_start();
include("../config/db.php");
include("send_otp.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = isset($_GET['step']) ? $_GET['step'] : 'request';
$message = '';
$error = '';
$email = '';

// ==================== STEP 1: REQUEST OTP ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_otp'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } else {
        // Check if email exists in user_table
        $check_query = "SELECT user_id, first_name, last_name, email FROM user_table WHERE email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $user = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($user) {
            // Generate 6-digit OTP
            $otp = sprintf("%06d", mt_rand(1, 999999));
            $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Create password_resets table if not exists
            $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(100) NOT NULL,
                otp VARCHAR(6) NOT NULL,
                expires_at DATETIME NOT NULL,
                is_used TINYINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            // Delete old OTP for this email
            $delete_query = "DELETE FROM password_resets WHERE email = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param("s", $email);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            // Insert new OTP
            $insert_query = "INSERT INTO password_resets (email, otp, expires_at) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sss", $email, $otp, $expires_at);
            $insert_stmt->execute();
            $insert_stmt->close();
            
            // Send email using PHPMailer
            $email_sent = sendOTPEmail($email, $user['first_name'] . ' ' . $user['last_name'], $otp);
            
            if ($email_sent) {
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_otp'] = $otp;
                $_SESSION['otp_sent'] = true;
                header("Location: forgot_password.php?step=verify");
                exit;
            } else {
                $error = "Failed to send OTP. Please check your email address or try again later.";
            }
        } else {
            $error = "Email address not found in our records.";
        }
    }
}

// ==================== STEP 2: VERIFY OTP ====================
if (isset($_POST['verify_otp']) && isset($_SESSION['reset_email'])) {
    $otp_entered = trim($_POST['otp']);
    $email = $_SESSION['reset_email'];
    
    if (empty($otp_entered)) {
        $error = "Please enter the OTP code.";
    } else {
        // Check OTP in database
        $check_query = "SELECT * FROM password_resets WHERE email = ? AND otp = ? AND expires_at > NOW() AND is_used = 0";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ss", $email, $otp_entered);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $reset_data = $result->fetch_assoc();
        $check_stmt->close();
        
        if ($reset_data) {
            // Mark OTP as used
            $update_query = "UPDATE password_resets SET is_used = 1 WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("i", $reset_data['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            $_SESSION['reset_verified'] = true;
            header("Location: forgot_password.php?step=reset");
            exit;
        } else {
            $error = "Invalid or expired OTP. Please request a new one.";
        }
    }
}

// ==================== STEP 3: RESET PASSWORD ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password']) && isset($_SESSION['reset_verified']) && $_SESSION['reset_verified'] === true) {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    $email = $_SESSION['reset_email'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_query = "UPDATE user_table SET password = ? WHERE email = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ss", $hashed_password, $email);
        
        if ($update_stmt->execute()) {
            // Clear session
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_otp']);
            unset($_SESSION['reset_verified']);
            unset($_SESSION['otp_sent']);
            
            $message = "Password reset successfully! You can now login with your new password.";
            $step = 'success';
        } else {
            $error = "Failed to reset password. Please try again.";
        }
        $update_stmt->close();
    }
}

// Resend OTP
if (isset($_GET['resend']) && isset($_SESSION['reset_email'])) {
    $email = $_SESSION['reset_email'];
    
    $user_query = "SELECT first_name, last_name FROM user_table WHERE email = ?";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bind_param("s", $email);
    $user_stmt->execute();
    $user = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();
    
    $otp = sprintf("%06d", mt_rand(1, 999999));
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    $delete_query = "DELETE FROM password_resets WHERE email = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("s", $email);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    $insert_query = "INSERT INTO password_resets (email, otp, expires_at) VALUES (?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("sss", $email, $otp, $expires_at);
    $insert_stmt->execute();
    $insert_stmt->close();
    
    // Resend email
    $email_sent = sendOTPEmail($email, $user['first_name'] . ' ' . $user['last_name'], $otp);
    
    if ($email_sent) {
        $_SESSION['reset_otp'] = $otp;
        header("Location: forgot_password.php?step=verify");
        exit;
    } else {
        $error = "Failed to resend OTP. Please try again.";
    }
}

$pageTitle = "Forgot Password";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .forgot-container {
            max-width: 450px;
            width: 100%;
        }

        .forgot-card {
            background: white;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid #ffcdd2;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #991b1b;
        }

        .logo span {
            color: #dc2626;
        }

        .logo p {
            color: #6b7280;
            font-size: 0.85rem;
            margin-top: 5px;
        }

        h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #991b1b;
            margin-bottom: 10px;
            text-align: center;
        }

        .subtitle {
            text-align: center;
            color: #6b7280;
            font-size: 0.85rem;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ffcdd2;
            border-radius: 12px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .input-icon input {
            padding-left: 45px;
        }

        .otp-input {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 5px;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn:hover {
            background: #991b1b;
            transform: translateY(-2px);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #6b7280;
            text-decoration: none;
            font-size: 0.85rem;
        }

        .back-link:hover {
            color: #dc2626;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .alert i {
            font-size: 1rem;
        }

        .resend-link {
            text-align: center;
            margin-top: 15px;
        }

        .resend-link a {
            color: #dc2626;
            text-decoration: none;
            font-size: 0.8rem;
        }

        .resend-link a:hover {
            text-decoration: underline;
        }

        hr {
            margin: 20px 0;
            border-color: #ffcdd2;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-card">
            <div class="logo">
                <h1>Thesis<span>Manager</span></h1>
                <p>Thesis Archiving System</p>
            </div>

            <?php if ($step == 'request'): ?>
                <h2>Forgot Password?</h2>
                <p class="subtitle">Enter your email address and we'll send you an OTP to reset your password.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Email Address</label>
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" placeholder="your-email@example.com" value="<?= htmlspecialchars($email) ?>" required>
                        </div>
                    </div>
                    <button type="submit" name="send_otp" class="btn">
                        <i class="fas fa-paper-plane"></i> Send OTP
                    </button>
                </form>
                
                <hr>
                <a href="login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>

            <?php elseif ($step == 'verify'): ?>
                <h2>Verify OTP</h2>
                <p class="subtitle">Enter the 6-digit code sent to your email.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>OTP Code</label>
                        <div class="input-icon">
                            <i class="fas fa-key"></i>
                            <input type="text" name="otp" placeholder="Enter 6-digit OTP" maxlength="6" class="otp-input" required>
                        </div>
                    </div>
                    <button type="submit" name="verify_otp" class="btn">
                        <i class="fas fa-check-circle"></i> Verify OTP
                    </button>
                </form>
                
                <div class="resend-link">
                    <a href="forgot_password.php?resend=1">Didn't receive code? Resend OTP</a>
                </div>
                
                <hr>
                <a href="login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>

            <?php elseif ($step == 'reset'): ?>
                <h2>Reset Password</h2>
                <p class="subtitle">Create a new password for your account.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>New Password</label>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="new_password" placeholder="Enter new password (min. 6 characters)" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <div class="input-icon">
                            <i class="fas fa-check-circle"></i>
                            <input type="password" name="confirm_password" placeholder="Confirm your new password" required>
                        </div>
                    </div>
                    <button type="submit" name="reset_password" class="btn">
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                </form>
                
                <hr>
                <a href="login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>

            <?php elseif ($step == 'success'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
                
                <a href="login.php" class="btn" style="text-align: center; text-decoration: none; display: block;">
                    <i class="fas fa-sign-in-alt"></i> Go to Login
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>