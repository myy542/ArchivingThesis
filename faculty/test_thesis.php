<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

$thesis_id = isset($_GET['id']) ? (int)$_GET['id'] : 4;

// Check thesis status - FIXED: Use thesis_table instead of theses
$query = "SELECT thesis_id, title, is_archived FROM thesis_table WHERE thesis_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $thesis_id);
$stmt->execute();
$thesis = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo "<h1>DEBUG INFO</h1>";
echo "<p>Thesis ID: " . $thesis_id . "</p>";
echo "<p>Thesis Title: " . ($thesis ? $thesis['title'] : 'Not found') . "</p>";
echo "<p>Thesis Status (is_archived): <strong style='color:red; font-size:20px;'>" . ($thesis ? ($thesis['is_archived'] == 1 ? 'Archived' : 'Pending/Active') : 'Not found') . "</strong></p>";

echo "<h2>Expected condition for Approve button:</h2>";
echo "<p>if (\$thesis['is_archived'] == 0) - " . (($thesis && $thesis['is_archived'] == 0) ? "<span style='color:green;'>TRUE ✅</span>" : "<span style='color:red;'>FALSE ❌</span>") . "</p>";

echo "<h2>Update status to pending (is_archived = 0):</h2>";
echo "<a href='?id=$thesis_id&update=pending' style='background:green; color:white; padding:10px; text-decoration:none;'>CLICK HERE TO UPDATE STATUS TO 'pending' (is_archived = 0)</a>";

if(isset($_GET['update']) && $_GET['update'] == 'pending') {
    $update = "UPDATE thesis_table SET is_archived = 0 WHERE thesis_id = ?";
    $stmt = $conn->prepare($update);
    $stmt->bind_param("i", $thesis_id);
    if($stmt->execute()) {
        echo "<p style='color:green;'>✅ Status updated to 'pending' (is_archived = 0)! <a href='reviewThesis.php?id=$thesis_id'>Go back to Review Thesis</a></p>";
    } else {
        echo "<p style='color:red;'>❌ Update failed: " . $conn->error . "</p>";
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Thesis Status</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- External CSS -->
    <link rel="stylesheet" href="css/test_thesis.css">
</head>
<body>
    <!-- Pass PHP data to JavaScript -->
    <script>
        window.debugData = {
            thesisId: <?php echo $thesis_id; ?>,
            isArchived: <?php echo $thesis ? $thesis['is_archived'] : 'null'; ?>,
            title: '<?php echo addslashes($thesis ? $thesis['title'] : 'Not found'); ?>'
        };
    </script>
    
    <!-- External JavaScript -->
    <script src="js/test_thesis.js"></script>
</body>
</html>