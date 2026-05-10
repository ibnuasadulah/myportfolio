# GameVault — Marketplace Item & Voucher Game

Website marketplace untuk jual beli item & voucher game dengan pembayaran Rupiah via ShopeePay, QRIS, dan transfer bank.

---

## Struktur File

```
gamemarket/
├── index.html              # Halaman utama
├── checkout.html           # Halaman checkout
├── assets/
│   ├── css/style.css       # Stylesheet utama
│   └── js/main.js          # JavaScript utama
├── api/
│   ├── products.php        # API daftar produk
│   ├── checkout.php        # API buat order + payment
│   ├── auth.php            # API login/register
│   └── payment-notify.php  # Webhook Midtrans
├── core/
│   └── DB.php              # Koneksi database (PDO)
├── config/
│   └── config.php          # Konfigurasi app & API keys
└── database/
    └── schema.sql          # Skema database MySQL
```

---

## Cara Setup

### 1. Persyaratan
- PHP 8.1+
- MySQL 8.0+
- Web server: Apache/Nginx (XAMPP/Laragon untuk lokal)
- Akun Midtrans (gratis untuk sandbox)

### 2. Import Database
```sql
mysql -u root -p < database/schema.sql
```

### 3. Konfigurasi
Edit `config/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'gamevault');
define('DB_USER', 'root');
define('DB_PASS', 'password_kamu');

// Midtrans
define('MIDTRANS_SERVER_KEY', 'SB-Mid-server-xxxxx'); // dari dashboard Midtrans
define('MIDTRANS_CLIENT_KEY', 'SB-Mid-client-xxxxx');
define('MIDTRANS_IS_PRODUCTION', false); // ubah ke true untuk live
```

### 4. Setup Midtrans (untuk ShopeePay)
1. Daftar di https://midtrans.com
2. Di dashboard → Settings → Payment Methods → aktifkan **ShopeePay** dan **QRIS**
3. Salin **Server Key** dan **Client Key** ke `config.php`
4. Daftarkan webhook URL di Midtrans:
   `https://yourdomain.com/api/payment-notify.php`

### 5. Update Client Key di checkout.html
```html
<script src="https://app.sandbox.midtrans.com/snap/snap.js"
        data-client-key="SB-Mid-client-xxxxx"></script>
```
Ganti dengan:
```html
<!-- Production -->
<script src="https://app.midtrans.com/snap/snap.js"
        data-client-key="Mid-client-xxxxx"></script>
```

---

## Metode Pembayaran yang Didukung

| Metode | Keterangan |
|---|---|
| **ShopeePay** | Deeplink / redirect ke ShopeePay |
| **QRIS** | QR code universal (semua e-wallet) |
| **Transfer BCA** | Virtual Account BCA |
| **Transfer Mandiri** | Virtual Account Mandiri |
| **Transfer BNI** | Virtual Account BNI |

Semua diproses melalui **Midtrans** — tidak perlu integrasi ShopeePay langsung.

---

## Flow Transaksi

```
User pilih produk
  → Checkout (isi data game + pilih pembayaran)
  → POST /api/checkout.php (buat order + charge Midtrans)
  → Midtrans tampilkan popup/redirect pembayaran
  → User bayar via ShopeePay / QRIS / dll
  → Midtrans kirim notifikasi ke /api/payment-notify.php
  → Status order diupdate → fulfilled
```

---

## Fitur
- ✅ Halaman beranda dengan produk terlaris
- ✅ Kategori game (ML, FF, PUBG, Genshin, Valorant, Voucher)
- ✅ API produk dengan filter & search
- ✅ Checkout dengan form data akun game
- ✅ Integrasi Midtrans (ShopeePay, QRIS, VA Bank)
- ✅ Webhook notifikasi pembayaran
- ✅ Login & register (API)
- ✅ Database MySQL lengkap

## Pengembangan Lanjutan
- [ ] Halaman admin (dashboard, kelola produk, lihat order)
- [ ] Sistem voucher / promo kode
- [ ] Integrasi API top-up otomatis (Digiflazz, VIP Reseller)
- [ ] Notifikasi WhatsApp/Email setelah bayar
- [ ] Halaman riwayat order user
