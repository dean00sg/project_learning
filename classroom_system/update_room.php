<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_staffs, exam, exam_rooms, exam_students
// โครงสร้างตารางแบบเต็มดูได้ที่ database/classroom_system.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id      = (int)$_SESSION['user_id'];
$exam_room_id = (int)($_POST['exam_room_id'] ?? 0);

function fail($message, $exam_room_id)
{
    echo "<script>
        alert(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        window.location.href = 'edit_room.php?exam_room_id=" . (int)$exam_room_id . "';
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
    SELECT r.exam_room_id, r.exam_id, e.created_by
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
    fail('คุณไม่มีสิทธิ์แก้ไขห้องสอบนี้', $exam_room_id);
}

// =====================================================
// รับ + ตรวจข้อมูล
// =====================================================

$room_code            = trim($_POST['room_code'] ?? "");
$room_name            = trim($_POST['room_name'] ?? "");
$building             = trim($_POST['building'] ?? "");
$floor                 = $_POST['floor'] ?? "";
$capacity              = $_POST['capacity'] ?? "";
$supervisor_staff_id   = (int)($_POST['supervisor_staff_id'] ?? 0);

if ($room_code === "" || $capacity === "") {
    fail('กรุณากรอกข้อมูลให้ครบถ้วน', $exam_room_id);
}

if (!is_numeric($capacity) || (int)$capacity < 1) {
    fail('ความจุที่นั่งต้องเป็นตัวเลขและมากกว่า 0', $exam_room_id);
}

$capacity = (int)$capacity;
$floor     = $floor !== "" ? (int)$floor : null;
$supervisor_staff_id = $supervisor_staff_id > 0 ? $supervisor_staff_id : null;

// ห้ามลดความจุต่ำกว่าจำนวนที่จัดที่นั่งไปแล้ว
$sql = "SELECT COUNT(*) AS n FROM exam_students WHERE exam_room_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $exam_room_id);
$stmt->execute();
$assigned_count = (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);
$stmt->close();

if ($capacity < $assigned_count) {
    fail("ลดความจุต่ำกว่าจำนวนที่จัดที่นั่งไปแล้วไม่ได้ (จัดไปแล้ว $assigned_count คน)", $exam_room_id);
}

// =====================================================
// บันทึกการแก้ไข
// =====================================================

$sql = "
    UPDATE exam_rooms
    SET room_code = ?, room_name = ?, building = ?, floor = ?, capacity = ?, supervisor_staff_id = ?
    WHERE exam_room_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssiiii", $room_code, $room_name, $building, $floor, $capacity, $supervisor_staff_id, $exam_room_id);

if (!$stmt->execute()) {
    $stmt->close();
    fail('เกิดข้อผิดพลาด ไม่สามารถบันทึกการแก้ไขได้', $exam_room_id);
}

$stmt->close();

header("Location: detail.php?id=" . $room['exam_id']);
exit;
