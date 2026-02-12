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
- `api/tick.php` now enforces a hard running-job timeout via `GENERATION_TIMEOUT_SECONDS` (default `3600` = 60 minutes). If exceeded before a final provider result is received, the generation is marked `failed` with a timeout error so jobs cannot stay in "Generating" forever.
- `api/tick.php` now recognizes additional async job id fields (`jobId`, `external_job_id`, nested `result/job/data` variants) so image/video jobs are not incorrectly failed when providers use non-`id` naming.
- `api/tick.php` now supports image responses that return `data[0].b64_json` by writing the decoded image into `storage/generated` and marking the generation `succeeded` immediately (no polling job id required).
- `lib/xai.php` now throws detailed provider errors for HTTP 4xx/5xx responses (includes method, endpoint, status code, and best-available provider message) so failures are diagnosable from gallery/history error text.
- `api/tick.php` now fails fast with explicit diagnostics when provider create responses return neither output media nor a pollable job id, instead of leaving ambiguous running/failure states.
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
- **Items stay in `Generating` too long**: ensure `/api/tick.php` is being called; jobs now auto-fail after `GENERATION_TIMEOUT_SECONDS` (default 60 minutes), so set a higher/lower value in config if your provider workloads need a different threshold.
- **`Running job is missing external_job_id.`**: provider create responses may be sync (`data[0].b64_json`) or async with alternate job id field names; `api/tick.php` now handles both patterns automatically, so this error usually indicates an unexpected provider payload—inspect the raw API response and model endpoint configuration.

## UI routing and navigation notes
- Main site entry now serves a marketing landing page at `/index.php` (root path `/`).
- `/app/create.php` remains the generator workspace.
- Generator `/app/create.php` now uses a two-tab switcher (Image/Video) so model selection and prompt drafting stay aligned per generation type; switching tabs preserves separate prompt + negative prompt drafts for each type.
- `/app/gallery.php` shows recent generations across in-progress and completed states, including status badges.
- A shared, mobile-responsive global navbar is used on the landing page and app pages (home/generator/gallery/admin links).
- Public/shared nav now shows **Admin** only when an admin session is active; signed-in admins also get an explicit admin logout link, while non-admin visitors only see **Login**.
- Shared styling is in `app/assets/css/style.css`; shared UI behaviors (including mobile nav toggle) are in `app/assets/js/app.js`.
- Public pages append a `?v=<filemtime>` cache-busting query string to shared CSS/JS includes so nav/button UI updates are not blocked by stale browser/CDN caches.
- Admin pages also use the shared global navbar + shared CSS/JS includes (with `?v=<filemtime>` cache busting) so mobile navigation and visual styling stay consistent across the entire site.
- Admin management pages (`/admin/settings.php`, `/admin/keys.php`, `/admin/models.php`, `/admin/migrations.php`) share the same in-page admin link row so navigation stays stable while switching sections; the logout link is intentionally omitted from that row.
- `/admin/models.php` now lists models as clickable cards that open `/admin/model_edit.php?id=<model-id>` for full editing.
- Models support `custom_prompt` and `custom_negative_prompt` defaults that are always merged into generation requests for that model (image or video).
- `/admin/keys.php` uses an **Add Key** button that opens a modal dialog for key creation; the old inline "Leave blank for new" ID field is removed from the create flow.
- The shared body background uses a single non-repeating top radial glow over a dark base color to avoid tiled/repeating artifacts on long mobile pages (e.g., gallery).
- Form submit buttons in studio pages use `.form-btn`; global form control width rules intentionally avoid all `<button>` elements so the mobile menu toggle retains intrinsic width.
- Mobile nav keeps the menu button at intrinsic width and opens a stacked dropdown panel to avoid full-width button overlap and cramped inline link wrapping in portrait layouts.
- `/app/login.php` is now the single shared login entry for both users and admins; the login form accepts admin usernames and redirects successful admin sign-ins to `/admin/users.php`.
- `/app/login.php` now wraps sign-in attempts in exception handling and surfaces detailed backend failure messages (for example migration/DB errors) directly in the login banner instead of failing as a blank white page.
- `/admin/users.php` now catches load/update exceptions and renders a clear on-page admin error banner so post-login failures are diagnosable.
- The login view links to account requests with a `Create Account` link that opens the create user request form (`/app/login.php?view=register`).
- Admins review/approve/reject user requests at `/admin/users.php`; admin section link rows now include **Users**.
- Generation requests now require an active account (`users.status = active`) or an authenticated admin session, and the generator submit button is disabled for non-approved visitors.
- `generations` now track `user_id` ownership and `is_public` visibility. Logged-in users see only their own generations in history/gallery; logged-out visitors see only public shared items.
- Gallery cards now include a share visibility toggle (`🔒 Private` / `🔗 Public`) so users can publish to the public gallery with username attribution.
- Model configuration now supports individual default fields (`seed`, `aspect_ratio`, `resolution`, `duration_seconds`, `fps`) surfaced directly in admin settings, add-model dialog, and model edit pages.
- Models page now uses a **New Model** button that opens an add-model dialog.
- Generator now includes contextual selectors for up to 3 characters, one scene, and multiple parts; selected entity names are appended as creative context in the generation prompt and persisted in `params_json`.
- New creator pages:
  - `/app/characters.php` supports private/shared characters with metadata (age 20+, gender, penis size, boob size, height) and up to 20 reference photos.
  - `/app/scenes.php` supports private/public scene libraries split by type (`image` vs `video`), enforcing image uploads for image scenes and video uploads for video scenes.
  - `/app/parts.php` supports private/public body-part variation libraries with up to 40 mixed image/video assets.
- New migration `0007_characters_scenes_parts.sql` adds `characters`, `character_media`, `scenes`, `scene_media`, `parts`, and `part_media` tables used by the generator selectors and library pages.
- Generator model dropdown now truly re-renders per tab, so the Image tab only contains image models and the Video tab only contains video models.
