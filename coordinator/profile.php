<?php
// profile.php
session_start();

// Load profile data from session or use defaults
$profile = [
    'first_name' => 'Camille Joyce',
    'last_name'  => 'Geocallo',
    'email'      => 'mylenesellar13@gmail.com',
    'phone'      => '2147483647',
    'birth_date' => '2005-05-12',
    'position'   => 'Faculty Member',
    'address'    => 'San Fernando'
];

if (isset($_SESSION['profile'])) {
    $profile = array_merge($profile, $_SESSION['profile']);
}

$userName = $profile['first_name'] . ' ' . $profile['last_name'];
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Research Coordinator</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/profile.css">
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
                <img src="https://via.placeholder.com/40x40?text=AV" class="profile-avatar">
            </div>
            <div class="profile-dropdown" id="profileDropdown">
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
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
            <h2>My Profile</h2>
        </div>

        <div class="profile-card">
            <div class="profile-details">
                <h3 class="profile-name-heading"><?php echo htmlspecialchars($userName); ?></h3>
                
                <div class="profile-field">
                    <span class="field-label">Email</span>
                    <span class="field-value"><?php echo htmlspecialchars($profile['email']); ?></span>
                </div>
                
                <div class="profile-field">
                    <span class="field-label">Contact</span>
                    <span class="field-value"><?php echo htmlspecialchars($profile['phone']); ?></span>
                </div>
                
                <div class="profile-field">
                    <span class="field-label">Address</span>
                    <span class="field-value"><?php echo htmlspecialchars($profile['address']); ?></span>
                </div>
                
                <div class="profile-field">
                    <span class="field-label">Birth Date</span>
                    <span class="field-value"><?php echo htmlspecialchars($profile['birth_date']); ?></span>
                </div>
                
                <div class="profile-field">
                    <span class="field-label">Position / Title</span>
                    <span class="field-value"><?php echo htmlspecialchars($profile['position']); ?></span>
                </div>
                
                <div class="profile-actions">
                    <a href="editProfile.php" class="edit-profile-btn"><i class="fas fa-edit"></i> Edit Profile</a>
                </div>
            </div>
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