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

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, user_staffs
// โครงสร้างตารางแบบเต็มดูได้ที่ database/schema.sql


// ========================================
// รับ ID
// ========================================

$user_id = intval(
    $_GET["id"] ?? 0
);


if ($user_id <= 0) {

    die("ไม่พบ User ID");

}


// ========================================
// ดึงข้อมูล User
// ========================================

$sql = "
    SELECT
        user_id,
        username,
        role,
        is_active
    FROM user_accounts
    WHERE user_id = ?
    LIMIT 1
";


$stmt = $conn->prepare($sql);


$stmt->bind_param(
    "i",
    $user_id
);


$stmt->execute();


$result =
    $stmt->get_result();


if ($result->num_rows == 0) {

    die("ไม่พบข้อมูลผู้ใช้งาน");

}


$user =
    $result->fetch_assoc();


// ========================================
// กำหนดตัวแปร
// ========================================

$person = [];


// ========================================
// นักเรียน
// ========================================

if ($user["role"] == "student") {


    $sql = "
        SELECT *
        FROM user_students
        WHERE user_id = ?
        LIMIT 1
    ";


    $stmt = $conn->prepare($sql);


    $stmt->bind_param(
        "i",
        $user_id
    );


    $stmt->execute();


    $person_result =
        $stmt->get_result();


    if (
        $person_result->num_rows > 0
    ) {

        $person =
            $person_result->fetch_assoc();

    }

}


// ========================================
// บุคลากร
// ========================================

if ($user["role"] == "staff") {


    $sql = "
        SELECT *
        FROM user_staffs
        WHERE user_id = ?
        LIMIT 1
    ";


    $stmt = $conn->prepare($sql);


    $stmt->bind_param(
        "i",
        $user_id
    );


    $stmt->execute();


    $person_result =
        $stmt->get_result();


    if (
        $person_result->num_rows > 0
    ) {

        $person =
            $person_result->fetch_assoc();

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
        แก้ไขผู้ใช้งาน
    </title>

    <link
        rel="stylesheet"
        href="../css/user.css"
    >

</head>


<body>


<div class="container">


    <div class="page-header">

        <div>

            <h1>
                แก้ไขผู้ใช้งาน
            </h1>

        </div>


        <a
            href="main.php"
            class="btn btn-secondary"
        >

            ← กลับ

        </a>

    </div>


    <div class="card">


        <form
            action="update.php"
            method="POST"
        >


            <input
                type="hidden"
                name="user_id"
                value="<?= $user["user_id"] ?>"
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
                    value="<?= htmlspecialchars(
                        $user["username"]
                    ) ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Password ใหม่
                </label>

                <input
                    type="password"
                    name="password"
                >

                <small>
                    หากไม่ต้องการเปลี่ยน Password
                    ให้เว้นว่าง
                </small>

            </div>


            <div class="form-group">

                <label>
                    ประเภท
                </label>

                <input
                    type="text"
                    value="<?php

                    if ($user["role"] == "student") {

                        echo "นักเรียน";

                    }
                    elseif ($user["role"] == "staff") {

                        echo "บุคลากร";

                    }
                    else {

                        echo $user["role"];

                    }

                    ?>"
                    readonly
                >

            </div>


            <!-- ==========================
                 Personal
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
                    value="<?= htmlspecialchars(
                        $person["citezen_id"] ?? ""
                    ) ?>"
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
                        -- เลือก --
                    </option>

                    <option
                        value="นาย"
                        <?= (
                            ($person["title_name"] ?? "")
                            == "นาย"
                        ) ? "selected" : "" ?>
                    >
                        นาย
                    </option>

                    <option
                        value="นางสาว"
                        <?= (
                            ($person["title_name"] ?? "")
                            == "นางสาว"
                        ) ? "selected" : "" ?>
                    >
                        นางสาว
                    </option>

                    <option
                        value="นาง"
                        <?= (
                            ($person["title_name"] ?? "")
                            == "นาง"
                        ) ? "selected" : "" ?>
                    >
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
                    value="<?= htmlspecialchars(
                        $person["first_name_th"] ?? ""
                    ) ?>"
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
                    value="<?= htmlspecialchars(
                        $person["last_name_th"] ?? ""
                    ) ?>"
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
                    value="<?= htmlspecialchars(
                        $person["first_name_en"] ?? ""
                    ) ?>"
                >

            </div>


            <div class="form-group">

                <label>
                    นามสกุลภาษาอังกฤษ
                </label>

                <input
                    type="text"
                    name="last_name_en"
                    value="<?= htmlspecialchars(
                        $person["last_name_en"] ?? ""
                    ) ?>"
                >

            </div>


            <div class="form-group">

                <label>
                    วันเกิด
                </label>

                <input
                    type="date"
                    name="birthday"
                    value="<?= htmlspecialchars(
                        $person["birthday"] ?? ""
                    ) ?>"
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

                    <option
                        value="M"
                        <?= (
                            ($person["sex"] ?? "")
                            == "M"
                        ) ? "selected" : "" ?>
                    >
                        ชาย
                    </option>

                    <option
                        value="F"
                        <?= (
                            ($person["sex"] ?? "")
                            == "F"
                        ) ? "selected" : "" ?>
                    >
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
                    value="<?= htmlspecialchars(
                        $person["email"] ?? ""
                    ) ?>"
                >

            </div>


            <div class="form-group">

                <label>
                    เบอร์โทรศัพท์
                </label>

                <input
                    type="text"
                    name="phone"
                    value="<?= htmlspecialchars(
                        $person["phone"] ?? ""
                    ) ?>"
                >

            </div>


            <!-- ==========================
                 Student
            =========================== -->

            <?php if ($user["role"] == "student") { ?>

                <h2>
                    ข้อมูลนักเรียน
                </h2>


                <div class="form-group">

                    <label>
                        รหัสนักเรียน
                    </label>

                    <input
                        type="text"
                        value="<?= htmlspecialchars(
                            $person["student_code"] ?? ""
                        ) ?>"
                        readonly
                    >

                    <small>
                        รหัสนักเรียนไม่สามารถแก้ไขได้
                    </small>

                </div>


                <div class="form-group">

                    <label>
                        ชั้นเรียน
                    </label>

                    <input
                        type="text"
                        name="classroom_id"
                        value="<?= htmlspecialchars(
                            $person["classroom_id"] ?? ""
                        ) ?>"
                    >

                </div>

            <?php } ?>


            <!-- ==========================
                 Staff
            =========================== -->

            <?php if ($user["role"] == "staff") { ?>

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
                        value="<?= htmlspecialchars(
                            $person["staff_type_code"] ?? ""
                        ) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Department Code
                    </label>

                    <input
                        type="text"
                        name="department_code"
                        value="<?= htmlspecialchars(
                            $person["department_code"] ?? ""
                        ) ?>"
                    >

                </div>

            <?php } ?>


            <!-- ==========================
                 Status
            =========================== -->

            <h2>
                สถานะ
            </h2>


            <div class="form-group">

                <label>

                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        <?= $user["is_active"]
                            ? "checked"
                            : "" ?>
                    >

                    เปิดใช้งาน

                </label>

            </div>


            <!-- ==========================
                 Buttons
            =========================== -->

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    บันทึกการแก้ไข

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


</body>

</html>