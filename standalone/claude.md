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
- Migration `0005_model_prompt_defaults.sql` now uses `information_schema` + dynamic `ALTER TABLE` statements (instead of `ADD COLUMN IF NOT EXISTS`) for compatibility with older MySQL/MariaDB versions.
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


## SQL compatibility guardrails (MySQL `ONLY_FULL_GROUP_BY`)
- Avoid `GROUP BY <entity_id>` queries that also select non-aggregated columns from joined media tables (`*_media.media_path`, `media_type`, etc.).
- For thumbnail/preview selection, use a correlated subquery with deterministic ordering instead, for example:
  - `SELECT ... (SELECT media_path FROM part_media WHERE part_id = p.id ORDER BY created_at DESC, id DESC LIMIT 1) AS thumbnail_path ...`
- This prevents production failures like: `Expression #N of SELECT list is not in GROUP BY ... incompatible with sql_mode=only_full_group_by`.
- Apply this pattern across create/gallery/library page queries whenever a single representative media row is needed per parent entity.

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
- App nav now consolidates character/scene/part links into a single **Customize** entry (`/app/customize.php`) that links out to Characters, Parts, and Scenes.
- Customize library pages (`/app/characters.php`, `/app/parts.php`, `/app/scenes.php`) now use a shared page header style (`Page Name` + `Characters | Parts | Scenes`) and open creation forms in mobile-friendly dialogs triggered by `+ New` buttons.
- Admin sessions can browse `/app/characters.php`, `/app/scenes.php`, and `/app/parts.php` without being forced back through `/app/login.php`; create actions on those pages still require a signed-in active user account.
- New creator pages:
  - `/app/characters.php` supports private/shared characters with metadata (age 20+, gender, penis size, boob size, height) and up to 20 reference photos.
  - `/app/scenes.php` supports private/public scene libraries split by type (`image` vs `video`), enforcing image uploads for image scenes and video uploads for video scenes.
  - `/app/parts.php` supports private/public body-part variation libraries with up to 40 mixed image/video assets.
- New migration `0007_characters_scenes_parts.sql` adds `characters`, `character_media`, `scenes`, `scene_media`, `parts`, and `part_media` tables used by the generator selectors and library pages.
- Generator model dropdown now truly re-renders per tab, so the Image tab only contains image models and the Video tab only contains video models.
- Session handling now sets secure/HttpOnly/Lax cookies with a 14-day lifetime (`session.gc_maxlifetime` + cookie lifetime), so user/admin sign-ins persist for roughly two weeks unless explicitly logged out.
- App pages (`/app/create.php`, `/app/gallery.php`, `/app/media.php`, `/app/characters.php`, `/app/scenes.php`, `/app/parts.php`) now guard initial DB load paths with exception handling and render inline error banners instead of failing as blank white pages when migrations/DB queries fail.


- Rebrand update: public/admin/app navigation now uses the **GetYourPics.com** name with a shared glowing transparent logo asset at `/app/assets/img/logo-glow.svg`; `/favicon.svg` reuses the same logo.
- Admin sessions now see generation activity in `/api/history.php` and `/app/gallery.php` even when no normal user session is active, so admin-triggered jobs no longer disappear from the generator history/gallery views.
- Generator submit feedback in `app/assets/js/app.js` now shows a human-readable "submitted/processing" message instead of dumping raw JSON.
- Base64 image outputs persisted by `api/tick.php` are now stored with a web path (`/storage/generated/<file>`) so previews render correctly in gallery/history/media views.
- `/api/toggle_visibility.php` and `/api/delete.php` now allow authenticated admin sessions to manage any gallery item while regular users remain restricted to their own generations.
- `lib/xai.php` image generation requests now send `resolution`/`aspect_ratio` instead of deprecated `size` to match xAI `/images/generations` API expectations and avoid HTTP 400 `Argument not supported: size` failures.
- `lib/xai.php` now normalizes legacy image resolution inputs (`1024x1024`, `2048x2048`, etc.) to xAI-supported enums (`1k` or `2k`) before calling `/images/generations`, preventing HTTP 422 resolution deserialization errors.
- `lib/xai.php` now normalizes video resolution inputs to xAI `/videos/generations` enums (`480p` or `720p`), including legacy/shared values like `1024x1024`, `1280x720`, and `1k`, preventing HTTP 422 errors for unsupported variants.
- `/app/media.php` now mirrors gallery item controls with inline **Download**, **Public/Private**, and **Delete** actions, and private item access now allows admins in addition to the owning user.
- `/api/download.php` now permits authenticated admin sessions to download private generation outputs while keeping owner/public restrictions for non-admin users.
- Model records support provider metadata (`api_provider`) while `/admin/model_edit.php` now enforces shared provider URL/API key usage from `/admin/keys.php` for existing models.
- `lib/xai.php` now resolves provider settings per model and uses model-specific credentials for generation/polling requests, with fallback to provider keys in `/admin/keys.php` when model-level keys are not set.
- `lib/xai.php` no longer falls back to `XAI_API_KEY` for non-xAI providers, so each model provider uses its own shared API key entry.
- `lib/xai.php` now resolves provider base URLs from provider-specific keys (`XAI_BASE_URL`, `OPENROUTER_BASE_URL`) with sensible defaults (`https://api.x.ai/v1`, `https://openrouter.ai/api/v1`) so xAI/OpenRouter models can share credentials from `/admin/keys.php` without per-model duplication.
- `/admin/keys.php` now auto-seeds blank shared provider key rows (`XAI_API_KEY`, `XAI_BASE_URL`, `OPENROUTER_API_KEY`, `OPENROUTER_BASE_URL`) when missing, and the manual preset helper forms were removed.
- `/admin/model_edit.php` now shows shared credential status hints only; model-specific base URL and API key controls were removed so individual model pages always fall back to `/admin/keys.php`.
- `/admin/model_edit.php` now explicitly loads `lib/crypto.php` before decrypting shared provider key rows, preventing fatal `undefined function decrypt_secret()` errors that previously rendered individual model pages as blank white screens.
- Migration `0008_model_provider_credentials_and_seed.sql` adds model API config columns and seeds these active models if missing: `Nous-Hermes 2 – Mixtral 8x7B`, `Dolphin 2.5 – Mixtral`, and `JOSIEFIED-Qwen3:8b`.
- Admin models UX polish: spacing added between toolbar and model list, add-model dialog now has a top-right close button, click-outside close behavior, and improved responsive dialog sizing for mobile.
- Migration `0009_seed_dual_type_extra_models.sql` seeds missing dual-type entries for the OpenRouter extras so `Nous-Hermes 2 – Mixtral 8x7B`, `Dolphin 2.5 – Mixtral`, and `JOSIEFIED-Qwen3:8b` each exist as both `image` and `video` models.

- Global generation defaults now live exclusively in `/admin/settings.php` (including custom prompt + custom negative prompt, seed, aspect ratio, resolution, duration, and fps) and are applied across all models.
- `/admin/models.php` and `/admin/model_edit.php` no longer expose per-model prompt/default fields; model pages now focus on model identity, provider credentials, and capability flags.
- `/admin/users.php` now provides a mobile-friendly management UX with a **New User** dialog (name/email/password), separate **User Requests** and **All Users** tables, inline email/password editing that saves on Enter, and Approve/Reject actions directly in the requests table.
- `/admin/users.php`, `/app/characters.php`, `/app/parts.php`, and `/app/scenes.php` now place their **New ...** action buttons in a dedicated toolbar immediately above their corresponding listing sections, using the same primary `form-btn` treatment as Models for consistent desktop/mobile UX.
- `/app/characters.php`, `/app/parts.php`, and `/app/scenes.php` now always render their Models-style **New ...** toolbar button above the "Available ..." section; admin sessions see the button in a disabled state with a tooltip so the control remains visible while still enforcing user-only creation.
- `/admin/keys.php` removed the `API Key Actions` helper card text and now uses a Models-style `New Key` primary button above the key list.
- Mobile nav behavior in `app/assets/js/app.js` now consistently closes/open state across taps, outside-clicks, link navigation, and viewport resizes to prevent flickering/inconsistent visibility of Customize/Admin/Login/Logout links.
- `/admin/index.php` and admin login success now route to `/admin/settings.php` so Admin nav + sign-in land on the main Settings page by default instead of Users.
- `lib/auth.php` now auto-links `admin_users` entries into active `users` rows with `role='admin'` during admin login, and admin-role user logins now initialize admin session flags so one admin account can use both user and admin capabilities.
- `/app/characters.php`, `/app/parts.php`, and `/app/scenes.php` no longer block creation actions for admin sessions; admin-role users can create/manage studio entities without switching accounts.
- `/admin/users.php` heading layout now uses the same top spacing pattern as other admin pages (Settings/Models) for visual consistency.
