<?php
// facultyFeedback.php (form to send feedback for a thesis)
session_start();
$userName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$thesisTitle = isset($_GET['title']) ? htmlspecialchars($_GET['title']) : 'Unknown Thesis';
$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'coordinator';
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provide Feedback - Research Coordinator</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- External CSS -->
    <link rel="stylesheet" href="css/R_adviserFeedback.css">
    <link rel="stylesheet" href="css/coordinatorDashboard.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
        </div>
        <div class="nav-right">
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger"><span class="profile-name"><?php echo htmlspecialchars($fullName ?: $userName); ?></span><div class="profile-avatar"><?php echo htmlspecialchars($initials); ?></div></div>
                <div class="profile-dropdown" id="profileDropdown"><a href="profile.php"><i class="fas fa-user"></i> Profile</a><a href="editProfile.php"><i class="fas fa-edit"></i> Edit Profile</a><hr><a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="logo-sub">COORDINATOR</div></div>
        <div class="nav-menu">
            <a href="coordinatorDashboard.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="reviewThesis.php" class="nav-item"><i class="fas fa-file-alt"></i><span>Review Theses</span></a>
            <a href="myFeedback.php" class="nav-item"><i class="fas fa-comment"></i><span>My Feedback</span></a>
            <a href="notification.php" class="nav-item"><i class="fas fa-bell"></i><span>Notifications</span></a>
            <a href="forwardedTheses.php" class="nav-item"><i class="fas fa-arrow-right"></i><span>Forwarded to Dean</span></a>
        </div>
        <div class="nav-footer"><div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-moon"></i> Dark Mode</label></div><a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h2>Provide Revision Feedback</h2>
            <a href="reviewThesis.php?title=<?php echo urlencode($thesisTitle); ?>" class="back-link"><i class="fas fa-arrow-left"></i> Back to Review</a>
        </div>

        <div class="feedback-form-container">
            <p><strong>Thesis:</strong> <?php echo $thesisTitle; ?></p>
            <form action="notification_handler.php" method="post">
                <input type="hidden" name="action" value="send_feedback">
                <input type="hidden" name="thesis" value="<?php echo $thesisTitle; ?>">
                <div class="form-group"><label for="feedback">Feedback Message</label><textarea id="feedback" name="feedback" rows="8" placeholder="Describe the revisions required..." required></textarea></div>
                <div class="form-actions"><button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Send Feedback</button><a href="reviewThesis.php?title=<?php echo urlencode($thesisTitle); ?>" class="btn-cancel">Cancel</a></div>
            </form>
        </div>
    </main>

    <script>
        window.userData = {
            fullName: '<?php echo addslashes($fullName ?: $userName); ?>',
            initials: '<?php echo addslashes($initials); ?>'
        };
    </script>
    
    <!-- External JavaScript -->
    <script src="js/R_adviserFeedback.js"></script>
</body>
</html>