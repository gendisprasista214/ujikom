<?php
// ==================== KONFIGURASI AWAL & AUTENTIKASI ====================
// Memulai session untuk menyimpan data login user
session_start();

// Mengecek apakah user sudah login dan memiliki role admin
// Jika tidak, redirect ke halaman login/index
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}

// Menyertakan file koneksi database
include '../koneksi.php';

// ==================== FUNGSI BANTUAN ====================
// Fungsi untuk mem-parsing judul task dengan format: "Nama Menu xJumlah (Special Request)"
// Contoh: "Nasi Goreng x2 (pedas)" -> ['menu_name'=>'Nasi Goreng', 'quantity'=>2, 'special_request'=>'pedas']
function parseTaskTitle($title) {
    $result = ['menu_name' => $title, 'quantity' => 1, 'special_request' => ''];
    // Regex pattern: menangkap nama menu, angka setelah x, dan teks dalam kurung
    if (preg_match('/^(.*?)\s*x(\d+)(?:\s*\((.*)\))?$/i', $title, $matches)) {
        $result['menu_name'] = trim($matches[1]);      // Nama menu
        $result['quantity'] = (int)$matches[2];       // Jumlah porsi
        $result['special_request'] = $matches[3] ?? ''; // Catatan khusus
    }
    return $result;
}

// ==================== AMBIL DATA DARI DATABASE ====================
// Ambil task dengan status 'todo' (antrian baru), join dengan user untuk dapat username
// Urutkan berdasarkan deadline terdekat (ASC)
$todo_tasks = $conn->query("SELECT task.*, user.username FROM task JOIN user ON task.user_id = user.id WHERE task.status = 'todo' ORDER BY task.deadline ASC");

// Ambil task dengan status 'progress' (sedang dimasak)
$progress_tasks = $conn->query("SELECT task.*, user.username FROM task JOIN user ON task.user_id = user.id WHERE task.status = 'progress' ORDER BY task.deadline ASC");

// Ambil task dengan status 'done' (selesai)
$done_tasks = $conn->query("SELECT task.*, user.username FROM task JOIN user ON task.user_id = user.id WHERE task.status = 'done' ORDER BY task.deadline ASC");

// ==================== FUNGSI HITUNG SISA WAKTU ====================
// Fungsi untuk menampilkan sisa waktu dari deadline
// Output: "⏰ 2 hari lagi", "⏰ 3 jam lagi", "⚠️ Melewati deadline", dll
function getTimeRemaining($deadline) {
    if (!$deadline) return null;  // Jika tidak ada deadline
    $now = new DateTime();         // Waktu sekarang
    $dead = new DateTime($deadline); // Waktu deadline
    if ($now > $dead) return '⚠️ Melewati deadline'; // Sudah lewat
    $diff = $now->diff($dead);     // Selisih waktu
    if ($diff->days > 0) return "⏰ {$diff->days} hari lagi";
    if ($diff->h > 0) return "⏰ {$diff->h} jam lagi";
    return "⏰ {$diff->i} menit lagi";
}

// ==================== HITUNG STATISTIK ====================
// Menghitung total task untuk setiap status (digunakan di kartu statistik)
$total_todo = $conn->query("SELECT COUNT(*) as count FROM task WHERE status = 'todo'")->fetch_assoc()['count'];
$total_progress = $conn->query("SELECT COUNT(*) as count FROM task WHERE status = 'progress'")->fetch_assoc()['count'];
$total_done = $conn->query("SELECT COUNT(*) as count FROM task WHERE status = 'done'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Dashboard Restoran - Manajemen Dapur</title>
    <style>
        /* ==================== RESET & VARIABEL CSS ==================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Variabel warna untuk tema restoran yang hangat dan lembut */
        :root {
            --soft-red: #e8a0a0;
            --soft-orange: #f4c2a2;
            --soft-green: #b5d3b5;
            --soft-blue: #b5c9e2;
            --soft-cream: #fdf8f0;
            --soft-brown: #c4a484;
            --soft-gray: #e8e8e8;
            --dark-brown: #5a3e3e;
            --shadow-sm: 0 2px 6px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --radius-md: 12px;
            --radius-sm: 8px;
            --transition: all 0.2s ease;
        }

        /* ==================== STYLE BODY & BACKGROUND ==================== */
        body {
            background: linear-gradient(135deg, #fdf8f0 0%, #f5ede0 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.5;
        }

        /* ==================== NAVBAR / HEADER ==================== */
        .navbar {
            background: linear-gradient(135deg, #d4a5a5 0%, #c49a9a 100%);
            color: var(--dark-brown);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1.5rem;
            flex-wrap: wrap;
            gap: 0.75rem;
            box-shadow: var(--shadow-sm);
            position: sticky;   /* Navbar tetap di atas saat scroll */
            top: 0;
            z-index: 100;
        }

        .navbar h1 {
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .navbar h1::before { content: "🍽️ "; }  /* Icon sebelum judul */
        .navbar h1::after { content: " 🍳"; }    /* Icon setelah judul */

        .navbar span {
            background: rgba(255,255,255,0.3);
            padding: 0.3rem 0.9rem;
            border-radius: 2rem;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .navbar > div {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }

        /* ==================== STYLE BUTTON ==================== */
        .btn-primary, .btn-secondary {
            padding: 0.4rem 1rem;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            cursor: pointer;
        }
        .btn-primary {
            background: var(--soft-orange);
            color: var(--dark-brown);
            border: none;
        }
        .btn-primary:hover {
            background: #e8b88a;
            transform: translateY(-2px);
        }
        .btn-primary::before { content: "➕ "; }  /* Icon plus sebelum teks */
        .btn-secondary {
            background: var(--soft-gray);
            color: var(--dark-brown);
            border: 1px solid var(--soft-brown);
        }
        .btn-secondary:hover {
            background: #dcdcdc;
        }

        /* ==================== ALERT NOTIFIKASI ==================== */
        .alert-success {
            background: #e6f4e6;
            color: #2e7d32;
            border-left: 4px solid var(--soft-green);
            border-radius: var(--radius-sm);
            padding: 0.7rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }

        /* ==================== WRAPPER UTAMA ==================== */
        .dashboard-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 1.5rem;
        }

        /* ==================== KARTU STATISTIK (3 KOLOM) ==================== */
        .stats-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.8rem;
            flex-wrap: wrap;
        }
        .stat-card {
            flex: 1;
            min-width: 150px;
            background: white;
            border-radius: var(--radius-md);
            padding: 0.8rem 1rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid #f0e4d4;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: var(--transition);
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        .stat-icon { font-size: 2rem; }
        .stat-info { flex: 1; }
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .stat-label {
            font-size: 0.75rem;
            color: #8b6b4d;
            font-weight: 500;
            margin-top: 0.2rem;
        }
        /* Warna khusus untuk setiap status */
        .stat-card.todo .stat-number { color: #e65100; }
        .stat-card.progress .stat-number { color: #1565c0; }
        .stat-card.done .stat-number { color: #2e7d32; }

        /* ==================== KANBAN CONTAINER (3 KOLOM) ==================== */
        .kanban-container {
            display: flex;
            gap: 1.2rem;
            align-items: flex-start;
            flex-wrap: wrap;
        }
        .kanban-column {
            flex: 1;
            min-width: 280px;
            background: rgba(255, 252, 245, 0.96);
            border-radius: var(--radius-md);
            border: 1px solid #f0e4d4;
            overflow: hidden;
            backdrop-filter: blur(2px);
        }

        /* ==================== HEADER KOLOM KANBAN ==================== */
        .column-header {
            padding: 0.7rem 1rem;
            font-weight: 600;
            font-size: 1rem;
        }
        .todo-header {
            background: var(--soft-orange);
            color: var(--dark-brown);
        }
        .todo-header::before { content: "📋 "; }
        .progress-header {
            background: var(--soft-blue);
            color: #3e5a5a;
        }
        .progress-header::before { content: "👨‍🍳 "; }
        .done-header {
            background: var(--soft-green);
            color: #3e5a3e;
        }
        .done-header::before { content: "✅ "; }

        /* ==================== DAFTAR TASK DALAM KOLOM ==================== */
        .task-list {
            padding: 0.8rem;
            max-height: 65vh;
            overflow-y: auto;  /* Scroll jika task terlalu banyak */
        }
        
        /* Custom scrollbar */
        .task-list::-webkit-scrollbar {
            width: 5px;
        }
        .task-list::-webkit-scrollbar-track {
            background: #f0e4d4;
            border-radius: 10px;
        }
        .task-list::-webkit-scrollbar-thumb {
            background: var(--soft-brown);
            border-radius: 10px;
        }

        /* ==================== KARTU TASK ==================== */
        .task-card {
            background: white;
            border-radius: var(--radius-sm);
            padding: 0.8rem;
            margin-bottom: 0.8rem;
            border: 1px solid #f0e4d4;
            transition: var(--transition);
        }
        .task-card:hover {
            box-shadow: var(--shadow-sm);
            transform: translateY(-2px);
        }
        .task-card h4 {
            color: #6b4c3b;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.6rem;
            border-bottom: 2px solid var(--soft-orange);
            display: inline-block;
            padding-bottom: 2px;
        }
        
        /* Detail task (baris informasi) */
        .task-detail {
            margin: 0.3rem 0;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .task-detail strong {
            min-width: 65px;
            color: #8b6b4d;
            font-size: 0.7rem;
        }
        
        /* Badge jumlah porsi */
        .quantity-badge {
            background: var(--soft-orange);
            padding: 0.15rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--dark-brown);
        }
        
        /* Sisa waktu deadline */
        .time-remaining {
            font-size: 0.65rem;
            padding: 0.15rem 0.5rem;
            border-radius: 20px;
        }
        .time-urgent { background: #ffebee; color: #c62828; }  /* Sudah melewati deadline */
        .time-safe { background: #e8f5e9; color: #2e7d32; }    /* Masih aman */
        
        /* Catatan khusus / special request */
        .special-request-badge {
            background: #fff3e0;
            padding: 0.3rem 0.6rem;
            border-radius: var(--radius-sm);
            font-size: 0.7rem;
            margin-top: 0.5rem;
            border-left: 3px solid #ff9800;
            color: #e65100;
        }
        
        /* Style khusus untuk task yang sudah selesai */
        .task-done {
            background: #f9faf5;
            border-left: 3px solid var(--soft-green);
        }
        
        /* Tombol aksi dalam task card */
        .task-actions {
            display: flex;
            gap: 0.6rem;
            margin-top: 0.7rem;
            flex-wrap: wrap;
        }
        .btn-status, .btn-delete {
            padding: 0.25rem 0.8rem;
            border-radius: var(--radius-sm);
            font-size: 0.7rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            transition: var(--transition);
        }
        .btn-status {
            background: var(--soft-blue);
            color: #3e5a5a;
        }
        .btn-status:hover { background: #9db8d4; }
        .btn-delete {
            background: var(--soft-red);
            color: var(--dark-brown);
        }
        .btn-delete:hover { background: #d48e8e; }
        
        /* Tombol download file */
        .btn-download {
            background: var(--soft-gray);
            color: #6b6b6b;
            padding: 0.2rem 0.6rem;
            border-radius: 5px;
            font-size: 0.65rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            margin-top: 0.3rem;
        }

        /* ==================== STYLE FOTO/GAMBAR ==================== */
        /* PERBAIKAN UKURAN FOTO - LEBIH BESAR & PROPORSIONAL */
        .task-image {
            margin: 0.5rem 0;
        }
        .task-image img {
            width: 100%;
            max-height: 180px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            border: 1px solid #f0e4d4;
        }
        /* Dokumentasi hasil masakan juga diperbesar */
        .completion-image {
            width: 100%;
            max-height: 150px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            margin-bottom: 0.3rem;
        }

        /* ==================== STATE KOSONG (TIDAK ADA TASK) ==================== */
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: #b8a99a;
        }
        .empty-state p::before {
            content: "🍽️ ";
            font-size: 1.8rem;
            display: block;
            margin-bottom: 0.5rem;
        }

        /* ==================== MEDIA QUERIES (RESPONSIVE) ==================== */
        /* Ukuran tablet dan laptop kecil */
        @media (max-width: 900px) {
            .dashboard-wrapper { padding: 0.8rem 1rem; }
            .stat-card { min-width: 130px; padding: 0.6rem 0.8rem; }
            .stat-icon { font-size: 1.6rem; }
            .stat-number { font-size: 1.5rem; }
            .task-image img { max-height: 150px; }
        }
        
        /* Ukuran tablet portrait */
        @media (max-width: 768px) {
            .navbar { flex-direction: column; text-align: center; }
            .navbar > div { justify-content: center; }
            .stats-container { flex-direction: column; gap: 0.8rem; }
            .kanban-container { flex-direction: column; }
            .task-list { max-height: 50vh; }
            .task-image img { max-height: 130px; }
        }
        
        /* Ukuran HP */
        @media (max-width: 480px) {
            .dashboard-wrapper { padding: 0.6rem; }
            .stat-card { padding: 0.5rem 0.7rem; }
            .stat-icon { font-size: 1.3rem; }
            .stat-number { font-size: 1.3rem; }
            .column-header { font-size: 0.9rem; padding: 0.5rem 0.8rem; }
            .task-card { padding: 0.6rem; }
            .task-image img { max-height: 110px; }
        }
    </style>
</head>
<body>

<!-- ==================== NAVBAR / HEADER ==================== -->
<div class="navbar">
    <h1>Manajemen Dapur Restoran</h1>
    <div>
        <!-- Menampilkan username admin yang sedang login -->
        <span>👨‍💼 Admin: <?= htmlspecialchars($_SESSION['username']) ?></span>
        <!-- Tombol menuju halaman tambah pesanan baru -->
        <a href="tambah.php" class="btn-primary">Pesanan Baru</a>
        <!-- Tombol logout dengan konfirmasi -->
        <a href="../logout.php" class="btn-secondary" onclick="return confirm('Yakin ingin keluar?')">Logout</a>
    </div>
</div>

<div class="dashboard-wrapper">
    <!-- ==================== NOTIFIKASI SUKSES ==================== -->
    <!-- Muncul jika ada parameter GET 'success' setelah redirect -->
    <?php if(isset($_GET['success'])): ?>
        <div class="alert-success">
            <?= $_GET['success'] == 'add' ? '✅ Pesanan baru telah masuk ke dapur!' : '✅ Status pesanan berhasil diperbarui!' ?>
        </div>
    <?php endif; ?>

    <!-- ==================== STATISTIK 3 KARTU ==================== -->
    <!-- Menampilkan jumlah task untuk setiap status -->
    <div class="stats-container">
        <div class="stat-card todo">
            <div class="stat-icon">📋</div>
            <div class="stat-info">
                <div class="stat-number"><?= $total_todo ?></div>
                <div class="stat-label">Pesanan Masuk</div>
            </div>
        </div>
        <div class="stat-card progress">
            <div class="stat-icon">👨‍🍳</div>
            <div class="stat-info">
                <div class="stat-number"><?= $total_progress ?></div>
                <div class="stat-label">Sedang Dimasak</div>
            </div>
        </div>
        <div class="stat-card done">
            <div class="stat-icon">✅</div>
            <div class="stat-info">
                <div class="stat-number"><?= $total_done ?></div>
                <div class="stat-label">Pesanan Selesai</div>
            </div>
        </div>
    </div>

    <!-- ==================== KANBAN 3 KOLOM ==================== -->
    <div class="kanban-container">
        
        <!-- ==================== KOLOM 1: PESANAN MASUK (TODO) ==================== -->
        <div class="kanban-column">
            <h3 class="column-header todo-header">Pesanan Masuk</h3>
            <div class="task-list">
                <?php if($todo_tasks->num_rows > 0): ?>
                    <?php while ($task = $todo_tasks->fetch_assoc()): 
                        // Parsing title untuk mendapatkan nama menu, jumlah, dan special request
                        $parsed = parseTaskTitle($task['title']);
                        // Hitung sisa waktu deadline
                        $timeRemaining = getTimeRemaining($task['deadline']);
                    ?>
                        <div class="task-card">
                            <!-- Nama menu -->
                            <h4><?= htmlspecialchars($parsed['menu_name']) ?></h4>
                            <!-- Nama koki yang bertugas -->
                            <div class="task-detail"><strong>👨‍🍳 Koki:</strong> <span><?= htmlspecialchars($task['username']) ?></span></div>
                            <!-- Jumlah porsi -->
                            <div class="task-detail"><strong>📊 Jumlah:</strong> <span class="quantity-badge"><?= $parsed['quantity'] ?> porsi</span></div>
                            <!-- Deadline dan sisa waktu -->
                            <?php if ($task['deadline']): ?>
                                <div class="task-detail">
                                    <strong>⏰ Deadline:</strong> <span><?= date('d M Y H:i', strtotime($task['deadline'])) ?></span>
                                    <span class="time-remaining <?= strpos($timeRemaining, 'Melewati') !== false ? 'time-urgent' : 'time-safe' ?>"><?= $timeRemaining ?></span>
                                </div>
                            <?php endif; ?>
                            <!-- Special request / catatan khusus -->
                            <?php if ($parsed['special_request']): ?>
                                <div class="special-request-badge">📝 <?= htmlspecialchars($parsed['special_request']) ?></div>
                            <?php endif; ?>
                            <!-- Foto menu (jika ada) -->
                            <?php if ($task['image']): ?>
                                <div class="task-image">
                                    <img src="../img/<?= $task['image'] ?>" alt="Foto Menu">
                                    <a href="../img/<?= $task['image'] ?>" download class="btn-download">📥 Download</a>
                                </div>
                            <?php endif; ?>
                            <!-- Tombol aksi -->
                            <div class="task-actions">
                                <a href="edit.php?id=<?= $task['id'] ?>" class="btn-status">✏️ Proses</a>
                                <a href="hapus.php?id=<?= $task['id'] ?>" class="btn-delete" onclick="return confirm('Batalkan pesanan ini?')">🗑️ Hapus</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <!-- Tampilan jika tidak ada task -->
                    <div class="empty-state"><p>Tidak ada pesanan baru</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ==================== KOLOM 2: SEDANG DIMASAK (PROGRESS) ==================== -->
        <div class="kanban-column">
            <h3 class="column-header progress-header">Sedang Dimasak</h3>
            <div class="task-list">
                <?php if($progress_tasks->num_rows > 0): ?>
                    <?php while ($task = $progress_tasks->fetch_assoc()): 
                        $parsed = parseTaskTitle($task['title']);
                        $timeRemaining = getTimeRemaining($task['deadline']);
                    ?>
                        <div class="task-card">
                            <h4><?= htmlspecialchars($parsed['menu_name']) ?></h4>
                            <div class="task-detail"><strong>👨‍🍳 Koki:</strong> <span><?= htmlspecialchars($task['username']) ?></span></div>
                            <div class="task-detail"><strong>📊 Jumlah:</strong> <span class="quantity-badge"><?= $parsed['quantity'] ?> porsi</span></div>
                            <?php if ($task['deadline']): ?>
                                <div class="task-detail">
                                    <strong>⏰ Target:</strong> <span><?= date('d M Y H:i', strtotime($task['deadline'])) ?></span>
                                    <span class="time-remaining <?= strpos($timeRemaining, 'Melewati') !== false ? 'time-urgent' : 'time-safe' ?>"><?= $timeRemaining ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($parsed['special_request']): ?>
                                <div class="special-request-badge">📝 <?= htmlspecialchars($parsed['special_request']) ?></div>
                            <?php endif; ?>
                            <?php if ($task['image']): ?>
                                <div class="task-image">
                                    <img src="../img/<?= $task['image'] ?>" alt="Foto Menu">
                                    <a href="../img/<?= $task['image'] ?>" download class="btn-download">📥 Download</a>
                                </div>
                            <?php endif; ?>
                            <div class="task-actions">
                                <a href="edit.php?id=<?= $task['id'] ?>" class="btn-status">✏️ Edit</a>
                                <a href="hapus.php?id=<?= $task['id'] ?>" class="btn-delete" onclick="return confirm('Batalkan pesanan ini?')">🗑️ Hapus</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state"><p>Tidak ada yang dimasak</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ==================== KOLOM 3: PESANAN SELESAI (DONE) ==================== -->
        <div class="kanban-column">
            <h3 class="column-header done-header">Pesanan Selesai</h3>
            <div class="task-list">
                <?php if($done_tasks->num_rows > 0): ?>
                    <?php while ($task = $done_tasks->fetch_assoc()): 
                        $parsed = parseTaskTitle($task['title']);
                    ?>
                        <div class="task-card task-done">
                            <h4><?= htmlspecialchars($parsed['menu_name']) ?></h4>
                            <div class="task-detail"><strong>👨‍🍳 Koki:</strong> <span><?= htmlspecialchars($task['username']) ?></span></div>
                            <div class="task-detail"><strong>📊 Jumlah:</strong> <span class="quantity-badge"><?= $parsed['quantity'] ?> porsi</span></div>
                            <?php if ($task['deadline']): ?>
                                <div class="task-detail"><strong>⏰ Target:</strong> <span><?= date('d M Y H:i', strtotime($task['deadline'])) ?></span></div>
                            <?php endif; ?>
                            <!-- Waktu penyelesaian task -->
                            <?php if ($task['completed_at']): ?>
                                <div class="task-detail"><strong>✅ Selesai:</strong> <span><?= date('d M Y H:i', strtotime($task['completed_at'])) ?></span></div>
                            <?php endif; ?>
                            <?php if ($parsed['special_request']): ?>
                                <div class="special-request-badge">📝 <?= htmlspecialchars($parsed['special_request']) ?></div>
                            <?php endif; ?>
                            <?php if ($task['image']): ?>
                                <div class="task-image">
                                    <img src="../img/<?= $task['image'] ?>" alt="Foto Menu">
                                    <a href="../img/<?= $task['image'] ?>" download class="btn-download">📥 Download</a>
                                </div>
                            <?php endif; ?>
                            
                            <!-- ==================== DOKUMENTASI HASIL MASAKAN ==================== -->
                            <!-- Tampilkan file bukti hasil masakan (foto atau file lain) -->
                            <?php if ($task['completion_file']): ?>
                                <div style="background:#f9f6f0; padding:0.5rem; border-radius:6px; margin:0.5rem 0; border-left:2px solid var(--soft-green);">
                                    <p style="font-size:0.65rem; font-weight:600; margin-bottom:0.3rem;">📸 Dokumentasi Hasil:</p>
                                    <?php 
                                    // Cek ekstensi file untuk menentukan apakah bisa ditampilkan sebagai gambar
                                    $ext = pathinfo($task['completion_file'], PATHINFO_EXTENSION);
                                    if (in_array($ext, ['jpg','jpeg','png','gif'])): ?>
                                        <img src="../img/<?= $task['completion_file'] ?>" class="completion-image">
                                    <?php else: ?>
                                        <p style="font-size:0.7rem;">📄 File <?= strtoupper($ext) ?></p>
                                    <?php endif; ?>
                                    <a href="../img/<?= $task['completion_file'] ?>" download class="btn-download">📥 Download</a>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Catatan dari koki setelah menyelesaikan masakan -->
                            <?php if ($task['completion_note']): ?>
                                <div style="background:#faf7f2; padding:0.5rem; border-radius:6px; margin:0.5rem 0;">
                                    <p style="font-size:0.65rem; font-weight:600;">📝 Catatan Koki:</p>
                                    <p style="font-size:0.7rem;"><?= nl2br(htmlspecialchars($task['completion_note'])) ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="task-actions">
                                <a href="edit.php?id=<?= $task['id'] ?>" class="btn-status">✏️ Detail</a>
                                <a href="hapus.php?id=<?= $task['id'] ?>" class="btn-delete" onclick="return confirm('Hapus riwayat pesanan ini?')">🗑️ Hapus</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state"><p>Belum ada pesanan selesai</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
