<?php

include 'config.php';
require 'vendor/phpqrcode/qrlib.php';

// Aktifkan siswa kembali
if (isset($_GET['aktifkan'])) {
    $id = intval($_GET['aktifkan']);
    mysqli_query($conn, "UPDATE siswa SET status='aktif' WHERE id=$id");
    header("Location: ?page=siswa_keluar");
    exit;
}

// Ambil filter kelas (default semua)
$kelasFilter = isset($_GET['kelas']) ? trim($_GET['kelas']) : '';
?>

<div class="container">

  <header class="bg-green-600 text-white p-4 mb-3 shadow-md relative text-center lg:text-center">
    <button id="menu-toggle" class="lg:hidden absolute left-4 top-1/2 -translate-y-1/2 p-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-white">
      <i class="fa-solid fa-bars fa-fw text-xl"></i>
    </button>
    <h1 class="text-2xl lg:text-3xl font-bold">Daftar Siswa Keluar</h1>
  </header>

  <a href="?page=siswa" class="btn btn-secondary mb-3">← Kembali ke Siswa Aktif</a>

  <!-- Form Filter Kelas -->
  <form method="get" class="row g-2 mb-3">
    <div class="col-8 col-md-4">
      <select name="kelas" class="form-select">
        <option value="">-- Semua Kelas --</option>
        <?php
        $kelasList = mysqli_query($conn, "SELECT DISTINCT kelas FROM siswa WHERE status='keluar' ORDER BY kelas ASC");
        while ($k = mysqli_fetch_assoc($kelasList)) {
            $selected = ($kelasFilter === $k['kelas']) ? 'selected' : '';
            echo "<option value='{$k['kelas']}' $selected>{$k['kelas']}</option>";
        }
        ?>
      </select>
    </div>
    <div class="col-4 col-md-2">
      <button type="submit" class="btn btn-primary w-100">Filter</button>
    </div>
  </form>

  <!-- Tabel Data -->
  <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
      <thead class="table-light text-center">
        <tr>
          <th>NIS</th>
          <th>NISN</th>
          <th>Nama</th>
          <th>Kelas</th>
          <th>QR Code</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php
        // Query filter
        $where = "status='keluar'";
        if ($kelasFilter !== '') {
            $kelasSafe = mysqli_real_escape_string($conn, $kelasFilter);
            $where .= " AND kelas='$kelasSafe'";
        }

        $q = mysqli_query($conn, "SELECT * FROM siswa WHERE $where ORDER BY nama ASC");
        if (mysqli_num_rows($q) > 0) {
            while ($row = mysqli_fetch_assoc($q)) {
                echo "<tr>
                    <td>{$row['nis']}</td>
                    <td>{$row['nisn']}</td>
                    <td>{$row['nama']}</td>
                    <td>{$row['kelas']}</td>
                    <td class='text-center'><img src='assets/qr/{$row['nisn']}.png' width='50'></td>
                    <td class='text-center'>
                      <a href='?page=siswa_keluar?aktifkan={$row['id']}' class='btn btn-success btn-sm' onclick='return confirm(\"Aktifkan kembali siswa ini?\")'>Aktifkan</a>
                    </td>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='6' class='text-center text-muted'>Tidak ada siswa keluar</td></tr>";
        }
        ?>
      </tbody>
    </table>
  </div>

</div>

