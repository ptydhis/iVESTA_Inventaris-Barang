<?php
require "../../koneksi.php";
session_start();

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

// Handle Edit Profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
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

// Handle perubahan status peminjaman
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id_pinjam = $_POST['id_pinjam'];
    $action = $_POST['action'];

    // Mulai transaksi
    $conn->begin_transaction();

    try {
        if ($action == 'approve') {
            // 1. Update status peminjaman di t_pinjam
            $query1 = "UPDATE t_pinjam 
                      SET status_peminjaman = 'Dipinjam' 
                      WHERE id_pinjam = ? AND status_peminjaman = 'Menunggu Verifikasi'";
            $stmt1 = $conn->prepare($query1);
            $stmt1->bind_param("i", $id_pinjam);
            $stmt1->execute();

            if ($stmt1->affected_rows === 0) {
                throw new Exception("Gagal mengupdate status peminjaman: Peminjaman tidak ditemukan atau sudah diverifikasi");
            }

            // 2. Dapatkan id_detail dari peminjaman
            $query2 = "SELECT id_detail FROM t_pinjam WHERE id_pinjam = ?";
            $stmt2 = $conn->prepare($query2);
            $stmt2->bind_param("i", $id_pinjam);
            $stmt2->execute();
            $result = $stmt2->get_result();

            if ($result->num_rows === 0) {
                throw new Exception("Detail peminjaman tidak ditemukan");
            }

            $row = $result->fetch_assoc();
            $id_detail = $row['id_detail'];

            // 3. Update status barang di t_barang_detail menjadi Dipinjam
            $query3 = "UPDATE t_barang_detail 
                      SET status = 'Dipinjam' 
                      WHERE id_detail = ? AND status = 'Menunggu Verifikasi'";
            $stmt3 = $conn->prepare($query3);
            $stmt3->bind_param("i", $id_detail);
            $stmt3->execute();

            if ($stmt3->affected_rows === 0) {
                throw new Exception("Gagal mengupdate status barang: Barang tidak ditemukan atau status bukan 'Menunggu Verifikasi'");
            }

            $_SESSION['success'] = "Peminjaman berhasil disetujui";
        } elseif ($action == 'complete') {
            // 1. Update status peminjaman di t_pinjam
            $query1 = "UPDATE t_pinjam 
                      SET status_peminjaman = 'Dikembalikan', tanggal_kembali = NOW() 
                      WHERE id_pinjam = ? AND status_peminjaman = 'Dipinjam'";
            $stmt1 = $conn->prepare($query1);
            $stmt1->bind_param("i", $id_pinjam);
            $stmt1->execute();

            if ($stmt1->affected_rows === 0) {
                throw new Exception("Gagal mengupdate status peminjaman: Peminjaman tidak ditemukan atau sudah dikembalikan");
            }

            // 2. Dapatkan id_detail dari peminjaman
            $query2 = "SELECT id_detail FROM t_pinjam WHERE id_pinjam = ?";
            $stmt2 = $conn->prepare($query2);
            $stmt2->bind_param("i", $id_pinjam);
            $stmt2->execute();
            $result = $stmt2->get_result();

            if ($result->num_rows === 0) {
                throw new Exception("Detail peminjaman tidak ditemukan");
            }

            $row = $result->fetch_assoc();
            $id_detail = $row['id_detail'];

            // 3. Update status barang di t_barang_detail menjadi Tersedia
            $query3 = "UPDATE t_barang_detail 
                      SET status = 'Tersedia' 
                      WHERE id_detail = ? AND status = 'Dipinjam'";
            $stmt3 = $conn->prepare($query3);
            $stmt3->bind_param("i", $id_detail);
            $stmt3->execute();

            if ($stmt3->affected_rows === 0) {
                throw new Exception("Gagal mengupdate status barang: Barang tidak ditemukan atau status bukan 'Dipinjam'");
            }

            $_SESSION['success'] = "Pengembalian berhasil dicatat";
        } elseif ($action == 'reject') {
            // 1. Update status peminjaman di t_pinjam
            $query1 = "UPDATE t_pinjam 
                      SET status_peminjaman = 'Ditolak'
                      WHERE id_pinjam = ? AND status_peminjaman = 'Menunggu Verifikasi'";
            $stmt1 = $conn->prepare($query1);
            $stmt1->bind_param("i", $id_pinjam);
            $stmt1->execute();

            if ($stmt1->affected_rows === 0) {
                throw new Exception("Gagal menolak peminjaman: Peminjaman tidak ditemukan atau sudah diverifikasi");
            }

            // 2. Dapatkan id_detail dari peminjaman
            $query2 = "SELECT id_detail FROM t_pinjam WHERE id_pinjam = ?";
            $stmt2 = $conn->prepare($query2);
            $stmt2->bind_param("i", $id_pinjam);
            $stmt2->execute();
            $result = $stmt2->get_result();

            if ($result->num_rows === 0) {
                throw new Exception("Detail peminjaman tidak ditemukan");
            }

            $row = $result->fetch_assoc();
            $id_detail = $row['id_detail'];

            // 3. Update status barang di t_barang_detail menjadi Tersedia
            $query3 = "UPDATE t_barang_detail 
                      SET status = 'Tersedia' 
                      WHERE id_detail = ? AND status = 'Menunggu Verifikasi'";
            $stmt3 = $conn->prepare($query3);
            $stmt3->bind_param("i", $id_detail);
            $stmt3->execute();

            if ($stmt3->affected_rows === 0) {
                throw new Exception("Gagal mengupdate status barang: Barang tidak ditemukan atau status bukan 'Menunggu Verifikasi'");
            }

            $_SESSION['success'] = "Peminjaman berhasil ditolak";
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: dataPinjam.php");
    exit;
}

// Handle Delete Peminjaman
if (isset($_GET['delete'])) {
    $id_pinjam = mysqli_real_escape_string($conn, $_GET['delete']);

    // 1. Dapatkan id_detail dari peminjaman
    $query = "SELECT id_detail FROM t_pinjam WHERE id_pinjam = '$id_pinjam'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $id_detail = $row['id_detail'];

        // 2. Update status barang menjadi Tersedia
        mysqli_query($conn, "UPDATE t_barang_detail SET status = 'Tersedia' WHERE id_detail = '$id_detail'");

        // 3. Hapus data peminjaman
        if (mysqli_query($conn, "DELETE FROM t_pinjam WHERE id_pinjam = '$id_pinjam'")) {
            $_SESSION['success'] = "Peminjaman berhasil dihapus";
        } else {
            $_SESSION['error'] = "Gagal menghapus peminjaman: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = "Data peminjaman tidak ditemukan";
    }

    header("Location: dataPinjam.php");
    exit;
}

// Handle AJAX request for peminjaman detail
if (isset($_GET['get_peminjaman'])) {
    $id_pinjam = mysqli_real_escape_string($conn, $_GET['get_peminjaman']);
    $query = "SELECT tp.*, b.nama_barang, bd.kode_barang, bd.kondisi, 
              u.fullName as nama_peminjam, tp.keterangan, 
              tp.tanggal_pinjam, tp.tanggal_kembali, tp.status_peminjaman,
              bd.status as status_barang, bd.tanggal_hilang
              FROM t_pinjam tp
              JOIN t_barang_detail bd ON tp.id_detail = bd.id_detail
              JOIN t_barang b ON bd.id_barang = b.id_barang
              JOIN t_user u ON tp.id_nip = u.id_nip
              WHERE tp.id_pinjam = '$id_pinjam'";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $peminjaman = mysqli_fetch_assoc($result);
        header('Content-Type: application/json');
        echo json_encode($peminjaman);
        exit;
    } else {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Data peminjaman tidak ditemukan']);
        exit;
    }
}

// Pagination settings
$entries_per_page = isset($_GET['entries']) ? (int) $_GET['entries'] : 10;
if (!in_array($entries_per_page, [10, 25, 50])) {
    $entries_per_page = 10;
}
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($current_page - 1) * $entries_per_page;

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$search_condition = $search ? "WHERE b.nama_barang LIKE '%$search%' OR bd.kode_barang LIKE '%$search%' OR u.fullName LIKE '%$search%'" : '';

// Filter status
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
if ($status_filter && in_array($status_filter, ['Menunggu Verifikasi', 'Dipinjam', 'Dikembalikan', 'Hilang'])) {
    $status_condition = $search_condition ? " AND tp.status_peminjaman = '$status_filter'" : "WHERE tp.status_peminjaman = '$status_filter'";
} else {
    $status_condition = '';
}

// Count total rows for pagination
$total_query = "SELECT COUNT(*) AS total FROM t_pinjam tp
                JOIN t_barang_detail bd ON tp.id_detail = bd.id_detail
                JOIN t_barang b ON bd.id_barang = b.id_barang
                JOIN t_user u ON tp.id_nip = u.id_nip
                $search_condition $status_condition";
$total_result = mysqli_query($conn, $total_query);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $entries_per_page);
$range = 2;
$start_page = max(1, $current_page - $range);
$end_page = min($total_pages, $current_page + $range);

// Get peminjaman data with pagination
$query = "SELECT tp.id_pinjam, tp.tanggal_pinjam, tp.tanggal_kembali, tp.status_peminjaman,
          b.nama_barang, bd.kode_barang, u.fullName as nama_peminjam, bd.status as status_barang
          FROM t_pinjam tp
          JOIN t_barang_detail bd ON tp.id_detail = bd.id_detail
          JOIN t_barang b ON bd.id_barang = b.id_barang
          JOIN t_user u ON tp.id_nip = u.id_nip
          $search_condition $status_condition
          ORDER BY 
            CASE 
              WHEN tp.status_peminjaman = 'Menunggu Verifikasi' THEN 1
              WHEN tp.status_peminjaman = 'Dipinjam' THEN 2
              ELSE 3
            END,
            tp.tanggal_pinjam DESC
          LIMIT $offset, $entries_per_page";
$result = mysqli_query($conn, $query);

// Auto-update status terlambat
$update_telat_query = "UPDATE t_pinjam 
                      SET status_peminjaman = 'Telat' 
                      WHERE status_peminjaman = 'Dipinjam' 
                      AND tanggal_kembali < NOW()";
mysqli_query($conn, $update_telat_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iVESTA - Data Pinjam</title>
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
            <a href="#" class="active">
                <i class="fas fa-hand-holding menu-icon"></i>
                <span>Data Pinjam</span>
            </a>
            <a href="dataUser.php">
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
                <span class="navbar-brand">DATA PINJAM</span>
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

        <!-- Data Pinjam Content -->
        <div class="container-fluid">
            <div class="table-container">
                <div class="table-header">
                    <div class="entries-info">
                        Show
                        <select class="form-select form-select-sm d-inline-block w-auto" id="entriesPerPage">

                            <option value="10" <?= $entries_per_page == 10 ? 'selected' : '' ?>>10</option>
                            <option value="25" <?= $entries_per_page == 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $entries_per_page == 50 ? 'selected' : '' ?>>50</option>
                        </select>
                        entries
                    </div>
                    <div class="table-controls d-flex align-items-center gap-2" style="gap: 1rem;">
                        <div class="search-box flex-grow-1">
                            <form method="get" class="d-flex" id="searchForm">
                                <input type="text" name="search" class="form-control form-control-sm"
                                    placeholder="Search..." value="<?= htmlspecialchars($search) ?>" id="searchInput"
                                    autocomplete="off">
                                <input type="hidden" name="page" value="1">
                                <input type="hidden" name="entries" value="<?= $entries_per_page ?>">
                            </form>
                        </div>
                        <div class="filter-box">
                            <select class="form-select form-select-sm filter-select" name="status" id="statusFilter"
                                style="min-width: 140px;">
                                <option value="">Semua Status</option>
                                <option value="Menunggu Verifikasi" <?= $status_filter == 'Menunggu Verifikasi' ? 'selected' : '' ?>>
                                    Menunggu Verifikasi</option>
                                <option value="Dipinjam" <?= $status_filter == 'Dipinjam' ? 'selected' : '' ?>>Dipinjam
                                </option>
                                <option value="Dikembalikan" <?= $status_filter == 'Dikembalikan' ? 'selected' : '' ?>>
                                    Dikembalikan</option>
                                <option value="Hilang" <?= $status_filter == 'Hilang' ? 'selected' : '' ?>>Hilang</option>
                                <option value="Ditolak" <?= $status_filter == 'Ditolak' ? 'selected' : '' ?>>Ditolak
                                </option>
                                <option value="Telat" <?= $status_filter == 'Telat' ? 'selected' : '' ?>>Telat</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover table-fixed">
                        <thead>
                            <tr>
                                <th style="width: 5%" class="text-center">No</th>
                                <th style="width: 10%" class="text-center">Nama</th>
                                <th style="width: 10%" class="text-center">Nama Barang</th>
                                <!-- <th style="width: 15%" class="text-center">Kode Barang</th> -->
                                <th style="width: 15%" class="text-center">Tanggal Pinjam</th>
                                <th style="width: 15%" class="text-center">Tanggal Kembali</th>
                                <th style="width: 15%" class="text-center">Status</th>
                                <th style="width: 15%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody><?php if (mysqli_num_rows($result) > 0): ?>
                                <?php
                                $no = $offset + 1;
                                while ($row = mysqli_fetch_assoc($result)):

                                    $statusClass = '';
                                    switch ($row['status_peminjaman']) {
                                        case 'Menunggu Verifikasi':
                                            $statusClass = 'badge-waiting';
                                            break;
                                        case 'Dipinjam':
                                            $statusClass = 'badge bg-primary';
                                            break;
                                        case 'Dikembalikan':
                                            $statusClass = 'badge bg-success';
                                            break;
                                        case 'Hilang':
                                            $statusClass = 'badge bg-dark';
                                            break;
                                        case 'Ditolak':
                                            $statusClass = 'badge bg-danger';
                                            break;
                                        case 'Telat':
                                            $statusClass = 'badge-waiting';
                                            break;
                                    }
                                    ?>
                                    <tr>
                                        <td class="text-center"><?= $no ?></td>
                                        <td class="text-center"><?= htmlspecialchars($row['nama_peminjam']) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($row['nama_barang']) ?></td>

                                        <td class="text-center">
                                            <?= $row['tanggal_pinjam'] ? date('d M Y H:i', strtotime($row['tanggal_pinjam'])) : '-' ?>
                                        </td>
                                        <td class="text-center">
                                            <?= $row['tanggal_kembali'] ? date('d M Y H:i', strtotime($row['tanggal_kembali'])) : '-' ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= $statusClass ?>">
                                                <?= htmlspecialchars($row['status_peminjaman']) ?>
                                            </span>
                                            <?= $isLate ? '<span class="badge bg-danger">Telat</span>' : '' ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons d-flex justify-content-center">
                                                <button class="btn btn-info btn-sm btn-action" title="Detail"
                                                    data-bs-toggle="modal" data-bs-target="#detailModal"
                                                    data-id="<?= $row['id_pinjam'] ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>

                                                <?php if ($row['status_peminjaman'] == 'Menunggu Verifikasi'): ?>
                                                    <button class="btn btn-success btn-sm btn-action" title="Setujui"
                                                        onclick="updateStatus(<?= $row['id_pinjam'] ?>, 'approve')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-danger btn-sm btn-action" title="Tolak"
                                                        onclick="updateStatus(<?= $row['id_pinjam'] ?>, 'reject')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php elseif ($row['status_peminjaman'] == 'Dipinjam' || $row['status_peminjaman'] == 'Telat'): ?>
                                                    <button class="btn btn-primary btn-sm btn-action" title="Tandai Dikembalikan"
                                                        onclick="updateStatus(<?= $row['id_pinjam'] ?>, 'complete')">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                <?php endif; ?>

                                                <a href="?delete=<?= $row['id_pinjam'] ?>"
                                                    class="btn btn-danger btn-sm btn-action" title="Hapus"
                                                    onclick="return confirm('Apakah Anda yakin ingin menghapus peminjaman ini?')">
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
                                    <td colspan="8" class="text-center py-4">Tidak ada data peminjaman</td>
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
                                    href="?page=<?= $current_page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&entries=<?= $entries_per_page ?>">Previous</a>
                            </li>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?= $i == $current_page ? 'disabled' : '' ?>">
                                    <a class="page-link"
                                        href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&entries=<?= $entries_per_page ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next Button -->
                            <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link"
                                    href="?page=<?= $current_page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&entries=<?= $entries_per_page ?>">Next</a>
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

    <!-- Detail Peminjaman Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailModalLabel">Detail Peminjaman</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detailModalContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
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
            const status = '<?= htmlspecialchars($status_filter) ?>';
            window.location.href = `?page=1&entries=${entries}&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`;
        });

        // Enhanced real-time search functionality
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const tableBody = document.querySelector('tbody');
        const originalTableContent = tableBody.innerHTML;

        // Store current values to prevent unnecessary updates
        let currentSearchValue = searchInput.value;
        let currentStatusValue = statusFilter.value;

        // Debounce function
        let debounceTimer;
        function debounceSearch() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const searchTerm = searchInput.value.trim();
                const statusValue = statusFilter.value;

                // Only proceed if values have changed
                if (searchTerm === currentSearchValue && statusValue === currentStatusValue) {
                    return;
                }

                currentSearchValue = searchTerm;
                currentStatusValue = statusValue;

                // If both search and filter are empty, restore original content
                if (searchTerm === '' && statusValue === '') {
                    tableBody.innerHTML = originalTableContent;
                    return;
                }

                // Fetch new data
                fetch(`?search=${encodeURIComponent(searchTerm)}&status=${encodeURIComponent(statusValue)}&page=1&entries=<?= $entries_per_page ?>`)
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
        statusFilter.addEventListener('change', debounceSearch);

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

        // Filter status change
        document.getElementById('statusFilter').addEventListener('change', function () {
            const status = this.value;
            const search = document.getElementById('searchInput').value;
            const entries = document.getElementById('entriesPerPage').value;

            window.location.href = `?page=1&entries=${entries}&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`;
        });

        // Detail modal handler
        const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
        const detailModalContent = document.getElementById('detailModalContent');

        document.querySelectorAll('[data-bs-target="#detailModal"]').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-id');

                // Show loading spinner
                detailModalContent.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;

                // Fetch data
                fetch(`?get_peminjaman=${id}`)
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        // Format tanggal
                        const tanggalPinjam = new Date(data.tanggal_pinjam).toLocaleString('id-ID', {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });

                        // Tentukan tanggal yang akan ditampilkan
                        let tanggalPengembalian;
                        let labelTanggal = 'Tanggal Kembali';

                        if (data.status_peminjaman == 'Hilang') {
                            labelTanggal = 'Tanggal Hilang';
                            tanggalPengembalian = data.tanggal_hilang
                                ? new Date(data.tanggal_hilang).toLocaleString('id-ID', {
                                    day: '2-digit',
                                    month: 'short',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                })
                                : '-';
                        } else {
                            tanggalPengembalian = data.tanggal_kembali
                                ? new Date(data.tanggal_kembali).toLocaleString('id-ID', {
                                    day: '2-digit',
                                    month: 'short',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                })
                                : '-';
                        }

                        // Format status peminjaman
                        let statusBadge = '';
                        if (data.status_peminjaman == 'Menunggu Verifikasi') {
                            statusBadge = '<span class="badge bg-warning">Menunggu Verifikasi</span>';
                        } else if (data.status_peminjaman == 'Dipinjam') {
                            statusBadge = '<span class="badge bg-primary">Dipinjam</span>';
                        } else if (data.status_peminjaman == 'Dikembalikan') {
                            statusBadge = '<span class="badge bg-success">Dikembalikan</span>';
                        } else if (data.status_peminjaman == 'Hilang') {
                            statusBadge = '<span class="badge bg-dark">Hilang</span>';
                        } else if (data.status_peminjaman == 'Ditolak') {
                            statusBadge = '<span class="badge bg-danger">Ditolak</span>';
                        } else if (data.status_peminjaman == 'Telat') {
                            statusBadge = '<span class="badge bg-warning">Telat</span>';
                        }

                        // Format kondisi
                        let kondisiBadge = '';
                        if (data.kondisi == 'Baik') {
                            kondisiBadge = '<span class="badge bg-success">Baik</span>';
                        } else if (data.kondisi == 'Rusak') {
                            kondisiBadge = '<span class="badge bg-danger">Rusak</span>';
                        } else if (data.kondisi == 'Hilang') {
                            kondisiBadge = '<span class="badge bg-dark">Hilang</span>';
                        }

                        // Update modal content
                        detailModalContent.innerHTML = `
                    <div class="mb-3">
                        <p><strong>Nama Peminjam:</strong> ${data.nama_peminjam}</p>
                        <p><strong>Nama Barang:</strong> ${data.nama_barang}</p>
                        <p><strong>Kode Barang:</strong> ${data.kode_barang}</p>
                        <p><strong>Tanggal Pinjam:</strong> ${tanggalPinjam}</p>
                        <p><strong>${labelTanggal}:</strong> ${tanggalPengembalian}</p>
                        <p><strong>Status Peminjaman:</strong> ${statusBadge}</p>
                        <p><strong>Kondisi Barang:</strong> ${kondisiBadge}</p>
                        <p><strong>Keterangan:</strong> ${data.keterangan || '-'}</p>
                    </div>
                `;
                    })
                    .catch(error => {
                        detailModalContent.innerHTML = `
                    <div class="alert alert-danger">
                        Gagal memuat data: ${error.message}
                    </div>
                `;
                    });
            });
        });

        // Function to update status
        function updateStatus(id, action) {
            let actionText = '';
            switch (action) {
                case 'approve':
                    actionText = 'menyetujui';
                    break;
                case 'complete':
                    actionText = 'menandai sebagai dikembalikan';
                    break;
                case 'reject':
                    actionText = 'menolak';
                    break;
            }

            if (confirm(`Apakah Anda yakin ingin ${actionText} peminjaman ini?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'dataPinjam.php';

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id_pinjam';
                idInput.value = id;
                form.appendChild(idInput);

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = action;
                form.appendChild(actionInput);

                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>

</html>