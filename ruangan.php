<?php
require_once 'config.php';

// Ambil nama ruangan dari parameter URL
$ruangan = isset($_GET['nama']) ? mysqli_real_escape_string($conn, $_GET['nama']) : '';

if (empty($ruangan)) {
    header('Location: user.php');
    exit();
}

// Ambil data barang di ruangan ini
$sql = "SELECT * FROM barang WHERE ruangan = '$ruangan' ORDER BY nama_barang ASC";
$barang_list = mysqli_query($conn, $sql);
$total_barang = mysqli_num_rows($barang_list);

// Hitung statistik kondisi
$stats = mysqli_query($conn, "
    SELECT 
        kondisi,
        COUNT(*) as jumlah
    FROM barang 
    WHERE ruangan = '$ruangan'
    GROUP BY kondisi
");

$kondisi_stats = [];
while ($stat = mysqli_fetch_assoc($stats)) {
    $kondisi_stats[$stat['kondisi']] = $stat['jumlah'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventaris - <?php echo htmlspecialchars($ruangan); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h1 {
            color: #667eea;
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .header .room-name {
            color: #764ba2;
            font-size: 1.8em;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid #667eea;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #333;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .items-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }
        
        .items-card h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.8em;
        }
        
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .item-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }
        
        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .item-name {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }
        
        .item-detail {
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .detail-label {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }
        
        .detail-value {
            color: #333;
            font-weight: 600;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-baik {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-rusak-ringan {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-rusak-berat {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .badge-hilang {
            background-color: #d6d8db;
            color: #383d41;
        }
        
        .keterangan {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
            color: #666;
            font-size: 14px;
            font-style: italic;
        }
        
        .back-link {
            text-align: center;
            margin-top: 30px;
        }
        
        .back-link a {
            color: white;
            text-decoration: none;
            font-size: 18px;
            font-weight: 600;
            padding: 12px 30px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            display: inline-block;
            transition: all 0.3s;
            margin: 0 10px;
        }
        
        .back-link a:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        
        .empty-state-text {
            font-size: 1.2em;
            color: #999;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .back-link {
                display: none;
            }
            
            .items-card, .header {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Inventaris Barang</h1>
            <div class="room-name">🚪 <?php echo htmlspecialchars($ruangan); ?></div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_barang; ?></div>
                    <div class="stat-label">Total Barang</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo isset($kondisi_stats['Baik']) ? $kondisi_stats['Baik'] : 0; ?></div>
                    <div class="stat-label">Kondisi Baik</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo isset($kondisi_stats['Rusak Ringan']) ? $kondisi_stats['Rusak Ringan'] : 0; ?></div>
                    <div class="stat-label">Rusak Ringan</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo isset($kondisi_stats['Rusak Berat']) ? $kondisi_stats['Rusak Berat'] : 0; ?></div>
                    <div class="stat-label">Rusak Berat</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo isset($kondisi_stats['Hilang']) ? $kondisi_stats['Hilang'] : 0; ?></div>
                    <div class="stat-label">Hilang</div>
                </div>
            </div>
        </div>
        
        <div class="items-card">
            <h2>📋 Daftar Barang</h2>
            
            <?php if ($total_barang > 0): ?>
                <!-- View Grid -->
                <div class="items-grid">
                    <?php 
                    mysqli_data_seek($barang_list, 0);
                    while ($item = mysqli_fetch_assoc($barang_list)): 
                        $badge_class = 'badge-baik';
                        if ($item['kondisi'] == 'Rusak Ringan') $badge_class = 'badge-rusak-ringan';
                        if ($item['kondisi'] == 'Rusak Berat') $badge_class = 'badge-rusak-berat';
                        if ($item['kondisi'] == 'Hilang') $badge_class = 'badge-hilang';
                    ?>
                        <div class="item-card">
                            <div class="item-name"><?php echo htmlspecialchars($item['nama_barang']); ?></div>
                            
                            <div class="item-detail">
                                <span class="detail-label">Kondisi:</span>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo $item['kondisi']; ?></span>
                            </div>
                            
                            <div class="item-detail">
                                <span class="detail-label">Petugas:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($item['nama_petugas']); ?></span>
                            </div>
                            
                            <div class="item-detail">
                                <span class="detail-label">Tanggal Cek:</span>
                                <span class="detail-value"><?php echo date('d/m/Y', strtotime($item['tanggal_pengecekan'])); ?></span>
                            </div>
                            
                            <?php if ($item['keterangan']): ?>
                                <div class="keterangan">
                                    "<?php echo htmlspecialchars($item['keterangan']); ?>"
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <!-- View Table for Print -->
                <table style="display: none;" class="print-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Barang</th>
                            <th>Kondisi</th>
                            <th>Petugas</th>
                            <th>Tanggal Cek</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($barang_list, 0);
                        $no = 1;
                        while ($item = mysqli_fetch_assoc($barang_list)): 
                        ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($item['nama_barang']); ?></td>
                                <td><?php echo $item['kondisi']; ?></td>
                                <td><?php echo htmlspecialchars($item['nama_petugas']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($item['tanggal_pengecekan'])); ?></td>
                                <td><?php echo htmlspecialchars($item['keterangan']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <div class="empty-state-text">Belum ada barang di ruangan ini</div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="back-link">
            <a href="user.php">🏠 Kembali ke Halaman Utama</a>
            <a href="admin.php">⚙️ Halaman Admin</a>
            <a href="javascript:window.print()">🖨️ Cetak Laporan</a>
        </div>
    </div>
    
    <style>
        @media print {
            .items-grid {
                display: none !important;
            }
            .print-table {
                display: table !important;
            }
        }
    </style>
</body>
</html>