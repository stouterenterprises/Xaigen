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
- Admin API keys page now renders each decrypted key value in an editable field for authenticated admins.
- Each key row provides inline **Save** and **Copy** actions next to the editable field; the old reveal/password re-check flow was removed.
- Delete action in the key rows is styled as a red destructive button with trash iconography.
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

- `/api/delete.php` deletes a generation row by id so users can remove items from gallery/history cards.
- `api/tick.php` now supports async provider flows: if create returns only a job id, records stay `running` and later ticks poll `/jobs/{id}` until an output URL is available.
- `api/tick.php` now also captures provider-supplied preview/thumbnail URLs while a job is still `running`, so gallery/history/media pages can show early visual progress before final output is ready.
- `api/tick.php` now persists polling exceptions into `generations.error_message` even while a job remains `running`, and clears stale errors once polling recovers or the job succeeds so stuck "Generating" items surface actionable diagnostics in gallery/history.
- Generator history cards and `/app/gallery.php` render media thumbnails (image/video), make the content area clickable to open media, and provide Download + Delete actions.
- `/app/gallery.php` now includes in-progress and completed generations with status badges (e.g., Generating, Generated, Failed), and each preview/title links to `/app/media.php?id=<generation-id>` for full-size viewing.
- `/app/media.php` provides a dedicated full media viewer page (image/video), current generation status, and quick Download/Back actions.

## Troubleshooting
- **Installer redirects unexpectedly**: check `installed.lock` presence.
- **DB connection failure**: verify DB host/user/pass and PDO MySQL extension.
- **Migration checksum mismatch**: do not edit shipped migration files after application; create a new migration instead.
- **`There is no active transaction` in installer**: this is usually a secondary error triggered after a migration SQL failure; inspect the original SQL/DB error above it and verify DB credentials/permissions and migration compatibility.
- **Admin installer step fails during migration**: `step_admin.php` now shows a guided message and suggests running `/installer/step_finish.php?mode=upgrade` to run migrations directly and reveal the exact SQL/DB failure.
- **No API key configured banner**: set active `XAI_API_KEY` in admin panel.
- **Generation failures**: inspect error messages in `generations.error_message` and verify x.ai base URL/API key.
- **Long-running videos show no visual progress**: previews are sourced from provider preview/thumbnail fields while status is `running`; if you still see placeholders, confirm `/api/tick.php` is being called regularly and that the provider response includes a preview URL.
- **Items stay in `Generating` with no obvious reason**: check gallery/history cards (or `/api/status.php?id=<id>`) for `error_message`; polling/network/provider exceptions are now saved there even before a job is marked failed.

## UI routing and navigation notes
- Main site entry now serves a marketing landing page at `/index.php` (root path `/`).
- `/app/create.php` remains the generator workspace.
- `/app/gallery.php` shows recent generations across in-progress and completed states, including status badges.
- A shared, mobile-responsive global navbar is used on the landing page and app pages (home/generator/gallery/admin links).
- Shared styling is in `app/assets/css/style.css`; shared UI behaviors (including mobile nav toggle) are in `app/assets/js/app.js`.
- Public pages append a `?v=<filemtime>` cache-busting query string to shared CSS/JS includes so nav/button UI updates are not blocked by stale browser/CDN caches.
- Admin pages also use the shared global navbar + shared CSS/JS includes (with `?v=<filemtime>` cache busting) so mobile navigation and visual styling stay consistent across the entire site.
- Admin management pages (`/admin/settings.php`, `/admin/keys.php`, `/admin/models.php`, `/admin/migrations.php`) share the same in-page admin link row so navigation stays stable while switching sections; the logout link is intentionally omitted from that row.
- `/admin/keys.php` uses an **Add Key** button that opens a modal dialog for key creation; the old inline "Leave blank for new" ID field is removed from the create flow.
- The shared body background uses a single non-repeating top radial glow over a dark base color to avoid tiled/repeating artifacts on long mobile pages (e.g., gallery).
- Form submit buttons in studio pages use `.form-btn`; global form control width rules intentionally avoid all `<button>` elements so the mobile menu toggle retains intrinsic width.
- Mobile nav keeps the menu button at intrinsic width and opens a stacked dropdown panel to avoid full-width button overlap and cramped inline link wrapping in portrait layouts.
