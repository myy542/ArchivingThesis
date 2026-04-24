<?php
session_start();
$conn = new mysqli("127.0.0.1", "root", "", "thesis_archiving");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$role_check = "SELECT role_id, email, contact_number, first_name FROM user_table WHERE user_id = ?";
$role_stmt = $conn->prepare($role_check);
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_result = $role_stmt->get_result();
$user = $role_result->fetch_assoc();

if ($user['role_id'] != 2) {
    header("Location: dashboard.php");
    exit();
}

$method_sql = "SELECT reset_method FROM user_settings WHERE user_id = ?";
$method_stmt = $conn->prepare($method_sql);
$method_stmt->bind_param("i", $user_id);
$method_stmt->execute();
$method_result = $method_stmt->get_result();
$settings = $method_result->fetch_assoc();
$current_method = $settings['reset_method'] ?? 'email';

$step = 'settings';
$message = '';
$otp_sent = false;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_method'])) {
    $reset_method = $_POST['reset_method'];
    
    $check = $conn->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $check->bind_param("i", $user_id);
    $check->execute();
    $check_result = $check->get_result();
    
    if ($check_result->num_rows > 0) {
        $update = $conn->prepare("UPDATE user_settings SET reset_method = ? WHERE user_id = ?");
        $update->bind_param("si", $reset_method, $user_id);
        $update->execute();
    } else {
        $insert = $conn->prepare("INSERT INTO user_settings (user_id, reset_method) VALUES (?, ?)");
        $insert->bind_param("is", $user_id, $reset_method);
        $insert->execute();
    }
    
    $message = "✅ Password reset method updated successfully!";
    $current_method = $reset_method;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_otp'])) {
    $otp = rand(100000, 999999);
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    $delete_old = $conn->prepare("DELETE FROM password_resets WHERE user_id = ? AND is_used = 0");
    $delete_old->bind_param("i", $user_id);
    $delete_old->execute();
    
    $insert_otp = $conn->prepare("INSERT INTO password_resets (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
    $insert_otp->bind_param("iss", $user_id, $otp, $expires_at);
    $insert_otp->execute();
    
    $_SESSION['reset_user_id'] = $user_id;
    $_SESSION['reset_otp'] = $otp;
    
    if ($current_method == 'email') {
        $otp_sent = true;
        $message = "📧 OTP sent to your email: " . $user['email'] . "<br>🔑 Test OTP: <strong>$otp</strong>";
    } else {
        $otp_sent = true;
        $message = "📱 OTP sent to your phone: " . $user['contact_number'] . "<br>🔑 Test OTP: <strong>$otp</strong>";
    }
    
    $step = 'verify_otp';
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_otp'])) {
    $entered_otp = $_POST['otp_code'];
    
    $sql = "SELECT * FROM password_resets WHERE user_id = ? AND otp_code = ? AND is_used = 0 AND expires_at > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $entered_otp);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $update = $conn->prepare("UPDATE password_resets SET is_used = 1 WHERE user_id = ? AND otp_code = ?");
        $update->bind_param("is", $user_id, $entered_otp);
        $update->execute();
        
        $_SESSION['can_reset_password'] = true;
        $step = 'reset_password';
        $message = "✅ OTP verified! You can now reset your password.";
    } else {
        $message = "❌ Invalid or expired OTP! Please try again.";
        $step = 'verify_otp';
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    if (!isset($_SESSION['can_reset_password']) || $_SESSION['can_reset_password'] !== true) {
        $message = "❌ Please verify OTP first!";
        $step = 'settings';
    } else {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $sql = "UPDATE user_table SET password = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_otp']);
                unset($_SESSION['can_reset_password']);
                
                $message = "✅ Password reset successfully! Please login with your new password.";
                $step = 'settings';
                
                echo "<script>setTimeout(function(){ window.location.href = 'logout.php'; }, 3000);</script>";
            } else {
                $message = "❌ Failed to reset password. Please try again!";
                $step = 'reset_password';
            }
        } else {
            $message = "❌ Passwords do not match!";
            $step = 'reset_password';
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'reset_otp') {
    $step = 'settings';
    $message = '';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Settings - Thesis Archiving</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/settings.css">
</head>
<body>
<div class="container">
    <div class="welcome">
        👋 Welcome, <?php echo htmlspecialchars($user['first_name']); ?> (Student)
    </div>
    
    <h2>⚙️ Account Settings</h2>
    
    <?php if ($message): ?>
        <div class="message <?php echo strpos($message, '✅') !== false ? 'success' : (strpos($message, '❌') !== false ? 'error' : 'info'); ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="section-title">📌 Step 1: Choose OTP Delivery Method</div>
    <form method="POST">
        <div class="option">
            <input type="radio" name="reset_method" value="email" id="email" <?php echo $current_method == 'email' ? 'checked' : ''; ?>>
            <label for="email">📧 Email OTP</label>
            <div class="desc">Receive code via email (<?php echo htmlspecialchars($user['email']); ?>)</div>
        </div>
        <div class="option">
            <input type="radio" name="reset_method" value="sms" id="sms" <?php echo $current_method == 'sms' ? 'checked' : ''; ?>>
            <label for="sms">📱 SMS OTP</label>
            <div class="desc">Receive code via text message (<?php echo htmlspecialchars($user['contact_number']); ?>)</div>
        </div>
        <button type="submit" name="save_method">💾 Save Preference</button>
    </form>
    
    <hr>
    
    <div class="section-title">🔐 Step 2: Reset Password</div>
    <?php if ($step == 'verify_otp'): ?>
        <form method="POST">
            <p style="margin-bottom: 10px; color: #555;">Enter the 6-digit OTP sent to your <?php echo $current_method; ?>:</p>
            <input type="text" name="otp_code" placeholder="000000" maxlength="6" class="otp-input" required autocomplete="off">
            <button type="submit" name="verify_otp">✅ Verify OTP</button>
           <a href="?action=reset_otp" style="display: block; text-align: center; margin-top: 10px; color: #007bff;">← Didn't receive code? Try again</a>
        </form>
    <?php elseif ($step == 'reset_password'): ?>
        <form method="POST">
            <input type="password" name="new_password" placeholder="New Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
            <button type="submit" name="reset_password">🔑 Reset Password</button>
            <button type="button" onclick="window.location.href='settings.php'" class="secondary" style="margin-top: 5px;">← Cancel</button>
        </form>
    <?php else: ?>
        <form method="POST">
            <button type="submit" name="send_otp" class="danger" style="background: #ff9800;">
                📲 Send OTP to <?php echo $current_method == 'email' ? 'Email' : 'SMS'; ?>
            </button>
        </form>
        <p style="font-size: 12px; color: #666; margin-top: 10px; text-align: center;">
            ⚠️ You will receive a 6-digit OTP valid for 10 minutes.
        </p>
    <?php endif; ?>
    
    <hr>
    <a href="student_dashboard.php" class="back-link">← Back to Dashboard</a>
</div>
<script src="js/settings.js"></script>
</body>
</html>