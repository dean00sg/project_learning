<?php

session_start();

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

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: classroom
// โครงสร้างตารางแบบเต็มดูได้ที่ database/repair_system.sql

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

$classrooms = [];
$result = $conn->query("
    SELECT classroom_id, classroom_type, classroom_number_code, classroom_level
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

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        เพิ่มผู้ใช้งาน
    </title>

      <link rel="stylesheet" href="../css/user.css">

</head>


<body>


<div class="container">


    <!-- =================================
         Header
    ================================== -->

    <div class="page-header">

        <div>

            <h1>
                เพิ่มผู้ใช้งาน
            </h1>

            <p>
                เพิ่มบัญชีผู้ใช้งานใหม่
            </p>

        </div>


        <a
            href="main.php"
            class="btn btn-secondary"
        >

            ← กลับ

        </a>

    </div>


    <!-- =================================
         Form
    ================================== -->

    <div class="card">


        <form
            action="store.php"
            method="POST"
        >


            <!-- ==========================
                 Login
            =========================== -->

            <h2>
                ข้อมูลการเข้าสู่ระบบ
            </h2>


            <div class="form-group">

                <label>
                    Username
                </label>

                <input
                    type="text"
                    name="username"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Password
                </label>

                <input
                    type="password"
                    name="password"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    ประเภทผู้ใช้งาน
                </label>

                <select
                    name="role"
                    id="role"
                    required
                    onchange="changeRole()"
                >

                    <option value="">
                        -- เลือกประเภท --
                    </option>

                    <option value="student">
                        นักเรียน
                    </option>

                    <option value="staff">
                        บุคลากร
                    </option>

                </select>

            </div>


            <!-- ==========================
                 ข้อมูลส่วนตัว
            =========================== -->

            <h2>
                ข้อมูลส่วนตัว
            </h2>


            <div class="form-group">

                <label>
                    เลขบัตรประชาชน
                </label>

                <input
                    type="text"
                    name="citizen_id"
                    maxlength="13"
                >

            </div>


            <div class="form-group">

                <label>
                    คำนำหน้า
                </label>

                <select
                    name="title_name"
                >

                    <option value="">
                        -- เลือกคำนำหน้า --
                    </option>

                    <option value="นาย">
                        นาย
                    </option>

                    <option value="นางสาว">
                        นางสาว
                    </option>

                    <option value="นาง">
                        นาง
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label>
                    ชื่อ
                </label>

                <input
                    type="text"
                    name="first_name_th"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    นามสกุล
                </label>

                <input
                    type="text"
                    name="last_name_th"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    ชื่อภาษาอังกฤษ
                </label>

                <input
                    type="text"
                    name="first_name_en"
                >

            </div>


            <div class="form-group">

                <label>
                    นามสกุลภาษาอังกฤษ
                </label>

                <input
                    type="text"
                    name="last_name_en"
                >

            </div>


            <div class="form-group">

                <label>
                    วันเกิด
                </label>

                <input
                    type="date"
                    name="birthday"
                >

            </div>


            <div class="form-group">

                <label>
                    เพศ
                </label>

                <select
                    name="sex"
                >

                    <option value="">
                        -- เลือก --
                    </option>

                    <option value="M">
                        ชาย
                    </option>

                    <option value="F">
                        หญิง
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label>
                    Email
                </label>

                <input
                    type="email"
                    name="email"
                >

            </div>


            <div class="form-group">

                <label>
                    เบอร์โทรศัพท์
                </label>

                <input
                    type="text"
                    name="phone"
                >

            </div>


            <!-- ==========================
                 นักเรียน
            =========================== -->

            <div
                id="student-section"
                style="display:none;"
            >

                <h2>
                    ข้อมูลนักเรียน
                </h2>


                <div class="form-group">

                    <label>
                        ชั้นเรียน
                    </label>

                    <select name="classroom_id">
                        <option value="">-- เลือกห้องเรียน --</option>
                        <?php foreach ($classrooms as $c): ?>
                            <option value="<?= e($c['classroom_id']) ?>">
                                <?= e(getClassroomLabel($c['classroom_type'], $c['classroom_number_code'], $c['classroom_level'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                </div>


                <div class="info-box">

                    <strong>
                        รหัสนักเรียนจะถูกสร้างอัตโนมัติ
                    </strong>

                    <br>

                    รูปแบบ:

                    <code>
                        ปี + คำนำหน้า + กลุ่มอักษร + 3 ตัวท้ายบัตรประชาชน
                    </code>

                    <br><br>

                    ตัวอย่าง:

                    <code>
                        690101123
                    </code>

                </div>

            </div>


            <!-- ==========================
                 บุคลากร
            =========================== -->

            <div
                id="staff-section"
                style="display:none;"
            >

                <h2>
                    ข้อมูลบุคลากร
                </h2>


                <div class="form-group">

                    <label>
                        Staff Type Code
                    </label>

                    <input
                        type="text"
                        name="staff_type_code"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Department Code
                    </label>

                    <input
                        type="text"
                        name="department_code"
                    >

                </div>

            </div>


            <!-- ==========================
                 Button
            =========================== -->

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    บันทึก

                </button>


                <a
                    href="main.php"
                    class="btn btn-secondary"
                >

                    ยกเลิก

                </a>

            </div>


        </form>


    </div>


</div>


<script>

function changeRole()
{

    const role =
        document.getElementById("role").value;


    const studentSection =
        document.getElementById("student-section");


    const staffSection =
        document.getElementById("staff-section");


    studentSection.style.display = "none";

    staffSection.style.display = "none";


    if (role === "student") {

        studentSection.style.display = "block";

    }


    if (role === "staff") {

        staffSection.style.display = "block";

    }

}

</script>


</body>

</html>