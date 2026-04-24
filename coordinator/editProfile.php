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
    $phone = $user_data['phone'] ?? ($user_data['contact'] ?? '');
    $birth_date = $user_data['birth_date'] ?? '';
    $address = $user_data['address'] ?? '';
} else {
    $first_name = '';
    $last_name = '';
    $email = '';
    $username = '';
    $phone = '';
    $birth_date = '';
    $address = '';
}

$userName = trim($first_name . ' ' . $last_name);
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
$currentPage = basename($_SERVER['PHP_SELF']);
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_first_name = trim($_POST['first_name'] ?? '');
    $new_last_name = trim($_POST['last_name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_username = trim($_POST['username'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');
    $new_birth_date = trim($_POST['birth_date'] ?? '');
    $new_address = trim($_POST['address'] ?? '');
    
    // Update user data
    $update_fields = [];
    $update_values = [];
    $types = '';
    
    if (!empty($new_first_name)) {
        $update_fields[] = "first_name = ?";
        $update_values[] = $new_first_name;
        $types .= 's';
    }
    if (!empty($new_last_name)) {
        $update_fields[] = "last_name = ?";
        $update_values[] = $new_last_name;
        $types .= 's';
    }
    if (!empty($new_email)) {
        $update_fields[] = "email = ?";
        $update_values[] = $new_email;
        $types .= 's';
    }
    if (!empty($new_username)) {
        $update_fields[] = "username = ?";
        $update_values[] = $new_username;
        $types .= 's';
    }
    if (in_array('phone', $existing_columns) && !empty($new_phone)) {
        $update_fields[] = "phone = ?";
        $update_values[] = $new_phone;
        $types .= 's';
    }
    if (in_array('contact', $existing_columns) && !empty($new_phone)) {
        $update_fields[] = "contact = ?";
        $update_values[] = $new_phone;
        $types .= 's';
    }
    if (!empty($new_birth_date)) {
        $update_fields[] = "birth_date = ?";
        $update_values[] = $new_birth_date;
        $types .= 's';
    }
    if (!empty($new_address)) {
        $update_fields[] = "address = ?";
        $update_values[] = $new_address;
        $types .= 's';
    }
    
    if (!empty($update_fields)) {
        $update_values[] = $user_id;
        $types .= 'i';
        $update_query = "UPDATE user_table SET " . implode(', ', $update_fields) . " WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param($types, ...$update_values);
        
        if ($update_stmt->execute()) {
            $success_message = "Profile updated successfully!";
            // Refresh data
            $first_name = $new_first_name ?: $first_name;
            $last_name = $new_last_name ?: $last_name;
            $email = $new_email ?: $email;
            $username = $new_username ?: $username;
            $phone = $new_phone ?: $phone;
            $birth_date = $new_birth_date ?: $birth_date;
            $address = $new_address ?: $address;
            $userName = trim($first_name . ' ' . $last_name);
            $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
            
            // Update session
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
        } else {
            $error_message = "Failed to update profile. Please try again.";
        }
        $update_stmt->close();
    }
}

$user_stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Edit Profile | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- External CSS -->
    <link rel="stylesheet" href="css/editProfile.css">
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
                <div class="profile-dropdown" id="profileDropdown"><a href="profile.php"><i class="fas fa-user"></i> Profile</a><a href="editProfile.php"><i class="fas fa-edit"></i> Settings</a><hr><a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="logo-sub"><?php echo strtoupper($role); ?></div></div>
        <div class="nav-menu">
            <a href="coordinatorDashboard.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="reviewThesis.php" class="nav-item"><i class="fas fa-file-alt"></i><span>Review Theses</span></a>
            <a href="myFeedback.php" class="nav-item"><i class="fas fa-comment"></i><span>My Feedback</span></a>
            <a href="forwardedTheses.php" class="nav-item"><i class="fas fa-arrow-right"></i><span>Forwarded to Dean</span></a>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i></label></div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="edit-profile-card">
            <h2>Edit Profile</h2>
            <p class="subtitle">Update your personal information</p>
            
            <?php if ($success_message): ?>
                <div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group"><label>First Name</label><input type="text" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required></div>
                    <div class="form-group"><label>Last Name</label><input type="text" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required></div>
                </div>
                <div class="form-group"><label>Email Address</label><input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required></div>
                <div class="form-group"><label>Username</label><input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>"></div>
                <div class="form-row">
                    <div class="form-group"><label>Phone Number</label><input type="text" name="phone" value="<?php echo htmlspecialchars($phone); ?>"></div>
                    <div class="form-group"><label>Birth Date</label><input type="date" name="birth_date" value="<?php echo htmlspecialchars($birth_date); ?>"></div>
                </div>
                <div class="form-group"><label>Address</label><textarea name="address" rows="3"><?php echo htmlspecialchars($address); ?></textarea></div>
                <div><button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button><a href="profile.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a></div>
            </form>
        </div>
    </main>

    <script>
        window.userData = {
            fullName: '<?php echo addslashes($userName); ?>',
            initials: '<?php echo addslashes($initials); ?>'
        };
    </script>
    
    <!-- External JavaScript -->
    <script src="js/editProfile.js"></script>
</body>
</html>