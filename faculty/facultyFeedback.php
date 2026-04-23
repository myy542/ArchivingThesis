<?php
session_start();
include("../config/db.php");

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is faculty
if (!isset($_SESSION["user_id"])) {
    header("Location: ../authentication/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

// Verify faculty role
$roleQuery = "SELECT role_id FROM user_table WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($roleQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userData || $userData['role_id'] != 3) {
    header("Location: ../authentication/login.php?error=invalid_role");
    exit;
}

$faculty_id = $user_id;

// Get faculty info
$stmt = $conn->prepare("SELECT first_name, last_name FROM user_table WHERE user_id = ?");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
$stmt->close();

$first = $faculty['first_name'] ?? '';
$last = $faculty['last_name'] ?? '';
$fullName = trim($first . ' ' . $last);
$initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));

// Create feedback table if not exists - FIXED: Use thesis_table instead of theses
$createTable = "CREATE TABLE IF NOT EXISTS feedback_table (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    thesis_id INT NOT NULL,
    faculty_id INT NOT NULL,
    comments TEXT NOT NULL,
    feedback_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (thesis_id) REFERENCES thesis_table(thesis_id),
    FOREIGN KEY (faculty_id) REFERENCES user_table(user_id)
)";
$conn->query($createTable);

// Handle form submission - Add new feedback
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_feedback'])) {
    $thesis_id = (int)$_POST['thesis_id'];
    $comments = trim($_POST['comments']);
    
    if (!empty($thesis_id) && !empty($comments)) {
        $insertQuery = "INSERT INTO feedback_table (thesis_id, faculty_id, comments) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("iis", $thesis_id, $faculty_id, $comments);
        
        if ($stmt->execute()) {
            $success = "Feedback added successfully!";
        } else {
            $error = "Error adding feedback: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle edit feedback
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_feedback'])) {
    $feedback_id = (int)$_POST['feedback_id'];
    $comments = trim($_POST['comments']);
    
    if (!empty($feedback_id) && !empty($comments)) {
        $updateQuery = "UPDATE feedback_table SET comments = ? WHERE feedback_id = ? AND faculty_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("sii", $comments, $feedback_id, $faculty_id);
        
        if ($stmt->execute()) {
            $success = "Feedback updated successfully!";
        } else {
            $error = "Error updating feedback: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle delete feedback
if (isset($_GET['delete'])) {
    $feedback_id = (int)$_GET['delete'];
    $deleteQuery = "DELETE FROM feedback_table WHERE feedback_id = ? AND faculty_id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("ii", $feedback_id, $faculty_id);
    
    if ($stmt->execute()) {
        $success = "Feedback deleted successfully!";
    } else {
        $error = "Error deleting feedback";
    }
    $stmt->close();
}

// Get feedback by ID for editing (AJAX)
if (isset($_GET['get_feedback'])) {
    $feedback_id = (int)$_GET['get_feedback'];
    $getQuery = "SELECT feedback_id, thesis_id, comments FROM feedback_table WHERE feedback_id = ? AND faculty_id = ?";
    $stmt = $conn->prepare($getQuery);
    $stmt->bind_param("ii", $feedback_id, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $feedback = $result->fetch_assoc();
    echo json_encode(['success' => true, 'feedback' => $feedback]);
    exit;
}

// Get all feedback by this faculty - FIXED: Use thesis_table instead of theses
$feedbackQuery = "SELECT f.*, t.title as thesis_title, 
                         CONCAT(u.first_name, ' ', u.last_name) as student_name
                  FROM feedback_table f
                  JOIN thesis_table t ON f.thesis_id = t.thesis_id
                  JOIN user_table u ON t.student_id = u.user_id
                  WHERE f.faculty_id = ?
                  ORDER BY f.feedback_date DESC";
$stmt = $conn->prepare($feedbackQuery);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$feedbackList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get pending theses for feedback dropdown - FIXED: Use thesis_table and is_archived
$pendingQuery = "SELECT thesis_id, title FROM thesis_table WHERE (is_archived = 0 OR is_archived IS NULL) ORDER BY date_submitted DESC";
$pendingResult = $conn->query($pendingQuery);
$pendingTheses = $pendingResult->fetch_all(MYSQLI_ASSOC);

$pageTitle = "My Feedback";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= $pageTitle ?> - Theses Archiving System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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

        .sidebar-header {
            padding: 28px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .sidebar-header h2 {
            color: white;
            font-size: 1.3rem;
        }

        .sidebar-header p {
            color: #fecaca;
            font-size: 0.7rem;
            margin-top: 5px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .nav-link {
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

        .nav-link i {
            width: 22px;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }

        .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .sidebar-footer {
            padding: 20px 16px;
            border-top: 1px solid rgba(255,255,255,0.2);
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

        /* Topbar */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #fee2e2;
        }

        .topbar h1 {
            color: #991b1b;
            font-size: 1.5rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1rem;
        }

        /* Feedback Container */
        .feedback-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header-section h2 {
            color: #991b1b;
        }

        .btn-add {
            background: #dc2626;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }

        .btn-add:hover {
            background: #991b1b;
            transform: translateY(-2px);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1100;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-header h2 {
            color: #991b1b;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }

        .close-btn:hover {
            color: #dc2626;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #991b1b;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #fee2e2;
            border-radius: 6px;
            font-size: 1rem;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #dc2626;
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }

        .btn-submit {
            background: #dc2626;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }

        .btn-submit:hover {
            background: #991b1b;
        }

        /* Feedback Cards */
        .feedback-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .feedback-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #fee2e2;
            transition: all 0.3s;
        }

        .feedback-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.1);
            border-color: #dc2626;
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .thesis-info h3 {
            color: #991b1b;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .student-name {
            color: #6b7280;
            font-size: 0.85rem;
        }

        .feedback-date {
            color: #9ca3af;
            font-size: 0.75rem;
        }

        .feedback-comments {
            color: #4b5563;
            line-height: 1.6;
            margin: 1rem 0;
            padding: 1rem 0;
            border-top: 1px solid #fee2e2;
            border-bottom: 1px solid #fee2e2;
        }

        .feedback-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-edit, .btn-delete {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-edit {
            background: #fef2f2;
            color: #dc2626;
        }

        .btn-delete {
            background: #fee2e2;
            color: #b91c1c;
        }

        .btn-edit:hover { 
            background: #fee2e2; 
        }
        .btn-delete:hover { 
            background: #fecaca; 
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            border-left: 4px solid #ef4444;
        }

        .no-feedback {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 12px;
            border: 1px solid #fee2e2;
            color: #6b7280;
        }

        .no-feedback i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dc2626;
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
            .header-section {
                flex-direction: column;
                gap: 1rem;
            }
            .feedback-grid {
                grid-template-columns: 1fr;
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
        body.dark-mode .topbar {
            background: #2d2d2d;
            border-color: #991b1b;
        }
        body.dark-mode .topbar h1 {
            color: #fecaca;
        }
        body.dark-mode .feedback-card {
            background: #2d2d2d;
            border-color: #991b1b;
        }
        body.dark-mode .thesis-info h3 {
            color: #fecaca;
        }
        body.dark-mode .feedback-comments {
            color: #cbd5e1;
        }
        body.dark-mode .profile-dropdown {
            background: #2d2d2d;
            border-color: #991b1b;
        }
        body.dark-mode .profile-dropdown a {
            color: #fecaca;
        }
        body.dark-mode .modal-content {
            background: #2d2d2d;
        }
        body.dark-mode .modal-header h2 {
            color: #fecaca;
        }
        body.dark-mode .form-group label {
            color: #fecaca;
        }
        body.dark-mode .form-group select,
        body.dark-mode .form-group textarea {
            background: #3d3d3d;
            border-color: #991b1b;
            color: white;
        }
        body.dark-mode .no-feedback {
            background: #2d2d2d;
            border-color: #991b1b;
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
                <input type="text" placeholder="Search...">
            </div>
        </div>
        <div class="nav-right">
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger">
                    <span class="profile-name"><?= htmlspecialchars($fullName) ?></span>
                    <div class="profile-avatar"><?= $initials ?></div>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="editProfile.php"><i class="fas fa-edit"></i> Edit Profile</a>
                    <hr>
                    <a href="../authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>Thesis Manager</h2>
            <p>Faculty Portal</p>
        </div>
        <nav class="sidebar-nav">
            <a href="facultyDashboard.php" class="nav-link">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="reviewThesis.php" class="nav-link">
                <i class="fas fa-book-reader"></i> Review Theses
            </a>
            <a href="facultyFeedback.php" class="nav-link active">
                <i class="fas fa-comment-dots"></i> My Feedback
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="../authentication/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <h1>My Feedback</h1>
            <div class="user-info">
                <div class="avatar"><?= $initials ?></div>
            </div>
        </div>

        <div class="feedback-container">
            <div class="header-section">
                <h2>Feedback History</h2>
                <button class="btn-add" onclick="openModal()">
                    <i class="fas fa-plus"></i> Add New Feedback
                </button>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>

            <?php if (empty($feedbackList)): ?>
                <div class="no-feedback">
                    <i class="fas fa-comment-dots"></i>
                    <h3>No feedback yet</h3>
                    <p>Click "Add New Feedback" to start providing feedback on theses.</p>
                </div>
            <?php else: ?>
                <div class="feedback-grid">
                    <?php foreach ($feedbackList as $feedback): ?>
                        <div class="feedback-card">
                            <div class="feedback-header">
                                <div class="thesis-info">
                                    <h3><?= htmlspecialchars($feedback['thesis_title']) ?></h3>
                                    <div class="student-name">
                                        <i class="fas fa-user"></i> 
                                        <?= htmlspecialchars($feedback['student_name']) ?>
                                    </div>
                                </div>
                                <div class="feedback-date">
                                    <i class="fas fa-calendar"></i> 
                                    <?= date('M d, Y', strtotime($feedback['feedback_date'])) ?>
                                </div>
                            </div>
                            
                            <div class="feedback-comments">
                                <?= nl2br(htmlspecialchars($feedback['comments'])) ?>
                            </div>
                            
                            <div class="feedback-actions">
                                <button class="btn-edit" onclick="editFeedback(<?= $feedback['feedback_id'] ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn-delete" onclick="deleteFeedback(<?= $feedback['feedback_id'] ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add Feedback Modal -->
    <div class="modal" id="feedbackModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Feedback</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="add_feedback" value="1">
                
                <div class="form-group">
                    <label>Select Thesis</label>
                    <select name="thesis_id" required>
                        <option value="">-- Choose a thesis --</option>
                        <?php foreach ($pendingTheses as $thesis): ?>
                            <option value="<?= $thesis['thesis_id'] ?>">
                                <?= htmlspecialchars($thesis['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Feedback Comments</label>
                    <textarea name="comments" placeholder="Enter your feedback here..." required></textarea>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Submit Feedback
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Feedback Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Feedback</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="edit_feedback" value="1">
                <input type="hidden" name="feedback_id" id="edit_feedback_id">
                
                <div class="form-group">
                    <label>Feedback Comments</label>
                    <textarea name="comments" id="edit_comments" rows="6" required></textarea>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Update Feedback
                </button>
            </form>
        </div>
    </div>

    <script>
        // DOM Elements
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const profileWrapper = document.getElementById('profileWrapper');
        const profileDropdown = document.getElementById('profileDropdown');
        const darkModeToggle = document.getElementById('darkmode');

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
                if (document.getElementById('feedbackModal').classList.contains('show')) closeModal();
                if (document.getElementById('editModal').classList.contains('show')) closeEditModal();
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

        // ==================== MODAL FUNCTIONS ====================
        function openModal() {
            document.getElementById('feedbackModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('feedbackModal').classList.remove('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        function deleteFeedback(id) {
            if (confirm('Are you sure you want to delete this feedback?')) {
                window.location.href = '?delete=' + id;
            }
        }

        function editFeedback(id) {
            fetch('?get_feedback=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_feedback_id').value = data.feedback.feedback_id;
                        document.getElementById('edit_comments').value = data.feedback.comments;
                        document.getElementById('editModal').classList.add('show');
                    } else {
                        alert('Error loading feedback');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading feedback');
                });
        }

        window.onclick = function(event) {
            const addModal = document.getElementById('feedbackModal');
            const editModal = document.getElementById('editModal');
            if (event.target == addModal) {
                closeModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }

        // ==================== INITIALIZE ====================
        initDarkMode();
        
        console.log('Faculty Feedback Page Initialized - Using thesis_table');
    </script>
</body>
</html>