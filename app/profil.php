<?php
// (Logika PHP Anda di bagian atas sudah benar, saya salin kembali)
$msg = "";

// Ambil data profil
$q = $conn->query("SELECT * FROM profil_sekolah LIMIT 1");
$profil = $q->fetch_assoc();
$profil_id = $profil['id'] ?? 0; // Ambil ID untuk query update

if (isset($_POST['simpan'])) {
    $nama = $_POST['nama'];
    $alamat = $_POST['alamat'];
    $kepala = $_POST['kepala'];
    $nip = $_POST['nip'];

    // Upload logo jika ada
    $logo = $profil['logo']; // Ambil logo lama sebagai default
    if (!empty($_FILES['logo']['name'])) {
        // Pastikan folder uploads ada
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $logo = "logo_" . time() . "." . $ext;
        
        // Hapus logo lama jika ada dan bukan logo default
        if (!empty($profil['logo']) && file_exists($upload_dir . $profil['logo'])) {
             unlink($upload_dir . $profil['logo']);
        }
        
        move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $logo);
    }

    // Gunakan prepared statement untuk keamanan
    $stmt = $conn->prepare("UPDATE profil_sekolah SET 
        nama_sekolah=?, alamat=?, kepala_sekolah=?, nip_kepala=?, logo=?
        WHERE id=?");
    $stmt->bind_param("sssssi", $nama, $alamat, $kepala, $nip, $logo, $profil_id);
    $stmt->execute();

    $msg = "Profil sekolah berhasil diperbarui!";
    
    // Ambil ulang data yang baru
    $q = $conn->query("SELECT * FROM profil_sekolah LIMIT 1");
    $profil = $q->fetch_assoc();
}

// Ubah password admin di tabel users
if (isset($_POST['ubah_password'])) {
    $old_pass = $_POST['old_password']; // Jangan hash dulu
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // Ambil password lama dari database
    // Asumsi admin adalah id=1, sesuaikan jika beda
    $res = $conn->query("SELECT password FROM users WHERE id=1 LIMIT 1"); 
    $row = $res->fetch_assoc();
    $db_pass_hash = $row['password'];

    // Verifikasi password lama
    // Logika Anda menggunakan md5, jadi kita verifikasi dengan md5
    if (md5($old_pass) !== $db_pass_hash) {
        $msg = "<span style='color:red;'>Password lama salah!</span>";
    } elseif ($new_pass !== $confirm_pass) {
        $msg = "<span style='color:red;'>Konfirmasi password baru tidak cocok!</span>";
    } elseif (empty($new_pass)) {
        $msg = "<span style='color:red;'>Password baru tidak boleh kosong!</span>";
    } else {
        $new_pass_md5 = md5($new_pass);
        $conn->query("UPDATE users SET password='$new_pass_md5' WHERE id=1");
        $msg = "<span style='color:green;'>Password berhasil diubah!</span>";
    }
}

// Definisikan helper class Tailwind
$input_class = "mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm";
$btn_class = "w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2";
?>

<div class="flex-1 p-6">

    <?php if ($msg): ?>
        <?php 
            // Cek apakah pesan sukses atau error
            $is_success = strpos($msg, 'berhasil') !== false || strpos($msg, 'green') !== false;
            $alert_class = $is_success 
                ? 'bg-green-100 border border-green-400 text-green-700' 
                : 'bg-red-100 border border-red-400 text-red-700';
        ?>
        <div class="mb-6 px-4 py-3 rounded relative <?php echo $alert_class; ?>" role="alert">
            <span class="block sm:inline"><?php echo strip_tags($msg); // Hapus span style lama ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Profil Sekolah</h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                
                <div>
                    <label for="nama" class="block text-sm font-medium text-gray-700">Nama Sekolah</label>
                    <input type="text" id="nama" name="nama" class="<?= $input_class ?>" value="<?= htmlspecialchars($profil['nama_sekolah']) ?>" required>
                </div>

                <div>
                    <label for="alamat" class="block text-sm font-medium text-gray-700">Alamat</label>
                    <textarea id="alamat" name="alamat" class="<?= $input_class ?>" rows="3" required><?= htmlspecialchars($profil['alamat']) ?></textarea>
                </div>
                
                <div>
                    <label for="kepala" class="block text-sm font-medium text-gray-700">Nama Kepala Sekolah</label>
                    <input type="text" id="kepala" name="kepala" class="<?= $input_class ?>" value="<?= htmlspecialchars($profil['kepala_sekolah']) ?>" required>
                </div>
                
                <div>
                    <label for="nip" class="block text-sm font-medium text-gray-700">NIP Kepala Sekolah</label>
                    <input type="text" id="nip" name="nip" class="<?= $input_class ?>" value="<?= htmlspecialchars($profil['nip_kepala']) ?>" required>
                </div>

                <div>
                    <label for="logo" class="block text-sm font-medium text-gray-700">Logo Sekolah</label>
                    <input type="file" id="logo" name="logo" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100" accept="image/*">
                    <p class="mt-1 text-xs text-gray-500">Kosongkan jika tidak ingin mengubah logo.</p>
                    
                    <?php
                    if (!empty($profil['logo'])) {
                        $logoPath = "uploads/" . $profil['logo'];
                        if (file_exists($logoPath)) {
                            $version = filemtime($logoPath); // Cache-busting
                            echo "<img src='{$logoPath}?v={$version}' alt='Logo Sekolah' class='mt-4 max-w-[150px] border rounded-md p-1 bg-gray-50'>";
                        } else {
                            echo "<p class='mt-2 text-sm text-red-600'>Logo tidak ditemukan (file: {$profil['logo']}).</p>";
                        }
                    }
                    ?>
                </div>

                <button type="submit" name="simpan" class="<?= $btn_class ?> bg-green-600 hover:bg-green-700 focus:ring-green-500">
                    <i class="fa-solid fa-save mr-2"></i>Simpan Profil
                </button>
            </form>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Ubah Password Admin</h2>
            <form method="POST" class="space-y-4">
                
                <div>
                    <label for="old_password" class="block text-sm font-medium text-gray-700">Password Lama</label>
                    <input type="password" id="old_password" name="old_password" class="<?= $input_class ?>" required>
                </div>
                
                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700">Password Baru</label>
                    <input type="password" id="new_password" name="new_password" class="<?= $input_class ?>" required>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">Ulangi Password Baru</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="<?= $input_class ?>" required>
                </div>

                <button type="submit" name="ubah_password" class="<?= $btn_class ?> bg-yellow-500 hover:bg-yellow-600 focus:ring-yellow-400">
                    <i class="fa-solid fa-lock mr-2"></i>Ubah Password
                </button>
            </form>
        </div>