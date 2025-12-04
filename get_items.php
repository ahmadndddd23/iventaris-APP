<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['ruangan']) || empty($_GET['ruangan'])) {
    echo json_encode([]);
    exit;
}

$ruangan = mysqli_real_escape_string($conn, $_GET['ruangan']);

$query = "SELECT id, nama_barang, jumlah, kondisi, status_pengecekan, nama_petugas, keterangan
          FROM barang
          WHERE ruangan = '$ruangan'
          ORDER BY nama_barang ASC";

$result = mysqli_query($conn, $query);

$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}

echo json_encode($items);
?>