# Local Runtime Modes

MediaForge can run locally either from a stable production build or through the Vite HMR server. On Windows bind mounts, prefer the production-build mode when Vite stops responding or the browser still shows an older React page.

## Default mode: production build

The **production build is the default**. The `vite` service in `deploy/dev/docker-compose.yml` sits behind the `hmr` Compose profile, so `make up` (`docker compose up`) starts the stack **without** the Vite dev server and **without** creating `public/hot`. Laravel then serves the fingerprinted assets from `public/build/`. This avoids the recurring Windows/Docker failure where a hung or stale Vite HMR server left the browser blank.

Fast paths:

```powershell
make runtime-reset   # remove public/hot + clear caches (production-build mode)
make assets          # rebuild public/build in a clean one-off node container
make hmr             # opt in to Vite HMR when you need live frontend updates
```

## Why assets must carry the port

Behind nginx, `fastcgi_param HTTP_HOST $host` drops the port, so PHP receives `HTTP_HOST=localhost` (no port) and Laravel derives a **portless** request root (`http://localhost`). In production-build mode `@vite` and Ziggy generate absolute URLs from that root, so the browser on `:8100` would fetch `http://localhost/build/...` from port 80 and render a blank page.

`App\Providers\AppServiceProvider::boot()` fixes this permanently by pinning every generated URL to `APP_URL` via `URL::forceRootUrl(config('app.url'))`. Keep `APP_URL` correct (e.g. `http://localhost:8100`) and do not remove that call — it is what keeps assets and route links on the right origin.

## Create a local development user

The command is deliberately limited to `local` and `testing` environments. It creates or updates a non-secret local account:

```powershell
docker compose -f deploy/dev/docker-compose.yml exec app php artisan mediaforge:dev-user
```

Email: `test@mediaforge.local`  
Password: `test123456`

## Reset to production-build mode

Use this mode when the local Vite server is unavailable or has stale modules. It produces fingerprinted assets in `public/build` and removes the local HMR pointer.

```powershell
npm run build
Remove-Item public/hot -Force -ErrorAction SilentlyContinue
docker compose -f deploy/dev/docker-compose.yml exec app php artisan optimize:clear
```

Then hard-reload the browser with `Ctrl+Shift+R`.

For Windows bind-mount stability, the dev PHP overlay keeps OPcache timestamp validation disabled. After changing PHP files, recreate only the app container before reloading the browser:

```powershell
docker compose -f deploy/dev/docker-compose.yml up -d --force-recreate --no-deps app
```

Production also keeps timestamp validation disabled; do not copy the dev INI into a different runtime without considering its deployment workflow.

After `docker compose ... up`, wait until the `app` service reports `healthy`. Its dev-only `/up` healthcheck warms PHP-FPM before the first browser flow.

## Reset Vite HMR mode

Use this only when you need live frontend updates. The `vite` service is behind the `hmr` profile, so start it explicitly. It recreates `public/hot` when it starts.

```powershell
docker compose -f deploy/dev/docker-compose.yml --profile hmr up -d vite   # or: make hmr
```

If it misbehaves, clear its cache and restart:

```powershell
Remove-Item node_modules/.vite -Recurse -Force -ErrorAction SilentlyContinue
docker compose -f deploy/dev/docker-compose.yml exec vite sh -lc 'rm -rf /var/www/html/node_modules/.vite'
docker compose -f deploy/dev/docker-compose.yml restart vite
```

Wait until the Vite logs report that the server is ready, then hard-reload the browser. If the dev server still blocks module requests on a Windows bind mount, return to production-build mode with `make runtime-reset`.

## Clear Laravel caches

```powershell
docker compose -f deploy/dev/docker-compose.yml exec app php artisan optimize:clear
```

## Generated runtime files

Never commit these files or directories:

- `public/hot`
- `public/build/`

Both are already ignored by Git. `public/hot` selects the Vite HMR server; without it, Laravel serves the current production manifest from `public/build/`.
