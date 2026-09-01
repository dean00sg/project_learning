<?php

session_start();

$page_title = "แก้ไขข้อมูลการสอบ";
$css_path   = "../css/classroom.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_staffs, exam
// โครงสร้างตารางแบบเต็มดูได้ที่ database/classroom_system.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$exam_id = (int)($_GET['id'] ?? 0);

if ($exam_id <= 0) {
    die("ไม่พบการสอบ");
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// =====================================================
// ดึงข้อมูลการสอบ + ตรวจสิทธิ์ (ผู้สร้างการสอบ หรือแอดมิน เท่านั้น)
// =====================================================

$sql = "SELECT * FROM exam WHERE exam_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    die("ไม่พบการสอบ");
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
$is_owner = $viewer && !empty($viewer['staff_id']) && (int)$viewer['staff_id'] === (int)$exam['created_by'];

if (!$is_owner && !$is_admin) {
    http_response_code(403);
    die("คุณไม่มีสิทธิ์แก้ไขการสอบนี้");
}

if ($exam['status'] === 'CANCELLED') {
    die("การสอบนี้ถูกยกเลิกไปแล้ว ไม่สามารถแก้ไขได้");
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
                <div class="brand-icon">📝</div>
                <div>
                    <h1>แก้ไขข้อมูลการสอบ</h1>
                    <span>Exam Room System</span>
                </div>
            </div>

            <a href="detail.php?id=<?= e($exam_id) ?>" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="classroom-container">
        <div class="classroom-card">

            <div class="card-title">
                <div class="title-icon">📝</div>
                <div>
                    <h3><?= e($exam['exam_name']) ?></h3>
                    <p>แก้ไขข้อมูลการสอบ (ไม่รวมห้องสอบ — แก้ห้องสอบแยกได้ที่หน้ารายละเอียด)</p>
                </div>
            </div>

            <form action="update.php" method="POST">
                <input type="hidden" name="exam_id" value="<?= e($exam_id) ?>">

                <div class="form-group">
                    <label>ชื่อการสอบ <span class="required">*</span></label>
                    <input type="text" name="exam_name" value="<?= e($exam['exam_name']) ?>" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>ประเภทการสอบ</label>
                        <select name="exam_type">
                            <?php
                            $types = ["MIDTERM" => "สอบกลางภาค", "FINAL" => "สอบปลายภาค", "QUIZ" => "สอบย่อย", "OTHER" => "อื่น ๆ"];
                            foreach ($types as $value => $label):
                            ?>
                                <option value="<?= e($value) ?>" <?= $exam['exam_type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>วิชา <span class="required">*</span></label>
                        <input type="text" name="subject_name" value="<?= e($exam['subject_name']) ?>" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>วันที่สอบ <span class="required">*</span></label>
                        <input type="date" name="exam_date" value="<?= e($exam['exam_date']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>เวลาเริ่ม - เวลาสิ้นสุด <span class="required">*</span></label>
                        <div style="display:flex; gap:10px;">
                            <input type="time" name="start_time" value="<?= e(substr($exam['start_time'], 0, 5)) ?>" required>
                            <input type="time" name="end_time" value="<?= e(substr($exam['end_time'], 0, 5)) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>รายละเอียด</label>
                    <textarea name="detail" rows="3"><?= e($exam['detail'] ?? '') ?></textarea>
                </div>

                <div class="form-actions">
                    <a href="detail.php?id=<?= e($exam_id) ?>" class="cancel-btn">ยกเลิก</a>
                    <button type="submit" class="submit-btn">💾 บันทึกการแก้ไข</button>
                </div>
            </form>

        </div>
    </main>
</div>
</body>
</html>
