<?php
$msg = ""; // Untuk notifikasi

// Tambah wali kelas
if (isset($_POST['tambah'])) {
    // [FIX] Baca 'id_kelas', bukan 'kelas'
    $id_kelas = intval($_POST['id_kelas']);
    $id_guru = intval($_POST['id_guru']);

    // [FIX] Cek duplikat kelas
    $cek = mysqli_query($conn, "SELECT id FROM wali_kelas WHERE id_kelas=$id_kelas");
    if (mysqli_num_rows($cek) > 0) {
        $msg = "<div class='mb-4 p-4 bg-red-100 text-red-700 border border-red-400 rounded'>Gagal: Kelas tersebut sudah memiliki wali kelas.</div>";
    } else {
        mysqli_query($conn, "INSERT INTO wali_kelas (id_kelas, id_guru) 
                             VALUES ($id_kelas, '$id_guru')");
        echo "<script>window.location.href = '?page=wali_kelas';</script>";
        exit;
    }
}

// Edit wali kelas
if (isset($_POST['edit'])) {
    $id = intval($_POST['id']);
    // [FIX] Baca 'id_kelas', bukan 'kelas'
    $id_kelas = intval($_POST['id_kelas']);
    $id_guru = intval($_POST['id_guru']);
    

    // [FIX] Cek duplikat (pastikan tidak bentrok dengan ID lain)
    $cek = mysqli_query($conn, "SELECT id FROM wali_kelas WHERE id_kelas=$id_kelas AND id != $id");
     if (mysqli_num_rows($cek) > 0) {
        $msg = "<div class='mb-4 p-4 bg-red-100 text-red-700 border border-red-400 rounded'>Gagal: Kelas tersebut sudah dipegang oleh wali lain.</div>";
    } else {
        mysqli_query($conn, "UPDATE wali_kelas SET id_kelas=$id_kelas, id_guru='$id_guru' WHERE id=$id");
        echo "<script>window.location.href = '?page=wali_kelas';</script>";
        exit;
    }
}

// Hapus wali kelas
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    mysqli_query($conn, "DELETE FROM wali_kelas WHERE id=$id");
    // [FIX] Redirect kembali ke halaman dashboard
    echo "<script>window.location.href = '?page=wali_kelas';</script>";
    exit;
}

// [FIX] Query lebih spesifik untuk menghindari konflik nama kolom
$waliList = mysqli_query($conn, "SELECT w.id, g.nama, w.id_kelas, k.nama_kelas 
                                 FROM wali_kelas w 
                                 JOIN guru g ON w.id_guru = g.id
                                 JOIN kelas k ON w.id_kelas = k.id 
                                 ORDER BY k.nama_kelas");

$kelasList = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");
$guruList = mysqli_query($conn, "SELECT id, nama FROM guru ORDER BY nama");

// Helper class Tailwind
$input_class = "mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm";
$btn_class = "inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2";
?>

<div class="flex-1 p-6">

    <?= $msg ?>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Tambah Wali Kelas Baru</h2>
        <form method="post">
            <div class="flex flex-wrap -mx-2 space-y-4 md:space-y-0">
                
                <div class="w-full md:w-1/2 px-2">
                    <label for="id_kelas_tambah" class="block text-sm font-medium text-gray-700">Kelas</label>
                    <select id="id_kelas_tambah" name="id_kelas" class="<?= $input_class ?>" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php
                        mysqli_data_seek($kelasList, 0); // Reset pointer
                        while ($k = mysqli_fetch_assoc($kelasList)) {
                            echo "<option value='{$k['id']}'>" . htmlspecialchars($k['nama_kelas']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="w-full md:w-1/2 px-2">
                    <label for="id_wali_tambah" class="block text-sm font-medium text-gray-700">Guru</label>
                    <select id="id_wali_tambah" name="id_guru" class="<?= $input_class ?>" required>
                        <option value="">-- Pilih Guru --</option>
                        <?php
                        mysqli_data_seek($guruList, 0); // Reset pointer
                        while ($g = mysqli_fetch_assoc($guruList)) {
                            echo "<option value='{$g['id']}'>" . htmlspecialchars($g['nama']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                
            </div>
            
            <div class="mt-4">
                <button type="submit" name="tambah" class="<?= $btn_class ?> bg-green-600 hover:bg-green-700 focus:ring-green-500">
                    <i class="fa-solid fa-plus mr-2"></i>Tambah
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider w-1/12">No</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Kelas</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Nama Wali</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">NIP</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider w-1/5">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $no = 1;
                    mysqli_data_seek($waliList, 0);
                    if (mysqli_num_rows($waliList) == 0) {
                        echo "<tr><td colspan='5' class='px-4 py-4 text-center text-gray-500'>Belum ada data wali kelas.</td></tr>";
                    }
                    while ($row = mysqli_fetch_assoc($waliList)) {
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700 text-center"><?= $no ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['nama_kelas']) ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['nama_wali']) ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['nip_wali']) ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-center space-x-2">
                                <button type="button" data-modal-toggle="editModal-<?= $row['id'] ?>" class="<?= $btn_class ?> bg-yellow-500 hover:bg-yellow-600 focus:ring-yellow-400 !py-1.5 !px-3 !text-xs">
                                    Edit
                                </button>
                                <a href="?page=wali_kelas&hapus=<?= $row['id'] ?>" class="<?= $btn_class ?> bg-red-600 hover:bg-red-700 focus:ring-red-500 !py-1.5 !px-3 !text-xs" onclick="return confirm('Yakin hapus data ini?')">
                                    Hapus
                                </a>
                            </td>
                        </tr>

                        <div id="editModal-<?= $row['id'] ?>" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 hidden" aria-hidden="true">
                            <div class="relative w-full max-w-lg p-4">
                                <div class="relative bg-white rounded-lg shadow-xl">
                                    <form method="post">
                                        <div class="flex items-center justify-between p-4 border-b rounded-t">
                                            <h3 class="text-xl font-semibold text-gray-900">
                                                Edit Wali Kelas: <?= htmlspecialchars($row['nama_wali']) ?>
                                            </h3>
                                            <button type="button" data-modal-close="editModal-<?= $row['id'] ?>" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center">
                                                <i class="fa-solid fa-times"></i>
                                                <span class="sr-only">Tutup modal</span>
                                            </button>
                                        </div>
                                        <div class="p-6 space-y-4">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            
                                            <div>
                                                <label for="id_kelas_edit_<?= $row['id'] ?>" class="block text-sm font-medium text-gray-700">Kelas</label>
                                                <select id="id_kelas_edit_<?= $row['id'] ?>" name="id_kelas" class="<?= $input_class ?>" required>
                                                    <option value="">-- Pilih Kelas --</option>
                                                    <?php
                                                    $current_kelas_id = $row['id_kelas']; // <-- FIX di sini
                                                    mysqli_data_seek($kelasList, 0); // Reset pointer
                                                    while ($k = mysqli_fetch_assoc($kelasList)) {
                                                        $selected = ($k['id'] == $current_kelas_id) ? 'selected' : '';
                                                        echo "<option value='{$k['id']}' $selected>" . htmlspecialchars($k['nama_kelas']) . "</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            
                                            <div>
                                                <label for="nama_wali_edit_<?= $row['id'] ?>" class="block text-sm font-medium text-gray-700">Nama Wali</label>
                                                <input type="text" id="nama_wali_edit_<?= $row['id'] ?>" name="nama_wali" class="<?= $input_class ?>" value="<?= htmlspecialchars($row['nama_wali']) ?>" required>
                                            </div>
                                            
                                            <div>
                                                <label for="nip_wali_edit_<?= $row['id'] ?>" class="block text-sm font-medium text-gray-700">NIP Wali</label>
                                                <input type="text" id="nip_wali_edit_<?= $row['id'] ?>" name="nip_wali" class="<?= $input_class ?>" value="<?= htmlspecialchars($row['nip_wali']) ?>">
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-end p-4 space-x-2 border-t border-gray-200 rounded-b">
                                            <button data-modal-close="editModal-<?= $row['id'] ?>" type="button" class="<?= $btn_class ?> bg-gray-500 hover:bg-gray-600 focus:ring-gray-400">Batal</button>
                                            <button type="submit" name="edit" class="<?= $btn_class ?> bg-blue-600 hover:bg-blue-700 focus:ring-blue-500">Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    <?php
                        $no++;
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Fungsi untuk menampilkan modal
    const showModal = (id) => {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
        }
    };

    // Fungsi untuk menyembunyikan modal
    const hideModal = (id) => {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
        }
    };

    // Tambahkan event listener untuk semua tombol "data-modal-toggle"
    document.querySelectorAll('[data-modal-toggle]').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const modalId = button.getAttribute('data-modal-toggle');
            showModal(modalId);
        });
    });

    // Tambahkan event listener untuk semua tombol "data-modal-close"
    document.querySelectorAll('[data-modal-close]').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const modalId = button.getAttribute('data-modal-close');
            hideModal(modalId);
        });
    });
});
</script>