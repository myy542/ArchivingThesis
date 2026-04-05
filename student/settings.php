<?php
session_start();
$conn = new mysqli("127.0.0.1", "root", "", "thesis_archiving");

// CHECK: kung wala naka-login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// CHECK: kung student ba (role_id = 2)
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

// kuhaon ang current reset method
$method_sql = "SELECT reset_method FROM user_settings WHERE user_id = ?";
$method_stmt = $conn->prepare($method_sql);
$method_stmt->bind_param("i", $user_id);
$method_stmt->execute();
$method_result = $method_stmt->get_result();
$settings = $method_result->fetch_assoc();
$current_method = $settings['reset_method'] ?? 'email';

// VARIABLES para sa mga steps
$step = 'settings'; // settings, send_otp, verify_otp, reset_password
$message = '';
$otp_sent = false;

// STEP 1: Save OTP method preference
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

// STEP 2: Send OTP
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_otp'])) {
    $otp = rand(100000, 999999);
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // delete old OTP
    $delete_old = $conn->prepare("DELETE FROM password_resets WHERE user_id = ? AND is_used = 0");
    $delete_old->bind_param("i", $user_id);
    $delete_old->execute();
    
    // save new OTP
    $insert_otp = $conn->prepare("INSERT INTO password_resets (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
    $insert_otp->bind_param("iss", $user_id, $otp, $expires_at);
    $insert_otp->execute();
    
    // store sa session
    $_SESSION['reset_user_id'] = $user_id;
    $_SESSION['reset_otp'] = $otp; // temporary for testing
    
    // i-send based sa preferred method
    if ($current_method == 'email') {
        // For testing: display OTP sa screen
        $otp_sent = true;
        $message = "📧 OTP sent to your email: " . $user['email'] . "<br>🔑 Test OTP: <strong>$otp</strong> (remove in production)";
    } else {
        // SMS
        $otp_sent = true;
        $message = "📱 OTP sent to your phone: " . $user['contact_number'] . "<br>🔑 Test OTP: <strong>$otp</strong> (remove in production)";
    }
    
    $step = 'verify_otp';
}

// STEP 3: Verify OTP
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_otp'])) {
    $entered_otp = $_POST['otp_code'];
    
    $sql = "SELECT * FROM password_resets WHERE user_id = ? AND otp_code = ? AND is_used = 0 AND expires_at > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $entered_otp);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // mark OTP as used
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

// STEP 4: Reset Password
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
                // clear session data
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_otp']);
                unset($_SESSION['can_reset_password']);
                
                $message = "✅ Password reset successfully! Please login with your new password.";
                $step = 'settings';
                
                // optional: auto logout after 3 seconds
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'logout.php';
                    }, 3000);
                </script>";
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

// RESET ang OTP sending form (balik sa send OTP)
if (isset($_GET['action']) && $_GET['action'] == 'reset_otp') {
    $step = 'settings';
    $message = '';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Settings - Thesis Archiving</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 20px; }
        .container { max-width: 550px; background: white; padding: 30px; border-radius: 12px; margin: auto; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        .welcome { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-radius: 8px; margin-bottom: 25px; }
        .message { padding: 12px; border-radius: 6px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message.info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .option { margin: 20px 0; padding: 15px; border: 2px solid #e0e0e0; border-radius: 10px; transition: all 0.3s; }
        .option:hover { border-color: #007bff; background: #f8f9fa; }
        input[type="radio"] { margin-right: 10px; transform: scale(1.2); }
        label { font-size: 16px; font-weight: bold; cursor: pointer; }
        .desc { margin-left: 28px; color: #666; font-size: 13px; margin-top: 5px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        button { background: #007bff; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; width: 100%; margin-top: 10px; transition: background 0.3s; }
        button:hover { background: #0056b3; }
        button.secondary { background: #6c757d; }
        button.secondary:hover { background: #5a6268; }
        button.danger { background: #dc3545; }
        button.danger:hover { background: #c82333; }
        .back-link { display: inline-block; margin-top: 20px; color: #007bff; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .otp-input { text-align: center; font-size: 24px; letter-spacing: 10px; }
        hr { margin: 20px 0; border: none; border-top: 1px solid #e0e0e0; }
        .section-title { font-size: 18px; font-weight: bold; margin: 20px 0 10px 0; color: #555; }
    </style>
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
    
    <!-- STEP 1: OTP METHOD PREFERENCE -->
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
    
    <!-- STEP 2: SEND OTP -->
    <div class="section-title">🔐 Step 2: Reset Password</div>
    <?php if ($step == 'verify_otp'): ?>
        <!-- Verify OTP Form -->
        <form method="POST">
            <p style="margin-bottom: 10px; color: #555;">Enter the 6-digit OTP sent to your <?php echo $current_method; ?>:</p>
            <input type="text" name="otp_code" placeholder="000000" maxlength="6" class="otp-input" required autocomplete="off">
            <button type="submit" name="verify_otp">✅ Verify OTP</button>
            <a href="?action=reset_otp" style="display: block; text-align: center; margin-top: 10px; color: #007bff;">← Didn't receive code? Try again</a>
        </form>
        
    <?php elseif ($step == 'reset_password'): ?>
        <!-- Reset Password Form -->
        <form method="POST">
            <input type="password" name="new_password" placeholder="New Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
            <button type="submit" name="reset_password">🔑 Reset Password</button>
            <button type="button" onclick="window.location.href='settings.php'" class="secondary" style="margin-top: 5px;">← Cancel</button>
        </form>
        
    <?php else: ?>
        <!-- Send OTP Button -->
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
</body>
</html>