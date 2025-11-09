<?php
// =============================================
//  Halaman Pendaftaran & Manajemen Device RFID
// =============================================

// Proses simpan kartu baru
if (isset($_POST['submit'])) {
    $rfid_name = mysqli_real_escape_string($conn, $_POST['rfid_name']);
    $api_length = 32; // panjang API key
    $api_key = bin2hex(random_bytes($api_length));

    $sql = "INSERT INTO rfid_model (rfid_name, api_key)
            VALUES ('$rfid_name', '$api_key')";
    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('Device RFID berhasil didaftarkan!');</script>";
        exit("<script>window.location.href='?page=device_rfid';</script>");
    } else {
        echo "Gagal: " . mysqli_error($conn);
        exit;
    }
}

// Proses edit device
if (isset($_POST['edit'])) {
    $rfid_name = mysqli_real_escape_string($conn, $_POST['rfid_name']);
    $device_id = intval($_POST['device_id']);

    $sql = "UPDATE rfid_model SET rfid_name='$rfid_name' WHERE id='$device_id'";
    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('Device RFID berhasil diupdate!');</script>";
        exit("<script>window.location.href='?page=device_rfid';</script>");
    } else {
        echo "Gagal: " . mysqli_error($conn);
        exit;
    }
}

// Ambil data untuk edit (jika ada)
$edit = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_query = mysqli_query($conn, "SELECT * FROM rfid_model WHERE id='$edit_id'");
    if (mysqli_num_rows($edit_query) > 0) {
        $edit = mysqli_fetch_assoc($edit_query);
    } else {
        echo "<script>alert('Device RFID tidak ditemukan.');</script>";
        exit("<script>window.location.href='?page=device_rfid';</script>");
    }
}

// Proses hapus device
if (isset($_GET['hapus'])) {
    $hapus_id = intval($_GET['hapus']);
    if (mysqli_query($conn, "DELETE FROM rfid_model WHERE id='$hapus_id'")) {
        echo "<script>alert('Device RFID berhasil dihapus!');</script>";
        exit("<script>window.location.href='?page=device_rfid';</script>");
    } else {
        echo "Gagal menghapus: " . mysqli_error($conn);
        exit;
    }
}

// Style tombol
$btn_warning = "bg-yellow-500 hover:bg-yellow-600 focus:ring-yellow-400";
$btn_info = "bg-cyan-500 hover:bg-cyan-600 focus:ring-cyan-400";
?>

<!-- ===================================== -->
<!-- FORM INPUT / UPDATE DEVICE RFID -->
<!-- ===================================== -->
<form method="POST" class="flex gap-2 items-end mb-4">
    <div class="px-2">
        <label for="rfid_name" class="block text-sm font-medium text-gray-700">Nama Device</label>
        <input 
            type="text" 
            id="rfid_name" 
            name="rfid_name" 
            value="<?= htmlspecialchars($edit['rfid_name'] ?? '') ?>" 
            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" 
            placeholder="Nama RFID" 
            required
        >
    </div>

    <?php if ($edit) { ?>
        <input type="hidden" name="device_id" value="<?= $edit['id']; ?>">
        <button 
            type="submit" 
            name="edit" 
            class="bg-blue-600 hover:bg-blue-700 focus:ring-blue-500 inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2"
        >
            <i class="fa-solid fa-pen-to-square mr-2"></i>Update
        </button>
    <?php } else { ?>
        <button 
            type="submit" 
            name="submit" 
            class="bg-green-600 hover:bg-green-700 focus:ring-green-500 inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2"
        >
            <i class="fa-solid fa-plus mr-2"></i>Simpan
        </button>
    <?php } ?>
</form>

<!-- ===================================== -->
<!-- TABEL DATA DEVICE RFID -->
<!-- ===================================== -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
                <tr class="text-center">
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Nama</th>
                    <th class="px-4 py-3 text-xs font-bold text-gray-600 uppercase tracking-wider">API Key</th>
                    <th class="px-4 py-3 text-xs font-bold text-gray-600 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody id="siswaTableBody" class="bg-white divide-y divide-gray-200">
                <?php
                $q = mysqli_query($conn, "SELECT * FROM rfid_model ORDER BY id ASC LIMIT 50");
                if (mysqli_num_rows($q) == 0) {
                    echo "<tr><td colspan='3' class='px-4 py-4 text-center text-gray-500'>Tidak ada data RFID device.</td></tr>";
                } else {
                    while ($row = mysqli_fetch_assoc($q)) {
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['rfid_name']) ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['api_key']) ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-center space-x-2">
                                <a href="?page=device_rfid&edit=<?= $row['id'] ?>" 
                                   class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md shadow-sm text-white <?= $btn_info ?>">
                                    Edit
                                </a>
                                <a href="?page=device_rfid&hapus=<?= $row['id'] ?>" 
                                   class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md shadow-sm text-white <?= $btn_warning ?>" 
                                   onclick="return confirm('Yakin ingin menghapus device ini?')">
                                    Hapus
                                </a>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
