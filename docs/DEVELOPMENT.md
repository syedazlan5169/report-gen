# Local Development

This project uses plain `docker compose` for local development only. Production is independent and always uses `docker compose -f compose.prod.yaml ...`.

## Fresh clone

```sh
git clone <repository>
cd report-gen
docker compose up -d --build
```

The application is available at:

- Laravel: <http://localhost:8080>
- Vite: <http://localhost:5173>

Override the ports with `APP_PORT` and `VITE_PORT`, for example:

```sh
APP_PORT=18080 VITE_PORT=15173 docker compose up -d --build
```

## Common commands

```sh
docker compose up -d --build
docker compose down
docker compose logs -f
docker compose exec app php artisan <command>
docker compose exec app sh -lc 'APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: SESSION_DRIVER=array CACHE_STORE=array php artisan test --compact'
docker compose exec app composer <command>
docker compose exec node npm <command>
```

The app creates `.env` from `.env.example` only when `.env` is absent. It generates `APP_KEY` only when the key is missing or blank, so an existing key is never replaced. Docker-only runtime values, including the MySQL connection, are supplied by `compose.yaml`; the host `.env` remains developer-owned.

Composer installs into the `vendor_data` volume on first startup. The current `composer.lock` hash is recorded after a successful install. A changed lock file causes `composer install` to run again; failed installs stop app startup visibly.

Node installs into the `node_modules_data` volume with `npm ci` on first startup. The current `package-lock.json` hash is recorded after a successful install, and changes cause `npm ci` to run again. Vite listens on all container interfaces while the browser-facing HMR URL defaults to `localhost` and the configured Vite port.

MySQL data persists in `mysql_data`. Laravel runs normal migrations on app startup; it does not run `migrate:fresh` or seed data. Docker development uses MySQL. Run the test suite with the existing in-memory SQLite configuration by explicitly setting the test environment inside the app container, because Compose's runtime environment takes precedence over non-forced PHPUnit XML variables.

The Node entrypoint removes a stale `public/hot` before Vite starts and removes it on shutdown. This prevents a later non-Docker `php artisan serve` session from treating a stopped Docker Vite server as active.

To intentionally reset all local Docker state:

```sh
docker compose down -v
```

**Warning:** this deletes the local development database and all named Docker volumes for this Compose project.
