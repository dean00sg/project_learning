<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: exam
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
        window.location.href = 'detail.php?id=" . (int)$exam_id . "';
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
    fail('คุณไม่มีสิทธิ์จัดการการสอบนี้', $exam_id);
}

if ($exam['status'] !== 'OPEN') {
    fail('การสอบนี้ถูกยกเลิกไปแล้ว', $exam_id);
}

// =====================================================
// ยกเลิกการสอบ
// =====================================================

$sql = "UPDATE exam SET status = 'CANCELLED' WHERE exam_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$stmt->close();

header("Location: detail.php?id=" . $exam_id);
exit;
