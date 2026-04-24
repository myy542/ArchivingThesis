
<?php
// test_smtp.php - Ibutang ni sa STUDENT folder
require_once __DIR__ . '/../config/smtp_config.php';  // Add __DIR__ . '/../'

echo "<h2>SMTP Connection Test</h2>";

// Test SMTP connection
$host = 'smtp.gmail.com';
$port = 587;

echo "Testing connection to $host:$port...<br>";

$connection = @fsockopen($host, $port, $errno, $errstr, 10);

if ($connection) {
    echo "✅ Connection successful!<br>";
    fclose($connection);
} else {
    echo "❌ Connection failed: $errstr ($errno)<br>";
}

echo "<hr>";

// Test DNS
echo "Testing DNS resolution...<br>";
$ip = gethostbyname('smtp.gmail.com');
if ($ip != 'smtp.gmail.com') {
    echo "✅ DNS resolved: $ip<br>";
} else {
    echo "❌ DNS resolution failed<br>";
}

echo "<hr>";
echo "If both tests passed, the issue is with your Gmail credentials.<br>";
echo "If connection failed, check your internet/firewall.";
?>