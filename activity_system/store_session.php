<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: activities, activity_sessions
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

function fail($message, $activity_id)
{
    echo "<script>
        alert(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        window.location.href = 'sessions.php?id=" . (int)$activity_id . "';
    </script>";
    exit;
}

if ($activity_id <= 0) {
    fail('ไม่พบกิจกรรม', 0);
}

// =====================================================
// ตรวจสิทธิ์: เจ้าของกิจกรรม หรือแอดมิน เท่านั้น
// =====================================================

$sql = "SELECT organizer_id, total_hours FROM activities WHERE activity_id = ? LIMIT 1";
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
    fail('คุณไม่มีสิทธิ์จัดการกิจกรรมนี้', $activity_id);
}

// =====================================================
// รับ + ตรวจข้อมูล
// =====================================================

$session_datetime = $_POST['session_datetime'] ?? "";
$hours_awarded    = $_POST['hours_awarded'] ?? "";
$note             = trim($_POST['note'] ?? "");

if ($session_datetime === "" || $hours_awarded === "") {
    fail('กรุณากรอกข้อมูลให้ครบถ้วน', $activity_id);
}

if (!is_numeric($hours_awarded) || (float)$hours_awarded < 0) {
    fail('ชั่วโมงที่ได้รับต้องเป็นตัวเลขและไม่ติดลบ', $activity_id);
}

$session_datetime_ts = strtotime($session_datetime);

if ($session_datetime_ts === false) {
    fail('รูปแบบวันเวลาไม่ถูกต้อง', $activity_id);
}

$session_datetime_sql = date("Y-m-d H:i:s", $session_datetime_ts);
$hours_awarded          = (float)$hours_awarded;

// =====================================================
// บันทึกรอบใหม่
//
// ล็อกแถวกิจกรรมด้วย transaction กันสองคนเพิ่มรอบพร้อมกันจนรวมชั่วโมงเกิน
// total_hours ที่ตั้งไว้
// =====================================================

$conn->begin_transaction();

try {
    $sql = "SELECT total_hours FROM activities WHERE activity_id = ? LIMIT 1 FOR UPDATE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $activity_id);
    $stmt->execute();
    $locked_activity = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$locked_activity) {
        throw new Exception('ไม่พบกิจกรรม');
    }

    $sql = "SELECT COALESCE(SUM(hours_awarded), 0) AS allocated FROM activity_sessions WHERE activity_id = ? FOR UPDATE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $activity_id);
    $stmt->execute();
    $allocated_hours = (float)$stmt->get_result()->fetch_assoc()['allocated'];
    $stmt->close();

    $remaining_hours = (float)$locked_activity['total_hours'] - $allocated_hours;

    if ($hours_awarded > $remaining_hours) {
        throw new Exception('ชั่วโมงที่ใส่เกินยอดที่เหลือให้จัดสรร (เหลือ ' . number_format($remaining_hours, 1) . ' ชั่วโมง)');
    }

    $sql = "
        INSERT INTO activity_sessions (activity_id, session_datetime, hours_awarded, note, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isds", $activity_id, $session_datetime_sql, $hours_awarded, $note);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    fail($e->getMessage(), $activity_id);
}

header("Location: sessions.php?id=" . $activity_id);
exit;
