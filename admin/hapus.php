<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}

include '../koneksi.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Hapus gambar dan completion file jika ada
    $result = $conn->query("SELECT image, completion_file FROM task WHERE id = $id");
    if ($result->num_rows > 0) {
        $task = $result->fetch_assoc();
        
        // Hapus gambar task
        if ($task['image'] && file_exists('../img/' . $task['image'])) {
            unlink('../img/' . $task['image']);
        }
        
        // Hapus file completion
        if ($task['completion_file'] && file_exists('../img/' . $task['completion_file'])) {
            unlink('../img/' . $task['completion_file']);
        }
    }
    
    // Hapus task
    $conn->query("DELETE FROM task WHERE id = $id");
}

header('Location: dashboard.php');
exit;
?>