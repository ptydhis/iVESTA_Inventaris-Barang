<?php
require "../koneksi.php";
session_start();

if (!isset($_SESSION['login']) || !in_array($_SESSION['role'], ['admin', 'kaprodi', 'kaleb'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Handle Edit Profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $uploadPath = __DIR__ . '/../assets/profiles/' . $namaFotoBaru;

            // Pastikan direktori ada dan bisa ditulisi
            if (!is_dir(__DIR__ . '/../assets/profiles/')) {
                mkdir(__DIR__ . '/../assets/profiles/', 0777, true);
            }

            if (move_uploaded_file($tmpFoto, $uploadPath)) {
                // Hapus foto lama jika ada
                if (!empty($_SESSION['fotoP']) && file_exists(__DIR__ . '/../assets/profiles/' . $_SESSION['fotoP'])) {
                    unlink(__DIR__ . '/../assets/profiles/' . $_SESSION['fotoP']);
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

// Query user untuk dashboard
$query_user = "SELECT COUNT(*) AS total FROM t_user";
$user = mysqli_fetch_array(mysqli_query($conn, $query_user));

// Query total barang yang sedang dipinjam
$query_pinjam = "SELECT COUNT(*) AS total_dipinjam FROM t_pinjam WHERE status_peminjaman = 'Dipinjam'";
$pinjam = mysqli_fetch_array(mysqli_query($conn, $query_pinjam));

// Query total barang untuk dashboard
$query_barang = "SELECT COUNT(*) AS total FROM t_barang_detail";
$barang = mysqli_fetch_array(mysqli_query($conn, $query_barang));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iVESTA - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&family=Raleway:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
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
            <a href="#" class="dashboard-btn active">
                <i class="fas fa-tachometer-alt"></i>
                <span>DASHBOARD</span>
            </a>

            <div class="menu-divider"></div>

            <!-- Menu Items -->
            <div class="menu-title">
                <span>MASTER DATA</span>
            </div>

            <a href="masterData/dataBarang.php">
                <i class="fas fa-box menu-icon"></i>
                <span>Data Barang</span>
            </a>
            <a href="masterData/dataPinjam.php">
                <i class="fas fa-hand-holding menu-icon"></i>
                <span>Data Pinjam</span>
            </a>
            <a href="masterData/dataUser.php">
                <i class="fas fa-users menu-icon"></i>
                <span>Data User</span>
            </a>

            <div class="menu-divider"></div>

            <a href="masterData/Maintenance.php">
                <i class="fas fa-tools menu-icon"></i>
                <span>Maintenance</span>
            </a>
            <div class="menu-divider"></div>

            <a href="masterData/Laporan.php">
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
                <span class="navbar-brand">DASHBOARD</span>
                <div class="d-flex align-items-center position-relative">
                    <div class="me-3">
                        <span class="text-muted">Selamat datang,</span>
                        <span class="fw-bold"><?= htmlspecialchars($_SESSION['fullName']) ?></span>
                    </div>
                    <div class="user-profile" id="userProfile"
                        style="overflow: hidden; border-radius: 50%; width: 40px; height: 40px; text-align: center; line-height: 40px; font-weight: bold; color: white; background: #6c757d; cursor: pointer;">
                        <?php
                        $foto = $_SESSION['fotoP'] ?? '';
                        $fotoPath = realpath(__DIR__ . '/../assets/profiles/' . $foto);

                        if ($foto && file_exists($fotoPath) && is_file($fotoPath)) {
                            echo '<img src="../assets/profiles/' . htmlspecialchars($foto) . '" alt="Foto Profil" style="width: 100%; height: 100%; object-fit: cover;">';
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
                        <a href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Dashboard Content -->
        <div class="container-fluid">
            <div class="row g-4 mb-4">
                <!-- Barang Card -->
                <div class="col-md-4">
                    <div class="card-dashboard card text-center p-4">
                        <div class="card-icon text-primary">
                            <i class="fas fa-box"></i>
                        </div>
                        <h5 class="card-title">BARANG</h5>
                        <span class="card-value"><?= $barang['total'] ?></span>
                        <span class="card-text">Total Data Barang</span>
                    </div>
                </div>

                <!-- Pinjam Card -->
                <div class="col-md-4">
                    <div class="card-dashboard card text-center p-4">
                        <div class="card-icon text-primary">
                            <i class="fas fa-hand-holding"></i>
                        </div>
                        <h5 class="card-title">PEMINJAMAN</h5>
                        <span class="card-value"><?= $pinjam['total_dipinjam'] ?></span>
                        <span class="card-text">Total Peminjaman Aktif</span>
                    </div>
                </div>

                <!-- User Card -->
                <div class="col-md-4">
                    <div class="card-dashboard card text-center p-4">
                        <div class="card-icon text-warning">
                            <i class="fas fa-users"></i>
                        </div>
                        <h5 class="card-title">TOTAL USER</h5>
                        <span class="card-value"><?= $user['total'] ?></span>
                        <span class="card-text">Pengguna Terdaftar</span>
                    </div>
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
                        $fotoPath = realpath(__DIR__ . '/../assets/profiles/' . $foto);

                        if ($foto && file_exists($fotoPath) && is_file($fotoPath)) {
                            echo '<img src="../assets/profiles/' . htmlspecialchars($foto) . '" alt="Foto Profil" style="width: 100%; height: 100%; object-fit: cover;">';
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
                        if ($foto && file_exists("../assets/profiles/$foto")) {
                            echo '<img src="../assets/profiles/' . htmlspecialchars($foto) . '" alt="Foto Profil" class="rounded-circle mb-2" style="width:80px;height:80px;object-fit:cover;">';
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
    </script>
</body>

</html>