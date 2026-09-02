<?php

session_start();

$page_title = "จัดการประเภทการลา";
$css_path   = "../css/leave.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: leave_types
// โครงสร้างตารางแบบเต็มดูได้ที่ database/leave_system.sql

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

$leave_types = [];
$result = $conn->query("
    SELECT
        lt.leave_type_id, lt.leave_type_name, lt.detail,
        lt.requires_discipline_approval, lt.is_active,
        (SELECT COUNT(*) FROM leave_requests r WHERE r.leave_type_id = lt.leave_type_id) AS request_count
    FROM leave_types lt
    ORDER BY lt.leave_type_name
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $leave_types[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="leave-page">

    <header class="leave-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">⚙️</div>
                <div>
                    <h1>จัดการประเภทการลา</h1>
                    <span>Leave &amp; Permission Request System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="leave-container">

        <div class="page-heading">
            <div>
                <h2>ประเภทการลา/ขออนุญาต</h2>
                <p><?= count($leave_types) ?> ประเภท</p>
            </div>

            <a href="types_create.php" class="submit-btn" style="text-decoration:none;">+ เพิ่มประเภท</a>
        </div>

        <div class="recent-card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ชื่อประเภท</th>
                            <th>รายละเอียด</th>
                            <th>ต้องผ่านฝ่ายปกครอง</th>
                            <th>สถานะ</th>
                            <th>ใช้แล้ว</th>
                            <th>การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($leave_types)): ?>
                            <?php foreach ($leave_types as $t): ?>
                                <tr>
                                    <td><?= e($t['leave_type_name']) ?></td>
                                    <td><?= e(mb_strimwidth($t['detail'] ?? '-', 0, 50, '...')) ?></td>
                                    <td><?= $t['requires_discipline_approval'] ? 'ใช่' : 'ไม่ใช่' ?></td>
                                    <td><span class="status <?= $t['is_active'] ? 'active' : 'inactive' ?>"><?= $t['is_active'] ? 'เปิดใช้งาน' : 'ปิดใช้งาน' ?></span></td>
                                    <td><?= (int)$t['request_count'] ?> คำขอ</td>
                                    <td><a href="types_edit.php?id=<?= e($t['leave_type_id']) ?>">แก้ไข</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-data">ยังไม่มีประเภทการลา</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>
</body>
</html>
