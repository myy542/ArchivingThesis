<?php
session_start();
include("../config/db.php");
include("../config/archive_manager.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"])) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$archiveManager = new ArchiveManager($conn);
$user_id = (int)$_SESSION["user_id"];

// Get user data
$user_query = "SELECT first_name, last_name, email FROM user_table WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

if (!$user) {
    session_destroy();
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$first = trim($user["first_name"] ?? "");
$last  = trim($user["last_name"] ?? "");
$displayName = trim($first . " " . $last);
$initials = $first && $last ? strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) : "U";

// Create thesis_invitations table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS thesis_invitations (
    invitation_id INT AUTO_INCREMENT PRIMARY KEY,
    thesis_id INT NOT NULL,
    invited_user_id INT NOT NULL,
    invited_by INT NOT NULL,
    invitation_status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (thesis_id),
    INDEX (invited_user_id),
    INDEX (invitation_status)
)");

// Create thesis_collaborators table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS thesis_collaborators (
    collaborator_id INT AUTO_INCREMENT PRIMARY KEY,
    thesis_id INT NOT NULL,
    user_id INT NOT NULL,
    role VARCHAR(50) DEFAULT 'co-author',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (thesis_id),
    INDEX (user_id),
    UNIQUE KEY unique_collaborator (thesis_id, user_id)
)");

// Get notification count - using is_read
$notificationCount = 0;
$notif_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
if ($notif_row = $notif_result->fetch_assoc()) {
    $notificationCount = $notif_row['count'];
}
$notif_stmt->close();

// Handle form submission
$successMessage = "";
$formErrors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_thesis'])) {
    $title = trim($_POST['title'] ?? '');
    $abstract = trim($_POST['abstract'] ?? '');
    $keywords = trim($_POST['keywords'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $adviser = trim($_POST['adviser'] ?? '');
    $invite_emails = trim($_POST['invite_emails'] ?? '');
    
    // Validation
    $errors = [];
    if (empty($title)) $errors[] = "Thesis title is required.";
    if (strlen($title) < 5) $errors[] = "Title must be at least 5 characters.";
    if (empty($abstract)) $errors[] = "Abstract is required.";
    if (strlen($abstract) < 50) $errors[] = "Abstract must be at least 50 characters.";
    if (empty($keywords)) $errors[] = "Keywords are required.";
    if (empty($department)) $errors[] = "Department is required.";
    if (empty($year)) $errors[] = "Year is required.";
    if (empty($adviser)) $errors[] = "Adviser name is required.";
    
    // File validation
    if (empty($_FILES["manuscript"]["name"])) {
        $errors[] = "Manuscript file is required.";
    } else {
        $file = $_FILES["manuscript"];
        $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        if ($ext !== "pdf") $errors[] = "Only PDF files are allowed.";
        if ($file["size"] > 10 * 1024 * 1024) $errors[] = "File size must not exceed 10MB.";
    }
    
    if (empty($errors)) {
        $uploadDir = __DIR__ . "/../uploads/manuscripts/";
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $timestamp = time();
        $safeTitle = preg_replace('/[^a-zA-Z0-9]/', '_', $title);
        $safeTitle = substr($safeTitle, 0, 50);
        $newFileName = $timestamp . '_' . $safeTitle . '.pdf';
        $uploadPath = $uploadDir . $newFileName;
        
        if (move_uploaded_file($file["tmp_name"], $uploadPath)) {
            chmod($uploadPath, 0644);
            $dbFilePath = 'uploads/manuscripts/' . $newFileName;
            
            // INSERT into thesis_table
            $insertQuery = "INSERT INTO thesis_table (student_id, title, abstract, keywords, department, year, adviser, file_path, date_submitted, is_archived) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param("isssssss", $user_id, $title, $abstract, $keywords, $department, $year, $adviser, $dbFilePath);
            
            if ($insertStmt->execute()) {
                $thesis_id = $insertStmt->insert_id;
                $insertStmt->close();
                
                // Add the creator as collaborator (owner)
                $collabQuery = "INSERT INTO thesis_collaborators (thesis_id, user_id, role) VALUES (?, ?, 'owner')";
                $collabStmt = $conn->prepare($collabQuery);
                $collabStmt->bind_param("ii", $thesis_id, $user_id);
                $collabStmt->execute();
                $collabStmt->close();
                
                // ==================== SEND NOTIFICATION TO FACULTY ====================
                $facultyQuery = "SELECT user_id FROM user_table WHERE role_id = 3";
                $facultyResult = $conn->query($facultyQuery);
                
                if ($facultyResult && $facultyResult->num_rows > 0) {
                    $studentName = $displayName;
                    $shortTitle = strlen($title) > 50 ? substr($title, 0, 50) . '...' : $title;
                    $message = "📢 New thesis submission from " . $studentName . ": \"" . $shortTitle . "\"";
                    $link = "../faculty/reviewThesis.php?id=" . $thesis_id;
                    
                    while ($faculty = $facultyResult->fetch_assoc()) {
                        $notifSql = "INSERT INTO notifications (user_id, thesis_id, message, type, link, is_read, created_at) 
                                    VALUES (?, ?, ?, 'thesis_submission', ?, 0, NOW())";
                        $notifStmt = $conn->prepare($notifSql);
                        $notifStmt->bind_param("iiss", $faculty['user_id'], $thesis_id, $message, $link);
                        $notifStmt->execute();
                        $notifStmt->close();
                    }
                }
                
                // Process invited co-authors
                $invited_count = 0;
                $invited_list = [];
                $invite_errors = [];
                
                if (!empty($invite_emails)) {
                    $emails = array_map('trim', explode(',', $invite_emails));
                    
                    foreach ($emails as $email) {
                        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            // Check if user exists
                            $userCheck = $conn->prepare("SELECT user_id, first_name, last_name, email FROM user_table WHERE email = ?");
                            $userCheck->bind_param("s", $email);
                            $userCheck->execute();
                            $invited_user = $userCheck->get_result()->fetch_assoc();
                            $userCheck->close();
                            
                            if ($invited_user && $invited_user['user_id'] != $user_id) {
                                // Check if already invited
                                $checkInvite = $conn->prepare("SELECT * FROM thesis_invitations WHERE thesis_id = ? AND invited_user_id = ?");
                                $checkInvite->bind_param("ii", $thesis_id, $invited_user['user_id']);
                                $checkInvite->execute();
                                $existing = $checkInvite->get_result()->fetch_assoc();
                                $checkInvite->close();
                                
                                if (!$existing) {
                                    // Send invitation
                                    $inviteQuery = "INSERT INTO thesis_invitations (thesis_id, invited_user_id, invited_by, invitation_status) VALUES (?, ?, ?, 'pending')";
                                    $inviteStmt = $conn->prepare($inviteQuery);
                                    $inviteStmt->bind_param("iii", $thesis_id, $invited_user['user_id'], $user_id);
                                    $inviteStmt->execute();
                                    $inviteStmt->close();
                                    
                                    // Send notification to invited user
                                    $notifMessage = "📢 " . $displayName . " invited you to collaborate on thesis: \"" . $title . "\"";
                                    $notifQuery = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'thesis_invitation', 0, NOW())";
                                    $notifStmt = $conn->prepare($notifQuery);
                                    $notifStmt->bind_param("iis", $invited_user['user_id'], $thesis_id, $notifMessage);
                                    $notifStmt->execute();
                                    $notifStmt->close();
                                    
                                    $invited_count++;
                                    $invited_list[] = $invited_user['email'];
                                }
                            } elseif ($invited_user && $invited_user['user_id'] == $user_id) {
                                $invite_errors[] = "You cannot invite yourself as a co-author.";
                            } else {
                                $invite_errors[] = "User with email '" . $email . "' not found in the system.";
                            }
                        } elseif (!empty($email)) {
                            $invite_errors[] = "Invalid email address: '" . $email . "'";
                        }
                    }
                }
                
                $successMessage = "✅ Thesis submitted successfully! Faculty have been notified.";
                if ($invited_count > 0) {
                    $successMessage .= " " . $invited_count . " invitation(s) sent to: " . implode(", ", $invited_list);
                }
                if (!empty($invite_errors)) {
                    $successMessage .= " Note: " . implode(", ", $invite_errors);
                }
                
                $_SESSION['submission_success'] = $successMessage;
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit;
            } else {
                $formErrors[] = "Database error: Failed to save thesis. " . $conn->error;
            }
        } else {
            $formErrors[] = "Failed to upload file. Please check folder permissions.";
        }
    } else {
        $formErrors = $errors;
    }
}

// Check for success message from session
if (isset($_SESSION['submission_success'])) {
    $successMessage = $_SESSION['submission_success'];
    unset($_SESSION['submission_success']);
}

// Get recent submissions
$recentSubmissions = [];
$recentQuery = "SELECT thesis_id, title, file_path, date_submitted
                FROM thesis_table 
                WHERE student_id = ?
                ORDER BY date_submitted DESC
                LIMIT 5";
$recentStmt = $conn->prepare($recentQuery);
$recentStmt->bind_param("i", $user_id);
$recentStmt->execute();
$recentResult = $recentStmt->get_result();
while ($row = $recentResult->fetch_assoc()) {
    $recentSubmissions[] = $row;
}
$recentStmt->close();

$pageTitle = "Submit Thesis";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Theses Archiving System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f5f5;
            color: #000000;
            line-height: 1.6;
        }

        body.dark-mode {
            background: #2d2d2d;
            color: #e0e0e0;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: -300px;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #FE4853 0%, #732529 100%);
            color: white;
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: left 0.3s ease;
            box-shadow: 5px 0 20px rgba(0,0,0,0.3);
        }

        .sidebar.show {
            left: 0;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
            color: white;
        }

        .sidebar-header p {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .sidebar-nav {
            flex: 1;
            padding: 1.5rem 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 0.25rem;
            transition: all 0.2s;
            font-weight: 500;
        }

        .nav-link i {
            width: 20px;
            color: white;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .theme-toggle {
            margin-bottom: 1rem;
        }

        .theme-toggle input {
            display: none;
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            cursor: pointer;
            position: relative;
        }

        .toggle-label i {
            font-size: 1rem;
            color: white;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .overlay.show {
            display: block;
        }

        .main-content {
            flex: 1;
            margin-left: 0;
            min-height: 100vh;
            padding: 2rem;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1);
        }

        body.dark-mode .topbar {
            background: #3a3a3a;
        }

        .topbar h1 {
            font-size: 1.5rem;
            color: #732529;
        }

        body.dark-mode .topbar h1 {
            color: #FE4853;
        }

        .hamburger-menu {
            font-size: 1.5rem;
            cursor: pointer;
            color: #FE4853;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .hamburger-menu:hover {
            background: rgba(254, 72, 83, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .notification-container {
            position: relative;
        }

        .notification-bell {
            position: relative;
            font-size: 1.2rem;
            color: #6E6E6E;
            text-decoration: none;
            transition: color 0.3s;
        }

        .notification-bell:hover {
            color: #FE4853;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #FE4853;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }

        .avatar-container {
            position: relative;
        }

        .avatar-dropdown {
            position: relative;
            cursor: pointer;
        }

        .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FE4853 0%, #732529 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1rem;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            top: 55px;
            right: 0;
            background: white;
            min-width: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 100;
        }

        body.dark-mode .dropdown-content {
            background: #3a3a3a;
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-content a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            color: #333;
            text-decoration: none;
            transition: background 0.2s;
        }

        body.dark-mode .dropdown-content a {
            color: #e0e0e0;
        }

        .dropdown-content a:hover {
            background: #f5f5f5;
        }

        body.dark-mode .dropdown-content a:hover {
            background: #4a4a4a;
        }

        .dropdown-content hr {
            margin: 0;
            border: none;
            border-top: 1px solid #e0e0e0;
        }

        .invite-section {
            margin-top: 1rem;
            margin-bottom: 1.5rem;
            padding: 1.25rem;
            background: linear-gradient(135deg, #fef2f2 0%, #fff5f5 100%);
            border-radius: 12px;
            border: 1px solid #fee2e2;
        }

        body.dark-mode .invite-section {
            background: #3d2a2a;
            border-color: #732529;
        }

        .invite-section label {
            font-weight: 600;
            color: #732529;
            margin-bottom: 0.5rem;
            display: block;
        }

        body.dark-mode .invite-section label {
            color: #FE4853;
        }

        .invite-section input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #fee2e2;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        body.dark-mode .invite-section input {
            background: #4a4a4a;
            border-color: #6E6E6E;
            color: #e0e0e0;
        }

        .invite-note {
            font-size: 0.75rem;
            color: #6E6E6E;
            margin-top: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .invite-note i {
            color: #FE4853;
        }

        .submission-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error-container {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .error-list {
            list-style: none;
            padding-left: 0;
        }

        .error-list li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.3rem;
        }

        .submission-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1);
            margin-bottom: 2rem;
        }

        body.dark-mode .submission-card {
            background: #3a3a3a;
        }

        .submission-card h2 {
            color: #732529;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        body.dark-mode .submission-card h2 {
            color: #FE4853;
        }

        .form-note {
            color: #6E6E6E;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
        }

        .required {
            color: #FE4853;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        body.dark-mode .form-group label {
            color: #e0e0e0;
        }

        .form-group label i {
            color: #FE4853;
            margin-right: 0.3rem;
        }

        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            font-family: inherit;
        }

        body.dark-mode .form-group input,
        body.dark-mode .form-group select,
        body.dark-mode .form-group textarea {
            background: #4a4a4a;
            border-color: #6E6E6E;
            color: #e0e0e0;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #FE4853;
            box-shadow: 0 0 0 3px rgba(254, 72, 83, 0.1);
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }

        .form-text {
            font-size: 0.75rem;
            color: #6E6E6E;
            margin-top: 0.25rem;
            display: block;
        }

        .file-upload-wrapper {
            border: 2px dashed #e0e0e0;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            background: #fafafa;
            cursor: pointer;
            transition: all 0.3s;
        }

        body.dark-mode .file-upload-wrapper {
            background: #4a4a4a;
            border-color: #6E6E6E;
        }

        .file-upload-wrapper:hover {
            border-color: #FE4853;
            background: #fef2f2;
        }

        .file-upload-info {
            margin-top: 0.5rem;
            font-size: 0.75rem;
            color: #6E6E6E;
        }

        .file-upload-info i {
            color: #FE4853;
        }

        .form-footer {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e0e0e0;
        }

        body.dark-mode .form-footer {
            border-top-color: #6E6E6E;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn.primary {
            background: #FE4853;
            color: white;
            flex: 1;
        }

        .btn.primary:hover {
            background: #732529;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(254, 72, 83, 0.3);
        }

        .btn.secondary {
            background: white;
            color: #6E6E6E;
            border: 2px solid #e0e0e0;
        }

        body.dark-mode .btn.secondary {
            background: #4a4a4a;
            color: #e0e0e0;
            border-color: #6E6E6E;
        }

        .btn.secondary:hover {
            background: #f5f5f5;
        }

        .recent-submissions {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1);
        }

        body.dark-mode .recent-submissions {
            background: #3a3a3a;
        }

        .recent-submissions h3 {
            color: #732529;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        body.dark-mode .recent-submissions h3 {
            color: #FE4853;
        }

        .submissions-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .submission-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 8px;
            transition: all 0.2s;
        }

        body.dark-mode .submission-item {
            background: #4a4a4a;
        }

        .submission-item:hover {
            transform: translateX(5px);
            background: #f0f0f0;
        }

        .submission-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .submission-info h4 {
            margin: 0;
            font-size: 0.95rem;
            color: #333;
        }

        body.dark-mode .submission-info h4 {
            color: #e0e0e0;
        }

        .file-indicator {
            font-size: 0.7rem;
            color: #10b981;
        }

        .submission-item small {
            color: #6E6E6E;
            font-size: 0.7rem;
        }

        .mobile-menu-btn {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 1001;
            border: none;
            background: #FE4853;
            color: white;
            padding: 12px 15px;
            border-radius: 10px;
            cursor: pointer;
            display: none;
            font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
            
            .main-content {
                padding: 1rem;
                margin-top: 60px;
            }
            
            .topbar {
                display: none;
            }
            
            .submission-card {
                padding: 1rem;
            }
            
            .form-footer {
                flex-direction: column;
            }
            
            .submission-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .submission-info {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 480px) {
            .submission-card h2 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>

<div class="overlay" id="overlay"></div>
<button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>Theses Archive</h2>
        <p>Student Portal</p>
    </div>
    <nav class="sidebar-nav">
        <a href="student_dashboard.php" class="nav-link">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="projects.php" class="nav-link">
            <i class="fas fa-folder-open"></i> My Projects
        </a>
        <a href="submission.php" class="nav-link active">
            <i class="fas fa-upload"></i> Submit Thesis
        </a>
        <a href="archived.php" class="nav-link">
            <i class="fas fa-archive"></i> Archived Theses
        </a>
        <a href="profile.php" class="nav-link">
            <i class="fas fa-user-circle"></i> Profile
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="theme-toggle">
            <input type="checkbox" id="darkmode" />
            <label for="darkmode" class="toggle-label">
                <i class="fas fa-sun"></i>
                <i class="fas fa-moon"></i>
                <span class="slider"></span>
            </label>
        </div>
        <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>

<div class="layout">
    <main class="main-content">
        <header class="topbar">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div class="hamburger-menu" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </div>
                <h1>Thesis Submission</h1>
            </div>
            <div class="user-info">
                <div class="notification-container">
                    <a href="notification.php" class="notification-bell">
                        <i class="fas fa-bell"></i>
                        <?php if ($notificationCount > 0): ?>
                            <span class="notification-badge"><?= $notificationCount ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="avatar-container">
                    <div class="avatar-dropdown">
                        <div class="avatar" id="avatarBtn">
                            <?= htmlspecialchars($initials) ?>
                        </div>
                        <div class="dropdown-content" id="dropdownMenu">
                            <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
                            <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                            <hr>
                            <a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="submission-container">
            <?php if ($successMessage): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($formErrors)): ?>
                <div class="error-container">
                    <ul class="error-list">
                        <?php foreach ($formErrors as $err): ?>
                            <li><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="submission-card">
                <h2><i class="fas fa-upload"></i> New Thesis Submission</h2>
                <p class="form-note">Fields marked with <span class="required">*</span> are required</p>

                <form method="POST" enctype="multipart/form-data" id="submissionForm">
                    <div class="form-group">
                        <label for="title"><i class="fas fa-heading"></i> Thesis Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                               placeholder="Enter the full title of your thesis"
                               minlength="5" maxlength="255">
                        <small class="form-text">Minimum 5 characters, maximum 255 characters</small>
                    </div>

                    <div class="form-group">
                        <label for="abstract"><i class="fas fa-align-left"></i> Abstract <span class="required">*</span></label>
                        <textarea id="abstract" name="abstract" required
                                  placeholder="Provide a comprehensive summary of your thesis (minimum 50 characters)"
                                  minlength="50" maxlength="5000"><?= htmlspecialchars($_POST['abstract'] ?? '') ?></textarea>
                        <small class="form-text">Minimum 50 characters, maximum 5000 characters</small>
                    </div>

                    <div class="form-group">
                        <label for="keywords"><i class="fas fa-tags"></i> Keywords <span class="required">*</span></label>
                        <input type="text" id="keywords" name="keywords" required
                               value="<?= htmlspecialchars($_POST['keywords'] ?? '') ?>"
                               placeholder="e.g., Machine Learning, Education, Data Analysis (separate with commas)">
                        <small class="form-text">Separate keywords with commas. At least 3 keywords recommended.</small>
                    </div>

                    <div class="invite-section">
                        <label><i class="fas fa-envelope"></i> Invite Co-Authors <span style="color: #6E6E6E; font-weight: normal;">(Optional)</span></label>
                        <input type="text" id="invite_emails" name="invite_emails" 
                               placeholder="Enter email addresses separated by commas (e.g., john@example.com, jane@example.com) - Leave blank if none"
                               value="<?= htmlspecialchars($_POST['invite_emails'] ?? '') ?>">
                        <div class="invite-note">
                            <i class="fas fa-info-circle"></i> 
                            Optional: Enter email addresses of co-authors. Leave blank if you don't have co-authors.
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="department"><i class="fas fa-building"></i> Department <span class="required">*</span></label>
                        <select id="department" name="department" required>
                            <option value="">Select Department</option>
                            <option value="BSIT" <?= (isset($_POST['department']) && $_POST['department'] == 'BSIT') ? 'selected' : '' ?>>BS Information Technology (BSIT)</option>
                            <option value="BSCRIM" <?= (isset($_POST['department']) && $_POST['department'] == 'BSCRIM') ? 'selected' : '' ?>>BS Criminology (BSCRIM)</option>
                            <option value="BSHTM" <?= (isset($_POST['department']) && $_POST['department'] == 'BSHTM') ? 'selected' : '' ?>>BS Hospitality Management (BSHTM)</option>
                            <option value="BSED" <?= (isset($_POST['department']) && $_POST['department'] == 'BSED') ? 'selected' : '' ?>>BS Education (BSED)</option>
                            <option value="BSBA" <?= (isset($_POST['department']) && $_POST['department'] == 'BSBA') ? 'selected' : '' ?>>BS Business Administration (BSBA)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="year"><i class="fas fa-calendar"></i> Year <span class="required">*</span></label>
                        <select id="year" name="year" required>
                            <option value="">Select Year</option>
                            <option value="2024" <?= (isset($_POST['year']) && $_POST['year'] == '2024') ? 'selected' : '' ?>>2024</option>
                            <option value="2025" <?= (isset($_POST['year']) && $_POST['year'] == '2025') ? 'selected' : '' ?>>2025</option>
                            <option value="2026" <?= (isset($_POST['year']) && $_POST['year'] == '2026') ? 'selected' : '' ?>>2026</option>
                            <option value="2027" <?= (isset($_POST['year']) && $_POST['year'] == '2027') ? 'selected' : '' ?>>2027</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="adviser"><i class="fas fa-user-tie"></i> Thesis Adviser <span class="required">*</span></label>
                        <input type="text" id="adviser" name="adviser" required
                               value="<?= htmlspecialchars($_POST['adviser'] ?? '') ?>"
                               placeholder="Full name of your thesis adviser">
                    </div>

                    <div class="form-group">
                        <label for="manuscript"><i class="fas fa-file-pdf"></i> Upload Manuscript <span class="required">*</span></label>
                        <div class="file-upload-wrapper">
                            <input type="file" id="manuscript" name="manuscript" accept=".pdf" required style="display: none;">
                            <div onclick="document.getElementById('manuscript').click()" style="cursor: pointer;">
                                <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #FE4853;"></i>
                                <p>Click or drag to upload PDF file</p>
                            </div>
                            <div class="file-upload-info">
                                <i class="fas fa-info-circle"></i>
                                <span>Accepted format: PDF only | Maximum size: 10MB</span>
                            </div>
                        </div>
                        <span id="file-name" style="font-size: 0.75rem; color: #10b981; display: block; margin-top: 0.5rem;"></span>
                    </div>

                    <div class="form-footer">
                        <button type="submit" name="submit_thesis" class="btn primary">
                            <i class="fas fa-paper-plane"></i> Submit Thesis
                        </button>
                        <button type="reset" class="btn secondary" onclick="return confirm('Are you sure you want to clear the form?')">
                            <i class="fas fa-undo"></i> Clear Form
                        </button>
                    </div>
                </form>
            </div>

            <?php if (!empty($recentSubmissions)): ?>
            <div class="recent-submissions">
                <h3><i class="fas fa-history"></i> Your Recent Submissions</h3>
                <div class="submissions-list">
                    <?php foreach ($recentSubmissions as $sub): ?>
                        <div class="submission-item">
                            <div class="submission-info">
                                <h4><?= htmlspecialchars($sub['title']) ?></h4>
                                <?php if (!empty($sub['file_path'])): ?>
                                    <span class="file-indicator" title="Manuscript uploaded">
                                        <i class="fas fa-file-pdf"></i> PDF
                                    </span>
                                <?php endif; ?>
                            </div>
                            <small><?= date('M d, Y', strtotime($sub['date_submitted'])) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    const fileInput = document.getElementById('manuscript');
    const fileNameSpan = document.getElementById('file-name');
    
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                fileNameSpan.innerHTML = '<i class="fas fa-check-circle"></i> Selected: ' + this.files[0].name;
                fileNameSpan.style.color = '#10b981';
            } else {
                fileNameSpan.innerHTML = '';
            }
        });
    }

    const darkToggle = document.getElementById('darkmode');
    if (darkToggle) {
        darkToggle.addEventListener('change', () => {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', darkToggle.checked);
        });
        if (localStorage.getItem('darkMode') === 'true') {
            darkToggle.checked = true;
            document.body.classList.add('dark-mode');
        }
    }

    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const mobileBtn = document.getElementById('mobileMenuBtn');

    function toggleSidebar() {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    }

    if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
    if (mobileBtn) mobileBtn.addEventListener('click', toggleSidebar);
    if (overlay) overlay.addEventListener('click', toggleSidebar);

    const avatarBtn = document.getElementById('avatarBtn');
    const dropdownMenu = document.getElementById('dropdownMenu');

    if (avatarBtn) {
        avatarBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });
    }

    document.addEventListener('click', (e) => {
        if (!avatarBtn?.contains(e.target) && dropdownMenu) {
            dropdownMenu.classList.remove('show');
        }
    });
</script>

</body>
</html>