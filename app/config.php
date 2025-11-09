<?php
// $host = "202.10.43.93";
// $user = "smkf6613_absensinew";
// $pass = "7VMw?P-A59T_ZBVC";
// $db = "smkf6613_absensi_new";

$host = "localhost";
$user = "root";
$pass = "";
$db = "absen_new";

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>
