<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_staffs, exam
// โครงสร้างตารางแบบเต็มดูได้ที่ database/classroom_system.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$exam_id = (int)($_POST['exam_id'] ?? 0);

function fail($message, $exam_id)
{
    echo "<script>
        alert(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        window.location.href = 'edit.php?id=" . (int)$exam_id . "';
    </script>";
    exit;
}

if ($exam_id <= 0) {
    fail('ไม่พบการสอบ', 0);
}

// =====================================================
// ตรวจสิทธิ์: ผู้สร้างการสอบ หรือแอดมิน เท่านั้น
// =====================================================

$sql = "SELECT created_by, status FROM exam WHERE exam_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    fail('ไม่พบการสอบ', $exam_id);
}

$sql = "
    SELECT ua.role, ust.staff_id
    FROM user_accounts ua
    LEFT JOIN user_staffs ust ON ust.user_id = ua.user_id
    WHERE ua.user_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$viewer = $stmt->get_result()->fetch_assoc();
$stmt->close();

$is_admin = $viewer && $viewer['role'] === 'admin';
$is_owner = $viewer && !empty($viewer['staff_id']) && (int)$viewer['staff_id'] === (int)$exam['created_by'];

if (!$is_owner && !$is_admin) {
    http_response_code(403);
    fail('คุณไม่มีสิทธิ์แก้ไขการสอบนี้', $exam_id);
}

if ($exam['status'] === 'CANCELLED') {
    fail('การสอบนี้ถูกยกเลิกไปแล้ว ไม่สามารถแก้ไขได้', $exam_id);
}

// =====================================================
// รับ + ตรวจข้อมูล
// =====================================================

$exam_name    = trim($_POST['exam_name'] ?? "");
$exam_type    = trim($_POST['exam_type'] ?? "");
$subject_name = trim($_POST['subject_name'] ?? "");
$exam_date     = $_POST['exam_date'] ?? "";
$start_time    = $_POST['start_time'] ?? "";
$end_time      = $_POST['end_time'] ?? "";
$detail        = trim($_POST['detail'] ?? "");

if ($exam_name === "" || $subject_name === "" || $exam_date === "" || $start_time === "" || $end_time === "") {
    fail('กรุณากรอกข้อมูลการสอบให้ครบถ้วน', $exam_id);
}

$allowed_types = ["MIDTERM", "FINAL", "QUIZ", "OTHER"];

if (!in_array($exam_type, $allowed_types, true)) {
    $exam_type = "OTHER";
}

if (strtotime($exam_date) === false) {
    fail('รูปแบบวันที่สอบไม่ถูกต้อง', $exam_id);
}

if ($end_time <= $start_time) {
    fail('เวลาสิ้นสุดต้องอยู่หลังเวลาเริ่ม', $exam_id);
}

// =====================================================
// บันทึกการแก้ไข
// =====================================================

$sql = "
    UPDATE exam
    SET exam_name = ?, exam_type = ?, subject_name = ?, exam_date = ?, start_time = ?, end_time = ?, detail = ?
    WHERE exam_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssssi", $exam_name, $exam_type, $subject_name, $exam_date, $start_time, $end_time, $detail, $exam_id);

if (!$stmt->execute()) {
    $stmt->close();
    fail('เกิดข้อผิดพลาด ไม่สามารถบันทึกการแก้ไขได้', $exam_id);
}

$stmt->close();

header("Location: detail.php?id=" . $exam_id);
exit;
