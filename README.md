# MTG-PHP

Projet MTG avec backend Laravel (actif) et frontend en cours de construction.

## Backend

- Dossier: [`apps/backend`](apps/backend)
- Documentation backend: [`apps/backend/README.md`](apps/backend/README.md)
- URL locale typique: `http://127.0.0.1:8000`

### Démarrage rapide

Depuis `apps/backend`:

```bash
composer run setup
composer run dev
```

### Infos utiles

- Base de données par défaut: MySQL (`DB_DATABASE=mtg` dans `.env.example`)
- Lancer les tests:

```bash
composer test
```

- Commandes pratiques:

```bash
php artisan route:list
php artisan cards:import-scryfall --count=100 --delay=10
```

## Frontend

Frontend **WIP** (work in progress).
