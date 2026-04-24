<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION
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

// Get student ID from URL
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($student_id == 0) {
    header("Location: dean.php?section=students");
    exit;
}

// Get student details - check if student belongs to dean's department
$student_query = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.contact_number, u.address, u.birth_date, u.status, 
                         u.department_id, d.department_name, d.department_code
                  FROM user_table u
                  LEFT JOIN department_table d ON u.department_id = d.department_id
                  WHERE u.user_id = ? AND u.role_id = 2";
$student_stmt = $conn->prepare($student_query);
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
$student = $student_result->fetch_assoc();
$student_stmt->close();

if (!$student) {
    header("Location: dean.php?section=students");
    exit;
}

// Check if student belongs to dean's department
if ($dean_department_id && $student['department_id'] != $dean_department_id) {
    $_SESSION['error_message'] = "You are not authorized to view this student. This student belongs to a different department.";
    header("Location: dean.php?section=students");
    exit;
}

// Get student's theses
$theses = [];
$theses_query = "SELECT t.thesis_id, t.title, t.adviser, t.year, t.status, t.date_submitted,
                        d.department_name, d.department_code
                 FROM thesis_table t
                 LEFT JOIN department_table d ON t.department_id = d.department_id
                 WHERE t.student_id = ? 
                 ORDER BY t.date_submitted DESC";
$theses_stmt = $conn->prepare($theses_query);
$theses_stmt->bind_param("i", $student_id);
$theses_stmt->execute();
$theses_result = $theses_stmt->get_result();
while ($row = $theses_result->fetch_assoc()) {
    $theses[] = $row;
}
$theses_stmt->close();

$pageTitle = "Student Details";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Dean Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/view_student.css">
</head>
<body>
    <div class="container">
        <a href="dean.php?section=students" class="back-link"><i class="fas fa-arrow-left"></i> Back to Students</a>
        
        <div class="card">
            <div class="card-header">
                <h1><i class="fas fa-user-graduate"></i> <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h1>
                <span class="status-badge status-<?= $student['status'] ?>"><?= $student['status'] ?></span>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-envelope"></i> Email</div>
                    <div class="info-value"><?= htmlspecialchars($student['email']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-phone"></i> Contact Number</div>
                    <div class="info-value"><?= htmlspecialchars($student['contact_number'] ?? 'Not provided') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-calendar-alt"></i> Birth Date</div>
                    <div class="info-value"><?= !empty($student['birth_date']) ? date('F d, Y', strtotime($student['birth_date'])) : 'Not provided' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-map-marker-alt"></i> Address</div>
                    <div class="info-value"><?= htmlspecialchars($student['address'] ?? 'Not provided') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-user-id"></i> Student ID</div>
                    <div class="info-value">STU-<?= str_pad($student['user_id'], 5, '0', STR_PAD_LEFT) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-building"></i> Department</div>
                    <div class="info-value"><?= htmlspecialchars($student['department_name'] ?? 'N/A') ?> (<?= htmlspecialchars($student['department_code'] ?? 'N/A') ?>)</div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h1><i class="fas fa-file-alt"></i> Thesis Submissions</h1>
                <span class="status-badge">Total: <?= count($theses) ?></span>
            </div>
            
            <?php if (empty($theses)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>No thesis submissions yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="theses-table">
                        <thead>
                            <tr>
                                <th>Thesis Title</th>
                                <th>Adviser</th>
                                <th>Department</th>
                                <th>Year</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($theses as $thesis): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($thesis['title']) ?></strong></td>
                                <td><?= htmlspecialchars($thesis['adviser'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($thesis['department_name'] ?? 'N/A') ?> (<?= htmlspecialchars($thesis['department_code'] ?? 'N/A') ?>)</span></td>
                                <td><?= htmlspecialchars($thesis['year'] ?? 'N/A') ?></td>
                                <td><span class="status-badge" style="background:#cce5ff; color:#004085;"><?= ucfirst(str_replace('_', ' ', $thesis['status'])) ?></span></td>
                                <td><a href="reviewThesis.php?id=<?= $thesis['thesis_id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        const darkMode = localStorage.getItem('darkMode') === 'true';
        if (darkMode) document.body.classList.add('dark-mode');
    </script>
</body>
</html>