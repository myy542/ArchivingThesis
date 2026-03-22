<?php
function getUserData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT first_name, last_name, email, contact_number, address, birth_date, profile_picture FROM user_table WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user;
}

function getNotificationCount($conn, $user_id) {
    try {
        $notif_query = "SELECT COUNT(*) as total FROM notification_table WHERE user_id = ? AND status != 'read'";
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

function handleProfileUpdate($conn, $user_id, $post, $files) {
    $first_name  = trim($post["first_name"] ?? "");
    $last_name   = trim($post["last_name"] ?? "");
    $email       = trim($post["email"] ?? "");
    $contact_num = trim($post["contact_number"] ?? "");
    $birth_date  = trim($post["birth_date"] ?? "");
    $address     = trim($post["address"] ?? "");

    // Validate required fields
    if ($first_name === "" || $last_name === "" || $email === "") {
        return "First name, last name, and email are required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email format.";
    }

    // Handle file upload
    $newFileName = null;
    if (!empty($files["profile_picture"]["name"])) {
        $uploadResult = uploadProfilePicture($files["profile_picture"], $user_id);
        if ($uploadResult['error']) {
            return $uploadResult['error'];
        }
        $newFileName = $uploadResult['filename'];
    }

    // Update database
    return updateUserProfile($conn, $user_id, [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'contact_number' => $contact_num,
        'address' => $address,
        'birth_date' => $birth_date,
        'profile_picture' => $newFileName
    ]);
}

function uploadProfilePicture($file, $user_id) {
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

    if (!in_array($ext, ["jpg", "jpeg", "png"])) {
        return ['error' => "Only JPG, JPEG or PNG files allowed.", 'filename' => null];
    }

    $uploadDir = __DIR__ . "/../../uploads/profile_pictures/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $newFileName = "user_" . $user_id . "_" . time() . "." . $ext;
    $dest = $uploadDir . $newFileName;

    if (!move_uploaded_file($file["tmp_name"], $dest)) {
        return ['error' => "Failed to upload picture.", 'filename' => null];
    }

    return ['error' => null, 'filename' => $newFileName];
}

function updateUserProfile($conn, $user_id, $data) {
    if ($data['profile_picture']) {
        $sql = "UPDATE user_table SET 
                first_name=?, last_name=?, email=?, contact_number=?, address=?, birth_date=?, profile_picture=?, updated_at=NOW() 
                WHERE user_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssi", 
            $data['first_name'], 
            $data['last_name'], 
            $data['email'], 
            $data['contact_number'], 
            $data['address'], 
            $data['birth_date'], 
            $data['profile_picture'], 
            $user_id
        );
    } else {
        $sql = "UPDATE user_table SET 
                first_name=?, last_name=?, email=?, contact_number=?, address=?, birth_date=?, updated_at=NOW() 
                WHERE user_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", 
            $data['first_name'], 
            $data['last_name'], 
            $data['email'], 
            $data['contact_number'], 
            $data['address'], 
            $data['birth_date'], 
            $user_id
        );
    }

    if ($stmt->execute()) {
        $stmt->close();
        return ""; // No error
    } else {
        $error = "Update failed: " . $stmt->error;
        $stmt->close();
        return $error;
    }
}
?>