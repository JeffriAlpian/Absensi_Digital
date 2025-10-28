<?php
// update_key_wa.php

$msg = "";

// Ambil key lama dari database
$sql = "SELECT key_wa_sidobe FROM profil_sekolah LIMIT 1";
$result = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($result);

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $key = mysqli_real_escape_string($conn, $_POST['key_wa_sidobe']);
    $update = "UPDATE profil_sekolah SET key_wa_sidobe='$key'";
    if (mysqli_query($conn, $update)) {
        $msg = "✅ Key WA berhasil diperbarui!";
        $data['key_wa_sidobe'] = $key;
    } else {
        $msg = "❌ Gagal memperbarui key: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Update Key WA Sidobe</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f7f7f7;
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 480px;
      margin: 20px auto;
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    h2 {
      text-align: center;
      color: #333;
      margin-bottom: 20px;
    }
    label {
      display: block;
      font-weight: bold;
      margin-bottom: 6px;
      color: #555;
    }
    input[type="text"] {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 8px;
      margin-bottom: 15px;
      font-size: 16px;
    }
    button {
      width: 100%;
      padding: 12px;
      background: #007BFF;
      color: white;
      font-size: 16px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
    }
    button:hover {
      background: #0056b3;
    }
    .btn-back {
      display: block;
      text-align: center;
      margin-top: 15px;
      padding: 12px;
      background: #6c757d;
      color: white;
      border-radius: 8px;
      text-decoration: none;
      font-size: 16px;
    }
    .btn-back:hover {
      background: #5a6268;
    }
    .message {
      text-align: center;
      margin-bottom: 15px;
      font-weight: bold;
    }
    .success { color: green; }
    .error { color: red; }
    .guide {
      background: #f1f9ff;
      border-left: 5px solid #007BFF;
      padding: 12px;
      font-size: 14px;
      color: #333;
      border-radius: 8px;
      margin-top: 20px;
    }
    .guide h3 {
      margin: 0 0 8px 0;
      font-size: 16px;
      color: #007BFF;
    }
    .guide ol {
      margin: 0;
      padding-left: 18px;
    }
    .guide li {
      margin-bottom: 6px;
    }
  </style>
  <script>
    function confirmSubmit() {
      return confirm("Apakah Anda yakin ingin menyimpan perubahan key WA?");
    }
  </script>
</head>
<body>
  <div class="container">
    <h2>Update Key WA Sidobe</h2>
    <?php if ($msg): ?>
      <div class="message <?php echo (strpos($msg,'berhasil')!==false)?'success':'error'; ?>">
        <?php echo $msg; ?>
      </div>
    <?php endif; ?>
    <form method="POST" onsubmit="return confirmSubmit()">
      <label for="key_wa_sidobe">Key WA Sidobe:</label>
      <input type="text" id="key_wa_sidobe" name="key_wa_sidobe"
             value="<?php echo htmlspecialchars($data['key_wa_sidobe'] ?? ''); ?>" required>
      <button type="submit">Simpan</button>
    </form>

    <a href="dashboard.php" class="btn-back">⬅️ Kembali ke Dashboard</a>

    <div class="guide">
      <h3>Panduan Mendapatkan Secret Key Sidobe</h3>
      <ol>
        <li>Daftar akun di <a href="https://sidobe.com" target="_blank">https://sidobe.com</a></li>
        <li>Login ke dashboard Sidobe.</li>
        <li>Buka menu <b>Device</b> lalu lakukan <b>Add Device</b> dan scan QR WhatsApp sampai status <b>Connected</b>.</li>
        <li>Pada menu <b>API / WhatsApp Gateway</b>, pilih <b>Generate Secret Key</b>.</li>
        <li>Salin kode <b>Secret Key</b> yang diberikan (contoh: <code>ezhwStwMFxLawkhKaWOZsYBtuehGPNTshvRoBZtymXvPUXxDaZ</code>).</li>
        <li>Tempelkan pada kolom di atas, lalu klik <b>Simpan</b>.</li>
        <li>Gunakan key ini untuk mengirim pesan WhatsApp otomatis dari aplikasi Anda.</li>
      </ol>
    </div>
  </div>
</body>
</html>
