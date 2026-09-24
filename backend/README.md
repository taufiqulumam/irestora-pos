# Backend API — Sistem POS F&B

Laravel 13 (PHP 8.3+) + PostgreSQL 16 + Sanctum + L5-Swagger, dijalankan via Docker.
Skema database mengikuti `03-ERD.md` di dokumen planning — sudah **multi-outlet-ready**
sejak migration awal (lihat catatan di `01-PRD.md`).

## Menjalankan dengan Docker (disarankan)

```bash
docker compose build
docker compose up -d

# Masuk ke container app untuk perintah Artisan/Composer
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

API akan tersedia di `http://localhost:8000`.

> **Alternatif lebih cepat:** kalau kamu belum familiar Docker Compose manual,
> pertimbangkan **Laravel Sail** (`php artisan sail:install`) sebagai starting
> point — sudah dikonfigurasi resmi oleh tim Laravel dan support PostgreSQL.

## Menjalankan tanpa Docker (development lokal)

Butuh PHP 8.3+, Composer, PostgreSQL 16+ terpasang lokal.

```bash
composer install
cp .env.example .env
php artisan key:generate

# Set di .env:
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=pos_db
# DB_USERNAME=pos_user
# DB_PASSWORD=secret

php artisan migrate
php artisan serve
```

## Struktur Migration (urutan penting — mengikuti dependency FK)

```
000000  personal_access_tokens   (Sanctum, uuidMorphs untuk cocok dgn users.id UUID)
000001  outlets                  (fondasi multi-outlet, termasuk pb1_rate & service_charge_rate)
000002  roles, permissions, role_permissions
000003  users                    (UUID pk, referensi outlets & roles)
000004  categories, menus, menu_prices
000005  tables
000006  shifts
000007  orders, order_items, payments   (id UUID di-generate client, kolom sync_status)
000008  audit_logs, fraud_alerts        (audit_logs bersifat append-only)
```

## Catatan Penting

- **Semua PK pakai UUID**, bukan auto-increment — konsisten dengan strategi offline-sync
  di `02-SDD.md` §4 (transaksi dibuat client harus aman digabung tanpa collision ID).
- **`audit_logs` sengaja tidak punya kolom `updated_at`** dan tidak boleh diberi endpoint
  update/delete di controller manapun — lihat `03-ERD.md` catatan desain.
- Migration Sanctum default (`create_personal_access_tokens_table`) **sudah dimodifikasi**
  memakai `uuidMorphs()` alih-alih `morphs()` bawaan, karena `tokenable_id` harus
  cocok dengan tipe UUID di `users.id`. Jangan publish ulang migration Sanctum asli
  di atas file ini.
- Kalau kamu generate ulang starter Laravel baru, **hapus migration default**
  `0001_01_01_000000_create_users_table.php` dan `..._create_jobs_table.php` bawaan,
  lalu pakai migration di folder ini sebagai gantinya.

## Struktur Aplikasi

```
app/
  Models/
    User.php, Outlet.php, Role.php    <- HasUuids, relasi sesuai ERD
    Order.php, OrderBatch.php, OrderItem.php, Payment.php
    DiningTable.php, Menu.php, MenuPrice.php, Category.php
    Shift.php, AuditLog.php
  Events/
    OrderBatchSubmitted.php            <- broadcast ke kasir (private channel)
    OrderBatchStatusChanged.php        <- broadcast ke customer (public channel)
  Http/
    Controllers/Api/
      AuthController.php               <- register, login, me, logout
      OrderController.php              <- buka order (dine_in/takeaway) - lihat SDD §4.7
      OrderBatchController.php         <- cart submit (customer) & confirm (kasir)
      TableController.php              <- tutup meja manual - lihat SDD §4.7.1
      OpenApiSpec.php                  <- info global OpenAPI + bearerAuth scheme
    Requests/Auth/
      RegisterRequest.php, LoginRequest.php
    Requests/Order/
      OpenOrderRequest.php              <- validasi order_type + table available
    Resources/
      UserResource.php
  Support/
    ApiResponse.php                    <- format response konsisten
database/migrations/                   <- lihat urutan di atas
routes/
  api.php, channels.php                <- channels.php: otorisasi private channel Reverb
docker/
  Dockerfile, nginx.conf               <- nginx.conf sudah proxy WebSocket ke reverb
docker-compose.yml                     <- termasuk service reverb
```

## Setelah Migration Jalan

Endpoint yang sudah bisa dicoba (lihat juga `04-openapi.yaml` di dokumen planning):
- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/auth/me` (butuh Bearer token)
- `POST /api/auth/logout` (butuh Bearer token)
- `POST /api/tables/{tableId}/cart/submit` (publik — dipanggil Customer App)
- `POST /api/order-batches/{batchId}/confirm` (butuh Bearer token — dipanggil Kasir App)
- `POST /api/tables/{tableId}/close` (butuh Bearer token — tutup meja manual, hanya jika order paid, tercatat di audit_logs)

## Real-Time Notification (Laravel Reverb)

Setup broadcasting supaya kasir menerima notifikasi instan saat customer submit cart:

```bash
docker compose exec app php artisan reverb:install
```

Set di `.env`:
```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=pos-app
REVERB_APP_KEY=pos-key
REVERB_APP_SECRET=pos-secret
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http
```

Jalankan Reverb (sudah otomatis lewat container `reverb` di `docker-compose.yml`):
```bash
docker compose exec reverb php artisan reverb:start
```

Detail alur & alasan pemilihan Reverb ada di `02-SDD.md` §4.6 (dokumen planning) —
termasuk penjelasan kenapa kasir app tetap wajib fallback ke REST API saat
reconnect dari kondisi offline, bukan mengandalkan WebSocket sepenuhnya.

Generate dokumentasi Swagger:
```bash
docker compose exec app php artisan l5-swagger:generate
```
Buka `http://localhost:8000/api/documentation`.
