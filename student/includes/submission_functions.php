<?php

function getUserData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT first_name, last_name, email, department_id FROM user_table WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user;
}

// Get notification count using is_read
function getNotificationCount($conn, $user_id) {
    try {
        $col_check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_read'");
        if ($col_check && $col_check->num_rows > 0) {
            $notif_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0";
        } else {
            $notif_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND status = 0";
        }
        $stmt = $conn->prepare($notif_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $notifResult = $stmt->get_result()->fetch_assoc();
        $count = $notifResult['total'] ?? 0;
        $stmt->close();
        return $count;
    } catch (Exception $e) {
        return 0;
    }
}

// ==================== GET DEPARTMENT INFO ====================
function getDepartmentName($conn, $department_id) {
    if (empty($department_id)) return null;
    $stmt = $conn->prepare("SELECT department_name FROM department_table WHERE department_id = ?");
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $dept = $result->fetch_assoc();
    $stmt->close();
    return $dept ? $dept['department_name'] : null;
}

// ==================== SEND NOTIFICATIONS TO RESEARCH ADVISERS (DEPARTMENT EXCLUSIVE) ====================
function sendNotificationsToFaculty($conn, $thesisId, $title, $first, $last, $department_id, $emailSender = null) {
    // Only get research advisers (role_id = 3) from the SAME department
    $facultyQuery = "SELECT user_id, email, first_name, last_name FROM user_table WHERE role_id = 3 AND department_id = ?";
    $facultyStmt = $conn->prepare($facultyQuery);
    $facultyStmt->bind_param("i", $department_id);
    $facultyStmt->execute();
    $facultyResult = $facultyStmt->get_result();
    
    if (!$facultyResult || $facultyResult->num_rows == 0) {
        error_log("No research advisers found for department_id: $department_id");
        $facultyStmt->close();
        return false;
    }
    
    $deptName = getDepartmentName($conn, $department_id);
    $deptDisplay = $deptName ?: "Department";
    
    $studentName = $first . ' ' . $last;
    $shortTitle = strlen($title) > 50 ? substr($title, 0, 50) . '...' : $title;
    $message = "📢 New thesis submission from " . $studentName . " (" . $deptDisplay . "): \"" . $shortTitle . "\"";
    $link = "../faculty/reviewThesis.php?id=" . $thesisId;
    $type = "thesis_submission";
    
    $col_check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_read'");
    $use_is_read = ($col_check && $col_check->num_rows > 0);
    
    $inserted = 0;
    
    while ($faculty = $facultyResult->fetch_assoc()) {
        $facultyId = $faculty['user_id'];
        
        if ($use_is_read) {
            $notifSql = "INSERT INTO notifications (user_id, thesis_id, message, type, link, is_read, created_at) 
                        VALUES (?, ?, ?, ?, ?, 0, NOW())";
            $notifStmt = $conn->prepare($notifSql);
            $notifStmt->bind_param("iisss", $facultyId, $thesisId, $message, $type, $link);
        } else {
            $notifSql = "INSERT INTO notifications (user_id, thesis_id, message, type, link, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, 0, NOW())";
            $notifStmt = $conn->prepare($notifSql);
            $notifStmt->bind_param("iisss", $facultyId, $thesisId, $message, $type, $link);
        }
        
        if ($notifStmt->execute()) {
            $inserted++;
        }
        $notifStmt->close();
        
        if ($emailSender && method_exists($emailSender, 'sendThesisSubmission')) {
            try {
                $emailSender->sendThesisSubmission(
                    $faculty['email'],
                    $studentName,
                    $title,
                    $thesisId,
                    $deptDisplay
                );
            } catch (Exception $e) {
                error_log("Email failed to {$faculty['email']}: " . $e->getMessage());
            }
        }
    }
    $facultyStmt->close();
    
    return $inserted > 0;
}

// ==================== MAIN SUBMISSION FUNCTION ====================
function handleThesisSubmission($conn, $user_id, $first, $last, $post, $files, $emailSender = null) {
    $errors = [];
    
    $title       = trim($post["title"] ?? "");
    $abstract    = trim($post["abstract"] ?? "");
    $adviser     = trim($post["adviser"] ?? "");
    $keywords    = trim($post["keywords"] ?? "");
    $department_id = trim($post["department_id"] ?? "");
    $year        = trim($post["year"] ?? "");
    $invite_emails = trim($post["invite_emails"] ?? "");

    if (empty($title)) $errors[] = "Thesis title is required.";
    if (strlen($title) < 5) $errors[] = "Title must be at least 5 characters long.";
    if (strlen($title) > 255) $errors[] = "Title must not exceed 255 characters.";
    
    if (empty($abstract)) $errors[] = "Abstract is required.";
    if (strlen($abstract) < 50) $errors[] = "Abstract must be at least 50 characters long.";
    if (strlen($abstract) > 5000) $errors[] = "Abstract must not exceed 5000 characters.";
    
    if (empty($adviser)) $errors[] = "Adviser name is required.";
    if (empty($keywords)) $errors[] = "Keywords are required.";
    
    $keywordArray = array_map('trim', explode(',', $keywords));
    if (count($keywordArray) < 3) $errors[] = "Please provide at least 3 keywords.";
    
    if (empty($department_id)) $errors[] = "Department is required.";
    if (empty($year)) $errors[] = "Year is required.";

    if (empty($files["manuscript"]["name"])) {
        $errors[] = "Please upload the manuscript (PDF).";
    } else {
        $file = $files["manuscript"];
        $fileName = $file["name"];
        $fileTmp = $file["tmp_name"];
        $fileSize = $file["size"];
        $fileError = $file["error"];
        
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if ($ext !== "pdf") {
            $errors[] = "Only PDF files are allowed.";
        }
        
        $maxFileSize = 10 * 1024 * 1024;
        if ($fileSize > $maxFileSize) {
            $errors[] = "File size must not exceed 10MB.";
        }
        
        if ($fileError !== 0) {
            $errors[] = "Error uploading file. Please try again.";
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileTmp);
        finfo_close($finfo);
        
        if ($mimeType !== 'application/pdf') {
            $errors[] = "The file must be a valid PDF document.";
        }
    }

    if (empty($errors)) {
        return uploadThesis($conn, $user_id, $first, $last, $post, $files, $fileTmp, $emailSender);
    }

    return ['success' => false, 'errors' => $errors];
}

function uploadThesis($conn, $user_id, $first, $last, $post, $files, $fileTmp, $emailSender = null) {
    $uploadDir = __DIR__ . "/../../uploads/manuscripts/";
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $timestamp = time();
    $uniqueId = uniqid();
    $safeTitle = preg_replace('/[^a-zA-Z0-9]/', '_', $post['title']);
    $safeTitle = substr($safeTitle, 0, 50);
    $newFileName = $timestamp . '_' . $uniqueId . '_' . $safeTitle . '.pdf';
    $uploadPath = $uploadDir . $newFileName;
    
    if (move_uploaded_file($fileTmp, $uploadPath)) {
        chmod($uploadPath, 0644);
        
        $dbFilePath = 'uploads/manuscripts/' . $newFileName;
        
        $student_id = $user_id;
        $department_id = $post['department_id'] ?? '';
        
        // BASED SA IMONG ACTUAL TABLE - GAMIT ANG department_id, is_archived
        $sql = "INSERT INTO thesis_table (
            student_id,
            title, 
            abstract, 
            keywords, 
            department_id, 
            year, 
            adviser, 
            file_path, 
            date_submitted,
            is_archived
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param(
                "isssisss", 
                $student_id,
                $post['title'],
                $post['abstract'],
                $post['keywords'],
                $department_id,
                $post['year'],
                $post['adviser'],
                $dbFilePath
            );
            
            if ($stmt->execute()) {
                $thesisId = $stmt->insert_id;
                
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
                
                // Add owner as collaborator
                $collabQuery = "INSERT INTO thesis_collaborators (thesis_id, user_id, role) VALUES (?, ?, 'owner')";
                $collabStmt = $conn->prepare($collabQuery);
                $collabStmt->bind_param("ii", $thesisId, $user_id);
                $collabStmt->execute();
                $collabStmt->close();
                
                // Send notifications to research advisers (SAME DEPARTMENT ONLY)
                $notifyResult = sendNotificationsToFaculty($conn, $thesisId, $post['title'], $first, $last, $department_id, $emailSender);
                
                $stmt->close();
                
                $message = "Thesis submitted successfully!";
                if ($notifyResult) {
                    $deptName = getDepartmentName($conn, $department_id);
                    $message .= " Research advisers in " . ($deptName ?: "your") . " department have been notified.";
                } else {
                    $message .= " No research advisers found for your department.";
                }
                
                return ['success' => true, 'message' => $message];
            } else {
                $stmt->close();
                return ['success' => false, 'errors' => ["Database error: Failed to save thesis information."]];
            }
        } else {
            return ['success' => false, 'errors' => ["System error: Failed to prepare query."]];
        }
    } else {
        return ['success' => false, 'errors' => ["Failed to upload file. Please check directory permissions."]];
    }
}

function getRecentSubmissions($conn, $user_id) {
    $submissions = [];
    try {
        $student_id = $user_id;
        
        $recentQuery = "SELECT thesis_id, title, file_path, date_submitted as created_at,
                               CASE WHEN is_archived = 1 THEN 'archived' ELSE 'pending' END as status
                       FROM thesis_table 
                       WHERE student_id = ? 
                       ORDER BY date_submitted DESC 
                       LIMIT 5";
        $stmt = $conn->prepare($recentQuery);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $submissions[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Recent submissions error: " . $e->getMessage());
    }
    return $submissions;
}

// ==================== INVITE CO-AUTHORS FUNCTION ====================
function inviteCoAuthors($conn, $thesis_id, $invite_emails, $invited_by, $inviter_name, $thesis_title) {
    $invited_count = 0;
    $invited_list = [];
    $invite_errors = [];
    
    if (empty($invite_emails)) {
        return ['count' => 0, 'list' => [], 'errors' => []];
    }
    
    $emails = array_map('trim', explode(',', $invite_emails));
    
    // Create thesis_invitations table if not exists - MATCH SA IMONG TABLE STRUCTURE (is_read column)
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
    
    foreach ($emails as $email) {
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Check if user exists
            $userCheck = $conn->prepare("SELECT user_id, first_name, last_name, email FROM user_table WHERE email = ?");
            $userCheck->bind_param("s", $email);
            $userCheck->execute();
            $invited_user = $userCheck->get_result()->fetch_assoc();
            $userCheck->close();
            
            if ($invited_user && $invited_user['user_id'] != $invited_by) {
                // Check if already invited
                $checkInvite = $conn->prepare("SELECT * FROM thesis_invitations WHERE thesis_id = ? AND invited_user_id = ?");
                $checkInvite->bind_param("ii", $thesis_id, $invited_user['user_id']);
                $checkInvite->execute();
                $existing = $checkInvite->get_result()->fetch_assoc();
                $checkInvite->close();
                
                if (!$existing) {
                    // Send invitation - using 'is_read' column (pending/accepted/declined)
                    $inviteQuery = "INSERT INTO thesis_invitations (thesis_id, invited_user_id, invited_by, is_read) VALUES (?, ?, ?, 'pending')";
                    $inviteStmt = $conn->prepare($inviteQuery);
                    $inviteStmt->bind_param("iii", $thesis_id, $invited_user['user_id'], $invited_by);
                    $inviteStmt->execute();
                    $inviteStmt->close();
                    
                    // Send notification to invited user
                    $notifMessage = "📢 " . $inviter_name . " invited you to collaborate on thesis: \"" . $thesis_title . "\"";
                    $notifQuery = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'thesis_invitation', 0, NOW())";
                    $notifStmt = $conn->prepare($notifQuery);
                    $notifStmt->bind_param("iis", $invited_user['user_id'], $thesis_id, $notifMessage);
                    $notifStmt->execute();
                    $notifStmt->close();
                    
                    $invited_count++;
                    $invited_list[] = $invited_user['email'];
                }
            } elseif ($invited_user && $invited_user['user_id'] == $invited_by) {
                $invite_errors[] = "You cannot invite yourself as a co-author.";
            } else {
                $invite_errors[] = "User with email '" . $email . "' not found in the system.";
            }
        } elseif (!empty($email)) {
            $invite_errors[] = "Invalid email address: '" . $email . "'";
        }
    }
    
    return ['count' => $invited_count, 'list' => $invited_list, 'errors' => $invite_errors];
}
?>