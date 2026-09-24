# Dokumen Perencanaan — Sistem POS F&B

Kumpulan dokumen teknis tahap planning, hasil brainstorming sebelum masuk ke development.

## Daftar Dokumen

1. **`01-PRD.md`** — Product Requirements Document. Scope, fitur, persona, kebutuhan fungsional & non-fungsional.
2. **`02-SDD.md`** — System Design Document. Arsitektur, tech stack, strategi offline-sync, desain fraud prevention, deployment.
3. **`03-ERD.md`** — Database Schema. Detail seluruh tabel, relasi, dan catatan desain (multi-outlet, audit log, sync).
4. **`04-openapi.yaml`** — API Specification (OpenAPI 3.0). Bisa langsung di-import ke Swagger UI / Postman.

## Ringkasan Keputusan Kunci dari Brainstorming

| Keputusan | Pilihan |
|---|---|
| Skala outlet | Skema data multi-outlet-ready sejak awal; scope fitur MVP boleh single/beberapa outlet |
| Arsitektur frontend | Laravel API (backend-only) + SPA terpisah, **bukan** Inertia |
| Framework frontend | **Vue 3** (dipilih karena kesamaan sintaks dengan Blade & kurva belajar lebih landai) |
| Backend framework | **Laravel 13** (PHP 8.3+) |
| Database | **PostgreSQL 16+** |
| Containerization | **Docker + Docker Compose** — lihat `docker-compose.yml` & folder `docker/` |
| Offline support kasir | **Wajib ada di MVP** — PWA + IndexedDB (Dexie.js) + Service Worker (Workbox) |
| Auth | Laravel Sanctum (token-based), dipakai semua client |
| Cetak struk | Soft-copy preview (HTML) → `window.print()`, PDF arsip opsional di fase 2 |
| Fraud prevention | `audit_logs` (append-only) + rule-based alert engine (bukan ML di MVP) |
| Perhitungan pajak | PBJT/PB1 (bukan PPN), tarif configurable per outlet — lihat `03-ERD.md` §4 |

## Urutan Baca yang Disarankan
PRD → SDD → ERD → OpenAPI Spec (dari "apa yang dibutuhkan" → "bagaimana dirancang" → "struktur data" → "kontrak API").

## Langkah Selanjutnya Setelah Dokumen Ini
- Review & validasi asumsi di PRD dengan calon pengguna (owner/kasir outlet riil)
- Breakdown SDD & ERD menjadi task development (sprint planning)
- Setup project skeleton: Laravel API + 3 project Vue (kasir/customer/admin)
- Buat migration Laravel berdasarkan `03-ERD.md`
