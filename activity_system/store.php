<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: activities
// โครงสร้างตารางแบบเต็มดูได้ที่ database/activity_system.sql

// =====================================================
// ตรวจสอบสิทธิ์: เฉพาะบุคลากร (staff) และผู้ดูแลระบบ (admin)
// =====================================================

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)
) {
    header("Location: ../login/index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

function fail($message)
{
    echo "<script>
        alert(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        history.back();
    </script>";
    exit;
}

// =====================================================
// รับข้อมูล
// =====================================================

$title            = trim($_POST['title'] ?? "");
$category         = trim($_POST['category'] ?? "");
$start_datetime   = $_POST['start_datetime'] ?? "";
$max_participants = $_POST['max_participants'] ?? "";
$total_hours      = $_POST['total_hours'] ?? "";
$location         = trim($_POST['location'] ?? "");
$detail           = trim($_POST['detail'] ?? "");

// =====================================================
// ตรวจข้อมูล
// =====================================================

if ($title === "" || $category === "" || $start_datetime === "" || $max_participants === "" || $total_hours === "") {
    fail('กรุณากรอกข้อมูลให้ครบถ้วน');
}

$allowed_categories = ["club", "volunteer", "trip", "competition", "other"];

if (!in_array($category, $allowed_categories, true)) {
    fail('ประเภทกิจกรรมไม่ถูกต้อง');
}

if (!is_numeric($max_participants) || (int)$max_participants < 1) {
    fail('จำนวนที่รับต้องเป็นตัวเลขและมากกว่า 0');
}

if (!is_numeric($total_hours) || (float)$total_hours < 0) {
    fail('ชั่วโมงรวมทั้งหมดต้องเป็นตัวเลขและไม่ติดลบ');
}

$start_datetime_ts = strtotime($start_datetime);

if ($start_datetime_ts === false) {
    fail('รูปแบบวันเวลาไม่ถูกต้อง');
}

$start_datetime_sql = date("Y-m-d H:i:s", $start_datetime_ts);
$max_participants    = (int)$max_participants;
$total_hours          = (float)$total_hours;

// =====================================================
// บันทึกกิจกรรม
// =====================================================

$sql = "
    INSERT INTO activities
        (title, category, detail, organizer_id, start_datetime, location, max_participants, total_hours, status, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW())
";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "sssissid",
    $title,
    $category,
    $detail,
    $user_id,
    $start_datetime_sql,
    $location,
    $max_participants,
    $total_hours
);

if (!$stmt->execute()) {
    $stmt->close();
    fail('เกิดข้อผิดพลาด ไม่สามารถบันทึกกิจกรรมได้');
}

$activity_id = $stmt->insert_id;

$stmt->close();

header("Location: sessions.php?id=" . $activity_id);
exit;
