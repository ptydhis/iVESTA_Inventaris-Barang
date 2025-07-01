<?php
require "../../koneksi.php";
session_start();

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

// Handle Edit Profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['tambah_barang']) && !isset($_POST['edit_barang']) && !isset($_POST['update_detail'])) {
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

// Handle Tambah Barang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_barang'])) {
    $nama_barang = mysqli_real_escape_string($conn, $_POST['nama_barang']);
    $merk = mysqli_real_escape_string($conn, $_POST['merk']);
    $unit = (int) $_POST['unit'];
    $milik = mysqli_real_escape_string($conn, $_POST['milik']);

    // Handle upload foto
    $foto_barang = '';
    if (!empty($_FILES['foto_barang']['name'])) {
        $file = $_FILES['foto_barang'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];

        if (in_array($ext, $allowed) && $file['error'] === UPLOAD_ERR_OK && $file['size'] <= 2 * 1024 * 1024) {
            $foto_barang = uniqid('barang_', true) . '.' . $ext;
            $upload_path = __DIR__ . '/../../assets/barang/' . $foto_barang;

            if (!is_dir(__DIR__ . '/../../assets/barang/')) {
                mkdir(__DIR__ . '/../../assets/barang/', 0777, true);
            }

            move_uploaded_file($file['tmp_name'], $upload_path);
        }
    }

    // Mulai transaksi
    mysqli_begin_transaction($conn);

    try {
        // Insert data utama barang
        $query = "INSERT INTO t_barang (nama_barang, merk, unit, milik, foto_barang) 
                  VALUES ('$nama_barang', '$merk', $unit, '$milik', '$foto_barang')";

        if (!mysqli_query($conn, $query)) {
            throw new Exception("Gagal menambahkan barang: " . mysqli_error($conn));
        }

        $id_barang = mysqli_insert_id($conn);

        // Generate kode barang sesuai unit
        for ($i = 1; $i <= $unit; $i++) {
            $kode_barang = 'K' . strtoupper(substr(uniqid(), 7, 6)) . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
            $kondisi = 'Baik';
            $status = 'Tersedia';

            $query_detail = "INSERT INTO t_barang_detail (id_barang, kode_barang, kondisi, status) 
                            VALUES ($id_barang, '$kode_barang', '$kondisi', '$status')";

            if (!mysqli_query($conn, $query_detail)) {
                throw new Exception("Gagal menambahkan kode barang: " . mysqli_error($conn));
            }
        }

        // Commit transaksi jika semua berhasil
        mysqli_commit($conn);
        $_SESSION['success'] = "Barang berhasil ditambahkan dengan $unit kode barang";
    } catch (Exception $e) {
        // Rollback jika ada error
        mysqli_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: dataBarang.php");
    exit;
}

// Handle Edit Barang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_barang'])) {
    $id_barang = mysqli_real_escape_string($conn, $_POST['id_barang']);
    $nama_barang = mysqli_real_escape_string($conn, $_POST['nama_barang']);
    $merk = mysqli_real_escape_string($conn, $_POST['merk']);
    $unit = (int) $_POST['unit'];
    $milik = mysqli_real_escape_string($conn, $_POST['milik']);

    // Handle upload foto baru
    $update_foto = '';
    if (!empty($_FILES['foto_barang']['name'])) {
        $file = $_FILES['foto_barang'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];

        if (in_array($ext, $allowed) && $file['error'] === UPLOAD_ERR_OK && $file['size'] <= 2 * 1024 * 1024) {
            // Hapus foto lama jika ada
            $query_foto = "SELECT foto_barang FROM t_barang WHERE id_barang = '$id_barang'";
            $result_foto = mysqli_query($conn, $query_foto);
            $row = mysqli_fetch_assoc($result_foto);

            if ($row['foto_barang']) {
                $foto_path = realpath(__DIR__ . '/../../assets/barang/' . $row['foto_barang']);
                if (file_exists($foto_path)) {
                    unlink($foto_path);
                }
            }

            // Upload foto baru
            $foto_barang = uniqid('barang_', true) . '.' . $ext;
            $upload_path = __DIR__ . '/../../assets/barang/' . $foto_barang;
            move_uploaded_file($file['tmp_name'], $upload_path);

            $update_foto = ", foto_barang = '$foto_barang'";
        }
    }

    // Mulai transaksi
    mysqli_begin_transaction($conn);

    try {
        // Update data utama barang
        $query = "UPDATE t_barang SET 
                  nama_barang = '$nama_barang',
                  merk = '$merk',
                  unit = $unit,
                  milik = '$milik'
                  $update_foto
                  WHERE id_barang = '$id_barang'";

        if (!mysqli_query($conn, $query)) {
            throw new Exception("Gagal memperbarui barang: " . mysqli_error($conn));
        }

        // Hitung kode barang yang sudah ada
        $query_count = "SELECT COUNT(*) as total FROM t_barang_detail WHERE id_barang = $id_barang";
        $result_count = mysqli_query($conn, $query_count);
        $row_count = mysqli_fetch_assoc($result_count);
        $existing_count = $row_count['total'];

        // Jika unit lebih besar dari yang ada, tambahkan kode baru
        if ($unit > $existing_count) {
            $to_add = $unit - $existing_count;

            for ($i = 1; $i <= $to_add; $i++) {
                $kode_barang = 'K' . strtoupper(substr(uniqid(), 7, 6)) . '-' . str_pad($existing_count + $i, 3, '0', STR_PAD_LEFT);
                $kondisi = 'Baik';
                $status = 'Tersedia';

                $query_detail = "INSERT INTO t_barang_detail (id_barang, kode_barang, kondisi, status) 
                                VALUES ($id_barang, '$kode_barang', '$kondisi', '$status')";

                if (!mysqli_query($conn, $query_detail)) {
                    throw new Exception("Gagal menambahkan kode barang: " . mysqli_error($conn));
                }
            }
        }
        // Jika unit lebih kecil dari yang ada, hapus kode yang terakhir (dengan status Tersedia)
        elseif ($unit < $existing_count) {
            $to_remove = $existing_count - $unit;

            // Hapus kode barang dengan status Tersedia terlebih dahulu
            $query_delete = "DELETE FROM t_barang_detail 
                            WHERE id_barang = $id_barang AND status = 'Tersedia'
                            ORDER BY id_detail DESC
                            LIMIT $to_remove";

            if (!mysqli_query($conn, $query_delete)) {
                throw new Exception("Gagal menghapus kode barang: " . mysqli_error($conn));
            }

            // Jika masih ada yang perlu dihapus, tampilkan warning
            $deleted_rows = mysqli_affected_rows($conn);
            if ($deleted_rows < $to_remove) {
                $_SESSION['warning'] = "Hanya $deleted_rows kode barang yang bisa dihapus karena ada yang sedang dipinjam";
            }
        }

        // Commit transaksi jika semua berhasil
        mysqli_commit($conn);
        $_SESSION['success'] = "Barang berhasil diperbarui";
    } catch (Exception $e) {
        // Rollback jika ada error
        mysqli_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: dataBarang.php");
    exit;
}

// Handle Update Kondisi dan Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_detail'])) {
    $id_detail = (int) $_POST['id_detail'];
    $kondisi = mysqli_real_escape_string($conn, $_POST['kondisi']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $query = "UPDATE t_barang_detail SET kondisi = '$kondisi', status = '$status' WHERE id_detail = $id_detail";

    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
    exit;
}

// Handle Delete Barang
if (isset($_GET['delete'])) {
    $id_barang = mysqli_real_escape_string($conn, $_GET['delete']);

    // Hapus foto jika ada
    $query_foto = "SELECT foto_barang FROM t_barang WHERE id_barang = '$id_barang'";
    $result_foto = mysqli_query($conn, $query_foto);
    $row = mysqli_fetch_assoc($result_foto);

    if ($row['foto_barang']) {
        $foto_path = realpath(__DIR__ . '/../../assets/barang/' . $row['foto_barang']);
        if (file_exists($foto_path)) {
            unlink($foto_path);
        }
    }

    $query = "DELETE FROM t_barang WHERE id_barang = '$id_barang'";

    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Barang berhasil dihapus";
    } else {
        $_SESSION['error'] = "Gagal menghapus barang: " . mysqli_error($conn);
    }

    header("Location: dataBarang.php");
    exit;
}

// Handle AJAX request for barang data
if (isset($_GET['get_barang'])) {
    $id = mysqli_real_escape_string($conn, $_GET['get_barang']);
    $query = "SELECT * FROM t_barang WHERE id_barang = '$id'";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $barang = mysqli_fetch_assoc($result);
        header('Content-Type: application/json');
        echo json_encode($barang);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Barang tidak ditemukan']);
    }
    exit;
}

// Handle AJAX request for kode barang
if (isset($_GET['id_barang']) && isset($_GET['get_kode'])) {
    $id_barang = (int) $_GET['id_barang'];

    $query = "SELECT id_detail, kode_barang, kondisi, status FROM t_barang_detail 
              WHERE id_barang = $id_barang 
              ORDER BY id_detail ASC";

    $result = mysqli_query($conn, $query);
    $data = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Pagination settings
$entries_per_page = isset($_GET['entries']) ? (int) $_GET['entries'] : 5; // Default 5
if (!in_array($entries_per_page, [5, 10, 25, 50])) {
    $entries_per_page = 5;
}
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($current_page - 1) * $entries_per_page;

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$search_condition = $search ? "WHERE nama_barang LIKE '%$search%' OR merk LIKE '%$search%'" : '';

// Count total rows for pagination
$total_query = "SELECT COUNT(*) AS total FROM t_barang $search_condition";
$total_result = mysqli_query($conn, $total_query);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $entries_per_page);
$range = 2;
$start_page = max(1, $current_page - $range);
$end_page = min($total_pages, $current_page + $range);

// Tambahkan parameter sort di query
$sort_column = isset($_GET['sort']) ? mysqli_real_escape_string($conn, $_GET['sort']) : 'id_barang';
$sort_order = isset($_GET['order']) ? mysqli_real_escape_string($conn, $_GET['order']) : 'DESC';

// Validasi kolom yang bisa di-sort
$allowed_columns = ['id_barang', 'nama_barang', 'merk', 'unit', 'milik'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'id_barang';
}
$sort_order = $sort_order === 'ASC' ? 'ASC' : 'DESC';

// Get barang data with pagination dan sorting
$query = "SELECT * FROM t_barang $search_condition ORDER BY $sort_column $sort_order LIMIT $offset, $entries_per_page";
$resultBarang = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iVESTA - Data Barang</title>
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

            <a href="#" class="active">
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
                <span class="navbar-brand">DATA BARANG</span>
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

        <?php if (isset($_SESSION['warning'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <?= $_SESSION['warning']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['warning']); ?>
        <?php endif; ?>

        <!-- Data Barang Content -->
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
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal"
                            data-bs-target="#tambahBarangModal">
                            <i class="fas fa-plus"></i> Tambah Barang
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover table-fixed">
                        <thead>
                            <tr>
                                <th style="width: 5%" class="text-center">No</th>
                                <th style="width: 15%" class="text-center">Foto</th>
                                <th style="width: 25%" class="text-center">Nama Barang</th>
                                <th style="width: 15%" class="text-center">Merk</th>
                                <th style="width: 10%" class="text-center">Unit</th>
                                <th style="width: 15%" class="text-center">
                                    <div class="d-flex align-items-center justify-content-center">
                                        Milik
                                        <div class="sort-arrows ms-2" data-column="milik">
                                            <i class="fas fa-caret-up sort-asc" title="Sort Ascending"></i>
                                            <i class="fas fa-caret-down sort-desc" title="Sort Descending"></i>
                                        </div>
                                    </div>
                                </th>
                                <th style="width: 15%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($resultBarang) > 0): ?>
                                <?php
                                $no = $offset + 1;
                                while ($row = mysqli_fetch_assoc($resultBarang)):
                                    ?>
                                    <tr>
                                        <td class="text-center"><?= $no ?></td>
                                        <td class="text-center">
                                            <?php if (!empty($row['foto_barang'])): ?>
                                                <img src="../../assets/barang/<?= htmlspecialchars($row['foto_barang']) ?>"
                                                    alt="Foto Barang" class="img-thumbnail mx-auto d-block"
                                                    style="width:80px;height:80px;object-fit:cover;">
                                            <?php else: ?>
                                                <div class="mx-auto"
                                                    style="width:80px;height:80px;border-radius:4px;background:#eee;display:flex;align-items:center;justify-content:center;color:#666;">
                                                    <i class="fas fa-box-open fa-2x"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?= htmlspecialchars($row['nama_barang']) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($row['merk']) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($row['unit']) ?></td>
                                        <td class="text-center">
                                            <?php
                                            $badgeClass = $row['milik'] === 'Prodi' ? 'bg-info' : 'bg-secondary';
                                            ?>
                                            <span class="badge <?= $badgeClass ?>">
                                                <?= htmlspecialchars($row['milik']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons justify-content-center">
                                                <button class="btn btn-primary btn-sm btn-action me-1" title="Edit"
                                                    data-bs-toggle="modal" data-bs-target="#editBarangModal"
                                                    data-id="<?= $row['id_barang'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-info btn-sm btn-action me-1" title="Detail"
                                                    data-bs-toggle="modal" data-bs-target="#detailBarangModal"
                                                    data-id="<?= $row['id_barang'] ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="?delete=<?= $row['id_barang'] ?>"
                                                    class="btn btn-danger btn-sm btn-action" title="Hapus"
                                                    onclick="return confirm('Apakah Anda yakin ingin menghapus barang ini?')">
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
                                    <td colspan="7" class="text-center py-4">Tidak ada data barang</td>
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

                        if ($foto && file_exists($fotoPath) && is_file($fotoPath)) {
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

    <!-- Modal Tambah Barang -->
    <div class="modal fade" id="tambahBarangModal" tabindex="-1" aria-labelledby="tambahBarangModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="post" action="" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahBarangModalLabel">Tambah Barang Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nama_barang" class="form-label">Nama Barang</label>
                        <input type="text" class="form-control" id="nama_barang" name="nama_barang" required>
                    </div>
                    <div class="mb-3">
                        <label for="merk" class="form-label">Merk</label>
                        <input type="text" class="form-control" id="merk" name="merk" required>
                    </div>
                    <div class="mb-3">
                        <label for="unit" class="form-label">Unit</label>
                        <input type="number" class="form-control" id="unit" name="unit" min="1" value="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="milik" class="form-label">Milik</label>
                        <select class="form-select" id="milik" name="milik" required>
                            <option value="Prodi">Prodi</option>
                            <option value="Lab 1">Lab 1</option>
                            <option value="Lab 2">Lab 2</option>
                            <option value="Lab 3">Lab 3</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="foto_barang" class="form-label">Foto Barang</label>
                        <input type="file" class="form-control" id="foto_barang" name="foto_barang" accept="image/*">
                        <small class="text-muted">Format: JPG, JPEG, PNG. Maks 2MB.</small>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="tambah_barang" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Barang -->
    <div class="modal fade" id="editBarangModal" tabindex="-1" aria-labelledby="editBarangModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="post" action="" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBarangModalLabel">Edit Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_barang" id="edit_id_barang">
                    <div class="mb-3">
                        <label for="edit_nama_barang" class="form-label">Nama Barang</label>
                        <input type="text" class="form-control" id="edit_nama_barang" name="nama_barang" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_merk" class="form-label">Merk</label>
                        <input type="text" class="form-control" id="edit_merk" name="merk" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_unit" class="form-label">Unit</label>
                        <input type="number" class="form-control" id="edit_unit" name="unit" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_milik" class="form-label">Milik</label>
                        <select class="form-select" id="edit_milik" name="milik" required>
                            <option value="Prodi">Prodi</option>
                            <option value="Lab 1">Lab 1</option>
                            <option value="Lab 2">Lab 2</option>
                            <option value="Lab 3">Lab 3</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_foto_barang" class="form-label">Foto Barang</label>
                        <input type="file" class="form-control" id="edit_foto_barang" name="foto_barang"
                            accept="image/*">
                        <small class="text-muted">Biarkan kosong jika tidak ingin mengubah foto.</small>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="edit_barang" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detail Barang -->
    <div class="modal fade" id="detailBarangModal" tabindex="-1" aria-labelledby="detailBarangModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailBarangModalLabel">Detail Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <img id="detail_foto" src="" alt="Foto Barang" class="img-thumbnail mb-3"
                                style="max-width:200px;max-height:200px;display:none;">
                            <h5 id="detail_nama_barang"></h5>
                            <p class="text-muted">
                                <span class="badge" id="detail_milik_badge"></span>
                            </p>
                            <div class="text-start mt-3">
                                <p><strong>Merk:</strong> <span id="detail_merk"></span></p>
                                <p><strong>Unit:</strong> <span id="detail_unit"></span></p>
                                <p><strong>Tanggal Input:</strong> <span id="detail_tanggal_input"></span></p>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h6>DAFTAR KODE BARANG</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Kode Barang</th>
                                            <th>Kondisi</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detail_kode_barang_list">
                                        <!-- Daftar kode barang akan diisi oleh JavaScript -->
                                    </tbody>
                                </table>
                            </div>
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

            // Initialize modals
            const editModalEl = document.getElementById('editBarangModal');
            const detailModalEl = document.getElementById('detailBarangModal');
            const editModal = new bootstrap.Modal(editModalEl);
            const detailModal = new bootstrap.Modal(detailModalEl);

            // Ensure modals are fully hidden on close
            [editModalEl, detailModalEl].forEach(modalEl => {
                modalEl.addEventListener('hidden.bs.modal', function () {
                    document.body.classList.remove('modal-open');
                    document.body.style = '';
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(bd => bd.parentNode.removeChild(bd));
                });
            });

            // Handle edit button clicks
            document.querySelectorAll('[data-bs-target="#editBarangModal"]').forEach(btn => {
                btn.addEventListener('click', function () {
                    const id = this.getAttribute('data-id');

                    fetch(`?get_barang=${id}`)
                        .then(response => {
                            if (!response.ok) throw new Error('Barang tidak ditemukan');
                            return response.json();
                        })
                        .then(data => {
                            document.getElementById('edit_id_barang').value = data.id_barang;
                            document.getElementById('edit_nama_barang').value = data.nama_barang;
                            document.getElementById('edit_merk').value = data.merk;
                            document.getElementById('edit_milik').value = data.milik;
                            document.getElementById('edit_unit').value = data.unit;

                            // Hide detail modal if open before showing edit modal
                            if (detailModalEl.classList.contains('show')) {
                                bootstrap.Modal.getInstance(detailModalEl).hide();
                            }
                            editModal.show();
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Gagal memuat data barang');
                        });
                });
            });

            // Handle detail button clicks
            document.querySelectorAll('[data-bs-target="#detailBarangModal"]').forEach(btn => {
                btn.addEventListener('click', function () {
                    const id = this.getAttribute('data-id');

                    // Reset modal content while loading
                    document.getElementById('detail_foto').style.display = 'none';
                    document.getElementById('detail_nama_barang').textContent = 'Memuat...';
                    document.getElementById('detail_kode_barang_list').innerHTML = '<tr><td colspan="4">Memuat data...</td></tr>';

                    // Hide edit modal if open before showing detail modal
                    if (editModalEl.classList.contains('show')) {
                        bootstrap.Modal.getInstance(editModalEl).hide();
                    }
                    detailModal.show();

                    // Load main item data
                    fetch(`?get_barang=${id}`)
                        .then(response => {
                            if (!response.ok) throw new Error('Barang tidak ditemukan');
                            return response.json();
                        })
                        .then(data => {
                            // Set informasi utama
                            document.getElementById('detail_nama_barang').textContent = data.nama_barang;
                            document.getElementById('detail_merk').textContent = data.merk;
                            document.getElementById('detail_unit').textContent = data.unit;

                            // Set milik dengan badge yang sesuai
                            const milikBadge = document.getElementById('detail_milik_badge');
                            milikBadge.textContent = data.milik;
                            milikBadge.className = 'badge ' + (data.milik === 'Prodi' ? 'bg-info' : 'bg-secondary');

                            // Set foto jika ada
                            const fotoElement = document.getElementById('detail_foto');
                            if (data.foto_barang) {
                                fotoElement.src = '../../assets/barang/' + data.foto_barang;
                                fotoElement.style.display = 'block';
                            } else {
                                fotoElement.style.display = 'none';
                            }

                            // Format tanggal
                            const date = new Date(data.tanggal_input);
                            document.getElementById('detail_tanggal_input').textContent =
                                date.toLocaleDateString('id-ID', {
                                    day: 'numeric',
                                    month: 'long',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });

                            // Load kode barang
                            return fetch(`?id_barang=${id}&get_kode=1`);
                        })
                        .then(response => {
                            if (!response.ok) throw new Error('Gagal mengambil kode barang');
                            return response.json();
                        })
                        .then(kodeBarang => {
                            const tableBody = document.getElementById('detail_kode_barang_list');
                            tableBody.innerHTML = '';

                            if (kodeBarang.length === 0) {
                                tableBody.innerHTML = '<tr><td colspan="4">Tidak ada kode barang</td></tr>';
                                return;
                            }

                            kodeBarang.forEach(item => {
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                <td>${item.kode_barang}</td>
                                <td>
                                    <select class="form-select form-select-sm" id="kondisi_${item.id_detail}">
                                        <option value="Baik" ${item.kondisi === 'Baik' ? 'selected' : ''}>Baik</option>
                                        <option value="Rusak" ${item.kondisi === 'Rusak' ? 'selected' : ''}>Rusak</option>
                                        <option value="Hilang" ${item.kondisi === 'Hilang' ? 'selected' : ''}>Hilang</option>
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" id="status_${item.id_detail}">
                                        <option value="Tersedia" ${item.status === 'Tersedia' ? 'selected' : ''}>Tersedia</option>
                                        <option value="Dipinjam" ${item.status === 'Dipinjam' ? 'selected' : ''}>Dipinjam</option>
                                        <option value="Hilang" ${item.status === 'Hilang' ? 'selected' : ''}>Hilang</option>
                                    </select>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="updateDetail(${item.id_detail})">
                                        <i class="fas fa-save"></i> Simpan
                                    </button>
                                </td>
                            `;
                                tableBody.appendChild(row);
                            });
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            document.getElementById('detail_kode_barang_list').innerHTML =
                                '<tr><td colspan="4">Gagal memuat data: ' + error.message + '</td></tr>';
                        });
                });
            });
        });

        // Function untuk update kondisi dan status
        function updateDetail(id_detail) {
            const kondisi = document.getElementById(`kondisi_${id_detail}`).value;
            const status = document.getElementById(`status_${id_detail}`).value;

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `update_detail=1&id_detail=${id_detail}&kondisi=${encodeURIComponent(kondisi)}&status=${encodeURIComponent(status)}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Data berhasil diperbarui');
                    } else {
                        alert('Gagal memperbarui data: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat memperbarui data');
                });
        }

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
                new bootstrap.Modal(document.getElementById('profileModal')).show();
            });
        }

        // Edit profile button handling
        if (document.getElementById('editProfileBtn')) {
            document.getElementById('editProfileBtn').addEventListener('click', function () {
                const profileModal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
                if (profileModal) profileModal.hide();
                new bootstrap.Modal(document.getElementById('editProfileModal')).show();
            });
        }

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

        // Handle sort arrows click
        document.querySelectorAll('.sort-arrows i').forEach(arrow => {
            arrow.addEventListener('click', function () {
                const column = this.closest('.sort-arrows').getAttribute('data-column');
                const isAsc = this.classList.contains('sort-asc');
                const order = isAsc ? 'ASC' : 'DESC';

                // Reset all arrows
                document.querySelectorAll('.sort-arrows i').forEach(i => {
                    i.classList.remove('active');
                });

                // Highlight active arrow
                this.classList.add('active');

                // Reload page with sort parameters
                const url = new URL(window.location.href);
                url.searchParams.set('sort', column);
                url.searchParams.set('order', order);
                window.location.href = url.toString();
            });
        });

        // Highlight active sort arrow on page load
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const sortColumn = urlParams.get('sort') || 'id_barang';
            const sortOrder = urlParams.get('order') || 'DESC';

            const sortArrows = document.querySelector(`.sort-arrows[data-column="${sortColumn}"]`);
            if (sortArrows) {
                const arrow = sortOrder === 'ASC'
                    ? sortArrows.querySelector('.sort-asc')
                    : sortArrows.querySelector('.sort-desc');
                if (arrow) {
                    arrow.classList.add('active');
                }
            }
        });
    </script>
</body>

</html>