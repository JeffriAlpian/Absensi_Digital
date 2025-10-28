<?php
session_start();
include 'app/config.php';

// Cek login dan role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
  header("Location: index.php");
  exit;
}
if (!isset($_SESSION['siswa_id'])) {
  session_destroy();
  header("Location: index.php?error=sesi_siswa_hilang");
  exit;
}

$siswa_id = intval($_SESSION['siswa_id']);
$username = $_SESSION['username']; // NISN

// Ambil data siswa (Nama, Kelas, NISN untuk QR)
$siswa = null;
// [DIUBAH] Tambahkan s.nisn di SELECT
$sql_siswa = "SELECT s.nama, s.nisn, k.nama_kelas
              FROM siswa AS s
              LEFT JOIN kelas AS k ON s.id_kelas = k.id
              WHERE s.id = ?";
$stmt_siswa = $conn->prepare($sql_siswa);

if ($stmt_siswa) {
  $stmt_siswa->bind_param("i", $siswa_id);
  $stmt_siswa->execute();
  $result_siswa = $stmt_siswa->get_result();
  if ($result_siswa->num_rows > 0) {
    $siswa = $result_siswa->fetch_assoc();
  }
  $stmt_siswa->close();
}

if ($siswa === null) {
  session_destroy();
  header("Location: index.php?error=data_siswa_tidak_ditemukan");
  exit;
}
$nisn_siswa = $siswa['nisn']; // [BARU] Simpan NISN

// Filter bulan & tahun (default bulan & tahun ini)
$bulanFilter = isset($_GET['bulan']) ? intval($_GET['bulan']) : date("m");
$tahunFilter = isset($_GET['tahun']) ? intval($_GET['tahun']) : date("Y");

// Ambil riwayat absensi sesuai filter
$absensiList = [];
$sql_absensi = "SELECT tanggal, jam, jam_pulang, status, keterangan
                FROM absensi
                WHERE siswa_id = ?
                  AND MONTH(tanggal) = ?
                  AND YEAR(tanggal) = ?
                ORDER BY tanggal DESC";
$stmt_absensi = $conn->prepare($sql_absensi);

if ($stmt_absensi) {
  $stmt_absensi->bind_param("iii", $siswa_id, $bulanFilter, $tahunFilter);
  $stmt_absensi->execute();
  $result_absensi = $stmt_absensi->get_result();
  while ($row = $result_absensi->fetch_assoc()) {
    $absensiList[] = $row;
  }
  $stmt_absensi->close();
}

// [BARU] Hitung ringkasan absensi BULAN INI (bulan berjalan)
$bulanIni = date("m");
$tahunIni = date("Y");
$ringkasanBulanIni = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];
$sql_ringkasan = "SELECT status, COUNT(*) as jumlah
                  FROM absensi
                  WHERE siswa_id = ?
                    AND MONTH(tanggal) = ?
                    AND YEAR(tanggal) = ?
                  GROUP BY status";
$stmt_ringkasan = $conn->prepare($sql_ringkasan);
if ($stmt_ringkasan) {
  $stmt_ringkasan->bind_param("iii", $siswa_id, $bulanIni, $tahunIni);
  $stmt_ringkasan->execute();
  $result_ringkasan = $stmt_ringkasan->get_result();
  while ($row = $result_ringkasan->fetch_assoc()) {
    if (isset($ringkasanBulanIni[$row['status']])) {
      $ringkasanBulanIni[$row['status']] = $row['jumlah'];
    }
  }
  $stmt_ringkasan->close();
}


// Mapping status
$statusMapping = [
  'H' => ['text' => 'Hadir', 'color' => 'text-green-600', 'icon' => 'fa-check-circle'],
  'S' => ['text' => 'Sakit', 'color' => 'text-yellow-600', 'icon' => 'fa-notes-medical'],
  'I' => ['text' => 'Izin', 'color' => 'text-blue-600', 'icon' => 'fa-info-circle'],
  'A' => ['text' => 'Alpa', 'color' => 'text-red-600', 'icon' => 'fa-times-circle'],
];

// Helper class
$input_class = "block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm";
$btn_class = "inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2";

?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Dashboard Siswa - <?= htmlspecialchars($siswa['nama']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f7fafc;
      /* Latar belakang lebih terang */
    }

    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    /* Custom scrollbar (opsional) */
    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }

    ::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 3px;
    }

    ::-webkit-scrollbar-thumb {
      background: #cbd5e0;
      border-radius: 3px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #a0aec0;
    }
  </style>
</head>

<body class="min-h-screen">

  <div class="flex flex-col min-h-screen">

    <header class="bg-gradient-to-r from-green-600 to-teal-600 text-white shadow-md sticky top-0 z-10">
      <div class="container mx-auto px-4 py-3 flex justify-between items-center">
        <div class="flex items-center gap-2">
          <i class="fas fa-user-graduate text-xl"></i>
          <span class="text-lg font-semibold">Dashboard Siswa</span>
        </div>
        <a href="app/logout.php" class="<?= $btn_class ?> bg-red-600 hover:bg-red-700 focus:ring-red-500 !py-1.5 !px-3 text-xs">
          <i class="fa-solid fa-right-from-bracket mr-1"></i> Logout
        </a>
      </div>
    </header>

    <main class="flex-grow container mx-auto px-4 py-6">

      <div class="bg-white p-6 rounded-xl shadow-lg mb-6 border border-gray-200">
        <div class="flex flex-col sm:flex-row items-center gap-4">
          <div class="w-16 h-16 rounded-full bg-gradient-to-br from-teal-400 to-green-500 flex items-center justify-center text-white text-2xl font-bold flex-shrink-0">
            <?= strtoupper(substr($siswa['nama'], 0, 1)) ?>
          </div>
          <div>
            <h2 class="text-2xl font-bold text-gray-800">
              Selamat Datang, <?= htmlspecialchars($siswa['nama']) ?>!
            </h2>
            <p class="text-gray-600 text-md mt-1">
              <i class="fas fa-chalkboard-teacher mr-1 text-green-600"></i> Kelas:
              <span class="font-semibold"><?= htmlspecialchars($siswa['nama_kelas'] ?? 'N/A') ?></span>
            </p>
            <p class="text-gray-500 text-sm">
              <i class="fas fa-id-card mr-1 text-green-600"></i> NISN:
              <span class="font-semibold"><?= htmlspecialchars($nisn_siswa) ?></span>
            </p>
          </div>
        </div>
      </div>


      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-lg border border-gray-200">
          <h4 class="text-lg font-semibold mb-4 text-gray-700 flex items-center">
            <i class="fas fa-calendar-check mr-2 text-indigo-600"></i>
            Ringkasan Absensi Bulan Ini (<?= date('F Y') ?>)
          </h4>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="p-4 bg-green-50 rounded-lg border border-green-200 hover:shadow-md transition-shadow duration-200">
              <div class="flex items-center justify-center mb-1">
                <i class="fas fa-check-circle text-xl text-green-500 mr-2"></i>
                <span class="text-3xl font-bold text-green-600"><?= $ringkasanBulanIni['H'] ?></span>
              </div>
              <div class="text-sm font-medium text-green-700 text-center">Hadir</div>
            </div>
            <div class="p-4 bg-yellow-50 rounded-lg border border-yellow-200 hover:shadow-md transition-shadow duration-200">
              <div class="flex items-center justify-center mb-1">
                <i class="fas fa-notes-medical text-xl text-yellow-500 mr-2"></i>
                <span class="text-3xl font-bold text-yellow-600"><?= $ringkasanBulanIni['S'] ?></span>
              </div>
              <div class="text-sm font-medium text-yellow-700 text-center">Sakit</div>
            </div>
            <div class="p-4 bg-blue-50 rounded-lg border border-blue-200 hover:shadow-md transition-shadow duration-200">
              <div class="flex items-center justify-center mb-1">
                <i class="fas fa-info-circle text-xl text-blue-500 mr-2"></i>
                <span class="text-3xl font-bold text-blue-600"><?= $ringkasanBulanIni['I'] ?></span>
              </div>
              <div class="text-sm font-medium text-blue-700 text-center">Izin</div>
            </div>
            <div class="p-4 bg-red-50 rounded-lg border border-red-200 hover:shadow-md transition-shadow duration-200">
              <div class="flex items-center justify-center mb-1">
                <i class="fas fa-times-circle text-xl text-red-500 mr-2"></i>
                <span class="text-3xl font-bold text-red-600"><?= $ringkasanBulanIni['A'] ?></span>
              </div>
              <div class="text-sm font-medium text-red-700 text-center">Alpa</div>
            </div>
          </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200 flex flex-col items-center justify-center">
          <h4 class="text-lg font-semibold mb-3 text-gray-700 flex items-center">
            <i class="fas fa-qrcode mr-2 text-gray-600"></i>
            QR Code Absensi
          </h4>
          <?php
          $qr_file = "assets/qr/{$nisn_siswa}.png";
          $qr_src = file_exists($qr_file) ? $qr_file : 'assets/qr/placeholder.png'; // Ganti ke placeholder jika perlu
          ?>
          <div class="p-2 border rounded-lg bg-gray-50 mb-3">
            <img src="<?= $qr_src ?>?t=<?= time() ?>" alt="QR Code" class="w-36 h-36">
          </div>
          <p class="text-xs text-gray-500 text-center mb-3">Gunakan kode ini untuk scan absensi.</p>
          <a href="<?= $qr_src ?>" download="QRCode-<?= $nisn_siswa ?>.png" class="mt-auto <?= $btn_class ?> bg-gray-700 hover:bg-gray-800 focus:ring-gray-500 !py-1.5 !px-3 !text-xs w-full sm:w-auto">
            <i class="fa-solid fa-download mr-1"></i> Unduh QR Code
          </a>
        </div>

      </div>


      <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b bg-gray-50">
          <h4 class="text-lg font-semibold text-gray-700 flex items-center">
            <i class="fas fa-history mr-2 text-purple-600"></i>
            Riwayat Kehadiran
          </h4>

          <p class="text-sm text-gray-500 p-3 text-center">Jika Siswa Tidak Melengkapi <strong>Absensi Masuk</strong> dan <strong> Absensi Pulang </strong> Maka Terhitung <span class="text-red-500"> Alpha </span> </p>

          <form id="filterForm" method="GET" class="flex flex-col sm:flex-row sm:items-end gap-3 mt-3">
            <div class="flex-1 min-w-[150px]">
              <label for="bulan" class="block text-xs font-medium text-gray-600">Bulan</label>
              <select name="bulan" id="bulan" class="<?= $input_class ?> !py-1.5 !text-sm">
                <?php /* Opsi Bulan */
                $namaBulan = [1 => "Jan", 2 => "Feb", 3 => "Mar", 4 => "Apr", 5 => "Mei", 6 => "Jun", 7 => "Jul", 8 => "Agu", 9 => "Sep", 10 => "Okt", 11 => "Nov", 12 => "Des"];
                foreach ($namaBulan as $num => $nama) {
                  $selected = ($bulanFilter == $num) ? "selected" : "";
                  echo "<option value='$num' $selected>$nama</option>";
                } ?>
              </select>
            </div>
            <div class="flex-1 min-w-[100px]">
              <label for="tahun" class="block text-xs font-medium text-gray-600">Tahun</label>
              <select name="tahun" id="tahun" class="<?= $input_class ?> !py-1.5 !text-sm">
                <?php /* Opsi Tahun */
                $tahunSekarang = date("Y");
                for ($t = $tahunSekarang; $t >= $tahunSekarang - 5; $t--) {
                  $selected = ($tahunFilter == $t) ? "selected" : "";
                  echo "<option value='$t' $selected>$t</option>";
                } ?>
              </select>
            </div>
            <noscript>
              <button type="submit" class="<?= $btn_class ?> bg-green-600 hover:bg-green-700 focus:ring-green-500 !py-1.5 !text-sm">
                Tampilkan
              </button>
            </noscript>
          </form>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider w-1/12">No</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Tanggal</th>
                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Masuk</th>
                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Pulang</th>
                <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Keterangan</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php if (!empty($absensiList)): ?>
                <?php $no = 1;
                foreach ($absensiList as $row): ?>
                  <?php
                  $statusInfo = $statusMapping[$row['status']] ?? ['text' => $row['status'], 'color' => 'text-gray-600', 'icon' => 'fa-question-circle'];
                  $jamMasuk = $row['jam'] ? date("H:i", strtotime($row['jam'])) : '-';
                  $jamPulang = $row['jam_pulang'] ? date("H:i", strtotime($row['jam_pulang'])) : '-';
                  $tanggalFormatted = date("d M Y", strtotime($row['tanggal']));
                  ?>
                  <tr class="hover:bg-gray-50 transition-colors duration-150">
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 text-center"><?= $no++ ?></td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700"><?= $tanggalFormatted ?></td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700 text-center"><?= $jamMasuk ?></td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700 text-center"><?= $jamPulang ?></td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-center font-medium <?= $statusInfo['color'] ?>">
                      <i class="fa-solid <?= $statusInfo['icon'] ?> mr-1"></i><?= $statusInfo['text'] ?>
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-500"><?= htmlspecialchars($row['keterangan'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" class="px-4 py-6 text-center text-gray-500 italic">
                    Belum ada data absensi untuk periode ini.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>

    </main>

    <footer class="bg-gray-800 text-white text-center py-4 mt-8">
      <p class="text-sm">&copy; <?= date("Y") ?> <?= htmlspecialchars($siswa['nama']) ?> - Aplikasi Absensi QR Code</p>
    </footer>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const filterForm = document.getElementById('filterForm');
      const bulanSelect = document.getElementById('bulan');
      const tahunSelect = document.getElementById('tahun');
      const submitFilter = () => filterForm.submit();
      bulanSelect.addEventListener('change', submitFilter);
      tahunSelect.addEventListener('change', submitFilter);
    });
  </script>

</body>

</html>