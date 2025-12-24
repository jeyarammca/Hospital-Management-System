<?php
$host = 'localhost';
$db_name = 'hospital_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Disable error reporting for production
    error_reporting(0);
    ini_set('display_errors', 0);

} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
