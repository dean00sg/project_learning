<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_staffs, exam, exam_rooms
// โครงสร้างตารางแบบเต็มดูได้ที่ database/classroom_system.sql

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

$sql = "SELECT staff_id FROM user_staffs WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$staff) {
    fail('บัญชีนี้ยังไม่มีข้อมูลบุคลากร กรุณาติดต่อผู้ดูแลระบบ');
}

$staff_id = (int)$staff['staff_id'];

// =====================================================
// รับข้อมูลการสอบ
// =====================================================

$exam_name    = trim($_POST['exam_name'] ?? "");
$exam_type    = trim($_POST['exam_type'] ?? "");
$subject_name = trim($_POST['subject_name'] ?? "");
$exam_date     = $_POST['exam_date'] ?? "";
$start_time    = $_POST['start_time'] ?? "";
$end_time      = $_POST['end_time'] ?? "";
$detail        = trim($_POST['detail'] ?? "");

if ($exam_name === "" || $subject_name === "" || $exam_date === "" || $start_time === "" || $end_time === "") {
    fail('กรุณากรอกข้อมูลการสอบให้ครบถ้วน');
}

$allowed_types = ["MIDTERM", "FINAL", "QUIZ", "OTHER"];

if (!in_array($exam_type, $allowed_types, true)) {
    $exam_type = "OTHER";
}

if (strtotime($exam_date) === false) {
    fail('รูปแบบวันที่สอบไม่ถูกต้อง');
}

if ($end_time <= $start_time) {
    fail('เวลาสิ้นสุดต้องอยู่หลังเวลาเริ่ม');
}

// =====================================================
// รับข้อมูลห้องสอบ (หลายห้อง — array ขนานกัน)
// =====================================================

$room_codes    = $_POST['room_code'] ?? [];
$room_names    = $_POST['room_name'] ?? [];
$buildings     = $_POST['building'] ?? [];
$floors        = $_POST['floor'] ?? [];
$capacities    = $_POST['capacity'] ?? [];
$supervisors   = $_POST['supervisor_staff_id'] ?? [];

$room_count = count($room_codes);

if ($room_count === 0) {
    fail('กรุณาเพิ่มห้องสอบอย่างน้อย 1 ห้อง');
}

$rooms = [];

for ($i = 0; $i < $room_count; $i++) {
    $room_code = trim($room_codes[$i] ?? "");
    $capacity  = $capacities[$i] ?? "";

    if ($room_code === "" || $capacity === "") {
        continue;
    }

    if (!is_numeric($capacity) || (int)$capacity < 1) {
        fail('ความจุที่นั่งต้องเป็นตัวเลขและมากกว่า 0');
    }

    $supervisor_staff_id = (int)($supervisors[$i] ?? 0);

    $rooms[] = [
        "room_code"            => $room_code,
        "room_name"            => trim($room_names[$i] ?? ""),
        "building"             => trim($buildings[$i] ?? ""),
        "floor"                 => $floors[$i] !== "" ? (int)$floors[$i] : null,
        "capacity"              => (int)$capacity,
        "supervisor_staff_id"   => $supervisor_staff_id > 0 ? $supervisor_staff_id : null,
    ];
}

if (empty($rooms)) {
    fail('กรุณาเพิ่มห้องสอบอย่างน้อย 1 ห้อง');
}

// =====================================================
// บันทึกการสอบ + ห้องสอบ
// =====================================================

$conn->begin_transaction();

try {
    $sql = "
        INSERT INTO exam (exam_name, exam_type, subject_name, exam_date, start_time, end_time, detail, status, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'OPEN', ?, NOW())
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssi", $exam_name, $exam_type, $subject_name, $exam_date, $start_time, $end_time, $detail, $staff_id);
    $stmt->execute();

    $exam_id = $stmt->insert_id;

    $stmt->close();

    $sql = "
        INSERT INTO exam_rooms (exam_id, room_code, room_name, building, floor, capacity, supervisor_staff_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt = $conn->prepare($sql);

    foreach ($rooms as $room) {
        $stmt->bind_param(
            "isssiii",
            $exam_id,
            $room['room_code'],
            $room['room_name'],
            $room['building'],
            $room['floor'],
            $room['capacity'],
            $room['supervisor_staff_id']
        );
        $stmt->execute();
    }

    $stmt->close();

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    fail('เกิดข้อผิดพลาด ไม่สามารถบันทึกการสอบได้');
}

header("Location: detail.php?id=" . $exam_id);
exit;
