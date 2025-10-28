<?php
// Pastikan hanya admin yang bisa akses
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    echo "<p class='text-red-500 p-4'>Akses ditolak. Anda harus login sebagai admin.</p>";
    exit;
}

$msg = ""; // Untuk notifikasi

// 1. Proses Tambah User Guru
if (isset($_POST['tambah_guru'])) { // [DIUBAH] Nama tombol submit
    $nama     = mysqli_real_escape_string($conn, $_POST['nama']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    // [DIUBAH] Role otomatis 'guru'
    $role     = 'guru'; 

    // Validasi dasar
    if (empty($nama) || empty($username) || empty($password)) {
        $msg = "<div class='mb-4 p-4 bg-red-100 text-red-700 border border-red-400 rounded'>Error: Semua field wajib diisi.</div>";
    } elseif (strlen($password) < 6) {
         $msg = "<div class='mb-4 p-4 bg-red-100 text-red-700 border border-red-400 rounded'>Error: Password minimal 6 karakter.</div>";
    } else {
        // Cek username sudah ada atau belum
        $cek = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $cek->bind_param("s", $username);
        $cek->execute();
        $result = $cek->get_result();

        if ($result->num_rows > 0) {
            $msg = "<div class='mb-4 p-4 bg-red-100 text-red-700 border border-red-400 rounded'>Error: Username '$username' sudah digunakan.</div>";
        } else {
            // Hash password dengan aman
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $conn->prepare("INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nama, $username, $password_hash, $role);
            if ($stmt->execute()) {
                echo "<script>window.location.href = '?page=users&status=tambah_ok';</script>";
                exit;
            } else {
                 $msg = "<div class='mb-4 p-4 bg-red-100 text-red-700 border border-red-400 rounded'>Error: Gagal menyimpan data. " . $stmt->error . "</div>";
            }
        }
    }
}

// 2. Proses Edit User (Admin/Guru)
if (isset($_POST['edit_user'])) { // [DIUBAH] Nama tombol submit
    $id       = intval($_POST['id']);
    $nama     = mysqli_real_escape_string($conn, $_POST['nama']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password']; // Password baru (opsional)

    // [DIUBAH] Validasi dasar (nama dan username hanya wajib jika bukan ID 1)
    if ($id != 1 && (empty($nama) || empty($username))) {
         $msg = "<div class='mb-4 p-4 bg-red-100 text-red-700 border border-red-400 rounded'>Error: Nama dan Username wajib diisi untuk user ini.</div>";
    } else {
        // [DIUBAH] Cek username duplikat hanya jika username diubah dan bukan ID 1
        $query_cek_username = "SELECT id FROM users WHERE username = ? AND id != ?";
        $stmt_cek_username = $conn->prepare($query_cek_username);
        $stmt_cek_username->bind_param("si", $username, $id);
        $stmt_cek_username->execute();
        $result_cek_username = $stmt_cek_username->get_result();

        if ($id != 1 && $result_cek_username->num_rows > 0) {
             $msg = "<div class='mb-4 p-4 bg-red-100 text-red-700 border border-red-400 rounded'>Error: Username '$username' sudah digunakan oleh user lain.</div>";
        } else {
            // Siapkan query update
            $sql_update = "UPDATE users SET ";
            $params = [];
            $types = "";

            // [DIUBAH] Hanya update nama & username jika BUKAN ID 1
            if ($id != 1) {
                $sql_update .= "nama = ?, username = ?, ";
                $params[] = $nama;
                $params[] = $username;
                $types .= "ss";
            }

            // Cek apakah password diisi (untuk direset) - Berlaku untuk SEMUA ID
            if (!empty($password)) {
                 if (strlen($password) < 6) {
                    $msg = "<div class='mb-4 p-4 bg-red-100 text-red-700 border border-red-400 rounded'>Error: Password baru minimal 6 karakter.</div>";
                 } else {
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $sql_update .= "password = ?, ";
                    $params[] = $password_hash;
                    $types .= "s";
                 }
            }

            // Jika ada yang diupdate (selain ID 1 atau password diisi)
            if (($id != 1 || !empty($password)) && empty($msg)) {
                // Hapus koma terakhir dan tambahkan WHERE clause
                $sql_update = rtrim($sql_update, ', ') . " WHERE id = ?";
                $params[] = $id;
                $types .= "i";

                $stmt = $conn->prepare($sql_update);
                // bind_param butuh variabel, jadi kita unpack array $params
                $stmt->bind_param($types, ...$params); 

                if ($stmt->execute()) {
                     echo "<script>window.location.href = '?page=users&status=edit_ok';</script>";
                     exit;
                } else {
                     $msg = "<div class='mb-4 p-4 bg-red-100 text-red-700 border border-red-400 rounded'>Error: Gagal mengupdate data. " . $stmt->error . "</div>";
                }
            } elseif(empty($msg)) {
                // Jika ID=1 dan password kosong, tidak ada yang diubah
                 echo "<script>window.location.href = '?page=users&status=nochange';</script>"; // Redirect tanpa pesan
                 exit;
            }
        }
    }
}


// 3. Proses Hapus User (Admin/Guru)
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);

    // Validasi: Jangan hapus admin utama (ID=1) atau diri sendiri
    $self_id_res = $conn->query("SELECT id FROM users WHERE username = '{$_SESSION['username']}' LIMIT 1");
    $self_id = $self_id_res->fetch_assoc()['id'] ?? 0;

    if ($id == 1) {
        $msg = "<div class='mb-4 p-4 bg-red-100 text-red-700 border border-red-400 rounded'>Error: Super Admin (ID=1) tidak dapat dihapus.</div>";
    } elseif ($id == $self_id) {
         $msg = "<div class='mb-4 p-4 bg-red-100 text-red-700 border border-red-400 rounded'>Error: Anda tidak dapat menghapus akun Anda sendiri.</div>";
    }
    else {
        // [DIUBAH] Bisa hapus admin atau guru
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role IN ('admin', 'guru')"); 
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo "<script>window.location.href = '?page=users&status=hapus_ok';</script>";
            exit;
        } else {
            $msg = "<div class='mb-4 p-4 bg-red-100 text-red-700 border border-red-400 rounded'>Error: Gagal menghapus user. " . $stmt->error . "</div>";
        }
    }
}

// 4. Ambil data user admin dan guru untuk ditampilkan
// [DIUBAH] Ambil role juga
$userList = mysqli_query($conn, "SELECT id, nama, username, role FROM users WHERE role IN ('admin', 'guru') ORDER BY role, nama"); 

// Pesan Sukses (dari redirect)
if(isset($_GET['status'])){
    if($_GET['status'] == 'tambah_ok') $msg = "<div class='mb-4 p-4 bg-green-100 text-green-700 border border-green-400 rounded'>Sukses: User guru baru berhasil ditambahkan.</div>";
    if($_GET['status'] == 'edit_ok') $msg = "<div class='mb-4 p-4 bg-green-100 text-green-700 border border-green-400 rounded'>Sukses: Data user berhasil diperbarui.</div>";
    if($_GET['status'] == 'hapus_ok') $msg = "<div class='mb-4 p-4 bg-green-100 text-green-700 border border-green-400 rounded'>Sukses: User berhasil dihapus.</div>";
    if($_GET['status'] == 'nochange') $msg = "<div class='mb-4 p-4 bg-blue-100 text-blue-700 border border-blue-400 rounded'>Info: Tidak ada perubahan data yang disimpan.</div>"; // [BARU] Info jika tidak ada perubahan
}


// Helper class Tailwind
$input_class = "mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm";
$btn_class = "inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2";
?>

<div class="flex-1 p-6">

    <?= $msg ?>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Tambah User Guru Baru</h2>
        <form method="post" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="nama_tambah" class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                    <input type="text" id="nama_tambah" name="nama" class="<?= $input_class ?>" placeholder="Nama Guru" required>
                </div>
                <div>
                    <label for="username_tambah" class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" id="username_tambah" name="username" class="<?= $input_class ?>" placeholder="Username (untuk login)" required>
                </div>
                <div>
                    <label for="password_tambah" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="password_tambah" name="password" class="<?= $input_class ?>" placeholder="Min. 6 karakter" required>
                </div>
            </div>
            <div class="pt-2">
                <button type="submit" name="tambah_guru" class="<?= $btn_class ?> bg-green-600 hover:bg-green-700 focus:ring-green-500">
                    <i class="fa-solid fa-chalkboard-user mr-2"></i>Tambah Guru 
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b">
             <h3 class="text-lg font-medium leading-6 text-gray-900">Daftar User Admin & Guru</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider w-1/12">No</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Nama</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Username</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Role</th> 
                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider w-1/5">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $no = 1;
                    mysqli_data_seek($userList, 0);
                    if (mysqli_num_rows($userList) == 0) {
                        echo "<tr><td colspan='5' class='px-4 py-4 text-center text-gray-500'>Belum ada data user.</td></tr>";
                    }
                    while ($row = mysqli_fetch_assoc($userList)) {
                        // [BARU] Tentukan style badge role
                        $role_badge = '';
                        if ($row['role'] == 'admin') {
                            $role_badge = '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Admin</span>';
                        } elseif ($row['role'] == 'guru') {
                             $role_badge = '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Guru</span>';
                        }
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700 text-center"><?= $no ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['nama']) ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['username']) ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-center"><?= $role_badge ?></td> 
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-center space-x-2">
                                <button type="button" data-modal-toggle="editModal-<?= $row['id'] ?>" class="<?= $btn_class ?> bg-yellow-500 hover:bg-yellow-600 focus:ring-yellow-400 !py-1.5 !px-3 !text-xs">
                                    Edit
                                </button>
                                <?php 
                                // [DIUBAH] Tombol hapus tidak muncul untuk ID=1 atau diri sendiri
                                $self_id = $_SESSION['user_id'] ?? 0; // Pastikan session user_id ada
                                if ($row['id'] != 1 && $row['id'] != $self_id): 
                                ?>
                                <a href="?page=users&hapus=<?= $row['id'] ?>" class="<?= $btn_class ?> bg-red-600 hover:bg-red-700 focus:ring-red-500 !py-1.5 !px-3 !text-xs" onclick="return confirm('Yakin hapus user <?= htmlspecialchars($row['nama']) ?>?')">
                                    Hapus
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <div id="editModal-<?= $row['id'] ?>" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 hidden" aria-hidden="true">
                            <div class="relative w-full max-w-lg p-4">
                                <div class="relative bg-white rounded-lg shadow-xl">
                                    <form method="post">
                                        <div class="flex items-center justify-between p-4 border-b rounded-t">
                                            <h3 class="text-xl font-semibold text-gray-900">
                                                Edit User: <?= htmlspecialchars($row['nama']) ?> 
                                                (<?= $role_badge ?>) 
                                            </h3>
                                            <button type="button" data-modal-close="editModal-<?= $row['id'] ?>" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center">
                                                <i class="fa-solid fa-times"></i><span class="sr-only">Tutup</span>
                                            </button>
                                        </div>
                                        <div class="p-6 space-y-4">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <div>
                                                <label for="nama_edit_<?= $row['id'] ?>" class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                                                <input type="text" id="nama_edit_<?= $row['id'] ?>" name="nama" class="<?= $input_class ?>" 
                                                       value="<?= htmlspecialchars($row['nama']) ?>" 
                                                       <?php if ($row['id'] == 1) echo 'readonly'; // [DIUBAH] Readonly jika ID=1 ?> 
                                                       required>
                                                <?php if ($row['id'] == 1): ?>
                                                    <p class="mt-1 text-xs text-gray-500">Nama Super Admin tidak dapat diubah.</p>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <label for="username_edit_<?= $row['id'] ?>" class="block text-sm font-medium text-gray-700">Username</label>
                                                <input type="text" id="username_edit_<?= $row['id'] ?>" name="username" class="<?= $input_class ?>" 
                                                       value="<?= htmlspecialchars($row['username']) ?>" 
                                                       <?php if ($row['id'] == 1) echo 'readonly'; // [DIUBAH] Readonly jika ID=1 ?>
                                                       required>
                                                <?php if ($row['id'] == 1): ?>
                                                    <p class="mt-1 text-xs text-gray-500">Username Super Admin tidak dapat diubah.</p>
                                                <?php endif; ?>
                                            </div>
                                             <div>
                                                <label for="password_edit_<?= $row['id'] ?>" class="block text-sm font-medium text-gray-700">Password Baru (Opsional)</label>
                                                <input type="password" id="password_edit_<?= $row['id'] ?>" name="password" class="<?= $input_class ?>" 
                                                       placeholder="Kosongkan jika tidak ingin diubah">
                                                <p class="mt-1 text-xs text-gray-500">Isi hanya jika ingin mereset password user ini.</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-end p-4 space-x-2 border-t border-gray-200 rounded-b">
                                            <button data-modal-close="editModal-<?= $row['id'] ?>" type="button" class="<?= $btn_class ?> bg-gray-500 hover:bg-gray-600 focus:ring-gray-400">Batal</button>
                                            <button type="submit" name="edit_user" class="<?= $btn_class ?> bg-blue-600 hover:bg-blue-700 focus:ring-blue-500">Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php
                        $no++;
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const showModal = (id) => {
        const modal = document.getElementById(id);
        if (modal) modal.classList.remove('hidden');
    };
    const hideModal = (id) => {
        const modal = document.getElementById(id);
        if (modal) modal.classList.add('hidden');
    };
    document.querySelectorAll('[data-modal-toggle]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault(); showModal(btn.getAttribute('data-modal-toggle'));
        });
    });
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault(); hideModal(btn.getAttribute('data-modal-close'));
        });
    });
});
</script>