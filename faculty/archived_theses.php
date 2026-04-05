<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// GET USER DATA
$user_query = "SELECT user_id, username, email, first_name, last_name, role_id, status FROM user_table WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();

if ($user_data) {
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $fullName = $first_name . " " . $last_name;
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
}

// GET NOTIFICATION COUNT
$notificationCount = 0;
$check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check_table && $check_table->num_rows > 0) {
    $notif_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $notif_stmt = $conn->prepare($notif_query);
    $notif_stmt->bind_param("i", $user_id);
    $notif_stmt->execute();
    $notif_result = $notif_stmt->get_result();
    if ($notif_row = $notif_result->fetch_assoc()) {
        $notificationCount = $notif_row['count'];
    }
    $notif_stmt->close();
}

// Get archived theses from database (status = 'Archived')
$filters = [
    'department' => $_GET['department'] ?? '',
    'year' => $_GET['year'] ?? '',
    'archived_from' => $_GET['archived_from'] ?? '',
    'archived_to' => $_GET['archived_to'] ?? ''
];

// Build query for archived theses (status = 'Archived') - NO archive_log table
$query = "SELECT thesis_id, title, author, department, year, abstract, status, created_at 
          FROM theses 
          WHERE status = 'Archived'";

$params = [];
$types = "";

if (!empty($filters['department'])) {
    $query .= " AND department = ?";
    $params[] = $filters['department'];
    $types .= "s";
}

if (!empty($filters['year'])) {
    $query .= " AND year = ?";
    $params[] = $filters['year'];
    $types .= "s";
}

if (!empty($filters['archived_from'])) {
    $query .= " AND created_at >= ?";
    $params[] = $filters['archived_from'];
    $types .= "s";
}

if (!empty($filters['archived_to'])) {
    $query .= " AND created_at <= ?";
    $params[] = $filters['archived_to'] . ' 23:59:59';
    $types .= "s";
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$archived = $stmt->get_result();

// Get summary statistics
$total_archived = $conn->query("SELECT COUNT(*) as c FROM theses WHERE status = 'Archived'")->fetch_assoc()['c'];

// Get departments list for filter
$dept_query = "SELECT DISTINCT department FROM theses WHERE department IS NOT NULL AND department != '' AND status = 'Archived'";
$dept_result = $conn->query($dept_query);
$departments = [];
if ($dept_result) {
    while ($row = $dept_result->fetch_assoc()) {
        $departments[] = $row['department'];
    }
}

// Handle restore thesis
if (isset($_POST['restore_thesis'])) {
    $thesis_id = $_POST['thesis_id'];
    
    $update_query = "UPDATE theses SET status = 'Pending' WHERE thesis_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $thesis_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = "Thesis restored successfully!";
        header("Location: archived_theses.php");
        exit();
    }
    $update_stmt->close();
}

$pageTitle = "Archived Theses";
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= htmlspecialchars($pageTitle) ?> | Thesis Management System</title>
    
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

        /* Top Navigation - full width */
        .top-nav {
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            height: 70px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            z-index: 99;
            border-bottom: 1px solid #fee2e2;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        /* Hamburger - ALWAYS VISIBLE */
        .hamburger {
            display: flex;
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
        }

        .search-area input {
            border: none;
            background: none;
            outline: none;
            font-size: 0.85rem;
            width: 200px;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Profile Dropdown */
        .profile-wrapper {
            position: relative;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .profile-name {
            font-weight: 500;
            color: #1f2937;
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
            font-size: 1rem;
        }

        .profile-dropdown {
            position: absolute;
            top: 55px;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            min-width: 180px;
            display: none;
            overflow: hidden;
            z-index: 100;
            border: 1px solid #fee2e2;
        }

        .profile-dropdown.show {
            display: block;
        }

        .profile-dropdown a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            text-decoration: none;
            color: #1f2937;
            transition: 0.2s;
        }

        .profile-dropdown a:hover {
            background: #fef2f2;
        }

        .profile-dropdown hr {
            margin: 5px 0;
            border-color: #fee2e2;
        }

        /* Sidebar - COLLAPSIBLE (hidden by default) */
        .sidebar {
            position: fixed;
            top: 0;
            left: -300px;
            width: 280px;
            height: 100%;
            background: linear-gradient(180deg, #991b1b 0%, #dc2626 100%);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: left 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }

        .sidebar.open {
            left: 0;
        }

        .logo-container {
            padding: 28px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
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
            margin-top: 5px;
            text-transform: uppercase;
        }

        .nav-menu {
            flex: 1;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 12px;
            text-decoration: none;
            color: #fecaca;
            transition: 0.2s;
            font-weight: 500;
        }

        .nav-item i {
            width: 22px;
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
            border-top: 1px solid rgba(255,255,255,0.2);
        }

        .theme-toggle {
            margin-bottom: 15px;
        }

        .theme-toggle input {
            display: none;
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .toggle-label i {
            font-size: 1rem;
            color: #fecaca;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            text-decoration: none;
            color: #fecaca;
            border-radius: 10px;
            transition: 0.2s;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.15);
            color: white;
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Main Content - full width */
        .main-content {
            margin-left: 0;
            margin-top: 70px;
            padding: 32px;
            transition: margin-left 0.3s ease;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 32px;
        }

        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #991b1b;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #fee2e2;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(220, 38, 38, 0.1);
        }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            color: #dc2626;
            margin-bottom: 8px;
        }

        .stat-card p {
            font-size: 0.85rem;
            color: #6b7280;
        }

        /* Filter Form */
        .filter-form {
            background: white;
            padding: 24px;
            border-radius: 20px;
            border: 1px solid #fee2e2;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .filter-form select,
        .filter-form input {
            padding: 10px 16px;
            border: 1px solid #fee2e2;
            border-radius: 40px;
            background: #fef2f2;
            font-size: 0.85rem;
            min-width: 150px;
        }

        .filter-form select:focus,
        .filter-form input:focus {
            outline: none;
            border-color: #dc2626;
        }

        .filter-form button {
            background: #dc2626;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }

        .filter-form button:hover {
            background: #991b1b;
            transform: translateY(-2px);
        }

        .filter-form .reset-btn {
            background: #6b7280;
            text-decoration: none;
            color: white;
            padding: 10px 24px;
            border-radius: 40px;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-block;
        }

        .filter-form .reset-btn:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 20px;
            border: 1px solid #fee2e2;
            overflow-x: auto;
            padding: 20px;
        }

        .theses-table {
            width: 100%;
            border-collapse: collapse;
        }

        .theses-table th {
            text-align: left;
            padding: 14px 12px;
            color: #6b7280;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            border-bottom: 1px solid #fee2e2;
        }

        .theses-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #fef2f2;
            font-size: 0.85rem;
            vertical-align: middle;
        }

        .theses-table tr:hover td {
            background: #fef2f2;
        }

        .archived-badge {
            display: inline-block;
            background: #dc2626;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            margin-top: 5px;
        }

        .btn-restore {
            background: #10b981;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.7rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-restore:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #fef2f2;
            color: #dc2626;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            transition: all 0.2s;
            margin-left: 8px;
        }

        .btn-view:hover {
            background: #fee2e2;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #dc2626;
        }

        .alert {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .top-nav {
                padding: 0 16px;
            }
            .main-content {
                padding: 20px;
            }
            .search-area {
                display: none;
            }
            .profile-name {
                display: none;
            }
            .summary-cards {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-form select,
            .filter-form input,
            .filter-form button,
            .filter-form .reset-btn {
                width: 100%;
            }
            .theses-table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 16px;
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
        body.dark-mode .stat-card h3 {
            color: #fecaca;
        }
        body.dark-mode .filter-form {
            background: #2d2d2d;
            border-color: #991b1b;
        }
        body.dark-mode .filter-form select,
        body.dark-mode .filter-form input {
            background: #3d3d3d;
            border-color: #991b1b;
            color: white;
        }
        body.dark-mode .table-container {
            background: #2d2d2d;
            border-color: #991b1b;
        }
        body.dark-mode .theses-table td {
            color: #e5e7eb;
            border-bottom-color: #3d3d3d;
        }
        body.dark-mode .theses-table th {
            color: #9ca3af;
            border-bottom-color: #991b1b;
        }
        body.dark-mode .theses-table tr:hover td {
            background: #3d3d3d;
        }
        body.dark-mode .profile-dropdown {
            background: #2d2d2d;
            border-color: #991b1b;
        }
        body.dark-mode .profile-dropdown a {
            color: #e5e7eb;
        }
        body.dark-mode .btn-view {
            background: #3d3d3d;
            color: #fecaca;
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
                <input type="text" id="searchInput" placeholder="Search...">
            </div>
        </div>
        <div class="nav-right">
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger">
                    <span class="profile-name"><?= htmlspecialchars($fullName) ?></span>
                    <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="facultyProfile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="facultyEditProfile.php"><i class="fas fa-edit"></i> Edit Profile</a>
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
            <div class="logo-sub">RESEARCH ADVISER</div>
        </div>
        
        <div class="nav-menu">
            <a href="facultyDashboard.php" class="nav-item">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="reviewThesis.php" class="nav-item">
                <i class="fas fa-file-alt"></i>
                <span>Review Theses</span>
            </a>
            <a href="facultyFeedback.php" class="nav-item">
                <i class="fas fa-comment-dots"></i>
                <span>My Feedback</span>
            </a>
            <a href="notification.php" class="nav-item">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
            </a>
            <a href="archived_theses.php" class="nav-item active">
                <i class="fas fa-archive"></i>
                <span>Archived Theses</span>
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
            <h1><i class="fas fa-archive"></i> Archived Theses</h1>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="stat-card">
                <h3><?= number_format($total_archived) ?></h3>
                <p>Total Archived Theses</p>
            </div>
            <div class="stat-card">
                <h3><?= number_format($archived->num_rows) ?></h3>
                <p>Filtered Results</p>
            </div>
            <div class="stat-card">
                <h3>Retention</h3>
                <p>Permanent Archive</p>
            </div>
        </div>

        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <!-- Filter Form -->
        <form method="GET" class="filter-form">
            <select name="department">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept) ?>" <?= $filters['department'] == $dept ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dept) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="text" name="year" placeholder="Year (e.g., 2024)" value="<?= htmlspecialchars($filters['year']) ?>">
            
            <input type="date" name="archived_from" placeholder="Archived From" value="<?= htmlspecialchars($filters['archived_from']) ?>">
            <input type="date" name="archived_to" placeholder="Archived To" value="<?= htmlspecialchars($filters['archived_to']) ?>">
            
            <button type="submit"><i class="fas fa-filter"></i> Apply Filters</button>
            <a href="archived_theses.php" class="reset-btn"><i class="fas fa-times"></i> Reset</a>
        </form>

        <!-- Archived Theses Table -->
        <div class="table-container">
            <?php if ($archived->num_rows > 0): ?>
                <table class="theses-table">
                    <thead>
                        <tr>
                            <th>Thesis Title</th>
                            <th>Author</th>
                            <th>Department</th>
                            <th>Year</th>
                            <th>Archived Date</th>
                            <th>Actions</th>
                        </thead>
                    <tbody>
                        <?php while ($thesis = $archived->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($thesis['title']) ?></strong>
                                <div><span class="archived-badge">Archived</span></div>
                            </td>
                            <td><?= htmlspecialchars($thesis['author'] ?? 'Unknown') ?></td>
                            <td><?= htmlspecialchars($thesis['department'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($thesis['year'] ?? 'N/A') ?></td>
                            <td><?= date('M d, Y', strtotime($thesis['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="thesis_id" value="<?= $thesis['thesis_id'] ?>">
                                    <button type="submit" name="restore_thesis" class="btn-restore" onclick="return confirm('Are you sure you want to restore this thesis?')">
                                        <i class="fas fa-undo-alt"></i> Restore
                                    </button>
                                </form>
                                <a href="view_archived.php?id=<?= $thesis['thesis_id'] ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-archive"></i>
                    <p>No archived theses found.</p>
                </div>
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

        // ==================== SIDEBAR FUNCTIONS ====================
        function openSidebar() {
            sidebar.classList.add('open');
            sidebarOverlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
        }

        function toggleSidebar(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (sidebar.classList.contains('open')) closeSidebar();
                if (profileDropdown && profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar();
        });

        // ==================== PROFILE DROPDOWN ====================
        function toggleProfileDropdown(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        }

        function closeProfileDropdown(e) {
            if (!profileWrapper.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        }

        if (profileWrapper) {
            profileWrapper.addEventListener('click', toggleProfileDropdown);
            document.addEventListener('click', closeProfileDropdown);
        }

        // ==================== DARK MODE ====================
        function initDarkMode() {
            const isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) {
                document.body.classList.add('dark-mode');
                if (darkModeToggle) darkModeToggle.checked = true;
            }
            if (darkModeToggle) {
                darkModeToggle.addEventListener('change', function() {
                    if (this.checked) {
                        document.body.classList.add('dark-mode');
                        localStorage.setItem('darkMode', 'true');
                    } else {
                        document.body.classList.remove('dark-mode');
                        localStorage.setItem('darkMode', 'false');
                    }
                });
            }
        }

        // ==================== SEARCH FUNCTION ====================
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const term = this.value.toLowerCase();
                const rows = document.querySelectorAll('.theses-table tbody tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(term) ? '' : 'none';
                });
            });
        }

        // ==================== INITIALIZE ====================
        initDarkMode();
        
        console.log('Archived Theses Page Initialized - Menu Bar Style Sidebar');
    </script>
</body>
</html>