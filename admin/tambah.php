<?php
// Memulai session untuk autentikasi user
session_start();

// Cek apakah user sudah login dan role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    // Jika tidak, redirect ke halaman login
    header('Location: ../index.php');
    exit; // Hentikan eksekusi
}

// Menghubungkan ke database
include '../koneksi.php';

// Ambil semua koki (user dengan role user)
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
    
    // Format title dengan menu jika ada dan bukan manual
    if ($selected_menu && $selected_menu != 'manual') {
        $title = $selected_menu . ' x' . $menu_quantity;
        if ($special_request) {
            $title .= ' (' . $special_request . ')';
        }
    }

    // Status default saat membuat pesanan
    $status = 'todo';

    // Jika deadline kosong, set NULL
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : NULL;

    // Default gambar kosong
    $image = NULL;
    
    // ============================
    // HANDLE UPLOAD GAMBAR
    // ============================

    // Cek apakah ada file yang diupload dan tidak error
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {

        // Format file yang diperbolehkan
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        // Ambil nama file
        $filename = $_FILES['image']['name'];

        // Ambil ekstensi file
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Validasi ekstensi file
        if (in_array($ext, $allowed)) {

            // Buat nama file unik
            $newname = uniqid() . '.' . $ext;

            // Tentukan path penyimpanan
            $upload_path = '../img/' . $newname;
            
            // Jika folder belum ada, buat folder img
            if (!file_exists('../img')) {
                mkdir('../img', 0777, true);
            }
            
            // Pindahkan file dari temporary ke folder tujuan
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                // Simpan nama file ke variabel
                $image = $newname;
            }
        }
    }
    
    // ============================
    // INSERT DATA KE DATABASE
    // ============================

    // Gunakan prepared statement untuk keamanan
    $stmt = $conn->prepare("INSERT INTO task (user_id, title, status, deadline, image) VALUES (?, ?, ?, ?, ?)");

    // Bind parameter ke query
    $stmt->bind_param("issss", $user_id, $title, $status, $deadline, $image);
    
    // Eksekusi query
    if ($stmt->execute()) {

        // Jika berhasil, redirect ke dashboard dengan status sukses
        header('Location: dashboard.php?success=add');

    } else {
        // Jika gagal, tampilkan error
        $error = "Gagal menambahkan pesanan!";
    }

    // Hentikan eksekusi setelah proses POST
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"> <!-- Encoding -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Responsive -->
    <title>Tambah Pesanan - Restoran</title>

    <!-- Import CSS -->
    <link rel="stylesheet" href="../assets/common.css">
    <link rel="stylesheet" href="../assets/form.css">
    
   
</head>
<body>

    <!-- Navbar Restoran -->
    <div class="navbar">
        <h1>Tambah Pesanan Restoran</h1>
    </div>

    <div class="form-container">

        <!-- Menampilkan error jika ada -->
        <?php if(isset($error)): ?>
            <div class="alert-error"><?= $error ?></div>
        <?php endif; ?>

        <!-- Form tambah pesanan -->
        <form method="POST" enctype="multipart/form-data">

            <!-- Pilih koki -->
            <div class="form-group">
                <label>👨‍🍳 Pilih Koki <span class="required">*</span></label>
                <select name="user_id" required>
                    <option value="">-- Pilih Koki --</option>
                    <?php while($user = $users->fetch_assoc()): ?>
                        <option value="<?= $user['id'] ?>">
                            🧑‍🍳 <?= htmlspecialchars($user['username']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small>Pilih koki yang akan memasak pesanan ini</small>
            </div>
            
            <!-- ============================ -->
            <!-- PILIHAN MENU RESTAURANT -->
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
                <input type="text" name="title" id="manualTitle" placeholder="Contoh: Nasi Goreng Spesial">
            </div>
            
            <!-- Input deadline -->
            <div class="form-group">
                <label>⏰ Deadline Pesanan</label>
                <input type="date" name="deadline" min="<?= date('Y-m-d') ?>">
                <small>Opsional - Kapan pesanan harus selesai?</small>
            </div>
            
            <!-- Upload gambar menu -->
            <div class="form-group">
                <label>🖼️ Upload Gambar Menu</label>
                <input type="file" name="image" accept="image/*">
                <small>Format: JPG, JPEG, PNG, GIF (Opsional)</small>
            </div>
            
            <hr>
            
            <!-- Tombol aksi -->
            <div class="form-actions">
                <button type="submit" class="btn-primary">Tambah Pesanan</button>
                <a href="dashboard.php" class="btn-secondary">Kembali</a>
            </div>
        </form>
    </div>
    
    <script>
        // Script untuk toggle input manual
        const menuSelect = document.getElementById('menuSelect');
        const manualTitleDiv = document.getElementById('manualTitleDiv');
        const manualTitleInput = document.getElementById('manualTitle');
        
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