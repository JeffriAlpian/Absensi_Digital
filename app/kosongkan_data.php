<?php
$msg_status = ""; // Status notifikasi: "", "sukses", "gagal"
$msg_detail = ""; // Pesan error spesifik, cth: "Password salah"

// [DIUBAH] Logika pemrosesan sekarang menggunakan POST dan cek password
if (isset($_POST['hapus_data_dengan_password'])) {
    
    $password_input = $_POST['admin_password'];
    $admin_username = $_SESSION['username']; // Ambil username admin yg login

    // 1. Ambil hash password admin dari database
    // (Gunakan prepared statement untuk keamanan)
    $stmt = $conn->prepare("SELECT password FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
    $stmt->bind_param("s", $admin_username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $msg_status = "gagal";
        $msg_detail = "Gagal memverifikasi akun admin Anda.";
    } else {
        $admin_data = $result->fetch_assoc();
        $admin_password_hash = $admin_data['password'];

        // 2. Verifikasi password
        // !!! PENTING: Ini menggunakan md5() agar konsisten dengan sistem Anda.
        // Ganti ke password_verify() jika Anda sudah upgrade ke password_hash()
        // $is_password_correct = (md5($password_input) === $admin_password_hash);
        
        // --- (Jika Anda sudah upgrade ke password_hash(), gunakan ini) ---
        $is_password_correct = password_verify($password_input, $admin_password_hash);
        
        if ($is_password_correct) {
            // === PASSWORD BENAR ===
            
            $conn->query("SET FOREIGN_KEY_CHECKS=0");

            // Kosongkan tabel
            $conn->query("TRUNCATE TABLE absensi");
            $conn->query("TRUNCATE TABLE hari_libur");
            $conn->query("TRUNCATE TABLE siswa");
            $conn->query("TRUNCATE TABLE kelas");
            $conn->query("TRUNCATE TABLE wali_kelas");
            
            $conn->query("DELETE FROM users WHERE role = 'siswa'");
            
            $qr_files = glob('assets/qr/*.png');
            foreach($qr_files as $file){
              if(is_file($file)) {
                unlink($file);
              }
            }

            $conn->query("SET FOREIGN_KEY_CHECKS=1");

            $msg_status = "sukses"; // Set status sukses
            
            // Arahkan otomatis kembali ke dashboard utama
            echo '<meta http-equiv="refresh" content="3;url=dashboard.php">';

        } else {
            // === PASSWORD SALAH ===
            $msg_status = "gagal";
            $msg_detail = "Password yang Anda masukkan salah!";
        }
    }
}

// Helper untuk tombol Tailwind
$btn_class = "inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2";
$btn_danger = "bg-red-600 hover:bg-red-700 focus:ring-red-500";
$btn_secondary = "bg-gray-600 hover:bg-gray-700 focus:ring-gray-500";
$input_class = "mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm";

?>

<div class="flex-1 p-6">

    <?php if ($msg_status == "sukses") : ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg max-w-lg mx-auto text-center" role="alert">
            <strong class="font-bold text-xl">✅ Berhasil!</strong>
            <p class="block sm:inline text-lg mt-2">Semua data berhasil dihapus. Halaman akan dimuat ulang.</p>
            <p class="mt-4"><a href="dashboard.php" class="font-bold underline text-green-800">Klik di sini</a> jika tidak otomatis.</p>
        </div>

    <?php else : ?>
        
        <?php if ($msg_status == "gagal") : ?>
        <div class="mb-4 p-4 bg-red-100 text-red-700 border border-red-400 rounded-lg max-w-lg mx-auto text-center" role="alert">
            <strong class="font-bold">Gagal!</strong>
            <p><?= htmlspecialchars($msg_detail) ?></p>
        </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-md max-w-lg mx-auto text-center">
            
            <form method="POST" action="?page=kosongkan_data">
                <i class="fa-solid fa-triangle-exclamation text-6xl text-red-500 mb-4"></i>
                
                <h2 class="text-3xl font-bold text-red-600">PERINGATAN!</h2>
                
                <p class="text-gray-700 my-4 text-lg">
                    Ini akan menghapus semua data di tabel:
                    <br><b>absensi</b>, <b>hari_libur</b>, <b>siswa</b>, <b>kelas</b>, <b>wali_kelas</b>, dan <b>akun users siswa</b>.
                </p>
                
                <p class="text-gray-900 font-bold text-xl">
                    Tindakan ini tidak dapat dibatalkan!
                </p>
                
                <p class="my-5 p-3 bg-pink-100 text-pink-700 rounded-md text-base">
                    <i class="fa-solid fa-download mr-1"></i>
                    Silakan <a href="?page=backup_restore" class="font-bold underline hover:text-pink-800">backup data dulu</a>.
                </p>

                <div class="mt-6 text-left">
                    <label for="admin_password" class="block text-sm font-medium text-gray-700">
                        Masukkan password <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> untuk konfirmasi:
                    </label>
                    <input type="password" name="admin_password" id="admin_password" class="<?= $input_class ?>" required>
                </div>
                
                <div class="flex flex-col sm:flex-row justify-center gap-4 mt-6">
                    <button type="submit" name="hapus_data_dengan_password" class="<?= $btn_class ?> <?= $btn_danger ?>">
                        <i class="fa-solid fa-trash-alt mr-2"></i>Ya, Hapus Semua Data
                    </button>
                    <a href="dashboard.php" class="<?= $btn_class ?> <?= $btn_secondary ?>">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>

</div>