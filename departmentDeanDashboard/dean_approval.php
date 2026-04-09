<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'dean') {
    header("Location: ../authentication/login.php");
    exit;
}

$dean_id = $_SESSION['user_id'];

// Get dean info
$dean_query = "SELECT first_name, last_name FROM user_table WHERE user_id = ?";
$dean_stmt = $conn->prepare($dean_query);
$dean_stmt->bind_param("i", $dean_id);
$dean_stmt->execute();
$dean_data = $dean_stmt->get_result()->fetch_assoc();
$dean_stmt->close();

$first_name = $dean_data['first_name'] ?? '';
$last_name = $dean_data['last_name'] ?? '';
$fullName = trim($first_name . " " . $last_name);
$initials = !empty($first_name) && !empty($last_name) ? strtoupper(substr($first_name,0,1).substr($last_name,0,1)) : "DN";

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

// Function to notify Librarian
function notifyLibrarian($conn, $thesis_id, $thesis_title, $student_name, $dean_name) {
    $lib_query = "SELECT user_id FROM user_table WHERE role = 'librarian' OR role_id = 7";
    $lib_result = $conn->query($lib_query);
    
    if ($lib_result && $lib_result->num_rows > 0) {
        while ($librarian = $lib_result->fetch_assoc()) {
            $message = "✅ Thesis approved for archiving: \"" . $thesis_title . "\" from student " . $student_name . ". Approved by Dean: " . $dean_name . ". Please archive this thesis.";
            $insert = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'librarian_archive', 0, NOW())";
            $stmt = $conn->prepare($insert);
            $stmt->bind_param("iis", $librarian['user_id'], $thesis_id, $message);
            $stmt->execute();
            $stmt->close();
        }
    }
    return true;
}

// Get theses forwarded to dean
$pending_theses = [];
$pending_query = "SELECT t.*, u.first_name, u.last_name, u.email 
                  FROM thesis_table t
                  JOIN user_table u ON t.student_id = u.user_id
                  WHERE t.status = 'Forwarded to Dean'
                  ORDER BY t.forwarded_to_dean_at DESC";
$pending_result = $conn->query($pending_query);
if ($pending_result && $pending_result->num_rows > 0) {
    while ($row = $pending_result->fetch_assoc()) {
        $pending_theses[] = $row;
    }
}

// Approve thesis (forward to librarian for archiving)
if (isset($_POST['approve_thesis']) && isset($_POST['thesis_id'])) {
    $thesis_id = intval($_POST['thesis_id']);
    $thesis_title = $_POST['thesis_title'] ?? '';
    $student_name = $_POST['student_name'] ?? '';
    $dean_name = $fullName;
    
    $update = "UPDATE thesis_table SET status = 'Approved by Dean', approved_by_dean_at = NOW() WHERE thesis_id = ?";
    $stmt = $conn->prepare($update);
    $stmt->bind_param("i", $thesis_id);
    $stmt->execute();
    $stmt->close();
    
    notifyLibrarian($conn, $thesis_id, $thesis_title, $student_name, $dean_name);
    
    echo json_encode(['success' => true]);
    exit;
}

// Reject thesis
if (isset($_POST['reject_thesis']) && isset($_POST['thesis_id'])) {
    $thesis_id = intval($_POST['thesis_id']);
    $reason = $_POST['reason'] ?? '';
    
    $update = "UPDATE thesis_table SET status = 'rejected', dean_rejection_reason = ? WHERE thesis_id = ?";
    $stmt = $conn->prepare($update);
    $stmt->bind_param("si", $reason, $thesis_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true]);
    exit;
}

$stats = [
    'pending' => count($pending_theses),
    'approved' => $conn->query("SELECT COUNT(*) FROM thesis_table WHERE status = 'Approved by Dean'")->fetch_row()[0] ?? 0,
    'rejected' => $conn->query("SELECT COUNT(*) FROM thesis_table WHERE status = 'rejected' AND approved_by_dean_at IS NULL")->fetch_row()[0] ?? 0
];

$pageTitle = "Dean Approval";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dean Approval - Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f4f8; color: #1f2937; }
        body.dark-mode { background: #1a1a1a; color: #e0e0e0; }
        
        .top-nav { position: fixed; top: 0; right: 0; left: 0; height: 70px; background: white; display: flex; align-items: center; justify-content: space-between; padding: 0 32px; z-index: 99; border-bottom: 1px solid #e0e0e0; }
        body.dark-mode .top-nav { background: #2d2d2d; }
        
        .logo { font-size: 1.3rem; font-weight: 700; color: #1e3a5f; }
        .logo span { color: #3b82f6; }
        
        .profile-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #1e3a5f, #3b82f6); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        
        .main-content { margin-top: 70px; padding: 32px; }
        
        .welcome-banner { background: linear-gradient(135deg, #1e3a5f, #2c5a8c); border-radius: 28px; padding: 32px 36px; color: white; margin-bottom: 32px; }
        .welcome-banner h1 { font-size: 1.6rem; margin-bottom: 8px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 32px; }
        .stat-card { background: white; border-radius: 20px; padding: 24px; display: flex; align-items: center; gap: 20px; border: 1px solid #e0e0e0; }
        body.dark-mode .stat-card { background: #2d2d2d; }
        .stat-icon { width: 60px; height: 60px; border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; background: #e8f0fe; color: #1e3a5f; }
        .stat-content h3 { font-size: 2rem; font-weight: 700; color: #1e3a5f; }
        
        .card { background: white; border-radius: 24px; padding: 24px; margin-bottom: 32px; border: 1px solid #e0e0e0; }
        body.dark-mode .card { background: #2d2d2d; }
        .card h3 { font-size: 1rem; font-weight: 600; color: #1e3a5f; margin-bottom: 20px; }
        
        .thesis-item { display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f8fafc; border-radius: 16px; border-left: 3px solid #3b82f6; margin-bottom: 12px; flex-wrap: wrap; gap: 12px; }
        body.dark-mode .thesis-item { background: #3d3d3d; }
        .thesis-title { font-weight: 600; font-size: 0.95rem; }
        .thesis-meta { font-size: 0.75rem; color: #6c757d; margin-top: 5px; }
        
        .btn-approve, .btn-reject { padding: 8px 20px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; cursor: pointer; border: none; }
        .btn-approve { background: #28a745; color: white; }
        .btn-reject { background: #f8f9fa; color: #dc3545; border: 1px solid #dee2e6; }
        
        .empty-state { text-align: center; padding: 40px; color: #9ca3af; }
        .empty-state i { font-size: 3rem; margin-bottom: 12px; color: #3b82f6; }
        
        .toast-message { position: fixed; bottom: 30px; right: 30px; background: #10b981; color: white; padding: 12px 20px; border-radius: 12px; z-index: 1001; animation: slideIn 0.3s ease; }
        .toast-message.error { background: #ef4444; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .main-content { padding: 20px; }
            .thesis-item { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header class="top-nav">
    <div class="logo">Thesis<span>Manager</span></div>
    <div style="display: flex; align-items: center; gap: 20px;">
        <span><?= htmlspecialchars($fullName) ?></span>
        <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
    </div>
</header>

<main class="main-content">
    <div class="welcome-banner">
        <div><h1>Dean's Office</h1><p>Welcome back, <?= htmlspecialchars($first_name) ?>! • Graduate School</p></div>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-content"><h3><?= $stats['pending'] ?></h3><p>Pending Approval</p></div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-content"><h3><?= $stats['approved'] ?></h3><p>Approved Theses</p></div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-times-circle"></i></div><div class="stat-content"><h3><?= $stats['rejected'] ?></h3><p>Rejected</p></div></div>
    </div>

    <div class="card">
        <h3><i class="fas fa-file-alt"></i> Theses Waiting for Dean's Approval (<?= count($pending_theses) ?>)</h3>
        <?php if (empty($pending_theses)): ?>
            <div class="empty-state"><i class="fas fa-check-circle"></i><p>No pending theses for approval.</p></div>
        <?php else: ?>
            <?php foreach ($pending_theses as $thesis): ?>
            <div class="thesis-item" data-thesis-id="<?= $thesis['thesis_id'] ?>" data-thesis-title="<?= htmlspecialchars($thesis['title']) ?>" data-student-name="<?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?>">
                <div>
                    <div class="thesis-title"><?= htmlspecialchars($thesis['title']) ?></div>
                    <div class="thesis-meta"><i class="fas fa-user"></i> <?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?> | <i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($thesis['forwarded_to_dean_at'] ?? $thesis['date_submitted'])) ?></div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn-approve" onclick="approveThesis(this)"><i class="fas fa-check"></i> Approve & Send to Librarian</button>
                    <button class="btn-reject" onclick="showRejectReason(this)"><i class="fas fa-times"></i> Reject</button>
                </div>
                <div class="reject-reason" style="display: none; width: 100%; margin-top: 10px;">
                    <input type="text" placeholder="Enter rejection reason..." style="width: 100%; padding: 8px; border-radius: 20px; border: 1px solid #ddd;">
                    <div style="display: flex; gap: 8px; margin-top: 8px;">
                        <button class="btn-reject" onclick="confirmReject(this)">Confirm Reject</button>
                        <button class="btn-approve" onclick="cancelReject(this)">Cancel</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script>
    function showToast(message, isError = false) {
        const toast = document.createElement('div');
        toast.className = 'toast-message' + (isError ? ' error' : '');
        toast.innerHTML = '<i class="fas ' + (isError ? 'fa-exclamation-circle' : 'fa-check-circle') + '"></i> ' + message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    function approveThesis(button) {
        const container = button.closest('.thesis-item');
        const thesisId = container.dataset.thesisId;
        const thesisTitle = container.dataset.thesisTitle;
        const studentName = container.dataset.studentName;
        
        if (confirm('Approve this thesis? It will be sent to the Librarian for archiving.')) {
            button.disabled = true;
            button.innerHTML = 'Processing...';
            
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'approve_thesis=1&thesis_id=' + thesisId + '&thesis_title=' + encodeURIComponent(thesisTitle) + '&student_name=' + encodeURIComponent(studentName)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Thesis approved! Librarian has been notified.');
                    container.remove();
                    location.reload();
                } else {
                    showToast('Error approving thesis', true);
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-check"></i> Approve & Send to Librarian';
                }
            })
            .catch(error => {
                showToast('Network error', true);
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-check"></i> Approve & Send to Librarian';
            });
        }
    }

    function showRejectReason(button) {
        const container = button.closest('.thesis-item');
        const actionsDiv = container.querySelector('div[style*="display: flex; gap: 10px;"]');
        const rejectDiv = container.querySelector('.reject-reason');
        actionsDiv.style.display = 'none';
        rejectDiv.style.display = 'block';
    }

    function cancelReject(button) {
        const container = button.closest('.thesis-item');
        const actionsDiv = container.querySelector('div[style*="display: flex; gap: 10px;"]');
        const rejectDiv = container.querySelector('.reject-reason');
        const input = rejectDiv.querySelector('input');
        actionsDiv.style.display = 'flex';
        rejectDiv.style.display = 'none';
        if (input) input.value = '';
    }

    function confirmReject(button) {
        const container = button.closest('.thesis-item');
        const thesisId = container.dataset.thesisId;
        const input = container.querySelector('.reject-reason input');
        const reason = input ? input.value : '';
        
        if (!reason.trim()) {
            showToast('Please enter a reason for rejection', true);
            return;
        }
        
        button.disabled = true;
        button.innerHTML = 'Processing...';
        
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'reject_thesis=1&thesis_id=' + thesisId + '&reason=' + encodeURIComponent(reason)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Thesis rejected successfully');
                container.remove();
                location.reload();
            } else {
                showToast('Error rejecting thesis', true);
                button.disabled = false;
                button.innerHTML = 'Confirm Reject';
            }
        })
        .catch(error => {
            showToast('Network error', true);
            button.disabled = false;
            button.innerHTML = 'Confirm Reject';
        });
    }
</script>

</body>
</html>