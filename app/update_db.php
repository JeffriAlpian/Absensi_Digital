

<style>
    
    h2 { text-align: center; }
    .log {
        background: #fff;
        margin: 6px 0;
        padding: 10px;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        font-size: 14px;
        word-wrap: break-word;
    }
    .success { background: #d4edda; color: #155724; }
    .error   { background: #f8d7da; color: #721c24; }
    .btn {
        display: block;
        margin: 20px auto 0;
        padding: 10px 18px;
        border-radius: 6px;
        background: #007bff;
        color: white;
        text-decoration: none;
        font-size: 16px;
        text-align: center;
        max-width: 250px;
    }
    .btn:hover { background: #0056b3; }
</style>

<h2>🔧 Proses Pembaruan Database</h2>
<div>
<?php
/* === Tambah kolom no_wa di tabel siswa === */
$check = mysqli_query($conn, "SHOW COLUMNS FROM siswa LIKE 'no_wa'");
if (mysqli_num_rows($check) == 0) {
    $sql = "ALTER TABLE siswa ADD no_wa VARCHAR(20) AFTER status";
    if (mysqli_query($conn, $sql)) {
        echo "<div class='log success'>Kolom <b>no_wa</b> berhasil ditambahkan!</div>";
    } else {
        echo "<div class='log error'>Error tambah kolom no_wa: " . mysqli_error($conn) . "</div>";
    }
} else {
    echo "<div class='log'>Kolom <b>no_wa</b> sudah ada.</div>";
}

/* === Tambah kolom nama di tabel users === */
$check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'nama'");
if (mysqli_num_rows($check) == 0) {
    $sql = "ALTER TABLE users ADD nama VARCHAR(100) AFTER password";
    if (mysqli_query($conn, $sql)) {
        echo "<div class='log success'>Kolom <b>nama</b> berhasil ditambahkan!</div>";
    } else {
        echo "<div class='log error'>Error tambah kolom nama: " . mysqli_error($conn) . "</div>";
    }
} else {
    echo "<div class='log'>Kolom <b>nama</b> sudah ada.</div>";
}

/* === Tambah kolom role di tabel users === */
$check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'role'");
if (mysqli_num_rows($check) == 0) {
    $sql = "ALTER TABLE users ADD role VARCHAR(50) AFTER nama";
    if (mysqli_query($conn, $sql)) {
        echo "<div class='log success'>Kolom <b>role</b> berhasil ditambahkan!</div>";
    } else {
        echo "<div class='log error'>Error tambah kolom role: " . mysqli_error($conn) . "</div>";
    }
} else {
    echo "<div class='log'>Kolom <b>role</b> sudah ada.</div>";
}

/* === Tambah kolom key_wa_sidobe di tabel profil_sekolah === */
$check = mysqli_query($conn, "SHOW COLUMNS FROM profil_sekolah LIKE 'key_wa_sidobe'");
if (mysqli_num_rows($check) == 0) {
    $sql = "ALTER TABLE profil_sekolah ADD key_wa_sidobe VARCHAR(255) AFTER nama_sekolah";
    if (mysqli_query($conn, $sql)) {
        echo "<div class='log success'>Kolom <b>key_wa_sidobe</b> berhasil ditambahkan!</div>";
    } else {
        echo "<div class='log error'>Error tambah kolom key_wa_sidobe: " . mysqli_error($conn) . "</div>";
    }
} else {
    echo "<div class='log'>Kolom <b>key_wa_sidobe</b> sudah ada.</div>";
}

/* === Update role user admin dan wali === */
mysqli_query($conn, "UPDATE users SET role = 'admin' WHERE username = 'admin'");
mysqli_query($conn, "UPDATE users SET role = 'wali' WHERE username = 'wali'");

/* === Tambahkan user guru jika belum ada === */
$check = mysqli_query($conn, "SELECT * FROM users WHERE username='guru'");
if (mysqli_num_rows($check) == 0) {
    $sql = "INSERT INTO users (username, password, nama, role)
            VALUES ('guru', MD5('guru'), 'guru', 'guru')";
    if (mysqli_query($conn, $sql)) {
        echo "<div class='log success'>User <b>guru</b> berhasil ditambahkan!</div>";
    } else {
        echo "<div class='log error'>Error tambah user guru: " . mysqli_error($conn) . "</div>";
    }
} else {
    echo "<div class='log'>User <b>guru</b> sudah ada.</div>";
}
?>
</div>
<a href="dashboard.php" class="btn">⬅️ Kembali ke Dashboard</a>

