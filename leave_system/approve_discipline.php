<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_staffs, leave_requests
// โครงสร้างตารางแบบเต็มดูได้ที่ database/leave_system.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id       = (int)$_SESSION['user_id'];
$request_id    = (int)($_POST['request_id'] ?? 0);
$action        = $_POST['action'] ?? '';
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
// ตรวจสิทธิ์: ครูฝ่ายปกครอง (staff_type_code = 'discipline') หรือแอดมิน
// =====================================================

$sql = "
    SELECT ua.role, ust.staff_type_code
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

$is_admin      = $viewer && $viewer['role'] === 'admin';
$is_discipline = $viewer && ($viewer['staff_type_code'] ?? '') === 'discipline';

if (!$is_discipline && !$is_admin) {
    http_response_code(403);
    fail('หน้านี้สำหรับครูฝ่ายปกครองเท่านั้น', $request_id);
}

// =====================================================
// ดึงคำขอ + ตรวจสถานะ
// =====================================================

$sql = "SELECT status FROM leave_requests WHERE request_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
    fail('ไม่พบคำขอ', $request_id);
}

if ($request['status'] !== 'PENDING_DISCIPLINE') {
    fail('คำขอนี้ไม่ได้อยู่ในขั้นรอฝ่ายปกครองอนุมัติแล้ว', $request_id);
}

// =====================================================
// บันทึกผล
// =====================================================

if ($action === 'approve') {
    $sql = "
        UPDATE leave_requests
        SET discipline_approved_by = ?, discipline_approved_at = NOW(), status = 'APPROVED'
        WHERE request_id = ? AND status = 'PENDING_DISCIPLINE'
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $request_id);
} else {
    $sql = "
        UPDATE leave_requests
        SET discipline_approved_by = ?, discipline_approved_at = NOW(), status = 'REJECTED', reject_reason = ?
        WHERE request_id = ? AND status = 'PENDING_DISCIPLINE'
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
