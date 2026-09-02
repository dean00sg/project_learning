<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_students, leave_requests
// โครงสร้างตารางแบบเต็มดูได้ที่ database/leave_system.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$request_id = (int)($_POST['request_id'] ?? 0);

function fail($message, $request_id)
{
    echo "<script>
        alert(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        window.location.href = 'detail.php?id=" . (int)$request_id . "';
    </script>";
    exit;
}

if ($request_id <= 0) {
    fail('ไม่พบคำขอ', 0);
}

$sql = "SELECT student_id FROM user_students WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    http_response_code(403);
    fail('เฉพาะนักเรียนเท่านั้นที่ยกเลิกคำขอได้', $request_id);
}

// =====================================================
// ตรวจสอบคำขอ (เจ้าของรายการเท่านั้น และต้องยังไม่อนุมัติ)
// =====================================================

$sql = "SELECT student_id, status FROM leave_requests WHERE request_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
    fail('ไม่พบคำขอ', $request_id);
}

if ((int)$request['student_id'] !== (int)$student['student_id']) {
    http_response_code(403);
    fail('คุณไม่มีสิทธิ์ยกเลิกคำขอนี้', $request_id);
}

if (!in_array($request['status'], ['PENDING_ADVISOR', 'PENDING_DISCIPLINE'], true)) {
    fail('คำขอนี้ผ่านการพิจารณาไปแล้ว ไม่สามารถยกเลิกได้', $request_id);
}

// =====================================================
// ยกเลิกคำขอ
// =====================================================

$sql = "UPDATE leave_requests SET status = 'CANCELLED' WHERE request_id = ? AND status IN ('PENDING_ADVISOR', 'PENDING_DISCIPLINE')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();

$ok = $stmt->affected_rows === 1;

$stmt->close();

if (!$ok) {
    fail('เกิดข้อผิดพลาด ไม่สามารถยกเลิกคำขอได้', $request_id);
}

header("Location: detail.php?id=" . $request_id);
exit;
