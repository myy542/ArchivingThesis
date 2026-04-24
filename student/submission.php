<?php
session_start();
include("../config/db.php");
include("includes/email_functions.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"])) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

// Get user data
$user_query = "SELECT first_name, last_name, email, department_id FROM user_table WHERE user_id = ? LIMIT 1";
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

// ==================== CREATE TABLES IF NOT EXISTS ====================
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

$conn->query("CREATE TABLE IF NOT EXISTS thesis_invitations (
    invitation_id INT AUTO_INCREMENT PRIMARY KEY,
    thesis_id INT NOT NULL,
    invited_user_id INT NOT NULL,
    invited_by INT NOT NULL,
    is_read ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (thesis_id),
    INDEX (invited_user_id),
    INDEX (is_read)
)");

$conn->query("CREATE TABLE IF NOT EXISTS pending_invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    invited_by INT NOT NULL,
    invited_by_name VARCHAR(255),
    thesis_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// ==================== GET NOTIFICATION COUNT ====================
$notificationCount = 0;
$notif_query = "SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
if ($notif_row = $notif_result->fetch_assoc()) {
    $notificationCount = $notif_row['cnt'];
}
$notif_stmt->close();

// ==================== GET DEPARTMENTS FOR DROPDOWN ====================
$departments = [];
$dept_query = "SELECT department_id, department_name, department_code FROM department_table ORDER BY department_name";
$dept_result = $conn->query($dept_query);
if ($dept_result && $dept_result->num_rows > 0) {
    while ($row = $dept_result->fetch_assoc()) {
        $departments[] = $row;
    }
}

// ==================== SEND NOTIFICATION TO FACULTY ====================
function sendToFaculty($conn, $thesis_id, $title, $displayName, $department_id) {
    $facultyQuery = "SELECT user_id FROM user_table WHERE role_id = 3 AND department_id = ?";
    $facultyStmt = $conn->prepare($facultyQuery);
    $facultyStmt->bind_param("i", $department_id);
    $facultyStmt->execute();
    $facultyResult = $facultyStmt->get_result();
    
    if ($facultyResult && $facultyResult->num_rows > 0) {
        $shortTitle = strlen($title) > 50 ? substr($title, 0, 50) . '...' : $title;
        $message = "📢 New thesis submission from " . $displayName . ": \"" . $shortTitle . "\"";
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
    $facultyStmt->close();
}

// ==================== PROCESS SUBMISSION ====================
$successMessage = "";
$formErrors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_thesis'])) {
    $title = trim($_POST['title'] ?? '');
    $abstract = trim($_POST['abstract'] ?? '');
    $keywords = trim($_POST['keywords'] ?? '');
    $department_id = trim($_POST['department_id'] ?? '');
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
    if (empty($department_id)) $errors[] = "Department is required.";
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
            
            // Insert thesis
            $insertQuery = "INSERT INTO thesis_table (student_id, title, abstract, keywords, department_id, year, adviser, file_path, date_submitted, is_archived) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param("isssisss", $user_id, $title, $abstract, $keywords, $department_id, $year, $adviser, $dbFilePath);
            
            if ($insertStmt->execute()) {
                $thesis_id = $insertStmt->insert_id;
                $insertStmt->close();
                
                // Add as collaborator
                $collabQuery = "INSERT INTO thesis_collaborators (thesis_id, user_id, role) VALUES (?, ?, 'owner')";
                $collabStmt = $conn->prepare($collabQuery);
                $collabStmt->bind_param("ii", $thesis_id, $user_id);
                $collabStmt->execute();
                $collabStmt->close();
                
                // Send notification to faculty
                sendToFaculty($conn, $thesis_id, $title, $displayName, $department_id);
                
                // Process co-author invitations with EMAIL
                $invited_count = 0;
                $invited_list = [];
                
                if (!empty($invite_emails)) {
                    $emails = array_map('trim', explode(',', $invite_emails));
                    
                    foreach ($emails as $email) {
                        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
                                    $inviteQuery = "INSERT INTO thesis_invitations (thesis_id, invited_user_id, invited_by, is_read) VALUES (?, ?, ?, 'pending')";
                                    $inviteStmt = $conn->prepare($inviteQuery);
                                    $inviteStmt->bind_param("iii", $thesis_id, $invited_user['user_id'], $user_id);
                                    $inviteStmt->execute();
                                    $inviteStmt->close();
                                    
                                    $notifMessage = "📢 " . $displayName . " invited you to collaborate on thesis: \"" . $title . "\"";
                                    $notifQuery = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'thesis_invitation', 0, NOW())";
                                    $notifStmt = $conn->prepare($notifQuery);
                                    $notifStmt->bind_param("iis", $invited_user['user_id'], $thesis_id, $notifMessage);
                                    $notifStmt->execute();
                                    $notifStmt->close();
                                    
                                    // SEND EMAIL TO CO-AUTHOR
                                    sendCoAuthorInvitationEmail($email, $invited_user['first_name'] . ' ' . $invited_user['last_name'], $displayName, $title, $thesis_id);
                                    
                                    $invited_count++;
                                    $invited_list[] = $email;
                                }
                            } elseif ($invited_user && $invited_user['user_id'] == $user_id) {
                                // Skip self
                            } else {
                                // User not found - store pending and send email
                                $pendingQuery = "INSERT INTO pending_invitations (email, invited_by, invited_by_name, thesis_id) VALUES (?, ?, ?, ?)";
                                $pendingStmt = $conn->prepare($pendingQuery);
                                $pendingStmt->bind_param("sisi", $email, $user_id, $displayName, $thesis_id);
                                $pendingStmt->execute();
                                $pendingStmt->close();
                                
                                // SEND EMAIL TO NON-REGISTERED USER
                                sendPendingInvitationEmail($email, $displayName, $title);
                                
                                $invited_count++;
                                $invited_list[] = $email . " (pending registration)";
                            }
                        }
                    }
                }
                
                $successMessage = "✅ Thesis submitted successfully!";
                if ($invited_count > 0) {
                    $successMessage .= " " . $invited_count . " invitation(s) sent to: " . implode(", ", $invited_list);
                }
                
                $_SESSION['submission_success'] = $successMessage;
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit;
            } else {
                $formErrors[] = "Database error: Failed to save thesis.";
            }
        } else {
            $formErrors[] = "Failed to upload file. Please check folder permissions.";
        }
    } else {
        $formErrors = $errors;
    }
}

if (isset($_SESSION['submission_success'])) {
    $successMessage = $_SESSION['submission_success'];
    unset($_SESSION['submission_success']);
}

// Get recent submissions
$recentSubmissions = [];
$recentQuery = "SELECT thesis_id, title, file_path, date_submitted FROM thesis_table WHERE student_id = ? ORDER BY date_submitted DESC LIMIT 5";
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
    <link rel="stylesheet" href="css/submission.css">
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
        <a href="student_dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
        <a href="projects.php" class="nav-link"><i class="fas fa-folder-open"></i> My Projects</a>
        <a href="submission.php" class="nav-link active"><i class="fas fa-upload"></i> Submit Thesis</a>
        <a href="archived.php" class="nav-link"><i class="fas fa-archive"></i> Archived Theses</a>
        <a href="profile.php" class="nav-link"><i class="fas fa-user-circle"></i> Profile</a>
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
        <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<div class="layout">
    <main class="main-content">
        <header class="topbar">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div class="hamburger-menu" id="hamburgerBtn"><i class="fas fa-bars"></i></div>
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
                        <div class="avatar" id="avatarBtn"><?= htmlspecialchars($initials) ?></div>
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
                               placeholder="Enter email addresses separated by commas (e.g., john@example.com, jane@example.com)"
                               value="<?= htmlspecialchars($_POST['invite_emails'] ?? '') ?>">
                        <div class="invite-note">
                            <i class="fas fa-info-circle"></i> 
                            Optional: Enter email addresses of co-authors. They will receive an email invitation.
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="department_id"><i class="fas fa-building"></i> Department <span class="required">*</span></label>
                        <select id="department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['department_id'] ?>" <?= (isset($_POST['department_id']) && $_POST['department_id'] == $dept['department_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['department_name']) ?> (<?= htmlspecialchars($dept['department_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Only research advisers from this department will be notified.</small>
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
                                    <span class="file-indicator"><i class="fas fa-file-pdf"></i> PDF</span>
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

<script src="js/submission.js"></script>
</body>
</html>