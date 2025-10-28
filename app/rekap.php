<?php
include 'config.php';

$kelas = $_GET['kelas'] ?? '';
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// Ambil daftar kelas dari tabel siswa
$kelasList = mysqli_query($conn, "SELECT DISTINCT kelas FROM siswa ORDER BY kelas");

// Query utama rekap absensi berdasarkan siswa_id
$query = "SELECT a.tanggal, s.nis, s.nisn, s.nama, s.kelas, a.status, a.keterangan
          FROM absensi a
          JOIN siswa s ON a.siswa_id = s.id
          WHERE MONTH(a.tanggal) = '$bulan' AND YEAR(a.tanggal) = '$tahun'";

if ($kelas != '') {
  $query .= " AND s.kelas = '$kelas'";
}

$query .= " ORDER BY s.kelas, s.nama, a.tanggal";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Rekap Absensi Bulanan</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { font-family: sans-serif; padding: 20px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { padding: 5px; border: 1px solid #000; font-size: 14px; text-align: center; }
    select, button { padding: 5px; }
  </style>
</head>
<body>
  <h2>Rekap Absensi Bulanan</h2>

  <form method="get">
    <label>Kelas:
      <select name="kelas">
        <option value="">Semua</option>
        <?php while ($k = mysqli_fetch_assoc($kelasList)) {
          $sel = ($k['kelas'] == $kelas) ? 'selected' : '';
          echo "<option $sel value='{$k['kelas']}'>{$k['kelas']}</option>";
        } ?>
      </select>
    </label>

    <label>Bulan:
      <select name="bulan">
        <?php for ($b = 1; $b <= 12; $b++) {
          $sel = ($b == $bulan) ? 'selected' : '';
          echo "<option $sel value='$b'>" . date('F', mktime(0, 0, 0, $b, 10)) . "</option>";
        } ?>
      </select>
    </label>

    <label>Tahun:
      <input type="number" name="tahun" value="<?= $tahun ?>" style="width:80px;">
    </label>

    <button type="submit">Tampilkan</button>
  </form>

  <table>
    <tr>
      <th>Tanggal</th>
      <th>NIS</th>
      <th>NISN</th>
      <th>Nama</th>
      <th>Kelas</th>
      <th>Status</th>
      <th>Keterangan</th>
    </tr>
    <?php if (mysqli_num_rows($result) > 0): ?>
      <?php while ($row = mysqli_fetch_assoc($result)) { ?>
      <tr>
        <td><?= $row['tanggal'] ?></td>
        <td><?= $row['nis'] ?></td>
        <td><?= $row['nisn'] ?></td>
        <td><?= $row['nama'] ?></td>
        <td><?= $row['kelas'] ?></td>
        <td><?= $row['status'] ?></td>
        <td><?= $row['keterangan'] ?></td>
      </tr>
      <?php } ?>
    <?php else: ?>
      <tr><td colspan="7">Tidak ada data absensi bulan ini.</td></tr>
    <?php endif; ?>
  </table>

  <br>
  <a href="export_rekap.php?kelas=<?= $kelas ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" target="_blank">
    <button>Export ke Excel</button>
  </a>
</body>
</html>
