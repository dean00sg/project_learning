<?php

session_start();

$page_title = "ระบบจัดห้องสอบ";
$css_path   = "../css/classroom.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, user_staffs,
//                     classroom, exam, exam_rooms, exam_students
// โครงสร้างตารางแบบเต็มดูได้ที่ database/classroom_system.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
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

function getExamTypeName($type)
{
    $types = [
        "MIDTERM" => "สอบกลางภาค",
        "FINAL"   => "สอบปลายภาค",
        "QUIZ"    => "สอบย่อย",
        "OTHER"   => "อื่น ๆ",
    ];

    return $types[$type] ?? ($type ?: "-");
}

// =====================================================
// ดึงข้อมูลผู้ใช้งาน
// =====================================================

$sql = "
    SELECT
        ua.user_id, ua.role,
        us.student_id, us.classroom_id AS student_classroom_id,
        ust.staff_id
    FROM user_accounts ua
    LEFT JOIN user_students us ON us.user_id = ua.user_id
    LEFT JOIN user_staffs ust ON ust.user_id = ua.user_id
    WHERE ua.user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$user) {
    die("ไม่พบข้อมูลผู้ใช้งาน");
}

$is_student   = !empty($user['student_id']);
$is_admin     = $user['role'] === 'admin';
$is_staff     = !empty($user['staff_id']);
$can_organize = $is_staff || $is_admin;

// =====================================================
// แอดมิน: Dashboard ภาพรวมทั้งระบบ + การสอบทั้งหมด
// =====================================================

$admin_stats = null;
$all_exams   = [];

if ($is_admin) {
    $admin_stats = [
        "total_exams"   => (int)($conn->query("SELECT COUNT(*) AS n FROM exam")->fetch_assoc()['n'] ?? 0),
        "total_rooms"   => (int)($conn->query("SELECT COUNT(*) AS n FROM exam_rooms")->fetch_assoc()['n'] ?? 0),
        "total_present" => (int)($conn->query("SELECT COUNT(*) AS n FROM exam_students WHERE attendance_status = 'present'")->fetch_assoc()['n'] ?? 0),
        "total_absent"  => (int)($conn->query("SELECT COUNT(*) AS n FROM exam_students WHERE attendance_status = 'absent'")->fetch_assoc()['n'] ?? 0),
    ];

    $sql = "
        SELECT
            e.exam_id, e.exam_name, e.exam_type, e.subject_name, e.exam_date, e.status,
            (SELECT COUNT(*) FROM exam_rooms r WHERE r.exam_id = e.exam_id) AS room_count,
            (SELECT COUNT(*) FROM exam_rooms r INNER JOIN exam_students es ON es.exam_room_id = r.exam_room_id WHERE r.exam_id = e.exam_id) AS assigned_count
        FROM exam e
        ORDER BY e.exam_date DESC
    ";
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $all_exams[] = $row;
        }
    }
}

// =====================================================
// staff (ไม่ใช่แอดมิน): การสอบที่ตัวเองสร้าง
// =====================================================

$my_exams = [];

if ($is_staff && !$is_admin) {
    $sql = "
        SELECT
            e.exam_id, e.exam_name, e.exam_type, e.subject_name, e.exam_date, e.status,
            (SELECT COUNT(*) FROM exam_rooms r WHERE r.exam_id = e.exam_id) AS room_count,
            (SELECT COUNT(*) FROM exam_rooms r INNER JOIN exam_students es ON es.exam_room_id = r.exam_room_id WHERE r.exam_id = e.exam_id) AS assigned_count
        FROM exam e
        WHERE e.created_by = ?
        ORDER BY e.exam_date DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user['staff_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $my_exams[] = $row;
    }

    $stmt->close();
}

// =====================================================
// ครูที่ปรึกษา: Dashboard ห้องเรียนที่ดูแล
// (advisor_staff_id เก็บเป็น JSON array ของ user_accounts.user_id)
// =====================================================

$advised_classrooms = [];

if ($is_staff) {
    $sql = "
        SELECT classroom_id, classroom_type, classroom_number_code, classroom_level
        FROM classroom
        WHERE
            advisor_staff_id IS NOT NULL
            AND JSON_VALID(advisor_staff_id)
            AND JSON_CONTAINS(advisor_staff_id, JSON_ARRAY(?))
        ORDER BY classroom_number_code
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $classroom_id = (int)$row['classroom_id'];

        $total_students = (int)($conn->query("SELECT COUNT(*) AS n FROM user_students WHERE classroom_id = $classroom_id")->fetch_assoc()['n'] ?? 0);

        // ตารางสอบของห้อง (เฉพาะการสอบที่จัดที่นั่งให้ห้องนี้ไปแล้ว)
        $sql2 = "
            SELECT DISTINCT
                e.exam_id, e.subject_name, e.exam_date, e.start_time,
                (SELECT COUNT(*) FROM exam_students es2
                    INNER JOIN exam_rooms r2 ON r2.exam_room_id = es2.exam_room_id
                    INNER JOIN user_students us2 ON us2.student_id = es2.student_id
                    WHERE r2.exam_id = e.exam_id AND us2.classroom_id = ? AND es2.attendance_status = 'present') AS present_count,
                (SELECT COUNT(*) FROM exam_students es2
                    INNER JOIN exam_rooms r2 ON r2.exam_room_id = es2.exam_room_id
                    INNER JOIN user_students us2 ON us2.student_id = es2.student_id
                    WHERE r2.exam_id = e.exam_id AND us2.classroom_id = ? AND es2.attendance_status = 'absent') AS absent_count
            FROM exam_students es
            INNER JOIN exam_rooms r ON r.exam_room_id = es.exam_room_id
            INNER JOIN exam e ON e.exam_id = r.exam_id
            INNER JOIN user_students us ON us.student_id = es.student_id
            WHERE us.classroom_id = ?
            ORDER BY e.exam_date DESC
        ";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("iii", $classroom_id, $classroom_id, $classroom_id);
        $stmt2->execute();
        $schedule = [];
        $result2 = $stmt2->get_result();
        while ($row2 = $result2->fetch_assoc()) {
            $schedule[] = $row2;
        }
        $stmt2->close();

        $advised_classrooms[] = [
            "classroom_id"      => $classroom_id,
            "classroom_code"    => getClassroomLabel($row['classroom_type'], $row['classroom_number_code'], $row['classroom_level']),
            "total_students"    => $total_students,
            "schedule"          => $schedule,
        ];
    }

    $stmt->close();
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
                    <h1>ระบบจัดห้องสอบ</h1>
                    <span>Exam Room System</span>
                </div>
            </div>

            <a href="../index.php" class="back-home">🏠 หน้าหลัก</a>
        </div>
    </header>

    <main class="classroom-container">

        <div class="page-heading">
            <div>
                <h2>ห้องสอบ/ที่นั่งสอบ</h2>
                <p><?= $can_organize ? "จัดการการสอบและห้องเรียน" : "ตารางสอบและที่นั่งของคุณ" ?></p>
            </div>

            <div style="display:flex; gap:10px;">
                <?php if ($can_organize): ?>
                    <a href="classroom_main.php" class="history-btn">🏫 จัดการห้องเรียน</a>
                    <a href="create.php" class="submit-btn" style="text-decoration:none;">+ สร้างการสอบใหม่</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================================================= -->
        <!-- แอดมิน: Dashboard ภาพรวม -->
        <!-- ================================================= -->
        <?php if ($is_admin): ?>
            <div class="stat-grid" style="margin-bottom:25px;">
                <div class="stat-card">
                    <div class="stat-value"><?= $admin_stats['total_exams'] ?></div>
                    <div class="stat-label">การสอบทั้งหมด</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $admin_stats['total_rooms'] ?></div>
                    <div class="stat-label">ห้องสอบทั้งหมด</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $admin_stats['total_present'] ?></div>
                    <div class="stat-label">นักเรียนเข้าสอบ</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $admin_stats['total_absent'] ?></div>
                    <div class="stat-label">ขาดสอบ</div>
                </div>
            </div>

            <div class="recent-card" style="margin-bottom:25px;">
                <div class="recent-header">
                    <div>
                        <h3>🗂️ การสอบทั้งหมดในระบบ</h3>
                        <p><?= count($all_exams) ?> รายการ</p>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ชื่อการสอบ</th>
                                <th>วิชา</th>
                                <th>วันที่สอบ</th>
                                <th>ห้องสอบ</th>
                                <th>จัดแล้ว</th>
                                <th>สถานะ</th>
                                <th>ดู</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($all_exams)): ?>
                                <?php foreach ($all_exams as $exam): ?>
                                    <tr>
                                        <td><?= e($exam['exam_name']) ?></td>
                                        <td><?= e($exam['subject_name']) ?></td>
                                        <td><?= date("d/m/Y", strtotime($exam['exam_date'])) ?></td>
                                        <td><?= (int)$exam['room_count'] ?></td>
                                        <td><?= (int)$exam['assigned_count'] ?> คน</td>
                                        <td><span class="status <?= $exam['status'] === 'CANCELLED' ? 'cancelled' : 'assigned' ?>"><?= $exam['status'] === 'CANCELLED' ? 'ยกเลิกแล้ว' : 'ปกติ' ?></span></td>
                                        <td><a href="detail.php?id=<?= e($exam['exam_id']) ?>">รายละเอียด</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-data">ยังไม่มีการสอบในระบบ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- ================================================= -->
        <!-- STAFF (ไม่ใช่แอดมิน): การสอบที่สร้าง -->
        <!-- ================================================= -->
        <?php if ($is_staff && !$is_admin): ?>
            <div class="recent-card" style="margin-bottom:25px;">
                <div class="recent-header">
                    <div>
                        <h3>🗂️ การสอบที่คุณสร้าง</h3>
                        <p><?= count($my_exams) ?> รายการ</p>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ชื่อการสอบ</th>
                                <th>วิชา</th>
                                <th>วันที่สอบ</th>
                                <th>ห้องสอบ</th>
                                <th>จัดแล้ว</th>
                                <th>สถานะ</th>
                                <th>ดู</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($my_exams)): ?>
                                <?php foreach ($my_exams as $exam): ?>
                                    <tr>
                                        <td><?= e($exam['exam_name']) ?></td>
                                        <td><?= e($exam['subject_name']) ?></td>
                                        <td><?= date("d/m/Y", strtotime($exam['exam_date'])) ?></td>
                                        <td><?= (int)$exam['room_count'] ?></td>
                                        <td><?= (int)$exam['assigned_count'] ?> คน</td>
                                        <td><span class="status <?= $exam['status'] === 'CANCELLED' ? 'cancelled' : 'assigned' ?>"><?= $exam['status'] === 'CANCELLED' ? 'ยกเลิกแล้ว' : 'ปกติ' ?></span></td>
                                        <td><a href="detail.php?id=<?= e($exam['exam_id']) ?>">รายละเอียด</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-data">คุณยังไม่ได้สร้างการสอบ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- ================================================= -->
        <!-- ครูที่ปรึกษา: Dashboard ห้องเรียนที่ดูแล -->
        <!-- ================================================= -->
        <?php foreach ($advised_classrooms as $ac): ?>
            <?php $latest = $ac['schedule'][0] ?? null; ?>
            <div class="recent-card" style="margin-bottom:25px;">
                <div class="recent-header">
                    <div>
                        <h3>🎓 Dashboard ห้องเรียน — <?= e($ac['classroom_code']) ?></h3>
                        <p><?= $latest ? 'จากการสอบล่าสุด: ' . e($latest['subject_name']) : 'ยังไม่มีข้อมูลการสอบ' ?></p>
                    </div>
                    <a href="classroom_students.php?id=<?= e($ac['classroom_id']) ?>">ดูรายชื่อนักเรียน →</a>
                </div>

                <div class="stat-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom:20px;">
                    <div class="stat-card">
                        <div class="stat-value"><?= $ac['total_students'] ?></div>
                        <div class="stat-label">นักเรียนทั้งหมด</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $latest ? (int)$latest['present_count'] : '-' ?></div>
                        <div class="stat-label">เข้าสอบ (ล่าสุด)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $latest ? (int)$latest['absent_count'] : '-' ?></div>
                        <div class="stat-label">ขาดสอบ (ล่าสุด)</div>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>วิชา</th>
                                <th>วันที่สอบ</th>
                                <th>เข้าสอบ</th>
                                <th>ขาดสอบ</th>
                                <th>ดู</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($ac['schedule'])): ?>
                                <?php foreach ($ac['schedule'] as $row): ?>
                                    <tr>
                                        <td><?= e($row['subject_name']) ?></td>
                                        <td><?= date("d/m/Y", strtotime($row['exam_date'])) ?></td>
                                        <td><?= (int)$row['present_count'] ?></td>
                                        <td><?= (int)$row['absent_count'] ?></td>
                                        <td><a href="detail.php?id=<?= e($row['exam_id']) ?>">ดูรายละเอียด</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-data">ยังไม่มีการสอบที่จัดที่นั่งให้ห้องนี้</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- ================================================= -->
        <!-- STUDENT: ตารางสอบของตัวเอง -->
        <!-- ================================================= -->
        <?php if ($is_student): ?>
            <?php
            $sql = "
                SELECT
                    e.exam_id, e.exam_name, e.subject_name, e.exam_date, e.start_time,
                    r.room_code, r.room_name, es.seat_number, es.attendance_status
                FROM exam_students es
                INNER JOIN exam_rooms r ON r.exam_room_id = es.exam_room_id
                INNER JOIN exam e ON e.exam_id = r.exam_id
                INNER JOIN user_students us ON us.student_id = es.student_id
                WHERE us.user_id = ?
                ORDER BY e.exam_date ASC
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $my_schedule = [];
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $my_schedule[] = $row;
            }
            $stmt->close();
            ?>
            <div class="recent-card">
                <div class="recent-header">
                    <div>
                        <h3>📋 ตารางสอบของคุณ</h3>
                        <p><?= count($my_schedule) ?> รายการ</p>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>วิชา</th>
                                <th>วันที่สอบ</th>
                                <th>ห้องสอบ</th>
                                <th>เลขที่นั่ง</th>
                                <th>ดู</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($my_schedule)): ?>
                                <?php foreach ($my_schedule as $row): ?>
                                    <tr>
                                        <td><?= e($row['subject_name']) ?></td>
                                        <td><?= date("d/m/Y", strtotime($row['exam_date'])) ?></td>
                                        <td><?= e($row['room_code']) ?></td>
                                        <td><?= e($row['seat_number']) ?></td>
                                        <td><a href="detail.php?id=<?= e($row['exam_id']) ?>">ดูรายละเอียด</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-data">ยังไม่มีตารางสอบ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>
