<?php
// Function to get user data - works with different possible table names
function getUserData($conn, $user_id) {
    $possible_tables = ['user_table', 'users', 'user', 'tbl_users', 'accounts', 'students', 'user_accounts'];
    
    foreach ($possible_tables as $table) {
        $check_query = "SHOW TABLES LIKE '$table'";
        $check_result = $conn->query($check_query);
        
        if ($check_result && $check_result->num_rows > 0) {
            $query = "SELECT * FROM `$table` WHERE ";
            
            $columns = $conn->query("DESCRIBE `$table`");
            $id_column = null;
            while ($col = $columns->fetch_assoc()) {
                if ($col['Field'] == 'user_id' || $col['Field'] == 'id' || $col['Field'] == 'student_id') {
                    $id_column = $col['Field'];
                    break;
                }
            }
            
            if ($id_column) {
                $query .= "`$id_column` = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                
                if ($user_data) {
                    if (isset($user_data['firstname']) && !isset($user_data['first_name'])) {
                        $user_data['first_name'] = $user_data['firstname'];
                    }
                    if (isset($user_data['lastname']) && !isset($user_data['last_name'])) {
                        $user_data['last_name'] = $user_data['lastname'];
                    }
                    if (isset($user_data['contact']) && !isset($user_data['contact_number'])) {
                        $user_data['contact_number'] = $user_data['contact'];
                    }
                    if (isset($user_data['birthdate']) && !isset($user_data['birth_date'])) {
                        $user_data['birth_date'] = $user_data['birthdate'];
                    }
                    return $user_data;
                }
            }
        }
    }
    return null;
}

// Function to get notification count - FIXED to use is_read
function getNotificationCount($conn, $user_id) {
    $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] ?? 0;
}

// Function to handle profile update
function handleProfileUpdate($conn, $user_id, $post_data, $file_data) {
    $possible_tables = ['user_table', 'users', 'user', 'tbl_users', 'accounts', 'students', 'user_accounts'];
    $user_table = null;
    $id_column = null;
    
    foreach ($possible_tables as $table) {
        $check_query = "SHOW TABLES LIKE '$table'";
        $check_result = $conn->query($check_query);
        
        if ($check_result && $check_result->num_rows > 0) {
            $columns = $conn->query("DESCRIBE `$table`");
            while ($col = $columns->fetch_assoc()) {
                if ($col['Field'] == 'user_id' || $col['Field'] == 'id' || $col['Field'] == 'student_id') {
                    $user_table = $table;
                    $id_column = $col['Field'];
                    break 2;
                }
            }
        }
    }
    
    if (!$user_table) {
        return "User table not found in database.";
    }
    
    $first_name = trim($post_data['first_name'] ?? '');
    $last_name = trim($post_data['last_name'] ?? '');
    $email = trim($post_data['email'] ?? '');
    $contact_number = trim($post_data['contact_number'] ?? '');
    $birth_date = trim($post_data['birth_date'] ?? '');
    $address = trim($post_data['address'] ?? '');
    
    if (empty($first_name) || empty($last_name) || empty($email)) {
        return "First name, last name, and email are required.";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email format.";
    }
    
    $check_query = "SELECT `$id_column` FROM `$user_table` WHERE email = ? AND `$id_column` != ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("si", $email, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        return "Email is already used by another account.";
    }
    
    $profile_picture = null;
    if (isset($file_data['profile_picture']) && $file_data['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . "/../uploads/profile_pictures/";
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($file_data['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            return "Only JPG, JPEG, and PNG files are allowed.";
        }
        
        $new_filename = "user_" . $user_id . "_" . time() . "." . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file_data['profile_picture']['tmp_name'], $upload_path)) {
            $profile_picture = $new_filename;
            
            $old_query = "SELECT profile_picture FROM `$user_table` WHERE `$id_column` = ?";
            $old_stmt = $conn->prepare($old_query);
            $old_stmt->bind_param("i", $user_id);
            $old_stmt->execute();
            $old_result = $old_stmt->get_result();
            $old_user = $old_result->fetch_assoc();
            
            if ($old_user && isset($old_user['profile_picture']) && $old_user['profile_picture'] && file_exists($upload_dir . $old_user['profile_picture'])) {
                unlink($upload_dir . $old_user['profile_picture']);
            }
        } else {
            return "Failed to upload profile picture.";
        }
    }
    
    $columns = $conn->query("DESCRIBE `$user_table`");
    $available_columns = [];
    while ($col = $columns->fetch_assoc()) {
        $available_columns[] = $col['Field'];
    }
    
    $update_fields = [];
    $update_values = [];
    $types = "";
    
    if (in_array('first_name', $available_columns)) {
        $update_fields[] = "first_name = ?";
        $update_values[] = $first_name;
        $types .= "s";
    } elseif (in_array('firstname', $available_columns)) {
        $update_fields[] = "firstname = ?";
        $update_values[] = $first_name;
        $types .= "s";
    }
    
    if (in_array('last_name', $available_columns)) {
        $update_fields[] = "last_name = ?";
        $update_values[] = $last_name;
        $types .= "s";
    } elseif (in_array('lastname', $available_columns)) {
        $update_fields[] = "lastname = ?";
        $update_values[] = $last_name;
        $types .= "s";
    }
    
    if (in_array('email', $available_columns)) {
        $update_fields[] = "email = ?";
        $update_values[] = $email;
        $types .= "s";
    }
    
    if (in_array('contact_number', $available_columns)) {
        $update_fields[] = "contact_number = ?";
        $update_values[] = $contact_number;
        $types .= "s";
    } elseif (in_array('contact', $available_columns)) {
        $update_fields[] = "contact = ?";
        $update_values[] = $contact_number;
        $types .= "s";
    }
    
    if (in_array('birth_date', $available_columns)) {
        $update_fields[] = "birth_date = ?";
        $update_values[] = $birth_date;
        $types .= "s";
    } elseif (in_array('birthdate', $available_columns)) {
        $update_fields[] = "birthdate = ?";
        $update_values[] = $birth_date;
        $types .= "s";
    }
    
    if (in_array('address', $available_columns)) {
        $update_fields[] = "address = ?";
        $update_values[] = $address;
        $types .= "s";
    }
    
    if ($profile_picture && in_array('profile_picture', $available_columns)) {
        $update_fields[] = "profile_picture = ?";
        $update_values[] = $profile_picture;
        $types .= "s";
    }
    
    if (empty($update_fields)) {
        return "No updatable fields found in database.";
    }
    
    $update_values[] = $user_id;
    $types .= "i";
    
    $query = "UPDATE `$user_table` SET " . implode(", ", $update_fields) . " WHERE `$id_column` = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$update_values);
    
    if ($stmt->execute()) {
        return false;
    } else {
        return "Failed to update profile: " . $conn->error;
    }
}
?>