# Cara Menjalankan Project iRestora POS di Local

## 📋 Prerequisites

- **Docker Desktop** (wajib)
- **Node.js 20+** & **npm**
- **Git**

---

## 🚀 Quick Start (Satu Perintah)

```bash
# 1. Clone & masuk ke project
cd /Users/taufiqulmam/Project/irestora-pos

# 2. Start semua Docker services (backend + database + websocket)
docker compose -f backend/docker-compose.yml up -d --build

# 3. Tunggu ~30 detik, lalu jalankan migrasi & seeder
docker compose -f backend/docker-compose.yml exec app php artisan migrate --force
docker compose -f backend/docker-compose.yml exec app php artisan db:seed --force
```

---

## 🐳 Jalankan Backend (Laravel API + Database + Reverb)

```bash
cd /Users/taufiqulmam/Project/irestora-pos/backend

# Build & start containers
docker compose up -d --build

# Jalankan migrasi database
docker compose exec app php artisan migrate --force

# Jalankan seeder (data dummy: outlet, menu, user, table)
docker compose exec app php artisan db:seed --force
```

### 🔗 Localhost Backend

| Service                 | URL                                     | Port |
| ----------------------- | --------------------------------------- | ---- |
| **API Gateway (Nginx)** | http://localhost:8000                   | 8000 |
| **Swagger API Docs**    | http://localhost:8000/api/documentation | 8000 |
| **PHP-FPM (Direct)**    | http://localhost:9000                   | 9000 |
| **Reverb WebSocket**    | ws://localhost:8080                     | 8080 |
| **PostgreSQL**          | localhost:5432                          | 5432 |
| **Redis**               | localhost:6379                          | 6379 |

### 🗄️ Database Connection (PostgreSQL)

```ini
Host: localhost
Port: 5432
Database: pos_db
User: pos_user
Password: secret
```

> ⚠️ **PostgreSQL tidak bisa diakses via browser** (port 5432 bukan HTTP).
> Gunakan **psql CLI** atau GUI client seperti **pgAdmin 4**, **DBeaver**, **TablePlus**.

---

## 🖥️ Jalankan Frontend Apps

### 1. Kasir PWA (Vue 3 + Vite + Tailwind)

```bash
cd /Users/taufiqulmam/Project/irestora-pos/frontend/cashier
npm install
npm run dev
```

| App           | URL                   | Port |
| ------------- | --------------------- | ---- |
| **Kasir PWA** | http://localhost:3000 | 3000 |

### 2. Customer Web App (Self-Order QR)

```bash
cd /Users/taufiqulmam/Project/irestora-pos/frontend/customer
npm install
npm run dev
```

| App                     | URL                   | Port |
| ----------------------- | --------------------- | ---- |
| **Customer Self-Order** | http://localhost:3001 | 3001 |

### 3. Admin Panel (Vue 3 + PrimeVue)

```bash
cd /Users/taufiqulmam/Project/irestora-pos/frontend/admin
npm install
npm run dev
```

| App             | URL                   | Port |
| --------------- | --------------------- | ---- |
| **Admin Panel** | http://localhost:3002 | 3002 |

---

## 📋 Port Summary

| Service                 | Port | Deskripsi                 |
| ----------------------- | ---- | ------------------------- |
| **Frontend Cashier**    | 3000 | Kasir PWA (Offline-first) |
| **Frontend Customer**   | 3001 | Customer Self-Order QR    |
| **Frontend Admin**      | 3002 | Admin Panel (PrimeVue)    |
| **API Gateway (Nginx)** | 8000 | Main API Entry Point      |
| **PHP-FPM**             | 9000 | Direct PHP-FPM            |
| **Reverb WebSocket**    | 8080 | Real-time Notifications   |
| **PostgreSQL**          | 5432 | Database                  |
| **Redis**               | 6379 | Cache / Queue / Broadcast |

---

## 🔑 Test Credentials

| Role           | Email                   | Password      | PIN    |
| -------------- | ----------------------- | ------------- | ------ |
| **Admin**      | admin@irestora.com      | admin123      | 123456 |
| **Manager**    | manager@irestora.com    | manager123    | 123456 |
| **Supervisor** | supervisor@irestora.com | supervisor123 | 123456 |
| **Cashier**    | cashier@irestora.com    | cashier123    | 1234   |
| **Cashier 2**  | cashier2@irestora.com   | cashier123    | 5678   |

---

## 🧪 Test API Endpoints

```bash
# 1. Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@irestora.com","password":"admin123"}'

# 2. Get Outlets
TOKEN="<TOKEN_DARI_LOGIN>"
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/outlets

# 3. Get Tables
curl -H "Authorization: Bearer $TOKEN" "http://localhost:8000/api/tables?outlet_id=<OUTLET_ID>"

# 4. Get Menus
curl -H "Authorization: Bearer $TOKEN" "http://localhost:8000/api/menus?outlet_id=<OUTLET_ID>"

# 5. Swagger UI
open http://localhost:8000/api/documentation
```

---

## 🛑 Stop Semua Services

```bash
# Stop Docker (backend)
docker compose -f backend/docker-compose.yml down

# Stop frontends (Ctrl+C di tiap terminal)
```

---

## 🔧 Troubleshooting

| Masalah                         | Solusi                                                                                     |
| ------------------------------- | ------------------------------------------------------------------------------------------ |
| Port conflict (8000, 3000, dll) | `lsof -i :<port>` → `kill -9 <PID>`                                                        |
| DB connection refused           | `docker compose -f backend/docker-compose.yml restart postgres`                            |
| Nginx 502 Bad Gateway           | `docker compose -f backend/docker-compose.yml restart app nginx`                           |
| Reverb tidak konek              | `docker compose logs reverb` — perlu PCNTL extension (sudah include di Dockerfile PHP 8.4) |
| Frontend error CORS             | Pastikan nginx proxy ke `app:9000` dan Reverb ke `reverb:8080`                             |
| "Page not working" di port 5432 | **PostgreSQL bukan HTTP** — gunakan psql/DBeaver/pgAdmin, bukan browser!                   |

---

## 📁 Struktur Project

```
irestora-pos/
├── backend/                 # Laravel 12 API
│   ├── app/                 # Controllers, Models, Services
│   ├── database/
│   │   ├── migrations/      # Schema DB
│   │   └── seeders/         # Data dummy
│   ├── docker/
│   │   ├── Dockerfile       # PHP 8.4 + PCNTL
│   │   └── nginx.conf
│   └── docker-compose.yml
├── frontend/
│   ├── cashier/             # Kasir PWA (Vue 3 + Vite + Tailwind)
│   ├── customer/            # Customer Self-Order
│   ├── admin/               # Admin Panel (PrimeVue)
│   └── shared/              # Shared types, utils, calculator
├── docs/                    # PRD, SDD, ERD, OpenAPI
└── docker-compose.yml       # Root (opsional)
```

---

## 📖 Dokumentasi Lengkap

| File                   | Deskripsi                     |
| ---------------------- | ----------------------------- |
| `docs/01-PRD.md`       | Product Requirements Document |
| `docs/02-SDD.md`       | System Design Document        |
| `docs/03-ERD.md`       | Entity Relationship Diagram   |
| `docs/04-openapi.yaml` | OpenAPI 3.0 Spec              |
| `backend/docs/`        | Laravel-specific docs         |

---

## 🛠️ Development Commands

```bash
# Backend logs
docker compose -f backend/docker-compose.yml logs -f app

# Database shell
docker compose -f backend/docker-compose.yml exec postgres psql -U pos_user -d pos_db

# Laravel artisan
docker compose -f backend/docker-compose.yml exec app php artisan <command>

# Clear cache
docker compose -f backend/docker-compose.yml exec app php artisan optimize:clear

# Run tests
docker compose -f backend/docker-compose.yml exec app php artisan test

# Frontend build production
cd frontend/cashier && npm run build
cd frontend/customer && npm run build
cd frontend/admin && npm run build
```

---

## 📝 Generated at

`2026-08-20` — iRestora POS v1.0.0
