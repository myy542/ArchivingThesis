<?php
// myFeedback.php
session_start();
$userName = "Camille Joyce Geocall";
$currentPage = basename($_SERVER['PHP_SELF']);

// Sample feedback data (static)
$feedbacks = [
    ['id' => 1, 'thesis' => 'Impact of AI on education', 'message' => 'Please revise the methodology section.', 'date' => '2026-03-01', 'read' => false],
    ['id' => 2, 'thesis' => 'Blockchain for voting', 'message' => 'Add more recent references.', 'date' => '2026-02-28', 'read' => false],
    ['id' => 3, 'thesis' => 'Low-cost water filter', 'message' => 'Your abstract needs to be more concise.', 'date' => '2026-02-27', 'read' => true],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Feedback - Coordinator</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/myFeedback.css">
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
                <img src="https://via.placeholder.com/40x40?text=AV" class="profile-avatar">
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
            <h2>My Feedback</h2>
        </div>

        <div class="feedback-list">
            <?php if (empty($feedbacks)): ?>
                <p class="empty">No feedback sent yet.</p>
            <?php else: ?>
                <?php foreach ($feedbacks as $feedback): ?>
                    <div class="feedback-item <?php echo $feedback['read'] ? 'read' : 'unread'; ?>">
                        <div class="feedback-header">
                            <strong><?php echo htmlspecialchars($feedback['thesis']); ?></strong>
                            <span class="date"><?php echo $feedback['date']; ?></span>
                        </div>
                        <div class="feedback-message">
                            <i class="fas fa-quote-left"></i>
                            <p><?php echo htmlspecialchars($feedback['message']); ?></p>
                        </div>
                        <div class="feedback-actions">
                            <span class="status-badge <?php echo $feedback['read'] ? 'read' : 'unread'; ?>">
                                <?php echo $feedback['read'] ? 'Read' : 'Unread'; ?>
                            </span>
                            <a href="notification_handler.php?action=delete_feedback&id=<?php echo $feedback['id']; ?>" class="delete-btn" onclick="return confirm('Delete this feedback?');"><i class="fas fa-trash"></i> Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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