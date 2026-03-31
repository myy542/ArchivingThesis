<?php
session_start();
include("../config/db.php");

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// GET COORDINATOR DATA
$department_name = "Research Department";
$position = "Research Coordinator";
$assigned_date = date('F Y');

// Sample forwarded theses data
$forwardedTheses = [
    [
        'id' => 1,
        'title' => 'Impact of Artificial Intelligence on Modern Education',
        'author' => 'Juan Dela Cruz',
        'date_forwarded' => '2026-03-15',
        'status' => 'Pending',
        'abstract' => 'This study explores how AI technologies are transforming educational practices, focusing on personalized learning and assessment methods.'
    ],
    [
        'id' => 2,
        'title' => 'Blockchain Technology for Secure Voting Systems',
        'author' => 'Maria Santos',
        'date_forwarded' => '2026-03-14',
        'status' => 'Under Review',
        'abstract' => 'This research investigates the application of blockchain technology in creating secure and transparent voting mechanisms.'
    ],
    [
        'id' => 3,
        'title' => 'Sustainable Low-Cost Water Filtration Systems',
        'author' => 'Jose Rizal',
        'date_forwarded' => '2026-03-12',
        'status' => 'Approved',
        'abstract' => 'This paper presents a novel approach to water filtration using locally available materials for rural communities.'
    ],
    [
        'id' => 4,
        'title' => 'Mental Health Awareness Among College Students',
        'author' => 'Ana Reyes',
        'date_forwarded' => '2026-03-10',
        'status' => 'Pending',
        'abstract' => 'This study examines the prevalence of mental health issues and the effectiveness of intervention programs.'
    ],
    [
        'id' => 5,
        'title' => 'Renewable Energy Solutions for Rural Communities',
        'author' => 'Carlos Garcia',
        'date_forwarded' => '2026-03-08',
        'status' => 'Approved',
        'abstract' => 'This research explores sustainable energy solutions for off-grid rural communities.'
    ]
];

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Forwarded to Dean | Thesis Management System</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #fef2f2;
            color: #1f2937;
            overflow-x: hidden;
        }

        /* Top Navigation */
        .top-nav {
            position: fixed;
            top: 0;
            right: 0;
            left: 280px;
            height: 70px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            z-index: 99;
            transition: left 0.3s ease;
            border-bottom: 1px solid #fee2e2;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .hamburger {
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 5px;
            width: 40px;
            height: 40px;
            background: #fef2f2;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .hamburger span {
            display: block;
            width: 22px;
            height: 2px;
            background: #dc2626;
            border-radius: 2px;
        }

        .hamburger:hover {
            background: #fee2e2;
        }

        .logo {
            font-size: 1.3rem;
            font-weight: 700;
            color: #991b1b;
        }

        .logo span {
            color: #dc2626;
        }

        .search-area {
            display: flex;
            align-items: center;
            background: #fef2f2;
            padding: 8px 16px;
            border-radius: 40px;
            gap: 10px;
        }

        .search-area i {
            color: #dc2626;
            font-size: 0.9rem;
        }

        .search-area input {
            border: none;
            background: none;
            outline: none;
            font-size: 0.85rem;
            width: 200px;
            color: #1f2937;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
        }

        /* Profile */
        .profile-wrapper {
            position: relative;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 5px 0;
        }

        .profile-name {
            font-weight: 500;
            color: #1f2937;
            font-size: 0.9rem;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #dc2626, #991b1b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .profile-dropdown {
            position: absolute;
            top: 55px;
            right: 0;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            min-width: 200px;
            display: none;
            overflow: hidden;
            z-index: 100;
            border: 1px solid #fee2e2;
        }

        .profile-dropdown.show {
            display: block;
            animation: fadeSlideDown 0.2s ease;
        }

        @keyframes fadeSlideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .profile-dropdown a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            text-decoration: none;
            color: #1f2937;
            transition: 0.2s;
            font-size: 0.85rem;
        }

        .profile-dropdown a:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        .profile-dropdown a i {
            width: 20px;
            color: #6b7280;
        }

        .profile-dropdown hr {
            margin: 5px 0;
            border-color: #fee2e2;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100%;
            background: linear-gradient(180deg, #991b1b 0%, #dc2626 100%);
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: transform 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }

        .logo-container {
            padding: 28px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
        }

        .logo-container .logo {
            color: white;
            font-size: 1.3rem;
        }

        .logo-container .logo span {
            color: #fecaca;
        }

        .logo-sub {
            font-size: 0.7rem;
            color: #fecaca;
            margin-top: 6px;
            letter-spacing: 1px;
        }

        .nav-menu {
            flex: 1;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 12px;
            text-decoration: none;
            color: #fecaca;
            transition: all 0.2s;
            font-weight: 500;
        }

        .nav-item i {
            width: 22px;
            font-size: 1.1rem;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }

        .nav-item.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .nav-footer {
            padding: 20px 16px;
            border-top: 1px solid rgba(255,255,255,0.15);
        }

        .theme-toggle {
            margin-bottom: 12px;
        }

        .theme-toggle input {
            display: none;
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 8px 0;
        }

        .toggle-label i {
            font-size: 1rem;
            color: #fecaca;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            text-decoration: none;
            color: #fecaca;
            border-radius: 10px;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.15);
            color: white;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 32px;
            transition: margin-left 0.3s ease;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #991b1b;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #dc2626;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 8px 16px;
            background: #fef2f2;
            border-radius: 30px;
            transition: all 0.2s;
        }

        .back-link:hover {
            background: #fee2e2;
            transform: translateX(-3px);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid #fee2e2;
            transition: all 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: #fef2f2;
            color: #dc2626;
        }

        .stat-content h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #111827;
        }

        .stat-content p {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 4px;
        }

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 20px;
            background: white;
            border: 1px solid #fee2e2;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-btn:hover {
            background: #fee2e2;
            color: #dc2626;
        }

        .filter-btn.active {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }

        /* Table Styles */
        .forwarded-list {
            background: white;
            border-radius: 24px;
            border: 1px solid #fee2e2;
            overflow: hidden;
        }

        .forwarded-table {
            width: 100%;
            border-collapse: collapse;
        }

        .forwarded-table th {
            text-align: left;
            padding: 18px 20px;
            background: #fef2f2;
            color: #991b1b;
            font-weight: 600;
            font-size: 0.85rem;
            border-bottom: 1px solid #fee2e2;
        }

        .forwarded-table td {
            padding: 16px 20px;
            color: #1f2937;
            font-size: 0.9rem;
            border-bottom: 1px solid #fef2f2;
            vertical-align: middle;
        }

        .forwarded-table tr:last-child td {
            border-bottom: none;
        }

        .forwarded-table tr:hover {
            background: #fef2f2;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-under-review {
            background: #dbeafe;
            color: #2563eb;
        }

        .status-approved {
            background: #d1fae5;
            color: #059669;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #fef2f2;
            color: #dc2626;
            text-decoration: none;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-view:hover {
            background: #fee2e2;
            transform: translateY(-2px);
        }

        .btn-track {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #dc2626;
            color: white;
            text-decoration: none;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-track:hover {
            background: #991b1b;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 3rem;
            color: #dc2626;
            margin-bottom: 16px;
        }

        .empty-state p {
            color: #6b7280;
            font-size: 0.9rem;
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 99;
            display: none;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .top-nav {
                left: 0;
                padding: 0 16px;
            }
            
            .hamburger {
                display: flex;
            }
            
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .forwarded-table {
                display: block;
                overflow-x: auto;
            }
            
            .search-area {
                display: none;
            }
            
            .profile-name {
                display: none;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 8px;
            }
            
            .btn-view, .btn-track {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 16px;
            }
            
            .forwarded-table th,
            .forwarded-table td {
                padding: 12px 16px;
            }
        }

        /* Dark Mode */
        body.dark-mode {
            background: #1a1a1a;
        }

        body.dark-mode .top-nav {
            background: #2d2d2d;
            border-bottom-color: #991b1b;
        }

        body.dark-mode .logo {
            color: #fecaca;
        }

        body.dark-mode .search-area {
            background: #3d3d3d;
        }

        body.dark-mode .search-area input {
            background: #3d3d3d;
            color: white;
        }

        body.dark-mode .profile-name {
            color: #fecaca;
        }

        body.dark-mode .stat-card {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .stat-content h3 {
            color: #fecaca;
        }

        body.dark-mode .forwarded-list {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .forwarded-table th {
            background: #3d3d3d;
            color: #fecaca;
            border-bottom-color: #991b1b;
        }

        body.dark-mode .forwarded-table td {
            color: #e5e7eb;
            border-bottom-color: #3d3d3d;
        }

        body.dark-mode .forwarded-table tr:hover {
            background: #3d3d3d;
        }

        body.dark-mode .filter-btn {
            background: #2d2d2d;
            border-color: #991b1b;
            color: #9ca3af;
        }

        body.dark-mode .filter-btn.active {
            background: #dc2626;
            color: white;
        }

        body.dark-mode .btn-view {
            background: #3d3d3d;
            color: #fecaca;
        }

        body.dark-mode .profile-dropdown {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .profile-dropdown a {
            color: #e5e7eb;
        }
    </style>
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
                <input type="text" id="searchInput" placeholder="Search forwarded theses...">
            </div>
        </div>
        <div class="nav-right">
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger">
                    <span class="profile-name"><?= htmlspecialchars($fullName) ?></span>
                    <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="editProfile.php"><i class="fas fa-edit"></i> Edit Profile</a>
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
            <div class="logo-sub">RESEARCH COORDINATOR</div>
        </div>
        
        <div class="nav-menu">
            <a href="coordinatorDashboard.php" class="nav-item">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="reviewThesis.php" class="nav-item">
                <i class="fas fa-file-alt"></i>
                <span>Review Theses</span>
            </a>
            <a href="myFeedback.php" class="nav-item">
                <i class="fas fa-comment"></i>
                <span>My Feedback</span>
            </a>
            <a href="forwardedTheses.php" class="nav-item active">
                <i class="fas fa-arrow-right"></i>
                <span>Forwarded to Dean</span>
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
            <h2>Forwarded to Dean</h2>
            <a href="coordinatorDashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-arrow-right"></i></div>
                <div class="stat-content">
                    <h3><?= count($forwardedTheses) ?></h3>
                    <p>Forwarded to Dean</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-content">
                    <h3><?= count(array_filter($forwardedTheses, function($t) { return $t['status'] == 'Pending'; })) ?></h3>
                    <p>Pending Review</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-content">
                    <h3><?= count(array_filter($forwardedTheses, function($t) { return $t['status'] == 'Approved'; })) ?></h3>
                    <p>Approved</p>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="filter-btn active" data-filter="all">All</button>
            <button class="filter-btn" data-filter="pending">Pending</button>
            <button class="filter-btn" data-filter="under-review">Under Review</button>
            <button class="filter-btn" data-filter="approved">Approved</button>
        </div>

        <!-- Table List -->
        <div class="forwarded-list">
            <?php if (empty($forwardedTheses)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No theses forwarded to dean yet.</p>
                </div>
            <?php else: ?>
                <table class="forwarded-table" id="thesesTable">
                    <thead>
                        <tr>
                            <th>Thesis Title</th>
                            <th>Author</th>
                            <th>Date Forwarded</th>
                            <th>Status</th>
                            <th>Action</th>
                        </thead>
                    <tbody>
                        <?php foreach ($forwardedTheses as $thesis): ?>
                            <tr data-status="<?= strtolower(str_replace(' ', '-', $thesis['status'])) ?>">
                                <td><strong><?= htmlspecialchars($thesis['title']) ?></strong></td>
                                <td><?= htmlspecialchars($thesis['author']) ?></td>
                                <td><?= date('M d, Y', strtotime($thesis['date_forwarded'])) ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $thesis['status'])) ?>">
                                        <i class="fas <?= $thesis['status'] == 'Pending' ? 'fa-clock' : ($thesis['status'] == 'Under Review' ? 'fa-spinner' : 'fa-check-circle') ?>"></i>
                                        <?= $thesis['status'] ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <a href="viewThesis.php?id=<?= $thesis['id'] ?>" class="btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="trackThesis.php?id=<?= $thesis['id'] ?>" class="btn-track">
                                        <i class="fas fa-chart-line"></i> Track
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // DOM Elements
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const profileWrapper = document.getElementById('profileWrapper');
        const profileDropdown = document.getElementById('profileDropdown');
        const darkModeToggle = document.getElementById('darkmode');
        const searchInput = document.getElementById('searchInput');
        const filterBtns = document.querySelectorAll('.filter-btn');
        const tableRows = document.querySelectorAll('#thesesTable tbody tr');

        // Toggle Sidebar
        function toggleSidebar() {
            sidebar.classList.toggle('open');
            if (sidebar.classList.contains('open')) {
                sidebarOverlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            } else {
                sidebarOverlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
        }

        // Toggle Profile Dropdown
        function toggleProfileDropdown(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        }

        function closeProfileDropdown(e) {
            if (!profileWrapper.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        }

        // Filter Function
        function filterTable(status) {
            tableRows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Search Function
        function handleSearch() {
            const term = searchInput.value.toLowerCase();
            tableRows.forEach(row => {
                const title = row.cells[0]?.textContent.toLowerCase() || '';
                const author = row.cells[1]?.textContent.toLowerCase() || '';
                
                if (title.includes(term) || author.includes(term)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Dark Mode
        function initDarkMode() {
            const isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) {
                document.body.classList.add('dark-mode');
                if (darkModeToggle) darkModeToggle.checked = true;
                applyDarkMode();
            }
            if (darkModeToggle) {
                darkModeToggle.addEventListener('change', function() {
                    if (this.checked) {
                        document.body.classList.add('dark-mode');
                        localStorage.setItem('darkMode', 'true');
                        applyDarkMode();
                    } else {
                        document.body.classList.remove('dark-mode');
                        localStorage.setItem('darkMode', 'false');
                        removeDarkMode();
                    }
                });
            }
        }

        function applyDarkMode() {
            const style = document.createElement('style');
            style.id = 'darkModeStyle';
            style.textContent = `
                body.dark-mode .stat-card { background: #2d2d2d; border-color: #991b1b; }
                body.dark-mode .stat-content h3 { color: #fecaca; }
                body.dark-mode .forwarded-list { background: #2d2d2d; border-color: #991b1b; }
                body.dark-mode .forwarded-table th { background: #3d3d3d; color: #fecaca; border-bottom-color: #991b1b; }
                body.dark-mode .forwarded-table td { color: #e5e7eb; border-bottom-color: #3d3d3d; }
                body.dark-mode .forwarded-table tr:hover { background: #3d3d3d; }
                body.dark-mode .filter-btn { background: #2d2d2d; border-color: #991b1b; color: #9ca3af; }
                body.dark-mode .filter-btn.active { background: #dc2626; color: white; }
                body.dark-mode .btn-view { background: #3d3d3d; color: #fecaca; }
            `;
            if (!document.getElementById('darkModeStyle')) {
                document.head.appendChild(style);
            }
        }

        function removeDarkMode() {
            const style = document.getElementById('darkModeStyle');
            if (style) style.remove();
        }

        // Event Listeners
        if (hamburgerBtn) {
            hamburgerBtn.addEventListener('click', toggleSidebar);
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }

        if (profileWrapper) {
            profileWrapper.addEventListener('click', toggleProfileDropdown);
            document.addEventListener('click', closeProfileDropdown);
        }

        if (searchInput) {
            searchInput.addEventListener('input', handleSearch);
        }

        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                filterTable(this.dataset.filter);
            });
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initDarkMode();
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
        });
    </script>
</body>
</html>