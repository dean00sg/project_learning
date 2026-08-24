
<?php
$host   = 'localhost';
$dbname = 'repair_system';
$user   = 'root';
$pass   = '';      

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die('เชื่อมต่อ DB ไม่ได้: ' . $conn->connect_error);
}