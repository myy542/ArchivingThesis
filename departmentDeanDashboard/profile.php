<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION - CHECK IF USER IS LOGGED IN AND IS A DEAN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'dean') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// GET USER DATA FROM DATABASE - REMOVED created_at column
$user_query = "SELECT user_id, username, email, first_name, last_name, role_id, status, contact_number, address, birth_date FROM user_table WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

if ($user_data) {
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $fullName = $first_name . " " . $last_name;
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
    $user_email = $user_data['email'];
    $username = $user_data['username'];
    $user_phone = $user_data['contact_number'] ?? 'Not provided';
    $user_address = $user_data['address'] ?? 'Not provided';
    $user_birth_date = $user_data['birth_date'] ?? '';
    $user_role_id = $user_data['role_id'];
    $user_status = $user_data['status'];
}

// GET DEPARTMENT INFO
$department_id = isset($_GET['dept_id']) ? intval($_GET['dept_id']) : 1;
$department_name = "College of Arts and Sciences";
$department_code = "CAS";

$dept_query = "SELECT department_id, department_name, department_code FROM department_table WHERE department_id = ?";
$dept_stmt = $conn->prepare($dept_query);
$dept_stmt->bind_param("i", $department_id);
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result();
if ($dept_row = $dept_result->fetch_assoc()) {
    $department_name = $dept_row['department_name'];
    $department_code = $dept_row['department_code'];
}
$dept_stmt->close();

// GET STATISTICS FOR DEAN
$stats = [];

// Total projects reviewed (all theses)
$projects_query = "SELECT COUNT(*) as count FROM thesis_table";
$projects_result = $conn->query($projects_query);
$stats['projects_reviewed'] = ($projects_result && $projects_result->num_rows > 0) ? ($projects_result->fetch_assoc())['count'] : 0;

// Total theses approved (is_archived = 0 = active/pending)
$approved_query = "SELECT COUNT(*) as count FROM thesis_table WHERE is_archived = 0";
$approved_result = $conn->query($approved_query);
$stats['theses_approved'] = ($approved_result && $approved_result->num_rows > 0) ? ($approved_result->fetch_assoc())['count'] : 0;

// Total faculty members
$faculty_query = "SELECT COUNT(*) as count FROM user_table WHERE role_id = 3 AND status = 'Active'";
$faculty_result = $conn->query($faculty_query);
$stats['faculty_supervised'] = ($faculty_result && $faculty_result->num_rows > 0) ? ($faculty_result->fetch_assoc())['count'] : 0;

// Years of experience - default 5 since wala'y created_at
$stats['years_experience'] = 5;

// GET RECENT ACTIVITIES
$recent_activities = [];

// Get recent thesis submissions
$thesis_activities = $conn->query("SELECT thesis_id, title, date_submitted FROM thesis_table ORDER BY date_submitted DESC LIMIT 3");
if ($thesis_activities && $thesis_activities->num_rows > 0) {
    while ($row = $thesis_activities->fetch_assoc()) {
        $recent_activities[] = [
            'icon' => 'file-alt',
            'action' => 'New thesis submitted',
            'title' => substr($row['title'], 0, 50) . (strlen($row['title']) > 50 ? '...' : ''),
            'date' => date('M d, Y', strtotime($row['date_submitted']))
        ];
    }
}

// Get recent feedback/comments
$feedback_activities = $conn->query("SELECT f.*, t.title FROM feedback_table f JOIN thesis_table t ON f.thesis_id = t.thesis_id ORDER BY f.feedback_date DESC LIMIT 2");
if ($feedback_activities && $feedback_activities->num_rows > 0) {
    while ($row = $feedback_activities->fetch_assoc()) {
        $recent_activities[] = [
            'icon' => 'comment',
            'action' => 'New feedback',
            'title' => substr($row['title'], 0, 50) . (strlen($row['title']) > 50 ? '...' : ''),
            'date' => date('M d, Y', strtotime($row['feedback_date']))
        ];
    }
}

// Sort activities by date (most recent first)
usort($recent_activities, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
$recent_activities = array_slice($recent_activities, 0, 5);

// GET NOTIFICATION COUNT
$notificationCount = 0;
$notif_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($notif_check && $notif_check->num_rows > 0) {
    $col_check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_read'");
    if ($col_check && $col_check->num_rows > 0) {
        $n = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
        $n->bind_param("i", $user_id);
        $n->execute();
        $result = $n->get_result();
        if ($row = $result->fetch_assoc()) {
            $notificationCount = $row['c'];
        }
        $n->close();
    }
}

// Member since - default date since wala'y created_at
$user_join_date = date('F Y');
$user_role = "Department Dean";
$user_bio = "Experienced academic leader dedicated to promoting research excellence and innovation. Committed to fostering a collaborative environment for faculty and students.";

$pageTitle = "My Profile";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= htmlspecialchars($pageTitle) ?> | Thesis Management System</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- External CSS -->
    <link rel="stylesheet" href="css/profile.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn">
                <span></span><span></span><span></span>
            </button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search...">
            </div>
        </div>
        <div class="nav-right">
            <div class="notification-icon">
                <i class="far fa-bell"></i>
                <?php if ($notificationCount > 0): ?>
                    <span class="notification-badge"><?= $notificationCount ?></span>
                <?php endif; ?>
            </div>
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger">
                    <span class="profile-name"><?= htmlspecialchars($fullName) ?></span>
                    <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="#"><i class="fas fa-cog"></i> Settings</a>
                    <hr>
                    <a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container">
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="logo-sub">DEPARTMENT DEAN</div>
        </div>
        <div class="nav-menu">
            <a href="dean.php?section=dashboard" class="nav-item">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="dean.php?section=department" class="nav-item">
                <i class="fas fa-building"></i>
                <span>Department</span>
            </a>
            <a href="dean.php?section=faculty" class="nav-item">
                <i class="fas fa-user-tie"></i>
                <span>Faculty</span>
            </a>
            <a href="dean.php?section=students" class="nav-item">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
            </a>
            <a href="dean.php?section=projects" class="nav-item">
                <i class="fas fa-project-diagram"></i>
                <span>Projects</span>
            </a>
            <a href="dean.php?section=archive" class="nav-item">
                <i class="fas fa-archive"></i>
                <span>Archived</span>
            </a>
            <a href="dean.php?section=reports" class="nav-item">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle">
                <input type="checkbox" id="darkmode">
                <label for="darkmode" class="toggle-label">
                    <i class="fas fa-sun"></i>
                    <i class="fas fa-moon"></i>
                </label>
            </div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-user-circle"></i> My Profile</h1>
            <p>View and manage your personal information</p>
        </div>

        <div class="profile-container">
            <!-- Left Column -->
            <div class="profile-left">
                <div class="profile-card">
                    <div class="profile-avatar-large">
                        <?= htmlspecialchars($initials) ?>
                    </div>
                    <h2><?= htmlspecialchars($fullName) ?></h2>
                    <p class="user-role"><?= $user_role ?></p>
                    <p class="user-department"><?= htmlspecialchars($department_name) ?></p>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($stats['projects_reviewed']) ?></div>
                            <div class="stat-label">Projects Reviewed</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($stats['theses_approved']) ?></div>
                            <div class="stat-label">Theses Approved</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($stats['faculty_supervised']) ?></div>
                            <div class="stat-label">Faculty</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($stats['years_experience']) ?></div>
                            <div class="stat-label">Years Exp.</div>
                        </div>
                    </div>
                    
                    <div class="profile-actions">
                        <button class="btn-edit" id="editProfileBtn">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                        <a href="change_password.php" class="btn-change-password" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fas fa-key"></i> Change Password
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="profile-right">
                <!-- Personal Information -->
                <div class="info-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                        <button class="btn-icon" id="editPersonalBtn">
                            <i class="fas fa-pen"></i>
                        </button>
                    </div>
                    <div class="info-content">
                        <div class="info-row">
                            <span class="info-label">Full Name:</span>
                            <span class="info-value" id="displayName"><?= htmlspecialchars($fullName) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email Address:</span>
                            <span class="info-value" id="displayEmail"><?= htmlspecialchars($user_email) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Username:</span>
                            <span class="info-value"><?= htmlspecialchars($username) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone Number:</span>
                            <span class="info-value" id="displayPhone"><?= htmlspecialchars($user_phone) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Address:</span>
                            <span class="info-value" id="displayAddress"><?= htmlspecialchars($user_address) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Birth Date:</span>
                            <span class="info-value" id="displayBirthDate"><?= $user_birth_date ? date('F d, Y', strtotime($user_birth_date)) : 'Not provided' ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Department:</span>
                            <span class="info-value"><?= htmlspecialchars($department_name) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Role:</span>
                            <span class="info-value"><?= $user_role ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Status:</span>
                            <span class="info-value"><?= htmlspecialchars($user_status) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Member Since:</span>
                            <span class="info-value"><?= $user_join_date ?></span>
                        </div>
                    </div>
                </div>

                <!-- Bio -->
                <div class="info-card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> About Me</h3>
                        <button class="btn-icon" id="editBioBtn">
                            <i class="fas fa-pen"></i>
                        </button>
                    </div>
                    <div class="info-content">
                        <p class="bio-text" id="displayBio"><?= htmlspecialchars($user_bio) ?></p>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="info-card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Recent Activity</h3>
                        <a href="#" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="activity-list">
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-<?= $activity['icon'] ?>"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-action"><?= htmlspecialchars($activity['action']) ?></div>
                                <div class="activity-title"><?= htmlspecialchars($activity['title']) ?></div>
                                <div class="activity-time"><?= htmlspecialchars($activity['date']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Profile Modal -->
    <div class="modal" id="editProfileModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Profile</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editProfileForm" method="POST" action="update_profile.php">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" id="editName" name="full_name" value="<?= htmlspecialchars($fullName) ?>">
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" id="editEmail" name="email" value="<?= htmlspecialchars($user_email) ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" id="editPhone" name="contact_number" value="<?= htmlspecialchars($user_phone) ?>">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" id="editAddress" name="address" value="<?= htmlspecialchars($user_address) ?>">
                    </div>
                    <div class="form-group">
                        <label>Birth Date</label>
                        <input type="date" id="editBirthDate" name="birth_date" value="<?= htmlspecialchars($user_birth_date) ?>">
                    </div>
                    <div class="form-group">
                        <label>Bio</label>
                        <textarea id="editBio" name="bio" rows="4"><?= htmlspecialchars($user_bio) ?></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel">Cancel</button>
                <button class="btn-save" onclick="saveProfile()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Pass PHP variables to JavaScript -->
    <script>
        window.userData = {
            fullName: '<?= htmlspecialchars($fullName) ?>',
            initials: '<?= htmlspecialchars($initials) ?>',
            email: '<?= htmlspecialchars($user_email) ?>',
            phone: '<?= htmlspecialchars($user_phone) ?>',
            address: '<?= htmlspecialchars($user_address) ?>',
            birthDate: '<?= htmlspecialchars($user_birth_date) ?>',
            bio: '<?= htmlspecialchars($user_bio) ?>'
        };
    </script>
    
    <!-- External JavaScript -->
    <script src="js/profile.js"></script>
</body>
</html>