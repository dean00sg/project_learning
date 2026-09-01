<?php

session_start();

$page_title = "แก้ไขห้องสอบ";
$css_path   = "../css/classroom.css";

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
$exam_room_id = (int)($_GET['exam_room_id'] ?? 0);

if ($exam_room_id <= 0) {
    die("ไม่พบห้องสอบ");
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// =====================================================
// ดึงห้องสอบ + การสอบ + ตรวจสิทธิ์
// =====================================================

$sql = "
    SELECT r.*, e.exam_id, e.exam_name, e.created_by
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
    die("คุณไม่มีสิทธิ์แก้ไขห้องสอบนี้");
}

// =====================================================
// จำนวนที่จัดไปแล้วในห้องนี้ (ใช้เป็นค่าความจุขั้นต่ำที่ลดได้)
// =====================================================

$sql = "SELECT COUNT(*) AS n FROM exam_students WHERE exam_room_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $exam_room_id);
$stmt->execute();
$assigned_count = (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);
$stmt->close();

// =====================================================
// รายชื่อบุคลากรสำหรับเลือกเป็นกรรมการคุมสอบ
// =====================================================

$staff_list = [];
$result = $conn->query("
    SELECT staff_id, title_name, first_name_th, last_name_th
    FROM user_staffs
    ORDER BY first_name_th
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staff_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="classroom-page">

    <header class="classroom-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">🚪</div>
                <div>
                    <h1>แก้ไขห้องสอบ</h1>
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
                    <h3><?= e($room['room_code']) ?></h3>
                    <p><?= e($room['exam_name']) ?><?= $assigned_count > 0 ? ' — จัดที่นั่งไปแล้ว ' . $assigned_count . ' คน (ลดความจุต่ำกว่านี้ไม่ได้)' : '' ?></p>
                </div>
            </div>

            <form action="update_room.php" method="POST">
                <input type="hidden" name="exam_room_id" value="<?= e($exam_room_id) ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>รหัสห้อง <span class="required">*</span></label>
                        <input type="text" name="room_code" value="<?= e($room['room_code']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>ชื่อห้อง</label>
                        <input type="text" name="room_name" value="<?= e($room['room_name'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>อาคาร</label>
                        <input type="text" name="building" value="<?= e($room['building'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>ชั้น</label>
                        <input type="number" name="floor" value="<?= e($room['floor'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>ความจุที่นั่ง <span class="required">*</span></label>
                        <input type="number" name="capacity" min="<?= max(1, $assigned_count) ?>" value="<?= e($room['capacity']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>กรรมการคุมสอบ</label>
                        <select name="supervisor_staff_id">
                            <option value="">-- ไม่ระบุ --</option>
                            <?php foreach ($staff_list as $s): ?>
                                <option value="<?= e($s['staff_id']) ?>" <?= (int)$s['staff_id'] === (int)$room['supervisor_staff_id'] ? 'selected' : '' ?>>
                                    <?= e(trim(($s['title_name'] ?? "") . " " . $s['first_name_th'] . " " . $s['last_name_th'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="detail.php?id=<?= e($room['exam_id']) ?>" class="cancel-btn">ยกเลิก</a>
                    <button type="submit" class="submit-btn">💾 บันทึกการแก้ไข</button>
                </div>
            </form>

        </div>
    </main>
</div>
</body>
</html>
