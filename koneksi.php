<?php
$conn = mysqli_connect('localhost','root','','ivesta');

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>