# MTG-PHP

MTG project with an active Laravel backend and a frontend currently under construction.

## Backend

- Folder: [`apps/backend`](apps/backend)
- Backend documentation: [`apps/backend/README.md`](apps/backend/README.md)
- Typical local URL: `http://127.0.0.1:8000`

### Quick Start

From `apps/backend`:

```bash
composer run setup
composer run dev
```

### Useful Info

- Default database: MySQL (`DB_DATABASE=mtg` in `.env.example`)
- Run tests:

```bash
composer test
```

- Handy commands:

```bash
php artisan route:list
php artisan cards:import-scryfall --count=100 --delay=10
```

## Frontend

Frontend **WIP** (work in progress).
