<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, user_staffs,
//                     classroom, equipment_item, borrow_requests
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

$item_id        = (int)($_POST['item_id'] ?? 0);
$borrow_type    = trim($_POST['borrow_type'] ?? "");
$classroom_id   = (int)($_POST['classroom_id'] ?? 0);
$request_detail = trim($_POST['request_detail'] ?? "");

// =====================================================
// ตรวจข้อมูล
// =====================================================

if ($item_id <= 0 || $borrow_type === "" || $request_detail === "") {
    fail('กรุณากรอกข้อมูลให้ครบถ้วน');
}

$allowed_types = ["classroom", "outside"];

if (!in_array($borrow_type, $allowed_types, true)) {
    fail('ลักษณะการใช้งานไม่ถูกต้อง');
}

if ($borrow_type === "classroom" && $classroom_id <= 0) {
    fail('กรุณาเลือกห้องเรียน');
}

// =====================================================
// ตรวจสิทธิ์ห้องเรียน (เฉพาะกรณีเลือก "ใช้ในห้องเรียน")
//
// นักเรียน: ต้องเป็นห้องของตัวเองเท่านั้น
// บุคลากร : ต้องเป็นห้องที่ตนเป็นครูที่ปรึกษาเท่านั้น
// =====================================================

if ($borrow_type === "classroom") {
    $sql = "
        SELECT ua.user_id, us.classroom_id AS student_classroom_id, ust.staff_id
        FROM user_accounts ua
        LEFT JOIN user_students us ON us.user_id = ua.user_id
        LEFT JOIN user_staffs ust ON ust.user_id = ua.user_id
        WHERE ua.user_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$user) {
        fail('ไม่พบข้อมูลผู้ใช้งาน');
    }

    if (!empty($user['student_classroom_id'])) {
        if ($classroom_id !== (int)$user['student_classroom_id']) {
            fail('ไม่สามารถเลือกห้องเรียนอื่นได้');
        }
    } elseif (!empty($user['staff_id'])) {
        $sql = "
            SELECT classroom_id
            FROM classroom
            WHERE
                classroom_id = ?
                AND advisor_staff_id IS NOT NULL
                AND JSON_VALID(advisor_staff_id)
                AND JSON_CONTAINS(advisor_staff_id, JSON_ARRAY(?))
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $classroom_id, $user_id);
        $stmt->execute();

        $advised_classroom = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$advised_classroom) {
            fail('คุณไม่ได้เป็นครูที่ปรึกษาของห้องเรียนนี้');
        }
    } else {
        fail('บัญชีนี้ไม่มีห้องเรียนที่ใช้งานได้');
    }
} else {
    $classroom_id = null;
}

// =====================================================
// ยืมทันที (ไม่ต้องรออนุมัติ)
//
// ล็อกแถวอุปกรณ์ด้วย transaction กันสองคนกดยืมชิ้นเดียวกันพร้อมกัน
// =====================================================

$conn->begin_transaction();

try {
    $sql = "SELECT item_id, status FROM equipment_item WHERE item_id = ? LIMIT 1 FOR UPDATE";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();

    $item = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$item) {
        throw new Exception('ไม่พบอุปกรณ์ที่เลือก');
    }

    if ($item['status'] !== 'available') {
        throw new Exception('อุปกรณ์นี้ไม่ว่างให้ยืมในขณะนี้');
    }

    $sql = "
        INSERT INTO borrow_requests (item_id, requester_id, borrow_type, classroom_id, request_detail, requester_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisis", $item_id, $user_id, $borrow_type, $classroom_id, $request_detail);
    $stmt->execute();

    $borrow_id = $stmt->insert_id;

    $stmt->close();

    $sql = "UPDATE equipment_item SET status = 'borrowed' WHERE item_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    fail($e->getMessage());
}

header("Location: detail.php?id=" . $borrow_id);
exit;
