<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);
// Tambahkan ini untuk menampilkan pesan sukses
if(isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
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
    if($angka === null || $angka === '') {
        return "Rp 0";
    }
    return "Rp " . number_format(floatval($angka), 0, ',', '.');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

function getProfil($pdo) {
    $stmt = $pdo->query("SELECT * FROM profil_toko WHERE id = 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function resetJatahHarian($pdo) {
    try {
        $today = date('Y-m-d');
        
        // Ambil semua santri
        $santri = $pdo->query("SELECT id, total_tabungan, jatah_per_hari, saldo_tabungan FROM santri")->fetchAll();
        
        foreach($santri as $s) {
            // Cek apakah sudah ada riwayat hari ini
            $cek = $pdo->prepare("SELECT id, sisa FROM riwayat_jatah_harian WHERE santri_id = ? AND tanggal = ?");
            $cek->execute([$s['id'], $today]);
            $riwayat = $cek->fetch();
            
            if(!$riwayat) {
                // Ambil sisa dari kemarin
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $stmt = $pdo->prepare("SELECT sisa FROM riwayat_jatah_harian WHERE santri_id = ? AND tanggal = ?");
                $stmt->execute([$s['id'], $yesterday]);
                $sisa_kemarin = $stmt->fetchColumn() ?: 0;
                
                // Jatah hari ini = jatah_per_hari + sisa_kemarin
                $jatah_hari_ini = $s['jatah_per_hari'] + $sisa_kemarin;
                
                // Catat jatah hari ini
                $pdo->prepare("INSERT INTO riwayat_jatah_harian (santri_id, tanggal, jatah_harian, terpakai, sisa) VALUES (?, ?, ?, 0, ?)")
                    ->execute([$s['id'], $today, $jatah_hari_ini, $jatah_hari_ini]);
            }
        }
    } catch(PDOException $e) {
        // Log error jika perlu
        error_log("Reset jatah error: " . $e->getMessage());
    }
}

try {
    $pdo->query("SELECT 1 FROM santri LIMIT 1");
    resetJatahHarian($pdo);
} catch(PDOException $e) {}

// ==================== AJAX HANDLERS ====================
if(isset($_GET['ajax'])) {
    if($_GET['ajax'] == 'cari_barang') {
        $keyword = $_GET['q'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM barang WHERE kode_barang LIKE ? OR nama_barang LIKE ? LIMIT 10");
        $stmt->execute(["%$keyword%", "%$keyword%"]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($items as &$item) {
            $opsi = [];
            if(!empty($item['opsi1_nama'])) $opsi[] = ['nama' => $item['opsi1_nama'], 'harga' => $item['opsi1_harga']];
            if(!empty($item['opsi2_nama'])) $opsi[] = ['nama' => $item['opsi2_nama'], 'harga' => $item['opsi2_harga']];
            if(!empty($item['opsi3_nama'])) $opsi[] = ['nama' => $item['opsi3_nama'], 'harga' => $item['opsi3_harga']];
            $item['opsi_list'] = $opsi;
        }
        echo json_encode($items);
        exit;
    }
    
    if($_GET['ajax'] == 'cari_pelanggan') {
        $keyword = $_GET['q'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE kode_pelanggan LIKE ? OR nama_pelanggan LIKE ? LIMIT 10");
        $stmt->execute(["%$keyword%", "%$keyword%"]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
   if($_GET['ajax'] == 'cari_santri') {
    $keyword = $_GET['q'] ?? '';
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("SELECT s.*, 
        COALESCE(r.sisa, s.jatah_per_hari) as sisa_hari_ini,
        COALESCE(r.terpakai, 0) as terpakai_hari_ini,
        s.jatah_per_hari,
        CASE 
            WHEN COALESCE(r.sisa, s.jatah_per_hari) <= 0 THEN 'habis'
            WHEN COALESCE(r.sisa, s.jatah_per_hari) < (s.jatah_per_hari / 2) THEN 'menipis'
            ELSE 'cukup'
        END as status_jatah
        FROM santri s 
        LEFT JOIN riwayat_jatah_harian r ON s.id = r.santri_id AND r.tanggal = ?
        WHERE s.nis LIKE ? OR s.nama_santri LIKE ? 
        LIMIT 10");
    $stmt->execute([$today, "%$keyword%", "%$keyword%"]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}
    
    if($_GET['ajax'] == 'detail_transaksi') {
        $stmt = $pdo->prepare("SELECT d.*, b.nama_barang FROM detail_penjualan d JOIN barang b ON d.barang_id = b.id WHERE penjualan_id = ?");
        $stmt->execute([$_GET['id']]);
        $items = $stmt->fetchAll();
        echo '<div class="card"><div class="card-body"><h5>Detail Transaksi</h5><table class="table table-bordered"><tr><th>Barang</th><th>Jumlah</th><th>Opsi</th><th>Harga</th><th>Subtotal</th></tr>';
        foreach($items as $i) {
            echo "<tr><td>{$i['nama_barang']}</td><td>{$i['jumlah']}</td><td>{$i['opsi_terpakai']}</td><td>" . rupiah($i['harga_saat_jual']) . "</td><td>" . rupiah($i['subtotal']) . "</td></tr>";
        }
        echo '</table></div></div>';
        exit;
    }
    
    if($_GET['ajax'] == 'get_profil') {
        $profil = getProfil($pdo);
        echo json_encode($profil);
        exit;
    }
    
    if($_GET['ajax'] == 'upload_logo' && isAdmin()) {
        if(isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if(in_array($ext, $allowed)) {
                $new_name = 'logo_' . time() . '.' . $ext;
                $upload_dir = 'uploads/';
                if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $new_name);
                $pdo->prepare("UPDATE profil_toko SET logo = ? WHERE id = 1")->execute([$upload_dir . $new_name]);
                echo json_encode(['success' => true, 'logo' => $upload_dir . $new_name]);
                exit;
            }
        }
        echo json_encode(['success' => false]);
        exit;
    }
}

// ==================== HANDLE SIMPAN TRANSAKSI ====================
if(isset($_POST['simpan_transaksi'])) {
    $cart = json_decode($_POST['cart'], true);
    $uang_bayar = $_POST['uang_bayar'];
    $total_harga = $_POST['total_harga'];
    $pelanggan_id = $_POST['pelanggan_id'] ?: null;
    $santri_id = $_POST['santri_id'] ?: null;
    $kembalian = $uang_bayar - $total_harga;
    $no_faktur = "TRX" . date("YmdHis") . rand(100, 999);
    
    try {
        $pdo->beginTransaction();
        
      
       // Cek jatah harian santri
if($santri_id) {
    $today = date('Y-m-d');
    
    // Ambil data santri dan jatah hari ini
    $stmt = $pdo->prepare("SELECT s.nama_santri, s.total_tabungan, r.id as riwayat_id, r.jatah_harian, r.terpakai, r.sisa 
        FROM santri s 
        LEFT JOIN riwayat_jatah_harian r ON s.id = r.santri_id AND r.tanggal = ?
        WHERE s.id = ?");
    $stmt->execute([$today, $santri_id]);
    $santri = $stmt->fetch();
    
    if(!$santri) {
        echo json_encode(['success' => false, 'error' => 'Data santri tidak ditemukan!']);
        exit;
    }
    
    $sisa_jatah_hari_ini = $santri['sisa'] ?? 0;
    
    if($sisa_jatah_hari_ini < $total_harga) {
        echo json_encode(['success' => false, 'error' => "❌ Jatah harian {$santri['nama_santri']} hari ini hanya tersisa " . rupiah($sisa_jatah_hari_ini) . "! Silakan jajan lagi besok."]);
        exit;
    }
 // Update riwayat jatah harian
if($santri_id && isset($riwayat_id)) {
    // Update terpakai dan sisa
    $update = $pdo->prepare("UPDATE riwayat_jatah_harian SET terpakai = ?, sisa = ? WHERE id = ?");
    $update->execute([$terpakai_baru, $sisa_baru, $riwayat_id]);
    
    // KURANGI total_tabungan sebesar yang DIJAJAN
    $kurangi = $pdo->prepare("UPDATE santri SET total_tabungan = total_tabungan - ? WHERE id = ?");
    $kurangi->execute([$total_harga, $santri_id]);
    
    // Update saldo_tabungan untuk tampilan
    $pdo->prepare("UPDATE santri SET saldo_tabungan = total_tabungan WHERE id = ?")->execute([$santri_id]);
}
    // Simpan data untuk update nanti
    $riwayat_id = $santri['riwayat_id'];
    $sisa_baru = $sisa_jatah_hari_ini - $total_harga;
    $terpakai_baru = ($santri['terpakai'] ?? 0) + $total_harga;
}
        
        $stmt = $pdo->prepare("INSERT INTO penjualan (no_faktur, tanggal, pelanggan_id, santri_id, karyawan_id, total_harga, uang_bayar, uang_kembali) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$no_faktur, $pelanggan_id, $santri_id, $_SESSION['user_id'], $total_harga, $uang_bayar, $kembalian]);
        $penjualan_id = $pdo->lastInsertId();
        
        if($pelanggan_id) {
            $pdo->prepare("UPDATE pelanggan SET total_belanja = total_belanja + ?, terakhir_belanja = CURDATE() WHERE id = ?")->execute([$total_harga, $pelanggan_id]);
        }
        
        // Kurangi saldo santri
        if($santri_id) {
            $pdo->prepare("UPDATE santri SET saldo_tabungan = saldo_tabungan - ? WHERE id = ?")->execute([$total_harga, $santri_id]);
            $stmt = $pdo->prepare("SELECT saldo_tabungan FROM santri WHERE id = ?");
            $stmt->execute([$santri_id]);
            $sisa_saldo = $stmt->fetchColumn();
            $pdo->prepare("INSERT INTO riwayat_jajan_santri (santri_id, penjualan_id, jumlah, sisa_saldo) VALUES (?, ?, ?, ?)")->execute([$santri_id, $penjualan_id, $total_harga, $sisa_saldo]);
        }
        
        foreach($cart as $item) {
            $stmt = $pdo->prepare("INSERT INTO detail_penjualan (penjualan_id, barang_id, jumlah, opsi_terpakai, harga_saat_jual, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$penjualan_id, $item['id'], $item['jumlah'], $item['opsi'], $item['harga'], $item['subtotal']]);
            
            $stmt = $pdo->prepare("UPDATE barang SET stok = stok - ?, terakhir_terjual = CURDATE() WHERE id = ?");
            $stmt->execute([$item['jumlah'], $item['id']]);
        }
        
        $stmt = $pdo->query("SELECT saldo_akhir FROM kas_warung ORDER BY tanggal DESC, id DESC LIMIT 1");
        $saldo_sekarang = $stmt->fetchColumn() ?: 0;
        $saldo_baru = $saldo_sekarang + $total_harga;
        $pdo->prepare("INSERT INTO kas_warung (tanggal, jenis, keterangan, jumlah, saldo_akhir) VALUES (CURDATE(), 'pemasukan', 'Penjualan $no_faktur', ?, ?)")->execute([$total_harga, $saldo_baru]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'no_faktur' => $no_faktur]);
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Di bagian HANDLE TAMBAH BARANG
if(isset($_POST['tambah_barang'])) {
    // Pastikan harga tidak ada perhitungan aneh
    $harga_beli = floatval(str_replace(',', '', $_POST['harga_beli']));
    $harga_jual = floatval(str_replace(',', '', $_POST['harga_jual']));
    $opsi1_harga = !empty($_POST['opsi1_harga']) ? floatval(str_replace(',', '', $_POST['opsi1_harga'])) : null;
    $opsi2_harga = !empty($_POST['opsi2_harga']) ? floatval(str_replace(',', '', $_POST['opsi2_harga'])) : null;
    $opsi3_harga = !empty($_POST['opsi3_harga']) ? floatval(str_replace(',', '', $_POST['opsi3_harga'])) : null;
    
    $stmt = $pdo->prepare("INSERT INTO barang (kode_barang, nama_barang, kategori, harga_beli, opsi1_nama, opsi1_harga, opsi2_nama, opsi2_harga, opsi3_nama, opsi3_harga, stok, stok_minimum, stok_maksimum) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $_POST['kode_barang'], $_POST['nama_barang'], $_POST['kategori'], $harga_beli,
        $_POST['opsi1_nama'] ?: null, $opsi1_harga,
        $_POST['opsi2_nama'] ?: null, $opsi2_harga,
        $_POST['opsi3_nama'] ?: null, $opsi3_harga,
        $_POST['stok'], $_POST['stok_minimum'], $_POST['stok_maksimum']
    ]);
}

// ==================== HANDLE EDIT BARANG ====================
if(isset($_POST['edit_barang'])) {
    $stmt = $pdo->prepare("UPDATE barang SET kode_barang=?, nama_barang=?, kategori=?, harga_beli=?, opsi1_nama=?, opsi1_harga=?, opsi2_nama=?, opsi2_harga=?, opsi3_nama=?, opsi3_harga=?, stok=?, stok_minimum=?, stok_maksimum=? WHERE id=?");
    $stmt->execute([
        $_POST['kode_barang'], $_POST['nama_barang'], $_POST['kategori'], $_POST['harga_beli'],
        $_POST['opsi1_nama'] ?: null, $_POST['opsi1_harga'] ?: null,
        $_POST['opsi2_nama'] ?: null, $_POST['opsi2_harga'] ?: null,
        $_POST['opsi3_nama'] ?: null, $_POST['opsi3_harga'] ?: null,
        $_POST['stok'], $_POST['stok_minimum'], $_POST['stok_maksimum'], $_POST['id']
    ]);
    $success = "Barang berhasil diupdate!";
}

// ==================== HANDLE HAPUS BARANG ====================
if(isset($_GET['hapus_barang'])) {
    $pdo->prepare("DELETE FROM barang WHERE id = ?")->execute([$_GET['hapus_barang']]);
    header("Location: ?page=barang");
    exit;
}

// ==================== HANDLE PELANGGAN ====================
if(isset($_POST['tambah_pelanggan'])) {
    $kode = 'PLG' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare("INSERT INTO pelanggan (kode_pelanggan, nama_pelanggan, no_telp, alamat) VALUES (?, ?, ?, ?)");
    $stmt->execute([$kode, $_POST['nama_pelanggan'], $_POST['no_telp'], $_POST['alamat']]);
    $success = "Pelanggan berhasil ditambahkan!";
}

if(isset($_GET['hapus_pelanggan'])) {
    $id = $_GET['hapus_pelanggan'];
    
    // Cek apakah pelanggan memiliki transaksi
    $cek = $pdo->prepare("SELECT COUNT(*) FROM penjualan WHERE pelanggan_id = ?");
    $cek->execute([$id]);
    $jumlah_transaksi = $cek->fetchColumn();
    
    if($jumlah_transaksi > 0) {
        // Jika punya transaksi, tampilkan error
        $error = "Pelanggan tidak dapat dihapus karena sudah memiliki $jumlah_transaksi transaksi!";
        header("Location: ?page=pelanggan&error=" . urlencode($error));
        exit;
    } else {
        // Jika tidak punya transaksi, boleh hapus
        $pdo->prepare("DELETE FROM pelanggan WHERE id = ?")->execute([$id]);
        $success = "Pelanggan berhasil dihapus!";
        header("Location: ?page=pelanggan");
        exit;
    }
}

// ==================== HANDLE TAMBAH SANTRI ====================
if(isset($_POST['tambah_santri']) && isAdmin()) {
    try {
        $nis = $_POST['nis'];
        $nama_santri = $_POST['nama_santri'];
        $kelas = $_POST['kelas'];
        $jatah_harian = $_POST['jatah_harian'];
        $saldo_awal = $_POST['saldo_awal'];
        
        // Validasi
        if(empty($nis) || empty($nama_santri)) {
            $error = "NIS dan Nama Santri wajib diisi!";
        } else {
            // Cek apakah NIS sudah ada
            $cek = $pdo->prepare("SELECT COUNT(*) FROM santri WHERE nis = ?");
            $cek->execute([$nis]);
            if($cek->fetchColumn() > 0) {
                $error = "NIS '$nis' sudah terdaftar!";
            } else {
                // Insert santri baru (sesuai dengan struktur tabel)
                $stmt = $pdo->prepare("INSERT INTO santri (nis, nama_santri, kelas, jatah_harian, saldo_tabungan, total_tabungan, jatah_per_hari, jumlah_hari_jatah, tanggal_reset, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW())");
                $stmt->execute([
                    $nis, 
                    $nama_santri, 
                    $kelas, 
                    $jatah_harian, 
                    $saldo_awal, 
                    $saldo_awal,
                    $jatah_harian,
                    30
                ]);
                $success = "Santri berhasil ditambahkan!";
                header("Location: ?page=santri");
                exit;
            }
        }
    } catch(PDOException $e) {
        $error = "Gagal menambahkan santri: " . $e->getMessage();
    }
}
// ==================== HANDLE EDIT SANTRI ====================
if(isset($_POST['edit_santri']) && isAdmin()) {
    try {
        $stmt = $pdo->prepare("UPDATE santri SET nis=?, nama_santri=?, kelas=?, jatah_harian=?, saldo_tabungan=?, total_tabungan=?, jatah_per_hari=? WHERE id=?");
        $stmt->execute([
            $_POST['nis'], 
            $_POST['nama_santri'], 
            $_POST['kelas'], 
            $_POST['jatah_harian'], 
            $_POST['saldo_tabungan'],
            $_POST['saldo_tabungan'],
            $_POST['jatah_harian'],
            $_POST['id']
        ]);
        $success = "Data santri berhasil diupdate!";
        header("Location: ?page=santri");
        exit;
    } catch(PDOException $e) {
        $error = "Gagal mengupdate santri: " . $e->getMessage();
    }
}
// ==================== HANDLE TOPUP SALDO ====================
if(isset($_POST['topup_saldo']) && isAdmin()) {
    $jumlah = $_POST['jumlah'];
    $santri_id = $_POST['santri_id'];
    
    if($jumlah > 0 && $santri_id > 0) {
        $cek = $pdo->prepare("SELECT * FROM santri WHERE id = ?");
        $cek->execute([$santri_id]);
        $santri = $cek->fetch();
        
        if($santri) {
            $stmt = $pdo->prepare("UPDATE santri SET saldo_tabungan = saldo_tabungan + ? WHERE id = ?");
            if($stmt->execute([$jumlah, $santri_id])) {
                $_SESSION['success'] = "Topup Rp " . number_format($jumlah,0,',','.') . " untuk " . $santri['nama_santri'] . " berhasil!";
                header("Location: ?page=santri");
                exit;
            } else {
                $error = "Gagal topup saldo!";
            }
        } else {
            $error = "Santri tidak ditemukan!";
        }
    } else {
        $error = "Jumlah topup harus lebih dari 0!";
    }
}
// ==================== HANDLE KARYAWAN ====================
if(isset($_POST['tambah_karyawan']) && isAdmin()) {
    $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama_lengkap, no_telp) VALUES (?, ?, 'karyawan', ?, ?)");
    $stmt->execute([$_POST['username'], $hash, $_POST['nama_lengkap'], $_POST['no_telp']]);
    $success = "Karyawan berhasil ditambahkan!";
}

if(isset($_GET['hapus_karyawan']) && isAdmin()) {
    $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'karyawan'")->execute([$_GET['hapus_karyawan']]);
    header("Location: ?page=karyawan");
    exit;
}

// ==================== HANDLE PENGELUARAN ====================
if(isset($_POST['tambah_pengeluaran'])) {
    $pdo->prepare("INSERT INTO pengeluaran (tanggal, keterangan, jumlah, jenis) VALUES (CURDATE(), ?, ?, ?)")->execute([$_POST['keterangan'], $_POST['jumlah'], $_POST['jenis']]);
    
    $stmt = $pdo->query("SELECT saldo_akhir FROM kas_warung ORDER BY tanggal DESC, id DESC LIMIT 1");
    $saldo_sekarang = $stmt->fetchColumn() ?: 0;
    $saldo_baru = $saldo_sekarang - $_POST['jumlah'];
    $pdo->prepare("INSERT INTO kas_warung (tanggal, jenis, keterangan, jumlah, saldo_akhir) VALUES (CURDATE(), 'pengeluaran', ?, ?, ?)")->execute([$_POST['keterangan'], $_POST['jumlah'], $saldo_baru]);
    
    $success = "Pengeluaran berhasil dicatat!";
}

// ==================== HANDLE KAS ====================
if(isset($_POST['tambah_kas'])) {
    $stmt = $pdo->query("SELECT saldo_akhir FROM kas_warung ORDER BY tanggal DESC, id DESC LIMIT 1");
    $saldo_sekarang = $stmt->fetchColumn() ?: 0;
    
    if($_POST['jenis_kas'] == 'pemasukan') {
        $saldo_baru = $saldo_sekarang + $_POST['jumlah'];
    } else {
        $saldo_baru = $saldo_sekarang - $_POST['jumlah'];
    }
    
    $pdo->prepare("INSERT INTO kas_warung (tanggal, jenis, keterangan, jumlah, saldo_akhir) VALUES (CURDATE(), ?, ?, ?, ?)")->execute([$_POST['jenis_kas'], $_POST['keterangan'], $_POST['jumlah'], $saldo_baru]);
    $success = "Kas berhasil dicatat!";
}

// ==================== HANDLE UPDATE PROFIL ====================
if(isset($_POST['update_profil']) && isAdmin()) {
    $stmt = $pdo->prepare("UPDATE profil_toko SET nama_toko = ?, alamat = ?, no_telp = ?, email = ?, footer_text = ? WHERE id = 1");
    $stmt->execute([$_POST['nama_toko'], $_POST['alamat'], $_POST['no_telp'], $_POST['email'], $_POST['footer_text']]);
    $success = "Profil toko berhasil diupdate!";
}

// ==================== HANDLE RESET DATA ====================
if(isset($_POST['reset_data']) && isAdmin()) {
    try {
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM detail_penjualan");
        $pdo->exec("DELETE FROM penjualan");
        $pdo->exec("DELETE FROM kas_warung");
        $pdo->exec("DELETE FROM pengeluaran");
        $pdo->exec("UPDATE barang SET stok = 0, terakhir_terjual = NULL");
        $pdo->exec("UPDATE pelanggan SET total_belanja = 0, terakhir_belanja = NULL");
        $pdo->exec("INSERT INTO kas_warung (tanggal, jenis, keterangan, jumlah, saldo_akhir) VALUES (CURDATE(), 'pemasukan', 'Reset Data - Saldo Awal', 0, 0)");
        $pdo->commit();
        $success = "Semua data berhasil direset!";
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Gagal reset data!";
    }
}

if(isset($_POST['reset_stok']) && isAdmin()) {
    $stok_baru = $_POST['stok_baru'] ?? 0;
    $pdo->prepare("UPDATE barang SET stok = ?")->execute([$stok_baru]);
    $success = "Stok semua barang berhasil direset menjadi $stok_baru!";
}

// ==================== HANDLE LOGIN ====================
if(isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $login_valid = false;
    
    if($user) {
        if(password_verify($password, $user['password'])) {
            $login_valid = true;
        } elseif($password == $user['password']) {
            $login_valid = true;
            $hash_baru = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash_baru, $user['id']]);
        } elseif(($password == 'admin123' && $user['username'] == 'admin') ||
                 ($password == 'admin123' && $user['role'] == 'karyawan')) {
            $hash_baru = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash_baru, $user['id']]);
            $login_valid = true;
        }
    }
    
    if($login_valid) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['nama'] = $user['nama_lengkap'];
        $_SESSION['show_welcome'] = true;
        header("Location: ?page=dashboard");
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}

// ==================== HANDLE GANTI PASSWORD ====================
if(isset($_POST['ganti_password'])) {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if(password_verify($_POST['password_lama'], $user['password'])) {
        if($_POST['password_baru'] == $_POST['konfirmasi']) {
            $hash = password_hash($_POST['password_baru'], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $_SESSION['user_id']]);
            $success = "Password berhasil diubah!";
        } else {
            $error = "Password baru tidak cocok!";
        }
    } else {
        $error = "Password lama salah!";
    }
}

// ==================== HANDLE LOGOUT ====================
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: ?");
    exit;
}

$page = $_GET['page'] ?? 'login';
if(isLoggedIn() && $page == 'login') $page = 'dashboard';

if(isset($_SESSION['show_welcome']) && $page != 'dashboard') {
    unset($_SESSION['show_welcome']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Warung Pondok - Sistem Penjualan & Stok</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .login-page {
            background: linear-gradient(135deg, rgba(102,126,234,0.9) 0%, rgba(118,75,162,0.9) 100%),
                        url('https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?q=80&w=2070') center/cover fixed;
            min-height: 100vh;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding-top: 20px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 5px 0 20px rgba(0,0,0,0.1);
        }
        
        .sidebar h4 {
            text-align: center;
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .sidebar a {
            display: block;
            padding: 12px 25px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            margin: 5px 15px;
            border-radius: 10px;
        }
        
        .sidebar a:hover, .sidebar a.active {
            background: linear-gradient(90deg, #e94560, #ff6b6b);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar a i { margin-right: 12px; width: 25px; }
        
        .content {
            margin-left: 280px;
            padding: 25px;
            min-height: 100vh;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: none;
            transition: transform 0.3s;
        }
        
        .card:hover { transform: translateY(-3px); }
        
        .stat-card {
            text-align: center;
            padding: 25px;
            border-radius: 20px;
            color: white;
            transition: all 0.3s;
        }
        
        .stat-card:hover { transform: scale(1.02); }
        .stat-card h3 { font-size: 2.2rem; font-weight: 700; margin: 15px 0; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: linear-gradient(90deg, #667eea, #764ba2); color: white; font-weight: 500; }
        tr:hover { background: #f8f9ff; }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: inline-block;
            margin: 3px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary { background: linear-gradient(90deg, #667eea, #764ba2); color: white; }
        .btn-danger { background: linear-gradient(90deg, #e94560, #ff6b6b); color: white; }
        .btn-warning { background: linear-gradient(90deg, #f39c12, #e67e22); color: white; }
        .btn-success { background: linear-gradient(90deg, #27ae60, #2ecc71); color: white; }
        .btn-info { background: linear-gradient(90deg, #1abc9c, #16a085); color: white; }
        .btn:hover { transform: translateY(-2px); filter: brightness(1.05); }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #eef2f7;
            border-radius: 12px;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.2);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: none;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-warning { background: #fff3cd; color: #856404; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        
        .row { display: flex; gap: 25px; flex-wrap: wrap; }
        .col { flex: 1; min-width: 280px; }
        
        .kasir-container { display: flex; gap: 25px; flex-wrap: wrap; }
        .kasir-form { flex: 1.2; min-width: 320px; }
        .kasir-keranjang { flex: 0.8; min-width: 320px; }
        .search-result { max-height: 400px; overflow-y: auto; }
        .item-card, .pelanggan-card, .opsi-card {
            background: #f8f9ff;
            border: 1px solid #eef2f7;
            padding: 12px 15px;
            margin: 8px 0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .item-card:hover, .pelanggan-card:hover, .opsi-card:hover {
            background: linear-gradient(90deg, #667eea15, #764ba215);
            transform: translateX(5px);
            border-color: #667eea;
        }
        
        .struk {
            max-width: 350px;
            margin: 0 auto;
            font-family: monospace;
            background: white;
            padding: 20px;
            border-radius: 12px;
        }
        
        .login-box {
            max-width: 450px;
            margin: 100px auto;
            background: white;
            padding: 40px;
            border-radius: 30px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 25px;
            max-width: 550px;
            width: 90%;
            max-height: 85%;
            overflow-y: auto;
        }
        .close {
            float: right;
            font-size: 28px;
            cursor: pointer;
        }
        .close:hover { color: #e94560; }
        
        .welcome-splash {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            animation: fadeOut 3s ease-in-out forwards;
        }
        .welcome-content {
            text-align: center;
            color: white;
            animation: bounceIn 0.8s ease;
        }
        .welcome-content h1 { font-size: 3rem; margin-bottom: 20px; }
        @keyframes fadeOut {
            0% { opacity: 1; visibility: visible; }
            70% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
        }
        @keyframes bounceIn {
            0% { transform: scale(0.5); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .logo-preview {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 20px;
            border: 3px solid #667eea;
            margin-bottom: 15px;
        }
        
        .text-gradient {
            background: linear-gradient(90deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .content { margin-left: 0; }
            .kasir-container { flex-direction: column; }
            .row { gap: 15px; }
            .stat-card h3 { font-size: 1.5rem; }
        }
        
        .table-responsive { overflow-x: auto; }
        .w-100 { width: 100% !important; }
    </style>
</head>
<body class="<?= !isLoggedIn() ? 'login-page' : '' ?>">

<?php if(!isLoggedIn()): ?>
    <!-- HALAMAN LOGIN -->
    <div class="login-box">
        <div class="text-center mb-4">
            <i class="fas fa-store fa-4x text-gradient"></i>
            <h3 class="mt-3">Warung Pondok</h3>
            <p class="text-muted">Sistem Penjualan & Stok</p>
        </div>
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Username</label>
                <input type="text" name="username" class="form-control" placeholder="Masukkan username" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
            </div>
            <button type="submit" name="login" class="btn btn-primary w-100"><i class="fas fa-sign-in-alt"></i> Login</button>
        </form>
        <hr>
        <div class="text-center text-muted small">
           JANGAN LUPA LOGIN TERLEBIH DAHULU YA!
        </div>
    </div>

<?php else: ?>

<!-- WELCOME SPLASH -->
<?php if(isset($_SESSION['show_welcome']) && $_SESSION['show_welcome']): ?>
<div class="welcome-splash" id="welcomeSplash">
    <div class="welcome-content">
        <i class="fas fa-store fa-5x mb-4"></i>
        <h1>🌸 Selamat Datang di Warpon! 🌸</h1>
        <p>Halo, <?= htmlspecialchars($_SESSION['nama']) ?>! Senang melihat Anda kembali 💙</p>
    </div>
</div>
<script>setTimeout(function() { document.getElementById('welcomeSplash').style.display = 'none'; }, 2800);</script>
<?php unset($_SESSION['show_welcome']); endif; ?>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="text-center mb-4">
        <?php 
        $profil = getProfil($pdo);
        if($profil && !empty($profil['logo']) && file_exists($profil['logo'])): ?>
            <img src="<?= $profil['logo'] ?>" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;">
        <?php else: ?>
            <i class="fas fa-store fa-3x"></i>
        <?php endif; ?>
        <h5><?= htmlspecialchars($profil['nama_toko'] ?? 'Warung Pondok') ?></h5>
        <small><?= $_SESSION['role'] == 'admin' ? 'Administrator' : 'Karyawan' ?></small>
    </div>
    <hr style="margin: 0 20px; opacity: 0.3;">
    <a href="?page=dashboard" class="<?= $page == 'dashboard' ? 'active' : '' ?>"><i class="fas fa-home"></i> Dashboard</a>
    <a href="?page=kasir" class="<?= $page == 'kasir' ? 'active' : '' ?>"><i class="fas fa-shopping-cart"></i> Kasir</a>
    <a href="?page=barang" class="<?= $page == 'barang' ? 'active' : '' ?>"><i class="fas fa-boxes"></i> Data Barang</a>
    <a href="?page=tambah_barang" class="<?= $page == 'tambah_barang' ? 'active' : '' ?>"><i class="fas fa-plus-circle"></i> Tambah Barang</a>
    <a href="?page=pelanggan" class="<?= $page == 'pelanggan' ? 'active' : '' ?>"><i class="fas fa-users"></i> Data Pelanggan</a>
    <a href="?page=santri" class="<?= $page == 'santri' ? 'active' : '' ?>"><i class="fas fa-user-graduate"></i> Data Santri</a>
    <a href="?page=tabungan" class="<?= $page == 'tabungan' ? 'active' : '' ?>"><i class="fas fa-piggy-bank"></i> Tabungan Santri</a>
    <?php if(isAdmin()): ?>
    <a href="?page=karyawan" class="<?= $page == 'karyawan' ? 'active' : '' ?>"><i class="fas fa-user-tie"></i> Data Karyawan</a>
    <?php endif; ?>
    <a href="?page=riwayat" class="<?= $page == 'riwayat' ? 'active' : '' ?>"><i class="fas fa-history"></i> Riwayat</a>
    <a href="?page=laporan" class="<?= $page == 'laporan' ? 'active' : '' ?>"><i class="fas fa-chart-line"></i> Laporan</a>
    <a href="?page=kas" class="<?= $page == 'kas' ? 'active' : '' ?>"><i class="fas fa-money-bill-wave"></i> Kas Warung</a>
    <a href="?page=profil" class="<?= $page == 'profil' ? 'active' : '' ?>"><i class="fas fa-building"></i> Profil Toko</a>
    <?php if(isAdmin()): ?>
    <a href="?page=reset_data" class="<?= $page == 'reset_data' ? 'active' : '' ?>"><i class="fas fa-database"></i> Reset Data</a>
    <?php endif; ?>
    <a href="?page=ganti_password"><i class="fas fa-key"></i> Ganti Password</a>
    <a href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- MAIN CONTENT -->
<div class="content">
    
    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if(isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>
    
    <?php
    // ==================== DASHBOARD ====================
    if($page == 'dashboard'): 
        $penjualan_hari = $pdo->query("SELECT SUM(total_harga) as total FROM penjualan WHERE DATE(tanggal) = CURDATE()")->fetch()['total'] ?? 0;
        $total_barang = $pdo->query("SELECT COUNT(*) FROM barang")->fetchColumn();
        $total_pelanggan = $pdo->query("SELECT COUNT(*) FROM pelanggan WHERE nama_pelanggan != 'Umum'")->fetchColumn();
        $saldo_kas = $pdo->query("SELECT saldo_akhir FROM kas_warung ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0;
        $stokTipis = $pdo->query("SELECT COUNT(*) FROM barang WHERE stok <= stok_minimum")->fetchColumn();
        $total_santri = $pdo->query("SELECT COUNT(*) FROM santri")->fetchColumn();
        $total_saldo_santri = $pdo->query("SELECT SUM(saldo_tabungan) FROM santri")->fetchColumn() ?: 0;
    ?>
        <h2><i class="fas fa-tachometer-alt text-gradient"></i> Dashboard</h2>
        <p>Selamat datang kembali, <?= htmlspecialchars($_SESSION['nama']) ?>! 👋</p>
        
        <div class="row">
            <div class="col"><div class="stat-card" style="background: linear-gradient(135deg, #667eea, #764ba2);"><i class="fas fa-money-bill-wave fa-2x"></i><h3><?= rupiah($penjualan_hari) ?></h3><p>Penjualan Hari Ini</p></div></div>
            <div class="col"><div class="stat-card" style="background: linear-gradient(135deg, #27ae60, #2ecc71);"><i class="fas fa-boxes fa-2x"></i><h3><?= $total_barang ?></h3><p>Total Barang</p></div></div>
            <div class="col"><div class="stat-card" style="background: linear-gradient(135deg, #f39c12, #e67e22);"><i class="fas fa-users fa-2x"></i><h3><?= $total_pelanggan ?></h3><p>Total Pelanggan</p></div></div>
            <div class="col"><div class="stat-card" style="background: linear-gradient(135deg, #1abc9c, #16a085);"><i class="fas fa-wallet fa-2x"></i><h3><?= rupiah($saldo_kas) ?></h3><p>Saldo Kas</p></div></div>
        </div>
        <div class="row">
            <div class="col"><div class="stat-card" style="background: linear-gradient(135deg, #e94560, #ff6b6b);"><i class="fas fa-exclamation-triangle fa-2x"></i><h3><?= $stokTipis ?></h3><p>Stok Menipis</p></div></div>
            <div class="col"><div class="stat-card" style="background: linear-gradient(135deg, #8e44ad, #9b59b6);"><i class="fas fa-user-graduate fa-2x"></i><h3><?= $total_santri ?></h3><p>Total Santri</p></div></div>
            <div class="col"><div class="stat-card" style="background: linear-gradient(135deg, #16a085, #1abc9c);"><i class="fas fa-piggy-bank fa-2x"></i><h3><?= rupiah($total_saldo_santri) ?></h3><p>Saldo Tabungan Santri</p></div></div>
            <div class="col"><div class="stat-card" style="background: linear-gradient(135deg, #2c3e50, #34495e);"><i class="fas fa-percent fa-2x"></i><h3><?= $total_barang > 0 ? round(($stokTipis/$total_barang)*100, 1) : 0 ?>%</h3><p>Persentase Stok Tipis</p></div></div>
        </div>
        
        <?php if($stokTipis > 0): ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Terdapat <?= $stokTipis ?> barang dengan stok menipis! <a href="?page=barang" class="alert-link">Lihat daftar</a></div>
        <?php endif; ?>
        
        <div class="row mt-3">
            <div class="col">
                <div class="card">
                    <h5><i class="fas fa-bolt text-gradient"></i> Menu Cepat</h5>
                    <hr>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="?page=kasir" class="btn btn-success"><i class="fas fa-cash-register"></i> Transaksi Baru</a>
                        <a href="?page=tambah_barang" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Barang</a>
                        <a href="?page=pelanggan" class="btn btn-info"><i class="fas fa-user-plus"></i> Tambah Pelanggan</a>
                        <a href="?page=santri" class="btn btn-warning"><i class="fas fa-user-graduate"></i> Tambah Santri</a>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card">
                    <h5><i class="fas fa-trophy text-gradient"></i> 5 Pelanggan Terbaik</h5>
                    <hr>
                    <?php
                    $topPelanggan = $pdo->query("SELECT nama_pelanggan, total_belanja FROM pelanggan WHERE nama_pelanggan != 'Umum' ORDER BY total_belanja DESC LIMIT 5")->fetchAll();
                    foreach($topPelanggan as $p): ?>
                        <div class="d-flex justify-content-between mb-2"><span><i class="fas fa-user-circle"></i> <?= $p['nama_pelanggan'] ?></span><span class="fw-bold text-success"><?= rupiah($p['total_belanja']) ?></span></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    
    <?php
// ==================== KASIR ====================
elseif($page == 'kasir'):
    $profil = getProfil($pdo);
?>
    <h2><i class="fas fa-cash-register text-gradient"></i> Kasir</h2>
    
    <!-- SCAN BARCODE (PALING ATAS) -->
    <div class="card mb-3" style="background: linear-gradient(135deg, #667eea15, #764ba215); border: 2px solid #667eea;">
        <div class="card-body">
            <div class="form-group mb-0">
                <label class="fw-bold"><i class="fas fa-barcode"></i> Scan Barcode / Kode Barang</label>
                <div class="input-group">
                    <span class="input-group-text bg-primary text-white"><i class="fas fa-qrcode"></i></span>
                    <input type="text" id="scan_barcode" class="form-control form-control-lg" placeholder="Scan atau ketik kode barang... lalu tekan ENTER" autofocus>
                </div>
                <small class="text-muted">⚡ Tekan Enter setelah scan untuk langsung menambah ke keranjang</small>
            </div>
        </div>
    </div>
    
    <div class="kasir-container">
        <div class="kasir-form card">
            <h5><i class="fas fa-search"></i> Cari Barang</h5>
            <input type="text" id="cari_barang" class="form-control" placeholder="Ketik kode atau nama barang..." onkeyup="cariBarang()">
            <div id="hasil_cari" class="search-result mt-3"></div>
            
            <hr>
            <h5><i class="fas fa-user-tag"></i> Cari Pelanggan (Umum)</h5>
            <input type="text" id="cari_pelanggan" class="form-control" placeholder="Cari pelanggan..." onkeyup="cariPelanggan()">
            <input type="hidden" id="pelanggan_id" value="">
            <div id="hasil_pelanggan" class="search-result mt-2"></div>
            
            <hr>
            <h5><i class="fas fa-user-graduate"></i> Pilih Santri (Potong Saldo Otomatis)</h5>
            <input type="text" id="cari_santri" class="form-control" placeholder="Cari santri..." onkeyup="cariSantri()">
            <input type="hidden" id="santri_id" value="">
            <div id="hasil_santri" class="search-result mt-2"></div>
            <div id="info_saldo" class="alert alert-info mt-2" style="display:none"></div>
        </div>
        
        <div class="kasir-keranjang card">
            <h5><i class="fas fa-shopping-basket"></i> Keranjang Belanja</h5>
            <div id="keranjang" class="mb-3" style="max-height: 400px; overflow-y: auto;"></div>
            <hr>
            <div class="d-flex justify-content-between"><h5>Total:</h5><h4 class="text-gradient" id="total_harga">Rp 0</h4></div>
            
            <div id="uang_bayar_container" style="display: block;">
                <div class="form-group">
                    <label><i class="fas fa-money-bill"></i> Uang Bayar</label>
                    <input type="number" id="uang_bayar" class="form-control" placeholder="Masukkan jumlah uang" onkeyup="hitungKembalian()">
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <h5>Kembalian:</h5>
                    <h5 class="text-success" id="kembalian">Rp 0</h5>
                </div>
            </div>
            
            <div id="info_santri_container" style="display: none;" class="alert alert-success">
                <i class="fas fa-info-circle"></i> <strong>Mode Santri Aktif</strong><br>
                Transaksi akan langsung dipotong dari saldo.
            </div>
            
            <button class="btn btn-success w-100 mt-3" onclick="simpanTransaksi()"><i class="fas fa-save"></i> Simpan & Cetak Struk</button>
        </div>
    </div>
    
    <div id="strukModal" class="modal"><div class="modal-content"><span class="close" onclick="closeModal()">&times;</span><div id="strukContent"></div><div class="d-flex gap-2 mt-3"><button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Cetak Struk</button><button class="btn btn-secondary" onclick="closeModal()">Tutup</button></div></div></div>
    <div id="opsiModal" class="modal"><div class="modal-content"><span class="close" onclick="closeOpsiModal()">&times;</span><h5>Pilih Opsi Barang</h5><div id="opsiList"></div></div></div>
    
    <script>
    let cart = [];
    let profil = {};
    let selectedItem = null;
    let isSantri = false;
    
    $.get('?ajax=get_profil', function(data) { profil = JSON.parse(data); });
    
   // =============== SCAN BARCODE (TAMBAH TANPA MENGHAPUS) ===============
$('#scan_barcode').keypress(function(e) {
    if(e.which == 13) {
        e.preventDefault();
        let kode = $(this).val().trim();
        if(kode != '') {
            $.get('?ajax=cari_barang&q=' + kode, function(data) {
                let items = JSON.parse(data);
                if(items.length > 0) {
                    let item = items[0];
                    
                    // Cari apakah barang sudah ada di keranjang
                    let found = false;
                    for(let i = 0; i < cart.length; i++) {
                        if(cart[i].id === item.id) {
                            // Jika sudah ada, tambah jumlahnya
                            cart[i].jumlah++;
                            cart[i].subtotal = cart[i].jumlah * cart[i].harga;
                            found = true;
                            break;
                        }
                    }
                    
                    // Jika belum ada, tambah barang baru
                    if(!found) {
                        let harga = (item.opsi_list && item.opsi_list.length > 0) ? item.opsi_list[0].harga : parseFloat(item.harga_jual);
                        let opsi = (item.opsi_list && item.opsi_list.length > 0) ? item.opsi_list[0].nama : (item.satuan_dasar || 'pcs');
                        
                        cart.push({
                            id: item.id,
                            nama: item.nama_barang,
                            opsi: opsi,
                            harga: parseFloat(harga),
                            jumlah: 1,
                            subtotal: parseFloat(harga)
                        });
                    }
                    
                    // Refresh tampilan keranjang
                    tampilkanKeranjang();
                    
                    // Reset input scan
                    $('#scan_barcode').val('');
                    $('#scan_barcode').focus();
                    
                    // Debug: lihat isi keranjang di console
                    console.log("Isi keranjang sekarang:", cart);
                    
                } else {
                    alert('Barang dengan kode "' + kode + '" tidak ditemukan!');
                    $('#scan_barcode').val('');
                    $('#scan_barcode').focus();
                }
            });
        }
    }
});
    
    function cariBarangByKode(kode) {
        $.get('?ajax=cari_barang&q=' + kode, function(data) {
            let items = JSON.parse(data);
            if(items.length > 0) {
                let item = items[0];
                if(item.opsi_list && item.opsi_list.length > 0) {
                    let opsi = item.opsi_list[0];
                    tambahKeKeranjangDenganOpsi(opsi.nama, opsi.harga, item.id, item.nama_barang);
                } else {
                    tambahKeKeranjangLangsung(item.id, item.nama_barang, item.harga_jual, item.satuan_dasar);
                }
                $('#scan_barcode').val('');
                $('#scan_barcode').focus();
            } else {
                alert('Barang dengan kode "' + kode + '" tidak ditemukan!');
                $('#scan_barcode').val('');
                $('#scan_barcode').focus();
            }
        });
    }
    
    function tambahKeKeranjangLangsung(id, nama, harga, satuan) {
        let existing = cart.find(item => item.id === id);
        if(existing) {
            existing.jumlah++;
            existing.subtotal = existing.jumlah * existing.harga;
        } else {
            cart.push({id: id, nama: nama, opsi: satuan, harga: harga, jumlah: 1, subtotal: harga});
        }
        tampilkanKeranjang();
    }
    
    function tambahKeKeranjangDenganOpsi(opsiNama, harga, id = null, nama = null) {
        if(id === null) id = selectedItem.id;
        if(nama === null) nama = selectedItem.nama;
        
        let existing = cart.find(item => item.id === id && item.opsi === opsiNama);
        if(existing) {
            existing.jumlah++;
            existing.subtotal = existing.jumlah * existing.harga;
        } else {
            cart.push({id: id, nama: nama, opsi: opsiNama, harga: harga, jumlah: 1, subtotal: harga});
        }
        if(selectedItem) closeOpsiModal();
        tampilkanKeranjang();
        $('#cari_barang').val('');
        $('#hasil_cari').html('');
    }
    function cariBarang() {
        let keyword = $('#cari_barang').val();
        if(keyword.length < 2) return;
        $.get('?ajax=cari_barang&q=' + keyword, function(data) {
            let items = JSON.parse(data);
            let html = '';
            items.forEach(item => {
                html += `<div class="item-card" onclick="pilihBarang(${item.id}, '${item.nama_barang}', ${JSON.stringify(item.opsi_list).replace(/"/g, '&quot;')})">
                            <strong>${item.kode_barang}</strong> - ${item.nama_barang}<br>
                            <small>Kategori: ${item.kategori} | Stok: ${item.stok}</small>
                        </div>`;
            });
            $('#hasil_cari').html(html || '<p class="text-muted">Barang tidak ditemukan</p>');
        });
    }
    
   function pilihBarang(id, nama, opsiList) {
    selectedItem = {id: id, nama: nama, opsi: opsiList};
    if(opsiList.length > 1) {
        let html = '';
        opsiList.forEach((opsi, idx) => {
            html += `<div class="opsi-card" onclick="tambahKeKeranjangDenganOpsi('${opsi.nama}', ${opsi.harga}, ${id}, '${nama}')">
                        <strong>${opsi.nama}</strong><br>
                        Harga: ${formatRupiah(opsi.harga)}
                    </div>`;
        });
        $('#opsiList').html(html);
        $('#opsiModal').css('display', 'flex');
    } else if(opsiList.length == 1) {
        tambahKeKeranjangDenganOpsi(opsiList[0].nama, opsiList[0].harga, id, nama);
    } else {
        tambahKeKeranjangLangsung(id, nama, <?php echo $b['harga_jual'] ?? 0; ?>, 'pcs');
    }
}
    
    function tambahKeKeranjangDenganOpsi(opsiNama, harga) {
        let existing = cart.find(item => item.id === selectedItem.id && item.opsi === opsiNama);
        if(existing) { existing.jumlah++; existing.subtotal = existing.jumlah * existing.harga; }
        else { cart.push({id: selectedItem.id, nama: selectedItem.nama, opsi: opsiNama, harga: harga, jumlah: 1, subtotal: harga}); }
        closeOpsiModal();
        tampilkanKeranjang();
        $('#cari_barang').val('');
        $('#hasil_cari').html('');
    }
    
    function tampilkanKeranjang() {
        if(cart.length === 0) { $('#keranjang').html('<p class="text-muted text-center">Keranjang kosong</p>'); $('#total_harga').text('Rp 0'); return; }
        let html = '<table class="table table-sm"><tr><th>Barang</th><th>Opsi</th><th>Jumlah</th><th>Subtotal</th><th></th></tr>';
        let total = 0;
        cart.forEach((item, idx) => {
            total += item.subtotal;
            html += `<tr><td><strong>${item.nama}</strong><br><small>${formatRupiah(item.harga)}</small></td><td><small>${item.opsi}</small></td><td><input type="number" value="${item.jumlah}" min="1" style="width:70px" onchange="ubahJumlah(${idx}, this.value)"></td><td>${formatRupiah(item.subtotal)}</td><td><button class="btn btn-danger btn-sm" onclick="hapusItem(${idx})"><i class="fas fa-trash"></i></button></td></tr>`;
        });
        html += '</table>';
        $('#keranjang').html(html);
        $('#total_harga').text(formatRupiah(total));
        
        // Hitung ulang jika mode santri
        if(isSantri) {
            $('#uang_bayar').val(total);
            $('#kembalian').text(formatRupiah(0));
        } else {
            hitungKembalian();
        }
    }
    
    function ubahJumlah(idx, jumlah) { jumlah = parseInt(jumlah); if(jumlah < 1) jumlah = 1; cart[idx].jumlah = jumlah; cart[idx].subtotal = cart[idx].harga * jumlah; tampilkanKeranjang(); }
    function hapusItem(idx) { cart.splice(idx, 1); tampilkanKeranjang(); }
    
    function cariPelanggan() {
        let keyword = $('#cari_pelanggan').val();
        if(keyword.length < 2) return;
        $.get('?ajax=cari_pelanggan&q=' + keyword, function(data) {
            let items = JSON.parse(data);
            let html = '';
            items.forEach(item => {
                if(item.nama_pelanggan !== 'Umum') {
                    html += `<div class="pelanggan-card" onclick="pilihPelanggan(${item.id}, '${item.nama_pelanggan}')">
                                <strong>${item.kode_pelanggan}</strong> - ${item.nama_pelanggan}<br>
                                <small>Telp: ${item.no_telp || '-'} | Total Belanja: ${formatRupiah(item.total_belanja)}</small>
                            </div>`;
                }
            });
            $('#hasil_pelanggan').html(html || '<p class="text-muted">Pelanggan tidak ditemukan</p>');
        });
    }
    
    function pilihPelanggan(id, nama) { 
        $('#pelanggan_id').val(id); 
        $('#cari_pelanggan').val(nama); 
        $('#hasil_pelanggan').html('');
        // Hapus pilihan santri jika ada
        $('#santri_id').val('');
        $('#cari_santri').val('');
        $('#hasil_santri').html('');
        $('#info_saldo').hide();
        isSantri = false;
        $('#uang_bayar_container').show();
        $('#info_santri_container').hide();
        hitungKembalian();
    }
    
    function cariSantri() {
    let keyword = $('#cari_santri').val();
    if(keyword.length < 2) return;
    $.get('?ajax=cari_santri&q=' + keyword, function(data) {
        let items = JSON.parse(data);
        let html = '';
        items.forEach(item => {
            let statusClass = '';
            let statusText = '';
            let sisa = item.sisa_hari_ini;
            
            if(sisa <= 0) {
                statusClass = 'border-danger bg-danger-light';
                statusText = ' <span class="badge bg-danger">Jatah Hari Ini Habis!</span>';
            } else if(sisa < (item.jatah_per_hari / 2)) {
                statusClass = 'border-warning bg-warning-light';
                statusText = ' <span class="badge bg-warning">Jatah Menipis!</span>';
            }
            
            html += `<div class="pelanggan-card ${statusClass}" onclick="pilihSantri(${item.id}, '${item.nama_santri}', ${item.sisa_hari_ini}, ${item.jatah_per_hari}, '${item.status_jatah}', ${item.terpakai_hari_ini})">
                        <strong>${item.nis}</strong> - ${item.nama_santri}${statusText}<br>
                        Kelas: ${item.kelas}<br>
                        <small>📅 Jatah hari ini: ${formatRupiah(item.sisa_hari_ini)} / ${formatRupiah(item.jatah_per_hari)} (Terpakai: ${formatRupiah(item.terpakai_hari_ini)})</small>
                    </div>`;
        });
        $('#hasil_santri').html(html || '<p class="text-muted">Santri tidak ditemukan</p>');
    });
}
    
   function pilihSantri(id, nama, sisaHariIni, jatahPerHari, statusJatah, terpakaiHariIni) {
    $('#santri_id').val(id);
    $('#cari_santri').val(nama);
    $('#hasil_santri').html('');
    
    let sisa = sisaHariIni;
    let terpakai = terpakaiHariIni || 0;
    
    if(sisa <= 0) {
        $('#info_saldo').html(`⚠️ <strong>Jatah ${nama} sudah habis hari ini!</strong><br>
            Jatah per hari: ${formatRupiah(jatahPerHari)}<br>
            Sudah terpakai: ${formatRupiah(terpakai)}<br>
            <strong>Silakan jajan lagi besok!</strong>`).removeClass('alert-info').addClass('alert-danger').show();
        isSantri = false;
        $('#uang_bayar_container').show();
        $('#info_santri_container').hide();
        $('#santri_id').val('');
        return;
    } else {
        $('#info_saldo').html(`💰 <strong>Jatah ${nama} hari ini:</strong><br>
            Jatah per hari: ${formatRupiah(jatahPerHari)}<br>
            Sisa: ${formatRupiah(sisa)}<br>
            Terpakai: ${formatRupiah(terpakai)}`).removeClass('alert-danger').addClass('alert-info').show();
    }
    
    $('#pelanggan_id').val('');
    $('#cari_pelanggan').val('');
    $('#hasil_pelanggan').html('');
    isSantri = true;
    $('#uang_bayar_container').hide();
    $('#info_santri_container').show();
    
    let total = 0;
    cart.forEach(item => total += item.subtotal);
    
    if(total > sisa) {
        alert(`⚠️ Jatah ${nama} hari ini hanya tersisa ${formatRupiah(sisa)}! Total belanja Anda ${formatRupiah(total)} melebihi jatah.`);
    }
    
    $('#uang_bayar').val(total);
    $('#kembalian').text(formatRupiah(0));
}
    
    function hitungKembalian() {
        let total = 0;
        cart.forEach(item => total += item.subtotal);
        let bayar = parseInt($('#uang_bayar').val()) || 0;
        $('#kembalian').text(formatRupiah(bayar - total < 0 ? 0 : bayar - total));
    }
    
    function formatRupiah(angka) { return 'Rp ' + angka.toLocaleString('id-ID'); }
    
    function simpanTransaksi() {
        if(cart.length === 0) { alert('Keranjang kosong!'); return; }
        let total = 0;
        cart.forEach(item => total += item.subtotal);
        
        let bayar = 0;
        let santriId = $('#santri_id').val();
        
        if(santriId) {
            // Mode Santri: uang bayar = total (otomatis)
            bayar = total;
        } else {
            bayar = parseInt($('#uang_bayar').val()) || 0;
            if(bayar < total) { alert('Uang bayar kurang!'); return; }
        }
        
        let namaPelanggan = $('#cari_pelanggan').val() || 'Umum';
        let namaSantri = $('#cari_santri').val();
        let namaCustomer = namaSantri ? namaSantri + ' (Santri)' : namaPelanggan;
        
        $.post('?', {
            simpan_transaksi: 1,
            cart: JSON.stringify(cart),
            total_harga: total,
            uang_bayar: bayar,
            pelanggan_id: $('#pelanggan_id').val(),
            santri_id: santriId
        }, function(response) {
            let res = JSON.parse(response);
            if(res.success) {
                let strukHtml = `<div class="struk"><h5 class="text-center">${profil.nama_toko || 'Warung Pondok'}</h5>
                    <p class="text-center small">${(profil.alamat || '').replace(/\n/g, '<br>')}<br>Telp: ${profil.no_telp || '-'}</p>
                    <hr><p class="small">No Faktur: ${res.no_faktur}<br>Tanggal: ${new Date().toLocaleString()}<br>Kasir: <?= $_SESSION['nama'] ?></p>
                    <p class="small"><strong>Pelanggan:</strong> ${namaCustomer}</p><hr>
                    <table style="width:100%">`;
                cart.forEach(item => { strukHtml += `<tr><td>${item.nama}<br><small>${item.opsi}</small></td><td>${item.jumlah}x</td><td align="right">${formatRupiah(item.subtotal)}</td></tr>`; });
                strukHtml += `<tr><td colspan="2"><strong>Total</strong></td><td align="right"><strong>${formatRupiah(total)}</strong></td></tr>
                    <tr><td colspan="2">Bayar</td><td align="right">${formatRupiah(bayar)}</td></tr>
                    <tr><td colspan="2">Kembalian</td><td align="right">${formatRupiah(bayar-total)}</td></tr>
                    </table><hr><p class="text-center small">${(profil.footer_text || 'Terima kasih!').replace(/\n/g, '<br>')}</p></div>`;
                $('#strukContent').html(strukHtml);
                $('#strukModal').css('display', 'flex');
                cart = []; 
                tampilkanKeranjang(); 
                $('#uang_bayar').val(''); 
                $('#cari_barang').val(''); 
                $('#cari_pelanggan').val(''); 
                $('#cari_santri').val(''); 
                $('#pelanggan_id').val(''); 
                $('#santri_id').val(''); 
                $('#hasil_cari').html(''); 
                $('#hasil_pelanggan').html(''); 
                $('#hasil_santri').html(''); 
                $('#info_saldo').hide();
                $('#uang_bayar_container').show();
                $('#info_santri_container').hide();
                isSantri = false;
            } else { alert(res.error || 'Gagal menyimpan transaksi!'); }
        });
    }
    
    function closeModal() { $('#strukModal').css('display', 'none'); }
    function closeOpsiModal() { $('#opsiModal').css('display', 'none'); selectedItem = null; }
    </script>

<?php

    // ==================== DATA BARANG ====================
    elseif($page == 'barang'):
        $barang = $pdo->query("SELECT * FROM barang ORDER BY nama_barang")->fetchAll();
    ?>
        <div class="d-flex justify-content-between align-items-center mb-4"><h2><i class="fas fa-boxes text-gradient"></i> Data Barang</h2><a href="?page=tambah_barang" class="btn btn-success"><i class="fas fa-plus"></i> Tambah Barang</a></div>
        <div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Harga Beli</th><th>Stok</th><th>Min/Max</th><th>Opsi</th><th>Aksi</th></tr></thead><tbody>
        <?php foreach($barang as $b): 
            $opsiText = [];
            if(!empty($b['opsi1_nama'])) $opsiText[] = $b['opsi1_nama'] . ' (' . rupiah($b['opsi1_harga']) . ')';
            if(!empty($b['opsi2_nama'])) $opsiText[] = $b['opsi2_nama'] . ' (' . rupiah($b['opsi2_harga']) . ')';
            if(!empty($b['opsi3_nama'])) $opsiText[] = $b['opsi3_nama'] . ' (' . rupiah($b['opsi3_harga']) . ')';
        ?>
        <tr style="<?= $b['stok'] <= $b['stok_minimum'] ? 'background:#fff3cd' : '' ?>">
            <td><?= $b['kode_barang'] ?></td><td><?= $b['nama_barang'] ?></td><td><?= $b['kategori'] ?></td>
            <td><?= rupiah($b['harga_beli']) ?></td><td><?= $b['stok'] ?></td><td><?= $b['stok_minimum'] ?> / <?= $b['stok_maksimum'] ?></td>
            <td><small><?= implode('<br>', $opsiText) ?></small></td>
            <td><a href="?page=edit_barang&id=<?= $b['id'] ?>" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i> Edit</a> <a href="?hapus_barang=<?= $b['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin hapus?')"><i class="fas fa-trash"></i> Hapus</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
    
    <?php
// ==================== TAMBAH/EDIT BARANG (VERSION BARU) ====================
elseif($page == 'tambah_barang' || $page == 'edit_barang'):
    $isEdit = ($page == 'edit_barang' && isset($_GET['id']));
    if($isEdit) { 
        $b = $pdo->prepare("SELECT * FROM barang WHERE id = ?"); 
        $b->execute([$_GET['id']]); 
        $b = $b->fetch(); 
    }
?>
<style>
    .form-section {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 25px;
        border-left: 4px solid #667eea;
    }
    .form-section h5 {
        color: #667eea;
        margin-bottom: 20px;
        font-weight: 600;
    }
    .image-preview {
        width: 150px;
        height: 150px;
        border: 2px dashed #ddd;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 15px;
        overflow: hidden;
        background: #f8f9fa;
    }
    .image-preview img {
        max-width: 100%;
        max-height: 100%;
        object-fit: cover;
    }
    .btn-upload {
        background: linear-gradient(90deg, #667eea, #764ba2);
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 8px;
        cursor: pointer;
    }
    .row-custom {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 15px;
    }
    .col-custom {
        flex: 1;
        min-width: 200px;
    }
    hr {
        margin: 20px 0;
    }
    .row {
    display: flex;
    flex-wrap: wrap;
}
.col-md-6 {
    width: 50%;
    padding: 0 10px;
}
@media (max-width: 768px) {
    .col-md-6 {
        width: 100%;
    }
}
</style>

<h2><i class="fas fa-<?= $isEdit ? 'edit' : 'plus-circle' ?> text-gradient"></i> <?= $isEdit ? 'Edit Barang' : 'Input Data Barang' ?></h2>

<form method="POST" enctype="multipart/form-data" id="formBarang">
    <?php if($isEdit): ?>
        <input type="hidden" name="id" value="<?= $b['id'] ?>">
    <?php endif; ?>
    
    <!-- ==================== IDENTITAS BARANG ==================== -->
    <div class="form-section">
        <h5><i class="fas fa-identity-card"></i> Identitas Barang</h5>
        <div class="row-custom">
            <div class="col-custom">
                <div class="form-group">
                    <label>Kode Barang <span class="text-danger">*</span></label>
                    <input type="text" name="kode_barang" class="form-control" value="<?= $isEdit ? htmlspecialchars($b['kode_barang']) : '' ?>" required>
                </div>
            </div>
            <div class="col-custom">
                <div class="form-group">
                    <label>Nama Barang <span class="text-danger">*</span></label>
                    <input type="text" name="nama_barang" class="form-control" value="<?= $isEdit ? htmlspecialchars($b['nama_barang']) : '' ?>" required>
                </div>
            </div>
            <div class="col-custom">
                <div class="form-group">
                    <label>Kategori <span class="text-danger">*</span></label>
                    <select name="kategori" class="form-control" required>
                        <option value="Kopi/Minuman" <?= ($isEdit && $b['kategori']=='Kopi/Minuman')?'selected':'' ?>>Kopi/Minuman</option>
                        <option value="Rokok" <?= ($isEdit && $b['kategori']=='Rokok')?'selected':'' ?>>Rokok</option>
                        <option value="Cemilan" <?= ($isEdit && $b['kategori']=='Cemilan')?'selected':'' ?>>Cemilan</option>
                        <option value="Mie" <?= ($isEdit && $b['kategori']=='Mie')?'selected':'' ?>>Mie</option>
                        <option value="Alat Tulis" <?= ($isEdit && $b['kategori']=='Alat Tulis')?'selected':'' ?>>Alat Tulis</option>
                        <option value="Buku & Kitab" <?= ($isEdit && $b['kategori']=='Buku & Kitab')?'selected':'' ?>>Buku & Kitab</option>
                        <option value="Lainnya" <?= ($isEdit && $b['kategori']=='Lainnya')?'selected':'' ?>>Lainnya</option>
                    </select>
                </div>
            </div>
            <div class="col-custom">
                <div class="form-group">
                    <label>Expired / Kadaluarsa</label>
                    <input type="date" name="expired" class="form-control" value="<?= $isEdit && isset($b['expired']) ? $b['expired'] : '' ?>">
                </div>
            </div>
        </div>
    </div>
    
    <!-- ==================== GAMBAR ==================== -->
    <div class="form-section">
        <h5><i class="fas fa-image"></i> Gambar Barang</h5>
        <div class="row-custom">
            <div class="col-custom text-center">
                <div class="image-preview" id="previewImage1">
                    <?php if($isEdit && !empty($b['gambar1']) && file_exists($b['gambar1'])): ?>
                        <img src="<?= $b['gambar1'] ?>" alt="Gambar 1">
                    <?php else: ?>
                        <i class="fas fa-image fa-3x text-muted"></i>
                    <?php endif; ?>
                </div>
                <input type="file" name="gambar1" class="form-control mt-2" accept="image/*" onchange="previewImage(this, 'previewImage1')">
                <small class="text-muted">Gambar Utama</small>
            </div>
            <div class="col-custom text-center">
                <div class="image-preview" id="previewImage2">
                    <?php if($isEdit && !empty($b['gambar2']) && file_exists($b['gambar2'])): ?>
                        <img src="<?= $b['gambar2'] ?>" alt="Gambar 2">
                    <?php else: ?>
                        <i class="fas fa-image fa-3x text-muted"></i>
                    <?php endif; ?>
                </div>
                <input type="file" name="gambar2" class="form-control mt-2" accept="image/*" onchange="previewImage(this, 'previewImage2')">
                <small class="text-muted">Gambar Tambahan</small>
            </div>
            <div class="col-custom text-center">
                <div class="image-preview" id="previewImage3">
                    <?php if($isEdit && !empty($b['gambar3']) && file_exists($b['gambar3'])): ?>
                        <img src="<?= $b['gambar3'] ?>" alt="Gambar 3">
                    <?php else: ?>
                        <i class="fas fa-image fa-3x text-muted"></i>
                    <?php endif; ?>
                </div>
                <input type="file" name="gambar3" class="form-control mt-2" accept="image/*" onchange="previewImage(this, 'previewImage3')">
                <small class="text-muted">Gambar Tambahan</small>
            </div>
        </div>
    </div>
    
    <!-- ==================== SATUAN DAN ISI ==================== -->
    <div class="form-section">
        <h5><i class="fas fa-balance-scale"></i> Satuan dan Isi</h5>
        <div class="row-custom">
            <div class="col-custom">
                <div class="form-group">
                    <label>Satuan Beli</label>
                    <input type="text" name="satuan_beli" class="form-control" placeholder="Contoh: Dus, Pak, Bungkus" value="<?= $isEdit ? ($b['satuan_beli'] ?? '') : '' ?>">
                </div>
            </div>
            <div class="col-custom">
                <div class="form-group">
                    <label>Satuan Jual</label>
                    <input type="text" name="satuan_jual" class="form-control" placeholder="Contoh: Pcs, Batang, Bungkus" value="<?= $isEdit ? ($b['satuan_jual'] ?? '') : '' ?>">
                </div>
            </div>
            <div class="col-custom">
                <div class="form-group">
                    <label>Isi</label>
                    <input type="number" name="isi" class="form-control" placeholder="Jumlah isi per satuan beli" value="<?= $isEdit ? ($b['isi'] ?? '1') : '1' ?>">
                </div>
            </div>
        </div>
    </div>
    
    <!-- ==================== HARGA DAN STOK ==================== -->
    <div class="form-section">
        <h5><i class="fas fa-money-bill"></i> Harga dan Stok</h5>
        <div class="row-custom">
            <div class="col-custom">
                <div class="form-group">
                    <label>Harga Beli <span class="text-danger">*</span></label>
                    <input type="number" name="harga_beli" class="form-control" value="<?= $isEdit ? $b['harga_beli'] : '' ?>" required>
                </div>
            </div>
            <div class="col-custom">
                <div class="form-group">
                    <label>Harga Jual <span class="text-danger">*</span></label>
                    <input type="number" name="harga_jual" class="form-control" value="<?= $isEdit ? $b['harga_jual'] : '' ?>" required>
                </div>
            </div>
            <div class="col-custom">
                <div class="form-group">
                    <label>Stok Awal <span class="text-danger">*</span></label>
                    <input type="number" name="stok" class="form-control" value="<?= $isEdit ? $b['stok'] : '0' ?>" required>
                </div>
            </div>
        </div>
        <div class="row-custom">
            <div class="col-custom">
                <div class="form-group">
                    <label>Stok Minimum</label>
                    <input type="number" name="stok_minimum" class="form-control" value="<?= $isEdit ? $b['stok_minimum'] : '5' ?>">
                </div>
            </div>
            <div class="col-custom">
                <div class="form-group">
                    <label>Stok Maksimum</label>
                    <input type="number" name="stok_maksimum" class="form-control" value="<?= $isEdit ? $b['stok_maksimum'] : '100' ?>">
                </div>
            </div>
            <div class="col-custom">
                <div class="form-group">
                    <label>Multi Opsi Harga</label>
                    <select name="tipe_item" class="form-control">
                        <option value="single" <?= ($isEdit && ($b['tipe_item'] ?? '') == 'single') ? 'selected' : '' ?>>Tidak Ada Opsi</option>
                        <option value="multi" <?= ($isEdit && ($b['tipe_item'] ?? '') == 'multi') ? 'selected' : '' ?>>Ada Multi Opsi</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ==================== MULTI OPSI (ditampilkan jika dipilih) ==================== -->
    <div id="multiOpsiContainer" class="form-section" style="display: none;">
        <h5><i class="fas fa-list-ul"></i> Multi Opsi Harga</h5>
        <div class="row-custom">
            <div class="col-custom">
                <div class="form-group">
                    <label>Opsi 1 Nama</label>
                    <input type="text" name="opsi1_nama" class="form-control" placeholder="Contoh: Kopi Sachet / Per Batang" value="<?= $isEdit ? htmlspecialchars($b['opsi1_nama'] ?? '') : '' ?>">
                </div>
                <div class="form-group">
                    <label>Opsi 1 Harga</label>
                    <input type="number" name="opsi1_harga" class="form-control" value="<?= $isEdit ? ($b['opsi1_harga'] ?? '') : '' ?>">
                </div>
            </div>
            <div class="col-custom">
                <div class="form-group">
                    <label>Opsi 2 Nama</label>
                    <input type="text" name="opsi2_nama" class="form-control" placeholder="Contoh: Kopi Seduh / Per Bungkus" value="<?= $isEdit ? htmlspecialchars($b['opsi2_nama'] ?? '') : '' ?>">
                </div>
                <div class="form-group">
                    <label>Opsi 2 Harga</label>
                    <input type="number" name="opsi2_harga" class="form-control" value="<?= $isEdit ? ($b['opsi2_harga'] ?? '') : '' ?>">
                </div>
            </div>
            <div class="col-custom">
                <div class="form-group">
                    <label>Opsi 3 Nama</label>
                    <input type="text" name="opsi3_nama" class="form-control" placeholder="Contoh: Per Pak" value="<?= $isEdit ? htmlspecialchars($b['opsi3_nama'] ?? '') : '' ?>">
                </div>
                <div class="form-group">
                    <label>Opsi 3 Harga</label>
                    <input type="number" name="opsi3_harga" class="form-control" value="<?= $isEdit ? ($b['opsi3_harga'] ?? '') : '' ?>">
                </div>
            </div>
        </div>
    </div>
    
    <div class="d-flex gap-2 mt-3">
        <button type="submit" name="<?= $isEdit ? 'edit_barang' : 'tambah_barang' ?>" class="btn btn-primary"><i class="fas fa-save"></i> Simpan (F8)</button>
        <a href="?page=barang" class="btn btn-secondary"><i class="fas fa-times"></i> Keluar (ESC)</a>
    </div>
</form>

<script>
    // Preview image sebelum upload
    function previewImage(input, previewId) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#' + previewId).html('<img src="' + e.target.result + '" style="max-width:100%; max-height:100%; object-fit:cover">');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    // Tampilkan/sembunyikan multi opsi
    $('select[name="tipe_item"]').change(function() {
        if($(this).val() == 'multi') {
            $('#multiOpsiContainer').slideDown();
        } else {
            $('#multiOpsiContainer').slideUp();
        }
    });
    
    // Cek saat load
    if($('select[name="tipe_item"]').val() == 'multi') {
        $('#multiOpsiContainer').show();
    }
    
    // Shortcut F8 untuk simpan, ESC untuk keluar
    $(document).keydown(function(e) {
        if(e.keyCode == 119) { // F8
            e.preventDefault();
            $('button[type="submit"]').click();
        }
        if(e.keyCode == 27) { // ESC
            e.preventDefault();
            window.location.href = '?page=barang';
        }
    });
</script>
    
    <?php
    // ==================== DATA PELANGGAN ====================
    elseif($page == 'pelanggan'):
       $pelanggan = $pdo->query("SELECT * FROM pelanggan WHERE nama_pelanggan != 'Umum' AND status = 'aktif' ORDER BY total_belanja DESC")->fetchAll();
    ?>
        <div class="d-flex justify-content-between align-items-center mb-4"><h2><i class="fas fa-users text-gradient"></i> Data Pelanggan</h2><button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalPelanggan"><i class="fas fa-plus"></i> Tambah Pelanggan</button></div>
        <div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>Kode</th><th>Nama</th><th>No Telp</th><th>Kobong</th><th>Aksi</th></tr></thead><tbody>
        <?php foreach($pelanggan as $p): ?>
        <tr><td><?= $p['kode_pelanggan'] ?></td><td><?= $p['nama_pelanggan'] ?></td><td><?= $p['no_telp'] ?? '-' ?></td><td><?= $p['alamat'] ?? '-' ?></td><td><a href="?hapus_pelanggan=<?= $p['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin hapus?')"><i class="fas fa-trash"></i> Hapus</a></td></tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
        <div class="modal fade" id="modalPelanggan"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5>Tambah Pelanggan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body"><div class="form-group"><label>Kode</label><input type="text" name="kode" class="form-control" required></div><div class="form-group"><label>Nama pelanggan</label><input type="text" name="nama_pelanggan" class="form-control"></div><div class="form-group"><label>No Telp</label><input type="text" name="no_telp" class="form-control"></div><div class="form-group"><label>Kobong</label><textarea name="alamat" class="form-control"></textarea></div></div><div class="modal-footer"><button type="submit" name="tambah_pelanggan" class="btn btn-primary">Simpan</button></div></form></div></div></div>
    
    <?php
    // ==================== DATA SANTRI ====================
    elseif($page == 'santri'):
        $santri = $pdo->query("SELECT * FROM santri ORDER BY kelas, nama_santri")->fetchAll();
    ?>
        <div class="d-flex justify-content-between align-items-center mb-4"><h2><i class="fas fa-user-graduate text-gradient"></i> Data Santri</h2><button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalSantri"><i class="fas fa-plus"></i> Tambah Santri</button></div>
        <div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Jatah/Hari</th><th>Sisa Saldo</th><th>Terakhir Reset</th><th>Aksi</th></tr></thead><tbody>
        <?php foreach($santri as $s): ?>
        <tr class="<?= ($s['saldo_tabungan'] ?? 0) < ($s['jatah_harian'] ?? 0) ? 'table-warning' : '' ?>"><td><?= $s['nis'] ?></td><td><?= $s['nama_santri'] ?></td><td><?= $s['kelas'] ?></td><td><?= rupiah($s['jatah_harian']) ?></td><td><strong class="<?= ($s['saldo_tabungan'] ?? 0) < 5000 ? 'text-danger' : 'text-success' ?>"><?= rupiah($s['saldo_tabungan'] ?? 0) ?></strong></td><td><?= $s['tanggal_reset'] ?? '-' ?></td>
        <td>
    <a href="test_topup.php?id=<?= $s['id'] ?>&nama=<?= urlencode($s['nama_santri']) ?>" class="btn btn-info btn-sm">
        <i class="fas fa-plus-circle"></i> Topup
    </a>
    <a href="?hapus_santri=<?= $s['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin hapus?')"><i class="fas fa-trash"></i></a>
</td></tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
        
<div class="modal fade" id="modalSantri" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Tambah Santri</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>NIS</label>
                        <input type="text" name="nis" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Santri</label>
                        <input type="text" name="nama_santri" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Kelas</label>
                        <input type="text" name="kelas" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Jatah Harian (Rp)</label>
                        <input type="number" name="jatah_harian" class="form-control" value="10000" required>
                    </div>
                    <div class="form-group">
                        <label>Saldo Awal (Rp)</label>
                        <input type="number" name="saldo_awal" class="form-control" value="50000" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="tambah_santri" class="btn btn-primary">Simpan</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>        
     <div class="modal fade" id="modalTopup" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-money-bill"></i> Topup Saldo Santri</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?page=santri">
                <input type="hidden" name="santri_id" id="topup_santri_id">
                <div class="modal-body">
                    <p>Santri: <strong id="topup_nama_santri"></strong></p>
                    <div class="form-group">
                        <label>Jumlah Topup (Rp)</label>
                        <input type="number" name="jumlah" class="form-control" required min="1000" step="1000">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="topup_saldo" class="btn btn-primary"><i class="fas fa-save"></i> Topup</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>
     <script>
function topupSaldo(id, nama) {
    document.getElementById('topup_santri_id').value = id;
    document.getElementById('topup_nama_santri').innerText = nama;
    var modal = new bootstrap.Modal(document.getElementById('modalTopup'));
    modal.show();
}
     </script>
    
    <?php
    // ==================== TABUNGAN SANTRI ====================
    elseif($page == 'tabungan'):
        $santri = $pdo->query("SELECT * FROM santri ORDER BY saldo_tabungan DESC")->fetchAll();
        $total_saldo = $pdo->query("SELECT SUM(saldo_tabungan) FROM santri")->fetchColumn();
    ?>
        <h2><i class="fas fa-piggy-bank text-gradient"></i> Tabungan Santri</h2>
        <div class="row"><div class="col-md-4"><div class="card text-center"><h5>Total Saldo</h5><h3 class="text-success"><?= rupiah($total_saldo) ?></h3></div></div><div class="col-md-4"><div class="card text-center"><h5>Jumlah Santri</h5><h3><?= count($santri) ?> Orang</h3></div></div><div class="col-md-4"><div class="card text-center"><h5>Rata-rata Saldo</h5><h3><?= rupiah($total_saldo / max(1, count($santri))) ?></h3></div></div></div>
        <div class="card mt-3"><div class="table-responsive"><table class="table"><thead><tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Jatah/Hari</th><th>Sisa Saldo</th><th>Status</th></tr></thead><tbody>
        <?php foreach($santri as $s): $status = ($s['saldo_tabungan'] ?? 0) >= ($s['jatah_harian'] ?? 0) ? 'Cukup' : 'Kurang'; $status_class = ($s['saldo_tabungan'] ?? 0) >= ($s['jatah_harian'] ?? 0) ? 'success' : 'danger'; ?>
        <tr><td><?= $s['nis'] ?></td><td><?= $s['nama_santri'] ?></td><td><?= $s['kelas'] ?></td><td><?= rupiah($s['jatah_harian']) ?></td><td><strong class="text-<?= $status_class ?>"><?= rupiah($s['saldo_tabungan'] ?? 0) ?></strong></td><td><span class="badge bg-<?= $status_class ?>"><?= $status ?></span></td></tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
    
    <?php
// ==================== DATA KARYAWAN ====================
elseif($page == 'karyawan' && isAdmin()):
    $karyawan = $pdo->query("SELECT * FROM users WHERE role = 'karyawan'")->fetchAll();
?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-user-tie text-gradient"></i> Data Karyawan</h2>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalKaryawan">
            <i class="fas fa-plus"></i> Tambah Karyawan
        </button>
    </div>
    
    <?php if(empty($karyawan)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Belum ada data karyawan. Silakan tambah karyawan baru.
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>No Telepon</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach($karyawan as $k): 
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($k['username']) ?></td>
                        <td><?= htmlspecialchars($k['nama_lengkap']) ?></td>
                        <td><?= htmlspecialchars($k['no_telp'] ?? '-') ?></td>
                        <td>
                            <a href="?hapus_karyawan=<?= $k['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin hapus karyawan ini?')">
                                <i class="fas fa-trash"></i> Hapus
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal Tambah Karyawan -->
    <div class="modal fade" id="modalKaryawan" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Tambah Karyawan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>No Telepon</label>
                            <input type="text" name="no_telp" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="tambah_karyawan" class="btn btn-primary">Simpan</button>
                        <button type="button" class="btn-secondary" data-bs-dismiss="modal">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php

    // ==================== RIWAYAT TRANSAKSI ====================
    elseif($page == 'riwayat'):
        $filter = $_GET['filter'] ?? 'hari';
        if($filter == 'hari') $transaksi = $pdo->query("SELECT p.*, pl.nama_pelanggan, s.nama_santri FROM penjualan p LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.id LEFT JOIN santri s ON p.santri_id = s.id WHERE DATE(tanggal) = CURDATE() ORDER BY tanggal DESC")->fetchAll();
        elseif($filter == 'minggu') $transaksi = $pdo->query("SELECT p.*, pl.nama_pelanggan, s.nama_santri FROM penjualan p LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.id LEFT JOIN santri s ON p.santri_id = s.id WHERE YEARWEEK(tanggal) = YEARWEEK(NOW()) ORDER BY tanggal DESC")->fetchAll();
        else $transaksi = $pdo->query("SELECT p.*, pl.nama_pelanggan, s.nama_santri FROM penjualan p LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.id LEFT JOIN santri s ON p.santri_id = s.id ORDER BY tanggal DESC LIMIT 100")->fetchAll();
    ?>
        <h2><i class="fas fa-history text-gradient"></i> Riwayat Transaksi</h2>
        <div class="card"><div class="mb-3"><a href="?page=riwayat&filter=hari" class="btn btn-primary btn-sm">Hari Ini</a> <a href="?page=riwayat&filter=minggu" class="btn btn-primary btn-sm">Minggu Ini</a> <a href="?page=riwayat&filter=semua" class="btn btn-primary btn-sm">Semua</a></div>
        <div class="table-responsive"><table class="table"><thead><tr><th>No Faktur</th><th>Tanggal</th><th>Pelanggan</th><th>Santri</th><th>Total</th><th>Bayar</th><th>Aksi</th></tr></thead><tbody>
        <?php foreach($transaksi as $t): ?>
        <tr><td><?= $t['no_faktur'] ?></td><td><?= date('d/m/Y H:i', strtotime($t['tanggal'])) ?></td><td><?= $t['nama_pelanggan'] ?? 'Umum' ?></td><td><?= $t['nama_santri'] ?? '-' ?></td><td><?= rupiah($t['total_harga']) ?></td><td><?= rupiah($t['uang_bayar']) ?></td><td><a href="#" class="btn btn-info btn-sm" onclick="lihatDetail(<?= $t['id'] ?>)">Detail</a></td></tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
        <div id="detailModal" class="modal"><div class="modal-content"><span class="close" onclick="$('#detailModal').hide()">&times;</span><div id="detailContent"></div></div></div>
    
    <?php
    // ==================== LAPORAN ====================
    elseif($page == 'laporan'):
        $periode = $_GET['periode'] ?? 'bulanan';
        if($periode == 'harian') {
            $pemasukan = $pdo->query("SELECT SUM(total_harga) as total FROM penjualan WHERE DATE(tanggal) = CURDATE()")->fetch()['total'] ?? 0;
            $pengeluaran = $pdo->query("SELECT SUM(jumlah) as total FROM pengeluaran WHERE DATE(tanggal) = CURDATE()")->fetch()['total'] ?? 0;
            $terlaris = $pdo->query("SELECT b.nama_barang, d.opsi_terpakai, SUM(d.jumlah) as terjual FROM detail_penjualan d JOIN barang b ON d.barang_id = b.id JOIN penjualan p ON d.penjualan_id = p.id WHERE DATE(p.tanggal) = CURDATE() GROUP BY b.id, d.opsi_terpakai ORDER BY terjual DESC LIMIT 10")->fetchAll();
            $tidakLaku = $pdo->query("SELECT b.nama_barang, d.opsi_terpakai, SUM(d.jumlah) as terjual FROM detail_penjualan d JOIN barang b ON d.barang_id = b.id JOIN penjualan p ON d.penjualan_id = p.id WHERE DATE(p.tanggal) = CURDATE() GROUP BY b.id, d.opsi_terpakai ORDER BY terjual ASC LIMIT 5")->fetchAll();
        } elseif($periode == 'mingguan') {
            $pemasukan = $pdo->query("SELECT SUM(total_harga) as total FROM penjualan WHERE YEARWEEK(tanggal) = YEARWEEK(NOW())")->fetch()['total'] ?? 0;
            $pengeluaran = $pdo->query("SELECT SUM(jumlah) as total FROM pengeluaran WHERE YEARWEEK(tanggal) = YEARWEEK(NOW())")->fetch()['total'] ?? 0;
            $terlaris = $pdo->query("SELECT b.nama_barang, d.opsi_terpakai, SUM(d.jumlah) as terjual FROM detail_penjualan d JOIN barang b ON d.barang_id = b.id JOIN penjualan p ON d.penjualan_id = p.id WHERE YEARWEEK(p.tanggal) = YEARWEEK(NOW()) GROUP BY b.id, d.opsi_terpakai ORDER BY terjual DESC LIMIT 10")->fetchAll();
            $tidakLaku = $pdo->query("SELECT b.nama_barang, d.opsi_terpakai, SUM(d.jumlah) as terjual FROM detail_penjualan d JOIN barang b ON d.barang_id = b.id JOIN penjualan p ON d.penjualan_id = p.id WHERE YEARWEEK(p.tanggal) = YEARWEEK(NOW()) GROUP BY b.id, d.opsi_terpakai ORDER BY terjual ASC LIMIT 5")->fetchAll();
        } else {
            $pemasukan = $pdo->query("SELECT SUM(total_harga) as total FROM penjualan WHERE MONTH(tanggal) = MONTH(CURDATE())")->fetch()['total'] ?? 0;
            $pengeluaran = $pdo->query("SELECT SUM(jumlah) as total FROM pengeluaran WHERE MONTH(tanggal) = MONTH(CURDATE())")->fetch()['total'] ?? 0;
            $terlaris = $pdo->query("SELECT b.nama_barang, d.opsi_terpakai, SUM(d.jumlah) as terjual FROM detail_penjualan d JOIN barang b ON d.barang_id = b.id JOIN penjualan p ON d.penjualan_id = p.id WHERE MONTH(p.tanggal) = MONTH(CURDATE()) GROUP BY b.id, d.opsi_terpakai ORDER BY terjual DESC LIMIT 10")->fetchAll();
            $tidakLaku = $pdo->query("SELECT b.nama_barang, d.opsi_terpakai, SUM(d.jumlah) as terjual FROM detail_penjualan d JOIN barang b ON d.barang_id = b.id JOIN penjualan p ON d.penjualan_id = p.id WHERE MONTH(p.tanggal) = MONTH(CURDATE()) GROUP BY b.id, d.opsi_terpakai ORDER BY terjual ASC LIMIT 5")->fetchAll();
        }
        $pelangganTerbaik = $pdo->query("SELECT nama_pelanggan, total_belanja FROM pelanggan WHERE nama_pelanggan != 'Umum' ORDER BY total_belanja DESC LIMIT 10")->fetchAll();
        $laba = $pemasukan - $pengeluaran;
    ?>
        <h2><i class="fas fa-chart-line text-gradient"></i> Laporan Lengkap</h2>
        <div class="card"><div class="mb-3"><a href="?page=laporan&periode=harian" class="btn btn-primary btn-sm">Harian</a> <a href="?page=laporan&periode=mingguan" class="btn btn-primary btn-sm">Mingguan</a> <a href="?page=laporan&periode=bulanan" class="btn btn-primary btn-sm">Bulanan</a></div>
        <div class="row"><div class="col-md-4"><div class="alert alert-success"><h6><i class="fas fa-arrow-up"></i> Pemasukan</h6><h4><?= rupiah($pemasukan) ?></h4></div></div><div class="col-md-4"><div class="alert alert-danger"><h6><i class="fas fa-arrow-down"></i> Pengeluaran</h6><h4><?= rupiah($pengeluaran) ?></h4></div></div><div class="col-md-4"><div class="alert <?= $laba >= 0 ? 'alert-success' : 'alert-danger' ?>"><h6><i class="fas fa-chart-line"></i> Laba/Rugi</h6><h4><?= rupiah($laba) ?></h4></div></div></div></div>
<<!-- BARANG TERLARIS & TIDAK LAKU SEJAJAR PAKAI TABLE -->
<table width="100%" cellpadding="10">
    <tr>
        <td width="50%" valign="top">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">🔥 BARANG TERLARIS</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr><th>Barang</th><th>Opsi</th><th>Terjual</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($terlaris as $t): ?>
                            <tr>
                                <td><?= $t['nama_barang'] ?></td>
                                <td><?= $t['opsi_terpakai'] ?></td>
                                <td><?= $t['terjual'] ?> pcs</pcs></pcs></?= ?>\n                                </tr>\n                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </td>
        <td width="50%" valign="top">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">❄️ BARANG TIDAK LAKU</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr><th>Barang</th><th>Opsi</th><th>Terjual</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($tidakLaku as $t): ?>
                            <tr>
                                <td><?= $t['nama_barang'] ?></td>
                                <td><?= $t['opsi_terpakai'] ?></td>
                                <td><?= $t['terjual'] ?> pcs</pcs></pcs></?= ?>\n                                </tr>\n                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </td>
    </tr>
</table>
        <div class="card"><h5>Pelanggan Terbaik</h5><table class="table"><thead><tr><th>Nama Pelanggan</th><th>Total Belanja</th></tr></thead><tbody><?php foreach($pelangganTerbaik as $p): ?><tr><td><?= $p['nama_pelanggan'] ?></td><td><?= rupiah($p['total_belanja']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    
 <?php
// ==================== KAS WARUNG ====================
elseif($page == 'kas'):
    $kas = $pdo->query("SELECT * FROM kas_warung ORDER BY tanggal DESC, id DESC LIMIT 50")->fetchAll();
    $saldo = $pdo->query("SELECT saldo_akhir FROM kas_warung ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0;
?>
    <h2><i class="fas fa-money-bill-wave text-gradient"></i> Kas Warung</h2>
    
    <div class="alert alert-info">💰 Saldo Kas Saat Ini: <strong><?= rupiah($saldo) ?></strong></div>
    
    <!-- FORM PEMASUKAN & PENGELUARAN SEJAJAR 50:50 -->
    <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 25px;">
        <div style="flex: 1; min-width: 300px;">
            <div class="card" style="height: 100%;">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Tambah Pemasukan</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label>Keterangan</label>
                            <input type="text" name="keterangan" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Jumlah (Rp)</label>
                            <input type="number" name="jumlah" class="form-control" required>
                        </div>
                        <input type="hidden" name="jenis_kas" value="pemasukan">
                        <button type="submit" name="tambah_kas" class="btn btn-success w-100">
                            <i class="fas fa-save"></i> Simpan Pemasukan
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div style="flex: 1; min-width: 300px;">
            <div class="card" style="height: 100%;">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-minus-circle"></i> Tambah Pengeluaran</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label>Keterangan</label>
                            <input type="text" name="keterangan" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Jumlah (Rp)</label>
                            <input type="number" name="jumlah" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Jenis Pengeluaran</label>
                            <select name="jenis" class="form-control">
                                <option value="beli_stok">Beli Stok Barang</option>
                                <option value="operasional">Operasional</option>
                                <option value="gaji_karyawan">Gaji Karyawan</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                        </div>
                        <input type="hidden" name="jenis_kas" value="pengeluaran">
                        <button type="submit" name="tambah_pengeluaran" class="btn btn-danger w-100">
                            <i class="fas fa-save"></i> Simpan Pengeluaran
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- RIWAYAT KAS (FULL WIDTH) -->
    <div class="card">
        <h5><i class="fas fa-history"></i> Riwayat Kas</h5>
        <hr>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th>Jumlah</th><th>Saldo Akhir</th></tr>
                </thead>
                <tbody>
                    <?php foreach($kas as $k): ?>
                    <tr class="<?= $k['jenis'] == 'pemasukan' ? 'text-success' : 'text-danger' ?>">
                        <td><?= date('d/m/Y', strtotime($k['tanggal'])) ?></td>
                        <td><?= $k['jenis'] == 'pemasukan' ? '➕ Pemasukan' : '➖ Pengeluaran' ?></td>
                        <td><?= $k['keterangan'] ?></td>
                        <td><?= rupiah($k['jumlah']) ?></td>
                        <td><?= rupiah($k['saldo_akhir']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php
// ==================== PROFIL TOKO ====================
elseif($page == 'profil'):
    $profil = getProfil($pdo);
?>
    <h2><i class="fas fa-building text-gradient"></i> Profil Toko</h2>
    
    <!-- EDIT PROFIL & LOGO SEJAJAR 50:50 -->
    <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 25px;">
        <div style="flex: 1; min-width: 300px;">
            <div class="card" style="height: 100%;">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Profil Toko</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label>Nama Toko</label>
                            <input type="text" name="nama_toko" class="form-control" value="<?= htmlspecialchars($profil['nama_toko'] ?? 'Warung Pondok') ?>">
                        </div>
                        <div class="form-group">
                            <label>Alamat</label>
                            <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($profil['alamat'] ?? '') ?></textarea>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <div style="flex: 1;">
                                <div class="form-group">
                                    <label>No Telepon</label>
                                    <input type="text" name="no_telp" class="form-control" value="<?= htmlspecialchars($profil['no_telp'] ?? '') ?>">
                                </div>
                            </div>
                            <div style="flex: 1;">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($profil['email'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Footer Struk (Ucapan Terima Kasih)</label>
                            <textarea name="footer_text" class="form-control" rows="2"><?= htmlspecialchars($profil['footer_text'] ?? 'Terima kasih atas kunjungan Anda') ?></textarea>
                        </div>
                        <button type="submit" name="update_profil" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> Simpan Profil
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div style="flex: 1; min-width: 300px;">
            <div class="card" style="height: 100%; text-align: center;">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-image"></i> Logo Toko</h5>
                </div>
                <div class="card-body">
                    <?php if($profil && !empty($profil['logo']) && file_exists($profil['logo'])): ?>
                        <img src="<?= $profil['logo'] ?>" style="width: 120px; height: 120px; object-fit: cover; border-radius: 15px; margin-bottom: 15px;">
                    <?php else: ?>
                        <div style="width:120px;height:120px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:20px;margin:0 auto 15px auto;display:flex;align-items:center;justify-content:center">
                            <i class="fas fa-store fa-4x text-white"></i>
                        </div>
                    <?php endif; ?>
                    
                    <form id="logoForm" enctype="multipart/form-data">
                        <input type="file" id="logo_file" name="logo" accept="image/*" style="display:none" onchange="uploadLogo()">
                        <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('logo_file').click()">
                            <i class="fas fa-upload"></i> Upload Logo
                        </button>
                    </form>
                    
                    <hr>
                    <h6>Preview Struk</h6>
                    <div class="struk" style="border:1px solid #ddd;padding:15px;border-radius:12px;background:#f9f9f9;margin-top:10px;text-align:left;">
                        <h6 class="text-center"><?= htmlspecialchars($profil['nama_toko'] ?? 'Warung Pondok') ?></h6>
                        <p class="text-center small"><?= nl2br(htmlspecialchars(substr($profil['alamat'] ?? '', 0, 50))) ?><br>Telp: <?= $profil['no_telp'] ?? '-' ?></p>
                        <hr>
                        <p class="small">Barang Contoh.....</p>
                        <p class="small">Total: Rp 10.000</p>
                        <hr>
                        <p class="text-center small"><?= nl2br(htmlspecialchars(substr($profil['footer_text'] ?? 'Terima kasih', 0, 50))) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function uploadLogo(){
        var fd = new FormData();
        fd.append('logo', $('#logo_file')[0].files[0]);
        $.ajax({
            url: '?ajax=upload_logo',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(r){
                var res = JSON.parse(r);
                if(res.success) location.reload();
                else alert('Gagal upload logo!');
            }
        });
    }
    </script>

<?php
    // ==================== RESET DATA ====================
    elseif($page == 'reset_data' && isAdmin()):
        $jml_penjualan = $pdo->query("SELECT COUNT(*) FROM penjualan")->fetchColumn();
        $jml_detail = $pdo->query("SELECT COUNT(*) FROM detail_penjualan")->fetchColumn();
    ?>
        <h2><i class="fas fa-database text-gradient"></i> Reset Data</h2>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <strong>Peringatan!</strong> Fitur ini akan menghapus semua data transaksi.</div>
        <div class="row"><div class="col-md-6"><div class="card"><h5>Status Data</h5><table class="table"><tr><th>Transaksi Penjualan</th><td><?= $jml_penjualan ?></td></tr><tr><th>Detail Penjualan</th><td><?= $jml_detail ?></td></tr></table></div></div>
        <div class="col-md-6"><div class="card"><h5>Reset Data Transaksi</h5><form method="POST" onsubmit="return confirm('Yakin hapus semua data?')"><button type="submit" name="reset_data" class="btn btn-danger">Reset Semua Data</button></form><hr><h5>Reset Stok Barang</h5><form method="POST" onsubmit="return confirm('Yakin reset stok?')"><div class="form-group"><label>Stok Baru</label><input type="number" name="stok_baru" class="form-control" value="0"></div><button type="submit" name="reset_stok" class="btn btn-warning">Reset Stok</button></form></div></div></div>
    
    <?php
    // ==================== GANTI PASSWORD ====================
    elseif($page == 'ganti_password'):
    ?>
        <h2><i class="fas fa-key text-gradient"></i> Ganti Password</h2>
        <div class="row"><div class="col-md-6"><div class="card"><form method="POST"><div class="form-group"><label>Password Lama</label><input type="password" name="password_lama" class="form-control" required></div><div class="form-group"><label>Password Baru</label><input type="password" name="password_baru" class="form-control" required></div><div class="form-group"><label>Konfirmasi Password</label><input type="password" name="konfirmasi" class="form-control" required></div><button type="submit" name="ganti_password" class="btn btn-primary">Ganti Password</button> <a href="?page=dashboard" class="btn btn-secondary">Kembali</a></form></div></div><div class="col-md-6"><div class="card text-center"><i class="fas fa-shield-alt fa-4x text-gradient mb-3"></i><h5>Tips Keamanan</h5><p class="text-muted small">Gunakan password yang kuat dan jangan bagikan ke siapa pun.</p></div></div></div>
    <?php endif; ?>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function lihatDetail(id) { $.get('?ajax=detail_transaksi&id=' + id, function(data) { $('#detailContent').html(data); $('#detailModal').show(); }); }
function confirmLogout(e) { e.preventDefault(); if(confirm('Yakin ingin logout? 🚪')) window.location.href = '?logout=1'; }
setTimeout(function() { $('.alert').fadeOut('slow'); }, 5000);
</script>

<?php endif; ?>
</body>
</html>