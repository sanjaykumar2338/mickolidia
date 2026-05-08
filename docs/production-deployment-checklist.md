# Wolforix Production Deployment Checklist

Use this checklist for small production deployments, especially MT5 onboarding, dashboard sync, mail, and route changes.

## Deploy

1. Put the site in a calm state: avoid deploying while a trader is actively setting up MT5 unless the change fixes that flow.
2. Pull the intended branch:
   ```bash
   git pull
   ```
3. Install dependencies only when `composer.json` or `composer.lock` changed:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
   If the server uses local Composer:
   ```bash
   php composer.phar install --no-dev --optimize-autoloader
   ```
4. Refresh autoload files:
   ```bash
   php composer.phar dump-autoload
   ```
   Or:
   ```bash
   composer dump-autoload
   ```
5. Run migrations:
   ```bash
   php artisan migrate --force
   ```
6. Clear framework caches:
   ```bash
   php artisan optimize:clear
   php artisan view:clear
   php artisan config:clear
   php artisan route:clear
   php artisan cache:clear
   ```
7. If the configured database cache store is unavailable, use the file-cache fallback for cache clearing:
   ```bash
   CACHE_STORE=file php artisan optimize:clear
   CACHE_STORE=file php artisan cache:clear
   ```

## Verify After Deploy

1. Confirm storage paths are writable by the web/PHP user:
   ```bash
   ls -ld storage bootstrap/cache
   ```
2. Confirm critical public routes do not return HTTP 500:
   ```bash
   curl -I -L https://www.wolforix.com/
   curl -I -L https://www.wolforix.com/login
   curl -I -L https://www.wolforix.com/dashboard
   curl -I -L https://www.wolforix.com/mt5/setup
   curl -I -L https://www.wolforix.com/trial/setup
   curl -I -L https://www.wolforix.com/mt5_demo.mp4
   ```
3. Confirm protected routes redirect safely when unauthenticated:
   ```bash
   curl -I https://www.wolforix.com/admin/clients
   curl -I https://www.wolforix.com/admin/client/1
   curl -I https://www.wolforix.com/dashboard/accounts/1/mt5-connector/download
   ```
4. Check application logs:
   ```bash
   tail -n 200 storage/logs/laravel.log
   ```
5. Check web/PHP logs if server access is available.
6. Send MT5 onboarding test mail before client delivery:
   ```bash
   php artisan wolforix:send-mt5-onboarding-email sk963070@gmail.com
   ```
7. Confirm the command output includes `CC: Support@wolforix.com`.
8. Confirm the email contains:
   - `https://www.wolforix.com/dashboard`
   - `https://www.wolforix.com/mt5/setup`
   - `https://www.wolforix.com/mt5_demo.mp4`
9. Confirm the email does not expose a secret token, MT5 password, or investor password.
10. Check admin client sync diagnostics after the trader connects MT5:
    - Last EA ping
    - Last successful metric update
    - Balance/equity/P&L
    - Trading days
    - Open/closed positions
    - Rejected or ignored sync logs

## Validation Commands

```bash
php artisan test --filter='mt5|connector|email|trial|dashboard|sync|metrics'
php -l app/Console/Commands/SendMt5OnboardingEmail.php
php -l app/Http/Controllers/TrialController.php
php -l app/Mail/Mt5OnboardingSetupMail.php
git diff --check
```
