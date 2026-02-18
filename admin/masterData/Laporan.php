<?php
require "../../koneksi.php";
session_start();

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

// Handle Print Report
if (isset($_GET['cetak'])) {
    $filter_tanggal_awal = isset($_GET['tanggal_awal']) ? mysqli_real_escape_string($conn, $_GET['tanggal_awal']) : '';
    $filter_tanggal_akhir = isset($_GET['tanggal_akhir']) ? mysqli_real_escape_string($conn, $_GET['tanggal_akhir']) : '';
    $filter_jenis = isset($_GET['jenis_laporan']) ? mysqli_real_escape_string($conn, $_GET['jenis_laporan']) : '';
    $filter_kondisi = isset($_GET['kondisi']) ? mysqli_real_escape_string($conn, $_GET['kondisi']) : '';
    $filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
    $id_item = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';

    // Build conditions for query
    $tanggal_condition = '';
    if ($filter_tanggal_awal && $filter_tanggal_akhir) {
        $tanggal_condition = "AND (";
        if ($filter_jenis === 'barang') {
            $tanggal_condition .= "bd.tanggal_maintenance BETWEEN '$filter_tanggal_awal 00:00:00' AND '$filter_tanggal_akhir 23:59:59'";
        } else {
            $tanggal_condition .= "p.tanggal_pinjam BETWEEN '$filter_tanggal_awal 00:00:00' AND '$filter_tanggal_akhir 23:59:59'";
        }
        $tanggal_condition .= ")";
    } elseif ($filter_tanggal_awal) {
        if ($filter_jenis === 'barang') {
            $tanggal_condition = "AND bd.tanggal_maintenance >= '$filter_tanggal_awal 00:00:00'";
        } else {
            $tanggal_condition = "AND p.tanggal_pinjam >= '$filter_tanggal_awal 00:00:00'";
        }
    } elseif ($filter_tanggal_akhir) {
        if ($filter_jenis === 'barang') {
            $tanggal_condition = "AND bd.tanggal_maintenance <= '$filter_tanggal_akhir 23:59:59'";
        } else {
            $tanggal_condition = "AND p.tanggal_pinjam <= '$filter_tanggal_akhir 23:59:59'";
        }
    }

    // Add kondisi filter for barang
    $kondisi_condition = '';
    if ($filter_jenis === 'barang' && $filter_kondisi) {
        $kondisi_condition = "AND bd.kondisi = '$filter_kondisi'";
    }

    // Add status filter for peminjaman (exclude Menunggu Verifikasi)
    $status_condition = "AND p.status_peminjaman != 'Menunggu Verifikasi'";
    if ($filter_jenis === 'peminjaman' && $filter_status) {
        $status_condition = "AND p.status_peminjaman = '$filter_status'";
    }

    // Get report data for printing
    if ($filter_jenis === 'barang') {
        if ($id_item) {
            $query = "SELECT bd.id_detail, bd.kode_barang, bd.kondisi, bd.tanggal_maintenance, bd.note,
                         b.id_barang, b.nama_barang, b.merk, b.foto_barang, b.milik
                  FROM t_barang_detail bd
                  JOIN t_barang b ON bd.id_barang = b.id_barang
                  WHERE bd.id_detail = '$id_item'";
        } else {
            $query = "SELECT bd.id_detail, bd.kode_barang, bd.kondisi, bd.tanggal_maintenance, bd.note,
                         b.id_barang, b.nama_barang, b.merk, b.foto_barang, b.milik
                  FROM t_barang_detail bd
                  JOIN t_barang b ON bd.id_barang = b.id_barang
                  WHERE 1=1 $tanggal_condition $kondisi_condition
                  ORDER BY b.nama_barang ASC, bd.tanggal_maintenance ASC";
        }
    } else {
        if ($id_item) {
            $query = "SELECT p.id_pinjam, p.tanggal_pinjam, p.tanggal_kembali, p.status_peminjaman as status,
                             bd.kode_barang, b.nama_barang, b.foto_barang,
                             u.fullName AS peminjam
                      FROM t_pinjam p
                      JOIN t_barang_detail bd ON p.id_detail = bd.id_detail
                      JOIN t_barang b ON bd.id_barang = b.id_barang
                      JOIN t_user u ON p.id_nip = u.id_nip
                      WHERE p.id_pinjam = '$id_item'";
        } else {
            $query = "SELECT p.id_pinjam, p.tanggal_pinjam, p.tanggal_kembali, p.status_peminjaman as status,
                             bd.kode_barang, b.nama_barang, b.foto_barang,
                             u.fullName AS peminjam
                      FROM t_pinjam p
                      JOIN t_barang_detail bd ON p.id_detail = bd.id_detail
                      JOIN t_barang b ON bd.id_barang = b.id_barang
                      JOIN t_user u ON p.id_nip = u.id_nip
                      WHERE 1=1 $tanggal_condition $status_condition
                      ORDER BY p.tanggal_pinjam ASC";
        }
    }
    $result = mysqli_query($conn, $query);

    // Get item details for single item print
    $item_title = '';
    if ($id_item) {
        $row = mysqli_fetch_assoc($result);
        if ($filter_jenis === 'barang') {
            $item_title = $row['kondisi'];
        }
        mysqli_data_seek($result, 0); // Reset pointer
    }

    // Generate HTML for printing
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Laporan <?= $filter_jenis === 'barang' ? 'Barang' : 'Peminjaman' ?></title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
            }

            .header {
                text-align: center;
                margin-bottom: 20px;
            }

            .header h2 {
                margin: 0;
            }

            .header p {
                margin: 5px 0 0;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }

            th,
            td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }

            th {
                background-color: #f2f2f2;
            }

            .text-center {
                text-align: center;
            }

            .badge {
                padding: 3px 6px;
                border-radius: 3px;
                font-size: 12px;
                color: white;
            }

            .bg-success {
                background-color: #28a745;
            }

            .bg-danger {
                background-color: #dc3545;
            }

            .bg-warning {
                background-color: #ffc107;
                color: #212529;
            }

            .footer {
                margin-top: 30px;
                text-align: right;
                font-size: 12px;
            }

            .img-thumbnail {
                max-width: 50px;
                max-height: 50px;
            }
        </style>
    </head>

    <body>
        <div class="header">
            <h2>Laporan
                <?= $filter_jenis === 'barang' ? 'Maintenance' . ($item_title ? ' ' . $item_title : '') : 'Peminjaman Barang' ?>
            </h2>
            <p>iVESTA - Inventory System Academic</p>
            <?php if ($filter_tanggal_awal || $filter_tanggal_akhir): ?>
                <p>Periode:
                    <?= $filter_tanggal_awal ? date('d M Y', strtotime($filter_tanggal_awal)) : 'Awal' ?>
                    -
                    <?= $filter_tanggal_akhir ? date('d M Y', strtotime($filter_tanggal_akhir)) : 'Akhir' ?>
                </p>
            <?php endif; ?>
            <?php if ($filter_jenis === 'barang' && $filter_kondisi): ?>
                <p>Kondisi: <?= htmlspecialchars($filter_kondisi) ?></p>
            <?php endif; ?>
            <?php if ($filter_jenis === 'peminjaman' && $filter_status): ?>
                <p>Status: <?= htmlspecialchars($filter_status) ?></p>
            <?php endif; ?>
            <p>Tanggal Cetak: <?= date('d M Y H:i:s') ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <?php if ($filter_jenis === 'barang'): ?>
                        <th>No</th>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Merk</th>
                        <th>Milik</th>
                        <th>Tanggal</th>
                        <th>Keterangan</th>
                        <th>Kondisi</th>
                    <?php else: ?>
                        <th>No</th>
                        <th>Nama Peminjam</th>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Tanggal Pinjam</th>
                        <th>Tanggal Kembali</th>
                        <th>Status</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <?php if ($filter_jenis === 'barang'): ?>
                                <td><?= $no ?></td>
                                <td><?= htmlspecialchars($row['kode_barang']) ?></td>
                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                <td><?= htmlspecialchars($row['merk']) ?></td>
                                <td>
                                    <span class="badge <?= $row['milik'] === 'Prodi' ? 'bg-info' : 'bg-secondary' ?>">
                                        <?= htmlspecialchars($row['milik']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $row['tanggal_maintenance'] ? date('d M Y H:i', strtotime($row['tanggal_maintenance'])) : '-' ?>
                                </td>
                                <td><?= htmlspecialchars($row['note'] ?? '-') ?></td>
                                <td>
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
                                            $badgeClass = 'badge bg-danger';
                                            break;
                                        default:
                                            $badgeClass = 'bg-secondary';
                                    }
                                    ?>
                                    <span class="<?= $badgeClass ?>">
                                        <?= htmlspecialchars($row['kondisi']) ?>
                                    </span>
                                </td>
                            <?php else: ?>
                                <td><?= $no ?></td>
                                <td><?= htmlspecialchars($row['peminjam']) ?></td>
                                <td><?= htmlspecialchars($row['kode_barang']) ?></td>
                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                <td><?= date('d M Y H:i', strtotime($row['tanggal_pinjam'])) ?></td>
                                <td>
                                    <?= $row['tanggal_kembali'] ? date('d M Y H:i', strtotime($row['tanggal_kembali'])) : '-' ?>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = '';
                                    switch ($row['status']) {
                                        case 'Dipinjam':
                                            $badgeClass = 'badge bg-primary';
                                            break;
                                        case 'Dikembalikan':
                                            $badgeClass = 'badge bg-success';
                                            break;
                                        case 'Hilang':
                                            $badgeClass = 'badge bg-danger';
                                            break;
                                        case 'Ditolak':
                                            $badgeClass = 'badge bg-danger';
                                            break;
                                        case 'Telat':
                                            $badgeClass = 'badge bg-primary';
                                            break;
                                        case 'Selesai':
                                            $badgeClass = 'badge bg-secondary';
                                            break;
                                        default:
                                            $badgeClass = 'bg-secondary';
                                    }
                                    ?>
                                    <span class="<?= $badgeClass ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php $no++; ?>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $filter_jenis === 'barang' ? 6 : 7 ?>" class="text-center">
                            Tidak ada data laporan untuk periode ini
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="footer">
            <p>Dicetak oleh: <?= htmlspecialchars($_SESSION['fullName']) ?></p>
        </div>

        <script>
            window.print();
            window.onafterprint = function () {
                window.close();
            };
        </script>
    </body>

    </html>
    <?php
    $html = ob_get_clean();
    echo $html;
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

// Pagination settings
$entries_per_page = isset($_GET['entries']) ? (int) $_GET['entries'] : 10;
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($current_page < 1)
    $current_page = 1;
$offset = ($current_page - 1) * $entries_per_page;

// Filter functionality
$filter_tanggal_awal = isset($_GET['tanggal_awal']) ? mysqli_real_escape_string($conn, $_GET['tanggal_awal']) : '';
$filter_tanggal_akhir = isset($_GET['tanggal_akhir']) ? mysqli_real_escape_string($conn, $_GET['tanggal_akhir']) : '';
$filter_jenis = isset($_GET['jenis_laporan']) ? mysqli_real_escape_string($conn, $_GET['jenis_laporan']) : '';
$filter_kondisi = isset($_GET['kondisi']) ? mysqli_real_escape_string($conn, $_GET['kondisi']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

// Build conditions for query
$tanggal_condition = '';
if ($filter_tanggal_awal && $filter_tanggal_akhir) {
    $tanggal_condition = "AND (";
    if ($filter_jenis === 'barang') {
        $tanggal_condition .= "bd.tanggal_maintenance BETWEEN '$filter_tanggal_awal 00:00:00' AND '$filter_tanggal_akhir 23:59:59'";
    } else {
        $tanggal_condition .= "p.tanggal_pinjam BETWEEN '$filter_tanggal_awal 00:00:00' AND '$filter_tanggal_akhir 23:59:59'";
    }
    $tanggal_condition .= ")";
} elseif ($filter_tanggal_awal) {
    if ($filter_jenis === 'barang') {
        $tanggal_condition = "AND bd.tanggal_maintenance >= '$filter_tanggal_awal 00:00:00'";
    } else {
        $tanggal_condition = "AND p.tanggal_pinjam >= '$filter_tanggal_awal 00:00:00'";
    }
} elseif ($filter_tanggal_akhir) {
    if ($filter_jenis === 'barang') {
        $tanggal_condition = "AND bd.tanggal_maintenance <= '$filter_tanggal_akhir 23:59:59'";
    } else {
        $tanggal_condition = "AND p.tanggal_pinjam <= '$filter_tanggal_akhir 23:59:59'";
    }
}

// Add kondisi filter for barang
$kondisi_condition = '';
if ($filter_jenis === 'barang' && $filter_kondisi) {
    $kondisi_condition = "AND bd.kondisi = '$filter_kondisi'";
}

// Add status filter for peminjaman (exclude Menunggu Verifikasi)
$status_condition = "AND p.status_peminjaman != 'Menunggu Verifikasi'";
if ($filter_jenis === 'peminjaman' && $filter_status) {
    $status_condition = "AND p.status_peminjaman = '$filter_status'";
}

// Get total rows for pagination
if ($filter_jenis === 'barang') {
    $count_query = "SELECT COUNT(*) as total FROM t_barang_detail bd 
                   JOIN t_barang b ON bd.id_barang = b.id_barang
                   WHERE 1=1 $tanggal_condition $kondisi_condition";
} else {
    $count_query = "SELECT COUNT(*) as total FROM t_pinjam p
                   JOIN t_barang_detail bd ON p.id_detail = bd.id_detail
                   JOIN t_barang b ON bd.id_barang = b.id_barang
                   JOIN t_user u ON p.id_nip = u.id_nip
                   WHERE 1=1 $tanggal_condition $status_condition";
}
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $entries_per_page);

// Adjust current page if it exceeds total pages
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $entries_per_page;
}

// Calculate pagination range
$start_page = max(1, $current_page - 2);
$end_page = min($total_pages, $current_page + 2);

// Get report data with pagination
if ($filter_jenis === 'barang') {
    $query = "SELECT bd.id_detail, bd.kode_barang, bd.kondisi, bd.tanggal_maintenance, bd.note,
                 b.id_barang, b.nama_barang, b.merk, b.milik, b.foto_barang
          FROM t_barang_detail bd
          JOIN t_barang b ON bd.id_barang = b.id_barang
          WHERE 1=1 $tanggal_condition $kondisi_condition
          ORDER BY CASE 
            WHEN bd.kondisi = 'Rusak' THEN 1
            WHEN bd.kondisi = 'Hilang' THEN 2
            ELSE 3
          END, bd.tanggal_maintenance DESC
          LIMIT $offset, $entries_per_page";
} else {
    $query = "SELECT p.id_pinjam, p.tanggal_pinjam, p.tanggal_kembali, p.status_peminjaman as status,
                     bd.kode_barang, b.nama_barang, b.foto_barang,
                     u.fullName AS peminjam
              FROM t_pinjam p
              JOIN t_barang_detail bd ON p.id_detail = bd.id_detail
              JOIN t_barang b ON bd.id_barang = b.id_barang
              JOIN t_user u ON p.id_nip = u.id_nip
              WHERE 1=1 $tanggal_condition $status_condition
              ORDER BY p.tanggal_pinjam DESC
              LIMIT $offset, $entries_per_page";
}
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iVESTA - Laporan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&family=Raleway:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .filter-box {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-select {
            width: 150px !important;
        }

        .refresh-btn {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0 5px;
        }

        .refresh-btn:hover {
            color: #0d6efd;
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

            <a href="Maintenance.php">
                <i class="fas fa-tools menu-icon"></i>
                <span>Maintenance</span>
            </a>
            <div class="menu-divider"></div>

            <a href="#" class="active">
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
                <span class="navbar-brand">LAPORAN</span>
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

        <!-- Filter Content -->
        <div class="container-fluid">
            <div class="table-container">
                <div class="table-header d-flex justify-content-between align-items-center mb-3">
                    <!-- Filter Section -->
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <label for="tanggal_awal" class="form-label mb-0">Tanggal Awal:</label>
                            <input type="date" class="form-control form-control-sm" id="tanggal_awal"
                                name="tanggal_awal" style="width: 190px;"
                                value="<?= htmlspecialchars($filter_tanggal_awal) ?>">
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <label for="tanggal_akhir" class="form-label mb-0">Tanggal Akhir:</label>
                            <input type="date" class="form-control form-control-sm" id="tanggal_akhir"
                                name="tanggal_akhir" style="width: 190px;"
                                value="<?= htmlspecialchars($filter_tanggal_akhir) ?>">
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <label for="jenis_laporan" class="form-label mb-0">Jenis:</label>
                            <select class="form-select form-select-sm" name="jenis_laporan" id="jenis_laporan"
                                style="width: 130px;">
                                <option value="">Pilih Jenis</option>
                                <option value="barang" <?= $filter_jenis === 'barang' ? 'selected' : '' ?>>Maintenance
                                </option>
                                <option value="peminjaman" <?= $filter_jenis === 'peminjaman' ? 'selected' : '' ?>>
                                    Peminjaman
                                </option>
                            </select>
                        </div>

                        <button type="button" class="btn btn-primary btn-sm" id="applyFilter">
                            <i class="fas fa-filter"></i> Filter
                        </button>

                        <?php if (!empty($filter_jenis)): ?>
                            <button type="button" class="btn btn-success btn-sm" id="cetakLaporan">
                                <i class="fas fa-print"></i> Cetak Laporan
                            </button>
                            <button type="button" class="refresh-btn" id="refreshBtn" title="Reset Filter">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Laporan Content - Hanya muncul jika sudah memilih jenis laporan -->
        <?php if (!empty($filter_jenis)): ?>
            <div class="container-fluid">
                <div class="table-container">
                    <!-- Additional Filters -->
                    <div class="d-flex justify-content-end mb-3">
                        <?php if ($filter_jenis === 'barang'): ?>
                            <div class="filter-box">
                                <select class="form-select form-select-sm filter-select" name="kondisi" id="filterKondisi">
                                    <option value="">Semua Kondisi</option>
                                    <option value="Baik" <?= $filter_kondisi === 'Baik' ? 'selected' : '' ?>>Baik</option>
                                    <option value="Rusak" <?= $filter_kondisi === 'Rusak' ? 'selected' : '' ?>>Rusak</option>
                                    <option value="Hilang" <?= $filter_kondisi === 'Hilang' ? 'selected' : '' ?>>Hilang</option>
                                </select>
                            </div>
                        <?php else: ?>
                            <div class="filter-box">
                                <select class="form-select form-select-sm filter-select" name="status" id="statusFilter">
                                    <option value="">Semua Status</option>
                                    <option value="Dipinjam" <?= $filter_status == 'Dipinjam' ? 'selected' : '' ?>>Dipinjam
                                    </option>
                                    <option value="Dikembalikan" <?= $filter_status == 'Dikembalikan' ? 'selected' : '' ?>>
                                        Dikembalikan</option>
                                    <option value="Selesai" <?= $filter_status == 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                                    <option value="Hilang" <?= $filter_status == 'Hilang' ? 'selected' : '' ?>>Hilang</option>
                                    <option value="Telat" <?= $filter_status == 'Telat' ? 'selected' : '' ?>>Telat</option>
                                    <option value="Ditolak" <?= $filter_status == 'Ditolak' ? 'selected' : '' ?>>Ditolak</option>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-fixed">
                            <thead>
                                <tr>
                                    <?php if ($filter_jenis === 'barang'): ?>
                                        <th style="width: 5%" class="text-center">No</th>
                                        <th style="width: 10%" class="text-center">Foto</th>
                                        <th style="width: 15%" class="text-center">Kode Barang</th>
                                        <th style="width: 20%" class="text-center">Nama Barang</th>
                                        <th style="width: 15%" class="text-center">Merk</th>
                                        <th style="width: 10%" class="text-center">Milik</th>
                                        <th style="width: 15%" class="text-center">Tanggal</th>
                                        <th style="width: 15%" class="text-center">Keterangan</th>
                                        <th style="width: 10%" class="text-center">Kondisi</th>
                                        <th style="width: 10%" class="text-center">Aksi</th>
                                    <?php else: ?>
                                        <th style="width: 5%" class="text-center">No</th>
                                        <th style="width: 15%" class="text-center">Nama</th>
                                        <th style="width: 15%" class="text-center">Kode Barang</th>
                                        <th style="width: 20%" class="text-center">Nama Barang</th>
                                        <th style="width: 15%" class="text-center">Tanggal Pinjam</th>
                                        <th style="width: 15%" class="text-center">Tanggal Kembali</th>
                                        <th style="width: 10%" class="text-center">Status</th>
                                        <th style="width: 10%" class="text-center">Aksi</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($result) > 0): ?>
                                    <?php $no = $offset + 1; ?>
                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <?php if ($filter_jenis === 'barang'): ?>
                                                <td class="text-center"><?= $no ?></td>
                                                <td class="text-center">
                                                    <?php if (!empty($row['foto_barang'])): ?>
                                                        <img src="../../assets/barang/<?= htmlspecialchars($row['foto_barang']) ?>"
                                                            class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
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
                                                    <span class="badge <?= $row['milik'] === 'Prodi' ? 'bg-info' : 'bg-secondary' ?>">
                                                        <?= htmlspecialchars($row['milik']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <?= $row['tanggal_maintenance'] ? date('d M Y H:i', strtotime($row['tanggal_maintenance'])) : '-' ?>
                                                </td>
                                                <td class="text-center"><?= htmlspecialchars($row['note'] ?? '-') ?></td>
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
                                                    <button class="btn btn-sm btn-outline-primary cetak-item"
                                                        data-id="<?= htmlspecialchars($row['id_detail']) ?>">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                </td>
                                            <?php else: ?>
                                                <td class="text-center"><?= $no ?></td>
                                                <td class="text-center"><?= htmlspecialchars($row['peminjam']) ?></td>
                                                <td class="text-center"><?= htmlspecialchars($row['kode_barang']) ?></td>
                                                <td class="text-center"><?= htmlspecialchars($row['nama_barang']) ?></td>
                                                <td class="text-center"><?= date('d M Y H:i', strtotime($row['tanggal_pinjam'])) ?></td>
                                                <td class="text-center">
                                                    <?= $row['tanggal_kembali'] ? date('d M Y H:i', strtotime($row['tanggal_kembali'])) : '-' ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php
                                                    $badgeClass = '';
                                                    switch ($row['status']) {
                                                        case 'Dipinjam':
                                                            $badgeClass = 'badge bg-primary';
                                                            break;
                                                        case 'Dikembalikan':
                                                            $badgeClass = 'badge bg-success';
                                                            break;
                                                        case 'Hilang':
                                                            $badgeClass = 'badge bg-dark';
                                                            break;
                                                        case 'Ditolak':
                                                            $badgeClass = 'badge bg-danger';
                                                            break;
                                                        case 'Telat':
                                                            $badgeClass = 'badge-waiting';
                                                            break;
                                                        case 'Selesai':
                                                            $badgeClass = 'badge bg-secondary';
                                                            break;
                                                        default:
                                                            $badgeClass = 'bg-secondary';
                                                    }
                                                    ?>
                                                    <span class="badge <?= $badgeClass ?>">
                                                        <?= htmlspecialchars($row['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-outline-primary cetak-item"
                                                        data-id="<?= htmlspecialchars($row['id_pinjam']) ?>">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php $no++; ?>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?= $filter_jenis === 'barang' ? 8 : 8 ?>" class="text-center py-4">
                                            Tidak ada data laporan untuk periode ini
                                        </td>
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
                                        href="?page=<?= $current_page - 1 ?>&entries=<?= $entries_per_page ?>&tanggal_awal=<?= urlencode($filter_tanggal_awal) ?>&tanggal_akhir=<?= urlencode($filter_tanggal_akhir) ?>&jenis_laporan=<?= urlencode($filter_jenis) ?>&kondisi=<?= urlencode($filter_kondisi) ?>&status=<?= urlencode($filter_status) ?>">Previous</a>
                                </li>

                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= $i == $current_page ? 'disabled' : '' ?>">
                                        <a class="page-link"
                                            href="?page=<?= $i ?>&entries=<?= $entries_per_page ?>&tanggal_awal=<?= urlencode($filter_tanggal_awal) ?>&tanggal_akhir=<?= urlencode($filter_tanggal_akhir) ?>&jenis_laporan=<?= urlencode($filter_jenis) ?>&kondisi=<?= urlencode($filter_kondisi) ?>&status=<?= urlencode($filter_status) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Next Button -->
                                <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link"
                                        href="?page=<?= $current_page + 1 ?>&entries=<?= $entries_per_page ?>&tanggal_awal=<?= urlencode($filter_tanggal_awal) ?>&tanggal_akhir=<?= urlencode($filter_tanggal_akhir) ?>&jenis_laporan=<?= urlencode($filter_jenis) ?>&kondisi=<?= urlencode($filter_kondisi) ?>&status=<?= urlencode($filter_status) ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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
                        // Expand parent menu if this is a submenu item
                        const parentMenu = link.closest('.submenu');
                        if (parentMenu) {
                            parentMenu.classList.add('show');
                            const parentLink = parentMenu.previousElementSibling;
                            if (parentLink) {
                                parentLink.classList.add('active');
                            }
                        }
                    }
                }
            });

            // Toggle submenus
            document.querySelectorAll('.has-submenu').forEach(item => {
                item.addEventListener('click', function (e) {
                    if (this.classList.contains('has-submenu')) {
                        e.preventDefault();
                        const submenu = this.nextElementSibling;
                        if (submenu) {
                            submenu.classList.toggle('show');
                        }
                    }
                });
            });
        });

        // User profile dropdown
        document.getElementById('userProfile').addEventListener('click', function (e) {
            e.stopPropagation();
            document.getElementById('userDropdown').classList.toggle('show');
            document.getElementById('dropdownOverlay').classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.getElementById('dropdownOverlay').addEventListener('click', function () {
            document.getElementById('userDropdown').classList.remove('show');
            this.classList.remove('active');
        });

        // Show profile modal
        document.getElementById('showProfileModal').addEventListener('click', function (e) {
            e.preventDefault();
            document.getElementById('userDropdown').classList.remove('show');
            document.getElementById('dropdownOverlay').classList.remove('active');
            const profileModal = new bootstrap.Modal(document.getElementById('profileModal'));
            profileModal.show();
        });

        // Show edit profile modal
        document.getElementById('editProfileBtn').addEventListener('click', function () {
            const profileModal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
            profileModal.hide();
            const editModal = new bootstrap.Modal(document.getElementById('editProfileModal'));
            editModal.show();
        });

        // Apply filter button
        document.getElementById('applyFilter').addEventListener('click', function () {
            const tanggalAwal = document.getElementById('tanggal_awal').value;
            const tanggalAkhir = document.getElementById('tanggal_akhir').value;
            const jenisLaporan = document.getElementById('jenis_laporan').value;

            let url = `?tanggal_awal=${tanggalAwal}&tanggal_akhir=${tanggalAkhir}&jenis_laporan=${jenisLaporan}`;
            window.location.href = url;
        });

        // Additional filter for kondisi/status
        document.getElementById('filterKondisi')?.addEventListener('change', function () {
            applyAdditionalFilter(this.value, 'kondisi');
        });

        document.getElementById('statusFilter')?.addEventListener('change', function () {
            applyAdditionalFilter(this.value, 'status');
        });

        function applyAdditionalFilter(value, type) {
            const params = new URLSearchParams(window.location.search);
            params.set(type, value);
            window.location.search = params.toString();
        }

        // Print report button
        document.getElementById('cetakLaporan')?.addEventListener('click', function () {
            const params = new URLSearchParams(window.location.search);
            params.set('cetak', '1');
            window.open(`?${params.toString()}`, '_blank');
        });

        // Print individual item
        document.querySelectorAll('.cetak-item').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const params = new URLSearchParams(window.location.search);
                params.set('cetak', '1');
                params.set('id', id);
                window.open(`?${params.toString()}`, '_blank');
            });
        });

        // Refresh button to reset filters
        document.getElementById('refreshBtn')?.addEventListener('click', function () {
            window.location.href = window.location.pathname;
        });

        // Validate date range
        document.getElementById('tanggal_akhir').addEventListener('change', function () {
            const tanggalAwal = document.getElementById('tanggal_awal').value;
            const tanggalAkhir = this.value;

            if (tanggalAwal && tanggalAkhir && new Date(tanggalAwal) > new Date(tanggalAkhir)) {
                alert('Tanggal akhir tidak boleh sebelum tanggal awal');
                this.value = '';
            }
        });

        document.getElementById('tanggal_awal').addEventListener('change', function () {
            const tanggalAwal = this.value;
            const tanggalAkhir = document.getElementById('tanggal_akhir').value;

            if (tanggalAwal && tanggalAkhir && new Date(tanggalAwal) > new Date(tanggalAkhir)) {
                alert('Tanggal awal tidak boleh setelah tanggal akhir');
                this.value = '';
            }
        });
    </script>
</body>

</html>