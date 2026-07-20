# TASK-001: Foundation — schema, models, seeders, TicketService, settings + Inertia branding

status: implemented
rank: 1
assigned-executor: executor-opus
parallel-safe: false

## Goal
Build the data + service backbone for the Disdukcapil queue app: database schema, Eloquent models, seeders, the queue-numbering + call-flow service, a branding settings store, and Inertia sharing of branding. Every later screen (kiosk, operator, display, admin) depends only on this task. No user-facing screens are built here — this is backend + shared plumbing only.

## Context (assume you have read NOTHING else)
- Stack: **Laravel 13**, PHP 8.3, Inertia 3, Vue 3, Fortify auth, SQLite DB (`database/database.sqlite`, already migrated with users/cache/jobs/passkeys/2FA tables). Tests use **Pest** (`php artisan test`). Static analysis: `phpstan analyse` (Larastan). Formatter: `vendor/bin/pint`.
- Inertia middleware is `app/Http/Middleware/HandleInertiaRequests.php`; its `share()` currently shares `name`, `auth.user`, `sidebarOpen`.
- Controllers flash toasts via `Inertia::flash('toast', ['type' => 'success', 'message' => __('...')])`.
- The app is a queue system with two service lines KTP and KK. See domain model below.

### Domain model
**services** — a service line (KTP, KK). Full CRUD later.
- `id`, `name` (string), `prefix` (string, 1–3 chars, used in ticket code, e.g. `A`), `color` (string hex like `#2563eb`, nullable), `is_active` (bool, default true), `sort_order` (int, default 0), timestamps.

**lokets** — a counter belonging to a service.
- `id`, `service_id` (FK → services, cascade on delete), `name` (string, e.g. "Loket KTP 1"), `officer_name` (string, nullable), `is_active` (bool default true), `sort_order` (int default 0), timestamps.

**tickets** — one issued queue number.
- `id`, `service_id` (FK → services, cascade), `loket_id` (FK → lokets nullable, nullOnDelete), `number` (unsignedInteger — daily sequence per service), `code` (string, formatted e.g. `A-001`), `status` (string, default `waiting`; one of waiting|called|serving|skipped|done), `issued_date` (date), `called_at` (timestamp nullable), `served_at` (timestamp nullable), `finished_at` (timestamp nullable), timestamps. Index on `(service_id, issued_date, status)` and unique `(service_id, issued_date, number)`.

**settings** — key/value branding store.
- `id`, `key` (string unique), `value` (text nullable), timestamps.

## Files in scope
- `database/migrations/2026_07_20_000001_create_services_table.php` — new
- `database/migrations/2026_07_20_000002_create_lokets_table.php` — new
- `database/migrations/2026_07_20_000003_create_tickets_table.php` — new
- `database/migrations/2026_07_20_000004_create_settings_table.php` — new
- `app/Models/Service.php` — new
- `app/Models/Loket.php` — new
- `app/Models/Ticket.php` — new
- `app/Models/Setting.php` — new (with static `get($key, $default=null)` / `set($key, $value)` / `all()` helpers, cached)
- `app/Services/QueueService.php` — new (core queue logic)
- `app/Support/Branding.php` — new (resolve branding array from settings with defaults)
- `database/factories/ServiceFactory.php`, `LoketFactory.php`, `TicketFactory.php` — new
- `database/seeders/DatabaseSeeder.php` — edit: seed admin user, 2 services (KTP/KK), sample lokets, default settings
- `app/Http/Middleware/HandleInertiaRequests.php` — edit: share `branding` on every page
- `tests/Feature/QueueServiceTest.php` — new (Pest)

Do NOT edit `routes/web.php` or create controllers/Vue pages — those belong to later tasks.

## Implementation steps
1. **Migrations** — create the four tables exactly as specified in the domain model above. Use `foreignId(...)->constrained()->cascadeOnDelete()` for `service_id`; `loketId` nullable with `nullOnDelete()`. Add the described indexes on `tickets`.

2. **Models** (all `declare(strict_types=1)` optional but match starter style; add `$fillable` and `$casts`):
   - `Service`: fillable `name, prefix, color, is_active, sort_order`; casts `is_active`→bool, `sort_order`→int. Relations: `lokets()` hasMany, `tickets()` hasMany. Scope `active()` → `where('is_active', true)`.
   - `Loket`: fillable `service_id, name, officer_name, is_active, sort_order`; cast `is_active`→bool. Relations: `service()` belongsTo, `currentTicket()` — belongsTo latest ticket with status `called`/`serving` for this loket, or just a `tickets()` hasMany. Scope `active()`.
   - `Ticket`: fillable `service_id, loket_id, number, code, status, issued_date, called_at, served_at, finished_at`; casts `issued_date`→date, `called_at/served_at/finished_at`→datetime. Relations: `service()`, `loket()` belongsTo. Status constants: `const STATUS_WAITING='waiting'` etc. Scopes: `waiting()`, `forToday()` → `whereDate('issued_date', today())`.
   - `Setting`: fillable `key, value`. Static helpers using a request-level/`Cache` memo:
     - `Setting::get(string $key, $default=null)` — returns value or default.
     - `Setting::set(string $key, $value): void` — upserts and forgets cache.
     - `Setting::allValues(): array` — `[key => value]` map (used by Branding). Cache the map under key `settings.map` and forget it on `set()`.

3. **QueueService** (`app/Services/QueueService.php`) — the core. Methods:
   - `issue(Service $service): Ticket` — inside a DB transaction, compute the next `number` for `(service, today)` as `max(number)+1` (start at 1) locking rows, format `code = sprintf('%s-%03d', $service->prefix, $number)`, create ticket with status `waiting`, `issued_date = today()`. Return it.
   - `callNext(Loket $loket): ?Ticket` — inside a transaction with row locking: find the oldest `waiting` ticket for `loket->service_id` issued today (order by `number`), `lockForUpdate()`. If none, return null. Before calling next, mark any currently `called`/`serving` ticket of THIS loket as `done` (auto-finish the previous one). Set the found ticket `status=called`, `loket_id=loket->id`, `called_at=now()`. Save. Return it.
   - `recall(Ticket $ticket): Ticket` — bump `called_at = now()` (so the display re-announces); keep status. Return it.
   - `skip(Ticket $ticket): Ticket` — set `status=skipped`. Return it.
   - `finish(Ticket $ticket): Ticket` — set `status=done`, `finished_at=now()`. Return it.
   - `startServing(Ticket $ticket): Ticket` — set `status=serving`, `served_at=now()`. (Optional helper the operator screen may call; include it.)
   Use `DB::transaction()` and `->lockForUpdate()`. Numbering must be safe under concurrent issue() calls (that's why opus): two simultaneous issues for the same service must not get the same number — rely on the transaction + lock + the unique `(service_id, issued_date, number)` constraint. If a `QueryException` from the unique index occurs, retry once.

4. **Branding support** (`app/Support/Branding.php`) — a class with static `resolve(): array` returning:
   ```php
   [
     'app_name'      => Setting::get('app_name', config('app.name')),
     'logo_path'     => Setting::get('logo_path'),          // storage-relative or null
     'logo_url'      => $logoPath ? Storage::url($logoPath) : null,
     'primary_color' => Setting::get('primary_color', '#2563eb'),
     'accent_color'  => Setting::get('accent_color', '#16a34a'),
     'running_text'  => Setting::get('running_text', 'Selamat datang di Dinas Kependudukan dan Pencatatan Sipil'),
   ]
   ```
   Use the `public` disk for logo (`Storage::disk('public')`), so `logo_url` uses `Storage::disk('public')->url($logoPath)`.

5. **Inertia share** — in `HandleInertiaRequests::share()`, add `'branding' => \App\Support\Branding::resolve(),` to the returned array (keep existing keys).

6. **Factories** — standard factories: ServiceFactory (random name, prefix a random letter, color a hex, is_active true), LoketFactory (belongs to a Service, name, officer_name faker name), TicketFactory (belongs to Service, number sequence, code, status waiting, issued_date today).

7. **Seeders** — `DatabaseSeeder::run()`:
   - Create an admin user if none exists: `User::firstOrCreate(['email' => 'admin@disdukcapil.test'], ['name' => 'Admin Disdukcapil', 'password' => Hash::make('password'), 'email_verified_at' => now()])`.
   - Create services (idempotent via `firstOrCreate` on `name`): KTP → prefix `A`, color `#2563eb`, sort 1; KK → prefix `B`, color `#16a34a`, sort 2.
   - For each service create 2 sample lokets if none exist (e.g. "Loket KTP 1"/"Loket KTP 2", officer names "Budi"/"Siti"; "Loket KK 1"/"Loket KK 2").
   - Seed default settings via `Setting::set()` for `app_name` ("Antrian Disdukcapil"), `primary_color`, `accent_color`, `running_text` — only if not already set.
   - Make the seeder safe to run repeatedly (idempotent).

8. **Storage link** — ensure `php artisan storage:link` is documented; the branding logo needs the public disk. (Don't fail the task if the symlink already exists.)

9. **Test** (`tests/Feature/QueueServiceTest.php`, Pest) covering:
   - Issuing tickets for a service produces sequential codes `A-001`, `A-002`.
   - Numbering is per-service (KTP and KK have independent sequences starting at 1).
   - `callNext` returns the oldest waiting ticket and sets it `called` with the loket id; a second `callNext` on the same loket auto-finishes the previous ticket and returns the next.
   - `callNext` returns null when no waiting tickets.
   - `skip` / `finish` set the right statuses.

## Success criteria (every item mechanically verifiable)
- [ ] `php artisan migrate:fresh --seed` exits 0 and creates services (KTP, KK), lokets, and an admin user.
- [ ] `php artisan test --filter=QueueServiceTest` passes (all assertions green).
- [ ] `php artisan test` passes with **no previously-passing test failing**.
- [ ] `vendor/bin/pint --test` reports no style issues on the new/edited files (or run `vendor/bin/pint` to fix).
- [ ] `php artisan tinker --execute="echo App\Support\Branding::resolve()['app_name'];"` prints the seeded app name.
- [ ] `HandleInertiaRequests::share()` returns a `branding` key (grep confirms it references `Branding::resolve`).

## Implementation result (executor)

Files created:
- database/migrations/2026_07_20_000001_create_services_table.php
- database/migrations/2026_07_20_000002_create_lokets_table.php
- database/migrations/2026_07_20_000003_create_tickets_table.php
- database/migrations/2026_07_20_000004_create_settings_table.php
- app/Models/Service.php
- app/Models/Loket.php
- app/Models/Ticket.php
- app/Models/Setting.php
- app/Services/QueueService.php
- app/Support/Branding.php
- database/factories/ServiceFactory.php
- database/factories/LoketFactory.php
- database/factories/TicketFactory.php
- tests/Feature/QueueServiceTest.php

Files edited:
- database/seeders/DatabaseSeeder.php
- app/Http/Middleware/HandleInertiaRequests.php

Success-criteria output:
- `php artisan migrate:fresh --seed` → exit 0; seeded services KTP+KK (2), lokets (4), admin user admin@disdukcapil.test (1).
- `php artisan test --filter=QueueServiceTest` → 7 passed, 20 assertions.
- `php artisan test` → 46 passed, 156 assertions (no previously-passing test regressed).
- `vendor/bin/pint --test` on all new/edited files → passed. (Pre-existing `single_blank_line_at_eof` findings in tests/Feature/Auth/* etc. are untouched and out of scope.)
- `php artisan tinker --execute="echo App\Support\Branding::resolve()['app_name'];"` → prints "Antrian Disdukcapil".
- `HandleInertiaRequests::share()` returns `'branding' => Branding::resolve()`.

Note: A pre-existing corrupt `vendor/pest-plugins.json` listed the uninstalled `Pest\Drift\Plugin`, which crashed Pest. Regenerated it non-destructively via `composer dump-autoload` (build artifact only; no source or dependency changes).

## Out of scope
- No `routes/web.php` edits, no controllers, no Vue pages, no sidebar/nav changes.
- No websockets/broadcasting. No role/permission system.
- No CRUD UI — only models/service/seed/share plumbing.
