# Product Requirements Document (PRD)

## Sistem POS F&B — iRestora POS

|                          |                        |
| ------------------------ | ---------------------- |
| **Versi**                | 1.0                    |
| **Tanggal**              | 18 Agustus 2026        |
| **Status**               | Draft — Tahap Planning |
| **Referensi kompetitor** | ESB POS / ESB POSLite  |

---

## 1. Latar Belakang & Tujuan

Bisnis F&B (restoran, kafe, food court) membutuhkan sistem kasir (POS) yang:

- Tetap bisa beroperasi walau koneksi internet tidak stabil (kondisi umum di banyak outlet F&B Indonesia).
- Terintegrasi dari sisi kasir, dapur (kitchen), dan pelanggan dalam satu ekosistem.
- Memiliki kontrol terhadap kecurangan (fraud) yang umum terjadi di operasional kasir harian.
- Bisa digunakan baik untuk 1 outlet maupun berkembang ke banyak outlet/cabang tanpa migrasi ulang sistem.

**Tujuan produk:** membangun sistem POS berbasis web yang andal secara operasional (offline-capable di sisi kasir), aman dari fraud, dan siap diskalakan ke multi-outlet.

---

## 2. Skenario Pengguna Utama (User Personas)

| Persona                       | Kebutuhan Utama                                                                                                                           |
| ----------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| **Kasir**                     | Input order cepat, tetap bisa transaksi saat internet mati, cetak struk/kitchen order                                                     |
| **Supervisor/Manager Outlet** | Approval void/diskon, tutup shift, lihat laporan harian outlet, kelola menu & harga terpusat, monitor indikasi fraud                      |
| **Owner/Admin Pusat**         | Lihat laporan gabungan semua outlet, kelola menu & harga terpusat, monitor indikasi fraud, managemen role pegawai, managemen akun pegawai |
| **Customer**                  | Lihat menu, order mandiri via QR code di meja (self-order), tanpa perlu antre ke kasir                                                    |
| **Staff Dapur (Kitchen)**     | Terima order masuk secara real-time per station, tandai status selesai                                                                    |

---

## 3. Ruang Lingkup (Scope)

### 3.1 In-Scope untuk MVP

**Modul Kasir (Cashier App — PWA)**

- Login kasir (dengan PIN/re-auth untuk aksi sensitif)
- Buka meja / buka order baru
- Tambah/kurang item, catatan per item
- Void item/order **dengan approval PIN supervisor**
- Diskon manual dengan batas maksimal per role
- Split bill / gabung bill
- Checkout & pembayaran (cash, QRIS, kartu via EDC manual entry)
- Cetak struk & kitchen order (soft-copy preview → print)
- **Bekerja offline penuh**, sync otomatis saat online kembali
- Shift management (buka/tutup shift, hitung kas fisik vs sistem)

**Modul Dapur (Kitchen Display / Print)**

- Order masuk otomatis per station (bisa via cetak kitchen order dulu di MVP, KDS layar opsional di fase berikutnya)
- Update status order (diterima → diproses → siap)

**Modul Customer (Web App)**

- Lihat menu digital (via QR code per meja)
- **Cart/keranjang** — pilih beberapa item dulu sebelum submit, bisa ubah qty/hapus item sebelum dikirim
- Self-order (kirim isi cart sebagai pesanan), masuk ke antrian dapur setelah dikonfirmasi kasir
- Bisa **menambah pesanan berkali-kali** dalam satu sesi makan (submit cart baru, tetap tergabung ke satu tagihan meja yang sama)
- Lihat status pesanan sederhana per Batch pesanan

**Modul Admin/Panel Pusat**

- Kelola master data: menu, kategori, harga, paket, outlet
- Kelola user & role/permission
- Laporan penjualan harian/mingguan/bulanan per outlet
- **Audit log** — riwayat semua aksi sensitif (void, diskon, ubah harga, refund)
- **Rule-based fraud alert** — dashboard notifikasi indikasi fraud (lihat §4.6)

**Fondasi Teknis**

- Skema database **multi-outlet-ready** (meski MVP dioperasikan untuk 1–beberapa outlet)
- REST API terpusat (Laravel) yang melayani semua aplikasi frontend
- Autentikasi berbasis token (Laravel Sanctum)

### 3.2 Out-of-Scope untuk MVP (Fase Berikutnya)

- Integrasi payment gateway otomatis (Midtrans/Xendit) — MVP: pencatatan manual metode pembayaran
- Modul inventory & resep (bill of materials) penuh — MVP: pencatatan sederhana tanpa auto-deduct stok
- KDS (Kitchen Display System) berbasis layar — MVP: cetak kitchen order
- Integrasi ERP / akuntansi
- Program loyalty/membership
- Native mobile app (Android/iOS) — MVP: web/PWA saja
- Multi-bahasa & multi-currency
- Machine-learning based fraud detection (MVP: rule-based saja)

---

## 4. Kebutuhan Fungsional Detail

### 4.1 Manajemen Order & Meja

- Saat membuka order baru, kasir **wajib memilih tipe order terlebih dahulu**: **Dine-in** atau **Takeaway**
  - **Dine-in** → lanjut pilih meja, baru pilih menu
  - **Takeaway** → langsung pilih menu, tanpa langkah pilih meja
- Kasir dapat memindahkan meja, menggabung/split bill (khusus order dine-in)
- Order tersimpan lokal dulu di device kasir sebelum sync ke server

### 4.2 Menu & Harga

- Menu dikelola terpusat dari Admin Panel, harga bisa berbeda per outlet
- Perubahan harga menu **tidak bisa dilakukan dari layar kasir**

### 4.3 Pembayaran & Perhitungan Pajak

- Cash, QRIS (manual/dicatat), kartu (dicatat manual di MVP)
- Setiap transaksi wajib tercatat dengan nomor urut berurutan per outlet per hari
- Setiap transaksi menghitung **PBJT/PB1** (pajak restoran daerah, bukan PPN) dan **service charge** (jika berlaku), dengan tarif yang **configurable per outlet** karena tarif PBJT diatur berbeda-beda oleh Perda tiap daerah (maksimal 10% secara nasional)
- Breakdown subtotal, diskon, service charge, dan PB1 wajib tercetak terpisah di struk — detail formula ada di `03-ERD.md` §4

### 4.4 Cetak Struk & Kitchen Order

- Preview soft-copy (HTML) sebelum cetak
- Cetak via `window.print()` browser ke printer thermal yang terpasang sebagai printer sistem operasi

### 4.5 Offline & Sinkronisasi (Kasir App)

- Kasir app **wajib** tetap berfungsi penuh tanpa internet: buka order, tambah item, checkout, cetak struk
- Data transaksi disimpan lokal (IndexedDB) dengan UUID unik per transaksi
- Sinkronisasi otomatis ke server begitu koneksi tersedia kembali
- Indikator visual status sync (tersinkron / menunggu / gagal) harus terlihat jelas oleh kasir

### 4.6 Fraud Prevention & Audit

- **Audit log** mencatat: void, diskon manual, perubahan harga, refund, buka laci kasir, login/logout — dengan `user_id`, `before/after value`, `reason`, `device_id`, `timestamp`
- **Rule-based alert**, minimal mencakup:
  - Void rate kasir melebihi threshold per shift
  - Total diskon manual kasir melebihi threshold per shift
  - Selisih kas fisik vs sistem saat tutup shift melebihi toleransi
  - Gap pada nomor urut transaksi
- Alert muncul di dashboard Admin/Manager, tidak memblokir transaksi kasir secara real-time (non-intrusive)

### 4.7 Shift Management

- Buka shift: kasir input modal awal kas
- Tutup shift: kasir input kas fisik akhir, sistem tampilkan selisih vs kas seharusnya

---

## 5. Kebutuhan Non-Fungsional

| Kategori                 | Requirement                                                                                           |
| ------------------------ | ----------------------------------------------------------------------------------------------------- |
| **Ketersediaan (kasir)** | Fungsi transaksi inti harus tetap berjalan tanpa internet                                             |
| **Keamanan**             | Password di-hash (bcrypt/argon2), token expiry, HTTPS wajib, rate limiting API                        |
| **Auditability**         | Audit log tidak dapat diubah/dihapus oleh siapa pun termasuk admin                                    |
| **Skalabilitas**         | Skema data mendukung penambahan outlet tanpa migrasi struktural                                       |
| **Performa**             | Aksi kasir (tambah item, checkout) merespons < 300ms secara lokal (tanpa menunggu server)             |
| **Kompatibilitas**       | Kasir app berjalan baik di tablet/browser umum (Chrome/Edge terbaru), mendukung instalasi sebagai PWA |

---

## 6. Asumsi & Batasan

- Setiap outlet minimal punya 1 device untuk kasir dan koneksi internet yang _sebagian besar waktu_ tersedia (offline hanya kondisi sementara, bukan permanen)
- Printer thermal yang digunakan sudah ter-install sebagai printer sistem operasi (driver USB/network standar)
- Tahap MVP difokuskan pada operasional dasar restoran/kafe, belum mencakup skenario hotel/complex F&B enterprise

---

## 7. Metrik Keberhasilan (Success Metrics)

- Kasir dapat menyelesaikan transaksi tanpa gangguan meski internet putus hingga durasi tertentu (misal 30 menit) tanpa kehilangan data
- 100% aksi sensitif (void/diskon/refund) tercatat di audit log
- Waktu rata-rata proses satu transaksi kasir menurun dibanding proses manual/pencatatan manual sebelumnya

---

## 8. Dependensi Dokumen Terkait

- System Design Document (SDD) — `02-SDD.md`
- Database Schema / ERD — `03-ERD.md`
- API Specification — `04-openapi.yaml`
