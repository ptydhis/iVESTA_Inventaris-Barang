<?php
session_start();
require "../koneksi.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['login'])) {
        // Validasi field kosong
        if (empty($_POST['email']) || empty($_POST['password'])) {
            header("Location: login.php?alert=empty");
            exit();
        }

        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $password = $_POST['password'];

        // Gunakan prepared statement untuk keamanan
        $stmt = $conn->prepare("SELECT * FROM t_user WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        if ($data) {
            // Migrasi password legacy
            if (strlen($data['password']) < 60) {
                $hashed_password = password_hash($data['password'], PASSWORD_BCRYPT);
                $update_stmt = $conn->prepare("UPDATE t_user SET password = ? WHERE id_nip = ?");
                $update_stmt->bind_param("ss", $hashed_password, $data['id_nip']);
                $update_stmt->execute();
                $data['password'] = $hashed_password;
            }

            // Verifikasi password
            if (password_verify($password, $data['password'])) {
                // Set session
                $_SESSION['id_nip'] = $data['id_nip'];
                $_SESSION['email'] = $data['email'];
                $_SESSION['fullName'] = $data['fullName'];
                $_SESSION['noHP'] = $data['noHP'];
                $_SESSION['fotoP'] = $data['fotoP'];
                $_SESSION['role'] = $data['role'];
                $_SESSION['login'] = true;

                // Redirect berdasarkan role - PATH DIPERBAIKI
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
                exit();
            }
        }

        // Jika email/password salah
        header("Location: login.php?alert=gagal");
        exit();

    } elseif (isset($_POST['register'])) {
        // Validasi registrasi
        $id_nip = mysqli_real_escape_string($conn, $_POST['id_nip']);
        $fullName = mysqli_real_escape_string($conn, $_POST['fullName']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $password = $_POST['password'];
        $role = isset($_POST['role']) ? $_POST['role'] : 'pegawai';

        // Hanya admin yang bisa mendaftarkan admin/kaprodi/kaleb baru
        if (
            in_array($role, ['admin', 'kaprodi', 'kaleb']) &&
            (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
        ) {
            header("Location: regist.php?alert=admin_only");
            exit();
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Cek email/NIP sudah ada (dengan prepared statement)
        $check_stmt = $conn->prepare("SELECT * FROM t_user WHERE email = ? OR id_nip = ?");
        $check_stmt->bind_param("ss", $email, $id_nip);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $existing = $check_result->fetch_assoc();
            $alert = ($existing['email'] === $email) ? 'email_exists' : 'nip_exists';
            header("Location: regist.php?alert=$alert");
            exit();
        }

        // Insert user baru
        $insert_stmt = $conn->prepare("INSERT INTO t_user (id_nip, fullName, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("sssss", $id_nip, $fullName, $email, $hashed_password, $role);

        if ($insert_stmt->execute()) {
            header("Location: login.php?alert=registered");
        } else {
            header("Location: regist.php?alert=error");
        }
        exit();
    }
}
?>