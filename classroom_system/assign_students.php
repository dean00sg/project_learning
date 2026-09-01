<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: exam, exam_rooms, exam_students, user_students
// โครงสร้างตารางแบบเต็มดูได้ที่ database/classroom_system.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id       = (int)$_SESSION['user_id'];
$exam_id       = (int)($_POST['exam_id'] ?? 0);
$classroom_ids = $_POST['classroom_ids'] ?? [];

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

if (empty($classroom_ids)) {
    fail('กรุณาเลือกห้องเรียนอย่างน้อย 1 ห้อง', $exam_id);
}

$classroom_ids = array_values(array_unique(array_map('intval', $classroom_ids)));

// =====================================================
// ตรวจสิทธิ์: ผู้สร้างการสอบ (ผ่าน staff_id) หรือแอดมิน เท่านั้น
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
    fail('การสอบนี้ถูกยกเลิกแล้ว', $exam_id);
}

// =====================================================
// จัดนักเรียนเข้าห้องสอบ (แทนที่การจัดเดิมทั้งหมดของการสอบนี้)
//
// ล็อกห้องสอบทั้งหมดของการสอบนี้ด้วย transaction กันกดจัดซ้ำซ้อนพร้อมกัน
// กระจายนักเรียน (เรียงตามรหัสนักเรียน) ลงห้องสอบแบบ round-robin —
// วนใส่ห้องละ 1 คนทีละรอบ กันคนห้องเดียวกันนั่งติดกัน
// =====================================================

$conn->begin_transaction();

try {
    $sql = "SELECT exam_room_id, capacity FROM exam_rooms WHERE exam_id = ? ORDER BY exam_room_id FOR UPDATE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $rooms = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rooms[] = ["exam_room_id" => (int)$row['exam_room_id'], "capacity" => (int)$row['capacity'], "filled" => 0];
    }
    $stmt->close();

    if (empty($rooms)) {
        throw new Exception('ยังไม่มีห้องสอบ');
    }

    $placeholders = implode(',', array_fill(0, count($classroom_ids), '?'));
    $types        = str_repeat('i', count($classroom_ids));

    $sql = "SELECT student_id FROM user_students WHERE classroom_id IN ($placeholders) ORDER BY student_code ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$classroom_ids);
    $stmt->execute();
    $students = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $students[] = (int)$row['student_id'];
    }
    $stmt->close();

    if (empty($students)) {
        throw new Exception('ไม่มีนักเรียนในห้องเรียนที่เลือก');
    }

    $total_capacity = array_sum(array_column($rooms, 'capacity'));

    if ($total_capacity < count($students)) {
        throw new Exception('ความจุที่นั่งรวมไม่พอ (ต้องการอย่างน้อย ' . count($students) . ' ที่)');
    }

    // ล้างการจัดเดิมของการสอบนี้ก่อน (ทุกห้องสอบของ exam นี้)
    $room_ids = array_column($rooms, 'exam_room_id');
    $room_placeholders = implode(',', array_fill(0, count($room_ids), '?'));
    $room_types         = str_repeat('i', count($room_ids));

    $sql = "DELETE FROM exam_students WHERE exam_room_id IN ($room_placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($room_types, ...$room_ids);
    $stmt->execute();
    $stmt->close();

    // กระจายที่นั่งแบบ round-robin
    $insert_sql  = "INSERT INTO exam_students (exam_room_id, student_id, seat_number) VALUES (?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);

    $student_index = 0;
    $student_total = count($students);

    while ($student_index < $student_total) {
        $assigned_this_round = false;

        foreach ($rooms as &$room) {
            if ($student_index >= $student_total) {
                break;
            }

            if ($room['filled'] < $room['capacity']) {
                $room['filled']++;
                $seat_number  = (string)$room['filled'];
                $exam_room_id = $room['exam_room_id'];
                $student_id   = $students[$student_index];

                $insert_stmt->bind_param("iis", $exam_room_id, $student_id, $seat_number);
                $insert_stmt->execute();

                $student_index++;
                $assigned_this_round = true;
            }
        }
        unset($room);

        if (!$assigned_this_round) {
            throw new Exception('เกิดข้อผิดพลาดระหว่างจัดที่นั่ง');
        }
    }

    $insert_stmt->close();

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    fail($e->getMessage(), $exam_id);
}

header("Location: detail.php?id=" . $exam_id);
exit;
