<?php
include 'config.php'; // Path ke koneksi DB
session_start();

// Cek Sesi Admin
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../dashboard.php?page=tambah_rfid&status=gagal&error=" . urlencode("Akses ditolak."));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard.php?page=tambah_rfid");
    exit;
}

// Ambil data
$tipe = $_POST['tipe'] ?? '';
$user_id = intval($_POST['user_id'] ?? 0);
$device_id = intval($_POST['device_id'] ?? 0);
$uid = trim($_POST['uid'] ?? ''); // Nama input di form adalah 'uid'

// Validasi
if (empty($tipe) || empty($user_id) || empty($device_id) || empty($uid)) {
    header("Location: ../dashboard.php?page=tambah_rfid&status=gagal&error=" . urlencode("Data tidak lengkap."));
    exit;
}

// Siapkan kolom berdasarkan tipe
$kolom_user_id = null;
$nama_user = "Data";

if ($tipe === 'siswa') {
    $kolom_user_id = 'siswa_id';
    $q = mysqli_query($conn, "SELECT nama FROM siswa WHERE id = $user_id");
    $nama_user = mysqli_fetch_assoc($q)['nama'] ?? 'Siswa';
} elseif ($tipe === 'guru') {
    $kolom_user_id = 'guru_id';
    $q = mysqli_query($conn, "SELECT nama FROM guru WHERE id = $user_id");
    $nama_user = mysqli_fetch_assoc($q)['nama'] ?? 'Guru';
} else {
    header("Location: ../dashboard.php?page=tambah_rfid&status=gagal&error=" . urlencode("Tipe user tidak valid."));
    exit;
}

// Siapkan Prepared Statement untuk INSERT
// registed_at akan diisi NOW() oleh DB
$sql = "INSERT INTO kartu_rfid (uid, $kolom_user_id, device_id, registed_at) VALUES (?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    header("Location: ../dashboard.php?page=tambah_rfid&status=gagal&error=" . urlencode("Gagal prepare statement: " . $conn->error));
    exit;
}

$stmt->bind_param("sii", $uid, $user_id, $device_id);

// Eksekusi dan tangani error
if ($stmt->execute()) {
    // Berhasil
    header("Location: ../dashboard.php?page=tambah_rfid&status=sukses&nama=" . urlencode($nama_user));
    exit;
} else {
    // Cek duplikat UID (Error code 1062)
    if ($conn->errno == 1062) {
        // Cari siapa pemilik UID duplikat
        $uid_safe = mysqli_real_escape_string($conn, $uid);
        $nama_duplikat = "Seseorang";
        $qCek = mysqli_query($conn, "SELECT nama FROM siswa s JOIN kartu_rfid
     r ON s.id = r.siswa_id WHERE r.uid = '$uid_safe'
                                     UNION 
                                     SELECT nama FROM guru g JOIN kartu_rfid
                                     r ON g.id = r.guru_id WHERE r.uid = '$uid_safe'
                                     LIMIT 1");
        if ($qCek && mysqli_num_rows($qCek) > 0) {
            $nama_duplikat = mysqli_fetch_assoc($qCek)['nama'];
        }
        
        header("Location: ../dashboard.php?page=tambah_rfid&status=duplikat&uid=" . urlencode($uid) . "&nama=" . urlencode($nama_duplikat));
        exit;
    } else {
        // Error SQL lainnya
        header("Location: ../dashboard.php?page=tambah_rfid&status=gagal&error=" . urlencode($stmt->error));
        exit;
    }
}

$stmt->close();
$conn->close();
?>