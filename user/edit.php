<?php

require_once "../config/db.php";


// รับ user_id จาก URL

$user_id = $_GET["id"];


// ดึงข้อมูล Login

$sql = "
    SELECT *
    FROM user_accounts
    WHERE user_id = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();


// ถ้าไม่พบ User

if (!$user) {

    die("ไม่พบผู้ใช้งาน");

}


// ตัวแปรเริ่มต้น

$person = [];


// ถ้าเป็นนักเรียน

if ($user["role"] == "student") {

    $sql = "
        SELECT *
        FROM user_students
        WHERE user_id = ?
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "i",
        $user_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $person = $result->fetch_assoc();

}


// ถ้าเป็นบุคลากร

if ($user["role"] == "staff") {

    $sql = "
        SELECT *
        FROM user_staffs
        WHERE user_id = ?
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "i",
        $user_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $person = $result->fetch_assoc();

}

?>

<!DOCTYPE html>
<html lang="th">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>แก้ไขผู้ใช้งาน</title>

    <link rel="stylesheet"
          href="../css/user.css">

</head>

<body>

<div class="container">

    <div class="page-header">

        <h1>แก้ไขผู้ใช้งาน</h1>

    </div>


    <div class="card">

        <form action="update.php" method="POST">


            <!-- ส่ง user_id ไปด้วย -->

            <input
                type="hidden"
                name="user_id"
                value="<?= $user["user_id"] ?>"
            >


            <div class="form-section">

                <h2>ข้อมูล Login</h2>


                <div class="form-group">

                    <label>Username</label>

                    <input
                        type="text"
                        name="username"
                        class="form-control"
                        value="<?= htmlspecialchars($user["username"]) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>ประเภท</label>

                    <select
                        name="role"
                        class="form-control"
                        required
                    >

                        <option
                            value="student"
                            <?= $user["role"] == "student" ? "selected" : "" ?>
                        >
                            นักเรียน
                        </option>

                        <option
                            value="staff"
                            <?= $user["role"] == "staff" ? "selected" : "" ?>
                        >
                            บุคลากร
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>สถานะ</label>

                    <select
                        name="is_active"
                        class="form-control"
                    >

                        <option
                            value="1"
                            <?= $user["is_active"] ? "selected" : "" ?>
                        >
                            ใช้งาน
                        </option>

                        <option
                            value="0"
                            <?= !$user["is_active"] ? "selected" : "" ?>
                        >
                            ปิดใช้งาน
                        </option>

                    </select>

                </div>

            </div>


            <!-- ข้อมูลส่วนตัว -->

            <div class="form-section">

                <h2>ข้อมูลส่วนตัว</h2>


                <div class="form-row">


                    <div class="form-group">

                        <label>เลขบัตรประชาชน</label>

                        <input
                            type="text"
                            name="citizen_id"
                            class="form-control"
                            value="<?= htmlspecialchars($person["citezen_id"] ?? "") ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label>คำนำหน้า</label>

                        <input
                            type="text"
                            name="title_name"
                            class="form-control"
                            value="<?= htmlspecialchars($person["title_name"] ?? "") ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label>ชื่อ</label>

                        <input
                            type="text"
                            name="first_name_th"
                            class="form-control"
                            value="<?= htmlspecialchars($person["first_name_th"] ?? "") ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>นามสกุล</label>

                        <input
                            type="text"
                            name="last_name_th"
                            class="form-control"
                            value="<?= htmlspecialchars($person["last_name_th"] ?? "") ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>Email</label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            value="<?= htmlspecialchars($person["email"] ?? "") ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label>เบอร์โทรศัพท์</label>

                        <input
                            type="text"
                            name="phone"
                            class="form-control"
                            value="<?= htmlspecialchars($person["phone"] ?? "") ?>"
                        >

                    </div>

                </div>

            </div>


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