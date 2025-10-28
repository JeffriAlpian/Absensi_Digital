<?php
include 'config.php';

header("Content-Type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=rekap_absen.xls");

$kelas = $_GET['kelas'] ?? '';
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// Ambil data kepala sekolah
$profil = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM profil_sekolah LIMIT 1"));
$kepalaSekolah = $profil['kepala_sekolah'];
$nipKepala = $profil['nip_kepala'];
$namaSekolah = $profil['nama'];

// Ambil data wali kelas (jika kelas dipilih)
$waliKelas = '';
$nipWali = '';
if ($kelas != '') {
    $dataWali = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_wali, nip_wali FROM wali_kelas WHERE kelas='$kelas' LIMIT 1"));
    if ($dataWali) {
        $waliKelas = $dataWali['nama_wali'];
        $nipWali = $dataWali['nip_wali'];
    }
}

// Tanggal terakhir bulan ini
$tanggalCetak = date("t") . " " . date("F Y");

// Query data absensi
$query = "SELECT a.tanggal, s.nis, s.nisn, s.nama, s.kelas, a.status, a.keterangan
          FROM absensi a
          JOIN siswa s ON a.nisn = s.nisn
          WHERE MONTH(a.tanggal) = '$bulan' AND YEAR(a.tanggal) = '$tahun'";

if ($kelas != '') {
  $query .= " AND s.kelas = '$kelas'";
}

$query .= " ORDER BY s.kelas, s.nama, a.tanggal";
$result = mysqli_query($conn, $query);

// Output tabel absensi
echo "<table border='1'>
<tr>
  <th>Tanggal</th>
  <th>NIS</th>
  <th>NISN</th>
  <th>Nama</th>
  <th>Kelas</th>
  <th>Status</th>
  <th>Keterangan</th>
</tr>";

while ($row = mysqli_fetch_assoc($result)) {
  echo "<tr>
    <td>{$row['tanggal']}</td>
    <td>{$row['nis']}</td>
    <td>{$row['nisn']}</td>
    <td>{$row['nama']}</td>
    <td>{$row['kelas']}</td>
    <td>{$row['status']}</td>
    <td>{$row['keterangan']}</td>
  </tr>";
}
echo "</table>";

// Spasi sebelum tanda tangan
echo "<br><br><table width='100%' style='border:0;'>
<tr>
  <td width='50%' align='center'>
    Mengetahui,<br>
    Kepala Sekolah<br><br><br><br>
    <u>$kepalaSekolah</u><br>
    NIP. $nipKepala
  </td>
  <td width='50%' align='center'>
    $namaSekolah, $tanggalCetak<br>
    Wali Kelas $kelas<br><br><br><br>
    <u>$waliKelas</u><br>
    NIP. $nipWali
  </td>
</tr>
</table>";
?>
