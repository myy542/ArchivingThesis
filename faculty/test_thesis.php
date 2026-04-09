<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

$thesis_id = isset($_GET['id']) ? (int)$_GET['id'] : 4;

// I-check ang thesis status
$query = "SELECT thesis_id, title, status FROM theses WHERE thesis_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $thesis_id);
$stmt->execute();
$thesis = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo "<h1>DEBUG INFO</h1>";
echo "<p>Thesis ID: " . $thesis_id . "</p>";
echo "<p>Thesis Title: " . ($thesis ? $thesis['title'] : 'Not found') . "</p>";
echo "<p>Thesis Status: <strong style='color:red; font-size:20px;'>" . ($thesis ? $thesis['status'] : 'Not found') . "</strong></p>";

echo "<h2>Expected condition for Approve button:</h2>";
echo "<p>if (\$thesis['status'] == 'pending') - " . (($thesis && $thesis['status'] == 'pending') ? "<span style='color:green;'>TRUE ✅</span>" : "<span style='color:red;'>FALSE ❌</span>") . "</p>";

echo "<h2>Update status to pending:</h2>";
echo "<a href='?id=$thesis_id&update=pending' style='background:green; color:white; padding:10px; text-decoration:none;'>CLICK HERE TO UPDATE STATUS TO 'pending'</a>";

if(isset($_GET['update']) && $_GET['update'] == 'pending') {
    $update = "UPDATE theses SET status = 'pending' WHERE thesis_id = ?";
    $stmt = $conn->prepare($update);
    $stmt->bind_param("i", $thesis_id);
    if($stmt->execute()) {
        echo "<p style='color:green;'>✅ Status updated to 'pending'! <a href='reviewThesis.php?id=$thesis_id'>Go back to Review Thesis</a></p>";
    } else {
        echo "<p style='color:red;'>❌ Update failed: " . $conn->error . "</p>";
    }
    $stmt->close();
}
?>