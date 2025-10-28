<?php
session_start();

if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'guru'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['message' => 'Unauthorized']);
    exit;
}
include "config.php";
date_default_timezone_set("Asia/Jakarta");

if (isset($_GET['nisn'])) {
    $nisn = $_GET['nisn'];
    $tanggal = date("Y-m-d");
    $jam = date("H:i:s");

    // Cek libur
    $cekLibur = mysqli_query($conn, "SELECT * FROM hari_libur WHERE tanggal='$tanggal'");
    if (mysqli_num_rows($cekLibur) > 0) {
        echo json_encode(["message" => "⛔ Hari ini libur!"]);
        exit;
    }

    // Ambil data siswa
    $siswa = mysqli_query($conn, "SELECT * FROM siswa WHERE nisn='$nisn'");
    if (mysqli_num_rows($siswa) == 0) {
        echo json_encode(["message" => "❌ Siswa tidak ditemukan."]);
        exit;
    }
    $s = mysqli_fetch_assoc($siswa);

    // Normalisasi nomor WA
    $no_wa = "";
    if (!empty($s['no_wa'])) {
        $no_wa = preg_replace('/[^0-9]/', '', $s['no_wa']);
        if (substr($no_wa, 0, 1) == "0") {
            $no_wa = "62" . substr($no_wa, 1);
        }
    }

    // Pesan WA
    $pesan = "Halo, {$s['nama']} dari kelas {$s['kelas']} sudah hadir pada $tanggal jam $jam.";
    $waLink = !empty($no_wa) ? "https://wa.me/$no_wa?text=" . urlencode($pesan) : "";

    // Cek absen
    $cekAbsen = mysqli_query($conn, "SELECT * FROM absensi WHERE siswa_id={$s['id']} AND tanggal='$tanggal'");
    if (mysqli_num_rows($cekAbsen) == 0) {
        mysqli_query($conn, "INSERT INTO absensi (siswa_id, tanggal, jam, status) 
                             VALUES ({$s['id']}, '$tanggal', '$jam', 'H')");
        $msg = "✅ Absen berhasil: {$s['nama']} ({$s['kelas']})<br>🕒 Jam hadir: $jam";
    } else {
        $row = mysqli_fetch_assoc($cekAbsen);
        $msg = "ℹ️ {$s['nama']} sudah absen hari ini.<br>🕒 Jam hadir: {$row['jam']}";
    }

    echo json_encode([
        "message" => $msg,
        "wa_link" => $waLink
    ]);
    exit;
}
?>
