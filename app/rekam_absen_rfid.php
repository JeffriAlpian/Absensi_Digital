<?php
// Set header di paling atas
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Izinkan akses dari mana saja
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// [PERBAIKAN] Path include diperbaiki (asumsi file ini ada di dalam folder 'app')
include 'config.php'; // Koneksi DB ($conn)

// Atur timezone default
date_default_timezone_set('Asia/Jakarta');

// ------------------------------------------------------------------
// FUNGSI KIRIM WA (dengan perbaikan bug cURL)
// ------------------------------------------------------------------
function kirim_wa($conn, $no_wa, $pesan)
{
    // ... (Logika normalisasi $no_wa Anda sudah benar) ...
    $no_wa = preg_replace('/[^0-9]/', '', $no_wa);
    if (substr($no_wa, 0, 1) === "0") {
        $no_wa = "+62" . substr($no_wa, 1);
    } elseif (substr($no_wa, 0, 2) === "62") {
        $no_wa = "+" . $no_wa;
    } elseif (substr($no_wa, 0, 3) !== "+62") {
        $no_wa = "";
    }

    // Ambil secret key
    $secretKey = "";
    if (!$conn) { return "Error: Koneksi DB gagal."; }
    $qKey = mysqli_query($conn, "SELECT key_wa_sidobe FROM profil_sekolah LIMIT 1");
    if ($qKey && mysqli_num_rows($qKey) > 0) {
        $rowKey = mysqli_fetch_assoc($qKey);
        $secretKey = $rowKey['key_wa_sidobe'] ?? "";
    }

    if (empty($no_wa)) { return "Nomor WA tidak valid."; }
    if (empty($secretKey)) { return "Secret key WA tidak ada."; }

    $data = ['phone' => $no_wa, 'message' => $pesan];
    $ch = curl_init('https://api.sidobe.com/wa/v1/send-message');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'X-Secret-Key: ' . $secretKey]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    // [PERBAIKAN] Set timeout SEBELUM curl_exec
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set timeout 10 detik

    $response = curl_exec($ch);
    $curl_error = curl_error($ch); // Ambil error cURL jika ada
    curl_close($ch);

    if ($response === false) { // Jika cURL gagal (misal timeout)
        file_put_contents('error_log_wa.txt', date('Y-m-d H:i:s') . " - cURL Error: " . $curl_error . PHP_EOL, FILE_APPEND);
        return "⚠️ Gagal koneksi ke API WA.";
    }

    $resData = json_decode($response, true);
    if ($resData && isset($resData['is_success']) && $resData['is_success']) {
        return "📲 WA terkirim."; // Sukses
    } else {
        file_put_contents('error_log_wa.txt', date('Y-m-d H:i:s') . " - API WA Error: " . $response . PHP_EOL, FILE_APPEND);
        return "⚠️ Gagal kirim WA.";
    }
    // [PERBAIKAN] Baris curl_setopt dan curl_errno yang error setelah curl_close() DIHAPUS.
}
// ------------------------------------------------------------------
// AKHIR FUNGSI WA
// ------------------------------------------------------------------

// Inisialisasi variabel respon
$status = "error";
$msg = "Request tidak valid";
$nama = "Unknown";
$user_id = null;
$user_type = null; // 'siswa' atau 'guru'
$wa_status = ""; // Status pengiriman WA

// Ambil data JSON dari body request
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['api_key']) || empty($data['uid'])) {
    echo json_encode(["status" => "error", "message" => "Data JSON (api_key/uid) tidak lengkap"]);
    exit;
}

$api_key = trim($data['api_key'] ?? '');
$uid     = trim($data['uid'] ?? '');
$waktu   = date('Y-m-d H:i:s'); // Waktu server

// 1. Validasi API key (dari tabel rfid_model)
$stmt_dev = $conn->prepare("SELECT id FROM rfid_model WHERE api_key = ?");
$stmt_dev->bind_param("s", $api_key);
$stmt_dev->execute();
$res_dev = $stmt_dev->get_result();
if ($res_dev->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "API Key tidak valid"]);
    exit;
}
$device = $res_dev->fetch_assoc();
$device_id = $device['id'];
$stmt_dev->close();

// Update last_seen (Opsional)
$conn->query("UPDATE rfid_model SET last_seen = NOW() WHERE id = $device_id");

// ------------------------------------------------------------------
// 2. Cari Siswa atau Guru berdasarkan UID Kartu (Tabel 'kartu_rfid')
// ------------------------------------------------------------------
$stmt_user = $conn->prepare("
    SELECT 
        r.id as kartu_rfid_id, 
        r.siswa_id, 
        r.guru_id,
        s.nama as nama_siswa, 
        s.id_kelas, 
        s.no_wa as no_wa_siswa,
        g.nama as nama_guru,
        g.no_wa as no_wa_guru
    FROM kartu_rfid r -- Menggunakan tabel kartu_rfid
    LEFT JOIN siswa s ON r.siswa_id = s.id AND s.status = 'aktif'
    LEFT JOIN guru g ON r.guru_id = g.id
    WHERE r.uid = ?
    LIMIT 1
");
$stmt_user->bind_param("s", $uid);
$stmt_user->execute();
$res_user = $stmt_user->get_result();

if ($res_user->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Kartu RFID (UID: $uid) belum terdaftar"]);
    exit;
}

$user = $res_user->fetch_assoc();

// Tentukan user_type, id, nama, dan no_wa
if ($user['siswa_id'] !== null) {
    $user_type = 'siswa';
    $user_id   = $user['siswa_id'];
    $nama      = $user['nama_siswa'];
    $no_wa     = $user['no_wa_siswa'] ?? '';
    $kelas_id  = $user['id_kelas'];
} elseif ($user['guru_id'] !== null) {
    $user_type = 'guru';
    $user_id   = $user['guru_id'];
    $nama      = $user['nama_guru'];
    $no_wa     = $user['no_wa_guru'] ?? '';
    $kelas_id  = null;
} else {
    echo json_encode(["status" => "error", "message" => "Kartu RFID terdaftar tapi tidak terhubung ke user."]);
    exit;
}
$stmt_user->close();

// ------------------------------------------------------------------
// 3. LOGIKA ABSENSI (BERCABANG)
// ------------------------------------------------------------------

$now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
$tanggal = $now->format('Y-m-d');
$jam = $now->format('H:i:s');
$pesan_wa = ""; // Reset pesan WA

if ($user_type === 'siswa') {
    // === LOGIKA SISWA ===
    
    // Ambil jam masuk/pulang dari kelas
    $q_jam_kelas = mysqli_query($conn, "SELECT jam_masuk, jam_pulang FROM kelas WHERE id = '$kelas_id' LIMIT 1");
    $jam_kelas = $q_jam_kelas ? mysqli_fetch_assoc($q_jam_kelas) : null;
    $jam_masuk_maximal = $jam_kelas['jam_masuk'] ?? '07:00:00';
    $jam_pulang_minimal = $jam_kelas['jam_pulang'] ?? '13:00:00';

    // Cek absensi hari ini
    $cek = $conn->prepare("SELECT id, jam, jam_pulang FROM absensi WHERE siswa_id = ? AND tanggal = ?");
    $cek->bind_param("is", $user_id, $tanggal);
    $cek->execute();
    $r = $cek->get_result();

    if ($r->num_rows == 0) {
        // --- LOGIKA MASUK SISWA ---
        $status_absensi = "H";
        $keterangan = (strtotime($jam) > strtotime($jam_masuk_maximal)) ? 'Terlambat' : '';
        
        $stmt_insert = $conn->prepare("INSERT INTO absensi (siswa_id, device_id, tanggal, jam, status, keterangan) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_insert->bind_param("iissss", $user_id, $device_id, $tanggal, $jam, $status_absensi, $keterangan);
        $stmt_insert->execute();
        $stmt_insert->close();
        
        $status = "masuk";
        $msg = "Absen masuk " . ($keterangan == 'Terlambat' ? 'Terlambat' : 'berhasil');
        $pesan_wa = "Info Absen: Ananda *$nama* telah *ABSEN MASUK" . ($keterangan == 'Terlambat' ? ' (TERLAMBAT)' : '') . "* pada $tanggal jam $jam. Terima kasih.";

    } else {
        // --- LOGIKA PULANG SISWA ---
        $row = $r->fetch_assoc();
        if (empty($row['jam_pulang'])) {
            if (strtotime($jam) < strtotime($jam_pulang_minimal)) {
                $status = "sudah_masuk";
                $msg = "Sudah absen masuk. Belum jam pulang (Min: $jam_pulang_minimal).";
            } else {
                $stmt_update = $conn->prepare("UPDATE absensi SET jam_pulang = ?, device_id_pulang = ? WHERE id = ?");
                $stmt_update->bind_param("sii", $jam, $device_id, $row['id']);
                $stmt_update->execute();
                $stmt_update->close();
                
                $status = "pulang";
                $msg = "Absen pulang berhasil";
                $pesan_wa = "Info Absen: Ananda *$nama* telah *ABSEN PULANG* pada $tanggal jam $jam. Terima kasih.";
            }
        } else {
            $status = "sudah_masuk_pulang";
            $msg = "Sudah absen masuk dan pulang hari ini";
        }
    }
    $cek->close();

} elseif ($user_type === 'guru') {
    // === LOGIKA GURU ===

    // Ambil jam masuk/pulang GURU
    $qJamGuru = mysqli_query($conn, "SELECT jam_masuk_guru, jam_pulang_guru FROM profil_sekolah LIMIT 1");
    $jam_guru_default = $qJamGuru ? mysqli_fetch_assoc($qJamGuru) : null;
    $jam_masuk_maximal = $jam_guru_default['jam_masuk_guru'] ?? '08:00:00';
    $jam_pulang_minimal = $jam_guru_default['jam_pulang_guru'] ?? '14:00:00';

    // Cek absensi hari ini
    $cek = $conn->prepare("SELECT id, jam, jam_pulang FROM absensi_guru WHERE guru_id = ? AND tanggal = ?");
    $cek->bind_param("is", $user_id, $tanggal);
    $cek->execute();
    $r = $cek->get_result();

    if ($r->num_rows == 0) {
        // --- LOGIKA MASUK GURU ---
        $status_absensi = "H";
        $keterangan = (strtotime($jam) > strtotime($jam_masuk_maximal)) ? 'Terlambat' : '';
        
        $stmt_insert = $conn->prepare("INSERT INTO absensi_guru (guru_id, device_id, tanggal, jam, status, keterangan) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_insert->bind_param("iissss", $user_id, $device_id, $tanggal, $jam, $status_absensi, $keterangan);
        $stmt_insert->execute();
        $stmt_insert->close();

        $status = "masuk";
        $msg = "Absen masuk " . ($keterangan == 'Terlambat' ? 'Terlambat' : 'berhasil');
        $pesan_wa = "Info Absen: Bapak/Ibu *$nama* telah *ABSEN MASUK" . ($keterangan == 'Terlambat' ? ' (TERLAMBAT)' : '') . "* pada $tanggal jam $jam. Terima kasih.";
    
    } else {
        // --- LOGIKA PULANG GURU ---
        $row = $r->fetch_assoc();
        if (empty($row['jam_pulang'])) {
            if (strtotime($jam) < strtotime($jam_pulang_minimal)) {
                $status = "sudah_masuk";
                $msg = "Sudah absen masuk. Belum jam pulang (Min: $jam_pulang_minimal).";
            } else {
                $stmt_update = $conn->prepare("UPDATE absensi_guru SET jam_pulang = ?, device_id = ? WHERE id = ?");
                $stmt_update->bind_param("sii", $jam, $device_id, $row['id']);
                $stmt_update->execute();
                $stmt_update->close();
                
                $status = "pulang";
                $msg = "Absen pulang berhasil";
                $pesan_wa = "Info Absen: Bapak/Ibu *$nama* telah *ABSEN PULANG* pada $tanggal jam $jam. Terima kasih.";
            }
        } else {
            $status = "sudah_masuk_pulang";
            $msg = "Sudah absen masuk dan pulang hari ini";
        }
    }
    $cek->close();
}

// ------------------------------------------------------------------
// 4. KIRIM WA (JIKA PERLU)
// ------------------------------------------------------------------
if (!empty($pesan_wa)) {
    $wa_status = kirim_wa($conn, $no_wa, $pesan_wa);
}

// ------------------------------------------------------------------
// 5. KIRIM RESPON JSON KE PERANGKAT IoT
// ------------------------------------------------------------------
echo json_encode([
    "status" => $status,
    "message" => $msg,
    "nama" => $nama,
    "user_id" => $user_id,
    "user_type" => $user_type,
    "tanggal" => $tanggal,
    "jam" => $jam,
    "wa_status" => $wa_status
]);

$conn->close();
exit;
?>