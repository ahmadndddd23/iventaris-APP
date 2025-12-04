<?php
require_once 'config.php';

// Proses simpan/update barang
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? $_POST['id'] : '';
    $nama_barang = mysqli_real_escape_string($conn, $_POST['nama_barang']);
    $ruangan = mysqli_real_escape_string($conn, $_POST['ruangan']);
    $kondisi = mysqli_real_escape_string($conn, $_POST['kondisi']);
    $nama_petugas = mysqli_real_escape_string($conn, $_POST['nama_petugas']);
    $tanggal_pengecekan = mysqli_real_escape_string($conn, $_POST['tanggal_pengecekan']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    if ($id) {
        // Update barang
        $sql = "UPDATE barang SET 
                nama_barang = '$nama_barang',
                ruangan = '$ruangan',
                kondisi = '$kondisi',
                nama_petugas = '$nama_petugas',
                tanggal_pengecekan = '$tanggal_pengecekan',
                keterangan = '$keterangan'
                WHERE id = $id";
        $message = "Data barang berhasil diupdate!";
    } else {
        // Insert barang baru
        $sql = "INSERT INTO barang (nama_barang, ruangan, kondisi, nama_petugas, tanggal_pengecekan, keterangan) 
                VALUES ('$nama_barang', '$ruangan', '$kondisi', '$nama_petugas', '$tanggal_pengecekan', '$keterangan')";
        $message = "Data barang berhasil ditambahkan!";
    }
    
    if (mysqli_query($conn, $sql)) {
        $success = true;
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// Proses hapus barang
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM barang WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        $success = true;
        $message = "Data barang berhasil dihapus!";
    }
}

// Ambil data untuk edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $result = mysqli_query($conn, "SELECT * FROM barang WHERE id = $id");
    $edit_data = mysqli_fetch_assoc($result);
}

// Ambil semua data barang
$barang_list = mysqli_query($conn, "SELECT * FROM barang ORDER BY ruangan ASC, nama_barang ASC");

// Ambil daftar ruangan untuk QR Code
$ruangan_list = mysqli_query($conn, "SELECT DISTINCT ruangan FROM barang ORDER BY ruangan ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Inventaris Barang</title>
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
            max-width: 1400px;
            margin: 0 auto;
        }
        
        h1 {
            color: white;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .qr-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }
        
        .qr-card h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.8em;
        }
        
        .qr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .qr-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .qr-item:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .qr-item h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        
        .qr-item img {
            width: 200px;
            height: 200px;
            margin: 10px 0;
        }
        
        .qr-link {
            font-size: 12px;
            color: #666;
            word-break: break-all;
            margin: 10px 0;
        }
        
        .download-btn {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }
        
        .form-card h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.8em;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        input, select, textarea {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        button {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .table-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow-x: auto;
        }
        
        .table-card h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.8em;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
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
        
        .badge {
            padding: 5px 12px;
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
        
        .action-btn {
            padding: 6px 12px;
            margin: 0 3px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
        }
        
        .btn-edit {
            background-color: #ffc107;
            color: #000;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-edit:hover {
            background-color: #e0a800;
        }
        
        .btn-delete:hover {
            background-color: #c82333;
        }
        
        .user-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .user-link a {
            color: white;
            text-decoration: none;
            font-size: 18px;
            font-weight: 600;
            padding: 12px 30px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .user-link a:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 Admin Inventaris Barang</h1>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- QR Code Section -->
        <div class="qr-card">
            <h2>📱 QR Code untuk Setiap Ruangan</h2>
            <p style="color: #666; margin-bottom: 20px;">Scan QR Code ini untuk langsung melihat inventaris barang di ruangan tersebut</p>
            <div class="qr-grid">
                <?php 
                mysqli_data_seek($ruangan_list, 0);
                while ($room = mysqli_fetch_assoc($ruangan_list)): 
                    $room_name = $room['ruangan'];
                    $room_url = 'http://localhost/inventaris_app/ruangan.php?nama=' . urlencode($room_name);
                    $qr_api = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($room_url);
                ?>
                    <div class="qr-item">
                        <h3>🚪 <?php echo $room_name; ?></h3>
                        <img src="<?php echo $qr_api; ?>" alt="QR Code <?php echo $room_name; ?>">
                        <div class="qr-link"><?php echo $room_url; ?></div>
                        <a href="<?php echo $qr_api; ?>" download="qr_<?php echo str_replace(' ', '_', $room_name); ?>.png" class="download-btn">
                            💾 Download QR
                        </a>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        
        <div class="form-card">
            <h2><?php echo $edit_data ? '✏️ Edit Barang' : '➕ Tambah Barang Baru'; ?></h2>
            <form method="POST" action="">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nama_barang">Nama Barang *</label>
                        <input type="text" id="nama_barang" name="nama_barang" 
                               value="<?php echo $edit_data ? $edit_data['nama_barang'] : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="ruangan">Ruangan *</label>
                        <input type="text" id="ruangan" name="ruangan" 
                               value="<?php echo $edit_data ? $edit_data['ruangan'] : ''; ?>" 
                               required
                               list="ruangan-list">
                        <datalist id="ruangan-list">
                            <?php 
                            mysqli_data_seek($ruangan_list, 0);
                            while ($room = mysqli_fetch_assoc($ruangan_list)): 
                            ?>
                                <option value="<?php echo $room['ruangan']; ?>">
                            <?php endwhile; ?>
                        </datalist>
                    </div>
                    
                    <div class="form-group">
                        <label for="kondisi">Kondisi *</label>
                        <select id="kondisi" name="kondisi" required>
                            <option value="Baik" <?php echo ($edit_data && $edit_data['kondisi'] == 'Baik') ? 'selected' : ''; ?>>Baik</option>
                            <option value="Rusak Ringan" <?php echo ($edit_data && $edit_data['kondisi'] == 'Rusak Ringan') ? 'selected' : ''; ?>>Rusak Ringan</option>
                            <option value="Rusak Berat" <?php echo ($edit_data && $edit_data['kondisi'] == 'Rusak Berat') ? 'selected' : ''; ?>>Rusak Berat</option>
                            <option value="Hilang" <?php echo ($edit_data && $edit_data['kondisi'] == 'Hilang') ? 'selected' : ''; ?>>Hilang</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="nama_petugas">Nama Petugas *</label>
                        <input type="text" id="nama_petugas" name="nama_petugas" 
                               value="<?php echo $edit_data ? $edit_data['nama_petugas'] : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="tanggal_pengecekan">Tanggal Pengecekan *</label>
                        <input type="date" id="tanggal_pengecekan" name="tanggal_pengecekan" 
                               value="<?php echo $edit_data ? $edit_data['tanggal_pengecekan'] : date('Y-m-d'); ?>" 
                               required>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label for="keterangan">Keterangan</label>
                    <textarea id="keterangan" name="keterangan"><?php echo $edit_data ? $edit_data['keterangan'] : ''; ?></textarea>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-primary">
                        <?php echo $edit_data ? '💾 Update Barang' : '➕ Simpan Barang'; ?>
                    </button>
                    <?php if ($edit_data): ?>
                        <a href="admin.php" style="text-decoration: none;">
                            <button type="button" class="btn-secondary">❌ Batal</button>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="table-card">
            <h2>📋 Daftar Barang</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nama Barang</th>
                        <th>Ruangan</th>
                        <th>Kondisi</th>
                        <th>Petugas</th>
                        <th>Tanggal Cek</th>
                        <th>Keterangan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($barang_list)): ?>
                        <tr>
                            <td><?php echo $row['nama_barang']; ?></td>
                            <td><?php echo $row['ruangan']; ?></td>
                            <td>
                                <?php
                                $badge_class = 'badge-baik';
                                if ($row['kondisi'] == 'Rusak Ringan') $badge_class = 'badge-rusak-ringan';
                                if ($row['kondisi'] == 'Rusak Berat') $badge_class = 'badge-rusak-berat';
                                if ($row['kondisi'] == 'Hilang') $badge_class = 'badge-hilang';
                                ?>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo $row['kondisi']; ?></span>
                            </td>
                            <td><?php echo $row['nama_petugas']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['tanggal_pengecekan'])); ?></td>
                            <td><?php echo $row['keterangan'] ? substr($row['keterangan'], 0, 50) . '...' : '-'; ?></td>
                            <td>
                                <a href="?edit=<?php echo $row['id']; ?>" class="action-btn btn-edit">✏️ Edit</a>
                                <a href="?delete=<?php echo $row['id']; ?>" 
                                   class="action-btn btn-delete" 
                                   onclick="return confirm('Yakin ingin menghapus barang ini?')">🗑️ Hapus</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div class="user-link">
            <a href="user.php">📱 Halaman User</a>
        </div>
    </div>
</body>
</html>