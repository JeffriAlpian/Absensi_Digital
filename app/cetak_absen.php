<?php
require('../fpdf/fpdf.php'); // Sesuaikan path jika perlu
include 'config.php';

// Ambil parameter
$kelas = $_GET['kelas'] ?? '';
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// ======================================================================
// [BARU] LOGIKA ABSEN PULANG (SINKRONISASI DENGAN REKAP WEB)
// ======================================================================
// Ubah status 'H' menjadi 'A' JIKA jam_pulang KOSONG dan tanggal SUDAH LEWAT.
$queryUpdateAlpha = "UPDATE absensi SET status = 'A' 
                     WHERE status = 'H' 
                     AND jam_pulang IS NULL 
                     AND tanggal < CURDATE()"; // Hanya untuk hari kemarin
mysqli_query($conn, $queryUpdateAlpha);
// ======================================================================


$jumlahHari = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);

// Data siswa (Ambil yang 'aktif' saja agar konsisten)
$siswaQuery = "SELECT * FROM siswa WHERE status='aktif'";
if ($kelas != '') {
    $siswaQuery .= " AND kelas = '$kelas'";
}
$siswaQuery .= " ORDER BY nama";
$siswaResult = mysqli_query($conn, $siswaQuery);

// Data absensi (Ambil yang 'aktif' saja)
$absensi = [];
$absensiQuery = "SELECT a.*, s.nis, s.nama FROM absensi a 
                 JOIN siswa s ON a.siswa_id = s.id 
                 WHERE MONTH(a.tanggal) = '$bulan' 
                   AND YEAR(a.tanggal) = '$tahun'
                   AND s.status='aktif'"; // <-- Filter siswa aktif
if ($kelas != '') {
    $absensiQuery .= " AND s.kelas = '$kelas'";
}
$resultAbsensi = mysqli_query($conn, $absensiQuery);
while ($row = mysqli_fetch_assoc($resultAbsensi)) {
    $sid = $row['siswa_id'];
    $tgl = (int)date('j', strtotime($row['tanggal']));
    $absensi[$sid][$tgl] = $row['status'];
}

// (Sisa logika data libur, profil, wali kelas sama...)
// Hari libur
$libur = [];
$queryLibur = mysqli_query($conn, "SELECT tanggal FROM hari_libur");
while ($row = mysqli_fetch_assoc($queryLibur)) {
    $libur[] = $row['tanggal'];
}
// Profil sekolah
$profil = mysqli_fetch_assoc(mysqli_query($conn, "SELECT kepala_sekolah, nip_kepala FROM profil_sekolah LIMIT 1"));
// Wali kelas
$wali_nama = '....................................';
$wali_nip = '........................';
if ($kelas != '') {
    $qWali = mysqli_query($conn, "SELECT nama_wali, nip_wali FROM wali_kelas WHERE kelas = '$kelas' LIMIT 1");
    if ($w = mysqli_fetch_assoc($qWali)) {
        $wali_nama = $w['nama_wali'] ?? $wali_nama;
        $wali_nip = $w['nip_wali'] ?? $wali_nip;
    }
}
$tanggal_terakhir = date("j F Y", strtotime("$tahun-$bulan-" . $jumlahHari));

// --- Mulai Cetak PDF ---
$pdf = new FPDF('L','mm','A4'); // L = Landscape
$pdf->AddPage();
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,10,"Rekap Absensi Bulanan - ".($kelas ?: "Semua Kelas"),0,1,'C');
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0,5, "Bulan: " . date('F', mktime(0, 0, 0, $bulan, 10)) . " $tahun", 0, 1, 'C');
$pdf->Ln(5);


// --- Header tabel ---
$pdf->SetFont('Arial','B',9);
$pdf->Cell(10,7,'No',1,0,'C');
$pdf->Cell(15,7,'NIS',1,0,'C');
$pdf->Cell(47,7,'Nama',1,0,'C');

// Header Tanggal
for ($i = 1; $i <= $jumlahHari; $i++) {
    $tanggal = "$tahun-" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "-" . str_pad($i, 2, '0', STR_PAD_LEFT);
    $day = date('w', strtotime($tanggal));
    if ($day == 5) { // 5 = Jumat (Libur)
        $pdf->SetFillColor(255, 220, 220); // Merah muda
    } else {
        $pdf->SetFillColor(230, 230, 230); // Abu-abu
    }
    $pdf->Cell(6,7,$i,1,0,'C', true);
}
$pdf->SetFillColor(230, 230, 230); // Reset

// [FIX] Header Rekap (Menambahkan 'H')
$pdf->SetFillColor(200, 255, 200); // Hijau muda
$pdf->Cell(7,7,'H',1,0,'C', true);
$pdf->SetFillColor(255, 255, 200); // Kuning muda
$pdf->Cell(7,7,'S',1,0,'C', true);
$pdf->SetFillColor(200, 220, 255); // Biru muda
$pdf->Cell(7,7,'I',1,0,'C', true);
$pdf->SetFillColor(255, 200, 200); // Merah muda
$pdf->Cell(7,7,'A',1,1,'C', true);


// --- Isi tabel ---
$pdf->SetFont('Arial','',9);
$no = 1;
while ($siswa = mysqli_fetch_assoc($siswaResult)) {
    $sid = $siswa['id'];
    $pdf->Cell(10,6,$no,1,0,'C');
    
    // Ambil 4 karakter terakhir dari NIS
    $nisAkhir = substr($siswa['nis'], -4);
    $pdf->Cell(15,6,$nisAkhir,1,0,'C');

    // Batasi nama
    $nama = $siswa['nama'];
    $maxChar = 25; // Disesuaikan untuk font 9
    if (strlen($nama) > $maxChar) {
        $nama = substr($nama, 0, $maxChar-3) . '...';
    }
    $pdf->Cell(47,6,$nama,1,0,'L');

    // [FIX] Inisialisasi hitungan (Menambahkan 'H')
    $countH = 0; $countS = 0; $countI = 0; $countA = 0;
    
    for ($i = 1; $i <= $jumlahHari; $i++) {
        $val = $absensi[$sid][$i] ?? '';
        $tanggal = "$tahun-" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "-" . str_pad($i, 2, '0', STR_PAD_LEFT);
        $day = date('w', strtotime($tanggal));

        if ($val == '') {
            if ($day == 5 || in_array($tanggal, $libur)) { // 5 = Jumat
                $pdf->SetTextColor(255,0,0); 
                $pdf->Cell(6,6,chr(149),1,0,'C'); // bullet point
                $pdf->SetTextColor(0,0,0);
            } else {
                $pdf->Cell(6,6,'',1,0,'C');
            }
        } else {
            // [FIX] Logika pewarnaan dan hitungan
            if ($val == 'H') {
                $pdf->SetTextColor(0,128,0); // Hijau
                $pdf->Cell(6,6,chr(149),1,0,'C');
                $pdf->SetTextColor(0,0,0);
                $countH++;
            } elseif ($val == 'S') {
                $pdf->SetTextColor(255,165,0); // Oranye
                $pdf->Cell(6,6,'S',1,0,'C');
                $pdf->SetTextColor(0,0,0);
                $countS++;
            } elseif ($val == 'I') {
                $pdf->SetTextColor(0,0,255); // Biru
                $pdf->Cell(6,6,'I',1,0,'C');
                $pdf->SetTextColor(0,0,0);
                $countI++;
            } elseif ($val == 'A') {
                $pdf->SetTextColor(255,0,0); // Merah
                $pdf->Cell(6,6,'A',1,0,'C');
                $pdf->SetTextColor(0,0,0);
                $countA++;
            }
        }
    }
    
    // [FIX] Tampilkan rekap (Menambahkan 'H')
    $pdf->Cell(7,6,$countH,1,0,'C');
    $pdf->Cell(7,6,$countS,1,0,'C');
    $pdf->Cell(7,6,$countI,1,0,'C');
    $pdf->Cell(7,6,$countA,1,1,'C');

    $no++;
}

// --- Tanda Tangan ---
$pdf->SetFont('Arial','',10);
$pdf->Ln(8);

// Total lebar tabel kita adalah 286mm (dihitung dari 10+15+47 + (31*6) + (4*7))
// Lebar kertas A4 Landscape 297mm.
// Margin kiri = (297 - 286) / 2 = 5.5mm (kira-kira)
// Kita set X di 5.5 + 72 (kolom No,NIS,Nama) = 77.5
// Atau cara mudah, bagi 2 area
$pdf->SetX(72); // Mulai setelah kolom Nama
$pdf->Cell(107, 6, "Mengetahui,", 0, 0, 'C'); // 107 adalah setengah dari 214 (sisa area)
$pdf->Cell(107, 6, $tanggal_terakhir, 0, 1, 'C');

$pdf->SetX(72);
$pdf->Cell(107, 6, "Kepala Sekolah", 0, 0, 'C');
$pdf->Cell(107, 6, "Wali Kelas " . ($kelas ?: "(Semua Kelas)"), 0, 1, 'C');

$pdf->Ln(15);
$pdf->SetX(72);
$pdf->Cell(107, 6, $profil['kepala_sekolah'] ?? '....................................', 0, 0, 'C');
$pdf->Cell(107, 6, $wali_nama, 0, 1, 'C');

$pdf->SetX(72);
$pdf->Cell(107, 6, "NIP. " . ($profil['nip_kepala'] ?? '........................'), 0, 0, 'C');
$pdf->Cell(107, 6, "NIP. " . $wali_nip, 0, 1, 'C');


$pdf->Output('I', 'Rekap Absen '.$kelas.' '.$bulan.'-'.$tahun.'.pdf');
?>