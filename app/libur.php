<?php


// Tambah hari libur
if (isset($_POST['simpan'])) {
  $tgl = $_POST['tanggal'];
  $desc = $_POST['deskripsi'];
  mysqli_query($conn, "INSERT INTO hari_libur (tanggal, deskripsi) VALUES ('$tgl', '$desc')");
  echo "<script>window.location.href = '?page=libur';</script>";
  exit;
}

// Hapus hari libur
if (isset($_GET['hapus'])) {
  $id = $_GET['hapus'];
  mysqli_query($conn, "DELETE FROM hari_libur WHERE id=$id");
  echo "<script>window.location.href = '?page=libur';</script>";
  exit;
}
?>


<div class="container mt-4">
  <h2>Pengaturan Hari Libur</h2>

  <!-- Kotak informasi hari Minggu -->
  <div class="alert alert-warning p-2 mb-3" role="alert" style="font-weight: bold;">
    📅 Hari Minggu sudah otomatis LIBUR / tanggal merah.
  </div>

  <a href="dashboard.php" class="btn btn-secondary mb-3">← Kembali</a>

  <form method="post" class="row g-2 mb-4">
    <div class="col-md-3">
      <input type="date" name="tanggal" class="form-control" required>
    </div>
    <div class="col-md-6">
      <input type="text" name="deskripsi" class="form-control" placeholder="Keterangan libur" required>
    </div>
    <div class="col-md-3">
      <button name="simpan" class="btn btn-primary w-100">Simpan</button>
    </div>
  </form>

  <table class="table table-bordered table-sm">
    <thead>
      <tr>
        <th>Tanggal</th>
        <th>Deskripsi</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $q = mysqli_query($conn, "SELECT * FROM hari_libur ORDER BY tanggal DESC");
      while ($r = mysqli_fetch_assoc($q)) {
        echo "<tr>
          <td>{$r['tanggal']}</td>
          <td>{$r['deskripsi']}</td>
          <td>
            <a href='?page=libur&hapus={$r['id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Yakin hapus?\")'>Hapus</a>
          </td>
        </tr>";
      }
      ?>
    </tbody>
  </table>

