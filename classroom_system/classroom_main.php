<?php

session_start();

$page_title = "จัดการห้องเรียน";
$css_path   = "../css/classroom.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: classroom, user_students, user_staffs, user_accounts
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

/**
 * แปลง advisor_staff_id (JSON array ของ user_accounts.user_id) เป็นชื่อครู
 */
function getAdvisorNames($conn, $advisor_staff_id)
{
    if (empty($advisor_staff_id) || !is_string($advisor_staff_id)) {
        return "-";
    }

    json_decode($advisor_staff_id);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return "-";
    }

    $ids = json_decode($advisor_staff_id, true);

    if (!is_array($ids) || empty($ids)) {
        return "-";
    }

    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "
        SELECT title_name, first_name_th, last_name_th
        FROM user_staffs
        WHERE user_id IN ($placeholders)
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();

    $names = [];
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $names[] = trim(($row['title_name'] ?? "") . " " . $row['first_name_th'] . " " . $row['last_name_th']);
    }

    $stmt->close();

    return !empty($names) ? implode(', ', $names) : "-";
}

// =====================================================
// รายการห้องเรียนทั้งหมด
// =====================================================

$sql = "
    SELECT
        c.classroom_id, c.classroom_type, c.classroom_number_code,
        c.classroom_level, c.advisor_staff_id, c.building,
        (SELECT COUNT(*) FROM user_students us WHERE us.classroom_id = c.classroom_id) AS student_count
    FROM classroom c
    ORDER BY c.classroom_level, c.classroom_number_code
";

$classrooms = [];
$result = $conn->query($sql);

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
                    <h1>จัดการห้องเรียน</h1>
                    <span>Classroom System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← ห้องสอบ</a>
        </div>
    </header>

    <main class="classroom-container">

        <div class="page-heading">
            <div>
                <h2>ห้องเรียนทั้งหมด</h2>
                <p><?= count($classrooms) ?> ห้อง</p>
            </div>

            <a href="classroom_create.php" class="submit-btn" style="text-decoration:none;">+ เพิ่มห้องเรียน</a>
        </div>

        <div class="recent-card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ห้อง</th>
                            <th>ประเภท</th>
                            <th>ระดับชั้น</th>
                            <th>อาคาร</th>
                            <th>ครูที่ปรึกษา</th>
                            <th>นักเรียน</th>
                            <th>การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($classrooms)): ?>
                            <?php foreach ($classrooms as $c): ?>
                                <tr>
                                    <td><?= e($c['classroom_number_code']) ?></td>
                                    <td><?= e($c['classroom_type'] ?? '-') ?></td>
                                    <td><?= e($c['classroom_level'] ?? '-') ?></td>
                                    <td><?= e($c['building'] ?? '-') ?></td>
                                    <td><?= e(getAdvisorNames($conn, $c['advisor_staff_id'])) ?></td>
                                    <td><?= (int)$c['student_count'] ?> คน</td>
                                    <td>
                                        <a href="classroom_edit.php?id=<?= e($c['classroom_id']) ?>">แก้ไข</a>
                                        &nbsp;|&nbsp;
                                        <a href="classroom_students.php?id=<?= e($c['classroom_id']) ?>">รายชื่อนักเรียน</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-data">ยังไม่มีห้องเรียนในระบบ</td>
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
