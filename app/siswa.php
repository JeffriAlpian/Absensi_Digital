<?php
require 'vendor/phpqrcode/qrlib.php';

/* ==== Tambah kolom no_wa di tabel siswa (jalankan sekali di phpMyAdmin) ====
ALTER TABLE siswa ADD no_wa VARCHAR(20) AFTER kelas;
============================================================================= */

/* ==== Buat tabel users jika belum ada ====
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  password VARCHAR(100) NOT NULL,
  role ENUM('admin','siswa') DEFAULT 'siswa'
);
============================================================================= */

// Proses simpan (tambah baru)
if (isset($_POST['simpan'])) {
  $nis   = mysqli_real_escape_string($conn, $_POST['nis']);
  $nisn  = mysqli_real_escape_string($conn, $_POST['nisn']);
  $nama  = mysqli_real_escape_string($conn, $_POST['nama']);
  // [DIUBAH] Ambil id_kelas (angka)
  $id_kelas = intval($_POST['id_kelas']);
  $no_wa = mysqli_real_escape_string($conn, $_POST['no_wa']);

  // [DIUBAH] Simpan id_kelas, bukan 'kelas'
  mysqli_query($conn, "INSERT INTO siswa (nis, nisn, nama, id_kelas, no_wa, status) 
                         VALUES ('$nis', '$nisn', '$nama', $id_kelas, '$no_wa', 'aktif')");

  // Buat akun user untuk siswa
  $username = $nisn;
  $password = password_hash($nisn, PASSWORD_BCRYPT); // Diganti ke hash yang aman
  $role     = 'siswa';

  $cek_user = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' LIMIT 1");
  if (mysqli_num_rows($cek_user) == 0) {
    mysqli_query($conn, "INSERT INTO users (username, nama, password, role) 
                             VALUES ('$username', '$nama', '$password', '$role')");
  }

  // Generate QR Code
  $qr_dir = "assets/qr/";
  if (!is_dir($qr_dir)) mkdir($qr_dir, 0777, true);
  QRcode::png($nisn, $qr_dir . "$nisn.png", QR_ECLEVEL_L, 4);

  echo "<script>window.location.href = '?page=siswa';</script>";
  exit;
}

// Proses update data (edit)
if (isset($_POST['update'])) {
  $id    = intval($_POST['id']);
  $nis   = mysqli_real_escape_string($conn, $_POST['nis']);
  $nisn  = mysqli_real_escape_string($conn, $_POST['nisn']);
  $nama  = mysqli_real_escape_string($conn, $_POST['nama']);
  // [DIUBAH] Ambil id_kelas (angka)
  $id_kelas = intval($_POST['id_kelas']);
  $no_wa = mysqli_real_escape_string($conn, $_POST['no_wa']);

  $res_old = mysqli_query($conn, "SELECT nisn FROM siswa WHERE id=$id LIMIT 1");
  $old     = mysqli_fetch_assoc($res_old);
  $old_nisn = $old['nisn'] ?? $nisn;

  // [DIUBAH] Update id_kelas, bukan 'kelas'
  mysqli_query($conn, "UPDATE siswa 
                         SET nis='$nis', nisn='$nisn', nama='$nama', id_kelas=$id_kelas, no_wa='$no_wa' 
                         WHERE id=$id");

  $new_password = password_hash($nisn, PASSWORD_BCRYPT); // Diganti ke hash yang aman
  mysqli_query($conn, "UPDATE users 
                         SET username='$nisn', nama='$nama', password='$new_password' 
                         WHERE username='$old_nisn' AND role='siswa'");

  $qr_dir = "assets/qr/";
  if (!is_dir($qr_dir)) mkdir($qr_dir, 0777, true);
  QRcode::png($nisn, $qr_dir . "$nisn.png", QR_ECLEVEL_L, 4);

  echo "<script>window.location.href = '?page=siswa';</script>";
  exit;
}

// (Logika 'keluar' dan 'generate_akun' Anda sudah benar, 
// hanya saja 'generate_akun' masih menggunakan md5. Saya biarkan dulu.)
// ... (Logika 'keluar' dan 'generate_akun' ada di sini)...

// Ambil data untuk edit jika ada
$edit_data = null;
if (isset($_GET['edit'])) {
  $id = intval($_GET['edit']);
  // [DIUBAH] Query select * sudah otomatis mengambil id_kelas
  $res = mysqli_query($conn, "SELECT * FROM siswa WHERE id=$id LIMIT 1");
  $edit_data = mysqli_fetch_assoc($res);
}

// [BARU] Ambil daftar kelas dari tabel 'kelas' untuk dropdown
$kelasList = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");

// Helper untuk tombol Tailwind
$btn_class = "inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2";
$btn_primary = "bg-green-600 hover:bg-green-700 focus:ring-green-500";
$btn_warning = "bg-yellow-500 hover:bg-yellow-600 focus:ring-yellow-400";
$btn_secondary = "bg-gray-600 hover:bg-gray-700 focus:ring-gray-500";
$btn_dark = "bg-gray-800 hover:bg-gray-900 focus:ring-gray-700";
$btn_danger_outline = "";
$btn_success = "bg-blue-600 hover:bg-blue-700 focus:ring-blue-500"; // Sukses = Biru
$btn_info = "bg-cyan-500 hover:bg-cyan-600 focus:ring-cyan-400";

// Helper untuk input form
$input_class = "mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm";
?>

<div class="flex-1 p-6">

  <?php if ($user_role === 'admin') : ?>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
      <h2 class="text-xl font-bold text-gray-800 mb-4">
        <?= $edit_data ? 'Edit Data Siswa' : 'Tambah Siswa Baru' ?>
      </h2>
      <form method="post" class="flex flex-wrap -mx-2 space-y-4 md:space-y-0">
        <input type="hidden" name="id" value="<?= $edit_data['id'] ?? '' ?>">

        <div class="w-1/2 md:w-1/6 px-2">
          <label for="nis" class="block text-sm font-medium text-gray-700">NIS</label>
          <input type="number" id="nis" name="nis" class="<?= $input_class ?>" placeholder="NIS" required value="<?= htmlspecialchars($edit_data['nis'] ?? '') ?>">
        </div>
        <div class="w-1/2 md:w-1/6 px-2">
          <label for="nisn" class="block text-sm font-medium text-gray-700">NISN</label>
          <input type="number" id="nisn" name="nisn" class="<?= $input_class ?>" placeholder="NISN" required value="<?= htmlspecialchars($edit_data['nisn'] ?? '') ?>">
        </div>
        <div class="w-full md:w-1/3 px-2">
          <label for="nama" class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
          <input type="text" id="nama" name="nama" class="<?= $input_class ?>" placeholder="Nama Siswa" required value="<?= htmlspecialchars($edit_data['nama'] ?? '') ?>">
        </div>
        <div class="w-1/2 md:w-1/6 px-2">
          <label for="id_kelas" class="block text-sm font-medium text-gray-700">Kelas</label>
          <select id="id_kelas" name="id_kelas" class="<?= $input_class ?>" required>
            <option value="">-- Pilih Kelas --</option>
            <?php
            $current_kelas_id = $edit_data['id_kelas'] ?? 0;
            mysqli_data_seek($kelasList, 0); // Reset pointer
            while ($k = mysqli_fetch_assoc($kelasList)) {
              $selected = ($k['id'] == $current_kelas_id) ? 'selected' : '';
              echo "<option value='{$k['id']}' $selected>" . htmlspecialchars($k['nama_kelas']) . "</option>";
            }
            ?>
          </select>
        </div>
        <div class="w-1/2 md:w-1/6 px-2">
          <label for="no_wa" class="block text-sm font-medium text-gray-700">No. WhatsApp</label>
          <input type="text" id="no_wa" name="no_wa" class="<?= $input_class ?>" placeholder="628xxxx" value="<?= htmlspecialchars($edit_data['no_wa'] ?? '') ?>">
        </div>

        <div class="w-full md:w-auto px-2 flex-grow md:flex-grow-0 pt-4 md:pt-0 md:self-end">
          <?php if ($edit_data): ?>
            <div class="flex gap-2">
              <button type="submit" name="update" class="<?= $btn_class ?> <?= $btn_warning ?> w-full">
                <i class="fa-solid fa-save mr-2"></i>Update
              </button>
              <a href="?page=siswa" class="<?= $btn_class ?> <?= $btn_secondary ?> w-full">Batal</a>
            </div>
          <?php else: ?>
            <button type="submit" name="simpan" class="<?= $btn_class ?> <?= $btn_primary ?> w-full">
              <i class="fa-solid fa-plus mr-2"></i>Simpan
            </button>
          <?php endif; ?>
        </div>
      </form>
    </div>

  <?php endif; ?>



  <div class="flex flex-wrap gap-2 mb-6">
    <form method="post" onsubmit="return confirm('Yakin ingin generate akun untuk semua siswa aktif yang belum punya akun?')">
      <?php if ($user_role === 'admin') : ?>
        <button type="submit" name="generate_akun" class="<?= $btn_class ?> <?= $btn_dark ?>">
          <i class="fa-solid fa-bolt mr-2"></i>Generate Akun Siswa
        </button>
      <?php endif; ?>
    </form>
    <a href="app/cetak_kartu.php" class="<?= $btn_class ?> <?= $btn_success ?>" target="_blank">
      <i class="fa-solid fa-id-card mr-2"></i>Cetak Semua Kartu
    </a>
    <?php if ($user_role === 'admin') : ?>
      <a href="?page=import_siswa" class="<?= $btn_class ?> <?= $btn_success ?>">
        <i class="fa-solid fa-file-excel mr-2"></i>Import dari Excel
      </a>
      <a href="?page=siswa_keluar" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 bg-white text-red-600 border border-red-600 hover:bg-red-50">
        <i class="fa-solid fa-user-minus mr-2"></i>Lihat Siswa Keluar
      </a>
    <?php endif; ?>
  </div>


  <div class="mb-4">
    <label for="searchSiswa" class="sr-only">Cari Siswa</label>
    <div class="relative rounded-md shadow-sm">
      <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
        <i class="fa-solid fa-search text-gray-400"></i>
      </div>
      <input type="text" id="searchSiswa" name="searchSiswa"
        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-green-500 focus:border-green-500 sm:text-sm"
        placeholder="Cari berdasarkan Nama, NIS, atau NISN...">
    </div>
    <div id="loadingIndicator" class="mt-2 text-sm text-gray-500 hidden">
      <i class="fa-solid fa-spinner fa-spin mr-1"></i> Mencari...
    </div>
  </div>


  <div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-100">
          <tr class="text-center">
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">NIS</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">NISN</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Nama</th>
            <th class="px-4 py-3 text-xs font-bold text-gray-600 uppercase tracking-wider">Kelas</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">No WA</th>
            <th class="px-4 py-3 text-xs font-bold text-gray-600 uppercase tracking-wider">QR Code</th>
            <?php if ($user_role === 'admin'): ?>
              <th class="px-4 py-3 text-xs font-bold text-gray-600 uppercase tracking-wider">Aksi</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody id="siswaTableBody" class="bg-white divide-y divide-gray-200">
          <?php
          // Query awal untuk menampilkan semua siswa (bisa dipindahkan ke cari_siswa.php)
          $q = mysqli_query($conn, "SELECT s.*, k.nama_kelas
                                             FROM siswa s
                                             LEFT JOIN kelas k ON s.id_kelas = k.id
                                             WHERE s.status='aktif'
                                             ORDER BY s.nama ASC LIMIT 50"); // Batasi jumlah awal
          if (mysqli_num_rows($q) == 0) {
            echo "<tr><td colspan='" . ($user_role === 'admin' ? 7 : 6) . "' class='px-4 py-4 text-center text-gray-500'>Tidak ada data siswa aktif.</td></tr>";
          }
          while ($row = mysqli_fetch_assoc($q)) {
            $qr_file = "assets/qr/{$row['nisn']}.png";
            $qr_src = file_exists($qr_file) ? $qr_file : 'assets/qr/default.png';
          ?>
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['nis']) ?></td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['nisn']) ?></td>
              <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['nama']) ?></td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700 text-center"><?= htmlspecialchars($row['nama_kelas'] ?? 'N/A') ?></td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['no_wa'] ?? '') ?></td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-center">
                <a href="<?= $qr_src ?>" target="_blank" title="Lihat QR Code">
                  <img src="<?= $qr_src ?>" width="40" class="mx-auto border rounded">
                </a>
              </td>
              <?php if ($user_role === 'admin'): ?>
                <td class="px-4 py-2 whitespace-nowrap text-sm text-center space-x-2">
                  <a href="?page=siswa&edit=<?= $row['id'] ?>" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md shadow-sm text-white <?= $btn_info ?>">Edit</a>
                  <a href="?page=siswa&keluar=<?= $row['id'] ?>" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md shadow-sm text-white <?= $btn_warning ?>" onclick="return confirm('Yakin siswa ini keluar/lulus?')">Keluar</a>
                </td>
              <?php endif; ?>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const searchInput = document.getElementById('searchSiswa');
      const tableBody = document.getElementById('siswaTableBody');
      const loadingIndicator = document.getElementById('loadingIndicator');
      let searchTimeout;

      searchInput.addEventListener('keyup', () => {
        // Hapus timeout sebelumnya jika ada (debouncing)
        clearTimeout(searchTimeout);
        loadingIndicator.classList.remove('hidden'); // Tampilkan loading

        // Set timeout baru
        searchTimeout = setTimeout(() => {
          const searchTerm = searchInput.value.trim();
          fetchSiswa(searchTerm);
        }, 500); // Tunggu 500ms setelah user berhenti mengetik
      });

      // Fungsi untuk mengambil data siswa via AJAX
      function fetchSiswa(query) {
        // Gunakan URLSearchParams untuk encoding yang aman
        const params = new URLSearchParams({
          q: query
        });

        // Pastikan path ke cari_siswa.php benar
        fetch(`app/siswa_cari.php?${params.toString()}`)
          .then(response => {
            if (!response.ok) {
              throw new Error('Network response was not ok');
            }
            return response.text(); // Ambil respons sebagai HTML
          })
          .then(html => {
            tableBody.innerHTML = html; // Ganti isi tbody dengan hasil pencarian
          })
          .catch(error => {
            console.error('Error fetching data:', error);
            tableBody.innerHTML = `<tr><td colspan="${'<?php echo ($user_role === 'admin' ? 7 : 6); ?>'}" class="px-4 py-4 text-center text-red-500">Gagal memuat data. Silakan coba lagi.</td></tr>`;
          })
          .finally(() => {
            loadingIndicator.classList.add('hidden'); // Sembunyikan loading
          });
      }

      // Tampilkan data awal saat halaman dimuat (opsional, bisa hapus query awal di PHP jika ini dipakai)
      fetchSiswa(''); 
    });
  </script>