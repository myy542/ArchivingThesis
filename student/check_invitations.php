<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: ../authentication/login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

$user_query = "SELECT email FROM user_table WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

echo "<h2>Your Co-Author Invitations</h2>";
echo "<p>Logged in as: " . $user['email'] . "</p>";

$invites = $conn->prepare("SELECT ti.*, tt.title, u.first_name, u.last_name 
                           FROM thesis_invitations ti 
                           JOIN thesis_table tt ON ti.thesis_id = tt.thesis_id 
                           JOIN user_table u ON ti.invited_by = u.user_id
                           WHERE ti.invited_user_id = ? AND ti.is_read = 'pending'");
$invites->bind_param("i", $user_id);
$invites->execute();
$result = $invites->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<div style='border:1px solid #ddd; padding:10px; margin:10px 0;'>";
        echo "<p><strong>Thesis:</strong> " . $row['title'] . "</p>";
        echo "<p><strong>Invited by:</strong> " . $row['first_name'] . " " . $row['last_name'] . "</p>";
        echo "<a href='accept_invitation.php?id=" . $row['invitation_id'] . "&action=accept'>Accept</a> | ";
        echo "<a href='accept_invitation.php?id=" . $row['invitation_id'] . "&action=decline'>Decline</a>";
        echo "</div>";
    }
} else {
    echo "<p>No pending invitations.</p>";
}

$pending = $conn->prepare("SELECT * FROM pending_invitations WHERE email = ?");
$pending->bind_param("s", $user['email']);
$pending->execute();
$pending_result = $pending->get_result();

if ($pending_result->num_rows > 0) {
    echo "<h3>Pending Invitations (from before registration):</h3>";
    while ($row = $pending_result->fetch_assoc()) {
        echo "<div style='border:1px solid #ff9800; padding:10px; margin:10px 0;'>";
        echo "<p><strong>Invited by:</strong> " . $row['invited_by_name'] . "</p>";
        echo "<p>You were invited to collaborate! <a href='accept_pending.php?id=" . $row['id'] . "'>Accept Now</a></p>";
        echo "</div>";
    }
}
?>