<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
include 'config.php'; // Database connection


$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['api_key']) || empty($data['uid'])) {
    echo json_encode(["status" => "error", "message" => "Data JSON tidak valid"]);
    exit;
}

$api_key  = trim($data['api_key'] ?? '');
$uid      = trim($data['uid'] ?? '');
$waktu    = date('Y-m-d H:i:s'); // waktu server (bisa disesuaikan)

// Validasi API key
$stmt = $conn->prepare("SELECT id FROM rfid_model WHERE api_key = ?");
$stmt->bind_param("s", $api_key);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "API Key tidak valid"]);
    exit;
}
$device = $res->fetch_assoc();
$device_id = $device['id'];

// Update last_seen
$conn->query("UPDATE rfid_model SET last_seen = NOW() WHERE id = $device_id");

// Cari siswa berdasarkan UID kartu RFID
$stmt = $conn->prepare("SELECT siswa_id, s.nama, id_kelas, s.no_wa FROM kartu_rfid k JOIN siswa s ON k.siswa_id=s.id WHERE uid = ?");
$stmt->bind_param("s", $uid);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Kartu RFID belum terdaftar"]);
    exit;
}

$s = $res->fetch_assoc();
$siswa_id = $s['siswa_id'] ?? null;
$nama     = $s['nama'];
$kelas     = $s['id_kelas'];
$no_wa    = $s['no_wa'] ?? '';

// ------------------------------------------------------------------
// ⚙️ FUNGSI UNTUK MENGIRIM WA (Saya pindahkan ke fungsi agar rapi)
// ------------------------------------------------------------------
function kirim_wa($conn, $no_wa, $pesan)
{
    // 🔧 Normalisasi nomor WA
    $no_wa = preg_replace('/[^0-9]/', '', $no_wa); // hanya angka
    if (substr($no_wa, 0, 1) === "0") {
        $no_wa = "+62" . substr($no_wa, 1);
    } elseif (substr($no_wa, 0, 2) === "62") {
        $no_wa = "+" . $no_wa;
    } elseif (substr($no_wa, 0, 3) !== "+62") {
        $no_wa = "";
    }

    // ✅ Ambil secret key dari tabel profil_sekolah
    $secretKey = "";
    $qKey = mysqli_query($conn, "SELECT key_wa_sidobe FROM profil_sekolah LIMIT 1");
    if ($qKey && mysqli_num_rows($qKey) > 0) {
        $rowKey = mysqli_fetch_assoc($qKey);
        $secretKey = $rowKey['key_wa_sidobe'] ?? "";
    }

    // ✅ Kirim WA otomatis hanya jika valid
    if (empty($no_wa)) {
        return "Nomor WA belum diisi atau tidak valid.";
    }
    if (empty($secretKey)) {
        return "⚠️ Secret key WA tidak ditemukan di tabel profil_sekolah.";
    }

    $data = [
        'phone' => $no_wa,  // format +628xxxx
        'message' => $pesan
    ];

    $ch = curl_init('https://api.sidobe.com/wa/v1/send-message');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Secret-Key: ' . $secretKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    $resData = json_decode($response, true);
    if ($resData && isset($resData['is_success']) && $resData['is_success']) {
        return "📲 WA berhasil dikirim ke $no_wa";
    } else {
        return "⚠️ Gagal kirim WA. Response: " . $response;
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // maksimal 10 detik
        if (curl_errno($ch)) {
            file_put_contents('error_log_wa.txt', date('Y-m-d H:i:s') . " - " . curl_error($ch) . PHP_EOL, FILE_APPEND);
        }
    }
}
// ------------------------------------------------------------------
// AKHIR FUNGSI WA
// ------------------------------------------------------------------


// Dapatkan tanggal dan jam sekarang
$tanggal = date('Y-m-d');
// Pastikan timezone sesuai (ubah ke timezone yang diinginkan)
date_default_timezone_set('Asia/Jakarta');

// Gunakan DateTime agar tanggal & jam konsisten
$now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
$tanggal = $now->format('Y-m-d'); // overwrite jika perlu
$jam = $now->format('H:i:s');


// Cek apakah siswa sudah absen hari ini
$cek = $conn->prepare("SELECT id, jam, jam_pulang FROM absensi WHERE siswa_id = ? AND tanggal = ?");
$cek->bind_param("is", $siswa_id, $tanggal);
$cek->execute();
$r = $cek->get_result();

if ($r->num_rows == 0) {

    // Jam masuk dari tabel kelas
    $q_jam_masuk = mysqli_query($conn, "SELECT jam_masuk FROM kelas WHERE id = '$kelas' LIMIT 1");
    $jam_masuk_maximal = "08:00:00"; // Waktu default jika tabel jam_absensi kosong

    if ($q_jam_masuk && mysqli_num_rows($q_jam_masuk) > 0) {
        $d_jam = mysqli_fetch_assoc($q_jam_masuk);
        if (!empty($d_jam['jam_masuk'])) {
            $jam_masuk_maximal = $d_jam['jam_masuk'];
        }
    }

    // Belum ada → input sebagai absen masuk
    $status = "H";
    $keterangan = (strtotime($jam) > strtotime($jam_masuk_maximal)) ? 'Terlambat' : 'Hadir';
    $stmt = $conn->prepare("INSERT INTO absensi (siswa_id, device_id, tanggal, jam, status, keterangan) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissss", $siswa_id, $device_id, $tanggal, $jam, $status, $keterangan);
    $stmt->execute();
    if ($keterangan == 'Terlambat') {
        $status .= " (Terlambat)";
    }
    $msg = "Absen masuk berhasil ($status)";

    // Buat pesan WA untuk "PULANG"
    $pesan = "Assalamualaikum Wr. Wb.\n\n"
        . "Orang tua/wali dari $nama.\n\n"
        . "Siswa/i telah melakukan *ABSENSI MASUK* pada "
        . date("d-m-Y H:i")
        . "\n\nMohon doanya selalu agar ananda dimudahkan dalam belajar dan beraktivitas."
        . "\nAtas perhatian-nya kami ucapkan terimakasih."
        . "\n\nWassalamualaikum Wr. Wb.";

    // Kirim WA
    $wa_status = kirim_wa($conn, $no_wa, $pesan);
} else {
    // Sudah ada → update jam_pulang

    $q_jam_pulang = mysqli_query($conn, "SELECT jam_pulang FROM kelas WHERE id = '$kelas' LIMIT 1");
    $jam_pulang_minimal = "14:00:00"; // Waktu default jika tabel jam_absensi kosong

    if ($q_jam_pulang && mysqli_num_rows($q_jam_pulang) > 0) {
        $d_jam = mysqli_fetch_assoc($q_jam_pulang);
        if (!empty($d_jam['jam_pulang'])) {
            $jam_pulang_minimal = $d_jam['jam_pulang'];
        }
    }

    $row = $r->fetch_assoc();
    if (empty($row['jam_pulang'])) {

        if ($jam < $jam_pulang_minimal) {
            echo json_encode([
                "message" => "$nama sudah absen masuk. Belum waktunya pulang (Jam pulang: $jam_pulang_minimal). sekarang jam $jam.",
            ]);
            exit;
        } else {

            $stmt = $conn->prepare("UPDATE absensi SET jam_pulang = ?, keterangan = 'Absen pulang' WHERE id = ?");
            $stmt->bind_param("si", $jam, $row['id']);
            $stmt->execute();
            $msg = "Absen pulang berhasil";

            // Buat pesan WA untuk "PULANG"
            $pesan = "Assalamualaikum Wr. Wb.\n\n"
                . "Orang tua/wali dari $nama.\n\n"
                . "Siswa/i telah melakukan *ABSENSI PULANG* pada "
                . date("d-m-Y H:i")
                . "\n\nMohon doanya selalu agar ananda dimudahkan dalam belajar dan beraktivitas."
                . "\nAtas perhatian-nya kami ucapkan terimakasih."
                . "\n\nWassalamualaikum Wr. Wb.";

            // Kirim WA
            $wa_status = kirim_wa($conn, $no_wa, $pesan);
        }
    } else {
        $msg = "Sudah absen masuk dan pulang hari ini";
    }
}

// Response ke ESP32
echo json_encode([
    "status" => "ok",
    "message" => $msg,
    "siswa_id" => $siswa_id,
    "tanggal" => $tanggal,
    "jam" => $jam
]);
