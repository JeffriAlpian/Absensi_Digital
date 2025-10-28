<?php
session_start();

// Tambahan: izinkan akses jika login sebagai admin atau wali
if (!isset($_SESSION['admin']) && !isset($_SESSION['wali'])) {
    header("Location: index.php");
    exit;
}

include "config.php";
date_default_timezone_set("Asia/Jakarta");

// Pastikan ID siswa ada
if (!isset($_GET['id'])) {
    echo "ID siswa tidak ditemukan.";
    exit;
}

$siswa_id = intval($_GET['id']); // pastikan integer

// Ambil nama dan kelas siswa
$qSiswa = mysqli_query($conn, "SELECT nama, kelas FROM siswa WHERE id = $siswa_id");
if (mysqli_num_rows($qSiswa) === 0) {
    echo "Siswa tidak ditemukan.";
    exit;
}
$siswa = mysqli_fetch_assoc($qSiswa);

// Filter bulan & tahun (default bulan ini)
$bulanFilter = isset($_GET['bulan']) ? $_GET['bulan'] : date("m");
$tahunFilter = isset($_GET['tahun']) ? $_GET['tahun'] : date("Y");

// Query riwayat absensi bulan & tahun tertentu
$sql = "
    SELECT tanggal, jam, status
    FROM absensi
    WHERE siswa_id = $siswa_id
      AND MONTH(tanggal) = '" . mysqli_real_escape_string($conn, $bulanFilter) . "'
      AND YEAR(tanggal) = '" . mysqli_real_escape_string($conn, $tahunFilter) . "'
    ORDER BY tanggal DESC, jam DESC
";
$data = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Riwayat Kehadiran - <?= htmlspecialchars($siswa['nama']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="container mt-4">

<h2>Riwayat Kehadiran</h2>
<a href="jam_absensi.php" class="btn btn-secondary mb-3">← Kembali</a>

<div class="mb-3">
    <h4><?= htmlspecialchars($siswa['nama']) ?> 
        <small class="text-muted">(Kelas <?= htmlspecialchars($siswa['kelas']) ?>)</small>
    </h4>
</div>

<form method="GET" class="row g-2 mb-3">
    <input type="hidden" name="id" value="<?= $siswa_id ?>">
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
        <?php if (mysqli_num_rows($data) > 0): ?>
            <?php $no = 1; while ($row = mysqli_fetch_assoc($data)): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['tanggal']) ?></td>
                    <td><?= htmlspecialchars($row['jam']) ?></td>
                    <td><?= htmlspecialchars($row['status']) ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4" class="text-center">Tidak ada data riwayat</td></tr>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>
