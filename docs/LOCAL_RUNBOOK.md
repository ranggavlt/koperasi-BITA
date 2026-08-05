# Local Runbook KBSM

## Prasyarat

- PHP 8.2 atau lebih baru
- Composer
- Node.js/npm
- MySQL lokal
- Database dummy bernama `koperasi`

## Konfigurasi aman

Pastikan nilai efektif berikut sebelum migration atau seed:

```text
APP_ENV=local
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=koperasi
FEATURE_SHU_ENABLED=false
FEATURE_DANA_SOSIAL_ENABLED=false
FEATURE_DANA_SOSIAL_ALTERNATIVE_SOURCES_ENABLED=false
```

Jangan menjalankan `migrate:fresh` pada database berisi data client/production.

## Instalasi dan build

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan config:clear
php artisan migrate --seed
npm.cmd install
npm.cmd run build
php artisan view:cache
```

Untuk rebuild database demo yang telah dipastikan dummy:

```powershell
php artisan migrate:fresh --seed
```

## Menjalankan aplikasi

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Buka `http://127.0.0.1:8000`.

## Akun demo dari seeder

| Role | Email | Password demo |
|---|---|---|
| Finance | `keuangan@kbsm.test` | `Kbsm12345!` |
| Approval Admin | `approval@kbsm.test` | `Kbsm12345!` |
| Kasir | `kasir@kbsm.test` | `Kbsm12345!` |

Akun tersebut khusus database dummy lokal. Jangan memakai password demo pada environment nyata.

## Gate verifikasi

```powershell
composer dump-autoload -o
php artisan migrate:status
php artisan route:list
php artisan view:clear
php artisan view:cache
npm.cmd run build
php artisan koperasi:preflight-accounting-integrity
php artisan koperasi:preflight-financial-reconciliation
php artisan test --do-not-cache-result
git diff --check
git diff --cached --check
```

Jalankan pula seluruh command yang muncul dari:

```powershell
php artisan list --raw | Select-String 'koperasi:preflight'
```
