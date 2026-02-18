<?php
session_start();
include "../koneksi.php";

if (isset($_SESSION['id_nip'])) {
    // Redirect berdasarkan role
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: ../admin/index.php");
            break;
        case 'kaprodi':
            header("Location: ../kaprodi/index.php");
            break;
        case 'kaleb':
            header("Location: ../kaleb/index.php");
            break;
        default: // pegawai
            header("Location: ../pegawai/index.php");
    }
    exit;
}

$alert = '';
if (isset($_GET['alert'])) {
    switch ($_GET['alert']) {
        case 'empty':
            $alert = '<div class="alert alert-warning">Email dan password harus diisi!</div>';
            break;
        case 'gagal':
            $alert = '<div class="alert alert-danger">Email atau password salah!</div>';
            break;
        case 'registered':
            $alert = '<div class="alert alert-success">Registrasi berhasil! Silakan login.</div>';
            break;
        case 'unauthorized':
            $alert = '<div class="alert alert-danger">Anda tidak memiliki akses ke halaman tersebut!</div>';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - iVESTA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&family=Raleway:wght@400;500;600&display=swap"
        rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-image: url('../assets/img/bg-login.jpg');
            background-size: cover;
            font-family: 'Raleway', sans-serif;
        }

        .login-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            width: 400px;
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

        .regist-link {
            text-align: center;
            margin-top: 15px;
            font-family: 'Raleway', sans-serif;
        }

        .regist-link a {
            color: #2c3e50;
            font-weight: 600;
            text-decoration: none;
        }

        .regist-link a:hover {
            color: #3498db;
            text-decoration: underline;
        }

        .role-info {
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
            color: #7f8c8d;
        }

        .site-footer {
            text-align: center;
            padding: 15px 10px;
            /* background-color: #2c3e50; */
            color: rgb(0, 0, 0);
            font-size: 14px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            /* box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.1); */
        }

        .site-footer a {
            color: #1abc9c;
            /* Warna aksen untuk nama pembuat */
            text-decoration: none;
            font-weight: bold;
        }

        .site-footer a:hover {
            text-decoration: underline;
            color: #16a085;
        }

        .site-footer i {
            margin-right: 5px;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="logo">
            <img src="../assets/img/Logo iVESTA.png" alt="PSTI Logo">
        </div>
        <h5 class="subtitle">Selamat Datang di iVESTA</h5>
        <div class="role-info">
            <p>LOGIN - Sistem Inventory Akademik</p>
        </div>

        <?php echo $alert; ?>

        <form action="check_login.php" method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
        </form>

        <div class="regist-link">
            Belum punya akun? <a href="regist.php">Daftar disini</a>
        </div>

        <div class="footer-text">
            <p>Copyright &copy; <?= date('Y'); ?> - Inventory System Academic</p>
        </div>

        <!-- <footer class="site-footer">
            Created By <a href="#" title="T.P"><i class="fa fa-copyright" aria-hidden="true"></i>Nune Fathih</a>
        </footer> -->

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>