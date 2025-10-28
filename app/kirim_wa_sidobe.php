<?php
// Endpoint untuk mengirim WA via Sidobe API

// Sertakan config database
require_once '../app/config.php'; // Sesuaikan path jika perlu

// Set header output ke JSON
header('Content-Type: application/json');

// Default response
$response = ['status' => 'error', 'message' => 'Permintaan tidak valid.'];

// Pastikan metode POST dan ada data nomor WA & pesan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['no_wa']) && isset($_POST['pesan'])) {
    
    $no_wa = $_POST['no_wa'];
    $pesan = $_POST['pesan'];

    // ------------------------------------------------------------------
    // FUNGSI UNTUK MENGIRIM WA (Sama seperti di file lain)
    // ------------------------------------------------------------------
    function kirimSidobeWhatsapp($conn, $no_wa_target, $pesan_teks) {
        // Normalisasi nomor WA
        $no_wa_norm = preg_replace('/[^0-9]/', '', $no_wa_target);
        if (substr($no_wa_norm, 0, 1) === "0") {
            $no_wa_norm = "+62" . substr($no_wa_norm, 1);
        } elseif (substr($no_wa_norm, 0, 2) === "62") {
            $no_wa_norm = "+" . $no_wa_norm;
        } elseif (substr($no_wa_norm, 0, 3) !== "+62") {
            $no_wa_norm = ""; 
        }

        // Ambil secret key dari tabel profil_sekolah
        $secretKey = "";
        $qKey = mysqli_query($conn, "SELECT key_wa_sidobe FROM profil_sekolah LIMIT 1");
        if ($qKey && mysqli_num_rows($qKey) > 0) {
            $rowKey = mysqli_fetch_assoc($qKey);
            $secretKey = $rowKey['key_wa_sidobe'] ?? "";
        }

        // Validasi
        if (empty($no_wa_norm)) {
            return ['status' => 'error', 'message' => 'Nomor WA tidak valid.'];
        }
        if (empty($secretKey) || $secretKey == "MASUKKAN_KEY_SIDOBE_ANDA_DISINI") {
            return ['status' => 'error', 'message' => 'Secret Key WA belum diatur.'];
        }
        if (empty($pesan_teks)) {
             return ['status' => 'error', 'message' => 'Pesan tidak boleh kosong.'];
        }
        
        $data = [
          'phone' => $no_wa_norm,
          'message' => $pesan_teks
        ];

        $ch = curl_init('https://api.sidobe.com/wa/v1/send-message');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
          'Content-Type: application/json',
          'X-Secret-Key: ' . $secretKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 

        $curl_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resData = json_decode($curl_response, true);
        if ($http_code == 200 && $resData && isset($resData['is_success']) && $resData['is_success']) {
          return ['status' => 'success', 'message' => 'WA berhasil dikirim.'];
        } else {
          $error_msg = $resData['message'] ?? $curl_response;
          return ['status' => 'error', 'message' => "Gagal kirim WA ($http_code): " . $error_msg];
        }
    }
    // ------------------------------------------------------------------
    // AKHIR FUNGSI WA
    // ------------------------------------------------------------------

    // Panggil fungsi kirim WA
    $response = kirimSidobeWhatsapp($conn, $no_wa, $pesan);

} else {
     $response['message'] = 'Metode tidak diizinkan atau data tidak lengkap.';
}

// Kembalikan response JSON
echo json_encode($response);
exit;
?>