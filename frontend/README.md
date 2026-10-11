# Chrono — Vue 3 frontend (optional)

A Vue 3 + Vite single-page rebuild of the Chrono UI. It talks to the existing
PHP backend (`api.php`) with no changes to the backend, and deploys as plain
static files — ideal for DirectAdmin shared hosting without SSH or Node on the
server.

The original `index.html` + `assets/` frontend at the project root still works
and is untouched; this `frontend/` project is an alternative you can build and
deploy when you want the modern SPA.

## Develop locally

Requires Node.js on your own machine (not on the host).

```bash
# 1) run the PHP backend (project root)
php -S 127.0.0.1:8099 -t .

# 2) run the Vue dev server (this folder)
cd frontend
npm install
npm run dev
```

Open the printed URL (http://localhost:5173). API calls to `api.php` are proxied
to the PHP server on port 8099 (see `vite.config.js`).

## Build for the host

```bash
cd frontend
npm run build
```

This produces `frontend/dist/` with `index.html` and an `assets/` folder, using
relative asset paths (`base: './'`).

## Deploy on DirectAdmin (no SSH needed)

1. Build locally as above.
2. Upload the **contents** of `frontend/dist/` into the same web directory as
   `api.php` (e.g. `public_html/`), next to `includes/`, `config.php`, etc.
3. The SPA calls `api.php` on the same origin, so no extra configuration is
   needed. Keep `config.php` on `DB_DRIVER = 'mysql'` for production.

To go back to the original UI, just keep using the root `index.html`.
