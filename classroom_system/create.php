<?php

session_start();

$page_title = "สร้างการสอบใหม่";
$css_path   = "../css/classroom.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_staffs
// โครงสร้างตารางแบบเต็มดูได้ที่ database/classroom_system.sql

// =====================================================
// ตรวจสอบสิทธิ์: เฉพาะบุคลากร (staff) และผู้ดูแลระบบ (admin) ที่มีข้อมูล
// บุคลากร (staff_id) แล้วเท่านั้น เพราะ exam.created_by ผูกกับ user_staffs.staff_id
// =====================================================

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)
) {
    header("Location: ../login/index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

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

$sql = "SELECT staff_id FROM user_staffs WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$staff) {
    die("บัญชีนี้ยังไม่มีข้อมูลบุคลากร (staff) กรุณาติดต่อผู้ดูแลระบบก่อนสร้างการสอบ");
}

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

// =====================================================
// รายชื่อห้องเรียนที่มีอยู่ (ให้เลือกเป็นห้องสอบ แทนการพิมพ์เอง)
// =====================================================

$classrooms = [];
$result = $conn->query("
    SELECT classroom_id, classroom_type, classroom_number_code, classroom_level, building
    FROM classroom
    ORDER BY classroom_level, classroom_number_code
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $classrooms[] = $row;
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
                    <h1>สร้างการสอบใหม่</h1>
                    <span>Exam Room System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="classroom-container">
        <div class="classroom-card">

            <div class="card-title">
                <div class="title-icon">📝</div>
                <div>
                    <h3>ข้อมูลการสอบ</h3>
                    <p>กรอกข้อมูลการสอบ + เพิ่มห้องสอบที่ใช้ได้เลยในหน้าเดียว</p>
                </div>
            </div>

            <form action="store.php" method="POST" id="exam-form">

                <div class="form-group">
                    <label>ชื่อการสอบ <span class="required">*</span></label>
                    <input type="text" name="exam_name" placeholder="เช่น สอบกลางภาค 1/2569" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>ประเภทการสอบ</label>
                        <select name="exam_type">
                            <option value="MIDTERM">สอบกลางภาค</option>
                            <option value="FINAL">สอบปลายภาค</option>
                            <option value="QUIZ">สอบย่อย</option>
                            <option value="OTHER">อื่น ๆ</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>วิชา <span class="required">*</span></label>
                        <input type="text" name="subject_name" placeholder="เช่น คณิตศาสตร์" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>วันที่สอบ <span class="required">*</span></label>
                        <input type="date" name="exam_date" required>
                    </div>

                    <div class="form-group">
                        <label>เวลาเริ่ม - เวลาสิ้นสุด <span class="required">*</span></label>
                        <div style="display:flex; gap:10px;">
                            <input type="time" name="start_time" required>
                            <input type="time" name="end_time" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>รายละเอียด</label>
                    <textarea name="detail" rows="3" placeholder="รายละเอียด/หมายเหตุ (ถ้ามี)"></textarea>
                </div>

                <div class="card-title" style="margin-top:10px;">
                    <div class="title-icon">🚪</div>
                    <div>
                        <h3>ห้องสอบ</h3>
                        <p>เพิ่มได้หลายห้อง — กด "+ เพิ่มห้องสอบ" เพื่อเพิ่มแถวใหม่</p>
                    </div>
                </div>

                <div id="room-rows"></div>

                <div class="form-actions" style="border-top:none; padding-top:0; justify-content:flex-start;">
                    <button type="button" class="cancel-btn" onclick="addRoomRow()">+ เพิ่มห้องสอบ</button>
                </div>

                <div class="form-actions">
                    <a href="main.php" class="cancel-btn">ยกเลิก</a>
                    <button type="submit" class="submit-btn">✅ สร้างการสอบ</button>
                </div>

            </form>

        </div>
    </main>
</div>

<template id="room-row-template">
    <div class="classroom-card" style="padding:18px; margin-bottom:14px;">
        <div class="form-grid">
            <div class="form-group">
                <label>ห้อง <span class="required">*</span></label>
                <select name="room_code[]" class="room-select" required onchange="onRoomSelectChange(this)">
                    <option value="">-- เลือกห้อง --</option>
                    <?php foreach ($classrooms as $c): ?>
                        <option value="<?= e($c['classroom_number_code']) ?>" data-building="<?= e($c['building'] ?? '') ?>">
                            <?= e(getClassroomLabel($c['classroom_type'], $c['classroom_number_code'], $c['classroom_level'])) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="__custom__">อื่น ๆ (พิมพ์เอง)</option>
                </select>
                <input
                    type="text"
                    name="room_code[]"
                    class="room-custom-input"
                    placeholder="เช่น หอประชุม"
                    style="display:none; margin-top:8px;"
                    disabled
                >
            </div>
            <div class="form-group">
                <label>ชื่อห้อง</label>
                <input type="text" name="room_name[]" placeholder="เช่น ห้อง 301">
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label>อาคาร</label>
                <input type="text" name="building[]" placeholder="เช่น อาคาร 49">
            </div>
            <div class="form-group">
                <label>ชั้น</label>
                <input type="number" name="floor[]">
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label>ความจุที่นั่ง <span class="required">*</span></label>
                <input type="number" name="capacity[]" min="1" required>
            </div>
            <div class="form-group">
                <label>กรรมการคุมสอบ</label>
                <select name="supervisor_staff_id[]">
                    <option value="">-- ไม่ระบุ --</option>
                    <?php foreach ($staff_list as $s): ?>
                        <option value="<?= e($s['staff_id']) ?>">
                            <?= e(trim(($s['title_name'] ?? "") . " " . $s['first_name_th'] . " " . $s['last_name_th'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-actions" style="border-top:none; padding-top:0; justify-content:flex-start;">
            <button type="button" class="reject-btn" onclick="this.closest('.classroom-card').remove()">✕ ลบห้องนี้</button>
        </div>
    </div>
</template>

<script>
function onRoomSelectChange(select) {
    var row = select.closest('.classroom-card');
    var customInput = row.querySelector('.room-custom-input');
    var buildingInput = row.querySelector('input[name="building[]"]');

    if (select.value === '__custom__') {
        customInput.disabled = false;
        customInput.style.display = 'block';
        customInput.focus();
    } else {
        customInput.disabled = true;
        customInput.style.display = 'none';
        customInput.value = '';

        var selectedOption = select.options[select.selectedIndex];
        var building = selectedOption ? selectedOption.getAttribute('data-building') : '';

        if (building) {
            buildingInput.value = building;
        }
    }
}

function addRoomRow() {
    var template = document.getElementById('room-row-template');
    var clone = template.content.cloneNode(true);
    document.getElementById('room-rows').appendChild(clone);
}

// เริ่มต้นด้วย 1 แถว
addRoomRow();

document.getElementById('exam-form').addEventListener('submit', function (e) {
    var rows = document.querySelectorAll('#room-rows .classroom-card');

    if (rows.length === 0) {
        e.preventDefault();
        alert('กรุณาเพิ่มห้องสอบอย่างน้อย 1 ห้อง');
        return;
    }

    // แต่ละแถวต้องส่งค่า room_code[] แค่อย่างเดียว (เลือกจาก dropdown หรือพิมพ์เอง)
    for (var i = 0; i < rows.length; i++) {
        var select = rows[i].querySelector('.room-select');
        var customInput = rows[i].querySelector('.room-custom-input');
        var isCustomMode = select.value === '__custom__';

        if (isCustomMode && customInput.value.trim() === '') {
            e.preventDefault();
            alert('กรุณากรอกชื่อห้องสอบ');
            customInput.focus();
            return;
        }

        select.disabled = isCustomMode;
    }
});
</script>

</body>
</html>
