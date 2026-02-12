# Image + Video Generation Studio (Standalone PHP)

## Runtime requirements
- PHP 8.1+
- MySQL with PDO
- Shared hosting compatible (cPanel)
- No Node runtime required in production

## Folder structure
- `app/` public pages and assets
- `api/` JSON endpoints (`generate`, `tick`, `status`, `history`, `download`)
- `admin/` admin panel for keys/settings/models/migrations
- `installer/` installation + upgrade tools
- `lib/` core libraries: DB, auth, crypto, migration runner, xAI integration, validation, rate limiting
- `migrations/` sequential SQL migrations (`0001` ...)
- `storage/generated` generated local files if used
- `storage/logs` logs and round-robin/rate-limit counters
- `config.sample.php` baseline config template
- `config.local.php` created by installer
- `installed.lock` created after fresh install

## Install steps (fresh install)
1. Upload `standalone/` content to production web root.
2. Open `/installer/index.php`.
3. Step DB: enter DB credentials, APP_URL, optional encryption key.
   - If encryption key is blank, installer auto-generates base64 32-byte key.
4. Step Admin: create admin user/password.
5. Installer runs migrations, seeds defaults (`api_keys`, models, settings), and creates `installed.lock`.
6. Log into `/admin/index.php` and set real `XAI_API_KEY` values.

## Upgrade steps
If `installed.lock` exists:
- Full reinstall is refused.
- Use `/installer/step_finish.php?mode=upgrade` to:
  - Run migrations
  - Repair storage folders
  - Validate DB connection

## Auto-migration behavior
When `AUTO_MIGRATE=true`, app runs migration checks automatically on:
- Admin login
- `/api/generate.php`
- `/api/tick.php`

Mechanics:
- Reads SQL files in `/migrations` sorted by filename.
- Tracks applied entries in `schema_migrations`.
- Stores `filename`, `checksum`, `applied_at`.
- If a previously applied migration file checksum changes, execution halts with clear mismatch error.
- Migration runner now guards begin/commit/rollback with transaction state checks because MySQL DDL may implicitly commit.
- Migration errors are rethrown with filename context (`Failed applying migration <file>: ...`) for clearer installer/admin diagnostics.

## How to add migrations
1. Add new file in `migrations/` with next sequence number, e.g. `0005_add_index.sql`.
2. Make SQL idempotent (`CREATE TABLE IF NOT EXISTS`, safe ALTER strategy).
3. Deploy.
4. Migration runs automatically (AUTO_MIGRATE) or manually via admin migrations page.

## API key encryption
- Secrets are stored encrypted at rest in `api_keys.key_value_encrypted`.
- Encryption algorithm: AES-256-GCM.
- Key source: `ENCRYPTION_KEY` from `config.local.php`.
- API keys are masked in admin list.
- Reveal action requires re-entering admin password.
- Multiple active `XAI_API_KEY` entries are selected round-robin.

## x.ai integration details
`lib/xai.php` exposes:
- `generate_image()`
- `generate_video()`
- `poll_job()`

Negative prompt behavior:
- If model supports negative prompt, sent directly.
- Else prompt fallback appends `Avoid: ...` and marks fallback in params.

## Manual FTP deployment instructions
- Deployment workflow: `.github/workflows/deploy-ftp.yml`.
- Trigger: `workflow_dispatch` only (manual only).
- Uses repo secrets: `PROD_FTP_HOST`, `PROD_FTP_USER`, `PROD_FTP_PASSWORD`, `PROD_ROOT_PATH`.
- Uploads standalone content directly to live root (`PROD_ROOT_PATH`) using **FTPS (TLS)**.
- No staging server used.

## Troubleshooting
- **Installer redirects unexpectedly**: check `installed.lock` presence.
- **DB connection failure**: verify DB host/user/pass and PDO MySQL extension.
- **Migration checksum mismatch**: do not edit shipped migration files after application; create a new migration instead.
- **`There is no active transaction` in installer**: this is usually a secondary error triggered after a migration SQL failure; inspect the original SQL/DB error above it and verify DB credentials/permissions and migration compatibility.
- **Admin installer step fails during migration**: `step_admin.php` now shows a guided message and suggests running `/installer/step_finish.php?mode=upgrade` to run migrations directly and reveal the exact SQL/DB failure.
- **No API key configured banner**: set active `XAI_API_KEY` in admin panel.
- **Generation failures**: inspect error messages in `generations.error_message` and verify x.ai base URL/API key.

## UI routing and navigation notes
- Main site entry now serves a marketing landing page at `/index.php` (root path `/`).
- `/app/create.php` remains the generator workspace.
- `/app/gallery.php` is the recent successful generations gallery.
- A shared, mobile-responsive global navbar is used on the landing page and app pages (home/generator/gallery/admin links).
- Shared styling is in `app/assets/css/style.css`; shared UI behaviors (including mobile nav toggle) are in `app/assets/js/app.js`.
- Mobile nav keeps the menu button at intrinsic width and opens a stacked dropdown panel to avoid full-width button overlap and cramped inline link wrapping in portrait layouts.
