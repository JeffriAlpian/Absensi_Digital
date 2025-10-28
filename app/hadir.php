<?php

$kelas = $_GET['kelas'] ?? '';
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// Ambil nama sekolah
$profil = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_sekolah FROM profil_sekolah LIMIT 1"));
$nama_sekolah = $profil['nama_sekolah'] ?? 'Nama Sekolah';

// Ambil daftar kelas
$kelasList = mysqli_query($conn, "SELECT DISTINCT id, nama_kelas FROM kelas ORDER BY nama_kelas");

// Ambil daftar siswa
$sqlSiswa = "SELECT s.id, s.nama, k.id, k.nama_kelas FROM siswa s JOIN kelas k ON s.id_kelas = k.id";
if ($kelas != '') {
    $sqlSiswa .= " WHERE id_kelas = '$kelas'";
}
$sqlSiswa .= " ORDER BY nama";
$siswaResult = mysqli_query($conn, $sqlSiswa);

// Hitung absensi per siswa
$rekap = [];
$totalGlobal = ['H' => 0, 'I' => 0, 'S' => 0, 'A' => 0];
while ($s = mysqli_fetch_assoc($siswaResult)) {
    $id = $s['id'];
    $rekap[$id] = [
        'nama' => $s['nama'],
        'kelas' => $s['nama_kelas'],
        'H' => 0,
        'I' => 0,
        'S' => 0,
        'A' => 0
    ];

    $qAbs = mysqli_query($conn, "SELECT status FROM absensi 
        WHERE siswa_id = '$id' 
        AND MONTH(tanggal) = '$bulan' 
        AND YEAR(tanggal) = '$tahun'");

    while ($row = mysqli_fetch_assoc($qAbs)) {
        $rekap[$id][$row['status']]++;
        $totalGlobal[$row['status']]++;
    }
}

// Hitung total hari aktif (hari dengan absensi)
$qHari = mysqli_query($conn, "SELECT COUNT(DISTINCT tanggal) as jml 
    FROM absensi 
    WHERE MONTH(tanggal) = '$bulan' 
    AND YEAR(tanggal) = '$tahun'");
$jmlHari = mysqli_fetch_assoc($qHari)['jml'] ?? 0;

?>

<style>
    h2 {
        text-align: center;
        margin-bottom: 5px;
    }

    form {
        text-align: center;
        margin-bottom: 20px;
    }

    select,
    input,
    button {
        padding: 5px;
        margin: 3px;
    }

    table {
        border-collapse: collapse;
        width: 100%;
        font-size: 14px;
    }

    th,
    td {
        border: 1px solid #ddd;
        padding: 6px;
        text-align: center;
    }

    th {
        background: #f4f4f4;
    }

    .summary {
        display: flex;
        justify-content: center;
        margin: 20px 0;
        gap: 20px;
        flex-wrap: wrap;
    }

    .box {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: bold;
        font-size: 16px;
        min-width: 120px;
        text-align: center;
    }

    .hadir {
        background: #dff0d8;
        color: #2e7d32;
    }

    .izin {
        background: #e3f2fd;
        color: #1565c0;
    }

    .sakit {
        background: #fff8e1;
        color: #ef6c00;
    }

    .alpha {
        background: #ffebee;
        color: #c62828;
    }

    .predikat {
        font-weight: bold;
        padding: 3px 6px;
        border-radius: 4px;
        display: inline-block;
    }

    .sangatbaik {
        background: #c8e6c9;
        color: #256029;
    }

    .baik {
        background: #bbdefb;
        color: #0d47a1;
    }

    .cukup {
        background: #fff9c4;
        color: #f57f17;
    }

    .kurang {
        background: #ffe0b2;
        color: #e65100;
    }
</style>

<h2><?= $nama_sekolah ?><br>
    Rekap Absensi Bulanan - <?= date('F Y', strtotime("$tahun-$bulan-01")) ?><br>
    <?= $kelas == '' ? 'Semua Kelas' : "Kelas $kelas" ?></h2>

<!-- Form filter -->
<form method="get">
    <input type="hidden" name="page" value="hadir">

    <label>Kelas:
        <select name="kelas">
            <option value="">Semua</option>
            <?php mysqli_data_seek($kelasList, 0);
            while ($k = mysqli_fetch_assoc($kelasList)) {
                $sel = ($k['id'] == $kelas) ? 'selected' : '';
                echo "<option $sel value='{$k['id']}'>{$k['nama_kelas']}</option>";
            } ?>
        </select>
    </label>

    <label>Bulan:
        <select name="bulan">
            <?php for ($b = 1; $b <= 12; $b++) {
                $sel = ($b == $bulan) ? 'selected' : '';
                echo "<option $sel value='$b'>" . date('F', mktime(0, 0, 0, $b, 10)) . "</option>";
            } ?>
        </select>
    </label>

    <label>Tahun:
        <input type="number" name="tahun" value="<?= $tahun ?>" style="width:80px;">
    </label>

    <button type="submit" class="inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
        <i class="fa-solid fa-filter mr-2"></i> Tampilkan
    </button>

</form>

<!-- Ringkasan -->
<div class="summary">
    <div class="box hadir"><?= $totalGlobal['H'] ?> <br>Total Hadir</div>
    <div class="box izin"><?= $totalGlobal['I'] ?> <br>Total Izin</div>
    <div class="box sakit"><?= $totalGlobal['S'] ?> <br>Total Sakit</div>
    <div class="box alpha"><?= $totalGlobal['A'] ?> <br>Total Alpha</div>
</div>

<!-- Tabel absensi -->
<table>
    <tr>
        <th>No</th>
        <th>Nama Siswa</th>
        <th>Hadir</th>
        <th>Izin</th>
        <th>Sakit</th>
        <th>Alpha</th>
        <th>Persentase & Predikat</th>
    </tr>
    <?php
    $no = 1;
    foreach ($rekap as $r) {
        $totalHadir = $r['H'];
        $izin = $r['I'];
        $sakit = $r['S'];
        $alpha = $r['A'];

        $persen = $jmlHari > 0 ? round(($totalHadir / $jmlHari) * 100, 1) : 0;

        // Tentukan predikat
        if ($persen == 100) {
            $predikat = "<span class='predikat sangatbaik'>100% Sangat Baik</span>";
        } elseif ($persen >= 90) {
            $predikat = "<span class='predikat baik'>{$persen}% Baik</span>";
        } elseif ($persen >= 80) {
            $predikat = "<span class='predikat cukup'>{$persen}% Cukup</span>";
        } else {
            $predikat = "<span class='predikat kurang'>{$persen}% Kurang</span>";
        }

        echo "<tr>
            <td>$no</td>
            <td style='text-align:left'>{$r['nama']}</td>
            <td>{$totalHadir}</td>
            <td>{$izin}</td>
            <td>{$sakit}</td>
            <td>{$alpha}</td>
            <td>$predikat</td>
        </tr>";
        $no++;
    }
    ?>
</table>