<?php

session_start();

$page_title = "เพิ่มคาบเรียน";
$css_path   = "../css/attendance.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: classroom, user_accounts, user_staffs
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
                    <h1>เพิ่มคาบเรียน</h1>
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
                    <h3>ข้อมูลคาบเรียน</h3>
                    <p>กรอกข้อมูลตารางสอนใหม่</p>
                </div>
            </div>

            <form action="schedule_store.php" method="POST">

                <div class="form-group">
                    <label>ห้องเรียน <span class="required">*</span></label>
                    <select name="classroom_id" required>
                        <option value="">-- เลือกห้องเรียน --</option>
                        <?php foreach ($classrooms as $c): ?>
                            <option value="<?= e($c['classroom_id']) ?>">
                                <?= e($c['classroom_type']) ?> <?= e($c['classroom_number_code']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>ครูผู้สอน <span class="required">*</span></label>
                    <select name="staff_id" required>
                        <option value="">-- เลือกครูผู้สอน --</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= e($t['user_id']) ?>">
                                <?= e(trim(($t['title_name'] ?? "") . " " . $t['first_name_th'] . " " . $t['last_name_th'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>รหัสวิชา</label>
                        <input type="text" name="subject_code" placeholder="เช่น ค21101">
                    </div>
                    <div class="form-group">
                        <label>ชื่อวิชา <span class="required">*</span></label>
                        <input type="text" name="subject_name" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>วัน <span class="required">*</span></label>
                    <select name="day_of_week" required>
                        <option value="">-- เลือกวัน --</option>
                        <?php foreach ($days as $value => $label): ?>
                            <option value="<?= e($value) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>เวลาเริ่ม <span class="required">*</span></label>
                        <input type="time" name="start_time" required>
                    </div>
                    <div class="form-group">
                        <label>เวลาสิ้นสุด <span class="required">*</span></label>
                        <input type="time" name="end_time" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>ห้องสอน</label>
                    <input type="text" name="room" placeholder="เช่น ห้องปฏิบัติการคอมพิวเตอร์ 1">
                </div>

                <div class="form-actions">
                    <a href="schedule_main.php" class="cancel-btn">ยกเลิก</a>
                    <button type="submit" class="submit-btn">✅ บันทึก</button>
                </div>

            </form>

        </div>
    </main>
</div>
</body>
</html>
