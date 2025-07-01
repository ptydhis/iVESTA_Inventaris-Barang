<?php
require "../../koneksi.php";
session_start();

// Jika belum login, baru arahkan ke login
if (!isset($_SESSION['login'])) {
    header("Location: ../../auth/login.php");
    exit;
}

// Jika role bukan pegawai, arahkan ke halaman lain (misal admin dashboard)
if ($_SESSION['role'] !== 'pegawai') {
    header("Location: ../../admin/index.php");
    exit;
}

// Query untuk mendapatkan data peminjaman user saat ini
$id_nip = $_SESSION['id_nip'];
$query_peminjaman = "SELECT p.*, b.nama_barang, d.kode_barang, d.kondisi, d.status 
                    FROM t_pinjam p
                    JOIN t_barang_detail d ON p.id_detail = d.id_detail
                    JOIN t_barang b ON d.id_barang = b.id_barang
                    WHERE p.id_nip = '$id_nip' 
                    AND (p.status_peminjaman = 'Dipinjam' 
                         OR p.status_peminjaman = 'Menunggu Verifikasi'
                         OR p.status_peminjaman = 'Telat')
                    ORDER BY 
                        CASE 
                            WHEN p.status_peminjaman = 'Menunggu Verifikasi' THEN 1
                            WHEN p.status_peminjaman = 'Dipinjam' THEN 2
                            WHEN p.status_peminjaman = 'Telat' THEN 3
                            ELSE 4
                        END,
                        p.tanggal_pinjam DESC";
$result_peminjaman = mysqli_query($conn, $query_peminjaman);

// Auto-update status terlambat
$update_telat_query = "UPDATE t_pinjam 
                      SET status_peminjaman = 'Telat' 
                      WHERE status_peminjaman = 'Dipinjam' 
                      AND tanggal_kembali < NOW()";
mysqli_query($conn, $update_telat_query);

// Handle Pengembalian Barang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kembalikan'])) {
    $id_pinjam = mysqli_real_escape_string($conn, $_POST['id_pinjam']);
    $id_detail = mysqli_real_escape_string($conn, $_POST['id_detail']);
    $kondisi = mysqli_real_escape_string($conn, $_POST['kondisi']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan'] ?? '');

    // Tanggal dari form (sudah dalam format Y-m-d H:i:s)
    $tanggal_rusak = ($kondisi === 'Rusak' && isset($_POST['tanggal_rusak']))
        ? date('Y-m-d H:i:s', strtotime($_POST['tanggal_rusak']))
        : null;
    $tanggal_hilang = ($kondisi === 'Hilang' && isset($_POST['tanggal_hilang']))
        ? date('Y-m-d H:i:s', strtotime($_POST['tanggal_hilang']))
        : null;

    // Tentukan status pengembalian
    $status_peminjaman = ($kondisi === 'Hilang') ? 'Hilang' : 'Dikembalikan';

    // Update status peminjaman - jika kondisi hilang, tanggal_kembali di-set NULL
    $query_kembalikan = "UPDATE t_pinjam SET 
                        status_peminjaman = '$status_peminjaman', 
                        tanggal_kembali = " . ($kondisi === 'Hilang' ? "NULL" : "NOW()") . ",
                        keterangan = '$keterangan'
                        WHERE id_pinjam = '$id_pinjam'";

    if (mysqli_query($conn, $query_kembalikan)) {
        // Update status dan kondisi barang detail
        $status_barang = ($kondisi === 'Hilang') ? 'Hilang' : 'Tersedia';

        // Query update barang detail
        $query_update = "UPDATE t_barang_detail SET 
                        status='$status_barang', 
                        kondisi='$kondisi'";

        // Tambahkan tanggal sesuai kondisi
        if ($kondisi === 'Rusak' && $tanggal_rusak) {
            $query_update .= ", tanggal_rusak='$tanggal_rusak'";
        } elseif ($kondisi === 'Hilang' && $tanggal_hilang) {
            $query_update .= ", tanggal_hilang='$tanggal_hilang'";
        }

        $query_update .= " WHERE id_detail='$id_detail'";

        mysqli_query($conn, $query_update);

        $_SESSION['success'] = "Barang berhasil dikembalikan";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $_SESSION['error'] = "Gagal mengembalikan barang: " . mysqli_error($conn);
        header("Location: " . $_SERVER['PHP_SELF']);
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iVESTA - Peminjaman</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&family=Raleway:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .peminjaman.section {
            padding-bottom: 80px;
        }

        .table-container {
            max-height: 500px;
            overflow-y: auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead {
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }

        .table th,
        .table td {
            text-align: center;
            vertical-align: middle;
        }

        .table th {
            white-space: nowrap;
            background-color: #f8f9fa;
            font-weight: 600;
            padding: 12px 8px;
        }

        .table td {
            padding: 10px 8px;
        }

        .badge {
            font-weight: 500;
            padding: 5px 8px;
            font-size: 0.8rem;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
        }

        .empty-state {
            padding: 40px 20px;
            text-align: center;
        }

        .empty-state i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 15px;
        }

        .section-title h2 {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
            text-align: center;
        }

        .section-title p {
            text-align: center;
            color: #6c757d;
        }

        .badge-waiting {
            background-color: #ffc107;
            color: #000;
        }

        .badge-borrowed {
            background-color: #0d6efd;
            color: #fff;
        }

        .badge-returned {
            background-color: #198754;
            color: #fff;
        }

        .badge-rejected {
            background-color: #dc3545;
            color: #fff;
        }

        .badge-lost {
            background-color: #212529;
            color: #fff;
        }

        /* Style untuk modal pengembalian */
        .modal-return .form-check {
            padding-left: 1.5em;
            margin-bottom: 0.5rem;
        }

        .modal-return .form-check-input {
            margin-top: 0.25rem;
            margin-left: -1.5em;
        }

        .modal-return textarea {
            resize: none;
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
            <a href="/ivesta/pegawai/index.php" class="dashboard-btn">
                <i class="fas fa-home"></i>
                <span>HOME</span>
            </a>

            <div class="menu-divider"></div>

            <!-- Menu Items -->
            <div class="menu-title">
                <span>MENU UTAMA</span>
            </div>

            <a href="Barang.php">
                <i class="fas fa-box menu-icon"></i>
                <span>Barang</span>
            </a>
            <a href="#" class="active">
                <i class="fas fa-hand-holding menu-icon"></i>
                <span>Peminjaman</span>
            </a>
            <a href="Riwayat.php">
                <i class="fas fa-history menu-icon"></i>
                <span>Riwayat</span>
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
                <span class="navbar-brand">PEMINJAMAN</span>
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

                        if ($foto && file_exists($fotoPath) && is_file($fotoPath)) {
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

        <!-- Peminjaman Section -->
        <section id="peminjaman" class="peminjaman section">
            <div class="container" data-aos="fade-up" data-aos-delay="100">
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

                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <?php if (mysqli_num_rows($result_peminjaman) > 0): ?>
                            <div class="table-container">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="5%">No</th>
                                            <th width="15%">Nama Barang</th>
                                            <th width="15%">Kode Barang</th>
                                            <th width="15%">Tanggal Pinjam</th>
                                            <th width="15%">Tanggal Kembali</th> <!-- Kolom baru -->
                                            <th width="15%">Status</th>
                                            <th width="20%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $no = 1;
                                        while ($row = mysqli_fetch_assoc($result_peminjaman)):
                                            // Tambahkan ini untuk menampilkan tanggal kembali
                                            $tanggalKembali = ($row['tanggal_kembali'])
                                                ? date('d M Y H:i', strtotime($row['tanggal_kembali']))
                                                : '-';
                                            // Status styling
                                            $statusClass = '';
                                            $statusText = '';

                                            switch ($row['status_peminjaman']) {
                                                case 'Menunggu Verifikasi':
                                                    $statusClass = 'badge-waiting';
                                                    $statusText = 'Menunggu Verifikasi';
                                                    break;
                                                case 'Dipinjam':
                                                    $statusClass = 'badge bg-primary';
                                                    $statusText = 'Dipinjam';
                                                    break;
                                                case 'Telat':
                                                    $statusClass = 'badge-waiting';
                                                    $statusText = 'Telat';
                                                    break;
                                                case 'Dikembalikan':
                                                    $statusClass = 'badge bg-success';
                                                    $statusText = 'Dikembalikan';
                                                    break;
                                                case 'Ditolak':
                                                    $statusClass = 'badge-rejected';
                                                    $statusText = 'Ditolak';
                                                    break;
                                                case 'Hilang':
                                                    $statusClass = 'badge bg-dark';
                                                    $statusText = 'Hilang';
                                                    break;
                                                default:
                                                    $statusClass = 'bg-secondary';
                                                    $statusText = $row['status_peminjaman'];
                                            }
                                            ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                                <td><?= htmlspecialchars($row['kode_barang']) ?></td>
                                                <td><?= date('d M Y H:i', strtotime($row['tanggal_pinjam'])) ?></td>
                                                <td><?= $tanggalKembali ?></td> <!-- Tampilkan tanggal kembali -->
                                                <td>
                                                    <span class="badge <?= $statusClass ?>">
                                                        <?= htmlspecialchars($statusText) ?>
                                                    </span>
                                                </td>
                                                <td class="action-buttons">
                                                    <button class="btn btn-sm badge bg-primary" data-bs-toggle="modal"
                                                        data-bs-target="#detailPeminjamanModal<?= $row['id_pinjam'] ?>">
                                                        <i class="fas fa-eye"></i> Info
                                                    </button>
                                                    <?php if ($row['status_peminjaman'] === 'Dipinjam' || $row['status_peminjaman'] === 'Telat'): ?>
                                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal"
                                                            data-bs-target="#kembalikanModal"
                                                            data-id-pinjam="<?= $row['id_pinjam'] ?>"
                                                            data-id-detail="<?= $row['id_detail'] ?>">
                                                            <i class="fas fa-undo me-1"></i> Kembalikan
                                                        </button>
                                                    <?php elseif ($row['status_peminjaman'] === 'Menunggu Verifikasi'): ?>
                                                        <button class="btn btn-sm btn-secondary" disabled>
                                                            <i class="fas fa-clock me-1"></i> Menunggu
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>

                                            <!-- Modal Detail Peminjaman -->
                                            <div class="modal fade" id="detailPeminjamanModal<?= $row['id_pinjam'] ?>"
                                                tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-light">
                                                            <h5 class="modal-title">Detail Peminjaman</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row mb-3">
                                                                <div class="col-md-4 fw-bold">Nama Barang</div>
                                                                <div class="col-md-8">
                                                                    <?= htmlspecialchars($row['nama_barang']) ?>
                                                                </div>
                                                            </div>
                                                            <div class="row mb-3">
                                                                <div class="col-md-4 fw-bold">Kode Barang</div>
                                                                <div class="col-md-8">
                                                                    <?= htmlspecialchars($row['kode_barang']) ?>
                                                                </div>
                                                            </div>
                                                            <div class="row mb-3">
                                                                <div class="col-md-4 fw-bold">Tanggal Pinjam</div>
                                                                <div class="col-md-8">
                                                                    <?= date('d M Y H:i', strtotime($row['tanggal_pinjam'])) ?>
                                                                </div>
                                                            </div>
                                                            <div class="row mb-3">
                                                                <div class="col-md-4 fw-bold">Tanggal Kembali</div>
                                                                <div class="col-md-8">
                                                                    <?= ($row['tanggal_kembali'])
                                                                        ? date('d M Y H:i', strtotime($row['tanggal_kembali']))
                                                                        : '-' ?>
                                                                </div>
                                                            </div>
                                                            <div class="row mb-3">
                                                                <div class="col-md-4 fw-bold">Status</div>
                                                                <div class="col-md-8">
                                                                    <span class="badge <?= $statusClass ?>">
                                                                        <?= htmlspecialchars($statusText) ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="row mb-3">
                                                                <div class="col-md-4 fw-bold">Kondisi</div>
                                                                <div class="col-md-8">
                                                                    <span
                                                                        class="badge <?= $row['kondisi'] == 'Baik' ? 'bg-success' : 'bg-danger' ?>">
                                                                        <?= htmlspecialchars($row['kondisi']) ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="row mb-3">
                                                                <div class="col-md-4 fw-bold">Keterangan</div>
                                                                <div class="col-md-8">
                                                                    <?= $row['keterangan'] ? htmlspecialchars($row['keterangan']) : '-' ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Tutup</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state text-center py-5">
                                <i class="fas fa-box-open mb-3"></i>
                                <h5 class="mb-2">Tidak ada peminjaman aktif</h5>
                                <p class="text-muted mb-4">Anda belum meminjam barang apapun</p>
                                <!-- <a href="Barang.php"
                                    class="btn btn-primary d-inline-flex flex-column align-items-center py-2 px-3"
                                    style="width: auto;">
                                    <i class="fas fa-box mb-1" style="font-size: 1.5rem;"></i>
                                    <span>Pinjam Barang</span>
                                </a> -->
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
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

                        if ($foto && file_exists($fotoPath) && is_file($fotoPath)) {
                            echo '<img src="../../assets/profiles/' . htmlspecialchars($foto) . '" alt="Foto Profil" style="width: 100%; height: 100%; object-fit: cover;">';
                        } else {
                            echo strtoupper(substr($_SESSION['fullName'], 0, 1));
                        }
                        ?>
                    </div>

                    <h5><?= htmlspecialchars($_SESSION['fullName']) ?></h5>
                    <p class="text-muted mb-3">
                        <span class="badge bg-success"><?= htmlspecialchars(ucfirst($_SESSION['role'])) ?></span>
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

    <!-- Modal Konfirmasi Pengembalian -->
    <div class="modal fade modal-return" id="kembalikanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title">Konfirmasi Pengembalian</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id_pinjam" id="modal_id_pinjam">
                        <input type="hidden" name="id_detail" id="modal_id_detail">

                        <div class="mb-3">
                            <label class="form-label">Kondisi Barang</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="kondisi" id="kondisiBaik"
                                    value="Baik" checked>
                                <label class="form-check-label" for="kondisiBaik">
                                    Baik
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="kondisi" id="kondisiRusak"
                                    value="Rusak">
                                <label class="form-check-label" for="kondisiRusak">
                                    Rusak
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="kondisi" id="kondisiHilang"
                                    value="Hilang">
                                <label class="form-check-label" for="kondisiHilang">
                                    Hilang
                                </label>
                            </div>
                        </div>

                        <!-- Tanggal Rusak -->
                        <div class="mb-3" id="tanggalRusakContainer" style="display: none;">
                            <label for="tanggal_rusak" class="form-label">Tanggal Rusak</label>
                            <input type="datetime-local" class="form-control" id="tanggal_rusak" name="tanggal_rusak">
                        </div>

                        <!-- Tanggal Hilang -->
                        <div class="mb-3" id="tanggalHilangContainer" style="display: none;">
                            <label for="tanggal_hilang" class="form-label">Tanggal Hilang</label>
                            <input type="datetime-local" class="form-control" id="tanggal_hilang" name="tanggal_hilang">
                        </div>

                        <div class="mb-3">
                            <label for="keterangan" class="form-label">Keterangan (Opsional)</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="kembalikan" class="btn btn-primary">Konfirmasi</button>
                    </div>
                </form>
            </div>
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
        });

        // Menu navigation handling
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', function (e) {
                // For dashboard button, prevent default and handle separately
                if (this.classList.contains('dashboard-btn')) {
                    e.preventDefault();

                    // Remove active class from all links
                    document.querySelectorAll('.sidebar-menu a').forEach(item => {
                        item.classList.remove('active');
                    });

                    // Add active class to clicked link
                    this.classList.add('active');

                    // Navigate to dashboard
                    window.location.href = this.getAttribute('href');
                    return;
                }

                // For other links, allow normal navigation but update active state
                if (this.getAttribute('href') && this.getAttribute('href') !== '#') {
                    // Remove active class from all links
                    document.querySelectorAll('.sidebar-menu a').forEach(item => {
                        item.classList.remove('active');
                    });

                    // Add active class to clicked link
                    this.classList.add('active');

                    // Allow default navigation to proceed
                    return;
                }

                // For links without href, prevent default
                e.preventDefault();
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
            document.getElementById('userDropdown').classList.remove('show');
            new bootstrap.Modal(document.getElementById('profileModal')).show();
        });

        // Periksa jika tombol edit ada
        const editProfileBtn = document.getElementById('editProfileBtn');
        if (editProfileBtn) {
            editProfileBtn.addEventListener('click', function () {
                const profileModal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
                profileModal.hide();
                new bootstrap.Modal(document.getElementById('editProfileModal')).show();
            });
        }

        // Handle modal pengembalian
        const kembalikanModal = document.getElementById('kembalikanModal');
        if (kembalikanModal) {
            kembalikanModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const id_pinjam = button.getAttribute('data-id-pinjam');
                const id_detail = button.getAttribute('data-id-detail');

                document.getElementById('modal_id_pinjam').value = id_pinjam;
                document.getElementById('modal_id_detail').value = id_detail;
            });
        }

        // Fungsi untuk format datetime-local
        function formatDateTimeLocal(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');

            return `${year}-${month}-${day}T${hours}:${minutes}`;
        }

        // Handle kondisi radio buttons
        document.querySelectorAll('input[name="kondisi"]').forEach(radio => {
            radio.addEventListener('change', function () {
                const rusakContainer = document.getElementById('tanggalRusakContainer');
                const hilangContainer = document.getElementById('tanggalHilangContainer');

                if (this.value === 'Rusak') {
                    rusakContainer.style.display = 'block';
                    hilangContainer.style.display = 'none';
                } else if (this.value === 'Hilang') {
                    rusakContainer.style.display = 'none';
                    hilangContainer.style.display = 'block';
                } else {
                    rusakContainer.style.display = 'none';
                    hilangContainer.style.display = 'none';
                }
            });
        });

        // Set waktu lokal saat modal dibuka
        document.getElementById('kembalikanModal').addEventListener('show.bs.modal', function () {
            // Dapatkan waktu lokal (WIB)
            const now = new Date();

            // Format untuk input datetime-local
            const formattedDateTime = formatDateTimeLocal(now);

            // Set nilai default
            document.getElementById('tanggal_rusak').value = formattedDateTime;
            document.getElementById('tanggal_hilang').value = formattedDateTime;
        });
    </script>
</body>

</html>