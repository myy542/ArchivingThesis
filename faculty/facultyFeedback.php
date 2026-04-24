<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"])) {
    header("Location: ../authentication/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

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

$stmt = $conn->prepare("SELECT first_name, last_name FROM user_table WHERE user_id = ?");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
$stmt->close();

$first = $faculty['first_name'] ?? '';
$last = $faculty['last_name'] ?? '';
$fullName = trim($first . ' ' . $last);
$initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));

// Create feedback table if not exists
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

// Get all feedback by this faculty
$feedbackQuery = "SELECT f.*, t.title as thesis_title, CONCAT(u.first_name, ' ', u.last_name) as student_name
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

// Get pending theses for feedback dropdown
$pendingQuery = "SELECT thesis_id, title FROM thesis_table WHERE (is_archived = 0 OR is_archived IS NULL) ORDER BY date_submitted DESC";
$pendingResult = $conn->query($pendingQuery);
$pendingTheses = $pendingResult->fetch_all(MYSQLI_ASSOC);

$pageTitle = "My Feedback";
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= $pageTitle ?> - Theses Archiving System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- External CSS -->
    <link rel="stylesheet" href="css/facultyFeedback.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area"><i class="fas fa-search"></i><input type="text" placeholder="Search..."></div>
        </div>
        <div class="nav-right">
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger"><span class="profile-name"><?= htmlspecialchars($fullName) ?></span><div class="profile-avatar"><?= $initials ?></div></div>
                <div class="profile-dropdown" id="profileDropdown"><a href="facultyProfile.php"><i class="fas fa-user"></i> Profile</a><a href="facultyEditProfile.php"><i class="fas fa-edit"></i> Edit Profile</a><hr><a href="../authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header"><h2>Thesis Manager</h2><p>Faculty Portal</p></div>
        <nav class="sidebar-nav">
            <a href="facultyDashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="reviewThesis.php" class="nav-link"><i class="fas fa-book-reader"></i> Review Theses</a>
            <a href="facultyFeedback.php" class="nav-link active"><i class="fas fa-comment-dots"></i> My Feedback</a>
        </nav>
        <div class="sidebar-footer"><a href="../authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </aside>

    <main class="main-content">
        <div class="topbar"><h1>My Feedback</h1><div class="user-info"><div class="avatar"><?= $initials ?></div></div></div>
        <div class="feedback-container">
            <div class="header-section"><h2>Feedback History</h2><button class="btn-add" onclick="openModal()"><i class="fas fa-plus"></i> Add New Feedback</button></div>
            <?php if (isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
            <?php if (isset($error)): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
            <?php if (empty($feedbackList)): ?>
                <div class="no-feedback"><i class="fas fa-comment-dots"></i><h3>No feedback yet</h3><p>Click "Add New Feedback" to start providing feedback on theses.</p></div>
            <?php else: ?>
                <div class="feedback-grid">
                    <?php foreach ($feedbackList as $feedback): ?>
                        <div class="feedback-card">
                            <div class="feedback-header"><div class="thesis-info"><h3><?= htmlspecialchars($feedback['thesis_title']) ?></h3><div class="student-name"><i class="fas fa-user"></i> <?= htmlspecialchars($feedback['student_name']) ?></div></div><div class="feedback-date"><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($feedback['feedback_date'])) ?></div></div>
                            <div class="feedback-comments"><?= nl2br(htmlspecialchars($feedback['comments'])) ?></div>
                            <div class="feedback-actions"><button class="btn-edit" onclick="editFeedback(<?= $feedback['feedback_id'] ?>)"><i class="fas fa-edit"></i> Edit</button><button class="btn-delete" onclick="deleteFeedback(<?= $feedback['feedback_id'] ?>)"><i class="fas fa-trash"></i> Delete</button></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add Feedback Modal -->
    <div class="modal" id="feedbackModal"><div class="modal-content"><div class="modal-header"><h2>Add New Feedback</h2><button class="close-btn" onclick="closeModal()">&times;</button></div>
    <form method="POST"><input type="hidden" name="add_feedback" value="1"><div class="form-group"><label>Select Thesis</label><select name="thesis_id" required><option value="">-- Choose a thesis --</option><?php foreach ($pendingTheses as $thesis): ?><option value="<?= $thesis['thesis_id'] ?>"><?= htmlspecialchars($thesis['title']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Feedback Comments</label><textarea name="comments" placeholder="Enter your feedback here..." required></textarea></div><button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Submit Feedback</button></form></div></div>

    <!-- Edit Feedback Modal -->
    <div class="modal" id="editModal"><div class="modal-content"><div class="modal-header"><h2>Edit Feedback</h2><button class="close-btn" onclick="closeEditModal()">&times;</button></div>
    <form method="POST"><input type="hidden" name="edit_feedback" value="1"><input type="hidden" name="feedback_id" id="edit_feedback_id"><div class="form-group"><label>Feedback Comments</label><textarea name="comments" id="edit_comments" rows="6" required></textarea></div><button type="submit" class="btn-submit"><i class="fas fa-save"></i> Update Feedback</button></form></div></div>

    <script>
        window.userData = {
            fullName: '<?php echo addslashes($fullName); ?>',
            initials: '<?php echo addslashes($initials); ?>'
        };
    </script>
    
    <!-- External JavaScript -->
    <script src="js/facultyFeedback.js"></script>
</body>
</html>