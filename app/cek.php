<?php
session_start();
include 'config.php'; // Make sure $conn is established here

// [DIUBAH] Set header to return JSON
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Terjadi kesalahan tidak diketahui.']; // Default error response

if (!isset($_POST['username']) || !isset($_POST['password'])) {
    $response['message'] = 'Username dan password harus diisi.';
    echo json_encode($response);
    exit;
}

$user = $_POST['username'];
$pass_input = $_POST['password'];

// [DIUBAH] Use Prepared Statements for security
$stmt = $conn->prepare("SELECT id, password, role, nama FROM users WHERE username = ? LIMIT 1");
if (!$stmt) {
     $response['message'] = 'Database query error (prepare): ' . $conn->error;
     echo json_encode($response);
     exit;
}

$stmt->bind_param("s", $user);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    $db_password_hash = $data['password'];
    $user_role = $data['role'];
    $user_id = $data['id']; // Get user ID

    // [DIUBAH] Verify password (still using md5 for consistency with your setup)
    // IMPORTANT: Consider upgrading to password_hash() and password_verify() later!
    // $is_password_correct = (md5($pass_input) === $db_password_hash);
    $is_password_correct = password_verify($pass_input, $db_password_hash);

    // --- (If you upgrade to password_hash(), use this instead) ---
    // $is_password_correct = password_verify($pass_input, $db_password_hash);
    // ---

    if ($is_password_correct) {
        // === Login Success ===
        $_SESSION['username'] = $user;
        $_SESSION['role'] = $user_role;
        $_SESSION['user_id'] = $user_id; // Store user ID in session

        $redirectUrl = '';

        // Determine redirect URL based on role
        if ($user_role === 'admin') {
            $redirectUrl = 'dashboard.php?page=home';
        } elseif ($user_role === 'guru') {
             // [DIUBAH] Pastikan path benar
            $redirectUrl = 'dashboard.php?page=home'; // Redirect guru ke dashboard utama juga? Atau file terpisah?
            // $redirectUrl = '../dashboard_guru.php'; // Jika file terpisah
        } elseif ($user_role === 'siswa') {
            // Find student ID from siswa table based on nisn (username)
            $stmtSiswa = $conn->prepare("SELECT id FROM siswa WHERE nisn = ? LIMIT 1");
             if ($stmtSiswa) {
                 $stmtSiswa->bind_param("s", $user);
                 $stmtSiswa->execute();
                 $resultSiswa = $stmtSiswa->get_result();
                 if ($rowSiswa = $resultSiswa->fetch_assoc()) {
                     $_SESSION['siswa_id'] = $rowSiswa['id']; // Store specific student ID
                     // [DIUBAH] Pastikan path benar
                     $redirectUrl = 'dashboard_siswa.php';
                 } else {
                     // Student user exists but no matching record in siswa table
                     session_destroy(); // Log out user
                     $response['message'] = 'Data siswa tidak ditemukan terkait akun ini. Hubungi admin.';
                     echo json_encode($response);
                     exit;
                 }
                 $stmtSiswa->close();
             } else {
                  session_destroy();
                  $response['message'] = 'Database query error (siswa).';
                  echo json_encode($response);
                  exit;
             }
        } else {
            session_destroy();
            $response['message'] = 'Role pengguna tidak valid.';
            echo json_encode($response);
            exit;
        }

        // Prepare success response
        $response = [
            'status' => 'success',
            'message' => 'Login berhasil, mengalihkan...',
            'redirectUrl' => $redirectUrl
        ];

    } else {
        // === Password Incorrect ===
        $response['message'] = 'Login gagal, username atau password salah.';
    }

} else {
    // === Username Not Found ===
    $response['message'] = 'Login gagal, username atau password salah.';
}

$stmt->close();

// [DIUBAH] Output the JSON response
echo json_encode($response);
exit; // Exit after sending JSON
?>