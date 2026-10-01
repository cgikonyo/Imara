# Imara

Imara is a money-lending platform with a Laravel API and React client. M-Pesa Daraja credentials are kept in environment variables and must never be committed.

## Project layout

- `api/` Laravel PHP API
- `frontend/` React TypeScript client (to be scaffolded)

## Local prerequisites

- PHP 8.4 with OpenSSL, mysqli, and pdo_mysql enabled
- Composer 2.x
- Node.js LTS and npm
- MySQL 8.x
- Git

## Backend setup

```powershell
Set-Location api
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Update `api/.env` with the local MySQL database values and Daraja sandbox values before using payment flows:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=imara
DB_USERNAME=root
DB_PASSWORD=

MPESA_ENVIRONMENT=sandbox
MPESA_CONSUMER_KEY=
MPESA_CONSUMER_SECRET=
MPESA_SHORTCODE=
MPESA_PASSKEY=
MPESA_CALLBACK_URL=
```



## Frontend setup

```powershell
Set-Location frontend
npm install
npm run dev
```
