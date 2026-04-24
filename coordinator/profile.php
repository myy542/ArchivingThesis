<?php
session_start();
include("../config/db.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'coordinator';

// First, check what columns exist in the user_table
$columns_query = "SHOW COLUMNS FROM user_table";
$columns_result = $conn->query($columns_query);
$existing_columns = [];
if ($columns_result) {
    while ($col = $columns_result->fetch_assoc()) {
        $existing_columns[] = $col['Field'];
    }
}

// Build query based on existing columns
$select_fields = ['first_name', 'last_name', 'email'];
if (in_array('username', $existing_columns)) $select_fields[] = 'username';
if (in_array('phone', $existing_columns)) $select_fields[] = 'phone';
if (in_array('contact', $existing_columns)) $select_fields[] = 'contact';
if (in_array('birth_date', $existing_columns)) $select_fields[] = 'birth_date';
if (in_array('address', $existing_columns)) $select_fields[] = 'address';
if (in_array('role_id', $existing_columns)) $select_fields[] = 'role_id';
if (in_array('created_at', $existing_columns)) $select_fields[] = 'created_at';

$user_query = "SELECT " . implode(', ', $select_fields) . " FROM user_table WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();

if ($user_data) {
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $email = $user_data['email'];
    $username = $user_data['username'] ?? '';
    $phone = $user_data['phone'] ?? ($user_data['contact'] ?? 'Not provided');
    $birth_date = $user_data['birth_date'] ?? 'Not provided';
    $address = $user_data['address'] ?? 'Not provided';
    $member_since = isset($user_data['created_at']) ? date('F Y', strtotime($user_data['created_at'])) : 'March 2026';
} else {
    $first_name = 'User';
    $last_name = '';
    $email = 'Not provided';
    $username = '';
    $phone = 'Not provided';
    $birth_date = 'Not provided';
    $address = 'Not provided';
    $member_since = 'March 2026';
}

$userName = trim($first_name . ' ' . $last_name);
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
$currentPage = basename($_SERVER['PHP_SELF']);

// Get position based on role
if ($role == 'coordinator') {
    $position = 'Research Coordinator';
} elseif ($role == 'faculty') {
    $position = 'Faculty Member';
} elseif ($role == 'student') {
    $position = 'Student';
} else {
    $position = 'User';
}

$user_stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>My Profile | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- External CSS -->
    <link rel="stylesheet" href="css/profile.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area"><i class="fas fa-search"></i><input type="text" placeholder="Search..."></div>
        </div>
        <div class="nav-right">
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger"><span class="profile-name"><?php echo htmlspecialchars($userName); ?></span><div class="profile-avatar"><?php echo htmlspecialchars($initials); ?></div></div>
                <div class="profile-dropdown" id="profileDropdown"><a href="profile.php"><i class="fas fa-user"></i> Profile</a><a href="editProfile.php"><i class="fas fa-edit"></i> Edit Profile</a><hr><a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="logo-sub"><?php echo strtoupper($role); ?></div></div>
        <div class="nav-menu">
            <a href="coordinatorDashboard.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="reviewThesis.php" class="nav-item"><i class="fas fa-file-alt"></i><span>Review Theses</span></a>
            <a href="myFeedback.php" class="nav-item"><i class="fas fa-comment"></i><span>My Feedback</span></a>
            <a href="notification.php" class="nav-item"><i class="fas fa-bell"></i><span>Notifications</span></a>
            <a href="forwardedTheses.php" class="nav-item"><i class="fas fa-arrow-right"></i><span>Forwarded to Dean</span></a>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i></label></div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="profile-container">
            <div class="profile-sidebar">
                <div class="profile-avatar-large"><?php echo htmlspecialchars($initials); ?></div>
                <h3><?php echo htmlspecialchars($userName); ?></h3>
                <p><?php echo htmlspecialchars($position); ?></p>
                <div class="member-since"><i class="far fa-calendar-alt"></i> Member since: <?php echo $member_since; ?></div>
            </div>

            <div class="profile-main">
                <h2>Personal Information</h2>
                <div class="info-grid">
                    <div class="info-item"><span class="info-label">Full Name</span><span class="info-value"><?php echo htmlspecialchars($userName); ?></span></div>
                    <div class="info-item"><span class="info-label">Email Address</span><span class="info-value"><?php echo htmlspecialchars($email); ?></span></div>
                    <div class="info-item"><span class="info-label">Username</span><span class="info-value"><?php echo htmlspecialchars($username ?: $first_name); ?></span></div>
                    <div class="info-item"><span class="info-label">Phone Number</span><span class="info-value"><?php echo htmlspecialchars($phone); ?></span></div>
                    <div class="info-item"><span class="info-label">Birth Date</span><span class="info-value"><?php echo htmlspecialchars($birth_date); ?></span></div>
                    <div class="info-item"><span class="info-label">Address</span><span class="info-value"><?php echo htmlspecialchars($address); ?></span></div>
                </div>

                <div class="about-section"><h3>About Me</h3><p><?php echo htmlspecialchars($position); ?> dedicated to academic excellence and research development. Committed to providing quality thesis management and guidance to students.</p></div>

                <a href="editProfile.php" class="btn-edit"><i class="fas fa-edit"></i> Edit Profile</a>
                <a href="changepass.php" class="change-pass-btn"><i class="fas fa-key"></i> Change Password</a>
            </div>
        </div>
    </main>

    <script>
        window.userData = {
            fullName: '<?php echo addslashes($userName); ?>',
            initials: '<?php echo addslashes($initials); ?>',
            role: '<?php echo addslashes($role); ?>'
        };
    </script>
    
    <!-- External JavaScript -->
    <script src="js/profile.js"></script>
</body>
</html>