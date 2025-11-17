<?php
// Pastikan hanya admin yang bisa akses
if (($user_role ?? '') !== 'admin') {
    echo "<p class='text-red-500 p-4'>Akses ditolak.</p>";
    exit;
}

// [DIUBAH] Ambil siswa yang BELUM ADA di tabel kartu_rfid
$siswaResult = mysqli_query($conn, "SELECT s.id, s.nama, s.nisn 
                                    FROM siswa s 
                                    LEFT JOIN kartu_rfid r ON s.id = r.siswa_id 
                                    WHERE s.status='aktif' AND r.id IS NULL 
                                    ORDER BY s.nama");

// [DIUBAH] Ambil guru yang BELUM ADA di tabel kartu_rfid
$guruResult = mysqli_query($conn, "SELECT g.id, g.nama, g.nip 
                                  FROM guru g
                                  LEFT JOIN kartu_rfid r ON g.id = r.guru_id
                                  WHERE r.id IS NULL 
                                  ORDER BY g.nama");

// [BARU] Ambil daftar perangkat reader
$deviceResult = mysqli_query($conn, "SELECT id, rfid_name FROM rfid_model ORDER BY rfid_name");

// (Opsional) Ambil data yang SUDAH terdaftar
$siswaTerdaftarResult = mysqli_query($conn, "SELECT s.nama, s.nisn, r.uid 
                                            FROM kartu_rfid r 
                                            JOIN siswa s ON r.siswa_id = s.id 
                                            ORDER BY s.nama");
$guruTerdaftarResult = mysqli_query($conn, "SELECT g.nama, g.nip, r.uid 
                                           FROM kartu_rfid r 
                                           JOIN guru g ON r.guru_id = g.id 
                                           ORDER BY g.nama");

// Tangani pesan feedback dari proses simpan
$status = $_GET['status'] ?? '';
$pesan = '';
$tipe_pesan = 'success';
if ($status === 'sukses') { /* ... (logika pesan sukses) ... */
    $nama = $_GET['nama'] ?? 'User';
    $pesan = "Kartu RFID untuk <strong>" . htmlspecialchars($nama) . "</strong> berhasil didaftarkan.";
} elseif ($status === 'gagal') { /* ... (logika pesan gagal) ... */
    $error = $_GET['error'] ?? 'Terjadi kesalahan tak terduga.';
    $pesan = "Gagal mendaftarkan kartu RFID. Error: " . htmlspecialchars($error);
    $tipe_pesan = 'danger';
} elseif ($status === 'duplikat') { /* ... (logika pesan duplikat) ... */
    $uid = $_GET['uid'] ?? '';
    $pesan = "Gagal mendaftarkan kartu RFID. UID <strong>" . htmlspecialchars($uid) . "</strong> sudah terdaftar.";
    $tipe_pesan = 'danger';
}


?>

<div class="container py-4">

    <header class="bg-success text-white p-4 rounded-3 shadow-sm mb-4">
        <h1 class="h3 mb-0"><i class="fa-solid fa-id-card-clip me-2"></i> Registrasi Kartu RFID</h1>
        <p class="mb-0">Daftarkan kartu RFID baru untuk Siswa dan Guru.</p>
    </header>

    <?php if ($pesan): /* ... (kode alert feedback) ... */ ?>
        <div class="alert alert-<?= $tipe_pesan; ?> alert-dismissible fade show" role="alert">
            <?= $pesan; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

    <?php endif; ?>

    <div class="row g-4">

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fa-solid fa-user-graduate me-2"></i> Registrasi Siswa</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="app/simpan_rfid_baru.php" onsubmit="return validasiForm('formSiswa')">
                        <input type="hidden" name="tipe" value="siswa">

                        <div class="mb-3">
                            <label for="selectSiswa" class="form-label fw-bold">Pilih Siswa</label>
                            <select class="form-select" id="selectSiswa" name="user_id" required>
                                <option value="" selected disabled>... Pilih Nama Siswa ...</option>
                                <?php
                                if ($siswaResult) {
                                    while ($siswa = mysqli_fetch_assoc($siswaResult)) {
                                        echo "<option value='{$siswa['id']}'>" . htmlspecialchars($siswa['nama']) . " (" . htmlspecialchars($siswa['nisn']) . ")</option>";
                                    }
                                }
                                ?>
                            </select>
                            <div class="form-text">Hanya menampilkan siswa aktif yang belum punya kartu.</div>
                        </div>

                        <div class="mb-3">
                            <label for="deviceSiswa" class="form-label fw-bold">Didaftarkan oleh Perangkat</label>
                            <select class="form-select" id="deviceSiswa" name="device_id" required>
                                <option value="" selected disabled>... Pilih Perangkat Reader ...</option>
                                <?php
                                if ($deviceResult) {
                                    mysqli_data_seek($deviceResult, 0); // Reset pointer
                                    while ($device = mysqli_fetch_assoc($deviceResult)) {
                                        echo "<option value='{$device['id']}'>" . htmlspecialchars($device['rfid_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="rfidSiswa" class="form-label fw-bold">Tempelkan Kartu RFID</label>
                            <input type="text" class="form-control form-control-lg" id="rfidSiswa" name="uid"
                                placeholder="UID Kartu akan muncul di sini..." required
                                autocomplete="off" autofocus>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fa-solid fa-save me-2"></i> Simpan RFID Siswa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-info text-dark">
                    <h5 class="mb-0"><i class="fa-solid fa-chalkboard-user me-2"></i> Registrasi Guru</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="app/simpan_rfid_baru.php" onsubmit="return validasiForm('formGuru')">
                        <input type="hidden" name="tipe" value="guru">

                        <div class="mb-3">
                            <label for="selectGuru" class="form-label fw-bold">Pilih Guru</label>
                            <select class="form-select" id="selectGuru" name="user_id" required>
                                <option value="" selected disabled>... Pilih Nama Guru ...</option>
                                <?php
                                if ($guruResult) {
                                    while ($guru = mysqli_fetch_assoc($guruResult)) {
                                        echo "<option value='{$guru['id']}'>" . htmlspecialchars($guru['nama']) . " (" . htmlspecialchars($guru['nip']) . ")</option>";
                                    }
                                }
                                ?>
                            </select>
                            <div class="form-text">Hanya menampilkan guru yang belum punya kartu.</div>
                        </div>

                        <div class="mb-3">
                            <label for="deviceGuru" class="form-label fw-bold">Didaftarkan oleh Perangkat</label>
                            <select class="form-select" id="deviceGuru" name="device_id" required>
                                <option value="" selected disabled>... Pilih Perangkat Reader ...</option>
                                <?php
                                if ($deviceResult) {
                                    mysqli_data_seek($deviceResult, 0); // Reset pointer
                                    while ($device = mysqli_fetch_assoc($deviceResult)) {
                                        echo "<option value='{$device['id']}'>" . htmlspecialchars($device['rfid_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="rfidGuru" class="form-label fw-bold">Tempelkan Kartu RFID</label>
                            <input type="text" class="form-control form-control-lg" id="rfidGuru" name="uid"
                                placeholder="UID Kartu akan muncul di sini..." required
                                autocomplete="off">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-info btn-lg">
                                <i class="fa-solid fa-save me-2"></i> Simpan RFID Guru
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Inisialisasi Select2 (Jika Anda menambahkannya)
        // $('.form-select').select2({
        //     theme: "bootstrap-5" // Jika pakai tema bootstrap 5 untuk select2
        // });

        // Pindahkan focus ke input RFID saat nama dipilih
        const selectSiswa = document.getElementById('selectSiswa');
        const rfidSiswa = document.getElementById('rfidSiswa');
        const selectGuru = document.getElementById('selectGuru');
        const rfidGuru = document.getElementById('rfidGuru');

        if (selectSiswa) {
            selectSiswa.addEventListener('change', function() {
                rfidSiswa.focus();
            });
        }

        if (selectGuru) {
            selectGuru.addEventListener('change', function() {
                rfidGuru.focus();
            });
        }
    });

    // Validasi sederhana agar UID tidak dikirim saat form di-submit oleh reader
    function validasiForm(formId) {
        let input;
        if (formId === 'formSiswa') {
            input = document.getElementById('rfidSiswa');
        } else {
            input = document.getElementById('rfidGuru');
        }

        // Cegah submit jika input RFID kosong (jika 'required' gagal)
        if (input.value.trim() === "") {
            alert('Harap tempelkan kartu RFID.');
            input.focus();
            return false;
        }
        return true;
    }
</script>