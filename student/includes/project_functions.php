<?php
function checkUserRole($conn, $user_id) {
    $roleQuery = "SELECT role_id FROM user_table WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($roleQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $userData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ($userData && $userData['role_id'] == 2);
}

function getUserData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT first_name, last_name FROM user_table WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $fullName = trim($user["first_name"] . " " . $user["last_name"]);
    $initials = strtoupper(substr($user["first_name"], 0, 1) . substr($user["last_name"], 0, 1));

    return [
        'fullName' => $fullName,
        'initials' => $initials,
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name']
    ];
}

function getStudentId($conn, $user_id) {
    $student_id = $user_id;
    $studentQuery = "SELECT student_id FROM student_table WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($studentQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $studentResult = $stmt->get_result();
    $studentData = $studentResult->fetch_assoc();
    $stmt->close();

    if ($studentData) {
        $student_id = $studentData['student_id'];
    }
    return $student_id;
}

function getStudentProjects($conn, $student_id) {
    $projects = [];

    try {
        $query = "SELECT t.*, 
                         COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Not Assigned') as adviser_name,
                         (SELECT COUNT(*) FROM feedback_table WHERE thesis_id = t.thesis_id) as feedback_count
                  FROM thesis_table t
                  LEFT JOIN user_table u ON t.adviser_id = u.user_id
                  WHERE t.student_id = ?
                  ORDER BY t.date_submitted DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
        $stmt->close();
        
        error_log("Projects found: " . count($projects));
        
    } catch (Exception $e) {
        error_log("Projects fetch error: " . $e->getMessage());
    }

    return $projects;
}

function getProjectCertificates($conn, $projects) {
    $certificates = [];
    
    if (!empty($projects)) {
        $thesisIds = array_column($projects, 'thesis_id');
        if (!empty($thesisIds)) {
            $placeholders = implode(',', array_fill(0, count($thesisIds), '?'));
            
            $certQuery = "SELECT thesis_id, certificate_id, certificate_file, downloaded_count 
                          FROM certificates_table 
                          WHERE thesis_id IN ($placeholders)";
            $stmt = $conn->prepare($certQuery);
            $stmt->bind_param(str_repeat('i', count($thesisIds)), ...$thesisIds);
            $stmt->execute();
            $certResult = $stmt->get_result();
            
            while ($row = $certResult->fetch_assoc()) {
                $certificates[$row['thesis_id']] = $row;
            }
            $stmt->close();
        }
    }
    
    return $certificates;
}

function calculateProgress($status, $feedback_count) {
    switch($status) {
        case 'approved':
            return 100;
        case 'rejected':
            return 30;
        case 'archived':
            return 100;
        case 'pending':
        default:
            $progress = 30 + min($feedback_count * 15, 55);
            return min($progress, 85);
    }
}

function getStatusClass($status) {
    switch($status) {
        case 'approved':
            return 'status-approved';
        case 'rejected':
            return 'status-rejected';
        case 'archived':
            return 'status-archived';
        case 'pending':
        default:
            return 'status-pending';
    }
}

function getStatusText($status) {
    switch($status) {
        case 'approved':
            return 'Approved';
        case 'rejected':
            return 'Rejected';
        case 'archived':
            return 'Archived';
        case 'pending':
        default:
            return 'Under Review';
    }
}
?>