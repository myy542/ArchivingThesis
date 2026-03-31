<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION - CHECK IF USER IS LOGGED IN AND IS A COORDINATOR
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

// GET LOGGED-IN USER INFO FROM SESSION
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// GET USER DATA FROM DATABASE
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
    $user_email = $user_data['email'];
    $username = $user_data['username'];
}

// GET COORDINATOR DATA
$department_name = "Research Department";
$position = "Research Coordinator";
$assigned_date = date('F Y');

// Sample archive data (replace with database queries)
$archivedTheses = [
    [
        'title' => 'AI in Education',
        'author' => 'Maria Santos',
        'submitted_date' => '2026-03-01',
        'status' => 'Archived',
        'type' => 'submitted'
    ],
    [
        'title' => 'Blockchain Voting System',
        'author' => 'Juan Dela Cruz',
        'submitted_date' => '2026-02-28',
        'status' => 'Archived',
        'type' => 'feedback'
    ],
    [
        'title' => 'Low-cost Water Filter',
        'author' => 'Ana Reyes',
        'submitted_date' => '2026-02-27',
        'status' => 'Rejected',
        'type' => 'rejected'
    ],
    [
        'title' => 'Impact of AI on Society',
        'author' => 'Mark Santiago',
        'submitted_date' => '2026-02-25',
        'status' => 'Approved',
        'type' => 'approved'
    ],
    [
        'title' => 'Mobile Learning Application',
        'author' => 'Lisa Garcia',
        'submitted_date' => '2026-02-20',
        'status' => 'Archived',
        'type' => 'submitted'
    ],
    [
        'title' => 'IoT Based Classroom Monitor',
        'author' => 'Pedro Reyes',
        'submitted_date' => '2026-02-15',
        'status' => 'Archived',
        'type' => 'submitted'
    ]
];

// Activities data
$activities = [
    [
        'icon' => 'fa-file-alt',
        'description' => 'New thesis submitted: "AI in Education"',
        'date' => '2026-03-01',
        'time' => '10:23 AM',
        'type' => 'submitted'
    ],
    [
        'icon' => 'fa-comment',
        'description' => 'Feedback received from student on "Blockchain Voting"',
        'date' => '2026-02-28',
        'time' => '02:15 PM',
        'type' => 'feedback'
    ],
    [
        'icon' => 'fa-times-circle',
        'description' => 'Thesis "Low-cost Water Filter" was rejected',
        'date' => '2026-02-27',
        'time' => '11:45 AM',
        'type' => 'rejected'
    ],
    [
        'icon' => 'fa-check-circle',
        'description' => 'Dean approved forwarded thesis "Impact of AI"',
        'date' => '2026-02-25',
        'time' => '09:30 AM',
        'type' => 'approved'
    ],
    [
        'icon' => 'fa-archive',
        'description' => 'Thesis "Mobile Learning App" archived',
        'date' => '2026-02-22',
        'time' => '04:00 PM',
        'type' => 'archived'
    ],
    [
        'icon' => 'fa-user-plus',
        'description' => 'New student registered: Camille Joyce Geocall',
        'date' => '2026-02-20',
        'time' => '01:20 PM',
        'type' => 'student'
    ]
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Thesis Archive | Research Coordinator</title>
    
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

        .search-area input::placeholder {
            color: #9ca3af;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
        }

        /* Notification */
        .notification-container {
            position: relative;
        }

        .notification-icon {
            position: relative;
            cursor: pointer;
            width: 40px;
            height: 40px;
            background: #fef2f2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .notification-icon:hover {
            background: #fee2e2;
        }

        .notification-icon i {
            font-size: 1.2rem;
            color: #dc2626;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            font-size: 0.6rem;
            font-weight: 600;
            min-width: 18px;
            height: 18px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }

        .notification-dropdown {
            position: absolute;
            top: 55px;
            right: 0;
            width: 320px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: none;
            overflow: hidden;
            z-index: 100;
            border: 1px solid #fee2e2;
        }

        .notification-dropdown.show {
            display: block;
            animation: fadeSlideDown 0.2s ease;
        }

        .notification-header {
            padding: 16px 20px;
            border-bottom: 1px solid #fee2e2;
        }

        .notification-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #991b1b;
        }

        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }

        .notification-item {
            display: flex;
            gap: 12px;
            padding: 12px 20px;
            border-bottom: 1px solid #fef2f2;
            cursor: pointer;
            transition: background 0.2s;
        }

        .notification-item:hover {
            background: #fef2f2;
        }

        .notif-icon {
            width: 32px;
            height: 32px;
            background: #fef2f2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dc2626;
        }

        .notif-content {
            flex: 1;
        }

        .notif-message {
            font-size: 0.8rem;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .notif-time {
            font-size: 0.65rem;
            color: #9ca3af;
        }

        .notification-footer {
            padding: 12px 20px;
            border-top: 1px solid #fee2e2;
            text-align: center;
        }

        .notification-footer a {
            color: #dc2626;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
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
            background: linear-gradient(135deg, #dc2626, #5b3b3b);
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
            gap: 4px;
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
            margin-bottom: 32px;
        }

        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #991b1b;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header h1 i {
            color: #dc2626;
            font-size: 1.6rem;
        }

        .page-header p {
            color: #6b7280;
            font-size: 0.9rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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

        .stat-info h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #ba0202;
        }

        .stat-info p {
            font-size: 0.8rem;
            color: #6b7280;
        }

        /* Two Column Layout */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }

        /* Recent Activities */
        .activities-card {
            background: white;
            border-radius: 24px;
            padding: 24px;
            border: 1px solid #fee2e2;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-header h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #991b1b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .activities-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .activity-item {
            display: flex;
            gap: 14px;
            padding: 12px;
            background: #fef2f2;
            border-radius: 16px;
            transition: all 0.2s;
        }

        .activity-item:hover {
            background: #fee2e2;
            transform: translateX(5px);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dc2626;
            font-size: 1.1rem;
        }

        .activity-content {
            flex: 1;
        }

        .activity-description {
            font-size: 0.85rem;
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .activity-date {
            font-size: 0.7rem;
            color: #9ca3af;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .activity-date i {
            font-size: 0.65rem;
        }

        /* Archived Theses Table */
        .archive-card {
            background: white;
            border-radius: 24px;
            padding: 24px;
            border: 1px solid #fee2e2;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: #fef2f2;
            padding: 8px 16px;
            border-radius: 40px;
            gap: 10px;
        }

        .search-bar i {
            color: #dc2626;
            font-size: 0.9rem;
        }

        .search-bar input {
            border: none;
            background: none;
            outline: none;
            font-size: 0.85rem;
            width: 200px;
            color: #1f2937;
        }

        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
        }

        .archive-table {
            width: 100%;
            border-collapse: collapse;
        }

        .archive-table th {
            text-align: left;
            padding: 12px 8px;
            color: #6b7280;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            border-bottom: 1px solid #fee2e2;
        }

        .archive-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #fef2f2;
            font-size: 0.85rem;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .status-badge.archived {
            background: #e5e7eb;
            color: #4b5563;
        }

        .status-badge.approved {
            background: #d1fae5;
            color: #059669;
        }

        .status-badge.rejected {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: #fef2f2;
            color: #dc2626;
            text-decoration: none;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-view:hover {
            background: #fee2e2;
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 12px;
            color: #dc2626;
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
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .two-columns {
                grid-template-columns: 1fr;
            }
        }

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
            
            .search-area {
                display: none;
            }
            
            .profile-name {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 16px;
            }
            
            .stat-card {
                padding: 16px;
            }
            
            .stat-icon {
                width: 45px;
                height: 45px;
                font-size: 1.2rem;
            }
            
            .stat-info h3 {
                font-size: 1.4rem;
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

        body.dark-mode .stat-card,
        body.dark-mode .activities-card,
        body.dark-mode .archive-card {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .stat-info h3 {
            color: #fecaca;
        }

        body.dark-mode .activity-item {
            background: #3d3d3d;
        }

        body.dark-mode .activity-item:hover {
            background: #4a4a4a;
        }

        body.dark-mode .activity-description {
            color: #e5e7eb;
        }

        body.dark-mode .archive-table td {
            color: #e5e7eb;
            border-bottom-color: #3d3d3d;
        }

        body.dark-mode .archive-table th {
            color: #9ca3af;
            border-bottom-color: #991b1b;
        }

        body.dark-mode .profile-dropdown,
        body.dark-mode .notification-dropdown {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .profile-dropdown a {
            color: #e5e7eb;
        }

        body.dark-mode .profile-dropdown a:hover {
            background: #3d3d3d;
        }

        body.dark-mode .btn-view {
            background: #3d3d3d;
            color: #fecaca;
        }

        body.dark-mode .btn-view:hover {
            background: #4a4a4a;
        }

        body.dark-mode .search-bar {
            background: #3d3d3d;
        }

        body.dark-mode .search-bar input {
            background: #3d3d3d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search archive...">
            </div>
        </div>
        <div class="nav-right">
            <div class="notification-container">
                <div class="notification-icon" id="notificationIcon">
                    <i class="far fa-bell"></i>
                    <span class="notification-badge">3</span>
                </div>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                    </div>
                    <div class="notification-list">
                        <div class="notification-item">
                            <div class="notif-icon"><i class="fas fa-file-alt"></i></div>
                            <div class="notif-content">
                                <div class="notif-message">New thesis submitted: "AI in Education"</div>
                                <div class="notif-time"><i class="far fa-clock"></i> Mar 1, 2026</div>
                            </div>
                        </div>
                        <div class="notification-item">
                            <div class="notif-icon"><i class="fas fa-comment"></i></div>
                            <div class="notif-content">
                                <div class="notif-message">Feedback received from student</div>
                                <div class="notif-time"><i class="far fa-clock"></i> Feb 28, 2026</div>
                            </div>
                        </div>
                    </div>
                    <div class="notification-footer">
                        <a href="#">View all notifications <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>

            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger">
                    <span class="profile-name"><?= htmlspecialchars($fullName) ?></span>
                    <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="editProfile.php"><i class="fas fa-edit"></i> Edit Profile</a>
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
            <a href="archive.php" class="nav-item active">
                <i class="fas fa-archive"></i>
                <span>Archive</span>
            </a>
            <a href="forwardedTheses.php" class="nav-item">
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
            <h1>
                <i class="fas fa-archive"></i> 
                Thesis Archive
            </h1>
            <p>View and manage all archived thesis projects</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
                <div class="stat-info">
                    <h3><?= count($archivedTheses) ?></h3>
                    <p>Total Archived</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?= count(array_filter($archivedTheses, fn($t) => $t['status'] == 'Approved')) ?></h3>
                    <p>Approved Theses</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stat-info">
                    <h3><?= count(array_filter($archivedTheses, fn($t) => $t['status'] == 'Rejected')) ?></h3>
                    <p>Rejected Theses</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-info">
                    <h3><?= date('Y') ?></h3>
                    <p>Current Year</p>
                </div>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="two-columns">
            <!-- Recent Activities -->
            <div class="activities-card">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Recent Activities</h2>
                    <a href="#" class="btn-view" style="font-size: 0.7rem;">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="activities-list">
                    <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas <?= $activity['icon'] ?>"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-description"><?= htmlspecialchars($activity['description']) ?></div>
                            <div class="activity-date">
                                <i class="far fa-calendar-alt"></i> <?= date('M d, Y', strtotime($activity['date'])) ?>
                                <i class="far fa-clock"></i> <?= $activity['time'] ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Archive Stats Summary -->
            <div class="archive-card">
                <div class="card-header">
                    <h2><i class="fas fa-chart-pie"></i> Archive Summary</h2>
                </div>
                <div style="margin-top: 10px;">
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-size: 0.8rem; color: #6b7280;">Archived Theses</span>
                            <span style="font-weight: 600; color: #dc2626;"><?= count($archivedTheses) ?></span>
                        </div>
                        <div class="progress-bar" style="height: 8px; background: #fef2f2; border-radius: 10px;">
                            <div class="progress-fill" style="width: 100%; height: 100%; background: #dc2626; border-radius: 10px;"></div>
                        </div>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-size: 0.8rem; color: #6b7280;">Approved Rate</span>
                            <span style="font-weight: 600; color: #059669;">
                                <?= round((count(array_filter($archivedTheses, fn($t) => $t['status'] == 'Approved')) / max(1, count($archivedTheses))) * 100) ?>%
                            </span>
                        </div>
                        <div class="progress-bar" style="height: 8px; background: #fef2f2; border-radius: 10px;">
                            <div class="progress-fill" style="width: <?= round((count(array_filter($archivedTheses, fn($t) => $t['status'] == 'Approved')) / max(1, count($archivedTheses))) * 100) ?>%; height: 100%; background: #10b981; border-radius: 10px;"></div>
                        </div>
                    </div>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-size: 0.8rem; color: #6b7280;">Rejection Rate</span>
                            <span style="font-weight: 600; color: #dc2626;">
                                <?= round((count(array_filter($archivedTheses, fn($t) => $t['status'] == 'Rejected')) / max(1, count($archivedTheses))) * 100) ?>%
                            </span>
                        </div>
                        <div class="progress-bar" style="height: 8px; background: #fef2f2; border-radius: 10px;">
                            <div class="progress-fill" style="width: <?= round((count(array_filter($archivedTheses, fn($t) => $t['status'] == 'Rejected')) / max(1, count($archivedTheses))) * 100) ?>%; height: 100%; background: #ef4444; border-radius: 10px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Archived Theses Table -->
        <div class="archive-card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> Archived Theses</h2>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="archiveSearchInput" placeholder="Search by title or author...">
                </div>
            </div>
            <div class="table-responsive">
                <table class="archive-table" id="archiveTable">
                    <thead>
                        <tr>
                            <th>Thesis Title</th>
                            <th>Author</th>
                            <th>Date Archived</th>
                            <th>Status</th>
                            <th>Action</th>
                        </thead>
                    <tbody id="archiveTableBody">
                        <?php foreach ($archivedTheses as $thesis): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($thesis['title']) ?></strong></td>
                            <td><?= htmlspecialchars($thesis['author']) ?></td>
                            <td><?= date('M d, Y', strtotime($thesis['submitted_date'])) ?></td>
                            <td>
                                <span class="status-badge <?= strtolower($thesis['status']) ?>">
                                    <?= $thesis['status'] ?>
                                </span>
                            </td>
                            <td>
                                <a href="viewThesis.php?id=<?= urlencode($thesis['title']) ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // DOM Elements
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const profileWrapper = document.getElementById('profileWrapper');
        const profileDropdown = document.getElementById('profileDropdown');
        const notificationIcon = document.getElementById('notificationIcon');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const darkModeToggle = document.getElementById('darkmode');
        const archiveSearchInput = document.getElementById('archiveSearchInput');
        const archiveTableBody = document.getElementById('archiveTableBody');

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
            if (notificationDropdown) notificationDropdown.classList.remove('show');
        }

        function closeProfileDropdown(e) {
            if (profileWrapper && !profileWrapper.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        }

        // Toggle Notification Dropdown
        function toggleNotificationDropdown(e) {
            e.stopPropagation();
            if (notificationDropdown) {
                notificationDropdown.classList.toggle('show');
                if (profileDropdown) profileDropdown.classList.remove('show');
            }
        }

        function closeNotificationDropdown(e) {
            const notificationContainer = document.querySelector('.notification-container');
            if (notificationContainer && !notificationContainer.contains(e.target)) {
                if (notificationDropdown) notificationDropdown.classList.remove('show');
            }
        }

        // Dark Mode
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

        // Search functionality
        function initSearch() {
            if (archiveSearchInput && archiveTableBody) {
                archiveSearchInput.addEventListener('input', function() {
                    const term = this.value.toLowerCase();
                    const rows = archiveTableBody.querySelectorAll('tr');
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(term) ? '' : 'none';
                    });
                });
            }
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

        if (notificationIcon) {
            notificationIcon.addEventListener('click', toggleNotificationDropdown);
            document.addEventListener('click', closeNotificationDropdown);
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initDarkMode();
            initSearch();
            
            // Close sidebar on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (sidebar.classList.contains('open')) closeSidebar();
                    if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
                    if (notificationDropdown && notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show');
                }
            });
            
            // Close sidebar when window is resized to desktop
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
        });
    </script>
</body>
</html>