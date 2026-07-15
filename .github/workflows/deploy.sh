#!/bin/sh
# Server-side deploy for JCDMS. Run over SSH by .github/workflows/deploy.yml with
# DEPLOY_PATH passed in. Adapted from the lcc_operation_management deploy, with the extra
# steps this project needs: the SSR process, the queue worker, and a deploy check that
# fails the pipeline if the deploy left the site broken.
#
# `set -eu`: any command that fails aborts the deploy (a half-applied deploy is worse than
# a failed one), and an unset variable is an error rather than an empty string.
set -eu

DEPLOY_PATH="${DEPLOY_PATH:?DEPLOY_PATH not set — the workflow must pass secrets.SSH_DEPLOY_PATH}"
cd "$DEPLOY_PATH"

echo "==> Pulling latest code"
git pull origin main

echo "==> Composer (production, no dev dependencies)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Running migrations"
php artisan migrate --force

echo "==> Ensuring the storage symlink"
# storage:link errors if the link already exists; that is fine and not a deploy failure.
php artisan storage:link 2>/dev/null || true

echo "==> Rebuilding caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Restarting queue workers"
# Signals the long-running (or cron-driven) workers to restart after their current job, so
# they pick up the new code. Crossref deposits run here.
php artisan queue:restart

echo "==> Restarting the Inertia SSR process"
# The SSR process must restart to load the new bootstrap/ssr bundle. When it is down,
# Inertia falls back to client rendering and the site goes blank to crawlers — so this is
# not optional. ADAPT this block to how SSR is supervised on your server (docs/DEPLOYMENT.md §6):
#   - systemd (root):   sudo systemctl restart jcdm-ssr
#   - cPanel (no root): kill it and relaunch detached; a per-minute cron watchdog is the
#                       safety net if the shell reaps the process when this session ends.
pkill -f "inertia:start-ssr" 2>/dev/null || true
sleep 1
setsid nohup php artisan inertia:start-ssr >> storage/logs/ssr.log 2>&1 &
:

echo "==> Deploy check (env, database, charset, assets, SSR bundle, queue)"
# FATAL: a FAIL here (missing SSR bundle, latin1 database, pending migration, debug on in
# production…) aborts the deploy. This is the difference between "the script ran" and "the
# site is actually serving correctly".
php artisan deploy:check

echo "==> Machine-readability check (is the live page server-rendered?)"
# Give the SSR process a few seconds to boot, then confirm a real page still returns its
# citation metadata to a no-JavaScript client. Non-fatal, because the SSR restart mechanism
# varies per host and the hourly scheduled check-ssr is the backstop — but it prints the
# result so a broken SSR process is visible in the deploy log.
ok=0
i=1
while [ "$i" -le 5 ]; do
  sleep 4
  if php artisan journal:check-ssr; then
    ok=1
    break
  fi
  echo "   SSR not confirmed yet (attempt $i/5)…"
  i=$((i + 1))
done

if [ "$ok" -ne 1 ]; then
  echo "::warning:: SSR was not confirmed machine-readable. The Blade citation tags still"
  echo "            work, but the body is not server-rendered. Check the SSR process and"
  echo "            storage/logs/ssr.log (docs/DEPLOYMENT.md §1)."
fi

echo "==> Deploy complete"
