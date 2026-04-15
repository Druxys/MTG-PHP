# Backend MTG

Minimal README to run the Laravel backend locally.

## Prerequisites

- PHP 8.3+
- Composer
- Node.js + npm
- MySQL

## Quick setup

From `apps/backend`:

```bash
composer run setup
```

This command performs the base setup:
- PHP and Node dependencies
- `.env` file creation if missing
- `APP_KEY` generation
- migrations
- Vite frontend build

## Database configuration

The project uses MySQL by default (`.env.example`):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mtg
DB_USERNAME=root
DB_PASSWORD=
```

Make sure the `mtg` database exists and credentials are correct in `.env`.

## Run the project

Still from `apps/backend`:

```bash
composer run dev
```

This command starts:
- Laravel server
- queue listener
- logs (pail)
- Vite in dev mode

Typical local URL: `http://127.0.0.1:8000`

## Tests

```bash
composer test
```

## Useful commands

```bash
php artisan migrate
php artisan route:list
php artisan cards:import-scryfall --count=100 --delay=10
npm run build
```

## Swagger / OpenAPI

Generate API docs:

```bash
php artisan l5-swagger:generate
```

Then open:

- Swagger UI: `http://127.0.0.1:8000/api/docs`
- OpenAPI JSON: `http://127.0.0.1:8000/api/openapi`

The generated files are stored in `storage/api-docs`.

