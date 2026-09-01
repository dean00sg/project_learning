<?php

session_start();

$page_title = "รายละเอียดการสอบ";
$css_path   = "../css/classroom.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, user_staffs,
//                     exam, exam_rooms, exam_students, classroom
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
// ดึงข้อมูลการสอบ
// =====================================================

$sql = "
    SELECT
        e.exam_id, e.exam_name, e.exam_type, e.subject_name, e.exam_date,
        e.start_time, e.end_time, e.detail, e.status, e.created_by,

        ust.title_name AS creator_title,
        ust.first_name_th AS creator_first_name,
        ust.last_name_th AS creator_last_name

    FROM exam e
    LEFT JOIN user_staffs ust ON ust.staff_id = e.created_by
    WHERE e.exam_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $exam_id);
$stmt->execute();

$exam = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$exam) {
    die("ไม่พบการสอบ");
}

$creator_name = trim(($exam['creator_title'] ?? "") . " " . ($exam['creator_first_name'] ?? "") . " " . ($exam['creator_last_name'] ?? ""));

if ($creator_name === "") {
    $creator_name = "-";
}

// =====================================================
// ตรวจสอบผู้ใช้งาน / สิทธิ์
// =====================================================

$sql = "
    SELECT ua.role, us.student_id, ust.staff_id
    FROM user_accounts ua
    LEFT JOIN user_students us ON us.user_id = ua.user_id
    LEFT JOIN user_staffs ust ON ust.user_id = ua.user_id
    WHERE ua.user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$viewer = $stmt->get_result()->fetch_assoc();

$stmt->close();

$is_student = $viewer && !empty($viewer['student_id']);
$is_staff   = $viewer && !empty($viewer['staff_id']);
$is_admin   = $viewer && $viewer['role'] === 'admin';
$is_owner   = $viewer && !empty($viewer['staff_id']) && (int)$viewer['staff_id'] === (int)$exam['created_by'];
$can_manage = $is_owner || $is_admin;

if (!$can_manage && !$is_staff && !$is_student) {
    http_response_code(403);
    die("คุณไม่มีสิทธิ์ดูการสอบนี้");
}

// นักเรียน: ที่นั่งของตัวเอง (ถ้ามี)
$my_seat = null;

if ($is_student) {
    $sql = "
        SELECT es.seat_number, es.attendance_status, r.room_code, r.room_name
        FROM exam_students es
        INNER JOIN exam_rooms r ON r.exam_room_id = es.exam_room_id
        INNER JOIN user_students us ON us.student_id = es.student_id
        WHERE us.user_id = ? AND r.exam_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $exam_id);
    $stmt->execute();
    $my_seat = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// =====================================================
// ห้องสอบที่เพิ่มไว้ + สรุปการเข้าสอบ
// =====================================================

$sql = "
    SELECT
        r.exam_room_id, r.room_code, r.room_name, r.building, r.floor, r.capacity, r.supervisor_staff_id,

        ust.title_name AS supervisor_title,
        ust.first_name_th AS supervisor_first_name,
        ust.last_name_th AS supervisor_last_name,

        (SELECT COUNT(*) FROM exam_students es WHERE es.exam_room_id = r.exam_room_id) AS assigned_count,
        (SELECT COUNT(*) FROM exam_students es WHERE es.exam_room_id = r.exam_room_id AND es.attendance_status = 'present') AS present_count,
        (SELECT COUNT(*) FROM exam_students es WHERE es.exam_room_id = r.exam_room_id AND es.attendance_status = 'absent') AS absent_count

    FROM exam_rooms r
    LEFT JOIN user_staffs ust ON ust.staff_id = r.supervisor_staff_id
    WHERE r.exam_id = ?
    ORDER BY r.room_code
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $exam_id);
$stmt->execute();

$exam_rooms      = [];
$total_capacity   = 0;
$total_assigned    = 0;
$total_present      = 0;
$total_absent        = 0;

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $exam_rooms[]     = $row;
    $total_capacity  += (int)$row['capacity'];
    $total_assigned  += (int)$row['assigned_count'];
    $total_present   += (int)$row['present_count'];
    $total_absent    += (int)$row['absent_count'];
}

$stmt->close();

$has_assignments = $total_assigned > 0;

// =====================================================
// ผู้จัดการ: รายชื่อห้องเรียนทั้งหมด (ให้เลือกตอนจัดนักเรียนเข้าห้องสอบ)
// =====================================================

$classrooms = [];

if ($can_manage && $exam['status'] === 'OPEN') {
    $sql = "
        SELECT c.classroom_id, c.classroom_type, c.classroom_number_code, c.classroom_level,
            (SELECT COUNT(*) FROM user_students us WHERE us.classroom_id = c.classroom_id) AS student_count
        FROM classroom c
        ORDER BY c.classroom_level, c.classroom_number_code
    ";
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $classrooms[] = $row;
        }
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
                <div class="brand-icon">📄</div>
                <div>
                    <h1>รายละเอียดการสอบ</h1>
                    <span>Exam Room System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="classroom-container">

        <!-- ================================================= -->
        <!-- เช็คชื่อเข้าสอบ (ย้ายไว้บนสุดให้เด่น กดถึงเร็ว) -->
        <!-- ================================================= -->
        <?php if ($can_manage && $has_assignments): ?>
            <div class="classroom-card" style="margin-bottom:25px; background:#f6f3ff; border-color:#ddd4fb;">
                <div class="card-title" style="border-bottom:none; margin-bottom:14px; padding-bottom:0;">
                    <div class="title-icon">✓</div>
                    <div>
                        <h3>เช็คชื่อเข้าสอบ</h3>
                        <p>เลือกห้องที่จะเช็คชื่อ — เข้าสอบ <?= $total_present ?> / ขาดสอบ <?= $total_absent ?> จากทั้งหมด <?= $total_assigned ?> คน</p>
                    </div>
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:10px;">
                    <?php foreach ($exam_rooms as $r): ?>
                        <?php if ((int)$r['assigned_count'] > 0): ?>
                            <a
                                href="room_roster.php?exam_room_id=<?= e($r['exam_room_id']) ?>"
                                class="submit-btn"
                                style="text-decoration:none; display:inline-flex; align-items:center; gap:8px;"
                            >
                                🚪 ห้อง <?= e($r['room_code']) ?>
                                <span style="opacity:.85; font-size:12px;">
                                    (<?= (int)$r['present_count'] + (int)$r['absent_count'] ?>/<?= (int)$r['assigned_count'] ?> เช็คแล้ว)
                                </span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($is_student && $my_seat): ?>
            <div class="classroom-card" style="margin-bottom:25px; background:#f6f3ff; border-color:#ddd4fb;">
                <div class="card-title" style="border-bottom:none; margin-bottom:0; padding-bottom:0;">
                    <div class="title-icon">🎫</div>
                    <div>
                        <h3>ที่นั่งสอบของคุณ</h3>
                        <p>
                            ห้อง <?= e($my_seat['room_code']) ?><?= !empty($my_seat['room_name']) ? ' (' . e($my_seat['room_name']) . ')' : '' ?>
                            — เลขที่นั่ง <?= e($my_seat['seat_number']) ?>
                            <?php if (!empty($my_seat['attendance_status'])): ?>
                                — <span class="status <?= $my_seat['attendance_status'] === 'present' ? 'assigned' : 'cancelled' ?>">
                                    <?= $my_seat['attendance_status'] === 'present' ? 'เข้าสอบแล้ว' : 'ขาดสอบ' ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php elseif ($is_student && !$can_manage): ?>
            <div class="notice-box" style="margin-top:0; margin-bottom:25px;">
                <div class="notice-icon">ℹ️</div>
                <div><strong>ยังไม่มีการจัดที่นั่งสำหรับคุณในการสอบนี้</strong></div>
            </div>
        <?php endif; ?>

        <div class="classroom-card">

            <div class="card-title">
                <div class="title-icon">📄</div>
                <div>
                    <h3><?= e($exam['exam_name']) ?></h3>
                    <p><?= e(getExamTypeName($exam['exam_type'])) ?> — <?= e($exam['subject_name']) ?> — ผู้สร้าง: <?= e($creator_name) ?></p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>วันเวลาสอบ</label>
                    <input
                        type="text"
                        value="<?= date("d/m/Y", strtotime($exam['exam_date'])) ?> เวลา <?= substr($exam['start_time'], 0, 5) ?>-<?= substr($exam['end_time'], 0, 5) ?> น."
                        readonly
                    >
                </div>

                <div class="form-group">
                    <label>สถานะ</label>
                    <div><span class="status <?= $exam['status'] === 'OPEN' ? ($has_assignments ? 'assigned' : 'draft') : 'cancelled' ?>">
                        <?= $exam['status'] === 'CANCELLED' ? 'ยกเลิกแล้ว' : ($has_assignments ? 'จัดที่นั่งแล้ว' : 'ยังไม่จัดที่นั่ง') ?>
                    </span></div>
                </div>
            </div>

            <?php if (!empty($exam['detail'])): ?>
                <div class="form-group">
                    <label>รายละเอียด</label>
                    <textarea rows="3" readonly><?= e($exam['detail']) ?></textarea>
                </div>
            <?php endif; ?>

            <?php if ($can_manage && $exam['status'] === 'OPEN'): ?>
                <div class="form-actions" style="border-top:none; padding-top:0; justify-content:flex-start; margin-top:10px; gap:10px;">
                    <a href="edit.php?id=<?= e($exam_id) ?>" class="cancel-btn" style="text-decoration:none;">✎ แก้ไขข้อมูลการสอบ</a>
                    <form action="update_exam_status.php" method="POST" onsubmit="return confirm('ยืนยันยกเลิกการสอบนี้?');">
                        <input type="hidden" name="exam_id" value="<?= e($exam_id) ?>">
                        <button type="submit" class="reject-btn">✕ ยกเลิกการสอบ</button>
                    </form>
                </div>
            <?php endif; ?>

        </div>

        <!-- ================================================= -->
        <!-- สรุปห้องสอบ -->
        <!-- ================================================= -->
        <div class="recent-card" style="margin-top:25px;">
            <div class="recent-header">
                <div>
                    <h3>🚪 ห้องสอบ</h3>
                    <p>
                        <?= count($exam_rooms) ?> ห้อง — ความจุรวม <?= $total_capacity ?> ที่
                        <?php if ($has_assignments): ?>
                            — จัดแล้ว <?= $total_assigned ?> คน — เข้าสอบ <?= $total_present ?> — ขาดสอบ <?= $total_absent ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ห้อง</th>
                            <th>อาคาร/ชั้น</th>
                            <th>ความจุ</th>
                            <th>กรรมการคุมสอบ</th>
                            <th>จัดแล้ว</th>
                            <th>เข้าสอบ/ขาดสอบ</th>
                            <th>การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($exam_rooms)): ?>
                            <?php foreach ($exam_rooms as $r): ?>
                                <?php
                                $supervisor_name = "-";
                                if (!empty($r['supervisor_first_name'])) {
                                    $supervisor_name = trim(($r['supervisor_title'] ?? "") . " " . $r['supervisor_first_name'] . " " . $r['supervisor_last_name']);
                                }
                                ?>
                                <tr>
                                    <td><?= e($r['room_code']) ?><?= !empty($r['room_name']) ? ' (' . e($r['room_name']) . ')' : '' ?></td>
                                    <td><?= e($r['building'] ?? '-') ?><?= $r['floor'] !== null ? ' ชั้น ' . e($r['floor']) : '' ?></td>
                                    <td><?= (int)$r['capacity'] ?></td>
                                    <td><?= e($supervisor_name) ?></td>
                                    <td><?= (int)$r['assigned_count'] ?></td>
                                    <td><?= (int)$r['present_count'] ?> / <?= (int)$r['absent_count'] ?></td>
                                    <td>
                                        <?php if ((int)$r['assigned_count'] > 0): ?>
                                            <a href="room_roster.php?exam_room_id=<?= e($r['exam_room_id']) ?>">รายชื่อ</a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                        <?php if ($can_manage && $exam['status'] === 'OPEN'): ?>
                                            &nbsp;|&nbsp;
                                            <a href="edit_room.php?exam_room_id=<?= e($r['exam_room_id']) ?>">แก้ไข</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-data">ยังไม่มีห้องสอบ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ================================================= -->
        <!-- จัดนักเรียนเข้าห้องสอบ -->
        <!-- ================================================= -->
        <?php if ($can_manage && $exam['status'] === 'OPEN'): ?>
            <div class="classroom-card" style="margin-top:25px;">

                <div class="card-title">
                    <div class="title-icon">🎲</div>
                    <div>
                        <h3>จัดนักเรียนเข้าห้องสอบ</h3>
                        <p><?= $has_assignments ? 'จัดไปแล้ว — เลือกห้องเรียนแล้วกดอีกครั้งเพื่อจัดใหม่ทั้งหมด (แทนที่ของเดิม)' : 'เลือกห้องเรียนที่ต้องเข้าสอบ แล้วกดจัดที่นั่งอัตโนมัติ' ?></p>
                    </div>
                </div>

                <?php if (empty($classrooms)): ?>
                    <div class="notice-box">
                        <div class="notice-icon">ℹ️</div>
                        <div><strong>ยังไม่มีห้องเรียนในระบบ</strong></div>
                    </div>
                <?php elseif (empty($exam_rooms)): ?>
                    <div class="notice-box">
                        <div class="notice-icon">ℹ️</div>
                        <div><strong>ต้องมีห้องสอบก่อนถึงจะจัดนักเรียนได้</strong></div>
                    </div>
                <?php else: ?>
                    <form action="assign_students.php" method="POST">
                        <input type="hidden" name="exam_id" value="<?= e($exam_id) ?>">

                        <div class="form-group">
                            <label>ห้องเรียนที่เข้าสอบ <span class="required">*</span></label>
                            <div class="checkbox-grid">
                                <?php foreach ($classrooms as $classroom): ?>
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="classroom_ids[]" value="<?= e($classroom['classroom_id']) ?>">
                                        <?= e(getClassroomLabel($classroom['classroom_type'], $classroom['classroom_number_code'], $classroom['classroom_level'])) ?> (<?= (int)$classroom['student_count'] ?> คน)
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-actions" style="border-top:none; padding-top:0;">
                            <button type="submit" class="submit-btn">🎲 จัดที่นั่งอัตโนมัติ</button>
                        </div>
                    </form>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>
