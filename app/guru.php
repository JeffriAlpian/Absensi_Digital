<?php
require 'vendor/phpqrcode/qrlib.php';

// Proses simpan (tambah baru)
if (isset($_POST['simpan'])) {
  $nip   = mysqli_real_escape_string($conn, $_POST['nip']);
  $nama  = mysqli_real_escape_string($conn, $_POST['nama']);
  $jabatan  = mysqli_real_escape_string($conn, $_POST['jabatan']);
  $tempat_lahir  = mysqli_real_escape_string($conn, $_POST['tempat_lahir']);
  $tanggal_lahir  = mysqli_real_escape_string($conn, $_POST['tanggal_lahir']);
  $no_wa = mysqli_real_escape_string($conn, $_POST['no_wa']);

  mysqli_query($conn, "INSERT INTO guru (nip, nama, jabatan, tempat_lahir, tanggal_lahir, no_wa) 
                         VALUES ('$nip', '$nama', '$jabatan', '$tempat_lahir', '$tanggal_lahir', '$no_wa')");

  // Buat akun user untuk guru
  $username = $nip;
  $password = password_hash($nip, PASSWORD_BCRYPT); // Diganti ke hash yang aman
  $role     = 'guru';

  $cek_user = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' LIMIT 1");
  if (mysqli_num_rows($cek_user) == 0) {
    mysqli_query($conn, "INSERT INTO users (username, nama, password, role) 
                             VALUES ('$username', '$nama', '$password', '$role')");
  }

  // Generate QR Code
  $qr_dir = "assets/qr/";
  if (!is_dir($qr_dir)) mkdir($qr_dir, 0777, true);
  QRcode::png($nip, $qr_dir . "$nip.png", QR_ECLEVEL_L, 4);

  echo "<script>window.location.href = '?page=guru';</script>";
  exit;
}

// Proses update data (edit)
if (isset($_POST['update'])) {
  $id    = intval($_POST['id']);
  $nip   = mysqli_real_escape_string($conn, $_POST['nip']);
  $nama  = mysqli_real_escape_string($conn, $_POST['nama']);
  $jabatan  = mysqli_real_escape_string($conn, $_POST['jabatan']);
  $tempat_lahir  = mysqli_real_escape_string($conn, $_POST['tempat_lahir']);
  $tanggal_lahir  = mysqli_real_escape_string($conn, $_POST['tanggal_lahir']);
  $no_wa = mysqli_real_escape_string($conn, $_POST['no_wa']);

  $res_old = mysqli_query($conn, "SELECT nip FROM guru WHERE id=$id LIMIT 1");
  $old     = mysqli_fetch_assoc($res_old);
  $old_nip = $old['nip'] ?? $nip;

  // Perbaiki query UPDATE: hilangkan koma ganda dan sertakan tempat/tanggal lahir
  $update_sql = "UPDATE guru
                   SET nip='$nip',
                       nama='$nama',
                       jabatan='$jabatan',
                       tempat_lahir='$tempat_lahir',
                       tanggal_lahir='$tanggal_lahir',
                       no_wa='$no_wa'
                 WHERE id=$id";
  mysqli_query($conn, $update_sql);

  $new_password = password_hash($nip, PASSWORD_BCRYPT); // Diganti ke hash yang aman
  mysqli_query($conn, "UPDATE users 
                         SET username='$nip', nama='$nama', password='$new_password' 
                         WHERE username='$old_nip' AND role='guru'");

  $qr_dir = "assets/qr/";
  if (!is_dir($qr_dir)) mkdir($qr_dir, 0777, true);

  // Hapus QR lama jika nip berubah
  if (!empty($old_nip) && $old_nip !== $nip) {
    $old_qr = $qr_dir . $old_nip . '.png';
    if (file_exists($old_qr)) @unlink($old_qr);
  }

  QRcode::png($nip, $qr_dir . "$nip.png", QR_ECLEVEL_L, 4);

  echo "<script>window.location.href = '?page=guru';</script>";
  exit;
}

if(isset($_GET['hapus'])) {
  $id = intval($_GET['hapus']);
  // Hapus data guru
  $res = mysqli_query($conn, "SELECT nip FROM guru WHERE id=$id LIMIT 1");
  $data = mysqli_fetch_assoc($res);
  $nip = $data['nip'] ?? '';

  // Hapus entri terkait di tabel kartu_rfid dahulu (hindari constraint FK)
  mysqli_query($conn, "DELETE FROM kartu_rfid WHERE guru_id = $id");
  // Hapus data guru
  mysqli_query($conn, "DELETE FROM guru WHERE id=$id");
  // Hapus akun user terkait
  mysqli_query($conn, "DELETE FROM users WHERE username='$nip' AND role='guru'");

  // Hapus file QR Code jika ada
  $qr_file = "assets/qr/{$nip}.png";
  if (file_exists($qr_file)) {
    @unlink($qr_file);
  }

  echo "<script>window.location.href = '?page=guru';</script>";
  exit;
}

// Proses generate akun untuk semua guru aktif yang belum punya akun
if (isset($_POST['generate_akun'])) {
  $res = mysqli_query($conn, "SELECT * FROM guru ORDER BY nama ASC");
  while ($row = mysqli_fetch_assoc($res)) {
    $username = $row['nip'];
    $password = password_hash($row['nip'], PASSWORD_BCRYPT); // Diganti ke hash yang aman
    $nama     = $row['nama'];
    $role     = 'guru';

    $cek_user = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' LIMIT 1");
    if (mysqli_num_rows($cek_user) == 0) {
      mysqli_query($conn, "INSERT INTO users (username, nama, password, role) 
                               VALUES ('$username', '$nama', '$password', '$role')");
    }
  }

  echo "<script>window.location.href = '?page=guru';</script>";
  exit;
}

// Ambil data untuk edit jika ada
$edit_data = null;
if (isset($_GET['edit'])) {
  $id = intval($_GET['edit']);
  // [DIUBAH] Query select * sudah otomatis mengambil id_kelas
  $res = mysqli_query($conn, "SELECT * FROM guru WHERE id=$id LIMIT 1");
  $edit_data = mysqli_fetch_assoc($res);
}

// Helper untuk tombol Tailwind
$btn_class = "inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2";
$btn_primary = "bg-green-600 hover:bg-green-700 focus:ring-green-500";
$btn_warning = "bg-yellow-500 hover:bg-yellow-600 focus:ring-yellow-400";
$btn_secondary = "bg-gray-600 hover:bg-gray-700 focus:ring-gray-500";
$btn_dark = "bg-gray-800 hover:bg-gray-900 focus:ring-gray-700";
$btn_danger_outline = "";
$btn_success = "bg-blue-600 hover:bg-blue-700 focus:ring-blue-500"; // Sukses = Biru
$btn_info = "bg-cyan-500 hover:bg-cyan-600 focus:ring-cyan-400";
$btn_print = "bg-green-600 hover:bg-green-700 focus:ring-blue-500"; 
// Helper untuk input form
$input_class = "mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm";
?>

<div class="flex-1 p-6">

  <?php if ($user_role === 'admin') : ?>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
      <h2 class="text-xl font-bold text-gray-800 mb-4">
        <?= $edit_data ? 'Edit Data guru' : 'Tambah Guru Baru' ?>
      </h2>
      <form method="post" class="flex flex-wrap gap-2 -mx-2 space-y-4 md:space-y-0">
        <?php if ($edit_data): ?>
          <input type="hidden" name="id" value="<?= intval($edit_data['id']) ?>">
        <?php endif; ?>

        <div class="w-full md:w-1/3 px-2">
          <label for="nip" class="block text-sm font-medium text-gray-700">NIP</label>
          <input type="number" id="nip" name="nip" class="<?= $input_class ?>" placeholder="NIP" required value="<?= htmlspecialchars($edit_data['nip'] ?? '') ?>">
        </div>

        <div class="w-full md:w-1/3 px-2">
          <label for="nama" class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
          <input type="text" id="nama" name="nama" class="<?= $input_class ?>" placeholder="Nama guru" required value="<?= htmlspecialchars($edit_data['nama'] ?? '') ?>">
        </div>

        <div class="w-full md:w-1/3 px-2">
          <label for="jabatan" class="block text-sm font-medium text-gray-700">Jabatan</label>
          <input type="text" id="jabatan" name="jabatan" class="<?= $input_class ?>" placeholder="Jabatan" required value="<?= htmlspecialchars($edit_data['jabatan'] ?? '') ?>">
        </div>

        <div class="w-full md:w-1/3 px-2">
          <label for="tempat_lahir" class="block text-sm font-medium text-gray-700">Tempat Lahir</label>
          <input type="text" id="tempat_lahir" name="tempat_lahir" class="<?= $input_class ?>" placeholder="Tempat Lahir" value="<?= htmlspecialchars($edit_data['tempat_lahir'] ?? '') ?>">
        </div>

        <div class="w-full md:w-1/3 px-2">
          <label for="tanggal_lahir" class="block text-sm font-medium text-gray-700">Tanggal Lahir</label>
          <input type="date" id="tanggal_lahir" name="tanggal_lahir" class="<?= $input_class ?>" value="<?= htmlspecialchars($edit_data['tanggal_lahir'] ?? '') ?>">
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
              <a href="?page=guru" class="<?= $btn_class ?> <?= $btn_secondary ?> w-full">Batal</a>
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
    <form method="post" onsubmit="return confirm('Yakin ingin generate akun untuk semua guru aktif yang belum punya akun?')">
      <?php if ($user_role === 'admin') : ?>
        <button type="submit" name="generate_akun" class="<?= $btn_class ?> <?= $btn_dark ?>">
          <i class="fa-solid fa-bolt mr-2"></i>Generate Akun guru
        </button>
      <?php endif; ?>
    </form>

    <a href="app/cetak_kartu_guru.php" class="<?= $btn_class ?> <?= $btn_success ?>" target="_blank">
      <i class="fa-solid fa-id-card mr-2"></i>Cetak Semua Kartu
    </a>
    <a href="app/backdesigncardguru.pdf" class="<?= $btn_class ?> <?= $btn_success ?>" target="_blank">
      <i class="fa-solid fa-download mr-2"></i>Unduh Desain Belakang Kartu
    </a>
    <?php if ($user_role === 'admin') : ?>
      <a href="?page=import_guru" class="<?= $btn_class ?> <?= $btn_success ?>">
        <i class="fa-solid fa-file-excel mr-2"></i>Import dari Excel
      </a>
      
    <?php endif; ?>
  </div>


  <div class="mb-4">
    <label for="searchguru" class="sr-only">Cari guru</label>
    <div class="relative rounded-md shadow-sm">
      <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
        <i class="fa-solid fa-search text-gray-400"></i>
      </div>
      <input type="text" id="searchguru" name="searchguru"
        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-green-500 focus:border-green-500 sm:text-sm"
        placeholder="Cari berdasarkan Nama, NIS, atau nip...">
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
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">NIP</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Nama</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Jabatan</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">No WA</th>
            <th class="px-4 py-3 text-xs font-bold text-gray-600 uppercase tracking-wider">QR Code</th>
            <?php if ($user_role === 'admin'): ?>
              <th class="px-4 py-3 text-xs font-bold text-gray-600 uppercase tracking-wider">Aksi</th>
            <?php endif; ?>
          </tr>
        </thead>

        <tbody id="guruTableBody" class="bg-white divide-y divide-gray-200">
          <!-- Data guru akan dimuat di sini melalui AJAX -->
        </tbody>

      </table>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const searchInput = document.getElementById('searchguru');
      const tableBody = document.getElementById('guruTableBody');
      const loadingIndicator = document.getElementById('loadingIndicator');
      let searchTimeout;

      searchInput.addEventListener('keyup', () => {
        // Hapus timeout sebelumnya jika ada (debouncing)
        clearTimeout(searchTimeout);
        loadingIndicator.classList.remove('hidden'); // Tampilkan loading

        // Set timeout baru
        searchTimeout = setTimeout(() => {
          const searchTerm = searchInput.value.trim();
          fetchguru(searchTerm);
        }, 500); // Tunggu 500ms setelah user berhenti mengetik
      });

      // Fungsi untuk mengambil data guru via AJAX
      function fetchguru(query) {
        // Gunakan URLSearchParams untuk encoding yang aman
        const params = new URLSearchParams({
          q: query
        });

        // Pastikan path ke cari_guru.php benar
        fetch(`app/guru_cari.php?${params.toString()}`)
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
      fetchguru('');
    });
  </script>