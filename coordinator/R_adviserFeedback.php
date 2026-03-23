<?php
// facultyFeedback.php (form to send feedback for a thesis)
session_start();
$userName = "Camille Joyce Geocall";
$thesisTitle = isset($_GET['title']) ? htmlspecialchars($_GET['title']) : 'Unknown Thesis';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provide Feedback - Research Coordinator</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/adviserFeedback.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
        <div class="logo">Theses Archive</div>
        <div class="profile-wrapper" id="profileWrapper">
            <div class="profile-trigger">
                <span class="profile-name"><?php echo $userName; ?></span>
                <img src="https://via.placeholder.com/40x40?text=AV" alt="Profile" class="profile-avatar">
            </div>
            <div class="profile-dropdown" id="profileDropdown">
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="editProfile.php"><i class="fas fa-edit"></i> Edit Profile</a>
                <a href="#"><i class="fas fa-cog"></i> Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </header>

    <nav class="side-nav" id="sideNav">
        <ul>
            <li><a href="coordinatorDashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="reviewThesis.php"><i class="fas fa-file-alt"></i> Review Theses</a></li>
            <li><a href="myFeedback.php"><i class="fas fa-comment"></i> My Feedback</a></li>
            <li><a href="notification.php"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a href="forwardedTheses.php"><i class="fas fa-arrow-right"></i> Forwarded to Dean</a></li>
        </ul>
        <div class="side-nav-footer"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </nav>

    <main class="dashboard-main">
        <div class="page-header">
            <h2>Provide Revision Feedback</h2>
            <a href="reviewThesis.php?title=<?php echo urlencode($thesisTitle); ?>" class="back-link"><i class="fas fa-arrow-left"></i> Back to Review</a>
        </div>

        <div class="feedback-form-container">
            <p><strong>Thesis:</strong> <?php echo $thesisTitle; ?></p>
            <form action="notification_handler.php" method="post">
                <input type="hidden" name="action" value="send_feedback">
                <input type="hidden" name="thesis" value="<?php echo $thesisTitle; ?>">
                <div class="form-group">
                    <label for="feedback">Feedback Message</label>
                    <textarea id="feedback" name="feedback" rows="8" placeholder="Describe the revisions required..." required></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Send Feedback</button>
                    <a href="reviewThesis.php?title=<?php echo urlencode($thesisTitle); ?>" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </main>

    <script>
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