<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'librarian') {
    header("Location: ../authentication/login.php");
    exit;
}

$librarian_id = $_SESSION['user_id'];

// Get librarian info
$lib_query = "SELECT first_name, last_name FROM user_table WHERE user_id = ?";
$lib_stmt = $conn->prepare($lib_query);
$lib_stmt->bind_param("i", $librarian_id);
$lib_stmt->execute();
$lib_data = $lib_stmt->get_result()->fetch_assoc();
$lib_stmt->close();

$first_name = $lib_data['first_name'] ?? '';
$last_name = $lib_data['last_name'] ?? '';
$fullName = trim($first_name . " " . $last_name);
$initials = !empty($first_name) && !empty($last_name) ? strtoupper(substr($first_name,0,1).substr($last_name,0,1)) : "LB";

// Create archive table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS archive_table (
    archive_id INT AUTO_INCREMENT PRIMARY KEY,
    thesis_id INT NOT NULL,
    archived_by INT NOT NULL,
    archive_date DATETIME NOT NULL,
    retention_period INT DEFAULT 5,
    archive_notes TEXT,
    access_level VARCHAR(20) DEFAULT 'public',
    views_count INT DEFAULT 0,
    downloads_count INT DEFAULT 0
)");

// Create notifications table
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    thesis_id INT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info',
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Function to notify student
function notifyStudent($conn, $student_id, $thesis_id, $thesis_title) {
    $message = "✅ Your thesis \"" . $thesis_title . "\" has been successfully archived by the Librarian. It is now available in the archive system.";
    $insert = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'archive_success', 0, NOW())";
    $stmt = $conn->prepare($insert);
    $stmt->bind_param("iis", $student_id, $thesis_id, $message);
    $stmt->execute();
    $stmt->close();
    return true;
}

// Get theses approved by dean (ready for archiving)
$pending_theses = [];
$pending_query = "SELECT t.*, u.first_name, u.last_name, u.email 
                  FROM thesis_table t
                  JOIN user_table u ON t.student_id = u.user_id
                  WHERE t.status = 'Approved by Dean'
                  ORDER BY t.approved_by_dean_at DESC";
$pending_result = $conn->query($pending_query);
if ($pending_result && $pending_result->num_rows > 0) {
    while ($row = $pending_result->fetch_assoc()) {
        $pending_theses[] = $row;
    }
}

// Get archived theses
$archived_theses = [];
$archived_query = "SELECT t.*, u.first_name, u.last_name, a.archive_id, a.archive_date, a.retention_period, a.archive_notes, a.access_level, a.views_count
                   FROM thesis_table t
                   JOIN user_table u ON t.student_id = u.user_id
                   LEFT JOIN archive_table a ON t.thesis_id = a.thesis_id
                   WHERE t.status = 'Archived'
                   ORDER BY a.archive_date DESC";
$archived_result = $conn->query($archived_query);
if ($archived_result && $archived_result->num_rows > 0) {
    while ($row = $archived_result->fetch_assoc()) {
        $archived_theses[] = $row;
    }
}

// Handle archive request
if (isset($_POST['archive_thesis'])) {
    $thesis_id = intval($_POST['thesis_id']);
    $retention_period = intval($_POST['retention_period']);
    $archive_notes = trim($_POST['archive_notes'] ?? '');
    $access_level = $_POST['access_level'] ?? 'public';
    
    $conn->begin_transaction();
    
    try {
        $update = "UPDATE thesis_table SET status = 'Archived', archived_at = NOW() WHERE thesis_id = ?";
        $stmt = $conn->prepare($update);
        $stmt->bind_param("i", $thesis_id);
        $stmt->execute();
        $stmt->close();
        
        $insert = "INSERT INTO archive_table (thesis_id, archived_by, archive_date, retention_period, archive_notes, access_level) 
                   VALUES (?, ?, NOW(), ?, ?, ?)";
        $stmt = $conn->prepare($insert);
        $stmt->bind_param("iiiss", $thesis_id, $librarian_id, $retention_period, $archive_notes, $access_level);
        $stmt->execute();
        $stmt->close();
        
        // Get student info
        $student_query = "SELECT student_id, title FROM thesis_table WHERE thesis_id = ?";
        $student_stmt = $conn->prepare($student_query);
        $student_stmt->bind_param("i", $thesis_id);
        $student_stmt->execute();
        $thesis_data = $student_stmt->get_result()->fetch_assoc();
        $student_stmt->close();
        
        notifyStudent($conn, $thesis_data['student_id'], $thesis_id, $thesis_data['title']);
        
        $conn->commit();
        
        header("Location: librarian_archive.php?success=1");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// Update access level
if (isset($_POST['update_access'])) {
    $thesis_id = intval($_POST['thesis_id']);
    $access_level = $_POST['access_level'];
    
    $update = "UPDATE archive_table SET access_level = ? WHERE thesis_id = ?";
    $stmt = $conn->prepare($update);
    $stmt->bind_param("si", $access_level, $thesis_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: librarian_archive.php");
    exit();
}

$stats = [
    'pending' => count($pending_theses),
    'archived' => count($archived_theses),
    'public' => $conn->query("SELECT COUNT(*) FROM archive_table WHERE access_level = 'public'")->fetch_row()[0] ?? 0
];

$pageTitle = "Librarian Archive Management";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f4f8; color: #1f2937; }
        body.dark-mode { background: #1a1a1a; color: #e0e0e0; }
        
        .top-nav { position: fixed; top: 0; right: 0; left: 0; height: 70px; background: white; display: flex; align-items: center; justify-content: space-between; padding: 0 32px; z-index: 99; border-bottom: 1px solid #e0e0e0; }
        body.dark-mode .top-nav { background: #2d2d2d; }
        
        .logo { font-size: 1.3rem; font-weight: 700; color: #6f42c1; }
        .logo span { color: #9b59b6; }
        
        .profile-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #6f42c1, #9b59b6); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        
        .main-content { margin-top: 70px; padding: 32px; }
        
        .welcome-banner { background: linear-gradient(135deg, #6f42c1, #9b59b6); border-radius: 28px; padding: 32px 36px; color: white; margin-bottom: 32px; }
        .welcome-banner h1 { font-size: 1.6rem; margin-bottom: 8px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 32px; }
        .stat-card { background: white; border-radius: 20px; padding: 24px; display: flex; align-items: center; gap: 20px; border: 1px solid #e0e0e0; }
        body.dark-mode .stat-card { background: #2d2d2d; }
        .stat-icon { width: 60px; height: 60px; border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; background: #f0e6ff; color: #6f42c1; }
        .stat-content h3 { font-size: 2rem; font-weight: 700; color: #6f42c1; }
        
        .card { background: white; border-radius: 24px; padding: 24px; margin-bottom: 32px; border: 1px solid #e0e0e0; }
        body.dark-mode .card { background: #2d2d2d; }
        .card h3 { font-size: 1rem; font-weight: 600; color: #6f42c1; margin-bottom: 20px; }
        
        .thesis-item { display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f8fafc; border-radius: 16px; border-left: 3px solid #6f42c1; margin-bottom: 12px; flex-wrap: wrap; gap: 12px; }
        body.dark-mode .thesis-item { background: #3d3d3d; }
        
        .btn-archive, .btn-view { padding: 8px 20px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; cursor: pointer; border: none; }
        .btn-archive { background: #28a745; color: white; }
        .btn-view { background: #6f42c1; color: white; }
        
        .empty-state { text-align: center; padding: 40px; color: #9ca3af; }
        .empty-state i { font-size: 3rem; margin-bottom: 12px; color: #6f42c1; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 2rem; border-radius: 16px; max-width: 500px; width: 90%; }
        body.dark-mode .modal-content { background: #2d2d2d; }
        .modal-content h3 { margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border-radius: 8px; border: 1px solid #ddd; }
        .modal-buttons { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; }
        
        .toast-message { position: fixed; bottom: 30px; right: 30px; background: #10b981; color: white; padding: 12px 20px; border-radius: 12px; z-index: 1001; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f8f9fa; font-weight: 600; font-size: 0.8rem; }
        td { padding: 12px; border-bottom: 1px solid #e0e0e0; font-size: 0.85rem; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .status-public { background: #d4edda; color: #155724; }
        .status-restricted { background: #fff3cd; color: #856404; }
        .status-private { background: #f8d7da; color: #721c24; }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .main-content { padding: 20px; }
            table, thead, tbody, th, td, tr { display: block; }
            thead { display: none; }
            tr { margin-bottom: 1rem; border: 1px solid #ddd; border-radius: 8px; padding: 0.5rem; }
            td { display: flex; justify-content: space-between; align-items: center; border-bottom: none; }
            td:before { content: attr(data-label); font-weight: bold; width: 40%; }
        }
    </style>
</head>
<body>

<header class="top-nav">
    <div class="logo">Thesis<span>Archive</span></div>
    <div style="display: flex; align-items: center; gap: 20px;">
        <span><?= htmlspecialchars($fullName) ?></span>
        <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
    </div>
</header>

<main class="main-content">
    <div class="welcome-banner">
        <div><h1>Librarian Archive Management</h1><p>Welcome back, <?= htmlspecialchars($first_name) ?>! • Archive Section</p></div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div style="background:#d4edda; color:#155724; padding:1rem; border-radius:8px; margin-bottom:1rem;">✅ Thesis archived successfully! Student has been notified.</div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-content"><h3><?= $stats['pending'] ?></h3><p>Pending Archiving</p></div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-archive"></i></div><div class="stat-content"><h3><?= $stats['archived'] ?></h3><p>Archived Theses</p></div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-globe"></i></div><div class="stat-content"><h3><?= $stats['public'] ?></h3><p>Public Access</p></div></div>
    </div>

    <!-- Pending Theses for Archiving -->
    <div class="card">
        <h3><i class="fas fa-hourglass-half"></i> Theses Ready for Archiving (<?= count($pending_theses) ?>)</h3>
        <?php if (empty($pending_theses)): ?>
            <div class="empty-state"><i class="fas fa-check-circle"></i><p>No theses pending for archiving.</p></div>
        <?php else: ?>
            <?php foreach ($pending_theses as $thesis): ?>
            <div class="thesis-item">
                <div>
                    <div style="font-weight:600;"><?= htmlspecialchars($thesis['title']) ?></div>
                    <div style="font-size:0.75rem; color:#6c757d;">Student: <?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?> | Submitted: <?= date('M d, Y', strtotime($thesis['date_submitted'])) ?></div>
                </div>
                <button class="btn-archive" onclick="openArchiveModal(<?= $thesis['thesis_id'] ?>, '<?= htmlspecialchars($thesis['title']) ?>')"><i class="fas fa-archive"></i> Archive</button>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Archived Theses List -->
    <div class="card">
        <h3><i class="fas fa-folder-open"></i> Archived Theses (<?= count($archived_theses) ?>)</h3>
        <?php if (empty($archived_theses)): ?>
            <div class="empty-state"><i class="fas fa-folder-open"></i><p>No archived theses yet.</p></div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>Thesis Title</th><th>Student</th><th>Archive Date</th><th>Access</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($archived_theses as $thesis): ?>
                        <tr>
                            <td data-label="Title"><strong><?= htmlspecialchars($thesis['title']) ?></strong></td>
                            <td data-label="Student"><?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></td>
                            <td data-label="Date"><?= isset($thesis['archive_date']) ? date('M d, Y', strtotime($thesis['archive_date'])) : 'N/A' ?></td>
                            <td data-label="Access"><span class="status-badge status-<?= $thesis['access_level'] ?? 'public' ?>"><?= ucfirst($thesis['access_level'] ?? 'public') ?></span></td>
                            <td data-label="Action"><button class="btn-view" onclick="viewThesis(<?= $thesis['thesis_id'] ?>)"><i class="fas fa-eye"></i> View</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Archive Modal -->
<div id="archiveModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-archive"></i> Archive Thesis</h3>
        <form method="POST">
            <input type="hidden" name="thesis_id" id="archive_thesis_id">
            <div class="form-group"><label>Thesis Title:</label><p id="thesis_title_display" style="font-weight:bold;"></p></div>
            <div class="form-group"><label>Retention Period:</label><select name="retention_period"><option value="5">5 years (Standard)</option><option value="10">10 years (Extended)</option><option value="20">20 years (Long-term)</option><option value="50">50 years (Permanent)</option></select></div>
            <div class="form-group"><label>Access Level:</label><select name="access_level"><option value="public">Public (Anyone can view)</option><option value="restricted">Restricted (Login required)</option><option value="private">Private (Admin only)</option></select></div>
            <div class="form-group"><label>Archive Notes:</label><textarea name="archive_notes" rows="3" placeholder="Add notes..."></textarea></div>
            <div class="modal-buttons"><button type="button" class="btn-view" style="background:#6c757d;" onclick="closeArchiveModal()">Cancel</button><button type="submit" name="archive_thesis" class="btn-archive">Confirm Archive</button></div>
        </form>
    </div>
</div>

<script>
    function openArchiveModal(thesisId, thesisTitle) {
        document.getElementById('archive_thesis_id').value = thesisId;
        document.getElementById('thesis_title_display').textContent = thesisTitle;
        document.getElementById('archiveModal').style.display = 'flex';
    }
    function closeArchiveModal() { document.getElementById('archiveModal').style.display = 'none'; }
    function viewThesis(thesisId) { window.open('view_thesis.php?id=' + thesisId, '_blank'); }
    window.onclick = function(e) { if (e.target.classList.contains('modal')) e.target.style.display = 'none'; }
</script>

</body>
</html>