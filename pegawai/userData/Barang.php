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

// Handle Pinjam Barang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pinjam_item'])) {
    $id_detail = mysqli_real_escape_string($conn, $_POST['id_detail']);
    $id_nip = $_SESSION['id_nip'];
    $tgl_pinjam = date('Y-m-d H:i:s', strtotime($_POST['tgl_pinjam']));
    $tgl_kembali = date('Y-m-d H:i:s', strtotime($_POST['tgl_kembali']));
    $keperluan = mysqli_real_escape_string($conn, $_POST['keperluan']);

    // Insert data peminjaman dengan status Menunggu Verifikasi
    $query_pinjam = "INSERT INTO t_pinjam (id_detail, id_nip, tanggal_pinjam, tanggal_kembali, jumlah, status_peminjaman, keterangan) 
                     VALUES ('$id_detail', '$id_nip', '$tgl_pinjam', '$tgl_kembali', 1, 'Menunggu Verifikasi', '$keperluan')";

    if (mysqli_query($conn, $query_pinjam)) {
        // Update status barang detail menjadi Menunggu Verifikasi
        $query_update = "UPDATE t_barang_detail SET status='Menunggu Verifikasi' WHERE id_detail='$id_detail'";
        mysqli_query($conn, $query_update);

        $_SESSION['success'] = "Peminjaman berhasil diajukan. Menunggu verifikasi admin.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $_SESSION['error'] = "Gagal mengajukan peminjaman: " . mysqli_error($conn);
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

// Query untuk mendapatkan data barang dengan perhitungan ketersediaan yang benar
$query = "SELECT b.*, 
             (SELECT COUNT(*) FROM t_barang_detail d 
              WHERE d.id_barang = b.id_barang 
              AND d.status = 'Tersedia'
              AND NOT EXISTS (
                  SELECT 1 FROM t_pinjam p 
                  WHERE p.id_detail = d.id_detail 
                  AND p.status_peminjaman IN ('Dipinjam', 'Menunggu Verifikasi')
              )) as tersedia,
             (SELECT COUNT(*) FROM t_barang_detail d WHERE d.id_barang = b.id_barang) as total
          FROM t_barang b";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query error: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iVESTA - Barang</title>
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
            <a href="/ivesta/pegawai/index.php" class="dashboard-btn">
                <i class="fas fa-home"></i>
                <span>HOME</span>
            </a>

            <div class="menu-divider"></div>

            <!-- Menu Items -->
            <div class="menu-title">
                <span>MENU UTAMA</span>
            </div>

            <a href="#" class="active">
                <i class="fas fa-box menu-icon"></i>
                <span>Barang</span>
            </a>
            <a href="Peminjaman.php">
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
                <span class="navbar-brand">BARANG</span>
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

        <!-- Barang Section -->
        <section id="barang" class="barang section">
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

                <div class="row gy-5">
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php
                        $idBarang = $row['id_barang'];
                        $namaBarang = htmlspecialchars($row['nama_barang']);
                        $merk = htmlspecialchars($row['merk']);
                        $unit = $row['unit'];
                        $fotoBarang = htmlspecialchars($row['foto_barang']);
                        $milik = $row['milik'];
                        $tersedia = $row['tersedia'];
                        $total = $row['total'];
                        ?>
                        <div class="col-xl-4 col-md-6" data-aos="zoom-in" data-aos-delay="200">
                            <div class="barang-item">
                                <div class="img">
                                    <?php if ($fotoBarang): ?>
                                        <img src="../../assets/barang/<?= $fotoBarang; ?>" class="img-fluid"
                                            alt="<?= $namaBarang; ?>" style="height: 200px; object-fit: cover;" />
                                    <?php else: ?>
                                        <div class="no-image"
                                            style="height: 200px; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-box-open fa-3x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="details position-relative">
                                    <div class="icon">
                                        <i class="fas fa-<?= $milik == 'Prodi' ? 'building' : 'flask'; ?>"></i>
                                    </div>
                                    <h3>
                                        <a href="#" class="stretched-link" data-bs-toggle="modal"
                                            data-bs-target="#detailBarangModal<?= $idBarang; ?>"></a>
                                        <?= $namaBarang; ?>
                                        </a>
                                    </h3>
                                    <p>
                                        <span class="badge bg-primary"><?= $milik; ?></span>
                                        <span class="badge bg-success">Tersedia: <?= $tersedia; ?>/<?= $total; ?></span>
                                    </p>
                                    <p class="text-muted"><?= $merk; ?></p>
                                    <div class="d-flex justify-content-between mt-2">
                                        <div class="ms-auto">
                                            <button class="btn-detail-custom" data-bs-toggle="modal"
                                                data-bs-target="#detailBarangModal<?= $idBarang; ?>">
                                                <i class="fas fa-eye"></i>
                                                <span>Lihat Detail</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detail Barang Modal -->
                        <div class="modal fade" id="detailBarangModal<?= $idBarang; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Detail Barang</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <?php if ($fotoBarang): ?>
                                                    <img src="../../assets/barang/<?= $fotoBarang; ?>" class="img-fluid rounded"
                                                        style="max-height: 300px;">
                                                <?php else: ?>
                                                    <div class="bg-light p-5 text-center text-muted rounded">
                                                        <i class="fas fa-box-open fa-3x"></i>
                                                        <p class="mt-2">Tidak ada gambar</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-8">
                                                <table class="table table-sm">
                                                    <tr>
                                                        <th width="30%">Nama Barang</th>
                                                        <td><?= $namaBarang; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Merk/Type</th>
                                                        <td><?= $merk; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Milik</th>
                                                        <td><?= $milik; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Unit</th>
                                                        <td><?= $unit; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Stok</th>
                                                        <td>
                                                            <span class="badge bg-success">Tersedia:
                                                                <?= $tersedia; ?></span>
                                                            <span class="badge bg-secondary">Total: <?= $total; ?></span>
                                                        </td>
                                                    </tr>
                                                </table>

                                                <h6 class="mt-4">Detail Item:</h6>
                                                <div class="table-responsive">
                                                    <table class="table table-bordered table-sm text-center">
                                                        <thead>
                                                            <tr>
                                                                <th>Kode Barang</th>
                                                                <th>Kondisi</th>
                                                                <th>Status</th>
                                                                <th>Aksi</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            // Query untuk mendapatkan detail item dengan status sebenarnya
                                                            $query_detail = "
                                                                SELECT 
                                                                    d.id_detail,
                                                                    d.kode_barang,
                                                                    d.kondisi,
                                                                    d.status AS status_barang,
                                                                    p.id_pinjam,
                                                                    p.status_peminjaman,
                                                                    p.tanggal_kembali,
                                                                    CASE 
                                                                        WHEN p.status_peminjaman = 'Dipinjam' AND p.tanggal_kembali < NOW() THEN 'Telat'
                                                                        WHEN p.status_peminjaman = 'Dipinjam' THEN 'Dipinjam'
                                                                        WHEN p.status_peminjaman = 'Menunggu Verifikasi' THEN 'Menunggu Verifikasi'
                                                                        ELSE d.status
                                                                    END AS status_pinjam
                                                                FROM t_barang_detail d
                                                                LEFT JOIN (
                                                                    SELECT *
                                                                    FROM t_pinjam
                                                                    WHERE id_pinjam IN (
                                                                        SELECT MAX(id_pinjam)
                                                                        FROM t_pinjam
                                                                        GROUP BY id_detail
                                                                    )
                                                                ) p ON d.id_detail = p.id_detail
                                                                WHERE d.id_barang = '$idBarang'
                                                            ";
                                                            $result_detail = mysqli_query($conn, $query_detail);

                                                            if (!$result_detail) {
                                                                echo "<tr><td colspan='4'>Error: " . mysqli_error($conn) . "</td></tr>";
                                                            } else {
                                                                while ($row_detail = mysqli_fetch_assoc($result_detail)) {
                                                                    $statusClass = '';
                                                                    $display_status = $row_detail['status_barang']; // Gunakan status dari t_barang_detail untuk tampilan utama
                                                                    $status_pinjam = $row_detail['status_pinjam'] ?? null;

                                                                    // Tentukan class badge
                                                                    switch ($display_status) {
                                                                        case 'Tersedia':
                                                                            $statusClass = 'bg-success';
                                                                            break;
                                                                        case 'Dipinjam':
                                                                            $statusClass = ($status_pinjam === 'Telat') ? 'bg-danger' : 'bg-primary';
                                                                            break;
                                                                        case 'Menunggu Verifikasi':
                                                                            $statusClass = 'badge-waiting';
                                                                            break;
                                                                        case 'Hilang':
                                                                            $statusClass = 'bg-dark';
                                                                            break;
                                                                        default:
                                                                            $statusClass = 'bg-secondary';
                                                                    }

                                                                    $kondisiClass = '';
                                                                    switch ($row_detail['kondisi']) {
                                                                        case 'Baik':
                                                                            $kondisiClass = 'bg-success';
                                                                            break;
                                                                        case 'Rusak':
                                                                            $kondisiClass = 'bg-danger';
                                                                            break;
                                                                        case 'Hilang':
                                                                            $kondisiClass = 'bg-dark';
                                                                            break;
                                                                        default:
                                                                            $kondisiClass = 'bg-secondary';
                                                                    }
                                                                    ?>
                                                                    <tr>
                                                                        <td><?= htmlspecialchars($row_detail['kode_barang']) ?></td>
                                                                        <td>
                                                                            <span class="badge <?= $kondisiClass ?>">
                                                                                <?= htmlspecialchars($row_detail['kondisi']) ?>
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <span class="badge <?= $statusClass ?>">
                                                                                <?= str_replace(' (Telat)', '', htmlspecialchars($display_status)) ?>
                                                                                <?php if ($isLate): ?>
                                                                                    <i class="fas fa-exclamation-triangle ms-1"></i>
                                                                                <?php endif; ?>
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <?php
                                                                            $status_barang = $row_detail['status_barang'];
                                                                            $status_peminjaman = $row_detail['status_peminjaman'] ?? null;
                                                                            $tanggal_kembali = $row_detail['tanggal_kembali'] ?? null;

                                                                            if ($status_barang === 'Tersedia'): ?>
                                                                                <button class="btn btn-sm btn-success"
                                                                                    data-bs-toggle="modal"
                                                                                    data-bs-target="#pinjamItemModal<?= $row_detail['id_detail'] ?>">
                                                                                    <i class="fas fa-hand-holding"></i> Pinjam
                                                                                </button>
                                                                            <?php elseif ($status_barang === 'Menunggu Verifikasi'): ?>
                                                                                <button class="btn btn-sm btn-warning" disabled>
                                                                                    <i class="fas fa-clock"></i> Menunggu
                                                                                </button>
                                                                            <?php elseif ($status_peminjaman === 'Telat'): ?>
                                                                                <span class="badge badge-waiting text-black">
                                                                                    <i class="fas fa-exclamation-triangle"></i> Telat
                                                                                </span>
                                                                            <?php elseif ($status_barang === 'Dipinjam' && $tanggal_kembali): ?>
                                                                                <span class="text-muted">
                                                                                    <i class="far fa-calendar-alt"></i>
                                                                                    Dikembalikan:
                                                                                    <br>
                                                                                    <?= date('d M Y H:i', strtotime($tanggal_kembali)) ?>
                                                                                </span>
                                                                            <?php else: ?>
                                                                                <span class="badge bg-dark">
                                                                                    <?= $status_barang ?>
                                                                                </span>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                    </tr>
                                                                    <?php
                                                                }
                                                            }
                                                            ?>
                                                        </tbody>
                                                    </table>
                                                </div>
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

    <!-- Pinjam Item Modal -->
    <?php
    // Query untuk mendapatkan semua item barang yang tersedia
    $query_all_items = "SELECT d.*, b.nama_barang 
                       FROM t_barang_detail d
                       JOIN t_barang b ON d.id_barang = b.id_barang
                       WHERE d.status = 'Tersedia'";
    $result_all_items = mysqli_query($conn, $query_all_items);

    while ($item = mysqli_fetch_assoc($result_all_items)) {
        $id_detail = $item['id_detail'];
        $nama_barang = htmlspecialchars($item['nama_barang']);
        $kode_barang = htmlspecialchars($item['kode_barang']);
        ?>
        <div class="modal fade" id="pinjamItemModal<?= $id_detail ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="">
                        <input type="hidden" name="pinjam_item" value="1">
                        <input type="hidden" name="id_detail" value="<?= $id_detail ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Form Peminjaman</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Barang</label>
                                <input type="text" class="form-control" value="<?= $nama_barang ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Kode Barang</label>
                                <input type="text" class="form-control" value="<?= $kode_barang ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="tgl_pinjam" class="form-label">Tanggal & Waktu Pinjam</label>
                                <input type="datetime-local" class="form-control" id="tgl_pinjam" name="tgl_pinjam"
                                    required>
                            </div>
                            <div class="mb-3">
                                <label for="tgl_kembali" class="form-label">Tanggal & Waktu Dikembalikan</label>
                                <input type="datetime-local" class="form-control" id="tgl_kembali" name="tgl_kembali"
                                    required>
                            </div>
                            <div class="mb-3">
                                <label for="keperluan" class="form-label">Keperluan</label>
                                <textarea class="form-control" id="keperluan" name="keperluan" rows="3" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Ajukan Peminjaman</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php } ?>

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

        // Update waktu saat modal dibuka dengan waktu lokal
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-bs-target^="#pinjamItemModal"]').forEach(button => {
                button.addEventListener('click', function () {
                    // Cari input tgl_pinjam dan tgl_kembali di dalam modal terkait
                    const modalId = button.getAttribute('data-bs-target');
                    const modal = document.querySelector(modalId);
                    const inputPinjam = modal.querySelector('input[name="tgl_pinjam"]');
                    const inputKembali = modal.querySelector('input[name="tgl_kembali"]');

                    if (inputPinjam && inputKembali) {
                        const now = new Date();
                        const nextWeek = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);

                        // Format untuk input datetime-local: YYYY-MM-DDTHH:MM
                        const formatDateTime = (date) => {
                            const pad = (num) => num.toString().padStart(2, '0');
                            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
                        };

                        inputPinjam.value = formatDateTime(now);
                        inputKembali.value = formatDateTime(nextWeek);
                    }
                });
            });
        });
    </script>
</body>

</html>