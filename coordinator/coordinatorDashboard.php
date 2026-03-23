<?php
// coordinatorDashboard.php
session_start();

// Simulate logged-in user
$userName = "Camille Joyce Geocall";
$userGreeting = "Camille Joyce Geocall!";

// Sample theses data with statuses reflecting the correct workflow
$allSubmissions = [
    'pending_coordinator' => [
        ['title' => 'Impact of AI on education', 'author' => 'John dela Cruz', 'date' => 'Mar 01, 2026'],
        ['title' => 'Blockchain for voting', 'author' => 'Jane Smith', 'date' => 'Feb 28, 2026']
    ],
    'forwarded_to_dean' => [
        ['title' => 'Low‑cost water filter', 'author' => 'Bob Johnson', 'date' => 'Feb 25, 2026']
    ],
    'rejected' => [
        ['title' => 'Test Title', 'author' => 'mylene raganas', 'date' => 'Feb 20, 2026']
    ]
];

// Compute statistics
$stats = [
    'forwarded' => count($allSubmissions['forwarded_to_dean']),
    'rejected'  => count($allSubmissions['rejected']),
    'pending'   => count($allSubmissions['pending_coordinator'])
];

$pendingTheses = $allSubmissions['pending_coordinator'];

// Flatten all theses for the main table
$allThesesWithStatus = [];
foreach ($allSubmissions as $status => $theses) {
    foreach ($theses as $thesis) {
        $thesis['status'] = $status;
        $allThesesWithStatus[] = $thesis;
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Coordinator Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <!-- Pass data to JavaScript -->
    <div id="chartData" 
         data-pending="<?php echo $stats['pending']; ?>"
         data-forwarded="<?php echo $stats['forwarded']; ?>"
         data-rejected="<?php echo $stats['rejected']; ?>">
    </div>

    <!-- Overlay for sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Top Navigation -->
    <header class="top-nav">
        <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <div class="logo">Theses Archive</div>
        <div class="profile-wrapper" id="profileWrapper">
            <div class="profile-trigger">
                <span class="profile-name"><?php echo $userName; ?></span>
                <img src="https://via.placeholder.com/40x40?text=AV" alt="Profile" class="profile-avatar">
            </div>
            <div class="profile-dropdown" id="profileDropdown">
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="#"><i class="fas fa-cog"></i> Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </header>

    <!-- Side Navigation -->
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
        <div class="side-nav-footer">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="dashboard-main">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <h1>Research Coordinator Dashboard</h1>
            <p>Welcome, <?php echo $userGreeting; ?> Here's an overview of theses pending your review.</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card approved">
                <span class="stat-value"><?php echo $stats['forwarded']; ?></span>
                <span class="stat-label">Forwarded to Dean</span>
            </div>
            <div class="stat-card rejected">
                <span class="stat-value"><?php echo $stats['rejected']; ?></span>
                <span class="stat-label">Rejected</span>
            </div>
            <div class="stat-card pending">
                <span class="stat-value"><?php echo $stats['pending']; ?></span>
                <span class="stat-label">Pending Review</span>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-container">
            <!-- Status Distribution Chart -->
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Thesis Status Distribution</h3>
                <div class="chart-wrapper">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            
            <!-- Monthly Submissions Chart -->
            <div class="chart-card">
                <h3><i class="fas fa-chart-line"></i> Monthly Thesis Submissions</h3>
                <div class="chart-wrapper">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Theses Waiting for Review -->
        <section class="waiting-review">
            <h3>Theses Waiting for Your Review</h3>
            <?php if (empty($pendingTheses)): ?>
                <p class="empty">No pending theses.</p>
            <?php else: ?>
                <?php foreach ($pendingTheses as $thesis): ?>
                    <div class="thesis-item">
                        <div class="thesis-info">
                            <strong><?php echo htmlspecialchars($thesis['title']); ?></strong>
                            <span class="meta"><?php echo htmlspecialchars($thesis['author']); ?> | <?php echo $thesis['date']; ?></span>
                        </div>
                        <a href="reviewThesis.php?title=<?php echo urlencode($thesis['title']); ?>" class="review-btn">Review →</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- All Thesis Submissions -->
        <section class="all-submissions">
            <h3>All Thesis Submissions</h3>
            <div class="search-bar">
                <input type="text" id="searchInput" placeholder="Search theses...">
                <button type="submit" id="searchBtn"><i class="fas fa-search"></i> Search</button>
            </div>
            <table class="theses-table">
                <thead>
                    <tr>
                        <th>Thesis Title</th>
                        <th>Author</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="thesisTableBody">
                    <?php foreach ($allThesesWithStatus as $thesis): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($thesis['title']); ?></td>
                        <td><?php echo htmlspecialchars($thesis['author']); ?></td>
                        <td><?php echo $thesis['date']; ?></td>
                        <td><span class="status-badge status-<?php echo $thesis['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $thesis['status'])); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>

    <script src="js/coordinatorDashboard.js"></script>
</body>
</html>