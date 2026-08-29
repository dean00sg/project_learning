<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: borrow_requests
// โครงสร้างตารางแบบเต็มดูได้ที่ database/schema.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
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

// =====================================================
// รับข้อมูล
// =====================================================

$borrow_id   = (int)($_POST['borrow_id'] ?? 0);
$return_note = trim($_POST['return_note'] ?? "");

if ($borrow_id <= 0) {
    fail('ไม่พบรายการแจ้งยืม');
}

// =====================================================
// ตรวจสอบรายการ (เจ้าของรายการเท่านั้น)
// =====================================================

$sql = "
    SELECT borrow_id, requester_id, return_requested_at, return_item_at
    FROM borrow_requests
    WHERE borrow_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $borrow_id);
$stmt->execute();

$request = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$request) {
    fail('ไม่พบรายการแจ้งยืม');
}

if ((int)$request['requester_id'] !== $user_id) {
    http_response_code(403);
    fail('คุณไม่มีสิทธิ์แจ้งคืนรายการนี้');
}

if (!empty($request['return_requested_at'])) {
    fail('รายการนี้แจ้งคืนไปแล้ว');
}

if (!empty($request['return_item_at'])) {
    fail('รายการนี้คืนอุปกรณ์เรียบร้อยแล้ว');
}

// =====================================================
// รูปถ่ายตอนคืน (บังคับ)
// =====================================================

if (!isset($_FILES['return_image']) || $_FILES['return_image']['error'] === UPLOAD_ERR_NO_FILE) {
    fail('กรุณาแนบรูปภาพอุปกรณ์ตอนคืน');
}

$file = $_FILES['return_image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    fail('ไม่สามารถอัปโหลดรูปภาพได้');
}

if ($file['size'] > 5 * 1024 * 1024) {
    fail('รูปภาพต้องมีขนาดไม่เกิน 5 MB');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);

$allowed_mime = ["image/jpeg" => "jpg", "image/png" => "png"];

if (!isset($allowed_mime[$mime])) {
    fail('รองรับเฉพาะ JPG และ PNG เท่านั้น');
}

$upload_dir = __DIR__ . "/uploads/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$extension = $allowed_mime[$mime];
$filename  = "return_" . date("YmdHis") . "_" . bin2hex(random_bytes(5)) . "." . $extension;
$target    = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    fail('ไม่สามารถบันทึกรูปภาพได้');
}

$return_image = "uploads/" . $filename;

// =====================================================
// บันทึกการแจ้งคืน
// =====================================================

$sql = "
    UPDATE borrow_requests
    SET return_requested_at = NOW(), return_image = ?, return_note = ?
    WHERE borrow_id = ? AND return_requested_at IS NULL
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $return_image, $return_note, $borrow_id);
$stmt->execute();

$ok = $stmt->affected_rows === 1;

$stmt->close();

if (!$ok) {
    fail('เกิดข้อผิดพลาด ไม่สามารถบันทึกการแจ้งคืนได้');
}

header("Location: detail.php?id=" . $borrow_id);
exit;
