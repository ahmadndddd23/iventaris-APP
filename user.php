<?php
require_once 'config.php';

// Check for success message from redirect
if (isset($_GET['success']) && isset($_GET['message'])) {
    $success = true;
    $message = urldecode($_GET['message']);
}

// Function to log changes
function logChange($barang_id, $field, $old_value, $new_value, $changed_by = 'System') {
    global $conn;
    $sql = "INSERT INTO barang_history (barang_id, field_name, old_value, new_value, changed_by)
            VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "issss", $barang_id, $field, $old_value, $new_value, $changed_by);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Proses simpan/update barang
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id) {
        // Update mode - only update kondisi and related fields
        $kondisi = mysqli_real_escape_string($conn, $_POST['kondisi']);
        $status_pengecekan = mysqli_real_escape_string($conn, $_POST['status_pengecekan'] ?? 'Sudah_Dicek');
        $nama_petugas = mysqli_real_escape_string($conn, $_POST['nama_petugas']);
        $tanggal_pengecekan = mysqli_real_escape_string($conn, $_POST['tanggal_pengecekan']);
        $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);

        // Update barang
        $jumlah = intval($_POST['jumlah']);
        $sql = "UPDATE barang SET
                jumlah = $jumlah,
                kondisi = '$kondisi',
                status_pengecekan = '$status_pengecekan',
                nama_petugas = '$nama_petugas',
                tanggal_pengecekan = '$tanggal_pengecekan',
                keterangan = '$keterangan'
                WHERE id = $id";

        if (mysqli_query($conn, $sql)) {
            // Log changes - only if item exists
            $old_result = mysqli_query($conn, "SELECT * FROM barang WHERE id = $id");
            if ($old_result && mysqli_num_rows($old_result) > 0) {
                $old_data = mysqli_fetch_assoc($old_result);
                $fields_to_check = ['jumlah', 'kondisi', 'status_pengecekan', 'nama_petugas', 'tanggal_pengecekan', 'keterangan'];
                foreach ($fields_to_check as $field) {
                    if (isset($old_data[$field]) && $old_data[$field] != ${$field}) {
                        logChange($id, $field, $old_data[$field], ${$field}, $nama_petugas ?: 'Admin');
                    }
                }
            }
        }

        $message = "Data barang berhasil diupdate!";
    } else {
        // Insert mode - check if item already exists
        $nama_barang = mysqli_real_escape_string($conn, $_POST['nama_barang']);
        $ruangan = mysqli_real_escape_string($conn, $_POST['ruangan']);
        $jumlah = intval($_POST['jumlah']);
        $kondisi = mysqli_real_escape_string($conn, $_POST['kondisi']);
        $status_pengecekan = mysqli_real_escape_string($conn, $_POST['status_pengecekan'] ?? 'Belum_Dicek');
        $nama_petugas = mysqli_real_escape_string($conn, $_POST['nama_petugas']);
        $tanggal_pengecekan = mysqli_real_escape_string($conn, $_POST['tanggal_pengecekan']);
        $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);

        // Check if item with same name and room already exists
        $check_sql = "SELECT id, jumlah FROM barang WHERE nama_barang = '$nama_barang' AND ruangan = '$ruangan'";
        $check_result = mysqli_query($conn, $check_sql);

        if (mysqli_num_rows($check_result) > 0) {
            // Item exists, update quantity
            $existing_item = mysqli_fetch_assoc($check_result);
            $new_jumlah = $existing_item['jumlah'] + $jumlah;
            $item_id = $existing_item['id'];

            $sql = "UPDATE barang SET
                    jumlah = $new_jumlah,
                    kondisi = '$kondisi',
                    status_pengecekan = '$status_pengecekan',
                    nama_petugas = '$nama_petugas',
                    tanggal_pengecekan = '$tanggal_pengecekan',
                    keterangan = '$keterangan'
                    WHERE id = $item_id";

            // Log the quantity increase
            logChange($item_id, 'jumlah', $existing_item['jumlah'], $new_jumlah, $nama_petugas ?: 'Admin');

            $message = "Jumlah barang berhasil ditambahkan! Total sekarang: $new_jumlah";
        } else {
            // Item doesn't exist, insert new
            $sql = "INSERT INTO barang (nama_barang, ruangan, jumlah, kondisi, status_pengecekan, nama_petugas, tanggal_pengecekan, keterangan)
                    VALUES ('$nama_barang', '$ruangan', $jumlah, '$kondisi', '$status_pengecekan', '$nama_petugas', '$tanggal_pengecekan', '$keterangan')";
            $message = "Data barang berhasil ditambahkan!";
        }
    }

    if (mysqli_query($conn, $sql)) {
        $success = true;
        // Redirect to prevent form resubmission on refresh
        $redirect_url = $_SERVER['PHP_SELF'];
        $params = [];
        if (isset($_GET['mode'])) {
            $params[] = 'mode=' . $_GET['mode'];
        }
        $params[] = 'success=1';
        $params[] = 'message=' . urlencode($message);
        $redirect_url .= '?' . implode('&', $params);
        header('Location: ' . $redirect_url);
        exit();
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// Proses hapus barang
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $sql = "DELETE FROM barang WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        $success = true;
        $message = "Data barang berhasil dihapus!";
        // Redirect to prevent form resubmission on refresh
        $redirect_url = $_SERVER['PHP_SELF'];
        $params = [];
        if (isset($_GET['mode'])) {
            $params[] = 'mode=' . $_GET['mode'];
        }
        $params[] = 'success=1';
        $params[] = 'message=' . urlencode($message);
        $redirect_url .= '?' . implode('&', $params);
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Proses bulk update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    $selected_items = isset($_POST['selected_items']) ? $_POST['selected_items'] : [];
    $bulk_kondisi = mysqli_real_escape_string($conn, $_POST['bulk_kondisi']);
    $bulk_status = mysqli_real_escape_string($conn, $_POST['bulk_status']);
    $bulk_petugas = mysqli_real_escape_string($conn, $_POST['bulk_petugas']);

    if (!empty($selected_items) && (!empty($bulk_kondisi) || !empty($bulk_status))) {
        $ids = implode(',', array_map('intval', $selected_items));
        $update_fields = [];

        if ($bulk_kondisi) $update_fields[] = "kondisi = '$bulk_kondisi'";
        if ($bulk_status) $update_fields[] = "status_pengecekan = '$bulk_status'";
        if ($bulk_petugas) $update_fields[] = "nama_petugas = '$bulk_petugas'";
        $update_fields[] = "tanggal_pengecekan = CURDATE()";

        $sql = "UPDATE barang SET " . implode(', ', $update_fields) . " WHERE id IN ($ids)";
        if (mysqli_query($conn, $sql)) {
            // Log bulk changes - only for existing items
            foreach ($selected_items as $item_id) {
                $check_result = mysqli_query($conn, "SELECT id FROM barang WHERE id = $item_id");
                if ($check_result && mysqli_num_rows($check_result) > 0) {
                    if ($bulk_kondisi) logChange($item_id, 'kondisi', 'bulk_update', $bulk_kondisi, $bulk_petugas ?: 'Admin');
                    if ($bulk_status) logChange($item_id, 'status_pengecekan', 'bulk_update', $bulk_status, $bulk_petugas ?: 'Admin');
                    if ($bulk_petugas) logChange($item_id, 'nama_petugas', 'bulk_update', $bulk_petugas, $bulk_petugas ?: 'Admin');
                    logChange($item_id, 'tanggal_pengecekan', 'bulk_update', date('Y-m-d'), $bulk_petugas ?: 'Admin');
                }
            }

            $success = true;
            $message = "Bulk update berhasil! " . count($selected_items) . " item diperbarui.";
            // Redirect to prevent form resubmission on refresh
            $redirect_url = $_SERVER['PHP_SELF'];
            $params = [];
            if (isset($_GET['mode'])) {
                $params[] = 'mode=' . $_GET['mode'];
            }
            $params[] = 'success=1';
            $params[] = 'message=' . urlencode($message);
            $redirect_url .= '?' . implode('&', $params);
            header('Location: ' . $redirect_url);
            exit();
        } else {
            $error = "Error bulk update: " . mysqli_error($conn);
        }
    }
}

// Mode: input atau update
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'input'; // default ke input

// Filter dan search
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_ruangan = isset($_GET['ruangan']) ? mysqli_real_escape_string($conn, $_GET['ruangan']) : '';
$filter_kondisi = isset($_GET['kondisi']) ? mysqli_real_escape_string($conn, $_GET['kondisi']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

// Ambil data untuk edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $result = mysqli_query($conn, "SELECT * FROM barang WHERE id = $id");
    $edit_data = mysqli_fetch_assoc($result);
}

// Ambil daftar ruangan
$ruangan_list = mysqli_query($conn, "SELECT DISTINCT ruangan FROM barang ORDER BY ruangan ASC");

// Hitung total barang per ruangan
$stats_query = mysqli_query($conn, "
    SELECT 
        ruangan,
        COUNT(*) as total,
        SUM(CASE WHEN kondisi = 'Baik' THEN 1 ELSE 0 END) as baik,
        SUM(CASE WHEN kondisi = 'Rusak Ringan' THEN 1 ELSE 0 END) as rusak_ringan,
        SUM(CASE WHEN kondisi = 'Rusak Berat' THEN 1 ELSE 0 END) as rusak_berat,
        SUM(CASE WHEN kondisi = 'Hilang' THEN 1 ELSE 0 END) as hilang
    FROM barang 
    GROUP BY ruangan
    ORDER BY ruangan ASC
");

$room_stats = [];
$room_items = [];
while ($stat = mysqli_fetch_assoc($stats_query)) {
    $room_stats[$stat['ruangan']] = $stat;
}

// Get items per room (limit to 3 items per room for display)
foreach ($room_stats as $room_name => $stats) {
    $items_query = mysqli_query($conn, "SELECT nama_barang, kondisi FROM barang WHERE ruangan = '" . mysqli_real_escape_string($conn, $room_name) . "' ORDER BY nama_barang LIMIT 3");
    $room_items[$room_name] = [];
    while ($item = mysqli_fetch_assoc($items_query)) {
        $room_items[$room_name][] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User - Scan QR Code Ruangan</title>
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
        
        h1 {
            color: white;
            text-align: center;
            margin-bottom: 20px;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .subtitle {
            color: white;
            text-align: center;
            margin-bottom: 40px;
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .info-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .info-card h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.8em;
        }
        
        .info-card p {
            color: #666;
            font-size: 1.1em;
            line-height: 1.6;
        }
        
        .scan-icon {
            font-size: 80px;
            margin: 20px 0;
        }
        
        .rooms-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }
        
        .rooms-card h2 {
            color: #667eea;
            margin-bottom: 25px;
            font-size: 1.8em;
            text-align: center;
        }
        
        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .room-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            border-radius: 12px;
            color: white;
            text-decoration: none;
            display: block;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            min-height: 200px;
        }
        
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.5);
        }
        
        .room-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }
        
        .room-name {
            font-size: 1.5em;
            font-weight: 600;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        
        .room-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 15px;
            position: relative;
            z-index: 1;
        }
        
        .stat-item {
            background: rgba(255,255,255,0.2);
            padding: 10px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.8em;
            font-weight: bold;
        }
        
        .stat-label {
            font-size: 0.85em;
            opacity: 0.9;
            margin-top: 5px;
        }
        
        .qr-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }
        
        .qr-section h2 {
            color: #667eea;
            margin-bottom: 25px;
            font-size: 1.8em;
            text-align: center;
        }
        
        .qr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
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
            border: 2px solid #e0e0e0;
            border-radius: 8px;
        }
        
        .qr-link {
            font-size: 11px;
            color: #666;
            word-break: break-all;
            margin: 10px 0;
        }
        
        .view-btn {
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
        
        .view-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .admin-link {
            text-align: center;
            margin-top: 30px;
        }
        
        .admin-link a {
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
        
        .admin-link a:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .instructions {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            color: white;
        }
        
        .instructions h3 {
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        
        .instructions ol {
            margin-left: 20px;
            line-height: 1.8;
        }
        
        .instructions li {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 Sistem Inventaris Barang</h1>
        <div class="subtitle">Kelola Data Barang dan Lihat QR Code per Ruangan</div>

        <?php if (isset($success)): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid #c3e6cb;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid #f5c6cb;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Mode Toggle -->
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="display: inline-flex; gap: 10px;">
                <a href="?mode=input" style="background: <?php echo $mode == 'input' ? '#28a745' : '#6c757d'; ?>; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; border: 2px solid <?php echo $mode == 'input' ? '#28a745' : '#6c757d'; ?>;">
                    ➕ Input Barang
                </a>
                <a href="?mode=update" style="background: <?php echo $mode == 'update' ? '#007bff' : '#6c757d'; ?>; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; border: 2px solid <?php echo $mode == 'update' ? '#007bff' : '#6c757d'; ?>;">
                    ✏️ Update Kondisi
                </a>
            </div>
            <div style="margin-top: 10px; color: <?php echo $mode == 'input' ? '#28a745' : '#007bff'; ?>; font-weight: bold;">
                Mode: <?php echo $mode == 'input' ? 'Input Barang Baru' : 'Update Kondisi Barang'; ?>
            </div>
        </div>

        <!-- Form Input/Update -->
        <div class="info-card">
            <h2><?php
                if ($mode == 'input') {
                    echo '➕ Input Barang Baru';
                } else {
                    echo $edit_data ? '🔍 Update Kondisi Barang' : '✏️ Pilih Barang untuk Update';
                }
            ?></h2>
            <form method="POST" action="" style="margin-top: 15px;">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                <?php endif; ?>

                <?php if ($mode == 'input'): ?>
                    <!-- Form untuk Input Mode -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                        <div>
                            <label for="nama_barang" style="display: block; margin-bottom: 5px; font-weight: bold;">Nama Barang *</label>
                            <input type="text" id="nama_barang" name="nama_barang"
                                   value="<?php echo $edit_data ? $edit_data['nama_barang'] : ''; ?>"
                                   required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        </div>

                        <div>
                            <label for="ruangan" style="display: block; margin-bottom: 5px; font-weight: bold;">Ruangan *</label>
                            <select id="ruangan" name="ruangan" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                <option value="">-- Pilih Ruangan --</option>
                                <option value="Aula" <?php echo ($edit_data && $edit_data['ruangan'] == 'Aula') ? 'selected' : ''; ?>>Aula</option>
                                <option value="Dapur" <?php echo ($edit_data && $edit_data['ruangan'] == 'Dapur') ? 'selected' : ''; ?>>Dapur</option>
                                <option value="Klinik" <?php echo ($edit_data && $edit_data['ruangan'] == 'Klinik') ? 'selected' : ''; ?>>Klinik</option>
                            </select>
                        </div>

                        <div>
                            <label for="jumlah" style="display: block; margin-bottom: 5px; font-weight: bold;">Jumlah *</label>
                            <input type="number" id="jumlah" name="jumlah" min="1" value="1"
                                   required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        </div>

                        <div>
                            <label for="nama_petugas" style="display: block; margin-bottom: 5px; font-weight: bold;">Nama Petugas *</label>
                            <input type="text" id="nama_petugas" name="nama_petugas"
                                   value="<?php echo $edit_data ? $edit_data['nama_petugas'] : ''; ?>"
                                   required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        </div>
                    </div>

                    <!-- Hidden fields untuk input mode -->
                    <input type="hidden" name="kondisi" value="Baik">
                    <input type="hidden" name="status_pengecekan" value="Belum_Dicek">
                    <input type="hidden" name="tanggal_pengecekan" value="<?php echo date('Y-m-d'); ?>">
                    <input type="hidden" name="keterangan" value="Barang baru ditambahkan">

                <?php elseif ($mode == 'update' && $edit_data): ?>
                    <!-- Form untuk Update Mode -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                        <div>
                            <label for="nama_barang" style="display: block; margin-bottom: 5px; font-weight: bold;">Nama Barang</label>
                            <input type="text" value="<?php echo htmlspecialchars($edit_data['nama_barang']); ?>" readonly
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background: #f8f9fa;">
                        </div>

                        <div>
                            <label for="ruangan" style="display: block; margin-bottom: 5px; font-weight: bold;">Ruangan</label>
                            <input type="text" value="<?php echo htmlspecialchars($edit_data['ruangan']); ?>" readonly
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background: #f8f9fa;">
                        </div>

                        <div>
                            <label for="kondisi" style="display: block; margin-bottom: 5px; font-weight: bold;">Kondisi *</label>
                            <select id="kondisi" name="kondisi" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                <option value="Baik" <?php echo ($edit_data['kondisi'] == 'Baik') ? 'selected' : ''; ?>>Baik</option>
                                <option value="Rusak Ringan" <?php echo ($edit_data['kondisi'] == 'Rusak Ringan') ? 'selected' : ''; ?>>Rusak Ringan</option>
                                <option value="Rusak Berat" <?php echo ($edit_data['kondisi'] == 'Rusak Berat') ? 'selected' : ''; ?>>Rusak Berat</option>
                                <option value="Hilang" <?php echo ($edit_data['kondisi'] == 'Hilang') ? 'selected' : ''; ?>>Hilang</option>
                            </select>
                        </div>

                        <div>
                            <label for="status_pengecekan" style="display: block; margin-bottom: 5px; font-weight: bold;">Status Pengecekan</label>
                            <select id="status_pengecekan" name="status_pengecekan" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                <option value="Belum_Dicek" <?php echo ($edit_data['status_pengecekan'] == 'Belum_Dicek') ? 'selected' : ''; ?>>Belum Dicek</option>
                                <option value="Sedang_Dicek" <?php echo ($edit_data['status_pengecekan'] == 'Sedang_Dicek') ? 'selected' : ''; ?>>Sedang Dicek</option>
                                <option value="Sudah_Dicek" <?php echo ($edit_data['status_pengecekan'] == 'Sudah_Dicek') ? 'selected' : ''; ?>>Sudah Dicek</option>
                            </select>
                        </div>

                        <div>
                            <label for="nama_petugas" style="display: block; margin-bottom: 5px; font-weight: bold;">Nama Petugas *</label>
                            <input type="text" id="nama_petugas" name="nama_petugas"
                                   value="<?php echo $edit_data['nama_petugas']; ?>"
                                   required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        </div>

                        <div>
                            <label for="tanggal_pengecekan" style="display: block; margin-bottom: 5px; font-weight: bold;">Tanggal Pengecekan</label>
                            <input type="date" id="tanggal_pengecekan" name="tanggal_pengecekan"
                                   value="<?php echo date('Y-m-d'); ?>"
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        </div>
                    </div>

                    <div style="margin-top: 15px;">
                        <label for="keterangan" style="display: block; margin-bottom: 5px; font-weight: bold;">Keterangan</label>
                        <textarea id="keterangan" name="keterangan" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-height: 80px;"><?php echo $edit_data['keterangan']; ?></textarea>
                    </div>

                <?php elseif ($mode == 'update' && !$edit_data): ?>
                    <!-- Direct Update Form -->
                    <div style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <h3 style="margin-bottom: 15px; color: #007bff;">🔍 Update Kondisi Barang</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="id" id="update_item_id">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                                <div>
                                    <label for="select_ruangan" style="display: block; margin-bottom: 8px; font-weight: bold; font-size: 16px;">🏢 Pilih Ruangan *</label>
                                    <select id="select_ruangan" required style="width: 100%; padding: 12px; border: 2px solid #007bff; border-radius: 5px; font-size: 16px;">
                                        <option value="">-- Pilih Ruangan --</option>
                                        <option value="Aula">Aula</option>
                                        <option value="Dapur">Dapur</option>
                                        <option value="Klinik">Klinik</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="select_barang" style="display: block; margin-bottom: 8px; font-weight: bold; font-size: 16px;">📦 Pilih Barang *</label>
                                    <select id="select_barang" name="selected_item" required disabled style="width: 100%; padding: 12px; border: 2px solid #007bff; border-radius: 5px; font-size: 16px; background: #f8f9fa;">
                                        <option value="">-- Pilih Barang --</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="update_jumlah" style="display: block; margin-bottom: 8px; font-weight: bold; font-size: 16px;">🔢 Jumlah *</label>
                                    <input type="number" id="update_jumlah" name="jumlah" min="1" required style="width: 100%; padding: 12px; border: 2px solid #007bff; border-radius: 5px; font-size: 16px;">
                                </div>

                                <div>
                                    <label for="update_kondisi" style="display: block; margin-bottom: 8px; font-weight: bold; font-size: 16px;">🔍 Kondisi Barang *</label>
                                    <select id="update_kondisi" name="kondisi" required style="width: 100%; padding: 12px; border: 2px solid #007bff; border-radius: 5px; font-size: 16px;">
                                        <option value="Baik">✅ Baik</option>
                                        <option value="Rusak Ringan">⚠️ Rusak Ringan</option>
                                        <option value="Rusak Berat">❌ Rusak Berat</option>
                                        <option value="Hilang">🚫 Hilang</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="update_petugas" style="display: block; margin-bottom: 8px; font-weight: bold; font-size: 16px;">👤 Nama Petugas *</label>
                                    <input type="text" id="update_petugas" name="nama_petugas" required style="width: 100%; padding: 12px; border: 2px solid #007bff; border-radius: 5px; font-size: 16px;" placeholder="Masukkan nama petugas">
                                </div>

                                <div>
                                    <label for="update_tanggal" style="display: block; margin-bottom: 8px; font-weight: bold; font-size: 16px;">📅 Tanggal Pengecekan</label>
                                    <input type="date" id="update_tanggal" name="tanggal_pengecekan" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 12px; border: 2px solid #007bff; border-radius: 5px; font-size: 16px;">
                                </div>
                            </div>

                            <!-- Hidden fields -->
                            <input type="hidden" name="status_pengecekan" value="Sudah_Dicek">
                            <input type="hidden" name="keterangan" id="update_keterangan" value="">

                            <div style="margin-top: 20px; display: flex; gap: 10px;">
                                <button type="submit" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; border: none; padding: 12px 30px; border-radius: 5px; font-weight: bold; cursor: pointer;">
                                    🔍 Update Kondisi
                                </button>
                                <button type="button" id="cancel_update" style="background: #6c757d; color: white; border: none; padding: 12px 30px; border-radius: 5px; font-weight: bold; cursor: pointer;">❌ Batal</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if (($mode == 'input') || ($mode == 'update' && $edit_data)): ?>
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="submit" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 30px; border-radius: 5px; font-weight: bold; cursor: pointer;">
                        <?php echo $mode == 'input' ? '➕ Simpan Barang Baru' : '🔍 Update Kondisi'; ?>
                    </button>
                    <?php if ($edit_data): ?>
                        <a href="?mode=update" style="text-decoration: none;">
                            <button type="button" style="background: #6c757d; color: white; border: none; padding: 12px 30px; border-radius: 5px; font-weight: bold; cursor: pointer;">❌ Batal</button>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- History Log -->
        <div class="rooms-card" style="margin-top: 30px;">
            <h2>📜 History Perubahan (5 Terakhir)</h2>
            <?php
            $history_query = mysqli_query($conn, "
                SELECT h.*, b.nama_barang
                FROM barang_history h
                JOIN barang b ON h.barang_id = b.id
                ORDER BY h.change_date DESC
                LIMIT 5
            ");
            if (mysqli_num_rows($history_query) > 0):
            ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <thead style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white;">
                        <tr>
                            <th style="padding: 10px; text-align: left;">Barang</th>
                            <th style="padding: 10px; text-align: left;">Field</th>
                            <th style="padding: 10px; text-align: left;">Dari</th>
                            <th style="padding: 10px; text-align: left;">Ke</th>
                            <th style="padding: 10px; text-align: left;">Oleh</th>
                            <th style="padding: 10px; text-align: left;">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($history = mysqli_fetch_assoc($history_query)): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 10px;"><?php echo htmlspecialchars($history['nama_barang']); ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($history['field_name']); ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($history['old_value'] ?: '-'); ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($history['new_value'] ?: '-'); ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($history['changed_by']); ?></td>
                                <td style="padding: 10px;"><?php echo date('d/m/Y H:i', strtotime($history['change_date'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 20px;">Belum ada history perubahan.</p>
            <?php endif; ?>
        </div>
        
        
        
        <div class="rooms-card">
            <h2>🏢 Daftar Ruangan</h2>
            <div class="rooms-grid">
                <?php foreach ($room_stats as $room_name => $stats): ?>
                    <a href="ruangan.php?nama=<?php echo urlencode($room_name); ?>" class="room-card">
                        <div class="room-name">🚪 <?php echo htmlspecialchars($room_name); ?></div>
                        <div class="room-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['total']; ?></div>
                                <div class="stat-label">Total Barang</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['baik']; ?></div>
                                <div class="stat-label">Kondisi Baik</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['rusak_ringan']; ?></div>
                                <div class="stat-label">Rusak Ringan</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['rusak_berat'] + $stats['hilang']; ?></div>
                                <div class="stat-label">Rusak Berat/Hilang</div>
                            </div>
                        </div>
                        <div style="margin-top: 15px; font-size: 0.9em; opacity: 0.9;">
                            <div style="font-weight: bold; margin-bottom: 5px;">Barang:</div>
                            <?php
                            $items = $room_items[$room_name];
                            if (empty($items)) {
                                echo '<div style="font-style: italic;">Tidak ada barang</div>';
                            } else {
                                foreach ($items as $item) {
                                    $kondisi_class = '';
                                    if ($item['kondisi'] == 'Rusak Ringan') $kondisi_class = 'style="color: #ffd700;"';
                                    if ($item['kondisi'] == 'Rusak Berat') $kondisi_class = 'style="color: #ff6b6b;"';
                                    if ($item['kondisi'] == 'Hilang') $kondisi_class = 'style="color: #dc3545;"';
                                    echo '<div>• ' . htmlspecialchars($item['nama_barang']) . ' <span ' . $kondisi_class . '>(' . $item['kondisi'] . ')</span></div>';
                                }
                                if (count($items) == 3) {
                                    echo '<div style="font-style: italic;">+ lebih banyak...</div>';
                                }
                            }
                            ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="qr-section">
            <h2>📱 QR Code untuk Setiap Ruangan</h2>
            <p style="text-align: center; color: #666; margin-bottom: 20px;">
                Scan QR Code ini menggunakan kamera HP untuk langsung membuka halaman inventaris ruangan
            </p>
            <div class="qr-grid">
                <?php
                $fixed_rooms = ['Aula', 'Dapur', 'Klinik'];
                foreach ($fixed_rooms as $room_name):
                    $room_url = 'http://localhost/inventaris_app/ruangan.php?nama=' . urlencode($room_name);
                    $qr_api = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($room_url);
                ?>
                    <div class="qr-item">
                        <h3>🚪 <?php echo htmlspecialchars($room_name); ?></h3>
                        <img src="<?php echo $qr_api; ?>" alt="QR Code <?php echo htmlspecialchars($room_name); ?>">
                        <div class="qr-link"><?php echo htmlspecialchars($room_url); ?></div>
                        <a href="ruangan.php?nama=<?php echo urlencode($room_name); ?>" class="view-btn">
                            👁️ Lihat Inventaris
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Filter dan Search -->
        <div class="info-card">
            <h2>🔍 Filter & Cari Barang</h2>
            <form method="GET" action="" style="margin-top: 15px;">
                <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                    <div>
                        <label for="search" style="display: block; margin-bottom: 5px; font-weight: bold;">Cari Nama Barang</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                    <div>
                        <label for="ruangan_filter" style="display: block; margin-bottom: 5px; font-weight: bold;">Filter Ruangan</label>
                        <select id="ruangan_filter" name="ruangan" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            <option value="">Semua Ruangan</option>
                            <option value="Aula" <?php echo $filter_ruangan == 'Aula' ? 'selected' : ''; ?>>Aula</option>
                            <option value="Dapur" <?php echo $filter_ruangan == 'Dapur' ? 'selected' : ''; ?>>Dapur</option>
                            <option value="Klinik" <?php echo $filter_ruangan == 'Klinik' ? 'selected' : ''; ?>>Klinik</option>
                        </select>
                    </div>
                    <div>
                        <label for="kondisi_filter" style="display: block; margin-bottom: 5px; font-weight: bold;">Filter Kondisi</label>
                        <select id="kondisi_filter" name="kondisi" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            <option value="">Semua Kondisi</option>
                            <option value="Baik" <?php echo $filter_kondisi == 'Baik' ? 'selected' : ''; ?>>Baik</option>
                            <option value="Rusak Ringan" <?php echo $filter_kondisi == 'Rusak Ringan' ? 'selected' : ''; ?>>Rusak Ringan</option>
                            <option value="Rusak Berat" <?php echo $filter_kondisi == 'Rusak Berat' ? 'selected' : ''; ?>>Rusak Berat</option>
                            <option value="Hilang" <?php echo $filter_kondisi == 'Hilang' ? 'selected' : ''; ?>>Hilang</option>
                        </select>
                    </div>
                    <div>
                        <label for="status_filter" style="display: block; margin-bottom: 5px; font-weight: bold;">Filter Status</label>
                        <select id="status_filter" name="status" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            <option value="">Semua Status</option>
                            <option value="Belum_Dicek" <?php echo $filter_status == 'Belum_Dicek' ? 'selected' : ''; ?>>Belum Dicek</option>
                            <option value="Sedang_Dicek" <?php echo $filter_status == 'Sedang_Dicek' ? 'selected' : ''; ?>>Sedang Dicek</option>
                            <option value="Sudah_Dicek" <?php echo $filter_status == 'Sudah_Dicek' ? 'selected' : ''; ?>>Sudah Dicek</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 10px 20px; border-radius: 5px; font-weight: bold; cursor: pointer; width: 100%;">🔍 Cari & Filter</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Daftar Barang dengan Edit/Delete -->
        <div class="rooms-card">
            <h2>📋 Daftar Semua Barang</h2>
            <?php
            // Build WHERE clause for filters
            $where_clauses = [];
            if ($search) $where_clauses[] = "nama_barang LIKE '%$search%'";
            if ($filter_ruangan) $where_clauses[] = "ruangan = '$filter_ruangan'";
            if ($filter_kondisi) $where_clauses[] = "kondisi = '$filter_kondisi'";
            if ($filter_status) $where_clauses[] = "status_pengecekan = '$filter_status'";

            $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
            $all_items = mysqli_query($conn, "SELECT * FROM barang $where_sql ORDER BY ruangan ASC, nama_barang ASC");
            if (mysqli_num_rows($all_items) > 0):
            ?>
            <form method="POST" action="">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <tr>
                            <th style="padding: 12px; text-align: center;"><input type="checkbox" id="select_all"></th>
                            <th style="padding: 12px; text-align: left;">Nama Barang</th>
                            <th style="padding: 12px; text-align: left;">Ruangan</th>
                            <th style="padding: 12px; text-align: center;">Jumlah</th>
                            <th style="padding: 12px; text-align: left;">Kondisi</th>
                            <th style="padding: 12px; text-align: left;">Status</th>
                            <th style="padding: 12px; text-align: left;">Petugas</th>
                            <th style="padding: 12px; text-align: left;">Tanggal Cek</th>
                            <th style="padding: 12px; text-align: left;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($all_items)): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px; text-align: center;"><input type="checkbox" name="selected_items[]" value="<?php echo $row['id']; ?>" class="item_checkbox"></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($row['ruangan']); ?></td>
                                <td style="padding: 12px; text-align: center;"><?php echo htmlspecialchars($row['jumlah']); ?></td>
                                <td style="padding: 12px;">
                                    <?php
                                    $badge_style = 'background: #d4edda; color: #155724;';
                                    if ($row['kondisi'] == 'Rusak Ringan') $badge_style = 'background: #fff3cd; color: #856404;';
                                    if ($row['kondisi'] == 'Rusak Berat') $badge_style = 'background: #f8d7da; color: #721c24;';
                                    if ($row['kondisi'] == 'Hilang') $badge_style = 'background: #d6d8db; color: #383d41;';
                                    ?>
                                    <span style="padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; <?php echo $badge_style; ?>">
                                        <?php echo htmlspecialchars($row['kondisi']); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px;">
                                    <?php
                                    $status_style = 'background: #6c757d; color: white;';
                                    $status_text = str_replace('_', ' ', $row['status_pengecekan']);
                                    if ($row['status_pengecekan'] == 'Sedang_Dicek') $status_style = 'background: #ffc107; color: #000;';
                                    if ($row['status_pengecekan'] == 'Sudah_Dicek') $status_style = 'background: #28a745; color: white;';
                                    ?>
                                    <span style="padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; <?php echo $status_style; ?>">
                                        <?php echo htmlspecialchars($status_text); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($row['nama_petugas']); ?></td>
                                <td style="padding: 12px;"><?php echo date('d/m/Y', strtotime($row['tanggal_pengecekan'])); ?></td>
                                <td style="padding: 12px;">
                                    <a href="?mode=update&edit=<?php echo $row['id']; ?>" style="background: #ffc107; color: #000; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 12px; margin-right: 5px;">✏️ Edit</a>
                                    <a href="?mode=<?php echo $mode; ?>&delete=<?php echo $row['id']; ?>"
                                       style="background: #dc3545; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 12px;"
                                       onclick="return confirm('Yakin ingin menghapus barang ini?')">🗑️ Hapus</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bulk Actions -->
            <div style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3 style="margin-bottom: 15px; color: #333;">⚡ Bulk Actions</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                    <div>
                        <label for="bulk_kondisi" style="display: block; margin-bottom: 5px; font-weight: bold;">Update Kondisi</label>
                        <select id="bulk_kondisi" name="bulk_kondisi" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            <option value="">Pilih Kondisi</option>
                            <option value="Baik">Baik</option>
                            <option value="Rusak Ringan">Rusak Ringan</option>
                            <option value="Rusak Berat">Rusak Berat</option>
                            <option value="Hilang">Hilang</option>
                        </select>
                    </div>
                    <div>
                        <label for="bulk_status" style="display: block; margin-bottom: 5px; font-weight: bold;">Update Status Pengecekan</label>
                        <select id="bulk_status" name="bulk_status" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            <option value="">Pilih Status</option>
                            <option value="Belum_Dicek">Belum Dicek</option>
                            <option value="Sedang_Dicek">Sedang Dicek</option>
                            <option value="Sudah_Dicek">Sudah Dicek</option>
                        </select>
                    </div>
                    <div>
                        <label for="bulk_petugas" style="display: block; margin-bottom: 5px; font-weight: bold;">Nama Petugas</label>
                        <input type="text" id="bulk_petugas" name="bulk_petugas" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                    <div>
                        <button type="submit" name="bulk_action" value="update" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; padding: 10px 20px; border-radius: 5px; font-weight: bold; cursor: pointer; width: 100%;">🔄 Update Terpilih</button>
                    </div>
                </div>
            </div>
            </form>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 20px;">Belum ada data barang.</p>
            <?php endif; ?>
        </div>

        <div class="admin-link">
            <a href="admin.php">⚙️ Halaman Admin Lengkap</a>
        </div>
    </div>

    <script>
        // Select all checkbox functionality
        document.getElementById('select_all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.item_checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        // Individual checkbox affects select all
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('item_checkbox')) {
                const allCheckboxes = document.querySelectorAll('.item_checkbox');
                const checkedBoxes = document.querySelectorAll('.item_checkbox:checked');
                const selectAll = document.getElementById('select_all');

                selectAll.checked = allCheckboxes.length === checkedBoxes.length;
                selectAll.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < allCheckboxes.length;
            }
        });

        // Update mode: Load items based on selected room
        document.getElementById('select_ruangan').addEventListener('change', function() {
            const ruangan = this.value;
            const barangSelect = document.getElementById('select_barang');

            if (ruangan) {
                // Fetch items for this room via AJAX
                fetch('get_items.php?ruangan=' + encodeURIComponent(ruangan))
                    .then(response => response.json())
                    .then(data => {
                        barangSelect.innerHTML = '<option value="">-- Pilih Barang --</option>';
                        data.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.id;
                            option.textContent = item.nama_barang + ' (Kondisi: ' + item.kondisi + ')';
                            option.dataset.item = JSON.stringify(item);
                            barangSelect.appendChild(option);
                        });
                        barangSelect.disabled = false;
                    })
                    .catch(error => {
                        console.error('Error loading items:', error);
                        barangSelect.innerHTML = '<option value="">Error loading items</option>';
                    });
            } else {
                barangSelect.innerHTML = '<option value="">-- Pilih Barang --</option>';
                barangSelect.disabled = true;
                // Reset kondisi and petugas
                document.getElementById('update_kondisi').value = 'Baik';
                document.getElementById('update_petugas').value = '';
                document.getElementById('update_item_id').value = '';
            }
        });

        // Update mode: Populate form when item is selected
        document.getElementById('select_barang').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];

            if (this.value && selectedOption.dataset.item) {
                const item = JSON.parse(selectedOption.dataset.item);

                // Populate form fields
                document.getElementById('update_item_id').value = item.id;
                document.getElementById('update_jumlah').value = item.jumlah || 1;
                document.getElementById('update_kondisi').value = item.kondisi;
                document.getElementById('update_petugas').value = item.nama_petugas || '';
                document.getElementById('update_tanggal').value = '<?php echo date('Y-m-d'); ?>';
                document.getElementById('update_keterangan').value = item.keterangan || '';
            } else {
                // Reset
                document.getElementById('update_jumlah').value = 1;
                document.getElementById('update_kondisi').value = 'Baik';
                document.getElementById('update_petugas').value = '';
                document.getElementById('update_item_id').value = '';
            }
        });

        // Cancel update
        document.getElementById('cancel_update').addEventListener('click', function() {
            document.getElementById('select_ruangan').value = '';
            document.getElementById('select_barang').innerHTML = '<option value="">-- Pilih Barang --</option>';
            document.getElementById('select_barang').disabled = true;
            document.getElementById('update_jumlah').value = 1;
            document.getElementById('update_kondisi').value = 'Baik';
            document.getElementById('update_petugas').value = '';
            document.getElementById('update_item_id').value = '';
        });
    </script>
</body>
</html>