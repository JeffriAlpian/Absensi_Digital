<?php
include "config.php";
session_start();
// Atur timezone ke WIB (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');

// ------------------------------------------------------------------
// FUNGSI UNTUK MENGIRIM WA
// ------------------------------------------------------------------
function kirim_wa($conn, $no_wa, $pesan)
{
    // Normalisasi nomor WA
    $no_wa = preg_replace('/[^0-9]/', '', $no_wa); // hanya angka
    if (substr($no_wa, 0, 1) === "0") {
        $no_wa = "+62" . substr($no_wa, 1);
    } elseif (substr($no_wa, 0, 2) === "62") {
        $no_wa = "+" . $no_wa;
    } elseif (substr($no_wa, 0, 3) !== "+62") {
        $no_wa = "";
    }

    // Ambil secret key dari tabel profil_sekolah
    $secretKey = "";
    $qKey = mysqli_query($conn, "SELECT key_wa_sidobe FROM profil_sekolah LIMIT 1");
    if ($qKey && mysqli_num_rows($qKey) > 0) {
        $rowKey = mysqli_fetch_assoc($qKey);
        $secretKey = $rowKey['key_wa_sidobe'] ?? "";
    }

    if (empty($no_wa)) {
        return "(Nomor WA tidak terdaftar, notifikasi tidak dikirim).";
    }
    if (empty($secretKey)) {
        return "⚠️ Secret key WA tidak ditemukan.";
    }

    $data = [ 'phone' => $no_wa, 'message' => $pesan ];
    $ch = curl_init('https://api.sidobe.com/wa/v1/send-message');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json', 'X-Secret-Key: ' . $secretKey ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    $resData = json_decode($response, true);
    if ($resData && isset($resData['is_success']) && $resData['is_success']) {
        return "📲 WA terkirim.";
    } else {
        return "⚠️ Gagal kirim WA.";
    }
}
// ------------------------------------------------------------------
// AKHIR FUNGSI WA
// ------------------------------------------------------------------

// Cek Sesi Login
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'guru'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(["message" => "❌ Error: Anda belum login (Unauthorized).", "wa_link" => ""]);
    exit;
}

// ------------------------------------------------------------------
// 1. AMBIL DAN VALIDASI ID
// ------------------------------------------------------------------
$id_scan = $_GET['id'] ?? '';
if (empty($id_scan)) {
    http_response_code(400); // Bad Request
    echo json_encode(["message" => "❌ Error: ID tidak diterima.", "wa_link" => ""]);
    exit;
}

$id_scan_safe = mysqli_real_escape_string($conn, $id_scan);
$user_data = null;
$user_type = null; // 'siswa' atau 'guru'

// ------------------------------------------------------------------
// 2. CARI DI TABEL SISWA (NISN ATAU RFID)
// ------------------------------------------------------------------
$sql_cari_siswa = "SELECT id, nama, id_kelas, no_wa, 'siswa' as user_type 
                   FROM siswa 
                   WHERE (nisn = '$id_scan_safe') 
                   AND status = 'aktif' 
                   LIMIT 1";
$res_siswa = mysqli_query($conn, $sql_cari_siswa);

if ($res_siswa && mysqli_num_rows($res_siswa) > 0) {
    $user_data = mysqli_fetch_assoc($res_siswa);
    $user_type = 'siswa';
}

// ------------------------------------------------------------------
// 3. CARI DI TABEL GURU (JIKA BUKAN SISWA)
// ------------------------------------------------------------------
if ($user_type === null) {
    $sql_cari_guru = "SELECT id, nip, nama, no_wa, 'guru' as user_type 
                      FROM guru 
                      WHERE (nip = '$id_scan_safe') 
                      LIMIT 1";
    $res_guru = mysqli_query($conn, $sql_cari_guru);

    if ($res_guru && mysqli_num_rows($res_guru) > 0) {
        $user_data = mysqli_fetch_assoc($res_guru);
        $user_type = 'guru';
    }
}

// ------------------------------------------------------------------
// 4. JIKA TIDAK DITEMUKAN SAMA SEKALI
// ------------------------------------------------------------------
if ($user_type === null) {
    http_response_code(404); // Not Found
    echo json_encode([
        "message" => "❌ Error: ID '$id_scan' tidak terdaftar sebagai Siswa maupun Guru.",
        "wa_link" => ""
    ]);
    exit;
}

// ------------------------------------------------------------------
// 5. SIAPKAN VARIABEL UMUM
// ------------------------------------------------------------------
$nama = $user_data['nama'];
$no_wa = $user_data['no_wa'] ?? ''; // [PERBAIKAN] no_wa sekarang terisi
$tanggal = date("Y-m-d");
$jam_sekarang = date("H:i:s");
$jam_absen = date("H:i");

$pesan_respon = "";
$pesan_wa = "";

// Ambil jam masuk/pulang GURU (Default)
$qJamGuru = mysqli_query($conn, "SELECT jam_masuk_guru, jam_pulang_guru FROM profil_sekolah LIMIT 1");
$jam_guru_default = mysqli_fetch_assoc($qJamGuru);
$jam_masuk_guru_setting = $jam_guru_default['jam_masuk_guru'] ?? '08:00:00';
$jam_pulang_guru_setting = $jam_guru_default['jam_pulang_guru'] ?? '14:00:00';

// ------------------------------------------------------------------
// 6. LOGIKA UTAMA (BERCABANG)
// ------------------------------------------------------------------

if ($user_type === 'guru') {
    // === [LOGIKA GURU] ===
    $guru_id  = $user_data['id']; 

    // 1️⃣ Cek absensi guru hari ini
    $sql_cek = "SELECT id, jam, jam_pulang FROM absensi_guru WHERE guru_id='$guru_id' AND tanggal='$tanggal' LIMIT 1";
    $cek = mysqli_query($conn, $sql_cek);

    if (mysqli_num_rows($cek) == 0) {
        // --- LOGIKA MASUK GURU ---
        $jam_masuk_maximal = $jam_masuk_guru_setting;
        $status = "H";
        $keterangan = (strtotime($jam_sekarang) > strtotime($jam_masuk_maximal)) ? "Terlambat" : "";

        mysqli_query($conn, "INSERT INTO absensi_guru (guru_id, tanggal, jam, status, keterangan) 
                              VALUES ('$guru_id', '$tanggal', '$jam_sekarang', '$status', '$keterangan')");
        
        $status_text = $keterangan ? "MASUK (TERLAMBAT)" : "MASUK";
        $pesan_respon = "✅ $nama berhasil absen $status_text pada jam $jam_absen.";
        $pesan_wa = "Assalamualaikum Wr. Wb.\n\nBapak/Ibu *$nama*.\n\nTelah melakukan *ABSENSI $status_text* pada " . date("d-m-Y H:i") . "\n\nTerima kasih.\nWassalamualaikum Wr. Wb.";

    } else {
        // --- LOGIKA PULANG GURU ---
        $row_absen = mysqli_fetch_assoc($cek);
        $jam_masuk = $row_absen['jam'];
        if ($row_absen['jam_pulang'] != NULL) {
            $pesan_respon = "ℹ️ $nama sudah absen MASUK (jam $jam_masuk) dan PULANG (jam {$row_absen['jam_pulang']}) hari ini.";
        } elseif (strtotime($jam_sekarang) < strtotime($jam_pulang_guru_setting)) {
            $pesan_respon = "ℹ️ $nama sudah absen masuk. Belum waktunya pulang (Jam pulang: $jam_pulang_guru_setting).";
        } else {
            mysqli_query($conn, "UPDATE absensi_guru SET jam_pulang = '$jam_sekarang' WHERE id = '{$row_absen['id']}'");
            $pesan_respon = "✅ $nama berhasil absen PULANG pada jam $jam_absen.";
            $pesan_wa = "Assalamualaikum Wr. Wb.\n\nBapak/Ibu *$nama*.\n\nTelah melakukan *ABSENSI PULANG* pada " . date("d-m-Y H:i") . "\n\nTerima kasih.\nWassalamualaikum Wr. Wb.";
        }
    }

} elseif ($user_type === 'siswa') {
    // === [LOGIKA SISWA] ===
    $siswa_id = $user_data['id'];
    $kelas_id = $user_data['id_kelas'];
    
    // Ambil jam masuk/pulang siswa DARI KELAS
    $q_jam_kelas = mysqli_query($conn, "SELECT jam_masuk, jam_pulang FROM kelas WHERE id = '$kelas_id' LIMIT 1");
    $jam_kelas = mysqli_fetch_assoc($q_jam_kelas);
    $jam_masuk_siswa_setting = $jam_kelas['jam_masuk'] ?? $jam_masuk_guru_setting; // Fallback
    $jam_pulang_siswa_setting = $jam_kelas['jam_pulang'] ?? $jam_pulang_guru_setting; // Fallback

    // 1️⃣ Cek absensi siswa hari ini
    $sql_cek = "SELECT id, jam, jam_pulang FROM absensi WHERE siswa_id='$siswa_id' AND tanggal='$tanggal' LIMIT 1";
    $cek = mysqli_query($conn, $sql_cek);

    if (mysqli_num_rows($cek) == 0) {
        // --- LOGIKA MASUK SISWA ---
        $jam_masuk_maximal = $jam_masuk_siswa_setting;
        $status = "H";
        $keterangan = (strtotime($jam_sekarang) > strtotime($jam_masuk_maximal)) ? "Terlambat" : "";

        mysqli_query($conn, "INSERT INTO absensi (siswa_id, tanggal, jam, status, keterangan) 
                              VALUES ('$siswa_id', '$tanggal', '$jam_sekarang', '$status', '$keterangan')");

        $status_text = $keterangan ? "MASUK (TERLAMBAT)" : "MASUK";
        $pesan_respon = "✅ $nama berhasil absen $status_text pada jam $jam_absen.";
        $pesan_wa = "Assalamualaikum Wr. Wb.\n\nOrang tua/wali dari *$nama*.\n\nSiswa/i telah melakukan *ABSENSI $status_text* pada " . date("d-m-Y H:i") . "\n\nMohon doanya selalu agar ananda dimudahkan dalam belajar dan beraktivitas.\nAtas Perhatian-nya Terimakasih.\n\nWassalamualaikum Wr. Wb.";
    
    } else {
        // --- LOGIKA PULANG SISWA ---
        $row_absen = mysqli_fetch_assoc($cek);
        $jam_masuk = $row_absen['jam'];
        if ($row_absen['jam_pulang'] != NULL) {
            $pesan_respon = "ℹ️ $nama sudah absen MASUK (jam $jam_masuk) dan PULANG (jam {$row_absen['jam_pulang']}) hari ini.";
        } elseif (strtotime($jam_sekarang) < strtotime($jam_pulang_siswa_setting)) {
            $pesan_respon = "ℹ️ $nama sudah absen masuk. Belum waktunya pulang (Jam pulang: $jam_pulang_siswa_setting).";
        } else {
            mysqli_query($conn, "UPDATE absensi SET jam_pulang = '$jam_sekarang' WHERE id = '{$row_absen['id']}'");
            $pesan_respon = "✅ $nama berhasil absen PULANG pada jam $jam_absen.";
            $pesan_wa = "Assalamualaikum Wr. Wb.\n\nOrang tua/wali dari *$nama*.\n\nSiswa/i telah melakukan *ABSENSI PULANG* pada " . date("d-m-Y H:i") . "\n\nMohon doanya selalu agar ananda dimudahkan dalam belajar dan beraktivitas.\nAtas perhatian-nya kami ucapkan terimakasih.\n\nWassalamualaikum Wr. Wb.";
        }
    }
}

// ------------------------------------------------------------------
// 7. BUAT LINK WA DAN KIRIM RESPON AKHIR
// ------------------------------------------------------------------
$wa_link = "";
$wa_status = "";

if (!empty($pesan_wa)) { // Hanya jika ada pesan yang perlu dikirim
    // Cek jika nomor WA ada (sudah diambil di Bagian 5)
    if (!empty($no_wa)) {
        // Normalisasi No WA (pastikan format 62...)
        $no_wa_link = preg_replace('/[^0-9]/', '', $no_wa);
        if (substr($no_wa_link, 0, 1) === '0') {
             $no_wa_link = '62' . substr($no_wa_link, 1);
        } elseif (substr($no_wa_link, 0, 2) !== '62') {
             $no_wa_link = '62' . $no_wa_link; // Asumsi jika format aneh
        }
        
        $wa_link = "https://api.whatsapp.com/send?phone=" . urlencode($no_wa_link) . "&text=" . urlencode($pesan_wa);
        $pesan_respon .= "<br>Membuka WA untuk dikirim...";
    } else {
         $pesan_respon .= "<br>(Nomor WA tidak terdaftar, notifikasi tidak dikirim).";
    }
}

// Kirim respon JSON yang valid
echo json_encode([
    "message" => $pesan_respon,
    "wa_link" => $wa_link
]);
exit;
?>