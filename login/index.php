<!DOCTYPE html>
<html lang="th">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>เข้าสู่ระบบ</title>

    <link rel="stylesheet"
          href="../css/login.css">

</head>

<body>

<div class="login-container">

    <div class="login-card">

        <h1>เข้าสู่ระบบ</h1>

        <p class="login-description">
            ระบบจัดการโรงเรียน
        </p>


        <form action="login.php" method="POST">

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


            <button
                type="submit"
                class="login-button"
            >
                เข้าสู่ระบบ
            </button>

        </form>

    </div>

</div>

</body>

</html>