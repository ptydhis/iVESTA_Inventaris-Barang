<?php
require "../../koneksi.php";
session_start();

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

// Handle Edit Profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['tambah_user']) && !isset($_POST['edit_user'])) {
    $id_nip = $_SESSION['id_nip'];
    $fullName = mysqli_real_escape_string($conn, $_POST['fullName']);
    $noHP = mysqli_real_escape_string($conn, $_POST['noHP']);

    // Handle upload foto
    if (!empty($_FILES['fotoP']['name'])) {
        $fotoBaru = $_FILES['fotoP']['name'];
        $tmpFoto = $_FILES['fotoP']['tmp_name'];
        $errorFoto = $_FILES['fotoP']['error'];
        $sizeFoto = $_FILES['fotoP']['size'];

        // Validasi file
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($fotoBaru, PATHINFO_EXTENSION));

        if ($errorFoto !== UPLOAD_ERR_OK) {
            $error = "Error uploading file. Code: $errorFoto";
        } elseif (!in_array($ext, $allowed)) {
            $error = "Hanya file JPG, JPEG, dan PNG yang diperbolehkan.";
        } elseif ($sizeFoto > 2 * 1024 * 1024) {
            $error = "Ukuran file maksimal 2MB.";
        } else {
            // Generate nama file unik
            $namaFotoBaru = uniqid('profile_', true) . '.' . $ext;
            $uploadPath = __DIR__ . '/../../assets/profiles/' . $namaFotoBaru;

            // Pastikan direktori ada dan bisa ditulisi
            if (!is_dir(__DIR__ . '/../../assets/profiles/')) {
                mkdir(__DIR__ . '/../../assets/profiles/', 0777, true);
            }

            if (move_uploaded_file($tmpFoto, $uploadPath)) {
                // Hapus foto lama jika ada
                if (!empty($_SESSION['fotoP']) && file_exists(__DIR__ . '/../../assets/profiles/' . $_SESSION['fotoP'])) {
                    unlink(__DIR__ . '/../../assets/profiles/' . $_SESSION['fotoP']);
                }

                // Update database
                $update = "UPDATE t_user SET fullName='$fullName', noHP='$noHP', fotoP='$namaFotoBaru' WHERE id_nip='$id_nip'";
                $_SESSION['fotoP'] = $namaFotoBaru;
            } else {
                $error = "Gagal mengupload foto profil.";
            }
        }
    } else {
        // Jika tidak upload foto baru, hanya update data lain
        $update = "UPDATE t_user SET fullName='$fullName', noHP='$noHP' WHERE id_nip='$id_nip'";
    }

    if (isset($update) && empty($error)) {
        if (mysqli_query($conn, $update)) {
            $_SESSION['fullName'] = $fullName;
            $_SESSION['noHP'] = $noHP;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = "Gagal memperbarui profil: " . mysqli_error($conn);
        }
    }
}

// Handle Tambah User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_user'])) {
    $nip = mysqli_real_escape_string($conn, $_POST['nip']);
    $fullName = mysqli_real_escape_string($conn, $_POST['fullName']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Validasi email unik
    $check_email = "SELECT * FROM t_user WHERE email = '$email'";
    $result = mysqli_query($conn, $check_email);

    if (mysqli_num_rows($result) > 0) {
        $_SESSION['error'] = "Email sudah terdaftar";
        header("Location: dataUser.php");
        exit;
    }

    // Validasi NIP unik
    $check_nip = "SELECT * FROM t_user WHERE id_nip = '$nip'";
    $result = mysqli_query($conn, $check_nip);

    if (mysqli_num_rows($result) > 0) {
        $_SESSION['error'] = "NIP sudah terdaftar";
        header("Location: dataUser.php");
        exit;
    }

    $query = "INSERT INTO t_user (id_nip, fullName, email, role, password) 
              VALUES ('$nip', '$fullName', '$email', '$role', '$password')";

    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "User berhasil ditambahkan";
    } else {
        $_SESSION['error'] = "Gagal menambahkan user: " . mysqli_error($conn);
    }

    header("Location: dataUser.php");
    exit;
}

// Handle AJAX request for user data
if (isset($_GET['get_user'])) {
    $id_nip = mysqli_real_escape_string($conn, $_GET['get_user']);
    $query = "SELECT * FROM t_user WHERE id_nip = '$id_nip'";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        header('Content-Type: application/json');
        echo json_encode($user);
        exit;
    } else {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'User not found']);
        exit;
    }
}

// Handle Delete User
if (isset($_GET['delete'])) {
    $id_nip = mysqli_real_escape_string($conn, $_GET['delete']);

    // Hapus foto profil jika ada
    $query_foto = "SELECT fotoP FROM t_user WHERE id_nip = '$id_nip'";
    $result_foto = mysqli_query($conn, $query_foto);
    $row = mysqli_fetch_assoc($result_foto);

    if ($row['fotoP']) {
        $foto_path = realpath(__DIR__ . '/../../assets/profiles/' . $row['fotoP']);
        if (file_exists($foto_path)) {
            unlink($foto_path);
        }
    }

    $query = "DELETE FROM t_user WHERE id_nip = '$id_nip'";

    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "User berhasil dihapus";
    } else {
        $_SESSION['error'] = "Gagal menghapus user: " . mysqli_error($conn);
    }

    header("Location: dataUser.php");
    exit;
}

// Handle Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $id_nip = mysqli_real_escape_string($conn, $_POST['id_nip']);
    $fullName = mysqli_real_escape_string($conn, $_POST['fullName']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $noHP = mysqli_real_escape_string($conn, $_POST['noHP']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);

    // Validasi email unik (kecuali untuk user yang sedang diedit)
    $check_email = "SELECT * FROM t_user WHERE email = '$email' AND id_nip != '$id_nip'";
    $result = mysqli_query($conn, $check_email);
    if (mysqli_num_rows($result) > 0) {
        $_SESSION['error'] = "Email sudah terdaftar";
        header("Location: dataUser.php");
        exit;
    }

    // Handle password jika diisi
    $password_update = "";
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $password_update = ", password = '$password'";
    }

    $query = "UPDATE t_user SET 
              fullName = '$fullName',
              email = '$email',
              noHP = '$noHP',
              role = '$role'
              $password_update
              WHERE id_nip = '$id_nip'";

    if (mysqli_query($conn, $query)) {
        // Jika yang diedit adalah user yang sedang login, update session
        if ($id_nip == $_SESSION['id_nip']) {
            $_SESSION['fullName'] = $fullName;
            $_SESSION['email'] = $email;
            $_SESSION['noHP'] = $noHP;
            $_SESSION['role'] = $role;
        }

        $_SESSION['success'] = "User berhasil diperbarui";
    } else {
        $_SESSION['error'] = "Gagal memperbarui user: " . mysqli_error($conn);
    }

    header("Location: dataUser.php");
    exit;
}

// Pagination settings
$entries_per_page = isset($_GET['entries']) ? (int) $_GET['entries'] : 5;
if (!in_array($entries_per_page, [5, 10, 25, 50])) {
    $entries_per_page = 5;
}
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($current_page - 1) * $entries_per_page;

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$search_condition = $search ? "WHERE fullName LIKE '%$search%' OR id_nip LIKE '%$search%'" : '';

// Count total rows for pagination
$total_query = "SELECT COUNT(*) AS total FROM t_user $search_condition";
$total_result = mysqli_query($conn, $total_query);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $entries_per_page);
$range = 2;
$start_page = max(1, $current_page - $range);
$end_page = min($total_pages, $current_page + $range);

// Get users data with pagination
$query = "SELECT id_nip, fullName, email, noHP, role, fotoP FROM t_user $search_condition LIMIT $offset, $entries_per_page";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iVESTA - Data User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&family=Raleway:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4>iVESTA</h4>
            <p>Inventory System Academic</p>
        </div>

        <div class="sidebar-menu">
            <!-- Dashboard Button -->
            <a href="/ivesta/admin/index.php" class="dashboard-btn">
                <i class="fas fa-tachometer-alt"></i>
                <span>DASHBOARD</span>
            </a>

            <div class="menu-divider"></div>

            <!-- Menu Items -->
            <div class="menu-title">
                <span>MASTER DATA</span>
            </div>

            <a href="dataBarang.php">
                <i class="fas fa-box menu-icon"></i>
                <span>Data Barang</span>
            </a>
            <a href="dataPinjam.php">
                <i class="fas fa-hand-holding menu-icon"></i>
                <span>Data Pinjam</span>
            </a>
            <a href="#" class="active">
                <i class="fas fa-users menu-icon"></i>
                <span>Data User</span>
            </a>

            <div class="menu-divider"></div>

            <a href="Maintenance.php">
                <i class="fas fa-tools menu-icon"></i>
                <span>Maintenance</span>
            </a>
            <div class="menu-divider"></div>

            <a href="Laporan.php">
                <i class="fas fa-file-alt menu-icon"></i>
                <span>Laporan</span>
            </a>
            <div class="menu-divider"></div>

            <!-- Toggle Button at Bottom -->
            <div class="toggle-container">
                <button class="toggle-sidebar" id="toggleSidebar" title="Toggle Sidebar">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white mb-4 rounded shadow-sm">
            <div class="container-fluid">
                <span class="navbar-brand">DATA USER</span>
                <div class="d-flex align-items-center position-relative">
                    <div class="me-3">
                        <span class="text-muted">Selamat datang,</span>
                        <span class="fw-bold"><?= htmlspecialchars($_SESSION['fullName']) ?></span>
                    </div>
                    <div class="user-profile" id="userProfile"
                        style="overflow: hidden; border-radius: 50%; width: 40px; height: 40px; text-align: center; line-height: 40px; font-weight: bold; color: white; background: #6c757d; cursor: pointer;">
                        <?php
                        $foto = $_SESSION['fotoP'] ?? '';
                        $fotoPath = realpath(__DIR__ . '/../../assets/profiles/' . $foto);

                        if ($foto && file_exists($fotoPath)) {
                            echo '<img src="../../assets/profiles/' . htmlspecialchars($foto) . '" alt="Foto Profil" style="width: 100%; height: 100%; object-fit: cover;">';
                        } else {
                            echo strtoupper(substr($_SESSION['fullName'], 0, 1));
                        }
                        ?>
                    </div>

                    <!-- User Dropdown Menu -->
                    <div class="user-dropdown" id="userDropdown">
                        <div class="px-3 py-2">
                            <small class="text-muted">Role:
                                <?php
                                $roleClass = '';
                                switch ($_SESSION['role']) {
                                    case 'admin':
                                        $roleClass = 'bg-primary';
                                        break;
                                    case 'kaprodi':
                                        $roleClass = 'badge-waiting';
                                        break;
                                    case 'kaleb':
                                        $roleClass = 'bg-warning';
                                        break;
                                    case 'pegawai':
                                        $roleClass = 'bg-success';
                                        break;
                                    default:
                                        $roleClass = 'bg-secondary';
                                }
                                ?>
                                <span class="badge <?= $roleClass ?>">
                                    <?= ucfirst(htmlspecialchars($_SESSION['role'])) ?>
                                </span>
                            </small>
                        </div>
                        <a href="#" id="showProfileModal">
                            <i class="fas fa-user"></i> Profil Saya
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../../auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Notifikasi -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Data User Content -->
        <div class="container-fluid">
            <div class="table-container">
                <div class="table-header">
                    <div class="entries-info">
                        Show
                        <select class="form-select form-select-sm d-inline-block w-auto" id="entriesPerPage">
                            <option value="5" <?= $entries_per_page == 5 ? 'selected' : '' ?>>5</option>
                            <option value="10" <?= $entries_per_page == 10 ? 'selected' : '' ?>>10</option>
                            <option value="25" <?= $entries_per_page == 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $entries_per_page == 50 ? 'selected' : '' ?>>50</option>
                        </select>
                        entries
                    </div>
                    <div class="d-flex">
                        <div class="search-box me-2">
                            <form method="get" class="d-flex" id="searchForm">
                                <input type="text" name="search" class="form-control form-control-sm"
                                    placeholder="Search..." value="<?= htmlspecialchars($search) ?>" id="searchInput"
                                    autocomplete="off">
                                <input type="hidden" name="page" value="1">
                                <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                            </form>
                        </div>
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#tambahUserModal">
                            <i class="fas fa-plus"></i> Tambah User
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover table-fixed">
                        <thead>
                            <tr>
                                <th style="width: 5%" class="text-center">No</th>
                                <th style="width: 8%" class="text-center">Foto</th>
                                <th style="width: 15%" class="text-center">NIP</th>
                                <th style="width: 25%" class="text-center">Nama User</th>
                                <th style="width: 25%" class="text-center">Email</th>
                                <th style="width: 12%" class="text-center">Role</th>
                                <th style="width: 10%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php
                                $no = $offset + 1;
                                while ($row = mysqli_fetch_assoc($result)):
                                    ?>
                                    <tr>
                                        <td class="text-center"><?= $no ?></td>
                                        <td class="text-center">
                                            <?php
                                            $foto = $row['fotoP'] ?? '';
                                            $fotoPath = realpath(__DIR__ . '/../../assets/profiles/' . $foto);

                                            if ($foto && file_exists($fotoPath)) {
                                                echo '<img src="../../assets/profiles/' . htmlspecialchars($foto) . '" alt="Foto Profil" class="profile-img mx-auto d-block">';
                                            } else {
                                                echo '<div class="mx-auto" style="width:40px;height:40px;border-radius:50%;background:#eee;display:flex;align-items:center;justify-content:center;color:#666;">'
                                                    . strtoupper(substr($row['fullName'], 0, 1))
                                                    . '</div>';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center"><?= htmlspecialchars($row['id_nip']) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($row['fullName']) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($row['email']) ?></td>
                                        <td class="text-center">
                                            <?php
                                            $roleClass = '';
                                            switch ($row['role']) {
                                                case 'admin':
                                                    $roleClass = 'bg-primary';
                                                    break;
                                                case 'kaprodi':
                                                    $roleClass = 'badge-waiting';
                                                    break;
                                                case 'kaleb':
                                                    $roleClass = 'bg-warning';
                                                    break;
                                                case 'pegawai':
                                                    $roleClass = 'bg-success';
                                                    break;
                                                default:
                                                    $roleClass = 'bg-secondary';
                                            }
                                            ?>
                                            <span class="badge <?= $roleClass ?>">
                                                <?= ucfirst(htmlspecialchars($row['role'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons justify-content-center">
                                                <button class="btn btn-primary btn-sm btn-action me-1" title="Edit"
                                                    data-bs-toggle="modal" data-bs-target="#editUserModal"
                                                    data-id="<?= $row['id_nip'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="?delete=<?= $row['id_nip'] ?>" class="btn btn-danger btn-sm btn-action"
                                                    title="Delete"
                                                    onclick="return confirm('Apakah Anda yakin ingin menghapus user ini?')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                    $no++;
                                endwhile;
                            else:
                                ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">Tidak ada data user</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="table-footer d-flex justify-content-between align-items-center mt-3">
                    <div class="entries-info">
                        Showing <?= $offset + 1 ?> to <?= min($offset + $entries_per_page, $total_rows) ?> of
                        <?= $total_rows ?> entries
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm">
                            <!-- Previous Button -->
                            <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link"
                                    href="?page=<?= $current_page - 1 ?>&search=<?= urlencode($search) ?>&entries=<?= $entries_per_page ?>">Previous</a>
                            </li>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?= $i == $current_page ? 'disabled' : '' ?>">
                                    <a class="page-link"
                                        href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&entries=<?= $entries_per_page ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next Button -->
                            <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link"
                                    href="?page=<?= $current_page + 1 ?>&search=<?= urlencode($search) ?>&entries=<?= $entries_per_page ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer" id="footer">
        <small>Copyright &copy; <?= date('Y'); ?> - Inventory System Academic</small>
    </div>

    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body p-4 text-center">
                    <div class="profile-avatar mx-auto mb-3"
                        style="width: 100px; height: 100px; border-radius: 50%; overflow: hidden; background: #eee; display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: bold; color: #666;">
                        <?php
                        $foto = $_SESSION['fotoP'] ?? '';
                        $fotoPath = realpath(__DIR__ . '/../../assets/profiles/' . $foto);

                        if ($foto && file_exists($fotoPath)) {
                            echo '<img src="../../assets/profiles/' . htmlspecialchars($foto) . '" alt="Foto Profil" style="width: 100%; height: 100%; object-fit: cover;">';
                        } else {
                            echo strtoupper(substr($_SESSION['fullName'], 0, 1));
                        }
                        ?>
                    </div>

                    <h5><?= htmlspecialchars($_SESSION['fullName']) ?></h5>
                    <p class="text-muted mb-3">
                        <span class="badge bg-primary"><?= htmlspecialchars(ucfirst($_SESSION['role'])) ?></span>
                    </p>
                    <hr>
                    <div class="text-start">
                        <p><strong>NIP:</strong> <?= htmlspecialchars($_SESSION['id_nip']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($_SESSION['email']) ?></p>
                        <p><strong>No HP:</strong> <?= htmlspecialchars($_SESSION['noHP'] ?? '-') ?></p>
                    </div>
                    <button id="editProfileBtn" class="btn btn-primary mt-3">Edit Profil</button>
                    <button class="btn btn-outline-secondary mt-3" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="post" action="" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileModalLabel">Edit Profil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 text-center">
                        <?php
                        $foto = $_SESSION['fotoP'] ?? '';
                        if ($foto && file_exists("../../assets/profiles/$foto")) {
                            echo '<img src="../../assets/profiles/' . htmlspecialchars($foto) . '" alt="Foto Profil" class="rounded-circle mb-2" style="width:80px;height:80px;object-fit:cover;">';
                        } else {
                            echo '<div class="profile-avatar mx-auto mb-2" style="width:80px;height:80px;line-height:80px;font-size:2rem;background:#eee;border-radius:50%;">' . strtoupper(substr($_SESSION['fullName'], 0, 1)) . '</div>';
                        }
                        ?>
                        <div class="mt-2">
                            <input class="form-control" type="file" name="fotoP" accept="image/*">
                            <small class="text-muted">Format: JPG, JPEG, PNG. Maks 2MB.</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="fullName" class="form-label">Nama</label>
                        <input type="text" class="form-control" id="fullName" name="fullName"
                            value="<?= htmlspecialchars($_SESSION['fullName']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="noHP" class="form-label">No HP</label>
                        <input type="text" class="form-control" id="noHP" name="noHP"
                            value="<?= htmlspecialchars($_SESSION['noHP'] ?? '-') ?>" required>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Tambah User -->
    <div class="modal fade" id="tambahUserModal" tabindex="-1" aria-labelledby="tambahUserModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="post" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahUserModalLabel">Tambah User Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nip" class="form-label">NIP</label>
                        <input type="text" class="form-control" id="nip" name="nip" required>
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
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Pilih Role</option>
                            <option value="admin">Admin</option>
                            <option value="kaprodi">Kaprodi</option>
                            <!-- <option value="kaleb">Kepala Lab</option> -->
                            <option value="pegawai">Pegawai</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="tambah_user" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit User -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="post" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_nip" id="edit_id_nip">
                    <div class="mb-3">
                        <label for="edit_nip" class="form-label">NIP</label>
                        <input type="text" class="form-control" id="edit_nip" name="nip" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_fullName" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" id="edit_fullName" name="fullName" required>
                    </div>
                    <div class="mb-3"> <label for="edit_email" class="form-label">Email</label> <input type="email"
                            class="form-control" id="edit_email" name="email" required> </div>
                    <div class="mb-3"> <label for="edit_noHP" class="form-label">No HP</label> <input type="text"
                            class="form-control" id="edit_noHP" name="noHP"> </div>
                    <div class="mb-3"> <label for="edit_role" class="form-label">Role</label> <select
                            class="form-select" id="edit_role" name="role" required>
                            <option value="">Pilih Role</option>
                            <option value="admin">Admin</option>
                            <option value="kaprodi">Kaprodi</option>
                            <!-- <option value="kaleb">Kepala Lab</option> -->
                            <option value="pegawai">Pegawai</option>
                        </select> </div>
                    <div class="mb-3"> <label for="edit_password" class="form-label">Password (Biarkan kosong jika tidak
                            ingin diubah)</label> <input type="password" class="form-control" id="edit_password"
                            name="password"> </div>
                </div>
                <div class="modal-footer justify-content-between"> <button type="button"
                        class="btn btn-outline-secondary" data-bs-dismiss="modal"> <i class="fas fa-times"></i> Batal
                    </button> <button type="submit" name="edit_user" class="btn btn-primary"> <i
                            class="fas fa-save"></i> Simpan </button> </div>
            </form>
        </div>
    </div>
    <!-- Click Outside to Close Dropdown -->
    <div class="dropdown-overlay" id="dropdownOverlay"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar function
        document.getElementById('toggleSidebar').addEventListener('click', function () {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const footer = document.getElementById('footer');

            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            footer.classList.toggle('expanded');

            // Save state to localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });

        // Check for saved sidebar state and set active menu
        document.addEventListener('DOMContentLoaded', function () {
            // Restore sidebar state
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                document.getElementById('sidebar').classList.add('collapsed');
                document.getElementById('mainContent').classList.add('expanded');
                document.getElementById('footer').classList.add('expanded');
            }

            // Set active menu based on current page
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.sidebar-menu a').forEach(link => {
                const linkHref = link.getAttribute('href');
                if (linkHref) {
                    const linkPage = linkHref.split('/').pop();
                    if (linkPage === currentPage ||
                        (currentPage === 'index.php' && linkHref.includes('index.php'))) {
                        link.classList.add('active');
                    }
                }
            });

            // Initialize edit modal
            const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));

            // Handle edit button clicks
            document.querySelectorAll('[data-bs-target="#editUserModal"]').forEach(btn => {
                btn.addEventListener('click', function () {
                    const userId = this.getAttribute('data-id');

                    fetch(`?get_user=${userId}`)
                        .then(response => {
                            if (!response.ok) throw new Error('User not found');
                            return response.json();
                        })
                        .then(user => {
                            // Fill the form
                            document.getElementById('edit_id_nip').value = user.id_nip;
                            document.getElementById('edit_nip').value = user.id_nip;
                            document.getElementById('edit_fullName').value = user.fullName;
                            document.getElementById('edit_email').value = user.email;
                            document.getElementById('edit_noHP').value = user.noHP || '';

                            // Set role
                            const roleSelect = document.getElementById('edit_role');
                            Array.from(roleSelect.options).forEach(option => {
                                option.selected = option.value === user.role;
                            });

                            // Show modal
                            editModal.show();
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Gagal memuat data user');
                        });
                });
            });

            // Clean URL when modal is closed
            editModal._element.addEventListener('hidden.bs.modal', function () {
                const url = new URL(window.location);
                if (url.searchParams.has('edit')) {
                    url.searchParams.delete('edit');
                    window.history.replaceState({}, '', url);
                }
            });
        });

        // User dropdown toggle
        const userProfile = document.getElementById('userProfile');
        const userDropdown = document.getElementById('userDropdown');
        const dropdownOverlay = document.getElementById('dropdownOverlay');

        userProfile.addEventListener('click', function (e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
            dropdownOverlay.style.display = userDropdown.classList.contains('show') ? 'block' : 'none';
        });

        // Close dropdown when clicking outside
        dropdownOverlay.addEventListener('click', function () {
            userDropdown.classList.remove('show');
            this.style.display = 'none';
        });

        // Close dropdown when clicking on dropdown items
        document.querySelectorAll('#userDropdown a').forEach(item => {
            item.addEventListener('click', function () {
                userDropdown.classList.remove('show');
                dropdownOverlay.style.display = 'none';
            });
        });

        document.getElementById('showProfileModal').addEventListener('click', function (e) {
            e.preventDefault();
            userDropdown.classList.remove('show');
            dropdownOverlay.style.display = 'none';
            new bootstrap.Modal(document.getElementById('profileModal')).show();
        });

        document.getElementById('editProfileBtn').addEventListener('click', function () {
            const profileModal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
            profileModal.hide();
            new bootstrap.Modal(document.getElementById('editProfileModal')).show();
        });

        // Change entries per page
        document.getElementById('entriesPerPage').addEventListener('change', function () {
            const entries = this.value;
            const search = '<?= htmlspecialchars($search) ?>';
            window.location.href = `?page=1&entries=${entries}&search=${encodeURIComponent(search)}`;
        });

        // Enhanced real-time search functionality
        const searchInput = document.getElementById('searchInput');
        const searchForm = document.getElementById('searchForm');
        const tableBody = document.querySelector('tbody');
        const originalTableContent = tableBody.innerHTML;

        // Debounce function
        let debounceTimer;
        function debounceSearch() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const searchTerm = searchInput.value.trim();

                if (searchTerm === '') {
                    tableBody.innerHTML = originalTableContent;
                    return;
                }

                fetch(`?search=${encodeURIComponent(searchTerm)}&page=1&entries=<?= $entries_per_page ?>`)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newTableBody = doc.querySelector('tbody');

                        if (newTableBody) {
                            tableBody.innerHTML = newTableBody.innerHTML;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }, 300);
        }

        // Event listeners
        searchInput.addEventListener('input', debounceSearch);

        searchInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchForm.submit();
            }
        });

        // Handle clear search (for browsers with clear button)
        searchInput.addEventListener('search', function () {
            if (this.value === '') {
                tableBody.innerHTML = originalTableContent;
            }
        });
    </script>
</body>

</html>