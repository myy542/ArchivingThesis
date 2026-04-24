<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a librarian
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'librarian') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

// Get logged-in user info
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'Librarian';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// Get complete user data from database
$user_query = "SELECT user_id, username, email, first_name, last_name, role_id, status FROM user_table WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();

// Set values
if ($user_data) {
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $fullName = $first_name . " " . $last_name;
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
    $user_email = $user_data['email'];
    $username = $user_data['username'];
}

// Default values
$user_phone = "+63 912 345 6789";
$user_address = "Manila, Philippines";
$user_bio = "Dedicated librarian with expertise in digital archiving and thesis management. Committed to preserving academic research and providing excellent service to students and faculty.";

$message = "";
$message_type = "";

// Get notification count
$notificationCount = 0;
$check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check_table && $check_table->num_rows > 0) {
    $notif_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $notif_stmt = $conn->prepare($notif_query);
    $notif_stmt->bind_param("i", $user_id);
    $notif_stmt->execute();
    $notif_result = $notif_stmt->get_result();
    if ($notif_row = $notif_result->fetch_assoc()) {
        $notificationCount = $notif_row['count'];
    }
    $notif_stmt->close();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_first_name = trim($_POST['first_name'] ?? '');
    $new_last_name = trim($_POST['last_name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_username = trim($_POST['username'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');
    $new_address = trim($_POST['address'] ?? '');
    $new_bio = trim($_POST['bio'] ?? '');
    
    // Validation
    $errors = [];
    
    if ($new_first_name === '') {
        $errors[] = "First name is required";
    }
    if ($new_last_name === '') {
        $errors[] = "Last name is required";
    }
    if ($new_email === '') {
        $errors[] = "Email address is required";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    if ($new_username === '') {
        $errors[] = "Username is required";
    } elseif (strlen($new_username) < 3) {
        $errors[] = "Username must be at least 3 characters";
    }
    
    if (empty($errors)) {
        // Update user table
        $update_query = "UPDATE user_table SET first_name = ?, last_name = ?, email = ?, username = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ssssi", $new_first_name, $new_last_name, $new_email, $new_username, $user_id);
        
        if ($update_stmt->execute()) {
            // Update session
            $_SESSION['first_name'] = $new_first_name;
            $_SESSION['last_name'] = $new_last_name;
            $_SESSION['email'] = $new_email;
            $_SESSION['username'] = $new_username;
            
            $message = "Profile updated successfully!";
            $message_type = "success";
            
            // Refresh values
            $first_name = $new_first_name;
            $last_name = $new_last_name;
            $fullName = $first_name . " " . $last_name;
            $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
            $user_email = $new_email;
            $username = $new_username;
            $user_phone = $new_phone;
            $user_address = $new_address;
            $user_bio = $new_bio;
        } else {
            $message = "Error updating profile. Please try again.";
            $message_type = "error";
        }
        $update_stmt->close();
    } else {
        $message = implode(", ", $errors);
        $message_type = "error";
    }
}

$pageTitle = "Edit Profile";
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- External CSS -->
    <link rel="stylesheet" href="css/edit_profile.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn" aria-label="Menu"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area"><i class="fas fa-search"></i><input type="text" placeholder="Search..."></div>
        </div>
        <div class="nav-right">
            <div class="notification-icon"><i class="far fa-bell"></i><?php if ($notificationCount > 0): ?><span class="notification-badge"><?= $notificationCount ?></span><?php endif; ?></div>
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger"><span class="profile-name"><?= htmlspecialchars($fullName) ?></span><div class="profile-avatar"><?= htmlspecialchars($initials) ?></div></div>
                <div class="profile-dropdown" id="profileDropdown"><a href="librarian_profile.php"><i class="fas fa-user"></i> Profile</a><a href="edit_profile.php"><i class="fas fa-edit"></i> Edit Profile</a><hr><a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="logo-sub">LIBRARIAN</div></div>
        <div class="nav-menu">
            <a href="librarian_dashboard.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="#" class="nav-item"><i class="fas fa-archive"></i><span>Archive</span></a>
            <a href="#" class="nav-item"><i class="fas fa-building"></i><span>Departments</span></a>
            <a href="librarian_profile.php" class="nav-item"><i class="fas fa-user-circle"></i><span>My Profile</span></a>
            <a href="edit_profile.php" class="nav-item active"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i><span class="slider"></span></label></div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="edit-profile-container">
            <?php if (!empty($message)) : ?>
                <div class="message <?php echo $message_type; ?>"><i class="fas <?php echo ($message_type === 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i><span><?php echo htmlspecialchars($message); ?></span></div>
            <?php endif; ?>

            <div class="edit-profile-card">
                <div class="edit-profile-header">
                    <h1>Edit Profile</h1>
                    <p>Update your personal information and preferences</p>
                </div>

                <form method="POST" action="">
                    <div class="avatar-section">
                        <div class="avatar-large" id="avatarPreview"><?= htmlspecialchars($initials) ?></div>
                        <button type="button" class="btn-change-avatar" id="changeAvatarBtn"><i class="fas fa-camera"></i> Change Avatar</button>
                        <input type="file" id="avatarInput" accept="image/*" style="display: none;">
                    </div>

                    <div class="form-grid">
                        <div class="form-group"><label><i class="fas fa-user"></i> First Name <span class="required">*</span></label><input type="text" name="first_name" value="<?= htmlspecialchars($first_name) ?>" required></div>
                        <div class="form-group"><label><i class="fas fa-user"></i> Last Name <span class="required">*</span></label><input type="text" name="last_name" value="<?= htmlspecialchars($last_name) ?>" required></div>
                        <div class="form-group"><label><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></label><input type="email" name="email" value="<?= htmlspecialchars($user_email) ?>" required></div>
                        <div class="form-group"><label><i class="fas fa-user-circle"></i> Username <span class="required">*</span></label><input type="text" name="username" value="<?= htmlspecialchars($username) ?>" required></div>
                        <div class="form-group"><label><i class="fas fa-phone"></i> Phone Number</label><input type="tel" name="phone" value="<?= htmlspecialchars($user_phone) ?>"></div>
                        <div class="form-group"><label><i class="fas fa-building"></i> Role</label><input type="text" value="Librarian" disabled readonly><small class="help-text">Role cannot be changed</small></div>
                        <div class="form-group full-width"><label><i class="fas fa-map-marker-alt"></i> Address</label><input type="text" name="address" value="<?= htmlspecialchars($user_address) ?>"></div>
                        <div class="form-group full-width"><label><i class="fas fa-info-circle"></i> Bio / About Me</label><textarea name="bio" rows="4"><?= htmlspecialchars($user_bio) ?></textarea><small class="help-text">Tell us a little about yourself</small></div>
                    </div>

                    <div class="form-actions">
                        <a href="librarian_profile.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        window.userData = {
            fullName: '<?php echo addslashes($fullName); ?>',
            initials: '<?php echo addslashes($initials); ?>',
            email: '<?php echo addslashes($user_email); ?>',
            notificationCount: <?php echo $notificationCount; ?>
        };
    </script>
    
    <!-- External JavaScript -->
    <script src="js/edit_profile.js"></script>
</body>
</html>