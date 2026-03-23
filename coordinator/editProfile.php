<?php
// editProfile.php
session_start();

$userName = "Camille Joyce Geocall";
$currentPage = basename($_SERVER['PHP_SELF']);

// Profile data (simulate from session or default)
$profile = [
    'first_name' => 'Camille Joyce',
    'last_name'  => 'Geocallo',
    'email'      => 'mylenesellar13@gmail.com',
    'phone'      => '2147483647',
    'birth_date' => '2005-05-12',
    'position'   => 'Faculty Member',
    'address'    => 'San Fernando'
];

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name']);
    $lastName  = trim($_POST['last_name']);
    $email     = trim($_POST['email']);
    $phone     = trim($_POST['phone']);
    $birthDate = trim($_POST['birth_date']);
    $address   = trim($_POST['address']);

    // Validate
    if (empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($birthDate) || empty($address)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        // Handle profile picture upload
        $avatarPath = $profile['avatar'] ?? 'https://via.placeholder.com/120x120?text=AV';
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_pic'];
            $allowedTypes = ['image/jpeg', 'image/png'];
            $maxSize = 2 * 1024 * 1024; // 2MB

            if (!in_array($file['type'], $allowedTypes)) {
                $error = 'Only JPG and PNG images are allowed.';
            } elseif ($file['size'] > $maxSize) {
                $error = 'File size must be less than 2MB.';
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newName = uniqid() . '.' . $ext;
                $uploadDir = 'uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $uploadPath = $uploadDir . $newName;
                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $avatarPath = $uploadPath;
                } else {
                    $error = 'Failed to upload image.';
                }
            }
        }

        if (empty($error)) {
            // Update profile data (in real app, save to DB)
            $_SESSION['profile'] = [
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email,
                'phone'      => $phone,
                'birth_date' => $birthDate,
                'position'   => $profile['position'], // unchanged
                'address'    => $address,
                'avatar'     => $avatarPath
            ];
            // Also update the user name for display
            $_SESSION['userName'] = $firstName . ' ' . $lastName;

            $message = 'Profile updated successfully! Redirecting...';
            header("refresh:2;url=profile.php");
        }
    }
} else {
    // Load existing session data if available
    if (isset($_SESSION['profile'])) {
        $profile = array_merge($profile, $_SESSION['profile']);
        if (isset($_SESSION['userName'])) $userName = $_SESSION['userName'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Research Coordinator</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/editProfile.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
        <div class="logo">Theses Archive · Research Coordinator</div>
        <div class="profile-wrapper" id="profileWrapper">
            <div class="profile-trigger">
                <span class="profile-name"><?php echo htmlspecialchars($userName); ?></span>
                <img src="<?php echo htmlspecialchars($profile['avatar'] ?? 'https://via.placeholder.com/40x40?text=AV'); ?>" class="profile-avatar">
            </div>
            <div class="profile-dropdown" id="profileDropdown">
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <!-- Edit Profile link not needed here; it's already on profile page -->
                <a href="#"><i class="fas fa-cog"></i> Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </header>

    <nav class="side-nav" id="sideNav">
        <ul>
            <li class="<?php echo $currentPage == 'coordinatorDashboard.php' ? 'active' : ''; ?>">
                <a href="coordinatorDashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </li>
            <li class="<?php echo $currentPage == 'reviewThesis.php' ? 'active' : ''; ?>">
                <a href="reviewThesis.php"><i class="fas fa-file-alt"></i> Review Theses</a>
            </li>
            <li class="<?php echo $currentPage == 'myFeedback.php' ? 'active' : ''; ?>">
                <a href="myFeedback.php"><i class="fas fa-comment"></i> My Feedback</a>
            </li>
            <li class="<?php echo $currentPage == 'notification.php' ? 'active' : ''; ?>">
                <a href="notification.php"><i class="fas fa-bell"></i> Notifications</a>
            </li>
            <li class="<?php echo $currentPage == 'forwardedTheses.php' ? 'active' : ''; ?>">
                <a href="forwardedTheses.php"><i class="fas fa-arrow-right"></i> Forwarded to Dean</a>
            </li>
        </ul>
        <div class="side-nav-footer"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </nav>

    <main class="dashboard-main">
        <div class="page-header">
            <h2>Update Your Information</h2>
            <a href="profile.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Profile</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="edit-profile-card">
            <div class="profile-avatar-large">
                <img src="<?php echo htmlspecialchars($profile['avatar'] ?? 'https://via.placeholder.com/120x120?text=AV'); ?>" alt="Avatar">
            </div>
            <form action="editProfile.php" method="post" enctype="multipart/form-data" class="edit-profile-form">
                <div class="form-group">
                    <label for="profile_pic">Profile Picture</label>
                    <input type="file" id="profile_pic" name="profile_pic" accept="image/jpeg,image/png">
                    <small class="form-help">JPG or PNG · max 2 MB · recommended 200×200 px</small>
                </div>

                <div class="form-row">
                    <div class="form-group half">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($profile['first_name']); ?>" required>
                    </div>
                    <div class="form-group half">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($profile['last_name']); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profile['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($profile['phone']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="birth_date">Birth Date</label>
                    <input type="date" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($profile['birth_date']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="position">Position / Title</label>
                    <input type="text" id="position" name="position" value="<?php echo htmlspecialchars($profile['position']); ?>" readonly disabled>
                    <small class="form-help">Position cannot be changed</small>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($profile['address']); ?>" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Save Changes</button>
                    <a href="profile.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Sidebar and dropdown scripts (same as before)
        const hamburger = document.getElementById('hamburgerBtn');
        const sideNav = document.getElementById('sideNav');
        const overlay = document.getElementById('sidebarOverlay');
        function openSidebar() { sideNav.classList.add('open'); overlay.classList.add('active'); }
        function closeSidebar() { sideNav.classList.remove('open'); overlay.classList.remove('active'); }
        hamburger.addEventListener('click', e => { e.stopPropagation(); sideNav.classList.contains('open') ? closeSidebar() : openSidebar(); });
        overlay.addEventListener('click', closeSidebar);
        const profileWrapper = document.getElementById('profileWrapper');
        const profileDropdown = document.getElementById('profileDropdown');
        profileWrapper.addEventListener('click', e => { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
        document.addEventListener('click', () => profileDropdown.classList.remove('show'));
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && sideNav.classList.contains('open')) closeSidebar(); });
    </script>
</body>
</html>