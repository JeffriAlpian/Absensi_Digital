<?php

date_default_timezone_set("Asia/Jakarta");

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$kelas = $_GET['kelas'] ?? '';

// Ubah atau tambah data absensi per siswa
if (isset($_POST['ubah'])) {
  $siswa_id = $_POST['siswa_id'];
  $status = $_POST['status'];
  $keterangan = $_POST['keterangan'];

  $cek = mysqli_query($conn, "SELECT id FROM absensi WHERE siswa_id=$siswa_id AND tanggal='$tanggal'");
  if (mysqli_num_rows($cek) > 0) {
    mysqli_query($conn, "UPDATE absensi SET status='$status', keterangan='$keterangan' WHERE siswa_id=$siswa_id AND tanggal='$tanggal'");
  } else {
    mysqli_query($conn, "INSERT INTO absensi (siswa_id, tanggal, status, keterangan) VALUES ($siswa_id, '$tanggal', '$status', '$keterangan')");
  }
  // header("Location: ?page=absensi&tanggal=$tanggal&kelas=$kelas");
  echo "<script>window.location.href = '?page=absensi&tanggal=$tanggal&kelas=$kelas';</script>";
  exit;
}

// Tombol Hadir Semua
if (isset($_POST['hadir_semua'])) {
  $filterKelas = $kelas ? "WHERE kelas='$kelas'" : "";
  $qsiswa = mysqli_query($conn, "SELECT id FROM siswa $filterKelas");
  while ($s = mysqli_fetch_assoc($qsiswa)) {
    $siswa_id = $s['id'];
    $cek = mysqli_query($conn, "SELECT id FROM absensi WHERE siswa_id=$siswa_id AND tanggal='$tanggal'");
    if (mysqli_num_rows($cek) > 0) {
      mysqli_query($conn, "UPDATE absensi SET status='H', keterangan='' WHERE siswa_id=$siswa_id AND tanggal='$tanggal'");
    } else {
      mysqli_query($conn, "INSERT INTO absensi (siswa_id, tanggal, status, keterangan) VALUES ($siswa_id, '$tanggal', 'H', '')");
    }
  }
  // header("Location: ?page=absensi&tanggal=$tanggal&kelas=$kelas");
  echo "<script>window.location.href = '?page=absensi&tanggal=$tanggal&kelas=$kelas';</script>";
  exit;
}
?>

<div class="container">

  <a href="dashboard.php" class="btn btn-secondary mb-3">← Kembali</a>

  <form method="get" class="mb-3 row g-2 align-items-center">
    <input type="hidden" name="page" value="absensi">
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
      <button class="btn btn-primary">Tampilkan</button>
    </div>
  </form>

  <!-- Tombol Hadir Semua -->
  <form method="post" class="mb-3">
    <button type="submit" name="hadir_semua" class="btn btn-success">
      ✅ Tandai Semua Hadir (H)
    </button>
    <input type="hidden" name="page" value="absensi">
  </form>

  <table class="table table-bordered table-sm">
    <thead>
      <tr>
        <th>NIS</th>
        <th>Nama</th>
        <th>Kelas</th>
        <th>Status</th>
        <th>Keterangan</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php

      $filterKelas = intval($kelas) ? "WHERE s.id_kelas = '$kelas'" : '';

      $q = mysqli_query($conn, "SELECT s.id AS siswa_id, s.nis, s.nama, k.nama_kelas AS kelas, a.status, a.keterangan, a.id AS absen_id
                                FROM siswa s 
                                LEFT JOIN kelas k ON s.id_kelas = k.id 
                                LEFT JOIN absensi a ON a.siswa_id = s.id AND a.tanggal = '$tanggal' 
                                $filterKelas 
                                ORDER BY s.nama; ");
                                
      while ($d = mysqli_fetch_assoc($q)) {
      ?>
        <tr>
          <form method="post">

            <input type="hidden" name="siswa_id" value="<?= $d['siswa_id'] ?>">
            <td><?= $d['nis'] ?></td>
            <td><?= $d['nama'] ?></td>
            <td><?= $d['kelas'] ?></td>
            <td>
              <select name="status" class="form-select form-select-sm">
                <option <?= $d['status'] == 'H' ? 'selected' : '' ?>>H</option>
                <option <?= $d['status'] == 'S' ? 'selected' : '' ?>>S</option>
                <option <?= $d['status'] == 'I' ? 'selected' : '' ?>>I</option>
                <option <?= $d['status'] == 'A' ? 'selected' : '' ?>>A</option>
              </select>
            </td>
            <td>
              <input type="text" name="keterangan" class="form-control form-control-sm" value="<?= $d['keterangan'] ?>">
            </td>
            <td>
              <button type="submit" name="ubah" class="btn btn-sm btn-success">
                <?= $d['absen_id'] ? 'Simpan' : 'Tambah' ?>
              </button>
            </td>
          </form>
        </tr>
      <?php
      }
      ?>
    </tbody>
  </table>
</div>