<?php
session_start();
include 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['siswa_id'])) {
    die("Data siswa tidak ditemukan, silakan login ulang.");
}

$siswa_id = intval($_SESSION['siswa_id']);
$username = $_SESSION['username'];

// Ambil data siswa
$q = mysqli_query($conn, "SELECT nama, kelas FROM siswa WHERE id = $siswa_id");
$siswa = mysqli_fetch_assoc($q);

// Filter bulan & tahun (default bulan & tahun ini)
$bulanFilter = isset($_GET['bulan']) ? $_GET['bulan'] : date("m");
$tahunFilter = isset($_GET['tahun']) ? $_GET['tahun'] : date("Y");

// Ambil riwayat absensi sesuai filter
$qAbsensi = mysqli_query($conn, "
    SELECT tanggal, jam, status 
    FROM absensi 
    WHERE siswa_id = $siswa_id
      AND MONTH(tanggal) = '" . mysqli_real_escape_string($conn, $bulanFilter) . "'
      AND YEAR(tanggal) = '" . mysqli_real_escape_string($conn, $tahunFilter) . "'
    ORDER BY tanggal DESC, jam DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Siswa</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="container mt-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Halo, <?= htmlspecialchars($siswa['nama']) ?> (<?= htmlspecialchars($siswa['kelas']) ?>)</h2>
    <div>
      <a href="ubah_password.php" class="btn btn-warning btn-sm">Ubah Password</a>
      <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
    </div>
  </div>

  <h4>Riwayat Kehadiran</h4>

  <!-- Form Filter Bulan & Tahun -->
  <form method="GET" class="row g-2 mb-3">
      <div class="col-md-3">
          <label class="form-label">Pilih Bulan</label>
          <select name="bulan" class="form-control">
              <?php 
              $namaBulan = [
                  1=>"Januari", 2=>"Februari", 3=>"Maret", 4=>"April",
                  5=>"Mei", 6=>"Juni", 7=>"Juli", 8=>"Agustus",
                  9=>"September", 10=>"Oktober", 11=>"November", 12=>"Desember"
              ];
              foreach ($namaBulan as $num => $nama) {
                  $selected = ($bulanFilter == $num) ? "selected" : "";
                  echo "<option value='$num' $selected>$nama</option>";
              }
              ?>
          </select>
      </div>
      <div class="col-md-3">
          <label class="form-label">Pilih Tahun</label>
          <select name="tahun" class="form-control">
              <?php
              $tahunSekarang = date("Y");
              for ($t = $tahunSekarang; $t >= $tahunSekarang - 5; $t--) {
                  $selected = ($tahunFilter == $t) ? "selected" : "";
                  echo "<option value='$t' $selected>$t</option>";
              }
              ?>
          </select>
      </div>
      <div class="col-md-2 align-self-end">
          <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
      </div>
  </form>

  <!-- Tabel Riwayat -->
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>No</th>
        <th>Tanggal</th>
        <th>Jam</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (mysqli_num_rows($qAbsensi) > 0): ?>
        <?php $no=1; while($row = mysqli_fetch_assoc($qAbsensi)): ?>
          <tr>
            <td><?= $no++ ?></td>
            <td><?= htmlspecialchars($row['tanggal']) ?></td>
            <td><?= htmlspecialchars($row['jam']) ?></td>
            <td><?= htmlspecialchars($row['status']) ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="4" class="text-center">Belum ada data absensi bulan ini</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

</body>
</html>
