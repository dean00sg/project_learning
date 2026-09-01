<?php

session_start();

$page_title = "แก้ไขห้องเรียน";
$css_path   = "../css/classroom.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: classroom, user_staffs
// โครงสร้างตารางแบบเต็มดูได้ที่ database/repair_system.sql

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

$classroom_id = (int)($_GET['id'] ?? 0);

if ($classroom_id <= 0) {
    die("ไม่พบห้องเรียน");
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getClassroomLabel($type, $code, $level)
{
    $parts = [];

    if (!empty($type)) {
        $parts[] = $type;
    }

    $parts[] = $code;

    if ($level !== null && $level !== '') {
        $parts[] = "/ " . $level;
    }

    return implode(' ', $parts);
}

$sql = "SELECT * FROM classroom WHERE classroom_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $classroom_id);
$stmt->execute();
$classroom = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$classroom) {
    die("ไม่พบห้องเรียน");
}

$current_advisor_ids = [];

if (!empty($classroom['advisor_staff_id'])) {
    $decoded = json_decode($classroom['advisor_staff_id'], true);
    if (is_array($decoded)) {
        $current_advisor_ids = array_map('intval', $decoded);
    }
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
                    <h1>แก้ไขห้องเรียน</h1>
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
                    <h3><?= e(getClassroomLabel($classroom['classroom_type'], $classroom['classroom_number_code'], $classroom['classroom_level'])) ?></h3>
                    <p>แก้ไขข้อมูลห้องเรียน</p>
                </div>
            </div>

            <form action="classroom_update.php" method="POST">
                <input type="hidden" name="classroom_id" value="<?= e($classroom_id) ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>ประเภทห้องเรียน</label>
                        <input type="text" name="classroom_type" value="<?= e($classroom['classroom_type']) ?>">
                    </div>

                    <div class="form-group">
                        <label>รหัสห้อง <span class="required">*</span></label>
                        <input type="text" name="classroom_number_code" value="<?= e($classroom['classroom_number_code']) ?>" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>ระดับชั้น</label>
                        <input type="number" name="classroom_level" value="<?= e($classroom['classroom_level']) ?>">
                    </div>

                    <div class="form-group">
                        <label>อาคาร</label>
                        <input type="text" name="building" value="<?= e($classroom['building']) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>ครูที่ปรึกษา (เลือกได้มากกว่า 1 คน)</label>
                    <div class="checkbox-grid">
                        <?php if (!empty($teachers)): ?>
                            <?php foreach ($teachers as $t): ?>
                                <label class="checkbox-item">
                                    <input
                                        type="checkbox"
                                        name="advisor_ids[]"
                                        value="<?= e($t['user_id']) ?>"
                                        <?= in_array((int)$t['user_id'], $current_advisor_ids, true) ? 'checked' : '' ?>
                                    >
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
                    <button type="submit" class="submit-btn">💾 บันทึกการแก้ไข</button>
                </div>

            </form>

        </div>
    </main>
</div>
</body>
</html>
