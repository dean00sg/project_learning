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

$classroom_id  = (int)($_POST['classroom_id'] ?? 0);
$staff_id      = (int)($_POST['staff_id'] ?? 0);
$subject_code  = trim($_POST['subject_code'] ?? "");
$subject_name  = trim($_POST['subject_name'] ?? "");
$day_of_week   = (int)($_POST['day_of_week'] ?? 0);
$start_time    = $_POST['start_time'] ?? "";
$end_time      = $_POST['end_time'] ?? "";
$room          = trim($_POST['room'] ?? "");

// =====================================================
// ตรวจข้อมูล
// =====================================================

if ($classroom_id <= 0 || $staff_id <= 0 || $subject_name === "" || $day_of_week < 1 || $day_of_week > 7 || $start_time === "" || $end_time === "") {
    fail('กรุณากรอกข้อมูลให้ครบถ้วน');
}

if ($end_time <= $start_time) {
    fail('เวลาสิ้นสุดต้องอยู่หลังเวลาเริ่ม');
}

$sql = "SELECT classroom_id FROM classroom WHERE classroom_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $classroom_id);
$stmt->execute();
$classroom = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$classroom) {
    fail('ไม่พบห้องเรียนที่เลือก');
}

$sql = "SELECT user_id FROM user_staffs WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher) {
    fail('ไม่พบครูผู้สอนที่เลือก');
}

// =====================================================
// บันทึกตารางสอน
// =====================================================

$sql = "
    INSERT INTO class_schedule
        (classroom_id, subject_code, subject_name, staff_id, day_of_week, start_time, end_time, room, is_active)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "issiisss",
    $classroom_id,
    $subject_code,
    $subject_name,
    $staff_id,
    $day_of_week,
    $start_time,
    $end_time,
    $room
);

if (!$stmt->execute()) {
    $stmt->close();
    fail('เกิดข้อผิดพลาด ไม่สามารถบันทึกตารางสอนได้');
}

$stmt->close();

header("Location: schedule_main.php");
exit;
