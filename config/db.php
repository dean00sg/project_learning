<?php
/*
=====================================================================
ไฟล์นี้เป็นจุดเชื่อมต่อฐานข้อมูลกลางของทุกโมดูล

โครงสร้างตารางทั้งหมด (CREATE TABLE) ดูได้ที่ database/schema.sql
ตาราง: user_accounts, user_students, user_staffs,
       classroom, repair_requests, repair_process
=====================================================================
*/

$host   = 'localhost';
$dbname = 'project_learning';
$user   = 'root';
$pass   = '';

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die('เชื่อมต่อ DB ไม่ได้: ' . $conn->connect_error);
}
