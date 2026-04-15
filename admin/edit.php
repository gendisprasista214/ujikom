<?php
// Memulai session untuk autentikasi user
session_start();

// Mengecek apakah user sudah login dan role-nya admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    // Jika tidak, redirect ke halaman login
    header('Location: ../index.php');
    exit; // Hentikan script
}

// Menghubungkan ke database
include '../koneksi.php';

// Mengambil ID task dari URL (default 0 jika tidak ada)
$id = $_GET['id'] ?? 0;

// Ambil data task berdasarkan ID
$result = $conn->query("SELECT * FROM task WHERE id = $id");

// Jika task tidak ditemukan
if ($result->num_rows == 0) {
    // Redirect ke dashboard
    header('Location: dashboard.php');
    exit;
}

// Ambil data task dalam bentuk array
$task = $result->fetch_assoc();

// Ambil semua user dengan role 'user' (koki)
$users = $conn->query("SELECT id, username FROM user WHERE role = 'user' ORDER BY username ASC");

// ============================
// DAFTAR MENU RESTAURANT
// ============================
$menu_items = [
    'makanan' => [
        'Nasi Goreng Spesial' => 35000,
        'Mie Goreng Jawa' => 28000,
        'Ayam Bakar Madu' => 42000,
        'Sate Ayam Lontong' => 38000,
        'Ikan Bakar Jimbaran' => 45000,
        'Gado-Gado Complete' => 30000,
        'Soto Ayam Lamongan' => 25000,
        'Rawon Daging Sapi' => 35000,
        'Rendang Padang' => 48000,
        'Pecel Lele Sambal' => 22000
    ],
    'minuman' => [
        'Es Teh Manis' => 5000,
        'Es Jeruk Peras' => 8000,
        'Jus Alpukat' => 15000,
        'Jus Mangga' => 15000,
        'Es Kelapa Muda' => 12000,
        'Es Campur' => 18000,
        'Teh Tarik' => 10000,
        'Kopi Hitam' => 8000,
        'Kopi Susu' => 12000,
        'Milkshake Coklat' => 18000
    ],
    'snack' => [
        'Pisang Goreng Crispy' => 15000,
        'Tahu Isi (5 pcs)' => 10000,
        'Tempe Mendoan' => 10000,
        'Cireng Pedas' => 12000,
        'Lumpia Semarang' => 15000,
        'Bakso Goreng' => 12000,
        'Kentang Goreng' => 13000,
        'Onion Ring' => 14000
    ]
];

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Ambil data dari form
    $user_id = $_POST['user_id'];
    $title = $_POST['title'];
    
    // Ambil data menu yang dipilih
    $selected_menu = $_POST['selected_menu'] ?? '';
    $menu_quantity = $_POST['menu_quantity'] ?? 1;
    $special_request = $_POST['special_request'] ?? '';
    
    // Format title dengan menu jika ada
    if ($selected_menu && $selected_menu != 'manual') {
        $title = $selected_menu . ' x' . $menu_quantity;
        if ($special_request) {
            $title .= ' (' . $special_request . ')';
        }
    }

    // Jika deadline kosong, set NULL
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : NULL;

    // Gunakan gambar lama sebagai default
    $image = $task['image'];
    
    // ============================
    // HANDLE UPLOAD GAMBAR BARU
    // ============================

    // Cek apakah ada file yang diupload dan tidak error
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {

        // Ekstensi file yang diizinkan
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        // Ambil nama file
        $filename = $_FILES['image']['name'];

        // Ambil ekstensi file
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Cek apakah ekstensi valid
        if (in_array($ext, $allowed)) {

            // Hapus gambar lama jika ada di folder
            if ($task['image'] && file_exists('../img/' . $task['image'])) {
                unlink('../img/' . $task['image']);
            }
            
            // Generate nama file baru agar unik
            $newname = uniqid() . '.' . $ext;

            // Tentukan lokasi upload
            $upload_path = '../img/' . $newname;
            
            // Pindahkan file dari temporary ke folder tujuan
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                // Simpan nama file baru ke variabel
                $image = $newname;
            }
        }
    }
    
    // ============================
    // UPDATE DATA KE DATABASE
    // ============================

    // Prepare statement untuk keamanan (hindari SQL Injection)
    $stmt = $conn->prepare("UPDATE task SET user_id = ?, title = ?, deadline = ?, image = ? WHERE id = ?");

    // Bind parameter ke query
    $stmt->bind_param("isssi", $user_id, $title, $deadline, $image, $id);
    
    // Eksekusi query
    if ($stmt->execute()) {

        // Jika berhasil, redirect ke dashboard dengan status sukses
        header('Location: dashboard.php?success=update');

    } else {
        // Jika gagal, tampilkan pesan error
        $error = "Gagal mengupdate task!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pesanan - Restoran</title>

    <!-- Import CSS -->
    <link rel="stylesheet" href="../assets/common.css">
    <link rel="stylesheet" href="../assets/form.css">
    
  
</head>
<body>

    <!-- Navbar -->
    <div class="navbar">
        <h1>🍽️ Edit Pesanan Restoran</h1>
    </div>

    <div class="form-container">

        <!-- Menampilkan error jika ada -->
        <?php if(isset($error)): ?>
            <div class="alert-error"><?= $error ?></div>
        <?php endif; ?>

        <!-- Form edit task -->
        <form method="POST" enctype="multipart/form-data">

            <!-- Pilih koki (dropdown menurun) -->
            <div class="form-group">
                <label>👨‍🍳 Pilih Koki <span class="required">*</span></label>
                <select name="user_id" required>
                    <?php 
                    $users->data_seek(0);
                    while($user = $users->fetch_assoc()): 
                    ?>
                        <option value="<?= $user['id'] ?>" <?= $user['id'] == $task['user_id'] ? 'selected' : '' ?>>
                            🧑‍🍳 <?= htmlspecialchars($user['username']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <!-- ============================ -->
            <!-- PILIHAN MENU - DROPDOWN MENURUN KE BAWAH -->
            <!-- ============================ -->
            <div class="form-group">
                <label>🍕 Pilih Menu Pesanan</label>
                
                <!-- Select dropdown untuk pilih menu -->
                <select name="selected_menu" id="menuSelect">
                    <option value="">-- Pilih Menu --</option>
                    <optgroup label="🍚 Makanan Utama">
                        <?php foreach($menu_items['makanan'] as $menu => $price): ?>
                            <option value="<?= htmlspecialchars($menu) ?>">
                                <?= htmlspecialchars($menu) ?> - Rp <?= number_format($price, 0, ',', '.') ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="🥤 Minuman">
                        <?php foreach($menu_items['minuman'] as $menu => $price): ?>
                            <option value="<?= htmlspecialchars($menu) ?>">
                                <?= htmlspecialchars($menu) ?> - Rp <?= number_format($price, 0, ',', '.') ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="🍢 Snack & Cemilan">
                        <?php foreach($menu_items['snack'] as $menu => $price): ?>
                            <option value="<?= htmlspecialchars($menu) ?>">
                                <?= htmlspecialchars($menu) ?> - Rp <?= number_format($price, 0, ',', '.') ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <option value="manual">✏️ Tulis menu sendiri (manual)</option>
                </select>
                <small>Pilih menu dari daftar atau pilih "Tulis menu sendiri"</small>
            </div>
            
            <!-- Quantity pesanan -->
            <div class="quantity-group">
                <label>📊 Jumlah Porsi:</label>
                <input type="number" name="menu_quantity" id="menuQuantity" value="1" min="1" max="99">
            </div>
            
            <!-- Catatan khusus -->
            <div class="special-request">
                <label>📝 Catatan Khusus (opsional):</label>
                <textarea name="special_request" id="specialRequest" rows="2" placeholder="Contoh: Tidak pedas, tambah sambal, ekstra nasi, dll..."></textarea>
            </div>
            
            <!-- Input judul manual (tampil jika pilih manual) -->
            <div class="manual-title" id="manualTitleDiv" style="display: none;">
                <label>📋 Nama Menu / Pesanan <span class="required">*</span></label>
                <input type="text" name="title" id="manualTitle" value="<?= htmlspecialchars($task['title']) ?>" placeholder="Contoh: Nasi Goreng Spesial">
            </div>
            
            <!-- Input deadline -->
            <div class="form-group">
                <label>⏰ Deadline Pesanan</label>
                <input type="date" name="deadline" value="<?= $task['deadline'] ?>">
                <small>Kapan pesanan harus selesai?</small>
            </div>
            
            <!-- Menampilkan gambar lama jika ada -->
            <?php if($task['image']): ?>
                <div class="form-group">
                    <label>📷 Gambar Menu Saat Ini:</label>
                    <div class="file-preview">
                        <img src="../img/<?= $task['image'] ?>" alt="Current">
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Upload gambar baru -->
            <div class="form-group">
                <label>🖼️ Upload Gambar Menu Baru</label>
                <input type="file" name="image" accept="image/*">
                <small>Biarkan kosong jika tidak ingin mengubah gambar</small>
            </div>
            
            <hr>
            
            <!-- Tombol aksi -->
            <div class="form-actions">
                <button type="submit" class="btn-primary">✅ Update Pesanan</button>
                <a href="dashboard.php" class="btn-secondary">❌ Batal</a>
            </div>
        </form>
    </div>
    
    <script>
        // Script untuk toggle input manual
        const menuSelect = document.getElementById('menuSelect');
        const manualTitleDiv = document.getElementById('manualTitleDiv');
        const manualTitleInput = document.getElementById('manualTitle');
        const menuQuantity = document.getElementById('menuQuantity');
        const specialRequest = document.getElementById('specialRequest');
        
        // Fungsi untuk toggle manual title
        function toggleManualTitle() {
            if (menuSelect.value === 'manual') {
                manualTitleDiv.style.display = 'block';
                manualTitleInput.required = true;
                manualTitleInput.disabled = false;
            } else {
                manualTitleDiv.style.display = 'none';
                manualTitleInput.required = false;
                manualTitleInput.disabled = true;
                manualTitleInput.value = '';
            }
        }
        
        // Event listener untuk perubahan select
        menuSelect.addEventListener('change', toggleManualTitle);
        
        // Trigger awal
        toggleManualTitle();
    </script>
</body>
</html>