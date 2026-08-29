<?php

session_start();


// ========================================
// ตรวจสอบ Login
// ========================================

if (!isset($_SESSION["user_id"])) {

    header("Location: login/index.php");

    exit;

}


// ========================================
// Database
// ========================================

require_once "config/db.php";


// ========================================
// ข้อมูลผู้ Login
// ========================================

$username = $_SESSION["username"] ?? "";

$role = $_SESSION["role"] ?? "";

$first_name_th = $_SESSION["first_name_th"] ?? "";

$last_name_th = $_SESSION["last_name_th"] ?? "";


// ========================================
// ชื่อ Role
// ========================================

if ($role == "admin") {

    $role_name = "ผู้ดูแลระบบ";

}
elseif ($role == "staff") {

    $role_name = "บุคลากร";

}
elseif ($role == "student") {

    $role_name = "นักเรียน";

}
else {

    $role_name = $role;

}


// ========================================
// ชื่อที่แสดง
// ========================================

$display_name = trim(
    $first_name_th . " " . $last_name_th
);


// ถ้าไม่มีชื่อ ให้ใช้ Username

if ($display_name == "") {

    $display_name = $username;

}


// ========================================
// Page
// ========================================

$page_title = "ระบบจัดการโรงเรียน";

$css_path = "css/style.css";

?>

<!DOCTYPE html>

<html lang="th">


<?php include "includes/head.php"; ?>


<body>


<div class="container">


    <!-- =================================
         Account
    ================================== -->

    <div class="account-box">


        <div class="account-icon">

            👤

        </div>


        <div class="account-info">


            <div class="account-username">

                <?= htmlspecialchars(
                    $display_name
                ) ?>

            </div>


            <div class="account-role">

                <?= htmlspecialchars(
                    $role_name
                ) ?>

            </div>


        </div>


        <a
            href="login/logout.php"
            class="logout-btn"
        >

            ออกจากระบบ

        </a>


    </div>


    <!-- =================================
         Header
    ================================== -->

    <div class="page-header">


        <h1>
            ระบบจัดการโรงเรียน
        </h1>


    </div>


    <!-- =================================
         Menu
    ================================== -->

    <div class="menu">


        <!-- ==============================
             จัดการผู้ใช้งาน
        =============================== -->

        <?php if ($role == "staff" || $role == "admin") { ?>

            <a
                href="user/main.php"
                class="menu-card"
            >

                <div class="menu-icon">
                    👤
                </div>


                <div class="menu-title">

                    จัดการผู้ใช้งาน

                </div>


                <div class="menu-detail">

                    เพิ่ม แก้ไข และจัดการผู้ใช้งาน

                </div>


            </a>

            <a
                href="api_demo/import_students.php"
                class="menu-card"
            >

                <div class="menu-icon">
                    ⇩
                </div>


                <div class="menu-title">

                    นำเข้าข้อมูลนักเรียนจาก API

                </div>


                <div class="menu-detail">

                    ดึงข้อมูลนักเรียนจาก API (ตัวอย่าง) เข้าฐานข้อมูล

                </div>


            </a>

        <?php } ?>


        <!-- ==============================
             ระบบแจ้งซ่อม
        =============================== -->

        <a
            href="repair_system/main.php"
            class="menu-card"
        >

            <div class="menu-icon">
                🔧
            </div>


            <div class="menu-title">

                ระบบแจ้งซ่อม

            </div>


            <div class="menu-detail">

                แจ้งซ่อมและติดตามสถานะการซ่อม

            </div>


        </a>


        <!-- ==============================
             ระบบยืมคืน
        =============================== -->

        <a
            href="borrow_system/main.php"
            class="menu-card"
        >

            <div class="menu-icon">
                📦
            </div>


            <div class="menu-title">

                ระบบยืม-คืน

            </div>


            <div class="menu-detail">

                ยืมและคืนอุปกรณ์

            </div>


        </a>


        <!-- ==============================
             ระบบจัดการกิจกรรมนักเรียน
        =============================== -->

        <a
            href="activity_system/main.php"
            class="menu-card"
        >

            <div class="menu-icon">
                🎉
            </div>


            <div class="menu-title">

                กิจกรรมนักเรียน

            </div>


            <div class="menu-detail">

                สมัครและจัดการกิจกรรมนักเรียน

            </div>


        </a>


        <!-- ==============================
             ระบบชั้นเรียน
        =============================== -->

        <a
            href="classroom_system/main.php"
            class="menu-card"
        >

            <div class="menu-icon">
                🏫
            </div>


            <div class="menu-title">

                ระบบชั้นเรียน

            </div>


            <div class="menu-detail">

                จัดการข้อมูลห้องเรียน

            </div>


        </a>


    </div>


</div>


</body>

</html>