<?php
session_start();

$host = 'localhost';
$dbname = 'warung_pondok';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

function rupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Cek login
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Ambil parameter id
$santri_id = $_GET['id'] ?? 0;
$santri_nama = $_GET['nama'] ?? '';

// Ambil semua santri untuk dropdown
$santri_list = $pdo->query("SELECT * FROM santri ORDER BY nama_santri")->fetchAll();

// Proses Topup
$message = '';
$message_type = '';
if(isset($_POST['topup'])) {
    $id = $_POST['santri_id'];
    $jumlah = $_POST['jumlah'];
    
    if($jumlah > 0) {
        $cek = $pdo->prepare("SELECT * FROM santri WHERE id = ?");
        $cek->execute([$id]);
        $s = $cek->fetch();
        
        if($s) {
            $update = $pdo->prepare("UPDATE santri SET saldo_tabungan = saldo_tabungan + ? WHERE id = ?");
            if($update->execute([$jumlah, $id])) {
                $_SESSION['success'] = "Topup Rp " . number_format($jumlah,0,',','.') . " untuk " . $s['nama_santri'] . " berhasil!";
                header("Location: index.php?page=santri");
                exit;
            } else {
                $message = "Gagal topup!";
                $message_type = "danger";
            }
        } else {
            $message = "Santri tidak ditemukan!";
            $message_type = "danger";
        }
    } else {
        $message = "Jumlah harus lebih dari 0!";
        $message_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Topup Saldo Santri - Warung Pondok</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: none;
        }
        .btn-primary { background: linear-gradient(90deg, #667eea, #764ba2); color: white; border: none; padding: 12px; }
        .btn-primary:hover { transform: translateY(-2px); filter: brightness(1.05); }
        .btn-secondary { padding: 12px; }
        .form-control { border-radius: 10px; padding: 12px 15px; border: 2px solid #eef2f7; }
        .form-control:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.2); }
        .text-gradient { background: linear-gradient(90deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center" style="min-height: 100vh; align-items: center;">
        <div class="col-md-5">
            <div class="card">
                <div class="text-center mb-4">
                    <i class="fas fa-piggy-bank fa-3x text-gradient"></i>
                    <h3 class="mt-2">Topup Saldo Santri</h3>
                    <p class="text-muted">Isi jumlah topup untuk menambah saldo</p>
                </div>
                
                <?php if($message): ?>
                    <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group mb-3">
                        <label><i class="fas fa-user-graduate"></i> Pilih Santri</label>
                        <select name="santri_id" class="form-control" required>
                            <option value="">-- Pilih Santri --</option>
                            <?php foreach($santri_list as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($santri_id == $s['id']) ? 'selected' : '' ?>>
                                <?= $s['nama_santri'] ?> (<?= $s['nis'] ?>) - Saldo: <?= rupiah($s['saldo_tabungan']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label><i class="fas fa-coins"></i> Jumlah Topup (Rp)</label>
                        <input type="number" name="jumlah" class="form-control" placeholder="Masukkan jumlah topup" required min="1000" step="1000">
                    </div>
                    
                    <button type="submit" name="topup" class="btn btn-primary w-100 mb-2"><i class="fas fa-save"></i> Topup Sekarang</button>
                    <a href="index.php?page=santri" class="btn btn-secondary w-100"><i class="fas fa-arrow-left"></i> Kembali</a>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>