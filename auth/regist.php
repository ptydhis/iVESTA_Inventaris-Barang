<?php
session_start();
include "../koneksi.php";

if (isset($_SESSION['login'])) {
    header("Location: ../index.php");
    exit();
}

$alert = '';
if (isset($_GET['alert'])) {
    switch ($_GET['alert']) {
        case 'email_exists':
            $alert = '<div class="alert alert-danger">Email sudah terdaftar!</div>';
            break;
        case 'nip_exists':
            $alert = '<div class="alert alert-danger">NIP sudah terdaftar!</div>';
            break;
        case 'error':
            $alert = '<div class="alert alert-danger">Terjadi kesalahan saat registrasi!</div>';
            break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi - iVESTA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&family=Raleway:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-image: url('../assets/images/bg-login.jpg');
            background-size: cover;
            font-family: 'Raleway', sans-serif;
        }

        .login-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            width: 450px;
            padding: 30px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo img {
            height: 80px;
        }

        .form-title {
            text-align: center;
            margin-bottom: 20px;
            color: #2c3e50;
            font-family: 'Nunito', sans-serif;
            font-weight: 700;
        }

        .subtitle {
            text-align: center;
            margin-bottom: 20px;
            color: #7f8c8d;
            font-family: 'Nunito', sans-serif;
            font-weight: 500;
        }

        .footer-text {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #7f8c8d;
            width: 100%;
        }

        .btn-primary {
            background-color: #2c3e50;
            border-color: #2c3e50;
            font-family: 'Raleway', sans-serif;
            font-weight: 600;
        }

        .btn-primary:hover {
            background-color: #1a252f;
            border-color: #1a252f;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
        }

        .login-link {
            text-align: center;
            margin-top: 15px;
            font-family: 'Raleway', sans-serif;
        }

        .login-link a {
            color: #2c3e50;
            font-weight: 600;
            text-decoration: none;
        }

        .login-link a:hover {
            color: #3498db;
            text-decoration: underline;
        }

        .role-info {
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
            color: #7f8c8d;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="logo">
            <img src="../assets/img/PRODI.jpg" alt="PRODI Logo">
        </div>
        <!-- <h3 class="form-title">REGISTRASI</h3> -->
        <h5 class="subtitle">Selamat Datang di iVESTA</h5>
        <div class="role-info">
            <p>DAFTAR - Sistem Inventory Akademik</p>
        </div>

        <?php echo $alert; ?>

        <form action="check_login.php" method="POST">
            <div class="mb-3">
                <label for="id_nip" class="form-label">NIP</label>
                <input type="text" class="form-control" id="id_nip" name="id_nip" required>
            </div>
            <div class="mb-3">
                <label for="fullName" class="form-label">Nama Lengkap</label>
                <input type="text" class="form-control" id="fullName" name="fullName" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <input type="hidden" name="role" value="pegawai">
            <button type="submit" name="register" class="btn btn-primary w-100">Daftar</button>
        </form>

        <div class="login-link">
            Sudah punya akun? <a href="login.php">Login disini</a>
        </div>

        <div class="footer-text">
            <p>Copyright &copy; <?= date('Y'); ?> - Inventory System Academic</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>