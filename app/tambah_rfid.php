<?php
// =======================
//  KONEKSI & DATA AWAL
// =======================
if (!isset($conn) || !$conn) {
    include_once 'app/config.php';
}

// Ambil daftar perangkat RFID
$device = mysqli_query($conn, "SELECT id, rfid_name FROM rfid_model");

// Ambil daftar siswa
$siswa = mysqli_query($conn, "SELECT id, nama FROM siswa ORDER BY nama ASC");

// =======================
//  TOMBOL AKSI (CRUD)
// =======================

// ---- Tambah Data ----
if (isset($_POST['tambah'])) {
    $siswa_id = mysqli_real_escape_string($conn, $_POST['siswa_id']);
    $card_uid = mysqli_real_escape_string($conn, $_POST['card_uid']);
    $device_id = mysqli_real_escape_string($conn, $_POST['device_id']);

    $sql = "INSERT INTO kartu_rfid (siswa_id, uid, device_id)
            VALUES ('$siswa_id', '$card_uid', '$device_id')";
    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('✅ Kartu RFID berhasil ditambahkan!'); window.location='?page=tambah_rfid';</script>";
        exit;
    } else {
        echo "<script>alert('❌ Gagal menambahkan kartu: " . mysqli_error($conn) . "');</script>";
    }
}

// ---- Edit Data ----
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $siswa_id = mysqli_real_escape_string($conn, $_POST['siswa_id']);
    $card_uid = mysqli_real_escape_string($conn, $_POST['card_uid']);
    $device_id = mysqli_real_escape_string($conn, $_POST['device_id']);

    $sql = "UPDATE kartu_rfid 
            SET siswa_id='$siswa_id', uid='$card_uid', device_id='$device_id'
            WHERE id='$id'";
    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('✅ Data RFID berhasil diperbarui!'); window.location='?page=tambah_rfid';</script>";
        exit;
    } else {
        echo "<script>alert('❌ Gagal memperbarui: " . mysqli_error($conn) . "');</script>";
    }
}

// ---- Hapus Data ----
if (isset($_GET['hapus'])) {
    $hapus_id = $_GET['hapus'];
    if (mysqli_query($conn, "DELETE FROM kartu_rfid WHERE id='$hapus_id'")) {
        echo "<script>alert('🗑️ Data RFID berhasil dihapus!'); window.location='?page=tambah_rfid';</script>";
        exit;
    } else {
        echo "<script>alert('❌ Gagal menghapus: " . mysqli_error($conn) . "');</script>";
    }
}

// =======================
//  MODE EDIT (AMBIL DATA)
// =======================
$edit = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $edit_query = mysqli_query($conn, "SELECT * FROM kartu_rfid WHERE id='$edit_id'");
    if (mysqli_num_rows($edit_query) > 0) {
        $edit = mysqli_fetch_assoc($edit_query);
    }
}

$btn_warning = "bg-yellow-500 hover:bg-yellow-600 focus:ring-yellow-400";
$btn_info = "bg-cyan-500 hover:bg-cyan-600 focus:ring-cyan-400";
?>

<!-- =======================
     FORM INPUT/EDIT
======================= -->
<form method="POST" class="flex flex-wrap gap-2 items-end">
    <?php if ($edit): ?>
        <input type="hidden" name="id" value="<?= $edit['id']; ?>">
    <?php endif; ?>

    <!-- Pilih Siswa -->
    <div class="px-2">
        <label for="siswa" class="block text-sm font-medium text-gray-700">Siswa</label>
        <select id="siswa" name="siswa_id" required
            class="w-full mt-1 block px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm">
            <option value="">-- Pilih Siswa --</option>
            <?php while ($s = mysqli_fetch_assoc($siswa)) { ?>
                <option value="<?= $s['id']; ?>" <?= ($edit && $edit['siswa_id'] == $s['id']) ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($s['nama']); ?>
                </option>
            <?php } ?>
        </select>
    </div>

    <!-- UID -->
    <div class="px-2">
        <label for="uid" class="block text-sm font-medium text-gray-700">UID Kartu</label>
        <input type="text" id="uid" name="card_uid" required
            value="<?= $edit ? htmlspecialchars($edit['uid']) : ''; ?>"
            placeholder="UID Kartu RFID"
            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm">
    </div>

    <!-- Device -->
    <div class="px-2">
        <label for="device" class="block text-sm font-medium text-gray-700">Perangkat</label>
        <select id="device" name="device_id" required
            class="mt-1 block px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm">
            <option value="">-- Pilih Perangkat --</option>
            <?php
            mysqli_data_seek($device, 0);
            while ($d = mysqli_fetch_assoc($device)) { ?>
                <option value="<?= $d['id']; ?>" <?= ($edit && $edit['device_id'] == $d['id']) ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($d['rfid_name']); ?>
                </option>
            <?php } ?>
        </select>
    </div>

    <!-- Tombol -->
    <div class="px-2">
        <?php if ($edit): ?>
            <button type="submit" name="update"
                class="bg-blue-600 hover:bg-blue-700 focus:ring-blue-500 inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2">
                <i class="fa-solid fa-pen-to-square mr-2"></i>Update
            </button>
            <a href="?page=tambah_rfid"
                class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md bg-gray-400 text-white hover:bg-gray-500">Batal</a>
        <?php else: ?>
            <button type="submit" name="tambah"
                class="bg-green-600 hover:bg-green-700 focus:ring-green-500 inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2">
                <i class="fa-solid fa-plus mr-2"></i>Simpan
            </button>
        <?php endif; ?>
    </div>
</form>

<!-- =======================
     TABEL DATA
======================= -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mt-4">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
                <tr class="text-center">
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Nama</th>
                    <th class="px-4 py-3 text-xs font-bold text-gray-600 uppercase tracking-wider">UID</th>
                    <th class="px-4 py-3 text-xs font-bold text-gray-600 uppercase tracking-wider">Perangkat</th>
                    <th class="px-4 py-3 text-xs font-bold text-gray-600 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php
                $q = mysqli_query($conn, "SELECT kr.*, s.nama AS nama_siswa, d.rfid_name AS device_name
                                          FROM kartu_rfid kr
                                          LEFT JOIN siswa s ON kr.siswa_id = s.id
                                          LEFT JOIN rfid_model d ON kr.device_id = d.id
                                          ORDER BY s.nama ASC");
                if (mysqli_num_rows($q) == 0) {
                    echo "<tr><td colspan='4' class='px-4 py-4 text-center text-gray-500'>Tidak ada data kartu RFID.</td></tr>";
                }
                while ($row = mysqli_fetch_assoc($q)) {
                ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($row['nama_siswa']); ?></td>
                        <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($row['uid']); ?></td>
                        <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($row['device_name']); ?></td>
                        <td class="px-4 py-2 text-sm text-center space-x-2">
                            <a href="?page=tambah_rfid&edit=<?= $row['id']; ?>"
                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md shadow-sm text-white <?= $btn_info ?>">Edit</a>
                            <a href="?page=tambah_rfid&hapus=<?= $row['id']; ?>"
                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md shadow-sm text-white <?= $btn_warning ?>"
                                onclick="return confirm('Yakin ingin menghapus kartu ini?')">Hapus</a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>
