<?php
session_start();
$userName = "Camille Joyce Geocall";
$currentPage = basename($_SERVER['PHP_SELF']);

// Sample forwarded theses (from coordinator to dean)
$forwardedTheses = [
    ['title' => 'Low‑cost water filter', 'author' => 'Bob Johnson', 'date_forwarded' => 'Feb 25, 2026', 'status' => 'Pending Dean Review'],
    ['title' => 'Impact of AI on education', 'author' => 'John dela Cruz', 'date_forwarded' => 'Feb 22, 2026', 'status' => 'Dean Approved'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forwarded Theses - Coordinator</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/forwardedTheses.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <header class="top-nav">...</header> <!-- same as previous -->
    <nav class="side-nav" id="sideNav">
        <ul>
            <li><a href="coordinatorDashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="reviewThesis.php"><i class="fas fa-file-alt"></i> Review Theses</a></li>
            <li><a href="facultyFeedback.php"><i class="fas fa-comment"></i> Feedback</a></li>
            <li><a href="notification.php"><i class="fas fa-bell"></i> Notifications</a></li>
            <li class="active"><a href="forwardedTheses.php"><i class="fas fa-arrow-right"></i> Forwarded to Dean</a></li>
            <li><a href="ganttChart.php"><i class="fas fa-chart-gantt"></i> Gantt Chart</a></li>
        </ul>
        <div class="side-nav-footer"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </nav>

    <main class="dashboard-main">
        <div class="page-header">
            <h2>Theses Forwarded to Dean</h2>
        </div>
        <div class="forwarded-list">
            <?php if (empty($forwardedTheses)): ?>
                <p class="empty">No theses forwarded yet.</p>
            <?php else: ?>
                <table class="forwarded-table">
                    <thead>
                        <tr>
                            <th>Thesis Title</th>
                            <th>Author</th>
                            <th>Date Forwarded</th>
                            <th>Dean's Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($forwardedTheses as $thesis): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($thesis['title']); ?></td>
                            <td><?php echo htmlspecialchars($thesis['author']); ?></td>
                            <td><?php echo $thesis['date_forwarded']; ?></td>
                            <td><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $thesis['status'])); ?>"><?php echo $thesis['status']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>