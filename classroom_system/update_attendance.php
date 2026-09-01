<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: exam_rooms, exam, exam_students
// โครงสร้างตารางแบบเต็มดูได้ที่ database/classroom_system.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id       = (int)$_SESSION['user_id'];
$exam_room_id  = (int)($_POST['exam_room_id'] ?? 0);
$attendance    = $_POST['attendance'] ?? [];

function fail($message, $exam_room_id)
{
    echo "<script>
        alert(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        window.location.href = 'room_roster.php?exam_room_id=" . (int)$exam_room_id . "';
    </script>";
    exit;
}

if ($exam_room_id <= 0) {
    fail('ไม่พบห้องสอบ', 0);
}

// =====================================================
// ตรวจสิทธิ์: ผู้สร้างการสอบ หรือแอดมิน เท่านั้น
// =====================================================

$sql = "
    SELECT r.exam_room_id, e.created_by
    FROM exam_rooms r
    INNER JOIN exam e ON e.exam_id = r.exam_id
    WHERE r.exam_room_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $exam_room_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    fail('ไม่พบห้องสอบ', $exam_room_id);
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
$is_owner = $viewer && !empty($viewer['staff_id']) && (int)$viewer['staff_id'] === (int)$room['created_by'];

if (!$is_owner && !$is_admin) {
    http_response_code(403);
    fail('คุณไม่มีสิทธิ์บันทึกการเข้าสอบของห้องนี้', $exam_room_id);
}

// =====================================================
// บันทึกสถานะการเข้าสอบทีละคน (ตรวจว่า exam_student_id เป็นของห้องนี้จริง)
// =====================================================

foreach ($attendance as $exam_student_id => $data) {
    $exam_student_id = (int)$exam_student_id;
    $status          = $data['status'] ?? '';
    $remark          = trim($data['remark'] ?? '');

    if (!in_array($status, ['present', 'absent'], true)) {
        continue;
    }

    $sql = "SELECT exam_student_id FROM exam_students WHERE exam_student_id = ? AND exam_room_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $exam_student_id, $exam_room_id);
    $stmt->execute();
    $valid = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$valid) {
        continue;
    }

    $sql = "UPDATE exam_students SET attendance_status = ?, checkin_at = NOW(), remark = ? WHERE exam_student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $status, $remark, $exam_student_id);
    $stmt->execute();
    $stmt->close();
}

echo "<script>
    alert('บันทึกการเข้าสอบเรียบร้อยแล้ว');
    window.location.href = 'room_roster.php?exam_room_id=" . (int)$exam_room_id . "';
</script>";
