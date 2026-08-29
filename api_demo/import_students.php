<?php

session_start();

$page_title = "นำเข้าข้อมูลนักเรียนจาก API";
$css_path   = "../css/user.css";

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

$result = $_SESSION['import_result'] ?? null;
unset($_SESSION['import_result']);
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="container">

    <div class="page-header">
        <div>
            <h1>นำเข้าข้อมูลนักเรียนจาก API</h1>
            <p>ตัวอย่างการดึงข้อมูลจาก API ภายนอกมาบันทึกลงฐานข้อมูล (user_accounts + user_students)</p>
        </div>

        <a href="../index.php" class="btn btn-secondary">← กลับหน้าหลัก</a>
    </div>

    <div class="card" style="margin-bottom:20px;">
        <h2 style="margin-top:0;">วิธีทำงาน</h2>
        <p>
            กด "ดึงข้อมูลจาก API" ระบบจะเรียก API ที่ path <code>/api/students</code>
            (จำลองเป็น API ภายนอก คืนค่าเป็น JSON — rewrite ไปหา <code>api_demo/mock_students_api.php</code>
            ตาม <code>.htaccess</code>) ผ่านไฟล์ <code>api_demo/fetch_students.php</code>
            ซึ่งจะ decode JSON แล้ว insert/update ลงตาราง <code>user_accounts</code> และ <code>user_students</code>
            ให้อัตโนมัติ — นักเรียนที่มีเลขบัตรประชาชนซ้ำกับที่มีอยู่แล้วจะถูกอัปเดตข้อมูลแทนการสร้างซ้ำ
        </p>
        <p style="color:#777;">
            รหัสผ่านเริ่มต้นของบัญชีที่สร้างใหม่คือ <code>123456</code> (username = เลขบัตรประชาชน)
        </p>

        <form action="fetch_students.php" method="POST">
            <button type="submit" class="btn btn-primary">⇩ ดึงข้อมูลจาก API</button>
        </form>
    </div>

    <?php if ($result): ?>
        <?php $summary = $result['summary']; ?>

        <div class="card" style="border-left:4px solid <?= $result['ok'] ? '#16a34a' : '#dc2626' ?>;">
            <h2 style="margin-top:0;"><?= $result['ok'] ? '✅' : '⚠️' ?> <?= e($result['message']) ?></h2>

            <?php if ($result['ok']): ?>
                <table class="table" style="max-width:420px;">
                    <tbody>
                        <tr><td>เพิ่มนักเรียนใหม่</td><td><strong><?= (int)$summary['inserted'] ?></strong></td></tr>
                        <tr><td>อัปเดตข้อมูลเดิม</td><td><strong><?= (int)$summary['updated'] ?></strong></td></tr>
                        <tr><td>ล้มเหลว</td><td><strong><?= (int)$summary['failed'] ?></strong></td></tr>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if (!empty($summary['errors'])): ?>
                <h3>รายการที่ผิดพลาด</h3>
                <ul>
                    <?php foreach ($summary['errors'] as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
