<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a Dean
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'dean') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// Get dean's department
$dean_query = "SELECT department_id FROM user_table WHERE user_id = ?";
$dean_stmt = $conn->prepare($dean_query);
$dean_stmt->bind_param("i", $user_id);
$dean_stmt->execute();
$dean_result = $dean_stmt->get_result();
$dean_data = $dean_result->fetch_assoc();
$dean_department_id = $dean_data['department_id'] ?? null;
$dean_stmt->close();

// Get thesis ID from URL
$thesis_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get thesis details with department info
$thesis = null;
$thesis_title = 'Unknown Thesis';
$thesis_author = 'Unknown Author';
$thesis_abstract = 'No abstract available.';
$thesis_file = '';
$thesis_date = '';
$thesis_status = '';
$student_id = 0;
$adviser_name = '';
$thesis_department_id = null;

if ($thesis_id > 0) {
    $thesis_query = "SELECT t.*, d.department_name, d.department_code
                     FROM thesis_table t
                     LEFT JOIN department_table d ON t.department_id = d.department_id
                     WHERE t.thesis_id = ?";
    $thesis_stmt = $conn->prepare($thesis_query);
    $thesis_stmt->bind_param("i", $thesis_id);
    $thesis_stmt->execute();
    $thesis_result = $thesis_stmt->get_result();
    if ($thesis_row = $thesis_result->fetch_assoc()) {
        $thesis = $thesis_row;
        $thesis_title = $thesis_row['title'];
        $thesis_abstract = $thesis_row['abstract'] ?? 'No abstract available.';
        $thesis_file = $thesis_row['file_path'] ?? '';
        $thesis_date = isset($thesis_row['date_submitted']) ? date('M d, Y', strtotime($thesis_row['date_submitted'])) : date('M d, Y');
        $thesis_status = $thesis_row['status'] ?? 'pending';
        $student_id = $thesis_row['student_id'] ?? 0;
        $adviser_name = $thesis_row['adviser'] ?? '';
        $thesis_department_id = $thesis_row['department_id'] ?? null;
        
        // Check if thesis belongs to dean's department
        if ($dean_department_id && $thesis_department_id != $dean_department_id) {
            $_SESSION['error_message'] = "You are not authorized to review this thesis. This thesis belongs to a different department.";
            header("Location: dean.php?section=dashboard");
            exit;
        }
        
        // Get student name
        $student_query = "SELECT first_name, last_name FROM user_table WHERE user_id = ?";
        $student_stmt = $conn->prepare($student_query);
        $student_stmt->bind_param("i", $student_id);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        if ($student_row = $student_result->fetch_assoc()) {
            $thesis_author = $student_row['first_name'] . " " . $student_row['last_name'];
        }
        $student_stmt->close();
    }
    $thesis_stmt->close();
}

// ==================== FUNCTION TO NOTIFY LIBRARIAN ====================
function notifyLibrarian($conn, $thesis_id, $thesis_title, $student_name, $dean_name) {
    $lib_query = "SELECT user_id FROM user_table WHERE role_id = 5";
    $lib_result = $conn->query($lib_query);
    
    if (!$lib_result || $lib_result->num_rows == 0) {
        error_log("No librarian found with role_id = 5");
        return false;
    }
    
    $notified = false;
    while ($librarian = $lib_result->fetch_assoc()) {
        $librarian_id = $librarian['user_id'];
        $message = "📚 Thesis ready for archiving: \"" . $thesis_title . "\" from student " . $student_name . ". Approved by Dean: " . $dean_name;
        $link = "../librarian/archiveThesis.php?id=" . $thesis_id;
        
        $insert_sql = "INSERT INTO notifications (user_id, thesis_id, message, type, link, is_read, created_at) 
                       VALUES ($librarian_id, $thesis_id, '$message', 'dean_approved', '$link', 0, NOW())";
        
        if ($conn->query($insert_sql)) {
            $notified = true;
        }
    }
    return $notified;
}

// Process form submission - Forward to Librarian
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['forward_to_librarian'])) {
    $thesis_id_post = intval($_POST['thesis_id']);
    $dean_feedback = isset($_POST['dean_feedback']) ? trim($_POST['dean_feedback']) : '';
    
    $update_query = "UPDATE thesis_table SET status = 'approved_by_dean', approved_by_dean_at = NOW() WHERE thesis_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $thesis_id_post);
    $update_stmt->execute();
    $update_stmt->close();
    
    if ($student_id > 0) {
        $student_msg = "✅ Good news! Your thesis \"" . $thesis_title . "\" has been APPROVED by Dean " . $fullName . " and forwarded to the Librarian for archiving.";
        if (!empty($dean_feedback)) {
            $student_msg .= " Feedback: " . $dean_feedback;
        }
        $notif_student = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES ($student_id, $thesis_id_post, '$student_msg', 'student_approved', 0, NOW())";
        $conn->query($notif_student);
    }
    
    if (!empty($adviser_name)) {
        $get_adviser = "SELECT user_id FROM user_table WHERE CONCAT(first_name, ' ', last_name) = ? AND role_id = 3";
        $adviser_stmt = $conn->prepare($get_adviser);
        $adviser_stmt->bind_param("s", $adviser_name);
        $adviser_stmt->execute();
        $adviser_result = $adviser_stmt->get_result();
        if ($adviser_row = $adviser_result->fetch_assoc()) {
            $adviser_id = $adviser_row['user_id'];
            $adviser_msg = "✅ Thesis \"" . $thesis_title . "\" has been APPROVED by Dean " . $fullName . " and forwarded to the Librarian.";
            if (!empty($dean_feedback)) {
                $adviser_msg .= " Feedback: " . $dean_feedback;
            }
            $notif_adviser = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES ($adviser_id, $thesis_id_post, '$adviser_msg', 'dean_approved', 0, NOW())";
            $conn->query($notif_adviser);
        }
        $adviser_stmt->close();
    }
    
    $student_name = $thesis_author;
    notifyLibrarian($conn, $thesis_id_post, $thesis_title, $student_name, $fullName);
    
    // Notify coordinator
    $get_coordinator = "SELECT user_id FROM user_table WHERE role_id = 6";
    $coord_result = $conn->query($get_coordinator);
    if ($coord_result && $coord_result->num_rows > 0) {
        while ($coord = $coord_result->fetch_assoc()) {
            $coord_msg = "✅ Thesis \"" . $thesis_title . "\" has been APPROVED by Dean " . $fullName . " and forwarded to Librarian.";
            $notif_coord = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (" . $coord['user_id'] . ", $thesis_id_post, '$coord_msg', 'coordinator_info', 0, NOW())";
            $conn->query($notif_coord);
        }
    }
    
    $dean_msg = "✅ You approved thesis \"" . $thesis_title . "\" and forwarded to Librarian";
    $notif_dean = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES ($user_id, $thesis_id_post, '$dean_msg', 'dean_action', 0, NOW())";
    $conn->query($notif_dean);
    
    header("Location: dean.php?section=dashboard&msg=forwarded");
    exit;
}

// Process form submission - Return to Coordinator
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['return_to_coordinator'])) {
    $thesis_id_post = intval($_POST['thesis_id']);
    $dean_feedback = isset($_POST['dean_feedback']) ? trim($_POST['dean_feedback']) : '';
    
    $update_query = "UPDATE thesis_table SET status = 'revision_needed', dean_feedback = ? WHERE thesis_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("si", $dean_feedback, $thesis_id_post);
    $update_stmt->execute();
    $update_stmt->close();
    
    if ($student_id > 0) {
        $student_msg = "❌ Your thesis \"" . $thesis_title . "\" needs revision as per Dean's review. Please work with your adviser.";
        if (!empty($dean_feedback)) {
            $student_msg .= " Feedback: " . $dean_feedback;
        }
        $notif_student = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES ($student_id, $thesis_id_post, '$student_msg', 'student_revision', 0, NOW())";
        $conn->query($notif_student);
    }
    
    $get_coordinator = "SELECT user_id FROM user_table WHERE role_id = 6";
    $coord_result = $conn->query($get_coordinator);
    if ($coord_result && $coord_result->num_rows > 0) {
        while ($coord = $coord_result->fetch_assoc()) {
            $coord_msg = "⚠️ Thesis \"" . $thesis_title . "\" was returned by Dean " . $fullName . " for revision.";
            if (!empty($dean_feedback)) {
                $coord_msg .= " Feedback: " . $dean_feedback;
            }
            $notif_coord = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (" . $coord['user_id'] . ", $thesis_id_post, '$coord_msg', 'coordinator_revision', 0, NOW())";
            $conn->query($notif_coord);
        }
    }
    
    $dean_msg = "❌ You returned thesis \"" . $thesis_title . "\" to Coordinator for revision";
    $notif_dean = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES ($user_id, $thesis_id_post, '$dean_msg', 'dean_action', 0, NOW())";
    $conn->query($notif_dean);
    
    header("Location: dean.php?section=dashboard&msg=returned");
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Thesis | Dean Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/review-thesis.css">
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
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div></div>
        <div class="nav-menu">
            <a href="dean.php?section=dashboard" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="reviewThesis.php" class="nav-item active"><i class="fas fa-file-alt"></i><span>Review Theses</span></a>
            <a href="dean.php?section=department" class="nav-item"><i class="fas fa-building"></i><span>Department</span></a>
            <a href="dean.php?section=reports" class="nav-item"><i class="fas fa-chart-bar"></i><span>Reports</span></a>
        </div>
        <div class="nav-footer">
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h2>Review Thesis</h2>
            <a href="dean.php?section=dashboard" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <?php if (!$thesis || ($dean_department_id && $thesis_department_id != $dean_department_id)): ?>
        <div class="thesis-detail-card">
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-file-alt" style="font-size: 3rem; color: #dc2626; margin-bottom: 16px;"></i>
                <h3 style="color: #991b1b;">Thesis Not Found or Not Authorized</h3>
                <p>You can only review theses from your department.</p>
                <a href="dean.php?section=dashboard" class="back-link" style="margin-top: 20px;">Go back to Dashboard</a>
            </div>
        </div>
        <?php else: ?>
        
        <div class="thesis-detail-card">
            <div class="thesis-header">
                <h1 class="thesis-title"><?= htmlspecialchars($thesis_title) ?></h1>
                <span class="status-badge status-pending">
                    <?= htmlspecialchars(ucfirst($thesis_status)) ?>
                </span>
            </div>
            
            <div class="thesis-meta">
                <div class="meta-item"><i class="fas fa-user"></i><span>Student: <?= htmlspecialchars($thesis_author) ?></span></div>
                <div class="meta-item"><i class="fas fa-chalkboard-user"></i><span>Adviser: <?= htmlspecialchars($adviser_name) ?></span></div>
                <div class="meta-item"><i class="fas fa-building"></i><span>Department: <?= htmlspecialchars($thesis['department_name'] ?? $department_name) ?> (<?= htmlspecialchars($thesis['department_code'] ?? '') ?>)</span></div>
                <div class="meta-item"><i class="fas fa-calendar-alt"></i><span>Submitted: <?= $thesis_date ?></span></div>
            </div>
            
            <div class="abstract-section">
                <h4><i class="fas fa-align-left"></i> Abstract</h4>
                <p><?= nl2br(htmlspecialchars($thesis_abstract)) ?></p>
            </div>
            
            <div class="file-section">
                <div class="file-info">
                    <i class="fas fa-file-pdf"></i>
                    <div class="file-name"><?= !empty($thesis_file) ? basename($thesis_file) : 'No file uploaded' ?></div>
                </div>
                <?php if (!empty($thesis_file)): ?>
                <a href="<?= htmlspecialchars('../' . $thesis_file) ?>" class="download-btn" download><i class="fas fa-download"></i> Download</a>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($thesis_file) && file_exists('../' . $thesis_file)): ?>
            <div class="pdf-viewer">
                <iframe src="<?= htmlspecialchars('../' . $thesis_file) ?>"></iframe>
            </div>
            <?php endif; ?>
        </div>

        <!-- ACTION CARDS -->
        <div class="action-cards">
            <div class="action-card">
                <div class="action-icon"><i class="fas fa-paper-plane"></i></div>
                <h3>Forward to Librarian</h3>
                <p>Approve this thesis and forward to the Librarian for final archiving.</p>
                <button class="btn-forward" onclick="openForwardModal()"><i class="fas fa-paper-plane"></i> Forward to Librarian</button>
            </div>
            <div class="action-card">
                <div class="action-icon"><i class="fas fa-undo-alt"></i></div>
                <h3>Return to Coordinator</h3>
                <p>Return this thesis to the Coordinator for revision. Provide feedback below.</p>
                <button class="btn-return" onclick="openReturnModal()"><i class="fas fa-undo"></i> Return to Coordinator</button>
            </div>
        </div>
        
        <?php endif; ?>
    </main>

    <!-- Forward Modal -->
    <div id="forwardModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="color:#10b981;"><i class="fas fa-paper-plane"></i> Forward to Librarian</h3>
                <span class="close-modal" onclick="closeForwardModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="thesis_id" value="<?= $thesis_id ?>">
                    <input type="hidden" name="forward_to_librarian" value="1">
                    <div class="form-group">
                        <label>Feedback (Optional)</label>
                        <textarea name="dean_feedback" rows="3" placeholder="Optional feedback..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeForwardModal()">Cancel</button>
                    <button type="submit" class="btn-forward">Confirm Forward</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Return Modal -->
    <div id="returnModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="color:#f59e0b;"><i class="fas fa-undo-alt"></i> Return to Coordinator</h3>
                <span class="close-modal" onclick="closeReturnModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="thesis_id" value="<?= $thesis_id ?>">
                    <input type="hidden" name="return_to_coordinator" value="1">
                    <div class="form-group">
                        <label>Reason for Return <span style="color:#f59e0b;">*</span></label>
                        <textarea name="dean_feedback" rows="3" placeholder="Please provide reason..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeReturnModal()">Cancel</button>
                    <button type="submit" class="btn-return">Confirm Return</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.userData = {
            fullName: '<?= htmlspecialchars($fullName) ?>',
            initials: '<?= htmlspecialchars($initials) ?>'
        };
        
        function openForwardModal() { document.getElementById('forwardModal').classList.add('show'); }
        function closeForwardModal() { document.getElementById('forwardModal').classList.remove('show'); }
        function openReturnModal() { document.getElementById('returnModal').classList.add('show'); }
        function closeReturnModal() { document.getElementById('returnModal').classList.remove('show'); }
        
        // Close modal on outside click
        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        }
    </script>
    
    <script src="js/review-thesis.js"></script>
</body>
</html>