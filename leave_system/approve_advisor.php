<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_staffs, classroom, leave_types, leave_requests
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
$action     = $_POST['action'] ?? '';
$reject_reason = trim($_POST['reject_reason'] ?? '');

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

if (!in_array($action, ['approve', 'reject'], true)) {
    fail('คำสั่งไม่ถูกต้อง', $request_id);
}

if ($action === 'reject' && $reject_reason === '') {
    fail('กรุณากรอกเหตุผลที่ไม่อนุมัติ', $request_id);
}

// =====================================================
// ดึงคำขอ + ตรวจสิทธิ์: ต้องเป็นครูที่ปรึกษาของห้องนักเรียนคนนั้น หรือแอดมิน
// =====================================================

$sql = "SELECT classroom_id, leave_type_id, status FROM leave_requests WHERE request_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
    fail('ไม่พบคำขอ', $request_id);
}

if ($request['status'] !== 'PENDING_ADVISOR') {
    fail('คำขอนี้ไม่ได้อยู่ในขั้นรอครูที่ปรึกษาอนุมัติแล้ว', $request_id);
}

$sql = "SELECT role FROM user_accounts WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$viewer = $stmt->get_result()->fetch_assoc();
$stmt->close();

$is_admin = $viewer && $viewer['role'] === 'admin';

$sql = "
    SELECT classroom_id FROM classroom
    WHERE
        classroom_id = ?
        AND advisor_staff_id IS NOT NULL
        AND JSON_VALID(advisor_staff_id)
        AND JSON_CONTAINS(advisor_staff_id, JSON_ARRAY(?))
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $request['classroom_id'], $user_id);
$stmt->execute();
$is_advisor_of_this = (bool)$stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$is_advisor_of_this && !$is_admin) {
    http_response_code(403);
    fail('คุณไม่ใช่ครูที่ปรึกษาของห้องนี้', $request_id);
}

// =====================================================
// ประเภทการลานี้ต้องผ่านฝ่ายปกครองต่อหรือไม่
// =====================================================

$sql = "SELECT requires_discipline_approval FROM leave_types WHERE leave_type_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request['leave_type_id']);
$stmt->execute();
$leave_type = $stmt->get_result()->fetch_assoc();
$stmt->close();

$requires_discipline = $leave_type && (int)$leave_type['requires_discipline_approval'] === 1;

// =====================================================
// บันทึกผล
// =====================================================

if ($action === 'approve') {
    $new_status = $requires_discipline ? 'PENDING_DISCIPLINE' : 'APPROVED';

    $sql = "
        UPDATE leave_requests
        SET advisor_approved_by = ?, advisor_approved_at = NOW(), status = ?
        WHERE request_id = ? AND status = 'PENDING_ADVISOR'
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $user_id, $new_status, $request_id);
} else {
    $sql = "
        UPDATE leave_requests
        SET advisor_approved_by = ?, advisor_approved_at = NOW(), status = 'REJECTED', reject_reason = ?
        WHERE request_id = ? AND status = 'PENDING_ADVISOR'
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $user_id, $reject_reason, $request_id);
}

$stmt->execute();

$ok = $stmt->affected_rows === 1;

$stmt->close();

if (!$ok) {
    fail('เกิดข้อผิดพลาด ไม่สามารถบันทึกผลได้', $request_id);
}

header("Location: detail.php?id=" . $request_id);
exit;
