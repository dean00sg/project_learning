<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: class_schedule, classroom, user_accounts
// โครงสร้างตารางแบบเต็มดูได้ที่ database/attendance_system.sql

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

$schedule_id   = (int)($_POST['schedule_id'] ?? 0);
$classroom_id  = (int)($_POST['classroom_id'] ?? 0);
$staff_id      = (int)($_POST['staff_id'] ?? 0);
$subject_code  = trim($_POST['subject_code'] ?? "");
$subject_name  = trim($_POST['subject_name'] ?? "");
$day_of_week   = (int)($_POST['day_of_week'] ?? 0);
$start_time    = $_POST['start_time'] ?? "";
$end_time      = $_POST['end_time'] ?? "";
$room          = trim($_POST['room'] ?? "");
$is_active     = isset($_POST['is_active']) ? 1 : 0;

// =====================================================
// ตรวจข้อมูล
// =====================================================

if ($schedule_id <= 0) {
    fail('ไม่พบคาบเรียน');
}

if ($classroom_id <= 0 || $staff_id <= 0 || $subject_name === "" || $day_of_week < 1 || $day_of_week > 7 || $start_time === "" || $end_time === "") {
    fail('กรุณากรอกข้อมูลให้ครบถ้วน');
}

if ($end_time <= $start_time) {
    fail('เวลาสิ้นสุดต้องอยู่หลังเวลาเริ่ม');
}

// =====================================================
// บันทึกการแก้ไข
// =====================================================

$sql = "
    UPDATE class_schedule
    SET classroom_id = ?, subject_code = ?, subject_name = ?, staff_id = ?,
        day_of_week = ?, start_time = ?, end_time = ?, room = ?, is_active = ?
    WHERE schedule_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "issiisssii",
    $classroom_id,
    $subject_code,
    $subject_name,
    $staff_id,
    $day_of_week,
    $start_time,
    $end_time,
    $room,
    $is_active,
    $schedule_id
);

if (!$stmt->execute()) {
    $stmt->close();
    fail('เกิดข้อผิดพลาด ไม่สามารถบันทึกการแก้ไขได้');
}

$stmt->close();

header("Location: schedule_main.php");
exit;
