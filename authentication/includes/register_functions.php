<?php
function handleRegistration($conn, $post) {
    $role_id     = (int)($post["role_id"] ?? 2);
    $first_name  = trim($post["first_name"] ?? "");
    $last_name   = trim($post["last_name"] ?? "");
    $email       = trim($post["email"] ?? "");
    $username    = trim($post["username"] ?? "");
    $password    = $post["password"] ?? "";
    $cpassword   = $post["cpassword"] ?? "";
    $department  = trim($post["department"] ?? "");
    $birth_date  = trim($post["birth_date"] ?? "");
    $address     = trim($post["address"] ?? "");
    $contact_number = trim($post["contact_number"] ?? "");
    $status      = "1";

    // Validation
    if ($first_name === "" || $last_name === "" || $email === "" || 
        $username === "" || $password === "" || $cpassword === "" || 
        $contact_number === "") {
        return ['success' => false, 'message' => "Please fill in all required fields."];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => "Invalid email format."];
    }
    
    if ($password !== $cpassword) {
        return ['success' => false, 'message' => "Password and Confirm Password do not match."];
    }
    
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => "Password must be at least 6 characters."];
    }
    
    if (!ctype_digit($contact_number) || strlen($contact_number) < 10) {
        return ['success' => false, 'message' => "Contact number must be numeric and at least 10 digits."];
    }
    
    if (!in_array($role_id, [1, 2, 3, 4, 5, 6], true)) {
        return ['success' => false, 'message' => "Invalid role selected."];
    }

    // Check if username or email already exists
    $check = $conn->prepare("SELECT user_id FROM user_table WHERE username = ? OR email = ? LIMIT 1");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $exists = $check->get_result();

    if ($exists && $exists->num_rows > 0) {
        $check->close();
        return ['success' => false, 'message' => "Username or Email already exists."];
    }
    $check->close();

    // Insert new user
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $profile_picture = "default.png";

    $stmt = $conn->prepare("
        INSERT INTO user_table
        (role_id, first_name, last_name, email, username, password, department, birth_date, address, contact_number, status, profile_picture)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isssssssssss",
        $role_id,
        $first_name,
        $last_name,
        $email,
        $username,
        $hashed,
        $department,
        $birth_date,
        $address,
        $contact_number,
        $status,
        $profile_picture
    );

    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => "Registered successfully! You can now login."];
    } else {
        $error = "Register failed: " . $conn->error;
        $stmt->close();
        return ['success' => false, 'message' => $error];
    }
}
?>