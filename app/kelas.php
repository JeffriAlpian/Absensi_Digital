<?php
$pesan = ""; // Untuk notifikasi
$pesan_tipe = "sukses"; // 'sukses' atau 'gagal'

// Tambah kelas
if (isset($_POST['simpan'])) {
    $nama_kelas = mysqli_real_escape_string($conn, trim($_POST['nama_kelas']));

    if (empty($nama_kelas)) {
        $pesan = "Nama kelas tidak boleh kosong.";
        $pesan_tipe = "gagal";
    } else {
        // Cek duplikat
        $cek = $conn->prepare("SELECT id FROM kelas WHERE nama_kelas = ?");
        $cek->bind_param("s", $nama_kelas);
        $cek->execute();
        $result = $cek->get_result();

        if ($result->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO kelas (nama_kelas) VALUES (?)");
            $stmt->bind_param("s", $nama_kelas);
            if ($stmt->execute()) {
                $pesan = "Kelas berhasil ditambahkan.";
                $pesan_tipe = "sukses";
                // Kosongkan input setelah sukses
                $_POST['nama_kelas'] = ''; 
            } else {
                $pesan = "Terjadi kesalahan saat menyimpan: " . $stmt->error;
                $pesan_tipe = "gagal";
            }
        } else {
            $pesan = "Kelas '$nama_kelas' sudah ada.";
            $pesan_tipe = "gagal";
        }
    }
}

// Hapus kelas
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);

    // [PENTING] Cek apakah kelas masih digunakan sebelum dihapus
    $cek_siswa = $conn->prepare("SELECT COUNT(*) as total FROM siswa WHERE id_kelas = ?");
    $cek_siswa->bind_param("i", $id);
    $cek_siswa->execute();
    $res_siswa = $cek_siswa->get_result()->fetch_assoc();

    $cek_wali = $conn->prepare("SELECT COUNT(*) as total FROM wali_kelas WHERE id_kelas = ?");
    $cek_wali->bind_param("i", $id);
    $cek_wali->execute();
    $res_wali = $cek_wali->get_result()->fetch_assoc();

    if ($res_siswa['total'] > 0 || $res_wali['total'] > 0) {
        $pesan = "Gagal menghapus: Kelas masih digunakan oleh data siswa atau wali kelas.";
        $pesan_tipe = "gagal";
    } else {
        $stmt = $conn->prepare("DELETE FROM kelas WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $pesan = "Kelas berhasil dihapus.";
            $pesan_tipe = "sukses";
            // Redirect untuk membersihkan URL
             echo "<script>window.location.href = '?page=kelas&status=hapus_ok';</script>";
             exit;
        } else {
            $pesan = "Gagal menghapus kelas: " . $stmt->error;
            $pesan_tipe = "gagal";
        }
    }
}

// Pesan Sukses (dari redirect hapus)
if(isset($_GET['status']) && $_GET['status'] == 'hapus_ok'){
    $pesan = "Kelas berhasil dihapus.";
    $pesan_tipe = "sukses";
}


// Ambil data kelas
$kelasList = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas ASC");

// Helper class Tailwind
$input_class = "mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm";
$btn_class = "inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2";

?>

<div class="flex-1 p-6">

    <?php if ($pesan): ?>
        <?php 
            $alert_class = ($pesan_tipe == "sukses") 
                ? 'bg-green-100 border-green-400 text-green-700' 
                : 'bg-red-100 border-red-400 text-red-700';
        ?>
        <div class="mb-4 p-4 <?= $alert_class ?> border rounded" role="alert">
            <?= $pesan ?>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6 max-w-lg">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Tambah Kelas Baru</h2>
        <form method="post" class="flex items-end gap-4">
            <div class="flex-grow">
                <label for="nama_kelas" class="block text-sm font-medium text-gray-700">Nama Kelas</label>
                <input type="text" id="nama_kelas" name="nama_kelas" class="<?= $input_class ?>" placeholder="Contoh: 7A, 8B, 9C" required value="<?= htmlspecialchars($_POST['nama_kelas'] ?? '') ?>">
            </div>
            <button type="submit" name="simpan" class="<?= $btn_class ?> bg-green-600 hover:bg-green-700 focus:ring-green-500">
                <i class="fa-solid fa-plus mr-2"></i>Tambah
            </button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
         <div class="px-6 py-4 border-b">
             <h3 class="text-lg font-medium leading-6 text-gray-900">Daftar Kelas</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider w-1/12">No</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Nama Kelas</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider w-1/5">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $no = 1;
                    mysqli_data_seek($kelasList, 0);
                    if ($kelasList->num_rows == 0) {
                        echo "<tr><td colspan='3' class='px-4 py-4 text-center text-gray-500'>Belum ada data kelas.</td></tr>";
                    }
                    while ($row = $kelasList->fetch_assoc()) {
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700 text-center"><?= $no++ ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['nama_kelas']) ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-center">
                                <a href="?page=kelas&hapus=<?= $row['id'] ?>" class="<?= $btn_class ?> bg-red-600 hover:bg-red-700 focus:ring-red-500 !py-1.5 !px-3 !text-xs" onclick="return confirm('Yakin hapus kelas <?= htmlspecialchars($row['nama_kelas']) ?>? Pastikan tidak ada siswa atau wali di kelas ini.')">
                                    Hapus
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>