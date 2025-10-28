<?php
// (Logika PHP Anda di bagian atas)
$kelas = $_GET['kelas'] ?? '';
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// ======================================================================
// [BARU] LOGIKA ABSEN PULANG
// ======================================================================
// Ubah status 'H' (Hadir) menjadi 'A' (Alpha) JIKA jam_pulang KOSONG
// dan tanggalnya SUDAH LEWAT (bukan hari ini).
// Ini membersihkan data siswa yang masuk tapi tidak absen pulang.

$queryUpdateAlpha = "UPDATE absensi SET status = 'A' 
                     WHERE status = 'H' 
                     AND jam_pulang IS NULL 
                     AND tanggal < CURDATE()"; // Hanya untuk hari kemarin
mysqli_query($conn, $queryUpdateAlpha);

// ======================================================================

$kelasList = mysqli_query($conn, "SELECT DISTINCT id, nama_kelas FROM kelas ORDER BY nama_kelas");
$jumlahHari = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);

// Ambil siswa hanya yang aktif
$siswaQuery = "SELECT *, k.id AS id_kelas FROM siswa s JOIN kelas k ON s.id_kelas = k.id WHERE status='aktif'";
if ($kelas != '') {
  $siswaQuery .= " AND id_kelas = '$kelas'";
}
$siswaQuery .= " ORDER BY nama";
$siswaResult = mysqli_query($conn, $siswaQuery);

// Ambil data absensi (setelah di-update oleh query di atas)
$absensi = [];
$absensiQuery = "SELECT a.*, s.nis, s.nama, k.nama_kelas FROM absensi a 
                 JOIN siswa s ON a.siswa_id = s.id 
                 JOIN kelas k ON s.id_kelas = k.id 
                 WHERE MONTH(a.tanggal) = '$bulan' 
                   AND YEAR(a.tanggal) = '$tahun'
                   AND s.status='aktif'";
if ($kelas != '') {
  $absensiQuery .= " AND nama_kelas = '$kelas'";
}
$resultAbsensi = mysqli_query($conn, $absensiQuery);

while ($row = mysqli_fetch_assoc($resultAbsensi)) {
  $sid = $row['siswa_id'];
  $tgl = (int)date('j', strtotime($row['tanggal']));
  // [DIUBAH] Simpan juga jam masuk dan pulang jika perlu
  $absensi[$sid][$tgl] = [
      'status' => $row['status'],
      'jam' => $row['jam'],
      'jam_pulang' => $row['jam_pulang']
  ];
}

// (Sisa logika PHP Anda untuk libur, profil, wali kelas, dll...)
// Ambil daftar hari libur dari database
$libur = [];
$queryLibur = mysqli_query($conn, "SELECT tanggal FROM hari_libur");
while ($row = mysqli_fetch_assoc($queryLibur)) {
  $libur[] = $row['tanggal'];
}
$profil = mysqli_fetch_assoc(mysqli_query($conn, "SELECT kepala_sekolah, nip_kepala FROM profil_sekolah LIMIT 1"));
$wali_nama = '....................................';
$wali_nip = '........................';
if ($kelas != '') {
  $qWali = mysqli_query($conn, "SELECT nama_wali, nip_wali, k.id AS id_kelas FROM wali_kelas w JOIN kelas k ON w.id_kelas = k.id WHERE id_kelas = '$kelas' LIMIT 1");
  if ($w = mysqli_fetch_assoc($qWali)) {
    $wali_nama = $w['nama_wali'] ?? $wali_nama;
    $wali_nip = $w['nip_wali'] ?? $wali_nip;
  }
}
$tanggal_terakhir = date("j F Y", strtotime("$tahun-$bulan-" . cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun)));

?>

<div class="flex-1">

    <div class="bg-white p-4 rounded-lg shadow-md mb-6">
      <form method="get" class="flex flex-col sm:flex-row sm:items-end sm:gap-4 space-y-2 sm:space-y-0">
        
        <input type="hidden" name="page" value="rekap_bulanan">

        <div class="flex-1">
          <label for="kelas" class="block text-sm font-medium text-gray-700">Kelas:</label>
          <select id="kelas" name="kelas" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
            <option value="">Semua</option>
            <?php 
            mysqli_data_seek($kelasList, 0); // Reset pointer
            while ($k = mysqli_fetch_assoc($kelasList)) {
              $sel = ($k['id'] == $kelas) ? 'selected' : '';
              echo "<option value='".htmlspecialchars($k['id'])."' $sel>".htmlspecialchars($k['nama_kelas'])."</option>";
            } ?>
          </select>
        </div>

        <div class="flex-1">
          <label for="bulan" class="block text-sm font-medium text-gray-700">Bulan:</label>
          <select id="bulan" name="bulan" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
            <?php for ($b = 1; $b <= 12; $b++) {
              $sel = ($b == $bulan) ? 'selected' : '';
              echo "<option value='$b' $sel>" . date('F', mktime(0, 0, 0, $b, 10)) . "</option>";
            } ?>
          </select>
        </div>

        <div class="flex-1">
          <label for="tahun" class="block text-sm font-medium text-gray-700">Tahun:</label>
          <input type="number" id="tahun" name="tahun" value="<?= htmlspecialchars($tahun) ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
        </div>

        <div class="flex-shrink-0 flex flex-col sm:flex-row gap-2 pt-2 sm:pt-0">
          <button type="submit" class="inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
            <i class="fa-solid fa-filter mr-2"></i> Tampilkan
          </button>
          
          <a href="app/cetak_absen.php?kelas=<?= htmlspecialchars($kelas) ?>&bulan=<?= htmlspecialchars($bulan) ?>&tahun=<?= htmlspecialchars($tahun) ?>" target="_blank" 
             class="inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <i class="fa-solid fa-print mr-2"></i> Cetak / PDF
          </a>
        </div>

      </form>
    </div>

    <div class="bg-white p-4 rounded-lg shadow-md overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 border-collapse" style="font-size: 11px;">
        <thead class="bg-gray-100 ">
          <tr>
            <th rowspan="2" class="px-2 py-2 text-center font-bold text-gray-600 uppercase tracking-wider border border-gray-200">No</th>
            <th rowspan="2" class="px-2 py-2 text-center font-bold text-gray-600 uppercase tracking-wider border border-gray-200">NIS</th>
            <th rowspan="2" class="px-2 py-2 text-left font-bold text-gray-600 uppercase tracking-wider border border-gray-200">Nama</th>
            <th colspan="<?= $jumlahHari ?>" class="px-2 py-2 text-center font-bold text-gray-600 uppercase tracking-wider border border-gray-200">Tanggal</th>
            <th colspan="4" class="px-2 py-2 text-center font-bold text-gray-600 uppercase tracking-wider border border-gray-200">Rekap</th>
          </tr>
          <tr>
            <?php
            for ($i = 1; $i <= $jumlahHari; $i++) {
              $tanggal = "$tahun-" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "-" . str_pad($i, 2, '0', STR_PAD_LEFT);
              $day = date('w', strtotime($tanggal)); // 0 = Minggu, 5 = Jumat
              
              // [FIX] Dikembalikan ke logika Jumat (5)
              $class = ($day == 5) ? 'text-red-600' : ''; 
              echo "<th class='px-1 py-2 w-5 $class'>$i</th>";
            }
            ?>
            <th class="px-1 py-2 w-6 bg-green-100 border border-gray-200 text-center">H</th>
            <th class="px-1 py-2 w-6 bg-yellow-100 border border-gray-200 text-center">S</th>
            <th class="px-1 py-2 w-6 bg-blue-100 border border-gray-200 text-center">I</th>
            <th class="px-1 py-2 w-6 bg-red-100 border border-gray-200 text-center">A</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <?php
          $no = 1;
          mysqli_data_seek($siswaResult, 0); // Reset pointer
          while ($siswa = mysqli_fetch_assoc($siswaResult)) {
            $sid = $siswa['id'];
            echo "<tr>";
            echo "<td class='px-2 py-1 whitespace-nowrap text-center border border-gray-200'>$no</td>";
            echo "<td class='px-2 py-1 whitespace-nowrap text-center border border-gray-200'>".htmlspecialchars($siswa['nis'])."</td>";
            echo "<td class='px-2 py-1 whitespace-nowrap text-left font-medium text-gray-900 border border-gray-200'>".htmlspecialchars($siswa['nama'])."</td>";

            $countH = $countS = $countI = $countA = 0;

            for ($i = 1; $i <= $jumlahHari; $i++) {
              // [DIUBAH] Ambil data dari array
              $data_hari = $absensi[$sid][$i] ?? null;
              $val = $data_hari['status'] ?? ''; // Ambil statusnya
              
              $tanggal = "$tahun-" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "-" . str_pad($i, 2, '0', STR_PAD_LEFT);
              $day = date('w', strtotime($tanggal));

              if ($val == '') {
                // [FIX] Dikembalikan ke logika Jumat (5)
                if ($day == 5 || in_array($tanggal, $libur)) {
                  echo "<td class='bg-gray-100 text-red-600 font-bold border border-gray-200'>&bull;</td>";
                } else {
                  echo "<td class='bg-gray-50 border border-gray-200'></td>"; 
                }
              } else {
                if ($val == 'H') {
                  echo "<td class='text-green-600 border border-gray-200'>&bull;</td>"; 
                  $countH++;
                } elseif ($val == 'A') {
                  // Ini akan otomatis menampilkan 'A' untuk yg lupa absen pulang
                  echo "<td class='text-red-600 font-bold border border-gray-200'>A</td>";
                  $countA++;
                } elseif ($val == 'S') {
                  echo "<td class='text-yellow-600 font-bold border border-gray-200'>S</td>";
                  $countS++;
                } elseif ($val == 'I') {
                  echo "<td class='text-blue-600 font-bold border border-gray-200'>I</td>";
                  $countI++;
                } else {
                  echo "<td>".htmlspecialchars($val)."</td>";
                }
              }
            }

            echo "<td class='text-center font-bold bg-green-50 border border-gray-200'>$countH</td>";
            echo "<td class='text-center font-bold bg-yellow-50 border border-gray-200'>$countS</td>";
            echo "<td class='text-center font-bold bg-blue-50 border border-gray-200'>$countI</td>";
            echo "<td class='text-center font-bold bg-red-50 border border-gray-200'>$countA</td>";
            echo "</tr>";
            $no++;
          }
          if ($no == 1) { // Jika tidak ada siswa
            echo "<tr><td colspan='".(3 + $jumlahHari + 4)."' class='text-center p-4 text-gray-500'>Tidak ada data siswa aktif di kelas ini.</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </div> <div class="mt-10">
      <table class="w-full border-0 text-sm text-center">
        <tr class="border-0">
          <td class="w-1/2 border-0">
            Mengetahui,<br>
            Kepala Sekolah<br>
            <br><br><br><br> <u class="font-bold"><?= htmlspecialchars($profil['kepala_sekolah'] ?? '....................................') ?></u><br>
            NIP. <?= htmlspecialchars($profil['nip_kepala'] ?? '........................') ?>
          </td>
          <td class="w-1/2 border-0">
            <?= htmlspecialchars($tanggal_terakhir) ?><br>
            Wali Kelas <?= $kelas != '' ? htmlspecialchars($kelas) : '(Semua Kelas)' ?><br>
            <br><br><br><br> <u class="font-bold"><?= htmlspecialchars($wali_nama) ?></u><br>
            NIP. <?= htmlspecialchars($wali_nip) ?>
          </td>
        </tr>
      </table>
    </div>

</div>