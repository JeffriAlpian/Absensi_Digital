<?php
// =====================
// KONFIGURASI
// =====================
$secretKey = 'KtIbbo HWYEvruqDzCXKuNoddYYHUpfAEmCOsrlSjmagVHswzJH'; // Ganti dengan Secret Key Sidobe Anda
$phone = '+6281578049508';       // Ganti dengan nomor tujuan WA (format internasional)
$message = 'Halo Pak/Ibu? saya menyimak tentang aplikasi absensi siswa qr. Bagaimana alur penggunaanya?';

// =====================
// SIAPKAN DATA
// =====================
$data = [
    'phone' => $phone,
    'message' => $message
];

// =====================
// INIT cURL
// =====================
$ch = curl_init('https://api.sidobe.com/wa/v1/send-message');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Secret-Key: ' . $secretKey
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

// =====================
// EKSEKUSI & HANDLE ERROR
// =====================
$response = curl_exec($ch);

if (curl_errno($ch)) {
    // Jika ada error cURL
    echo 'cURL Error: ' . curl_error($ch);
} else {
    // Decode JSON response
    $resData = json_decode($response, true);

    if ($resData && isset($resData['is_success']) && $resData['is_success']) {
        echo "✅ Pesan berhasil dikirim!\n";
        echo "ID Pesan: " . $resData['data']['id'] . "\n";
    } else {
        echo "❌ Gagal mengirim pesan.\n";
        echo "Response: " . $response . "\n";
    }
}

// =====================
// TUTUP cURL
// =====================
curl_close($ch);
?>
