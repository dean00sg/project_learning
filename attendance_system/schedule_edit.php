<?php

session_start();

$page_title = "แก้ไขคาบเรียน";
$css_path   = "../css/attendance.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: class_schedule, classroom, user_staffs
// โครงสร้างตารางแบบเต็มดูได้ที่ database/attendance_system.sql

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

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$days = [1 => "จันทร์", 2 => "อังคาร", 3 => "พุธ", 4 => "พฤหัสบดี", 5 => "ศุกร์", 6 => "เสาร์", 7 => "อาทิตย์"];

// =====================================================
// รับ ID
// =====================================================

$schedule_id = (int)($_GET['id'] ?? 0);

if ($schedule_id <= 0) {
    die("ไม่พบคาบเรียน");
}

$sql = "SELECT * FROM class_schedule WHERE schedule_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$schedule = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$schedule) {
    die("ไม่พบคาบเรียน");
}

$classrooms = [];
$result = $conn->query("
    SELECT classroom_id, classroom_type, classroom_number_code, classroom_level
    FROM classroom
    ORDER BY classroom_level, classroom_number_code
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $classrooms[] = $row;
    }
}

$teachers = [];
$result = $conn->query("
    SELECT ust.user_id, ust.title_name, ust.first_name_th, ust.last_name_th
    FROM user_staffs ust
    ORDER BY ust.first_name_th
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="attendance-page">

    <header class="attendance-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">🗓️</div>
                <div>
                    <h1>แก้ไขคาบเรียน</h1>
                    <span>Class Attendance System</span>
                </div>
            </div>

            <a href="schedule_main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="attendance-container">
        <div class="attendance-card">

            <div class="card-title">
                <div class="title-icon">📝</div>
                <div>
                    <h3><?= e($schedule['subject_name']) ?></h3>
                    <p>แก้ไขข้อมูลคาบเรียน</p>
                </div>
            </div>

            <form action="schedule_update.php" method="POST">
                <input type="hidden" name="schedule_id" value="<?= e($schedule_id) ?>">

                <div class="form-group">
                    <label>ห้องเรียน <span class="required">*</span></label>
                    <select name="classroom_id" required>
                        <?php foreach ($classrooms as $c): ?>
                            <option value="<?= e($c['classroom_id']) ?>" <?= (int)$c['classroom_id'] === (int)$schedule['classroom_id'] ? 'selected' : '' ?>>
                                <?= e($c['classroom_type']) ?> <?= e($c['classroom_number_code']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>ครูผู้สอน <span class="required">*</span></label>
                    <select name="staff_id" required>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= e($t['user_id']) ?>" <?= (int)$t['user_id'] === (int)$schedule['staff_id'] ? 'selected' : '' ?>>
                                <?= e(trim(($t['title_name'] ?? "") . " " . $t['first_name_th'] . " " . $t['last_name_th'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>รหัสวิชา</label>
                        <input type="text" name="subject_code" value="<?= e($schedule['subject_code'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>ชื่อวิชา <span class="required">*</span></label>
                        <input type="text" name="subject_name" value="<?= e($schedule['subject_name']) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>วัน <span class="required">*</span></label>
                    <select name="day_of_week" required>
                        <?php foreach ($days as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (int)$schedule['day_of_week'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>เวลาเริ่ม <span class="required">*</span></label>
                        <input type="time" name="start_time" value="<?= e(substr($schedule['start_time'], 0, 5)) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>เวลาสิ้นสุด <span class="required">*</span></label>
                        <input type="time" name="end_time" value="<?= e(substr($schedule['end_time'], 0, 5)) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>ห้องสอน</label>
                    <input type="text" name="room" value="<?= e($schedule['room'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" style="width:auto;" <?= $schedule['is_active'] ? 'checked' : '' ?>>
                        เปิดใช้งาน
                    </label>
                </div>

                <div class="form-actions">
                    <a href="schedule_main.php" class="cancel-btn">ยกเลิก</a>
                    <button type="submit" class="submit-btn">💾 บันทึกการแก้ไข</button>
                </div>

            </form>

        </div>
    </main>
</div>
</body>
</html>
