<?php
session_start();
include("../config/db.php"); 

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"])) {
    header("Location: ../authentication/login.php");
    exit;
}

$faculty_id = (int)$_SESSION["user_id"];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

$stmt = $conn->prepare("SELECT first_name, last_name, email, contact_number, address, birth_date, profile_picture, role_id FROM user_table WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$faculty) {
    session_destroy();
    header("Location: ../authentication/login.php");
    exit;
}

$first = trim($faculty["first_name"] ?? "");
$last  = trim($faculty["last_name"] ?? "");
$email = trim($faculty["email"] ?? "");
$contact = trim($faculty["contact_number"] ?? "");
$address = trim($faculty["address"] ?? "");
$birthDate = trim($faculty["birth_date"] ?? "");
$role_id = $faculty["role_id"] ?? 3;

$position = "Faculty Member";
if ($role_id == 1) $position = "Administrator";
elseif ($role_id == 2) $position = "Student";
elseif ($role_id == 3) $position = "Faculty Member";
elseif ($role_id == 4) $position = "Dean";

$department = "College of Computer Studies";

$initials = $first && $last ? strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) : "FA";

$unreadCount = 0;
$recentNotifications = [];

try {
    $notif_columns = $conn->query("SHOW COLUMNS FROM notifications");
    $notif_user_column = 'user_id';
    $notif_read_column = 'is_read';
    $notif_message_column = 'message';
    $notif_date_column = 'created_at';
    
    while ($col = $notif_columns->fetch_assoc()) {
        $field = $col['Field'];
        if (strpos($field, 'user') !== false && strpos($field, 'sender') === false) $notif_user_column = $field;
        if (strpos($field, 'read') !== false || strpos($field, 'status') !== false) $notif_read_column = $field;
        if (strpos($field, 'message') !== false) $notif_message_column = $field;
        if (strpos($field, 'created_at') !== false || strpos($field, 'date') !== false) $notif_date_column = $field;
    }
    
    $countQuery = "SELECT COUNT(*) as total FROM notifications WHERE $notif_user_column = ? AND $notif_read_column = 0";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $countResult = $stmt->get_result()->fetch_assoc();
    $unreadCount = $countResult['total'] ?? 0;
    $stmt->close();
    
    $notifQuery = "SELECT $notif_message_column as message, $notif_read_column as is_read, $notif_date_column as created_at
                   FROM notifications WHERE $notif_user_column = ? ORDER BY $notif_date_column DESC LIMIT 5";
    $stmt = $conn->prepare($notifQuery);
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentNotifications[] = $row;
    }
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Notification error: " . $e->getMessage());
    $unreadCount = 0;
    $recentNotifications = [];
}

$successMessage = "";
$errorMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name  = trim($_POST["first_name"] ?? "");
    $last_name   = trim($_POST["last_name"] ?? "");
    $email       = trim($_POST["email"] ?? "");
    $contact_num = trim($_POST["contact_number"] ?? "");
    $address     = trim($_POST["address"] ?? "");
    $birth_date  = trim($_POST["birth_date"] ?? "");

    if ($first_name === "" || $last_name === "" || $email === "") {
        $errorMessage = "First name, last name, and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Invalid email format.";
    } else {
        $newFileName = null;
        if (!empty($_FILES["profile_picture"]["name"])) {
            $file = $_FILES["profile_picture"];
            $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

            if (!in_array($ext, ["jpg", "jpeg", "png"])) {
                $errorMessage = "Only JPG, JPEG or PNG files allowed.";
            } else {
                $uploadDir = __DIR__ . "/../uploads/profile_pictures/";
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $newFileName = "faculty_" . $faculty_id . "_" . time() . "." . $ext;
                $dest = $uploadDir . $newFileName;

                if (!move_uploaded_file($file["tmp_name"], $dest)) {
                    $errorMessage = "Failed to upload picture.";
                    $newFileName = null;
                }
            }
        }

        if (!$errorMessage) {
            if ($newFileName) {
                $sql = "UPDATE user_table SET first_name=?, last_name=?, email=?, contact_number=?, address=?, birth_date=?, profile_picture=?, updated_at=NOW() WHERE user_id=?";
                $upd = $conn->prepare($sql);
                $upd->bind_param("ssssssssi", $first_name, $last_name, $email, $contact_num, $address, $birth_date, $newFileName, $faculty_id);
            } else {
                $sql = "UPDATE user_table SET first_name=?, last_name=?, email=?, contact_number=?, address=?, birth_date=?, updated_at=NOW() WHERE user_id=?";
                $upd = $conn->prepare($sql);
                $upd->bind_param("ssssssi", $first_name, $last_name, $email, $contact_num, $address, $birth_date, $faculty_id);
            }

            if ($upd->execute()) {
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;
                $upd->close();
                header("Location: facultyProfile.php");
                exit;
            } else {
                $errorMessage = "Update failed: " . $upd->error;
                $upd->close();
            }
        }
    }
}

$pageTitle = "Edit Profile";
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= htmlspecialchars($pageTitle) ?> - Theses Archiving System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- External CSS -->
    <link rel="stylesheet" href="css/facultyEditProfile.css">
</head>
<body>
    <div class="overlay" id="overlay"></div>
    <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header"><h2>Theses Archive</h2><p>Faculty Portal</p></div>
        <nav class="sidebar-nav">
            <a href="facultyDashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="facultyProfile.php" class="nav-link"><i class="fas fa-user-circle"></i> Profile</a>
            <a href="reviewThesis.php" class="nav-link"><i class="fas fa-book-reader"></i> Review Theses</a>
            <a href="facultyFeedback.php" class="nav-link"><i class="fas fa-comment-dots"></i> My Feedback</a>
        </nav>
        <div class="sidebar-footer">
            <div class="theme-toggle"><input type="checkbox" id="darkmode" /><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i><span class="slider"></span></label></div>
            <a href="../authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>
    <div class="layout"><main class="main-content">
        <header class="topbar"><div style="display: flex; align-items: center; gap: 1rem;"><div class="hamburger-menu" id="hamburgerBtn"><i class="fas fa-bars"></i></div><h1><?= htmlspecialchars($pageTitle) ?></h1></div>
        <div class="user-info">
            <div class="notification-container"><div class="notification-bell" id="notificationBell"><i class="fas fa-bell"></i><?php if ($unreadCount > 0): ?><span class="notification-badge"><?= $unreadCount ?></span><?php endif; ?></div>
            <div class="notification-dropdown" id="notificationDropdown"><div class="notification-header"><h4>Notifications</h4><a href="#" id="markAllRead">Mark all as read</a></div>
            <div class="notification-list"><?php if (empty($recentNotifications)): ?><div class="notification-item"><div class="no-notifications">No new notifications</div></div>
            <?php else: ?><?php foreach ($recentNotifications as $notif): ?><div class="notification-item <?= isset($notif['is_read']) && !$notif['is_read'] ? 'unread' : '' ?>"><div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div><div class="notif-time"><?= date('M d, h:i A', strtotime($notif['created_at'])) ?></div></div><?php endforeach; ?><?php endif; ?></div>
            <div class="notification-footer"><a href="notifications.php">View all notifications</a></div></div></div>
            <div class="avatar-dropdown"><div class="avatar" id="avatarBtn"><?= htmlspecialchars($initials) ?></div><div class="dropdown-content" id="dropdownMenu"><a href="facultyProfile.php"><i class="fas fa-user-circle"></i> Profile</a><a href="settings.php"><i class="fas fa-cog"></i> Settings</a><hr><a href="../authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div></div>
        </div></header>
        <div class="profile-card edit-card">
            <h2 class="form-title">Update Your Information</h2>
            <?php if ($errorMessage): ?><div class="alert-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>
            <?php if ($successMessage): ?><div class="alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
            <form action="" method="post" enctype="multipart/form-data">
                <div class="avatar-upload-section">
                    <label>Profile Picture</label>
                    <div class="current-avatar" id="preview-container"><div class="avatar-placeholder"><?= htmlspecialchars($initials) ?></div></div>
                    <input type="file" id="avatar" name="profile_picture" accept="image/jpeg,image/png" hidden>
                    <button type="button" class="btn secondary" onclick="document.getElementById('avatar').click()"><i class="fas fa-upload"></i> Choose New Photo</button>
                    <p class="help-text">JPG or PNG • max 2 MB • recommended 200×200 px</p>
                </div>
                <div class="form-grid">
                    <div class="field"><label>First Name</label><input type="text" name="first_name" value="<?= htmlspecialchars($first) ?>" required></div>
                    <div class="field"><label>Last Name</label><input type="text" name="last_name" value="<?= htmlspecialchars($last) ?>" required></div>
                    <div class="field"><label>Email Address</label><input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required></div>
                    <div class="field"><label>Phone Number</label><input type="tel" name="contact_number" value="<?= htmlspecialchars($contact) ?>"></div>
                    <div class="field"><label>Birth Date</label><input type="date" name="birth_date" value="<?= htmlspecialchars($birthDate) ?>"></div>
                    <div class="field"><label>Position / Title</label><input type="text" value="<?= htmlspecialchars($position) ?>" disabled><small style="color:#6E6E6E;">Position cannot be changed</small></div>
                    <div class="field full-width"><label>Address</label><textarea name="address" rows="3"><?= htmlspecialchars($address) ?></textarea></div>
                </div>
                <div class="form-actions"><button type="submit" class="btn primary"><i class="fas fa-save"></i> Save Changes</button><a href="facultyProfile.php" class="btn secondary">Cancel</a></div>
            </form>
        </div>
    </main></div>

    <script>
        window.userData = {
            fullName: '<?php echo addslashes($fullName); ?>',
            initials: '<?php echo addslashes($initials); ?>',
            unreadCount: <?php echo $unreadCount; ?>
        };
    </script>
    
    <!-- External JavaScript -->
    <script src="js/facultyEditProfile.js"></script>
</body>
</html>