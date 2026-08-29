<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: activities, activity_sessions, activity_signups, activity_attendance
// โครงสร้างตารางแบบเต็มดูได้ที่ database/activity_system.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$session_id = (int)($_POST['session_id'] ?? 0);
$attendance = $_POST['attendance'] ?? [];

function fail($message, $session_id)
{
    echo "<script>
        alert(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        window.location.href = 'attendance.php?session_id=" . (int)$session_id . "';
    </script>";
    exit;
}

if ($session_id <= 0) {
    fail('ไม่พบรอบกิจกรรม', 0);
}

// =====================================================
// ตรวจสิทธิ์: เจ้าของกิจกรรม หรือแอดมิน เท่านั้น
// =====================================================

$sql = "
    SELECT s.session_id, s.activity_id, a.organizer_id
    FROM activity_sessions s
    INNER JOIN activities a ON a.activity_id = s.activity_id
    WHERE s.session_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $session_id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$session) {
    fail('ไม่พบรอบกิจกรรม', $session_id);
}

$sql = "SELECT role FROM user_accounts WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$viewer = $stmt->get_result()->fetch_assoc();
$stmt->close();

$is_admin = $viewer && $viewer['role'] === 'admin';
$is_owner = (int)$session['organizer_id'] === $user_id;

if (!$is_owner && !$is_admin) {
    http_response_code(403);
    fail('คุณไม่มีสิทธิ์เช็คชื่อกิจกรรมนี้', $session_id);
}

// =====================================================
// บันทึกการเช็คชื่อทีละคน (ตรวจว่า registration_id เป็นของกิจกรรมนี้จริง)
// =====================================================

$activity_id = (int)$session['activity_id'];

foreach ($attendance as $registration_id => $attend_status) {
    $registration_id = (int)$registration_id;

    if (!in_array($attend_status, ['present', 'absent'], true)) {
        continue;
    }

    $sql = "SELECT registration_id FROM activity_signups WHERE registration_id = ? AND activity_id = ? AND status = 'registered' LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $registration_id, $activity_id);
    $stmt->execute();
    $valid = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$valid) {
        continue;
    }

    $sql = "
        INSERT INTO activity_attendance (session_id, registration_id, attend_status, checked_by, checked_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE attend_status = VALUES(attend_status), checked_by = VALUES(checked_by), checked_at = NOW()
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisi", $session_id, $registration_id, $attend_status, $user_id);
    $stmt->execute();
    $stmt->close();
}

echo "<script>
    alert('บันทึกการเช็คชื่อเรียบร้อยแล้ว');
    window.location.href = 'attendance.php?session_id=" . (int)$session_id . "';
</script>";
