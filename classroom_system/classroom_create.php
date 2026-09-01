<?php

session_start();

$page_title = "เพิ่มห้องเรียน";
$css_path   = "../css/classroom.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_staffs
// โครงสร้างตารางแบบเต็มดูได้ที่ database/repair_system.sql (ตาราง classroom)

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

$teachers = [];
$result = $conn->query("
    SELECT user_id, title_name, first_name_th, last_name_th
    FROM user_staffs
    WHERE staff_type_code = 'teacher'
    ORDER BY first_name_th
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
<div class="classroom-page">

    <header class="classroom-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">🏫</div>
                <div>
                    <h1>เพิ่มห้องเรียน</h1>
                    <span>Classroom System</span>
                </div>
            </div>

            <a href="classroom_main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="classroom-container">
        <div class="classroom-card">

            <div class="card-title">
                <div class="title-icon">📝</div>
                <div>
                    <h3>ข้อมูลห้องเรียน</h3>
                    <p>กรอกข้อมูลห้องเรียนใหม่</p>
                </div>
            </div>

            <form action="classroom_store.php" method="POST">

                <div class="form-grid">
                    <div class="form-group">
                        <label>ประเภทห้องเรียน</label>
                        <input type="text" name="classroom_type" placeholder="เช่น มัธยม">
                    </div>

                    <div class="form-group">
                        <label>รหัสห้อง <span class="required">*</span></label>
                        <input type="text" name="classroom_number_code" placeholder="เช่น ม.1/1" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>ระดับชั้น</label>
                        <input type="number" name="classroom_level" placeholder="เช่น 1">
                    </div>

                    <div class="form-group">
                        <label>อาคาร</label>
                        <input type="text" name="building" placeholder="เช่น อาคาร 49">
                    </div>
                </div>

                <div class="form-group">
                    <label>ครูที่ปรึกษา (เลือกได้มากกว่า 1 คน)</label>
                    <div class="checkbox-grid">
                        <?php if (!empty($teachers)): ?>
                            <?php foreach ($teachers as $t): ?>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="advisor_ids[]" value="<?= e($t['user_id']) ?>">
                                    <?= e(trim(($t['title_name'] ?? "") . " " . $t['first_name_th'] . " " . $t['last_name_th'])) ?>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span style="color:#9999a5; font-size:13px;">ยังไม่มีข้อมูลครูในระบบ (staff_type_code = 'teacher')</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="classroom_main.php" class="cancel-btn">ยกเลิก</a>
                    <button type="submit" class="submit-btn">✅ บันทึก</button>
                </div>

            </form>

        </div>
    </main>
</div>
</body>
</html>
