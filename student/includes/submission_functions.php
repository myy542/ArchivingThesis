<?php

function getUserData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT first_name, last_name, email FROM user_table WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user;
}

// Dili na kinahanglan ang getOrCreateStudentId - diretso na lang
function getNotificationCount($conn, $user_id) {
    try {
        // Check if 'is_read' column exists, if not use 'status' for backward compatibility
        $col_check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_read'");
        if ($col_check && $col_check->num_rows > 0) {
            $notif_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0";
        } else {
            // Fallback to status column
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

function handleThesisSubmission($conn, $user_id, $first, $last, $post, $files) {
    $errors = [];
    
    $title       = trim($post["title"] ?? "");
    $abstract    = trim($post["abstract"] ?? "");
    $adviser     = trim($post["adviser"] ?? "");
    $keywords    = trim($post["keywords"] ?? "");
    $department  = trim($post["department"] ?? "");
    $year        = trim($post["year"] ?? "");

    // Manual validation
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
    
    if (empty($department)) $errors[] = "Department is required.";
    if (empty($year)) $errors[] = "Year is required.";

    // File validation
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
        return uploadThesis($conn, $user_id, $first, $last, $post, $files, $fileTmp);
    }

    return ['success' => false, 'errors' => $errors];
}

function uploadThesis($conn, $user_id, $first, $last, $post, $files, $fileTmp) {
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
        
        // UPDATED: Gamiton ang user_id isip student_id
        $student_id = $user_id;
        
        $sql = "INSERT INTO thesis_table (
            student_id,
            title, 
            abstract, 
            keywords, 
            department, 
            year, 
            adviser, 
            status, 
            file_path, 
            date_submitted
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $status = 'pending';
            
            $stmt->bind_param(
                "issssssss", 
                $student_id,
                $post['title'],
                $post['abstract'],
                $post['keywords'],
                $post['department'],
                $post['year'],
                $post['adviser'],
                $status,
                $dbFilePath
            );
            
            if ($stmt->execute()) {
                $thesisId = $stmt->insert_id;
                
                // SEND NOTIFICATIONS TO ALL FACULTY
                sendNotificationsToFaculty($conn, $thesisId, $post['title'], $first, $last);
                
                $stmt->close();
                
                return ['success' => true, 'message' => "Thesis submitted successfully! Faculty members have been notified."];
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

// SEND NOTIFICATIONS TO FACULTY - UPDATED to use is_read instead of status
function sendNotificationsToFaculty($conn, $thesisId, $title, $first, $last) {
    $facultyQuery = "SELECT user_id FROM user_table WHERE role_id = 3";
    $facultyResult = $conn->query($facultyQuery);
    
    if (!$facultyResult || $facultyResult->num_rows == 0) {
        error_log("No faculty members found");
        return false;
    }
    
    $studentName = $first . ' ' . $last;
    $shortTitle = substr($title, 0, 50) . (strlen($title) > 50 ? '...' : '');
    $message = "New thesis submission from $studentName: \"$shortTitle\"";
    $link = "../faculty/reviewThesis.php?id=" . $thesisId;
    $type = "thesis_submission";
    
    // Check if 'is_read' column exists in notifications table
    $col_check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_read'");
    $use_is_read = ($col_check && $col_check->num_rows > 0);
    
    $inserted = 0;
    
    while ($faculty = $facultyResult->fetch_assoc()) {
        $facultyId = $faculty['user_id'];
        
        if ($use_is_read) {
            // Use is_read column
            $notifSql = "INSERT INTO notifications (user_id, thesis_id, message, type, link, is_read, created_at) 
                        VALUES (?, ?, ?, ?, ?, 0, NOW())";
            $notifStmt = $conn->prepare($notifSql);
            $notifStmt->bind_param("iisss", $facultyId, $thesisId, $message, $type, $link);
        } else {
            // Fallback to status column
            $notifSql = "INSERT INTO notifications (user_id, thesis_id, message, type, link, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, 0, NOW())";
            $notifStmt = $conn->prepare($notifSql);
            $notifStmt->bind_param("iisss", $facultyId, $thesisId, $message, $type, $link);
        }
        
        if ($notifStmt->execute()) {
            $inserted++;
        }
        $notifStmt->close();
    }
    
    return $inserted > 0;
}

function getRecentSubmissions($conn, $user_id) {
    $submissions = [];
    try {
        $student_id = $user_id;
        
        $recentQuery = "SELECT thesis_id, title, status, file_path, date_submitted as created_at
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

?>