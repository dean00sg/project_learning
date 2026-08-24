<?php
$page_title = "ระบบจัดการโรงเรียน";
$css_path = "css/style.css";
?>

<!DOCTYPE html>
<html lang="th">

<?php include "includes/head.php"; ?>

<body>

    <div class="container">

        <h1>ระบบจัดการโรงเรียน</h1>

        <div class="menu">

            <a href="repair_system/main.php" class="menu-card">
                <div class="menu-icon">🔧</div>
                <div class="menu-title">ระบบแจ้งซ่อม</div>
                <div class="menu-detail">
                    แจ้งซ่อมและติดตามสถานะการซ่อม
                </div>
            </a>

            <a href="borrow_system/main.php" class="menu-card">
                <div class="menu-icon">📦</div>
                <div class="menu-title">ระบบยืม-คืน</div>
                <div class="menu-detail">
                    ยืมและคืนอุปกรณ์
                </div>
            </a>

            <a href="classroom_system/main.php" class="menu-card">
                <div class="menu-icon">🏫</div>
                <div class="menu-title">ระบบชั้นเรียน</div>
                <div class="menu-detail">
                    จัดการข้อมูลห้องเรียน
                </div>
            </a>

        </div>

    </div>

</body>
</html>