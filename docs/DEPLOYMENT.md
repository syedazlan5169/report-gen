# Production Deployment

This project has a production-only Docker stack. It is designed for the VPS architecture documented in `docs/VPS_DEPLOYMENT_PROFILE.md`.

## Architecture

```text
Internet
  -> host Nginx :80/:443 and Certbot
  -> 127.0.0.1:${REPORT_GEN_HOST_PORT}
  -> Docker Nginx
  -> PHP 8.4-FPM app
  -> probono-mysql on the external probono-db network
```

The Compose stack contains only `app` and `nginx`. There is no project-local MySQL, Redis, Node runtime, queue worker, scheduler, Horizon, or Supervisor service.

The application and Nginx images are immutable and tagged with the Git commit SHA. Laravel's `storage/` directory is the only named volume. It stores file sessions, file cache, logs, compiled views, and framework runtime files. Import JSON files use PHP temporary upload storage and are not persisted.

## Files

- `compose.prod.yaml` defines the two production services, networks, port, and storage volume.
- `docker/php/Dockerfile.production` builds Vite assets, Composer dependencies, the PHP-FPM image, and the Nginx image target.
- `docker/nginx/production.conf` serves `/public`, forwards `index.php` to `app:9000`, preserves forwarded proxy headers, and allows up to 2 MB of request body for the application's 1 MB import files plus multipart overhead.
- `.env.production.example` is a non-secret template. The real `.env.production` file is server-local and must never be committed.
- `.dockerignore` excludes local secrets, dependencies, SQLite files, runtime storage, and build artifacts.

## First deployment

Run these steps on the VPS after reviewing the selected commit. The commands below are examples; replace placeholders with deployment-specific values.

1. Confirm the working tree is clean and the intended commit is pushed.
2. Clone or update the repository at `/opt/report-gen` and check out the intended commit.
3. Copy `.env.production.example` to a server-local `.env.production` file and set the final domain, database name, database username, and database password. This env file is required by Compose; the stack must not be started without it.
4. Generate `APP_KEY` once with the app image or a trusted PHP environment. Store it in `.env.production` and keep it unchanged across all releases. Never generate it during container startup.
5. Create a dedicated MySQL database and application user on the existing shared MySQL service. Grant only the permissions needed by this application. Do not use MySQL root credentials in the application environment.
6. Verify that the external Docker network `probono-db` exists and that the shared MySQL service has the `probono-mysql` alias.
7. Check available ports with `ss -ltnp` and choose an unused localhost high port. Do not assume the existing app's `8081` port is available.
8. Set the release variables:

   ```sh
   export RELEASE_ID="$(git rev-parse HEAD)"
   export REPORT_GEN_HOST_PORT=18082
   ```

9. Build both immutable image targets:

   ```sh
   docker compose -f compose.prod.yaml build
   ```

10. Validate the rendered Compose configuration:

    ```sh
    docker compose -f compose.prod.yaml config
    ```

11. Run migrations from a one-off container built from the new app image before enabling public traffic:

    ```sh
    docker compose -f compose.prod.yaml run --rm app php artisan migrate --force
    ```

12. Start the web stack:

    ```sh
    docker compose -f compose.prod.yaml up -d
    ```

13. Compile Laravel's runtime caches inside the running app container, after the production environment is present:

    ```sh
    docker compose -f compose.prod.yaml exec app php artisan optimize
    ```

    This caches configuration, routes, and views. Do not run this during the image build because runtime secrets must not be baked into an image.

14. Verify the application through `http://127.0.0.1:${REPORT_GEN_HOST_PORT}` before configuring host Nginx.
15. Add a host Nginx site for the final domain. Host Nginx remains the only public ingress and TLS terminator:

    ```nginx
    server {
        listen 80;
        server_name <domain>;

        location / {
            proxy_pass http://127.0.0.1:<REPORT_GEN_HOST_PORT>;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }
    }
    ```

16. Point DNS at the VPS, verify HTTP routing, then obtain and configure TLS using the existing host Certbot convention. Do not put certificates in Docker.
17. Verify HTTPS, forwarded scheme handling, registration, login, session persistence, configuration import/export, and real report generation.
18. Before relying on production data, confirm that the existing `probono-mysql-backup.service` and scheduled backup script include the new Report Generator database.
19. Inspect application and infrastructure logs after the first functional test.

## Future deployments

Use the target commit's full SHA for every release:

```sh
git fetch origin
git checkout <target-commit>
export RELEASE_ID="$(git rev-parse HEAD)"
export REPORT_GEN_HOST_PORT=<verified-unused-port>
docker compose -f compose.prod.yaml build
docker compose -f compose.prod.yaml config
docker compose -f compose.prod.yaml run --rm app php artisan migrate --force
docker compose -f compose.prod.yaml up -d
docker compose -f compose.prod.yaml exec app php artisan optimize
```

This is a simple release process, not a zero-downtime migration framework. Review migrations that may be destructive or incompatible with the previous application version before running them.

## Rollback

Identify the previous Git SHA and its `report-gen-app:<sha>` and `report-gen-nginx:<sha>` images, set `RELEASE_ID` to that SHA, and redeploy the Compose stack. Verify the localhost endpoint, health, logs, and host Nginx path.

Code/image rollback does not automatically roll back the database. Do not run `migrate:rollback` as part of a routine code rollback. Review the migration and restore from the existing database backup only through an explicit operational decision.

## Environment and secrets

Required production values include:

- `APP_ENV=production`
- `APP_DEBUG=false`
- Stable generated `APP_KEY`
- Final HTTPS `APP_URL`
- `APP_TIMEZONE=Asia/Kuala_Lumpur`
- `DB_CONNECTION=mysql`
- `DB_HOST=probono-mysql`
- `DB_PORT=3306`
- Dedicated `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`
- `SESSION_DRIVER=file`
- `CACHE_STORE=file`
- `QUEUE_CONNECTION=sync`
- `SESSION_SECURE_COOKIE=true`

The database name and credentials are deployment decisions. Do not commit `.env.production` or any real secret.

## Proxy behavior

The Docker Nginx container receives HTTP from host Nginx, so it must not replace `X-Forwarded-Proto` with its own `$scheme`. The container forwards the original `X-Forwarded-Proto`, `X-Forwarded-For`, `X-Real-IP`, and `Host` values to PHP-FPM.

Laravel registers its built-in `TrustProxies` middleware in `bootstrap/app.php` and trusts the private proxy path. No `forceScheme('https')` workaround is used.

## Local validation

The external `probono-db` network and production MySQL are not mocked locally. Validate the stack definition and images without starting a fake database:

```sh
export RELEASE_ID=local-validation
export REPORT_GEN_HOST_PORT=18082
docker compose -f compose.prod.yaml config
docker compose -f compose.prod.yaml build app nginx
docker run --rm report-gen-app:${RELEASE_ID} php -v
docker run --rm report-gen-app:${RELEASE_ID} composer check-platform-reqs --no-dev
docker run --rm --env-file .env.production report-gen-app:${RELEASE_ID} php artisan --version
docker run --rm --env-file .env.production report-gen-app:${RELEASE_ID} php artisan about
docker run --rm --env-file .env.production report-gen-app:${RELEASE_ID} php artisan route:list
docker run --rm report-gen-nginx:${RELEASE_ID} sh -c 'test -f /var/www/html/public/index.php && test -f /var/www/html/public/build/manifest.json'
docker run --rm report-gen-app:${RELEASE_ID} sh -c 'test -d /var/www/html/vendor && test ! -e /var/www/html/.env && test -w /var/www/html/storage && test -w /var/www/html/bootstrap/cache'
```

The final app-image Artisan commands prove that Laravel boots and that routes can be inspected without a database service. The production env file is required for the Compose stack and should be created locally only for validation, never committed. Also run the host test suite, `npm run build`, and the project's PHP formatter. A full production Compose startup requires the real external network and MySQL service.

## Logs and troubleshooting

```sh
docker compose -f compose.prod.yaml ps
docker compose -f compose.prod.yaml logs nginx
docker compose -f compose.prod.yaml logs app
docker compose -f compose.prod.yaml exec app tail -f storage/logs/laravel.log
docker compose -f compose.prod.yaml exec app php artisan about
```

On the host, inspect `/var/log/nginx/access.log`, `/var/log/nginx/error.log`, `docker ps`, `docker inspect`, `docker network inspect probono-db`, and the relevant Docker/Nginx system logs.