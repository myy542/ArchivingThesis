<?php
// reviewThesis.php
session_start();
$userName = "Camille Joyce Geocall";
$thesisTitle = isset($_GET['title']) ? htmlspecialchars($_GET['title']) : 'Unknown Thesis';

// Sample thesis details (in real app, fetch from DB)
$thesis = [
    'title' => $thesisTitle,
    'author' => 'John dela Cruz',
    'submitted' => 'Mar 01, 2026',
    'abstract' => 'This research explores the impact of artificial intelligence on modern education systems...',
    'file' => 'thesis_sample.pdf'
];

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Thesis - Coordinator</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/reviewThesis.css">
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
            <h2>Review Thesis</h2>
            <a href="coordinatorDashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <div class="thesis-detail-card">
            <h3><?php echo $thesis['title']; ?></h3>
            <p class="meta"><i class="fas fa-user"></i> <?php echo $thesis['author']; ?> | <i class="fas fa-calendar"></i> <?php echo $thesis['submitted']; ?></p>
            <div class="abstract">
                <h4>Abstract</h4>
                <p><?php echo $thesis['abstract']; ?></p>
            </div>
            <div class="file-attachment">
                <i class="fas fa-file-pdf"></i> <a href="#"><?php echo $thesis['file']; ?></a>
            </div>
        </div>

        <div class="action-cards">
            <div class="action-card">
                <h4>Forward to Dean</h4>
                <p>Approve this thesis and forward it to the dean for final guidelines check.</p>
                <form action="forwardedTheses.php" method="post">
                    <input type="hidden" name="thesis" value="<?php echo $thesis['title']; ?>">
                    <button type="submit" class="btn-approve"><i class="fas fa-check"></i> Approve & Forward</button>
                </form>
            </div>
            <div class="action-card">
                <h4>Request Revisions</h4>
                <p>Send feedback to the faculty for revisions.</p>
                <a href="facultyFeedback.php?title=<?php echo urlencode($thesis['title']); ?>" class="btn-revise"><i class="fas fa-edit"></i> Request Revisions</a>
            </div>
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