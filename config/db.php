<?php
$servername = "localhost";
$username = "root";
$password = "Ohmm30121106*";
$dbname = "school_score_system";
$port = 3307;


try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "การเชื่อมต่อล้มเหลว: " . $e->getMessage();
    exit();
}

// Start session on all pages that include this file
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>