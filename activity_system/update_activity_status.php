<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: activities
// โครงสร้างตารางแบบเต็มดูได้ที่ database/activity_system.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id     = (int)$_SESSION['user_id'];
$activity_id = (int)($_POST['activity_id'] ?? 0);
$new_status  = $_POST['status'] ?? '';

function fail($message, $activity_id)
{
    echo "<script>
        alert(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        window.location.href = 'detail.php?id=" . (int)$activity_id . "';
    </script>";
    exit;
}

if ($activity_id <= 0) {
    fail('ไม่พบกิจกรรม', 0);
}

$allowed_status = ['open', 'closed', 'cancelled', 'finished'];

if (!in_array($new_status, $allowed_status, true)) {
    fail('สถานะไม่ถูกต้อง', $activity_id);
}

// =====================================================
// ตรวจสิทธิ์: เจ้าของกิจกรรม หรือแอดมิน เท่านั้น
// =====================================================

$sql = "SELECT organizer_id FROM activities WHERE activity_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$activity = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$activity) {
    fail('ไม่พบกิจกรรม', $activity_id);
}

$sql = "SELECT role FROM user_accounts WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$viewer = $stmt->get_result()->fetch_assoc();
$stmt->close();

$is_admin = $viewer && $viewer['role'] === 'admin';
$is_owner = (int)$activity['organizer_id'] === $user_id;

if (!$is_owner && !$is_admin) {
    http_response_code(403);
    fail('คุณไม่มีสิทธิ์แก้ไขกิจกรรมนี้', $activity_id);
}

// =====================================================
// อัปเดตสถานะ
// =====================================================

$sql = "UPDATE activities SET status = ? WHERE activity_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $new_status, $activity_id);
$stmt->execute();
$stmt->close();

header("Location: detail.php?id=" . $activity_id);
exit;
