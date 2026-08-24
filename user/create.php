<!DOCTYPE html>
<html lang="th">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>เพิ่มผู้ใช้งาน</title>

    <link rel="stylesheet"
          href="../css/user.css">

</head>

<body>

<div class="container">

    <!-- หัวข้อ -->

    <div class="page-header">

        <div>

            <h1>เพิ่มผู้ใช้งาน</h1>

            <p>กรอกข้อมูลผู้ใช้งานใหม่</p>

        </div>

    </div>


    <!-- Form -->

    <div class="card">

        <form action="store.php" method="POST">


            <!-- =========================
                 ข้อมูล Login
            ========================== -->

            <div class="form-section">

                <h2>ข้อมูล Login</h2>


                <div class="form-row">

                    <div class="form-group">

                        <label>
                            Username
                        </label>

                        <input
                            type="text"
                            name="username"
                            class="form-control"
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
                            class="form-control"
                            required
                        >

                    </div>

                </div>


                <div class="form-group">

                    <label>
                        ประเภทผู้ใช้งาน
                    </label>

                    <select
                        name="role"
                        class="form-control"
                        required
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

            </div>


            <!-- =========================
                 ข้อมูลส่วนตัว
            ========================== -->

            <div class="form-section">

                <h2>ข้อมูลส่วนตัว</h2>


                <div class="form-row">

                    <div class="form-group">

                        <label>
                            เลขบัตรประชาชน
                        </label>

                        <input
                            type="text"
                            name="citizen_id"
                            class="form-control"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            คำนำหน้า
                        </label>

                        <input
                            type="text"
                            name="title_name"
                            class="form-control"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            ชื่อ
                        </label>

                        <input
                            type="text"
                            name="first_name_th"
                            class="form-control"
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
                            class="form-control"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            วันเกิด
                        </label>

                        <input
                            type="date"
                            name="birthday"
                            class="form-control"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            เพศ
                        </label>

                        <select
                            name="sex"
                            class="form-control"
                        >

                            <option value="">
                                -- เลือกเพศ --
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
                            class="form-control"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            เบอร์โทรศัพท์
                        </label>

                        <input
                            type="text"
                            name="phone"
                            class="form-control"
                        >

                    </div>

                </div>

            </div>


            <!-- =========================
                 ข้อมูลนักเรียน
            ========================== -->

            <div class="form-section">

                <h2>ข้อมูลนักเรียน</h2>

                <div class="form-group">

                    <label>
                        Classroom ID
                    </label>

                    <input
                        type="text"
                        name="classroom_id"
                        class="form-control"
                    >

                </div>

            </div>


            <!-- =========================
                 ข้อมูลบุคลากร
            ========================== -->

            <div class="form-section">

                <h2>ข้อมูลบุคลากร</h2>


                <div class="form-row">

                    <div class="form-group">

                        <label>
                            Staff Type Code
                        </label>

                        <input
                            type="text"
                            name="staff_type_code"
                            class="form-control"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Department Code
                        </label>

                        <input
                            type="text"
                            name="department_code"
                            class="form-control"
                        >

                    </div>

                </div>

            </div>


            <!-- =========================
                 ปุ่ม
            ========================== -->

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

</body>

</html>