<?php

date_default_timezone_set("Asia/Jakarta");

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$kelas   = $_GET['kelas'] ?? '';
?>

<div class="container">

  <a href="dashboard.php" class="btn btn-secondary mb-3">← Kembali</a>

  <form method="get" class="mb-3 row g-2 items-center flex">
    <input type="hidden" name="page" value="belum_absensi">
    <div class="col-auto">
      <label for="tanggal" class="form-label mb-0">Tanggal:</label>
      <input type="date" name="tanggal" value="<?= $tanggal ?>" class="form-control">
    </div>

    <div class="col-auto">
      <label for="kelas" class="form-label mb-0">Kelas:</label>
      <select name="kelas" class="form-select">
        <option value="">Semua Kelas</option>
        <?php
        $qkelas = mysqli_query($conn, "SELECT DISTINCT id, nama_kelas FROM kelas ORDER BY nama_kelas");
        while ($k = mysqli_fetch_assoc($qkelas)) {
          $selected = $k['id'] == $kelas ? 'selected' : '';
          echo "<option $selected value='{$k['id']}'>{$k['nama_kelas']}</option>";
        }
        ?>
      </select>
    </div>
    
    <div class="col-auto">
      <button type="submit" class="inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
        <i class="fa-solid fa-filter mr-2"></i> Tampilkan
      </button>
    </div>
  </form>

  <table class="table table-bordered table-sm">
    <thead class="table-light">
      <tr>
        <th>NIS</th>
        <th>Nama</th>
        <th>Kelas</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $filterKelas = intval($kelas) ? "AND s.id_kelas = '$kelas'" : '';

      $q = mysqli_query($conn, "SELECT s.id, s.nis, s.nama, k.nama_kelas AS kelas FROM siswa s JOIN kelas k ON s.id_kelas = k.id WHERE s.status = 'aktif' $filterKelas
        AND NOT EXISTS (
        SELECT 1 
        FROM absensi a
        WHERE a.siswa_id = s.id
          AND a.tanggal = '$tanggal') ORDER BY s.nama; ");

      if (mysqli_num_rows($q) > 0) {
        while ($d = mysqli_fetch_assoc($q)) {
          // Pesan untuk dibagikan ke WhatsApp
          $pesan  = "Assalamu'alaikum,\n\n"
            . "Kami informasikan bahwa ananda *{$d['nama']}* "
            . "(NIS: {$d['nis']}, Kelas: {$d['kelas']}) "
            . "belum tercatat hadir pada tanggal *$tanggal*.\n\n"
            . "Mohon perhatian Bapak/Ibu 🙏";
          $urlPesan = "https://wa.me/?text=" . urlencode($pesan);

          echo "<tr>
            <td>{$d['nis']}</td>
            <td>{$d['nama']}</td>
            <td>{$d['kelas']}</td>
            <td>
              <a href='$urlPesan' target='_blank' class='btn btn-success btn-sm'>
                📲 Kirim WA
              </a>
            </td>
          </tr>";
        }
      } else {
        echo "<tr><td colspan='4' class='text-center'>Semua siswa sudah ada record absensi pada tanggal ini</td></tr>";
      }
      ?>
    </tbody>
  </table>
</div>