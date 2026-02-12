# Deployment Guide (Manual-only GitHub Actions + FTP)

## Requirements
- cPanel/shared hosting with PHP 8.1+
- MySQL 5.7+/8.0+
- FTP account access

## Manual deploy pipeline
1. Push changes to GitHub.
2. Go to **Actions** → **Deploy Standalone via FTP**.
3. Click **Run workflow** (manual trigger only).
4. Workflow uploads `/standalone` content directly into `LIVE_ROOT_PATH`.

## Required repository secrets
- `FTP_HOST`
- `FTP_USERNAME`
- `FTP_PASSWORD`
- `LIVE_ROOT_PATH`

## First install on production
1. Upload standalone folder (via workflow).
2. Open `https://your-domain.com/installer/index.php`.
3. Complete DB and admin steps.
4. Configure API keys in `/admin/keys.php`.

## Upgrade
- Re-run manual workflow.
- Open `/installer/step_finish.php?mode=upgrade` and run migration utility.

