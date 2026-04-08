<?php
// Include konfigurasi database
require_once '../config.php';
// Cek login dan start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['login_petugas']) || $_SESSION['level'] != 'user') {
    header("Location: ../login.php");
    exit();
}

// Ambil koneksi
$koneksi = getKoneksi();
$id_user = $_SESSION['id_petugas'];
$nama_user = $_SESSION['nama_petugas'];

// === LOGOUT ===
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// === HANDLE ACTION ===
// 1. Add to Cart
if (isset($_POST['add_to_cart'])) {
    $id_produk = $_POST['id_produk'];
    $qty = $_POST['qty'];
    
    $produk = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM produk WHERE id = '$id_produk'"));
    
    if ($produk) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        if (isset($_SESSION['cart'][$id_produk])) {
            $_SESSION['cart'][$id_produk]['qty'] += $qty;
        } else {
            $_SESSION['cart'][$id_produk] = [
                'id' => $produk['id'],
                'nama' => $produk['nama_produk'],
                'harga' => $produk['harga'],
                'qty' => $qty,
                'deskripsi' => $produk['deskripsi'] ?? '',
                'gambar' => $produk['gambar'] ?? 'default.jpg'
            ];
        }
        header("Location: dashboard_user.php?page=cart");
        exit();
    }
}

// 2. Buy Now (Add to Cart + Redirect to Checkout)
if (isset($_POST['buy_now'])) {
    $id_produk = $_POST['id_produk'];
    $qty = $_POST['qty'];
    
    $produk = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM produk WHERE id = '$id_produk'"));
    
    if ($produk) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Reset cart dulu untuk buy now
        $_SESSION['cart'] = [];
        
        $_SESSION['cart'][$id_produk] = [
            'id' => $produk['id'],
            'nama' => $produk['nama_produk'],
            'harga' => $produk['harga'],
            'qty' => $qty,
            'deskripsi' => $produk['deskripsi'] ?? '',
            'gambar' => $produk['gambar'] ?? 'default.jpg'
        ];
        
        // Hitung total
        $total = $produk['harga'] * $qty;
        header("Location: dashboard_user.php?page=checkout&total=".$total);
        exit();
    }
}

// 3. Update Cart
if (isset($_POST['update_cart'])) {
    if (isset($_POST['qty']) && is_array($_POST['qty'])) {
        foreach ($_POST['qty'] as $id => $quantity) {
            if ($quantity > 0 && isset($_SESSION['cart'][$id])) {
                $_SESSION['cart'][$id]['qty'] = $quantity;
            }
        }
    }
    header("Location: dashboard_user.php?page=cart");
    exit();
}

// 4. Remove from Cart
if (isset($_GET['remove'])) {
    $remove_id = $_GET['remove'];
    if (isset($_SESSION['cart'][$remove_id])) {
        unset($_SESSION['cart'][$remove_id]);
    }
    header("Location: dashboard_user.php?page=cart");
    exit();
}

// 5. Clear Cart
if (isset($_GET['clear_cart'])) {
    unset($_SESSION['cart']);
    header("Location: dashboard_user.php?page=cart");
    exit();
}

// 6. Checkout Process
if (isset($_POST['checkout'])) {
    $alamat = $_POST['alamat'];
    $metode_pembayaran = $_POST['metode_pembayaran'];
    $total_harga = $_POST['total_harga'];
    $nama_penerima = $_POST['nama_penerima'];
    $telepon = $_POST['telepon'];
    
    if (!empty($_SESSION['cart'])) {
        $no_invoice = "INV-" . date('Ymd') . "-" . rand(1000, 9999);
        $tgl_pesanan = date('Y-m-d H:i:s');
        $status = 'belum_bayar';
        
        // Insert ke tabel pesanan
        $query_pesanan = "INSERT INTO pesanan (nomor_pesanan, id_pelanggan, tanggal_pesanan, total_harga, status_pesanan, status_pembayaran, metode_pembayaran, alamat_pengiriman, nama_penerima, telepon_penerima) 
                          VALUES ('$no_invoice', '$id_user', '$tgl_pesanan', '$total_harga', 'pending', '$status', '$metode_pembayaran', '".escape($alamat)."', '".escape($nama_penerima)."', '".escape($telepon)."')";
        
        if (mysqli_query($koneksi, $query_pesanan)) {
            $id_pesanan = mysqli_insert_id($koneksi);
            
            // Insert detail pesanan
            foreach ($_SESSION['cart'] as $item) {
                $subtotal = $item['harga'] * $item['qty'];
                mysqli_query($koneksi, "INSERT INTO detail_pesanan (id_pesanan, id_produk, jumlah, subtotal) 
                                        VALUES ('$id_pesanan', '{$item['id']}', '{$item['qty']}', '$subtotal')");
            }
            
            // Kosongkan cart
            unset($_SESSION['cart']);
            header("Location: dashboard_user.php?page=invoice&id=$id_pesanan");
            exit();
        } else {
            $error_checkout = "Gagal memproses pesanan: " . mysqli_error($koneksi);
        }
    }
}

// === AMBIL DATA UNTUK DASHBOARD ===
$total_orders = mysqli_num_rows(mysqli_query($koneksi, "SELECT id FROM pesanan WHERE id_pelanggan = '$id_user'"));
$total_spent = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT SUM(total_harga) as total FROM pesanan WHERE id_pelanggan = '$id_user' AND status_pembayaran = 'lunas'"))['total'] ?? 0;

// Halaman aktif
$active_page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Deteksi jika sedang di halaman profile_user.php (untuk highlight menu)
if (basename($_SERVER['PHP_SELF']) == 'profile_user.php') {
    $active_page = 'profile';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard User - E Commerce</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f5f5f5;
    color: #333;
}
.main-container {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR */
.sidebar {
    width: 260px;
    background: linear-gradient(180deg, #9CAF88 0%, #8A9B76 100%);
    padding: 25px 20px;
    display: flex;
    flex-direction: column;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
}
.sidebar-header {
    text-align: center;
    padding: 15px 0;
    margin-bottom: 25px;
    border-bottom: 2px solid rgba(255,255,255,0.3);
}
.sidebar-title {
    color: white;
    font-size: 20px;
    font-weight: 700;
    letter-spacing: 0.5px;
}
.menu-item {
    background-color: rgba(255,255,255,0.9);
    padding: 14px 18px;
    margin-bottom: 12px;
    border-radius: 8px;
    text-align: left;
    cursor: pointer;
    font-weight: 500;
    color: #333;
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.menu-item:hover {
    background-color: white;
    transform: translateX(4px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.menu-item.active {
    background-color: white;
    color: #9CAF88;
    font-weight: 700;
    border-left: 4px solid #9CAF88;
}
.menu-item svg {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
}
.logout-section {
    margin-top: auto;
    padding-top: 20px;
    border-top: 2px solid rgba(255,255,255,0.3);
}
.logout-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 18px;
    background-color: rgba(255,255,255,0.2);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: background-color 0.2s;
}
.logout-btn:hover {
    background-color: rgba(255,255,255,0.3);
}
.logout-btn svg {
    width: 18px;
    height: 18px;
    stroke: white;
    fill: none;
    stroke-width: 2;
}

/* MAIN CONTENT */
.main-content {
    flex: 1;
    margin-left: 260px;
    padding: 30px;
    background-color: white;
    min-height: 100vh;
}
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 35px;
    padding-bottom: 15px;
    border-bottom: 6px solid #9CAF88;
}
.header-title {
    font-size: 22px;
    font-weight: 700;
    color: #333;
}

/* USER INFO & PROFILE LINK - TOP RIGHT */
.user-info {
    display: flex;
    align-items: center;
    gap: 15px;
}
.user-avatar {
    width: 40px;
    height: 40px;
    background-color: #9CAF88;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
}
.user-name {
    color: #666;
    font-size: 14px;
}
.profile-link {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background-color: #f0f0f0;
    border-radius: 20px;
    text-decoration: none;
    color: #333;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s ease;
    border: 1px solid #ddd;
}
.profile-link:hover {
    background-color: #9CAF88;
    color: white;
    border-color: #9CAF88;
}
.profile-link svg {
    width: 16px;
    height: 16px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
}
.profile-link.active {
    background-color: #9CAF88;
    color: white;
    border-color: #9CAF88;
}

/* PRODUCT GRID */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
}
.product-card {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    transition: transform 0.2s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}
.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.product-img {
    width: 100%;
    height: 180px;
    object-fit: cover;
    border-radius: 8px;
    background: #eee;
    margin-bottom: 10px;
}
.product-title {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 5px;
    height: 40px;
    overflow: hidden;
}
.product-desc {
    font-size: 12px;
    color: #666;
    margin-bottom: 8px;
    height: 35px;
    overflow: hidden;
}
.product-price {
    color: #9CAF88;
    font-weight: 700;
    font-size: 16px;
    margin-bottom: 10px;
}
.btn-group {
    display: flex;
    gap: 8px;
}
.btn-add {
    background: #9CAF88;
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 5px;
    cursor: pointer;
    flex: 1;
    font-weight: 600;
    font-size: 13px;
}
.btn-add:hover {
    background: #8A9B76;
}
.btn-buy {
    background: #FF6B6B;
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 5px;
    cursor: pointer;
    flex: 1;
    font-weight: 600;
    font-size: 13px;
}
.btn-buy:hover {
    background: #EE5A5A;
}

/* CART & CHECKOUT */
.cart-table, .order-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}
.cart-table th, .cart-table td, .order-table th, .order-table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    text-align: left;
}
.cart-table th {
    background: #f9f9f9;
    color: #555;
}
.btn-checkout {
    background: #9CAF88;
    color: white;
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    float: right;
}
.btn-checkout:hover {
    background: #8A9B76;
}
.btn-remove {
    background: #f44336;
    color: white;
    padding: 6px 12px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 12px;
    text-decoration: none;
}
.btn-remove:hover {
    background: #d32f2f;
}
.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}
.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    border-color: #9CAF88;
    outline: none;
}

/* INVOICE */
.invoice-box {
    border: 1px solid #eee;
    padding: 30px;
    max-width: 800px;
    margin: 0 auto;
    background: #fff;
}
.invoice-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
    border-bottom: 2px solid #9CAF88;
    padding-bottom: 10px;
}
.invoice-title {
    font-size: 24px;
    font-weight: 700;
    color: #333;
}
.btn-print {
    background: #333;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    margin-top: 20px;
}
.btn-print:hover {
    background: #555;
}

/* STATS */
.stats-row {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}
.stat-card {
    background-color: #E0E0E0;
    padding: 22px 25px;
    border-radius: 12px;
    min-width: 170px;
    flex: 1;
    max-width: 220px;
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #333;
}
.stat-label {
    font-size: 13px;
    color: #333;
    font-weight: 500;
}

/* Responsive */
@media (max-width: 768px) {
    .main-container {
        flex-direction: column;
    }
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
        padding: 15px;
    }
    .main-content {
        margin-left: 0;
        padding: 20px;
    }
    .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    .user-info {
        width: 100%;
        justify-content: space-between;
    }
    .product-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .btn-group {
        flex-direction: column;
    }
}

/* Print Style */
@media print {
    .sidebar, .header, .btn-print, .no-print, .profile-link {
        display: none;
    }
    .main-content {
        margin: 0;
        padding: 0;
    }
    .invoice-box {
        border: none;
    }
}
</style>
</head>
<body>
<div class="main-container">
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h1 class="sidebar-title">Toko Online</h1>
        </div>
        <a href="dashboard_user.php?page=home" class="menu-item <?= $active_page=='home'?'active':'' ?>">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            Produk
        </a>
        <a href="dashboard_user.php?page=cart" class="menu-item <?= $active_page=='cart'?'active':'' ?>">
            <svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
            Keranjang (<span id="cart-count"><?= isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0 ?></span>)
        </a>
        <a href="dashboard_user.php?page=orders" class="menu-item <?= $active_page=='orders'?'active':'' ?>">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            Riwayat Pesanan
        </a>
        <!-- Profile dipindah ke header, tidak ada di sidebar lagi -->
        <div class="logout-section">
            <a href="../auth/login.php" class="logout-btn">
                <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                Logout
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="header">
            <h2 class="header-title">
                <?php 
                if($active_page == 'home') echo "Katalog Produk";
                elseif($active_page == 'cart') echo "Keranjang Belanja";
                elseif($active_page == 'checkout') echo "Checkout";
                elseif($active_page == 'orders') echo "Riwayat Pesanan";
                elseif($active_page == 'invoice') echo "Invoice";
                elseif($active_page == 'profile') echo "Profile Saya";
                else echo "Dashboard";
                ?>
            </h2>
            
            <!-- USER INFO + PROFILE LINK (POJOK KANAN ATAS) -->
            <div class="user-info">
                <a href="profile_user.php" class="profile-link <?= $active_page=='profile'?'active':'' ?>" title="Buka Profile">
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    Profile
                </a>
                <div class="user-avatar"><?= strtoupper(substr($nama_user, 0, 1)) ?></div>
                <span class="user-name"><?= htmlspecialchars($nama_user) ?></span>
            </div>
        </div>

        <?php if ($active_page == 'home'): ?>
            <!-- HALAMAN PRODUK -->
            <div class="stats-row">
                <div class="stat-card">
                    <span class="stat-label">Total Pesanan</span>
                    <div class="stat-value"><?= $total_orders ?></div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Total Belanja</span>
                    <div class="stat-value">Rp <?= number_format($total_spent, 0, ',', '.') ?></div>
                </div>
            </div>
            
            <div class="product-grid">
                <?php
                $produk_query = mysqli_query($koneksi, "SELECT * FROM produk ORDER BY id DESC");
                while($p = mysqli_fetch_assoc($produk_query)):
                ?>
                <div class="product-card">
                    <img src="../uploads/<?= $p['gambar'] ?? 'default.jpg' ?>" alt="<?= htmlspecialchars($p['nama_produk']) ?>" class="product-img">
                    <div class="product-title"><?= htmlspecialchars($p['nama_produk']) ?></div>
                    <div class="product-desc"><?= htmlspecialchars(substr($p['deskripsi'] ?? '', 0, 50)) ?>...</div>
                    <div class="product-price">Rp <?= number_format($p['harga'], 0, ',', '.') ?></div>
                    <form method="POST">
                        <input type="hidden" name="id_produk" value="<?= $p['id'] ?>">
                        <input type="hidden" name="qty" value="1">
                        <div class="btn-group">
                            <button type="submit" name="add_to_cart" class="btn-add">🛒 Add to Cart</button>
                            <button type="submit" name="buy_now" class="btn-buy">⚡ Buy Now</button>
                        </div>
                    </form>
                </div>
                <?php endwhile; ?>
            </div>

        <?php elseif ($active_page == 'cart'): ?>
            <!-- HALAMAN CART -->
            <?php if (empty($_SESSION['cart'])): ?>
                <p>Keranjang Anda kosong.</p>
                <a href="?page=home" style="color:#9CAF88;">Kembali Belanja</a>
            <?php else: ?>
                <form method="POST">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Harga</th>
                                <th>Qty</th>
                                <th>Subtotal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grand_total = 0;
                            foreach ($_SESSION['cart'] as $id => $item): 
                                $subtotal = $item['harga'] * $item['qty'];
                                $grand_total += $subtotal;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($item['nama']) ?></td>
                                <td>Rp <?= number_format($item['harga'], 0, ',', '.') ?></td>
                                <td><input type="number" name="qty[<?= $id ?>]" value="<?= $item['qty'] ?>" min="1" style="width:60px;"></td>
                                <td>Rp <?= number_format($subtotal, 0, ',', '.') ?></td>
                                <td>
                                    <a href="?page=cart&remove=<?= $id ?>" class="btn-remove" onclick="return confirm('Hapus produk ini dari keranjang?')">🗑 Hapus</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="overflow:hidden; margin-top:20px;">
                        <h3 style="float:left;">Total: Rp <?= number_format($grand_total, 0, ',', '.') ?></h3>
                        <button type="submit" name="update_cart" style="background:#666; color:white; padding:10px 20px; border:none; border-radius:5px; cursor:pointer; margin-right:10px;">Update Cart</button>
                        <a href="?clear_cart=true" style="background:#f44336; color:white; padding:10px 20px; border-radius:5px; text-decoration:none; margin-right:10px; display:inline-block;">Clear Cart</a>
                        <button type="button" onclick="location.href='?page=checkout&total=<?= $grand_total ?>'" class="btn-checkout">Lanjut Checkout</button>
                    </div>
                </form>
            <?php endif; ?>

        <?php elseif ($active_page == 'checkout'): ?>
            <!-- HALAMAN CHECKOUT -->
            <form method="POST" style="max-width: 600px;">
                <div class="form-group">
                    <label>Nama Penerima</label>
                    <input type="text" name="nama_penerima" required value="<?= htmlspecialchars($nama_user) ?>">
                </div>
                <div class="form-group">
                    <label>Nomor Telepon</label>
                    <input type="text" name="telepon" required placeholder="08xxxxxxxxxx">
                </div>
                <div class="form-group">
                    <label>Alamat Pengiriman</label>
                    <textarea name="alamat" rows="4" required placeholder="Jalan, Nomor Rumah, Kota, Kode Pos"></textarea>
                </div>
                <div class="form-group">
                    <label>Metode Pembayaran</label>
                    <select name="metode_pembayaran" required>
                        <option value="Transfer Bank">Transfer Bank</option>
                        <option value="E-Wallet">E-Wallet (GoPay/OVO/Dana)</option>
                        <option value="COD">Cash On Delivery (COD)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Total Bayar</label>
                    <input type="text" value="Rp <?= number_format($_GET['total'], 0, ',', '.') ?>" readonly style="background:#eee; font-weight:bold;">
                    <input type="hidden" name="total_harga" value="<?= $_GET['total'] ?>">
                </div>
                <button type="submit" name="checkout" class="btn-checkout" style="float:none; width:100%;">Buat Pesanan</button>
            </form>

        <?php elseif ($active_page == 'orders'): ?>
            <!-- HALAMAN RIWAYAT PESANAN -->
            <table class="order-table">
                <thead>
                    <tr>
                        <th>No Invoice</th>
                        <th>Tanggal</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $orders = mysqli_query($koneksi, "SELECT * FROM pesanan WHERE id_pelanggan = '$id_user' ORDER BY id DESC");
                    while($o = mysqli_fetch_assoc($orders)):
                    ?>
                    <tr>
                        <td><?= $o['nomor_pesanan'] ?></td>
                        <td><?= $o['tanggal_pesanan'] ?></td>
                        <td>Rp <?= number_format($o['total_harga'], 0, ',', '.') ?></td>
                        <td>
                            <span style="padding:4px 8px; border-radius:4px; background:<?= $o['status_pembayaran']=='lunas'?'#d4edda':'#fff3cd' ?>; color:<?= $o['status_pembayaran']=='lunas'?'#155724':'#856404' ?>; font-size:12px;">
                                <?= $o['status_pembayaran'] ?>
                            </span>
                        </td>
                        <td>
                            <a href="?page=invoice&id=<?= $o['id'] ?>" style="color:#9CAF88; font-weight:600;">Lihat Invoice</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

        <?php elseif ($active_page == 'invoice' && isset($_GET['id'])): ?>
            <!-- HALAMAN INVOICE (PDF READY) -->
            <?php
            $id_pesanan = $_GET['id'];
            $inv = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM pesanan WHERE id = '$id_pesanan' AND id_pelanggan = '$id_user'"));
            if ($inv):
                $details = mysqli_query($koneksi, "SELECT dp.*, p.nama_produk, p.deskripsi FROM detail_pesanan dp JOIN produk p ON dp.id_produk = p.id WHERE dp.id_pesanan = '$id_pesanan'");
            ?>
            <div class="invoice-box">
                <div class="invoice-header">
                    <div class="invoice-title">INVOICE / BUKTI PEMBAYARAN</div>
                    <div style="text-align:right;">
                        <div><?= $inv['nomor_pesanan'] ?></div>
                        <div style="font-size:12px; color:#666;"><?= $inv['tanggal_pesanan'] ?></div>
                    </div>
                </div>
                <div style="margin-bottom:20px;">
                    <strong>Dikirim Kepada:</strong><br>
                    <?= htmlspecialchars($inv['nama_penerima']) ?><br>
                    <?= htmlspecialchars($inv['telepon_penerima']) ?><br>
                    <?= nl2br(htmlspecialchars($inv['alamat_pengiriman'])) ?>
                </div>
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Deskripsi</th>
                            <th>Qty</th>
                            <th>Harga</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($d = mysqli_fetch_assoc($details)): ?>
                        <tr>
                            <td><?= htmlspecialchars($d['nama_produk']) ?></td>
                            <td><?= htmlspecialchars(substr($d['deskripsi'] ?? '', 0, 30)) ?>...</td>
                            <td><?= $d['jumlah'] ?></td>
                            <td>Rp <?= number_format($d['subtotal'] / $d['jumlah'], 0, ',', '.') ?></td>
                            <td>Rp <?= number_format($d['subtotal'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <tr>
                            <td colspan="4" style="text-align:right; font-weight:700;">Total</td>
                            <td style="font-weight:700;">Rp <?= number_format($inv['total_harga'], 0, ',', '.') ?></td>
                        </tr>
                    </tbody>
                </table>
                <div style="margin-top:20px; padding-top:10px; border-top:1px solid #eee;">
                    <strong>Metode Pembayaran:</strong> <?= $inv['metode_pembayaran'] ?><br>
                    <strong>Status:</strong> <?= $inv['status_pembayaran'] ?><br>
                    <strong>Status Pesanan:</strong> <?= $inv['status_pesanan'] ?>
                </div>
                <div style="text-align:center; margin-top:30px;" class="no-print">
                    <button onclick="window.print()" class="btn-print">Download / Print PDF</button>
                    <a href="?page=orders" style="display:inline-block; margin-top:10px; color:#666;">Kembali</a>
                </div>
            </div>
            <?php else: ?>
                <p>Invoice tidak ditemukan.</p>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>
</body>
</html>