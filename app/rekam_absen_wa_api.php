<?php
include "config.php";
session_start();
// ✅ Atur timezone ke WIB (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');

// ------------------------------------------------------------------
// ⚙️ FUNGSI UNTUK MENGIRIM WA (Saya pindahkan ke fungsi agar rapi)
// ------------------------------------------------------------------
function kirim_wa($conn, $no_wa, $pesan) {
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
    }
}
// ------------------------------------------------------------------
// AKHIR FUNGSI WA
// ------------------------------------------------------------------


// Pastikan hanya role tertentu yang bisa mengakses
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'guru'])) {
    echo json_encode(["message" => "Unauthorized"]);
    exit;
}

// Pastikan ada parameter NISN
if (!isset($_GET['nisn'])) {
    echo json_encode(["message" => "NISN tidak ditemukan"]);
    exit;
}

$nisn = $_GET['nisn'];

// 🔎 Cari data siswa + nomor WA
$sql = "SELECT id, nama, no_wa FROM siswa WHERE nisn='$nisn' LIMIT 1";
$res = mysqli_query($conn, $sql);

if (!$res || mysqli_num_rows($res) == 0) {
    echo json_encode(["message" => "❌ Siswa tidak ditemukan"]);
    exit;
}

$s = mysqli_fetch_assoc($res);
$siswa_id = $s['id'];
$nama     = $s['nama'];
$no_wa    = $s['no_wa'] ?? '';

// Siapkan variabel waktu
$tanggal = date("Y-m-d");
$jam     = date("H:i:s");
$jam_absen = date("H:i");

// ------------------------------------------------------------------
// 🔄 LOGIKA BARU UNTUK MASUK & PULANG
// ------------------------------------------------------------------

// 1️⃣ Cek apakah siswa sudah ada datanya HARI INI
$sql_cek = "SELECT id, jam, jam_pulang FROM absensi WHERE siswa_id='$siswa_id' AND tanggal='$tanggal' LIMIT 1";
$cek = mysqli_query($conn, $sql_cek);

if (mysqli_num_rows($cek) == 0) {
    // === [LOGIKA 1: ABSEN MASUK] ===
    // Belum ada data, ini adalah Absen Masuk.
    
    $status  = "H"; // Hadir
    mysqli_query($conn, "INSERT INTO absensi (siswa_id, tanggal, jam, status) 
                         VALUES ('$siswa_id', '$tanggal', '$jam', '$status')");

    // Buat pesan WA untuk "MASUK"
    $pesan = "Assalamualaikum Wr. Wb.\n\n"
            . "Orang tua/wali dari $nama.\n\n"
            . "Siswa/i telah melakukan *ABSENSI MASUK* pada "
            . date("d-m-Y H:i")
            . "\n\nMohon doanya selalu agar ananda dimudahkan dalam belajar dan beraktivitas."
            . "\nAtas Perhatian-nya Terimakasih."
            . "\n\nWassalamualaikum Wr. Wb.";
           
    // Kirim WA
    $wa_status = kirim_wa($conn, $no_wa, $pesan);

    // Balikan ke frontend
    echo json_encode([
        "message" => "✅ ABSEN MASUK $nama berhasil dicatat pada jam $jam_absen.<br>$wa_status"
    ]);
    exit;

} else {
    // === [LOGIKA 2: ABSEN PULANG] ===
    // Sudah ada data, ini adalah percobaan Absen Pulang.
    
    $row_absen = mysqli_fetch_assoc($cek);
    $absen_id = $row_absen['id'];
    $jam_masuk = $row_absen['jam'];
    
    // 2a. Cek apakah jam pulang sudah terisi?
    if ($row_absen['jam_pulang'] != NULL) {
        $jam_pulang = $row_absen['jam_pulang'];
        echo json_encode([
            "message" => "ℹ️ $nama sudah absen MASUK (jam $jam_masuk) dan PULANG (jam $jam_pulang) hari ini."
        ]);
        exit;
    }

    // 2b. Cek "Gerbang Waktu Pulang"
    // ⚠️ Pastikan Anda punya tabel 'jam_absensi' dan sudah diisi
    $q_jam_pulang = mysqli_query($conn, "SELECT jam_pulang FROM absensi LIMIT 1");
    $jam_pulang_minimal = "14:00:00"; // Waktu default jika tabel jam_absensi kosong
    
    if ($q_jam_pulang && mysqli_num_rows($q_jam_pulang) > 0) {
        $d_jam = mysqli_fetch_assoc($q_jam_pulang);
        if (!empty($d_jam['jam_pulang'])) {
             $jam_pulang_minimal = $d_jam['jam_pulang'];
        }
    }

    // Cek apakah waktu sekarang sudah melewati jam pulang minimal
    if ($jam < $jam_pulang_minimal) {
        echo json_encode([
            "message" => "ℹ️ $nama sudah absen masuk. Belum waktunya pulang (Jam pulang: $jam_pulang_minimal)."
        ]);
        exit;
    }

    // 2c. Lakukan Absen Pulang (UPDATE)
    mysqli_query($conn, "UPDATE absensi SET jam_pulang = '$jam' WHERE id = '$absen_id'");

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

    // Balikan ke frontend
    echo json_encode([
        "message" => "✅ ABSEN PULANG $nama berhasil dicatat pada jam $jam_absen.<br>$wa_status"
    ]);
    exit;
}
?>