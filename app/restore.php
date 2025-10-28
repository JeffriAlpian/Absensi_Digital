<?php

// Lokasi file SQL
$sqlFile = 'db-absensi-qr-v4.sql';

// Jika belum ada konfirmasi, tampilkan form konfirmasi
if (!isset($_POST['confirm'])) {
    ?>
    
    <div class="bg-light">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow-lg border-0">
                        <div class="card-body p-4">
                            <h4 class="text-danger mb-3">⚠️ Konfirmasi Restore Database</h4>
                            <p>
                                Apakah Anda yakin akan <b class="text-danger">menimpa data saat ini dengan data contoh</b>?<br>
                                <span class="text-warning">Semua data yang sudah Anda entrykan akan hilang.</span><br>
                                Silakan lakukan backup dulu di menu 
                                <a href="backup_restore.php" class="text-primary fw-bold">backup_restore.php</a>
                                sebelum melanjutkan.
                            </p>
                            <form method="post">
                                <input type="hidden" name="confirm" value="yes">
                                <div class="d-flex justify-content-end mt-4">
                                    <a href="dashboard.php" class="btn btn-secondary me-2">Batal</a>
                                    <button type="submit" class="btn btn-danger">Ya, Lanjutkan Restore</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    exit;
}

// --- Jika user sudah konfirmasi, eksekusi restore ---
if (!file_exists($sqlFile)) {
    die("File $sqlFile tidak ditemukan!");
}

// Baca isi file SQL
$sqlContent = file_get_contents($sqlFile);
if ($sqlContent === false) {
    die("Gagal membaca file SQL.");
}

// Hapus semua tabel di database (RESET)
$result = mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
$res = mysqli_query($conn, "SHOW TABLES");
while ($row = mysqli_fetch_array($res)) {
    mysqli_query($conn, "DROP TABLE IF EXISTS `" . $row[0] . "`");
}
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

// Eksekusi isi file SQL (multi-query)
if (mysqli_multi_query($conn, $sqlContent)) {
    do {
        if ($result = mysqli_store_result($conn)) {
            mysqli_free_result($result);
        }
    } while (mysqli_next_result($conn));

    echo "<div style='padding:20px;font-family:sans-serif'>
            <h3>✅ Database berhasil direset dan diisi dari $sqlFile</h3>
            <a href='dashboard.php'>Kembali ke Dashboard</a>
          </div>";
} else {
    echo "<div style='padding:20px;font-family:sans-serif'>
            <h3>❌ Gagal restore:</h3> " . mysqli_error($conn) . "
            <br><a href='dashboard.php'>Kembali</a>
          </div>";
}

mysqli_close($conn);
