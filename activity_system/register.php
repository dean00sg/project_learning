<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_students, activities, activity_signups
// โครงสร้างตารางแบบเต็มดูได้ที่ database/activity_system.sql

// =====================================================
// ตรวจสอบ Login (เฉพาะนักเรียน)
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

function fail($message, $activity_id)
{
    echo "<script>
        alert(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        window.location.href = 'detail.php?id=" . (int)$activity_id . "';
    </script>";
    exit;
}

$action      = $_POST['action'] ?? '';
$activity_id = (int)($_POST['activity_id'] ?? 0);

if ($activity_id <= 0) {
    fail('ไม่พบกิจกรรม', 0);
}

// ต้องเป็นนักเรียนเท่านั้น
$stmt = $conn->prepare("SELECT student_id FROM user_students WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    http_response_code(403);
    fail('เฉพาะนักเรียนเท่านั้นที่สมัครกิจกรรมได้', $activity_id);
}

if (!in_array($action, ['register', 'cancel'], true)) {
    fail('คำสั่งไม่ถูกต้อง', $activity_id);
}

// =====================================================
// สมัครเข้าร่วมกิจกรรม
//
// ล็อกแถวกิจกรรมด้วย transaction กันสองคนสมัครที่นั่งสุดท้ายพร้อมกัน
// =====================================================

if ($action === 'register') {

    $conn->begin_transaction();

    try {
        $sql = "SELECT activity_id, status, max_participants FROM activities WHERE activity_id = ? LIMIT 1 FOR UPDATE";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $activity_id);
        $stmt->execute();
        $activity = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$activity) {
            throw new Exception('ไม่พบกิจกรรม');
        }

        if ($activity['status'] !== 'open') {
            throw new Exception('กิจกรรมนี้ปิดรับสมัครแล้ว');
        }

        $sql = "SELECT registration_id, status FROM activity_signups WHERE activity_id = ? AND requester_id = ? LIMIT 1 FOR UPDATE";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $activity_id, $user_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing && in_array($existing['status'], ['registered', 'waitlisted'], true)) {
            throw new Exception('คุณสมัครกิจกรรมนี้ไปแล้ว');
        }

        $sql = "SELECT COUNT(*) AS total FROM activity_signups WHERE activity_id = ? AND status = 'registered'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $activity_id);
        $stmt->execute();
        $registered_count = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $new_status = $registered_count < (int)$activity['max_participants'] ? 'registered' : 'waitlisted';

        if ($existing) {
            // เคยสมัครแล้วยกเลิกไปก่อนหน้า -> สมัครใหม่โดยใช้แถวเดิม
            $sql = "UPDATE activity_signups SET status = ?, registered_at = NOW(), cancelled_at = NULL WHERE registration_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_status, $existing['registration_id']);
            $stmt->execute();
            $stmt->close();
        } else {
            $sql = "INSERT INTO activity_signups (activity_id, requester_id, registered_at, status) VALUES (?, ?, NOW(), ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $activity_id, $user_id, $new_status);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        fail($e->getMessage(), $activity_id);
    }

    echo "<script>window.location.href = 'detail.php?id=" . (int)$activity_id . "';</script>";
    exit;
}

// =====================================================
// ยกเลิกการสมัคร (เลื่อน waitlist ลำดับแรกขึ้นมาแทนที่ ถ้าเดิม 'registered')
// =====================================================

if ($action === 'cancel') {

    $conn->begin_transaction();

    try {
        // ล็อกแถวกิจกรรมด้วย เพื่อ serialize กับคนอื่นที่กำลังสมัคร/ยกเลิกพร้อมกัน
        $sql = "SELECT activity_id FROM activities WHERE activity_id = ? LIMIT 1 FOR UPDATE";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $activity_id);
        $stmt->execute();
        $stmt->close();

        $sql = "SELECT registration_id, status FROM activity_signups WHERE activity_id = ? AND requester_id = ? LIMIT 1 FOR UPDATE";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $activity_id, $user_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existing || !in_array($existing['status'], ['registered', 'waitlisted'], true)) {
            throw new Exception('ไม่พบการสมัครที่สามารถยกเลิกได้');
        }

        $was_registered = $existing['status'] === 'registered';

        $sql = "UPDATE activity_signups SET status = 'cancelled', cancelled_at = NOW() WHERE registration_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $existing['registration_id']);
        $stmt->execute();
        $stmt->close();

        if ($was_registered) {
            $sql = "
                SELECT registration_id FROM activity_signups
                WHERE activity_id = ? AND status = 'waitlisted'
                ORDER BY registered_at ASC
                LIMIT 1
                FOR UPDATE
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $activity_id);
            $stmt->execute();
            $next_in_line = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($next_in_line) {
                $sql = "UPDATE activity_signups SET status = 'registered' WHERE registration_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $next_in_line['registration_id']);
                $stmt->execute();
                $stmt->close();
            }
        }

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        fail($e->getMessage(), $activity_id);
    }

    echo "<script>window.location.href = 'detail.php?id=" . (int)$activity_id . "';</script>";
    exit;
}
