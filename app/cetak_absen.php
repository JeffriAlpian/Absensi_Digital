<?php
require('../fpdf/fpdf.php'); // Sesuaikan path jika perlu
include 'config.php'; // Pastikan $conn ada

// Ambil parameter
$kelas = $_GET['kelas'] ?? '';
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// Sanitasi input
$kelas_int = intval($kelas);
$bulan_int = intval($bulan);
$tahun_int = intval($tahun);

// ======================================================================
// [LOGIKA SINKRONISASI]
// Ubah status 'H' menjadi 'A' JIKA jam_pulang KOSONG dan tanggal SUDAH LEWAT.
$queryUpdateAlpha = "UPDATE absensi SET status = 'A', keterangan = 'Lupa Absen Pulang'
                     WHERE status = 'H' 
                     AND (jam_pulang = '' OR jam_pulang IS NULL)
                     AND tanggal < CURDATE()";
mysqli_query($conn, $queryUpdateAlpha); // Jalankan query update
// ======================================================================

$jumlahHari = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
$tanggal_hari_ini = date('Y-m-d'); // Tanggal hari ini
$namaBulanTahun = strftime('%B %Y', mktime(0, 0, 0, $bulan_int, 1, $tahun_int));

// Data siswa (Prepared Statement)
$siswaParams = [];
$siswaQuery = "SELECT id, nis, nama, id_kelas FROM siswa WHERE status='aktif'";
if ($kelas_int > 0) {
    $siswaQuery .= " AND id_kelas = ?";
    $siswaParams[] = $kelas_int;
}
$siswaQuery .= " ORDER BY nama"; // Urutkan berdasarkan nama

$stmtSiswa = $conn->prepare($siswaQuery);
if ($stmtSiswa) {
    if ($kelas_int > 0) $stmtSiswa->bind_param("i", $kelas_int);
    $stmtSiswa->execute();
    $siswaResult = $stmtSiswa->get_result();
} else { die("Error query siswa: " . $conn->error); }

// Data absensi (Prepared Statement)
$absensi = [];
$absensiParams = [$bulan_int, $tahun_int];
$absensiQuery = "SELECT a.siswa_id, a.tanggal, a.status, a.keterangan 
                 FROM absensi a 
                 JOIN siswa s ON a.siswa_id = s.id 
                 WHERE MONTH(a.tanggal) = ? 
                   AND YEAR(a.tanggal) = ?
                   AND s.status='aktif'";
if ($kelas_int > 0) {
    $absensiQuery .= " AND s.id_kelas = ?";
    $absensiParams[] = $kelas_int;
}

$stmtAbsensi = $conn->prepare($absensiQuery);
if ($stmtAbsensi) {
    $types = 'ii' . ($kelas_int > 0 ? 'i' : '');
    $stmtAbsensi->bind_param($types, ...$absensiParams);
    $stmtAbsensi->execute();
    $resultAbsensi = $stmtAbsensi->get_result();
    while ($row = $resultAbsensi->fetch_assoc()) {
        $sid = $row['siswa_id'];
        $tgl = (int)date('j', strtotime($row['tanggal']));
        $absensi[$sid][$tgl] = [
            'status'     => $row['status'],
            'keterangan' => $row['keterangan']
        ];
    }
    $stmtAbsensi->close();
} else { die("Error query absensi: " . $conn->error); }

// Hari libur (Prepared Statement)
$libur = [];
$queryLibur = $conn->prepare("SELECT tanggal FROM hari_libur WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$queryLibur->bind_param("ii", $bulan_int, $tahun_int);
$queryLibur->execute();
$resultLibur = $queryLibur->get_result();
while ($row = $resultLibur->fetch_assoc()) {
    $libur[] = $row['tanggal'];
}
$queryLibur->close();

// Profil sekolah
$profil = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_sekolah, kepala_sekolah, nip_kepala FROM profil_sekolah LIMIT 1"));
$nama_sekolah = $profil['nama_sekolah'] ?? 'Nama Sekolah';

// Wali kelas
$wali_nama = '....................................';
$wali_nip = '........................';
$nama_kelas_terpilih = '(Semua Kelas)';
if ($kelas_int > 0) {
    $qWali = $conn->prepare("SELECT k.nama_kelas, w.nama_wali, w.nip_wali 
                            FROM kelas k 
                            LEFT JOIN wali_kelas w ON k.id = w.id_kelas 
                            WHERE k.id = ? LIMIT 1");
    $qWali->bind_param("i", $kelas_int);
    $qWali->execute();
    $resultWali = $qWali->get_result();
    if ($w = $resultWali->fetch_assoc()) {
        $wali_nama = $w['nama_wali'] ?? $wali_nama;
        $wali_nip = $w['nip_wali'] ?? $wali_nip;
        $nama_kelas_terpilih = htmlspecialchars($w['nama_kelas']);
    }
    $qWali->close();
}
// Set locale Indonesia untuk format tanggal
setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'Indonesian');
$tanggal_terakhir_obj = new DateTime("$tahun-$bulan-01");
$tanggal_terakhir_obj->modify('last day of this month');
$tanggal_terakhir = strftime('%e %B %Y', $tanggal_terakhir_obj->getTimestamp());


// --- Mulai Cetak PDF ---
class PDF extends FPDF
{
    // Header Halaman
    function Header()
    {
        global $nama_sekolah, $namaBulanTahun, $nama_kelas_terpilih;
        // Logo (Opsional, sesuaikan path)
        // $this->Image('path/to/logo.png', 10, 8, 20); 
        
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 6, 'REKAP ABSENSI SISWA', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 6, strtoupper($nama_sekolah), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, "Bulan: $namaBulanTahun | Kelas: $nama_kelas_terpilih", 0, 1, 'C');
        $this->Ln(4); // Jarak
    }

    // Footer Halaman
    function Footer()
    {
        $this->SetY(-15); // Posisi 1.5 cm dari bawah
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C'); // Nomor halaman
    }
}

$pdf = new PDF('L', 'mm', 'A4'); // L = Landscape
$pdf->AliasNbPages(); // Aktifkan nomor halaman total
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 8); // Font lebih kecil untuk header tabel

// --- Header tabel ---
$lebar_no = 8;
$lebar_nis = 14;
$lebar_nama = 45;
$lebar_tgl = (277 - $lebar_no - $lebar_nis - $lebar_nama - (4 * 7)) / $jumlahHari; // Lebar sel tanggal dinamis
if ($lebar_tgl < 5) $lebar_tgl = 5; // Lebar minimal
$lebar_rekap = 7; // Lebar kolom rekap (H,S,I,A)
$total_lebar = $lebar_no + $lebar_nis + $lebar_nama + ($jumlahHari * $lebar_tgl) + (4 * $lebar_rekap);

// Center tabel (opsional, jika total lebar < 277)
$startX = ($pdf->GetPageWidth() - $total_lebar - $pdf->$lMargin - $pdf->$rMargin) / 2 + $pdf->$lMargin;
$pdf->SetX($startX);

// Baris 1 Header (Nama, Tanggal, Rekap)
$pdf->Cell($lebar_no, 10, 'No', 1, 0, 'C'); // rowspan 2
$pdf->Cell($lebar_nis, 10, 'NIS', 1, 0, 'C'); // rowspan 2
$pdf->Cell($lebar_nama, 10, 'Nama', 1, 0, 'C'); // rowspan 2
$pdf->Cell($jumlahHari * $lebar_tgl, 5, 'Tanggal', 1, 0, 'C'); // colspan
$pdf->Cell(4 * $lebar_rekap, 5, 'Rekap', 1, 1, 'C'); // colspan

// Baris 2 Header (Angka tanggal & H,S,I,A)
$pdf->SetX($startX + $lebar_no + $lebar_nis + $lebar_nama); // Pindah ke awal kolom tanggal
$pdf->SetFont('Arial', 'B', 7); // Font lebih kecil untuk tanggal
for ($i = 1; $i <= $jumlahHari; $i++) {
    $tanggal = sprintf("%04d-%02d-%02d", $tahun_int, $bulan_int, $i);
    $day = date('w', strtotime($tanggal)); // 0 = Minggu
    
    // [PERBAIKAN] Cek libur (Minggu atau DB)
    if ($day == 0 || in_array($tanggal, $libur)) {
        $pdf->SetFillColor(255, 220, 220); // Merah muda
        $pdf->SetTextColor(255, 0, 0);
    } else {
        $pdf->SetFillColor(230, 230, 230); // Abu-abu
        $pdf->SetTextColor(0, 0, 0);
    }
    $pdf->Cell($lebar_tgl, 5, $i, 1, 0, 'C', true);
}
$pdf->SetTextColor(0, 0, 0); // Reset warna teks

// Header Rekap (H,S,I,A)
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(200, 255, 200); $pdf->Cell($lebar_rekap, 5, 'H', 1, 0, 'C', true);
$pdf->SetFillColor(255, 255, 200); $pdf->Cell($lebar_rekap, 5, 'S', 1, 0, 'C', true);
$pdf->SetFillColor(200, 220, 255); $pdf->Cell($lebar_rekap, 5, 'I', 1, 0, 'C', true);
$pdf->SetFillColor(255, 200, 200); $pdf->Cell($lebar_rekap, 5, 'A', 1, 1, 'C', true);


// --- Isi tabel ---
$pdf->SetFont('Arial', '', 7); // Font isi tabel lebih kecil
$pdf->SetFillColor(255, 255, 255); // Reset fill color
$no = 1;

if ($siswaResult && mysqli_num_rows($siswaResult) > 0) {
    mysqli_data_seek($siswaResult, 0); // Reset pointer
    while ($siswa = mysqli_fetch_assoc($siswaResult)) {
        $sid = $siswa['id'];
        $pdf->SetX($startX); // Set X ke awal baris
        $pdf->Cell($lebar_no, 5, $no, 1, 0, 'C');
        $pdf->Cell($lebar_nis, 5, htmlspecialchars($siswa['nis']), 1, 0, 'C');
        // Singkat nama jika terlalu panjang untuk 45mm
        $namaSiswa = htmlspecialchars($siswa['nama']);
        if ($pdf->GetStringWidth($namaSiswa) > ($lebar_nama - 2)) { // -2 untuk padding
             while ($pdf->GetStringWidth($namaSiswa . '...') > ($lebar_nama - 2)) {
                 $namaSiswa = mb_substr($namaSiswa, 0, -1, 'UTF-8');
             }
             $namaSiswa .= '...';
        }
        $pdf->Cell($lebar_nama, 5, $namaSiswa, 1, 0, 'L');

        $countH = $countS = $countI = $countA = 0;

        for ($i = 1; $i <= $jumlahHari; $i++) {
            $tanggal = sprintf("%04d-%02d-%02d", $tahun_int, $bulan_int, $i);
            $day = date('w', strtotime($tanggal));
            $data_hari = $absensi[$sid][$i] ?? null;
            $val = $data_hari['status'] ?? '';
            $ket = $data_hari['keterangan'] ?? '';

            $cellText = '';
            $pdf->SetTextColor(0, 0, 0); // Default hitam

            // [PERBAIKAN] Logika 'A' dan Libur
            if ($day == 0 || in_array($tanggal, $libur)) {
                // 1. HARI LIBUR / MINGGU
                $pdf->SetFillColor(240, 240, 240); // Abu-abu muda
                $pdf->SetTextColor(255, 0, 0);
                $cellText = 'L'; // Tampilkan 'L' (Libur)
            } else if ($data_hari !== null) {
                // 2. ADA DATA ABSENSI
                $pdf->SetFillColor(255, 255, 255); // Putih
                if ($val == 'H') {
                    if ($ket == 'Terlambat') {
                        $pdf->SetTextColor(200, 0, 0); // Merah tua (Telat)
                        $cellText = 'HT';
                    } else {
                        $pdf->SetTextColor(0, 128, 0); // Hijau
                        $cellText = 'H';
                    }
                    $countH++;
                } elseif ($val == 'A') {
                    $pdf->SetTextColor(255, 0, 0); // Merah
                    $cellText = 'A';
                    $countA++;
                } elseif ($val == 'S') {
                    $pdf->SetTextColor(255, 165, 0); // Oranye
                    $cellText = 'S';
                    $countS++;
                } elseif ($val == 'I') {
                    $pdf->SetTextColor(0, 0, 255); // Biru
                    $cellText = 'I';
                    $countI++;
                } else {
                    $cellText = '?'; // Status tidak dikenal
                }
            } else if ($tanggal <= $tanggal_hari_ini) {
                // 3. TIDAK ADA DATA, BUKAN LIBUR, TANGGAL SUDAH LEWAT (ALPHA)
                $pdf->SetFillColor(255, 255, 255); // Putih
                $pdf->SetTextColor(255, 0, 0); // Merah
                $cellText = 'A';
                $countA++;
            } else {
                // 4. TIDAK ADA DATA, BUKAN LIBUR, TANGGAL DI MASA DEPAN (KOSONG)
                $pdf->SetFillColor(245, 245, 245); // Abu-abu sangat muda
                $cellText = ''; // Kosong
            }
            $pdf->Cell($lebar_tgl, 5, $cellText, 1, 0, 'C', true); // 'true' untuk fill
            $pdf->SetFillColor(255, 255, 255); // Reset fill
            $pdf->SetTextColor(0, 0, 0); // Reset text color
        } // End loop hari

        // Rekap
        $pdf->Cell($lebar_rekap, 5, $countH, 1, 0, 'C');
        $pdf->Cell($lebar_rekap, 5, $countS, 1, 0, 'C');
        $pdf->Cell($lebar_rekap, 5, $countI, 1, 0, 'C');
        $pdf->Cell($lebar_rekap, 5, $countA, 1, 1, 'C'); // '1' ganti baris

        $no++;
    } // End loop siswa
} else {
    // Jika tidak ada siswa
    $pdf->SetX($startX);
    $pdf->Cell($total_lebar, 10, 'Tidak ada data siswa aktif.', 1, 1, 'C');
}

// --- Tanda Tangan ---
$pdf->SetFont('Arial', '', 10);
$pdf->Ln(10); // Jarak

// Lebar area tanda tangan
$ttdWidth = $total_lebar / 2; // Bagi 2
$pdf->SetX($startX); // Mulai dari X awal tabel

// Kolom Kepala Sekolah
$pdf->Cell($ttdWidth, 6, "Mengetahui,", 0, 0, 'C');
// Kolom Wali Kelas
$pdf->Cell($ttdWidth, 6, htmlspecialchars(strftime('%e %B %Y', strtotime($tanggal_terakhir))), 0, 1, 'C'); // Tampilkan tanggal

$pdf->SetX($startX);
$pdf->Cell($ttdWidth, 6, "Kepala Sekolah", 0, 0, 'C');
$pdf->Cell($ttdWidth, 6, "Wali Kelas " . $nama_kelas_terpilih, 0, 1, 'C');

$pdf->Ln(15); // Jarak untuk TTD

$pdf->SetX($startX);
$pdf->SetFont('Arial', 'BU', 10); // Font tebal + underline
$pdf->Cell($ttdWidth, 6, htmlspecialchars($profil['kepala_sekolah'] ?? '....................................'), 0, 0, 'C');
$pdf->Cell($ttdWidth, 6, htmlspecialchars($wali_nama), 0, 1, 'C');

$pdf->SetX($startX);
$pdf->SetFont('Arial', '', 10); // Font normal
$pdf->Cell($ttdWidth, 6, "NIP. " . htmlspecialchars($profil['nip_kepala'] ?? '........................'), 0, 0, 'C');
$pdf->Cell($ttdWidth, 6, "NIP. " . htmlspecialchars($wali_nip), 0, 1, 'C');


// Output PDF
$pdf->Output('I', 'Rekap Absen ' . $nama_kelas_terpilih . ' ' . $bulan . '-' . $tahun . '.pdf');

?>