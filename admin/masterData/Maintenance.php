<?php
require "../../koneksi.php";
session_start();

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

// Handle AJAX request for maintenance detail
if (isset($_GET['get_maintenance_detail'])) {
    $id_detail = mysqli_real_escape_string($conn, $_GET['get_maintenance_detail']);

    $query = "SELECT bd.id_detail, bd.kode_barang, bd.kondisi, bd.tanggal_rusak, bd.tanggal_hilang, bd.note,
                 bd.tanggal_maintenance,
                 b.id_barang, b.nama_barang, b.merk, b.foto_barang, b.tanggal_input, b.milik
          FROM t_barang_detail bd
          JOIN t_barang b ON bd.id_barang = b.id_barang
          WHERE bd.id_detail = '$id_detail'";

    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $data = mysqli_fetch_assoc($result);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    } else {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Data maintenance tidak ditemukan']);
        exit;
    }
}

// Handle Edit Profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fullName'])) {
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

// Handle Update Maintenance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_maintenance'])) {
    $id_detail = mysqli_real_escape_string($conn, $_POST['id_detail']);
    $kondisi = mysqli_real_escape_string($conn, $_POST['kondisi']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan'] ?? '');
    $tanggal_rusak = null;
    $tanggal_hilang = null;

    // Gunakan waktu lokal dari input form untuk maintenance
    $tanggal_maintenance = date('Y-m-d H:i:s', strtotime($_POST['tanggal_maintenance']));

    // Set tanggal berdasarkan kondisi
    if ($kondisi === 'Rusak' && !empty($_POST['tanggal_rusak'])) {
        $tanggal_rusak = date('Y-m-d H:i:s', strtotime($_POST['tanggal_rusak']));
    } elseif ($kondisi === 'Hilang' && !empty($_POST['tanggal_hilang'])) {
        $tanggal_hilang = date('Y-m-d H:i:s', strtotime($_POST['tanggal_hilang']));
    }

    // Tentukan status berdasarkan kondisi
    if ($kondisi === 'Baik') {
        $status = 'Tersedia';
    } elseif ($kondisi === 'Hilang') {
        $status = 'Hilang';
    } else {
        $status = 'Tersedia';
    }

    // Begin transaction
    mysqli_begin_transaction($conn);

    try {
        $query = "UPDATE t_barang_detail 
                 SET kondisi = '$kondisi', 
                     status = '$status',
                     tanggal_rusak = " . ($kondisi === 'Rusak' ? "'$tanggal_rusak'" : "NULL") . ",
                     tanggal_hilang = " . ($kondisi === 'Hilang' ? "'$tanggal_hilang'" : "NULL") . ",
                     note = " . ($kondisi !== 'Baik' ? "'$keterangan'" : "NULL") . ",
                     tanggal_maintenance = '$tanggal_maintenance'
                 WHERE id_detail = '$id_detail'";

        $result = mysqli_query($conn, $query);

        if (!$result) {
            throw new Exception("Gagal memperbarui detail barang: " . mysqli_error($conn));
        }

        mysqli_commit($conn);
        $_SESSION['success'] = "Data maintenance berhasil diperbarui";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Pagination settings
$entries_per_page = isset($_GET['entries']) ? (int) $_GET['entries'] : 5;
if (!in_array($entries_per_page, [5, 10, 25, 50])) {
    $entries_per_page = 10;
}
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($current_page - 1) * $entries_per_page;

// Filter and Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_kondisi = isset($_GET['kondisi']) ? mysqli_real_escape_string($conn, $_GET['kondisi']) : '';

// Build conditions for query
$search_condition = $search ? "AND (b.nama_barang LIKE '%$search%' OR bd.kode_barang LIKE '%$search%')" : '';
$filter_condition = $filter_kondisi ? "AND bd.kondisi = '$filter_kondisi'" : '';

// Count total rows for pagination
$total_query = "SELECT COUNT(*) AS total 
                FROM t_barang_detail bd
                JOIN t_barang b ON bd.id_barang = b.id_barang
                WHERE 1=1 $search_condition $filter_condition";
$total_result = mysqli_query($conn, $total_query);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $entries_per_page);
$range = 2;
$start_page = max(1, $current_page - $range);
$end_page = min($total_pages, $current_page + $range);

// Get maintenance data with pagination
$query = "SELECT bd.id_detail, bd.kode_barang, bd.kondisi, bd.tanggal_rusak, bd.tanggal_maintenance,
                 b.id_barang, b.nama_barang, b.merk, b.foto_barang, b.tanggal_input, b.milik,
                 (SELECT p.keterangan FROM t_pinjam p WHERE p.id_detail = bd.id_detail ORDER BY p.tanggal_pinjam DESC LIMIT 1) AS keterangan
          FROM t_barang_detail bd
          JOIN t_barang b ON bd.id_barang = b.id_barang
          WHERE 1=1 $search_condition $filter_condition
          ORDER BY bd.kondisi DESC, bd.tanggal_rusak DESC
          LIMIT $offset, $entries_per_page";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iVESTA - Maintenance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&family=Raleway:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .no-photo {
            width: 50px;
            height: 50px;
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }

        .profile-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
    </style>
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
            <a href="dataUser.php">
                <i class="fas fa-users menu-icon"></i>
                <span>Data User</span>
            </a>

            <div class="menu-divider"></div>

            <a href="#" class="active">
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
                <span class="navbar-brand">MAINTENANCE</span>
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
                                        $roleClass = 'bg-info';
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

        <!-- Maintenance Content -->
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
                    <div class="table-controls">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="search-box" style="min-width: 220px;">
                                <form method="get" class="d-flex" id="searchForm">
                                    <input type="text" name="search" class="form-control form-control-sm"
                                        placeholder="Search..." value="<?= htmlspecialchars($search) ?>"
                                        id="searchInput" autocomplete="off">
                                    <input type="hidden" name="page" value="1">
                                    <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                                </form>
                            </div>
                            <div style="min-width: 150px;">
                                <select class="form-select form-select-sm filter-select" name="kondisi"
                                    id="filterKondisi">
                                    <option value="">Semua Kondisi</option>
                                    <option value="Baik" <?= $filter_kondisi === 'Baik' ? 'selected' : '' ?>>Baik</option>
                                    <option value="Rusak" <?= $filter_kondisi === 'Rusak' ? 'selected' : '' ?>>Rusak
                                    </option>
                                    <option value="Hilang" <?= $filter_kondisi === 'Hilang' ? 'selected' : '' ?>>Hilang
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover table-fixed">
                        <thead>
                            <tr>
                                <th style="width: 5%" class="text-center">No</th>
                                <th style="width: 10%" class="text-center">Foto</th>
                                <th style="width: 20%" class="text-center">Kode Barang</th>
                                <th style="width: 25%" class="text-center">Nama Barang</th>
                                <th style="width: 15%" class="text-center">Merk</th>
                                <th style="width: 15%" class="text-center">Kondisi</th>
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
                                            <?php if (!empty($row['foto_barang'])): ?>
                                                <img src="../../assets/barang/<?= htmlspecialchars($row['foto_barang']) ?>"
                                                    class="profile-img" alt="Foto Barang">
                                            <?php else: ?>
                                                <div class="no-photo">
                                                    <i class="fas fa-box-open"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?= htmlspecialchars($row['kode_barang']) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($row['nama_barang']) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($row['merk']) ?></td>
                                        <td class="text-center">
                                            <?php
                                            $badgeClass = '';
                                            switch ($row['kondisi']) {
                                                case 'Baik':
                                                    $badgeClass = 'badge bg-success';
                                                    break;
                                                case 'Rusak':
                                                    $badgeClass = 'badge bg-danger';
                                                    break;
                                                case 'Hilang':
                                                    $badgeClass = 'badge bg-dark';
                                                    break;
                                                default:
                                                    $badgeClass = 'bg-secondary';
                                            }
                                            ?>
                                            <span class="badge <?= $badgeClass ?>">
                                                <?= htmlspecialchars($row['kondisi']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons d-flex justify-content-center">
                                                <!-- Detail Button -->
                                                <button class="btn btn-info btn-sm btn-action" data-bs-toggle="modal"
                                                    data-bs-target="#detailMaintenanceModal" data-id="<?= $row['id_detail'] ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>

                                                <!-- Edit Button -->
                                                <button class="btn btn-primary btn-sm btn-action" data-bs-toggle="modal"
                                                    data-bs-target="#updateMaintenanceModal" data-id="<?= $row['id_detail'] ?>"
                                                    data-kondisi="<?= htmlspecialchars($row['kondisi']) ?>"
                                                    data-keterangan="<?= htmlspecialchars($row['keterangan'] ?? '') ?>"
                                                    data-tanggal-rusak="<?= $row['tanggal_rusak'] ?? '' ?>"
                                                    data-tanggal-hilang="<?= $row['tanggal_hilang'] ?? '' ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                    $no++;
                                endwhile;
                            else:
                                ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">Tidak ada data maintenance</td>
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
                                    href="?page=<?= $current_page - 1 ?>&search=<?= urlencode($search) ?>&entries=<?= $entries_per_page ?>&kondisi=<?= urlencode($filter_kondisi) ?>">Previous</a>
                            </li>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?= $i == $current_page ? 'disabled' : '' ?>">
                                    <a class="page-link"
                                        href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&entries=<?= $entries_per_page ?>&kondisi=<?= urlencode($filter_kondisi) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next Button -->
                            <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link"
                                    href="?page=<?= $current_page + 1 ?>&search=<?= urlencode($search) ?>&entries=<?= $entries_per_page ?>&kondisi=<?= urlencode($filter_kondisi) ?>">Next</a>
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

    <!-- Detail Maintenance Modal (Read-only) -->
    <div class="modal fade" id="detailMaintenanceModal" tabindex="-1" aria-labelledby="detailMaintenanceModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailMaintenanceModalLabel">Detail Maintenance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detailModalContent">
                    <!-- Konten akan diisi oleh JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Maintenance Modal (Editable) -->
    <div class="modal fade" id="updateMaintenanceModal" tabindex="-1" aria-labelledby="updateMaintenanceModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="post" action="maintenance.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateMaintenanceModalLabel">Update Maintenance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_detail" id="maintenance_id_detail">
                    <div class="mb-3">
                        <label for="kondisi" class="form-label">Kondisi Barang</label>
                        <select class="form-select" id="kondisi" name="kondisi" required>
                            <option value="Baik">Baik</option>
                            <option value="Rusak">Rusak</option>
                            <option value="Hilang">Hilang</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="keterangan" class="form-label">Keterangan Maintenance</label>
                        <textarea class="form-control" id="keterangan" name="keterangan" rows="3"></textarea>
                    </div>
                    <!-- Tanggal Rusak (only shown when condition is Rusak) -->
                    <div class="mb-3" id="tanggalRusakContainer">
                        <label for="tanggal_rusak" class="form-label">Tanggal & Waktu Rusak</label>
                        <input type="datetime-local" class="form-control" id="tanggal_rusak" name="tanggal_rusak">
                    </div>
                    <!-- Tanggal Hilang (only shown when condition is Hilang) -->
                    <div class="mb-3" id="tanggalHilangContainer" style="display: none;">
                        <label for="tanggal_hilang" class="form-label">Tanggal & Waktu Hilang</label>
                        <input type="datetime-local" class="form-control" id="tanggal_hilang" name="tanggal_hilang">
                    </div>
                    <!-- Tanggal Maintenance (independent field) -->
                    <div class="mb-3">
                        <label for="tanggal_maintenance" class="form-label">Tanggal & Waktu di Maintenance</label>
                        <input type="datetime-local" class="form-control" id="tanggal_maintenance"
                            name="tanggal_maintenance">
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="update_maintenance" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Click Outside to Close Dropdown -->
    <div class="dropdown-overlay" id="dropdownOverlay"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        });

        // User dropdown toggle
        const userProfile = document.getElementById('userProfile');
        const userDropdown = document.getElementById('userDropdown');
        const dropdownOverlay = document.getElementById('dropdownOverlay');

        if (userProfile && userDropdown) {
            userProfile.addEventListener('click', function (e) {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
                if (dropdownOverlay) {
                    dropdownOverlay.style.display = userDropdown.classList.contains('show') ? 'block' : 'none';
                }
            });

            // Close dropdown when clicking outside
            if (dropdownOverlay) {
                dropdownOverlay.addEventListener('click', function () {
                    userDropdown.classList.remove('show');
                    this.style.display = 'none';
                });
            }

            // Close dropdown when clicking on dropdown items
            document.querySelectorAll('#userDropdown a').forEach(item => {
                item.addEventListener('click', function () {
                    userDropdown.classList.remove('show');
                    if (dropdownOverlay) dropdownOverlay.style.display = 'none';
                });
            });
        }

        // Profile modal handling
        if (document.getElementById('showProfileModal')) {
            document.getElementById('showProfileModal').addEventListener('click', function (e) {
                e.preventDefault();
                if (userDropdown) userDropdown.classList.remove('show');
                if (dropdownOverlay) dropdownOverlay.style.display = 'none';
                const profileModal = new bootstrap.Modal(document.getElementById('profileModal'));
                profileModal.show();
            });
        }

        // Edit profile button
        if (document.getElementById('editProfileBtn')) {
            document.getElementById('editProfileBtn').addEventListener('click', function () {
                const profileModal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
                profileModal.hide();

                const editModal = new bootstrap.Modal(document.getElementById('editProfileModal'));
                editModal.show();
            });
        }

        // Entries per page change
        document.getElementById('entriesPerPage').addEventListener('change', function () {
            const entries = this.value;
            const search = document.getElementById('searchInput').value;
            const kondisi = document.getElementById('filterKondisi').value;

            window.location.href = `?page=1&entries=${entries}&search=${encodeURIComponent(search)}&kondisi=${encodeURIComponent(kondisi)}`;
        });

        // Enhanced real-time search functionality
        const searchInput = document.getElementById('searchInput');
        const filterKondisi = document.getElementById('filterKondisi');
        const tableBody = document.querySelector('tbody');
        const originalTableContent = tableBody.innerHTML;

        // Store current values to prevent unnecessary updates
        let currentSearchValue = searchInput.value;
        let currentKondisiValue = filterKondisi.value;

        // Debounce function
        let debounceTimer;
        function debounceSearch() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const searchTerm = searchInput.value.trim();
                const kondisiValue = filterKondisi.value;

                // Only proceed if values have changed
                if (searchTerm === currentSearchValue && kondisiValue === currentKondisiValue) {
                    return;
                }

                currentSearchValue = searchTerm;
                currentKondisiValue = kondisiValue;

                // If both search and filter are empty, restore original content
                if (searchTerm === '' && kondisiValue === '') {
                    tableBody.innerHTML = originalTableContent;
                    return;
                }

                // Fetch new data
                fetch(`?search=${encodeURIComponent(searchTerm)}&kondisi=${encodeURIComponent(kondisiValue)}&page=1&entries=<?= $entries_per_page ?>`)
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
        filterKondisi.addEventListener('change', debounceSearch);

        // Handle Enter key in search input
        searchInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('searchForm').submit();
            }
        });

        // Handle clear search (for browsers with clear button)
        searchInput.addEventListener('search', function () {
            if (this.value === '') {
                debounceSearch();
            }
        });

        // Filter kondisi change
        document.getElementById('filterKondisi').addEventListener('change', function () {
            const kondisi = this.value;
            const search = document.getElementById('searchInput').value;
            const entries = document.getElementById('entriesPerPage').value;

            window.location.href = `?page=1&entries=${entries}&search=${encodeURIComponent(search)}&kondisi=${encodeURIComponent(kondisi)}`;
        });

        // Detail Maintenance Modal
        $('#detailMaintenanceModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const idDetail = button.data('id');
            const modal = $(this);

            // Show loading spinner
            modal.find('#detailModalContent').html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `);

            // Fetch data via AJAX
            $.get(`?get_maintenance_detail=${idDetail}`, function (data) {
                if (data.error) {
                    modal.find('#detailModalContent').html(`
                        <div class="alert alert-danger mb-0">${data.error}</div>
                    `);
                } else {
                    // Format tanggal input
                    const tanggalInput = data.tanggal_input
                        ? new Date(data.tanggal_input).toLocaleString('id-ID', {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        })
                        : '-';

                    // Format tanggal rusak/hilang berdasarkan kondisi
                    let tanggalMasalah = '-';
                    if (data.kondisi === 'Rusak' && data.tanggal_rusak) {
                        tanggalMasalah = new Date(data.tanggal_rusak).toLocaleString('id-ID', {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    } else if (data.kondisi === 'Hilang' && data.tanggal_hilang) {
                        tanggalMasalah = new Date(data.tanggal_hilang).toLocaleString('id-ID', {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }

                    // Format tanggal maintenance
                    const tanggalMaintenance = data.tanggal_maintenance
                        ? new Date(data.tanggal_maintenance).toLocaleString('id-ID', {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        })
                        : '-';

                    // Format kondisi
                    let kondisiBadge = '';
                    if (data.kondisi === 'Baik') {
                        kondisiBadge = '<span class="badge bg-success">Baik</span>';
                    } else if (data.kondisi === 'Rusak') {
                        kondisiBadge = '<span class="badge bg-danger">Rusak</span>';
                    } else if (data.kondisi === 'Hilang') {
                        kondisiBadge = '<span class="badge bg-dark">Hilang</span>';
                    } else {
                        kondisiBadge = `<span class="badge bg-secondary">${data.kondisi || '-'}</span>`;
                    }

                    // Format milik dengan badge
                    let milikBadge;
                    if (data.milik === 'Prodi') {
                        milikBadge = '<span class="badge bg-info">Prodi</span>';
                    } else {
                        milikBadge = `<span class="badge bg-secondary">${data.milik}</span>`;
                    }

                    // Tentukan label tanggal berdasarkan kondisi
                    const tanggalLabel = data.kondisi === 'Hilang' ? 'Tanggal Hilang' : 'Tanggal Rusak';

                    modal.find('#detailModalContent').html(`
                        <div class="container-fluid">
                            <div class="row">
                                <div class="col-md-6">
                                    <p>
                                        <strong>Kode Barang:</strong><br>
                                        ${data.kode_barang}
                                    </p>
                                    <p>
                                        <strong>Tanggal Input:</strong><br>
                                        ${tanggalInput}
                                    </p>
                                    <p>
                                        <strong>${tanggalLabel}:</strong><br>
                                        ${tanggalMasalah}
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p>
                                        <strong>Milik:</strong><br>
                                        ${milikBadge}
                                    </p>
                                    <p>
                                        <strong>Kondisi:</strong><br>
                                        ${kondisiBadge}
                                    </p>
                                    <p>
                                        <strong>Maintenance Terbaru:</strong><br>
                                        ${tanggalMaintenance}
                                    </p>
                                    <p>
                                        <strong>Keterangan Maintenance:</strong><br>
                                        ${data.note || '-'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    `);
                }
            }).fail(function () {
                modal.find('#detailModalContent').html(`
                    <div class="alert alert-danger">Gagal memuat data maintenance</div>
                `);
            });
        });

        // Update Maintenance Modal
        $('#updateMaintenanceModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const idDetail = button.data('id');
            const kondisi = button.data('kondisi');
            const keterangan = button.data('keterangan');
            const tanggalRusak = button.data('tanggal-rusak');
            const tanggalHilang = button.data('tanggal-hilang');

            const modal = $(this);
            modal.find('#maintenance_id_detail').val(idDetail);
            modal.find('#kondisi').val(kondisi);
            modal.find('#keterangan').val(keterangan || '');

            // Fungsi untuk format datetime-local dengan timezone lokal
            function toLocalDateTimeString(date) {
                if (!date) return '';

                const d = new Date(date);
                const pad = num => (num < 10 ? '0' + num : num);

                return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
            }

            // Set waktu saat ini untuk semua field
            const now = new Date();
            const localTimeString = toLocalDateTimeString(now);

            // Set nilai default untuk semua field waktu
            modal.find('#tanggal_maintenance').val(localTimeString);
            modal.find('#tanggal_rusak').val(localTimeString);
            modal.find('#tanggal_hilang').val(localTimeString);

            // Jika ada data sebelumnya, gunakan data tersebut
            if (kondisi === 'Rusak' && tanggalRusak) {
                modal.find('#tanggal_rusak').val(toLocalDateTimeString(tanggalRusak));
            }
            if (kondisi === 'Hilang' && tanggalHilang) {
                modal.find('#tanggal_hilang').val(toLocalDateTimeString(tanggalHilang));
            }

            // Show/hide date fields based on initial condition
            toggleDateFields(kondisi);

            // Add event listener for kondisi change
            modal.find('#kondisi').off('change').on('change', function () {
                const newKondisi = $(this).val();
                toggleDateFields(newKondisi);

                // Update current time when condition changes
                const currentTime = toLocalDateTimeString(new Date());

                if (newKondisi === 'Rusak') {
                    modal.find('#tanggal_rusak').val(currentTime);
                } else if (newKondisi === 'Hilang') {
                    modal.find('#tanggal_hilang').val(currentTime);
                }
                // Selalu update maintenance time
                modal.find('#tanggal_maintenance').val(currentTime);
            });
        });

        function toggleDateFields(kondisi) {
            const rusakContainer = $('#tanggalRusakContainer');
            const hilangContainer = $('#tanggalHilangContainer');

            // Hide all first
            rusakContainer.hide();
            hilangContainer.hide();

            // Show relevant field based on condition
            if (kondisi === 'Rusak') {
                rusakContainer.show();
            } else if (kondisi === 'Hilang') {
                hilangContainer.show();
            }
        }
    </script>
</body>

</html>