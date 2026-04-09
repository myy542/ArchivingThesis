<?php
function getStudentId($conn, $user_id) {
    error_log("=== STUDENT ID DEBUG ===");
    error_log("User ID from session: " . $user_id);

    $studentColumns = $conn->query("SHOW COLUMNS FROM student_table");
    $studentCols = [];
    while ($col = $studentColumns->fetch_assoc()) {
        $studentCols[] = $col['Field'];
    }
    error_log("Student table columns: " . implode(', ', $studentCols));

    $studentQuery = "SELECT student_id FROM student_table WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($studentQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $studentResult = $stmt->get_result();
    $studentData = $studentResult->fetch_assoc();
    $stmt->close();

    if (!$studentData) {
        error_log("No student record found for user_id: " . $user_id);
        $student_id = createStudentRecord($conn, $user_id, $studentCols);
    } else {
        $student_id = $studentData['student_id'];
        error_log("Found existing student record with ID: " . $student_id);
    }

    error_log("Final student_id to use: " . $student_id);
    error_log("=== END STUDENT ID DEBUG ===");
    
    return $student_id;
}

function createStudentRecord($conn, $user_id, $studentCols) {
    $userQuery = "SELECT * FROM user_table WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($userQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $userResult = $stmt->get_result();
    $userData = $userResult->fetch_assoc();
    $stmt->close();
    
    if ($userData) {
        error_log("User data found: " . json_encode($userData));
        
        $insertFields = ['user_id'];
        $insertValues = [$user_id];
        $paramTypes = "i";
        
        if (in_array('first_name', $studentCols) && isset($userData['first_name'])) {
            $insertFields[] = 'first_name';
            $insertValues[] = $userData['first_name'];
            $paramTypes .= "s";
        }
        
        if (in_array('last_name', $studentCols) && isset($userData['last_name'])) {
            $insertFields[] = 'last_name';
            $insertValues[] = $userData['last_name'];
            $paramTypes .= "s";
        }
        
        if (in_array('email', $studentCols) && isset($userData['email'])) {
            $insertFields[] = 'email';
            $insertValues[] = $userData['email'];
            $paramTypes .= "s";
        }
        
        $placeholders = implode(', ', array_fill(0, count($insertValues), '?'));
        $insertStudent = "INSERT INTO student_table (" . implode(', ', $insertFields) . ") VALUES ($placeholders)";
        
        error_log("Insert student query: " . $insertStudent);
        
        $stmt = $conn->prepare($insertStudent);
        if ($stmt) {
            $stmt->bind_param($paramTypes, ...$insertValues);
            
            if ($stmt->execute()) {
                $student_id = $stmt->insert_id;
                error_log("Created new student record with ID: " . $student_id);
            } else {
                error_log("Failed to insert student record: " . $stmt->error);
                $student_id = $user_id;
                error_log("Using user_id as fallback: " . $student_id);
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare student insert: " . $conn->error);
            $student_id = $user_id;
            error_log("Using user_id as fallback: " . $student_id);
        }
    } else {
        error_log("No user data found for user_id: " . $user_id);
        $student_id = $user_id;
        error_log("Using user_id as fallback: " . $student_id);
    }
    
    return $student_id;
}

function getUserData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT first_name, last_name FROM user_table WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user;
}

function getNotificationCount($conn, $user_id) {
    try {
        $notif_query = "SELECT COUNT(*) as total FROM notification_table WHERE user_id = ? AND status = 'unread'";
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

function handleThesisSubmission($conn, $user_id, $student_id, $first, $last, $post, $files) {
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
        
        $maxFileSize = 10 * 1024 * 1024; // 10MB
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
        return uploadThesis($conn, $user_id, $student_id, $first, $last, $post, $files, $fileTmp);
    }

    return ['success' => false, 'errors' => $errors];
}

function uploadThesis($conn, $user_id, $student_id, $first, $last, $post, $files, $fileTmp) {
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
        
        error_log("=== THESIS INSERT DEBUG ===");
        error_log("Student ID (submitted_by): " . $student_id);
        error_log("Title: " . $post['title']);
        
        $sql = "INSERT INTO theses (
            submitted_by, 
            author, 
            title, 
            abstract, 
            keywords, 
            department, 
            year, 
            adviser, 
            status, 
            file_path, 
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $status = 'pending';
            $authorName = $first . ' ' . $last;
            
            $stmt->bind_param(
                "isssssssss", 
                $student_id,      // submitted_by
                $authorName,      // author
                $post['title'],   // title
                $post['abstract'],// abstract
                $post['keywords'],// keywords
                $post['department'],// department
                $post['year'],    // year
                $post['adviser'], // adviser
                $status,          // status
                $dbFilePath       // file_path
            );
            
            if ($stmt->execute()) {
                $thesisId = $stmt->insert_id;
                error_log("Thesis inserted successfully with ID: " . $thesisId);
                error_log("File saved at: " . $dbFilePath);
                
                // Send notifications to faculty
                $notificationResult = sendNotifications($conn, $thesisId, $post['title'], $first, $last);
                error_log("Notifications sent: " . $notificationResult);
                
                $stmt->close();
                
                $message = "Thesis submitted successfully!";
                if ($notificationResult > 0) {
                    $message .= " Faculty members have been notified.";
                } else {
                    $message .= " (No faculty members found to notify)";
                }
                
                return ['success' => true, 'message' => $message];
            } else {
                $error = "Database error: Failed to save thesis information.";
                error_log("SQL Error: " . $stmt->error);
                $stmt->close();
                return ['success' => false, 'errors' => [$error]];
            }
        } else {
            $error = "System error: Failed to prepare query.";
            error_log("Prepare Error: " . $conn->error);
            return ['success' => false, 'errors' => [$error]];
        }
    } else {
        $error = "Failed to upload file. Please check directory permissions.";
        error_log("Upload Error: Failed to move file to " . $uploadPath);
        return ['success' => false, 'errors' => [$error]];
    }
}

function sendNotifications($conn, $thesisId, $title, $first, $last) {
    try {
        error_log("=== START NOTIFICATION ===");
        
        // Create notification_table if not exists
        $conn->query("CREATE TABLE IF NOT EXISTS notification_table (
            notification_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            thesis_id INT NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'unread',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Get all faculty members (role_id = 3)
        $facultyQuery = "SELECT user_id, first_name, last_name FROM user_table WHERE role_id = 3";
        $facultyResult = $conn->query($facultyQuery);
        
        if (!$facultyResult) {
            error_log("Error fetching faculty: " . $conn->error);
            return 0;
        }
        
        error_log("Number of faculty found: " . $facultyResult->num_rows);
        
        $notificationsInserted = 0;
        
        if ($facultyResult && $facultyResult->num_rows > 0) {
            $studentName = $first . ' ' . $last;
            $shortTitle = substr($title, 0, 50) . (strlen($title) > 50 ? '...' : '');
            $message = "New thesis from $studentName: \"$shortTitle\"";
            
            while ($faculty = $facultyResult->fetch_assoc()) {
                $facultyId = $faculty['user_id'];
                $facultyName = $faculty['first_name'] . ' ' . $faculty['last_name'];
                
                error_log("Sending notification to faculty: $facultyName (ID: $facultyId)");
                
                $notifSql = "INSERT INTO notification_table (user_id, thesis_id, message, status, created_at) 
                            VALUES (?, ?, ?, 'unread', NOW())";
                $notifStmt = $conn->prepare($notifSql);
                $notifStmt->bind_param("iis", $facultyId, $thesisId, $message);
                
                if ($notifStmt->execute()) {
                    $notificationsInserted++;
                    error_log("Notification sent successfully to faculty ID: $facultyId");
                } else {
                    error_log("Error sending to faculty $facultyId: " . $notifStmt->error);
                }
                $notifStmt->close();
            }
            
            error_log("Total notifications sent: $notificationsInserted");
        } else {
            error_log("No faculty members found with role_id = 3");
            
            // Debug: Show all users and their role_id
            $allUsers = $conn->query("SELECT user_id, first_name, last_name, role_id FROM user_table");
            if ($allUsers) {
                error_log("All users in system:");
                while ($user = $allUsers->fetch_assoc()) {
                    error_log("User: {$user['first_name']} {$user['last_name']}, role_id: {$user['role_id']}");
                }
            }
        }
        
        return $notificationsInserted;
        
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        return 0;
    }
}

function getRecentSubmissions($conn, $student_id) {
    $submissions = [];
    try {
        $recentQuery = "SELECT thesis_id, title, status, created_at, file_path 
                       FROM theses 
                       WHERE submitted_by = ? 
                       ORDER BY created_at DESC 
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