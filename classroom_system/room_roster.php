<?php

session_start();

$page_title = "รายชื่อในห้องสอบ";
$css_path   = "../css/classroom.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_staffs, exam, exam_rooms,
//                     exam_students, user_students
// โครงสร้างตารางแบบเต็มดูได้ที่ database/classroom_system.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id      = (int)$_SESSION['user_id'];
$exam_room_id = (int)($_GET['exam_room_id'] ?? 0);

if ($exam_room_id <= 0) {
    die("ไม่พบห้องสอบ");
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// =====================================================
// ดึงห้องสอบ + การสอบ + ตรวจสิทธิ์ (ผู้สร้างการสอบ หรือแอดมิน เท่านั้น)
// =====================================================

$sql = "
    SELECT
        r.exam_room_id, r.room_code, r.room_name, r.capacity,
        e.exam_id, e.exam_name, e.subject_name, e.exam_date, e.created_by
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
    die("ไม่พบห้องสอบ");
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
    die("คุณไม่มีสิทธิ์ดูรายชื่อห้องสอบนี้");
}

// =====================================================
// รายชื่อนักเรียนในห้องนี้
// =====================================================

$sql = "
    SELECT
        es.exam_student_id, es.seat_number, es.attendance_status, es.checkin_at, es.remark,
        us.student_code, us.title_name, us.first_name_th, us.last_name_th
    FROM exam_students es
    INNER JOIN user_students us ON us.student_id = es.student_id
    WHERE es.exam_room_id = ?
    ORDER BY es.seat_number + 0 ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $exam_room_id);
$stmt->execute();

$roster = [];
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $roster[] = $row;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="classroom-page">

    <header class="classroom-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">✓</div>
                <div>
                    <h1>รายชื่อในห้องสอบ</h1>
                    <span>Exam Room System</span>
                </div>
            </div>

            <a href="detail.php?id=<?= e($room['exam_id']) ?>" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="classroom-container">
        <div class="classroom-card">

            <div class="card-title">
                <div class="title-icon">🚪</div>
                <div>
                    <h3>ห้อง <?= e($room['room_code']) ?><?= !empty($room['room_name']) ? ' (' . e($room['room_name']) . ')' : '' ?></h3>
                    <p><?= e($room['exam_name']) ?> — <?= e($room['subject_name']) ?> — <?= date("d/m/Y", strtotime($room['exam_date'])) ?> — <?= count($roster) ?>/<?= (int)$room['capacity'] ?> ที่นั่ง</p>
                </div>
            </div>

            <?php if (empty($roster)): ?>
                <div class="notice-box">
                    <div class="notice-icon">ℹ️</div>
                    <div><strong>ยังไม่มีนักเรียนในห้องสอบนี้</strong></div>
                </div>
            <?php else: ?>

                <form action="update_attendance.php" method="POST">
                    <input type="hidden" name="exam_room_id" value="<?= e($exam_room_id) ?>">

                    <?php foreach ($roster as $r): ?>
                        <?php $name = trim(($r['title_name'] ?? "") . " " . $r['first_name_th'] . " " . $r['last_name_th']); ?>
                        <div class="attendance-row" style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; padding:14px 4px; border-top:1px solid #eeeeef;">
                            <div class="attendance-name" style="min-width:220px;">
                                ที่นั่ง <?= e($r['seat_number']) ?> — <?= e($r['student_code']) ?> — <?= e($name) ?>
                            </div>
                            <div class="attendance-choice" style="display:flex; gap:14px; align-items:center; font-size:13px;">
                                <label>
                                    <input
                                        type="radio"
                                        name="attendance[<?= e($r['exam_student_id']) ?>][status]"
                                        value="present"
                                        <?= $r['attendance_status'] === 'present' ? 'checked' : '' ?>
                                    >
                                    เข้าสอบ
                                </label>
                                <label>
                                    <input
                                        type="radio"
                                        name="attendance[<?= e($r['exam_student_id']) ?>][status]"
                                        value="absent"
                                        <?= $r['attendance_status'] === 'absent' ? 'checked' : '' ?>
                                    >
                                    ขาดสอบ
                                </label>
                                <input
                                    type="text"
                                    name="attendance[<?= e($r['exam_student_id']) ?>][remark]"
                                    value="<?= e($r['remark'] ?? '') ?>"
                                    placeholder="หมายเหตุ"
                                    style="width:160px; padding:7px 9px; border:1px solid #dcdde5; border-radius:6px; font-family:inherit; font-size:12px;"
                                >
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="form-actions">
                        <a href="detail.php?id=<?= e($room['exam_id']) ?>" class="cancel-btn">ยกเลิก</a>
                        <button type="submit" class="submit-btn">💾 บันทึกการเข้าสอบ</button>
                    </div>
                </form>

            <?php endif; ?>

        </div>
    </main>
</div>
</body>
</html>
