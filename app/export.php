<?php

$kelasList = mysqli_query($conn, "SELECT DISTINCT kelas FROM siswa ORDER BY kelas");

// Default bulan & tahun
$bulan_awal = $_GET['bulan_awal'] ?? date('m');
$tahun_awal = $_GET['tahun_awal'] ?? date('Y');
$bulan_akhir = $_GET['bulan_akhir'] ?? date('m');
$tahun_akhir = $_GET['tahun_akhir'] ?? date('Y');
$kelas = $_GET['kelas'] ?? '';

?>

<header class="bg-green-600 text-white p-4 shadow-md relative text-center lg:text-center">
    <button id="menu-toggle" class="lg:hidden absolute left-4 top-1/2 -translate-y-1/2 p-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-white">
        <i class="fa-solid fa-bars fa-fw text-xl"></i>
    </button>
    <h1 class="text-2xl lg:text-3xl font-bold">Export Laporan</h1>
</header>

<div class="flex-1 p-6">
    
    <div class="bg-white p-6 rounded-lg shadow-md max-w-4xl mx-auto">

        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Export Absensi ke Excel</h2>

        <form method="get" class="space-y-6">
            <input type="hidden" name="page" value="export">
            <input type="hidden" name="action" value="export">

            <div>
                <label for="kelas" class="block text-sm font-medium text-gray-700 mb-1">
                    Pilih Kelas
                </label>
                <select id="kelas" name="kelas" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
                    <option value="">Semua Kelas</option>
                    <?php while ($k = mysqli_fetch_assoc($kelasList)) {
                        $sel = ($k['kelas'] == $kelas) ? 'selected' : '';
                    ?>
                        <option value="<?= htmlspecialchars($k['kelas']) ?>" <?= $sel ?>><?= htmlspecialchars($k['kelas']) ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                
                <div class="border border-gray-200 p-4 rounded-lg space-y-4">
                    <legend class="text-base font-medium text-gray-900">Periode Awal</legend>
                    
                    <div>
                        <label for="bulan_awal" class="block text-sm font-medium text-gray-700">Bulan Awal</label>
                        <select id="bulan_awal" name="bulan_awal" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
                            <?php for ($b = 1; $b <= 12; $b++) {
                                $namaBulan = date('F', mktime(0, 0, 0, $b, 10));
                                $sel = ($b == $bulan_awal) ? 'selected' : '';
                                echo "<option value='$b' $sel>$namaBulan</option>";
                            } ?>
                        </select>
                    </div>

                    <div>
                        <label for="tahun_awal" class="block text-sm font-medium text-gray-700">Tahun Awal</label>
                        <input type="number" id="tahun_awal" name="tahun_awal" value="<?= htmlspecialchars($tahun_awal) ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" placeholder="Contoh: 2024">
                    </div>
                </div>

                <div class="border border-gray-200 p-4 rounded-lg space-y-4">
                    <legend class="text-base font-medium text-gray-900">Periode Akhir</legend>
                    
                    <div>
                        <label for="bulan_akhir" class="block text-sm font-medium text-gray-700">Bulan Akhir</label>
                        <select id="bulan_akhir" name="bulan_akhir" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
                            <?php for ($b = 1; $b <= 12; $b++) {
                                $namaBulan = date('F', mktime(0, 0, 0, $b, 10));
                                $sel = ($b == $bulan_akhir) ? 'selected' : '';
                                echo "<option value='$b' $sel>$namaBulan</option>";
                            } ?>
                        </select>
                    </div>

                    <div>
                        <label for="tahun_akhir" class="block text-sm font-medium text-gray-700">Tahun Akhir</label>
                        <input type="number" id="tahun_akhir" name="tahun_akhir" value="<?= htmlspecialchars($tahun_akhir) ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" placeholder="Contoh: 2024">
                    </div>
                </div>

            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" class="inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-150 ease-in-out">
                    <i class="fa-solid fa-file-excel mr-2 -ml-1"></i>
                    Export ke Excel
                </button>
            </div>
            
        </form>
    </div>
</div>