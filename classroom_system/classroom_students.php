<?php

session_start();

$page_title = "รายชื่อนักเรียนในห้อง";
$css_path   = "../css/classroom.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: classroom, user_students
// โครงสร้างตารางแบบเต็มดูได้ที่ database/repair_system.sql, database/users.sql

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

$sql = "SELECT classroom_id, classroom_type, classroom_number_code, classroom_level FROM classroom WHERE classroom_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $classroom_id);
$stmt->execute();
$classroom = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$classroom) {
    die("ไม่พบห้องเรียน");
}

$sql = "
    SELECT student_id, student_code, title_name, first_name_th, last_name_th, sex
    FROM user_students
    WHERE classroom_id = ?
    ORDER BY student_code
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $classroom_id);
$stmt->execute();

$students = [];
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
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
                <div class="brand-icon">🏫</div>
                <div>
                    <h1>รายชื่อนักเรียน</h1>
                    <span>Classroom System</span>
                </div>
            </div>

            <a href="classroom_main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="classroom-container">

        <div class="page-heading">
            <div>
                <h2>ห้อง <?= e(getClassroomLabel($classroom['classroom_type'], $classroom['classroom_number_code'], $classroom['classroom_level'])) ?></h2>
                <p><?= count($students) ?> คน</p>
            </div>
        </div>

        <div class="recent-card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>รหัสนักเรียน</th>
                            <th>ชื่อ-สกุล</th>
                            <th>เพศ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($students)): ?>
                            <?php foreach ($students as $s): ?>
                                <?php $name = trim(($s['title_name'] ?? "") . " " . $s['first_name_th'] . " " . $s['last_name_th']); ?>
                                <tr>
                                    <td><?= e($s['student_code']) ?></td>
                                    <td><?= e($name) ?></td>
                                    <td><?= e($s['sex'] === 'M' ? 'ชาย' : ($s['sex'] === 'F' ? 'หญิง' : '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="empty-data">ยังไม่มีนักเรียนในห้องนี้</td>
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
