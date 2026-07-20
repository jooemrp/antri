# TASK-005: Admin — Services + Lokets CRUD, branding settings, dashboard stats, nav

status: implemented
rank: 5
assigned-executor: executor-sonnet
parallel-safe: false

## Executor report

Files created:
- `app/Http/Controllers/Admin/ServiceController.php`
- `app/Http/Controllers/Admin/LoketController.php`
- `app/Http/Controllers/Admin/BrandingController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Requests/Admin/ServiceRequest.php`
- `app/Http/Requests/Admin/LoketRequest.php`
- `app/Http/Requests/Admin/BrandingRequest.php`
- `resources/js/pages/admin/Services.vue`
- `resources/js/pages/admin/Lokets.vue`
- `resources/js/pages/admin/Branding.vue`
- `tests/Feature/Admin/ServiceCrudTest.php`
- `tests/Feature/Admin/LoketCrudTest.php`
- `tests/Feature/Admin/BrandingTest.php`

Files edited:
- `routes/web.php` — replaced `Route::inertia('dashboard', 'Dashboard')` with `DashboardController@index`; added `admin/services`, `admin/lokets` resource routes and `admin/branding` GET/POST routes inside the existing `auth`,`verified` group.
- `resources/js/pages/Dashboard.vue` — real stat cards (Total Hari Ini, Menunggu, Selesai, Loket Aktif), per-service table, quick links to Kiosk/Display/Operator.
- `resources/js/components/AppSidebar.vue` — added "Loket" (`/admin/lokets`), "Layanan" (`/admin/services`), "Tampilan" (`/admin/branding`) to mainNavItems; added Kiosk/Display convenience links to the footer (opens in new tab via existing NavFooter behavior) alongside the pre-existing Repository/Documentation links. Did not duplicate the existing Dashboard/"Loket / Operator" items.

Success criteria output:
- `php artisan test --filter="ServiceCrudTest|LoketCrudTest|BrandingTest"` → `{"tool":"pest","result":"passed","tests":11,"passed":11,"assertions":44}`
- `php artisan test` (full suite) → `{"tool":"pest","result":"passed","tests":65,"passed":65,"assertions":254}`
- `npm run types:check` → no errors (vue-tsc --noEmit clean)
- `npm run lint:check` → no errors (eslint clean, exit code 0)
- `vendor/bin/pint --test` on all created/edited files → `{"tool":"pint","result":"passed"}`. Full-repo `vendor/bin/pint --test` shows only the same 12 pre-existing `single_blank_line_at_eof` failures in untouched test files, as expected per the task brief.
- `npm run build` → succeeded; Vite manifest includes `admin/Services`, `admin/Lokets`, `admin/Branding`, `Dashboard`.
- Sidebar confirmed to render "Loket" (`/admin/lokets`), "Layanan" (`/admin/services`), "Tampilan" (`/admin/branding`) nav items alongside the pre-existing Dashboard and "Loket / Operator" items.

No deviations from spec.

## Goal
Build the authenticated **admin** area: manage service lines (KTP/KK — rename, prefix, color), full CRUD for **lokets** (create any quantity, each with a loket name + officer name), a **branding** settings page (app name, logo upload, colors, running text), and turn the placeholder Dashboard into a real overview with today's stats. Wire everything into the sidebar nav.

## Context (assume you have read NOTHING else)
Stack: Laravel 13, Inertia 3, Vue 3, Fortify auth, Tailwind v4, Reka UI. **Layouts are resolved centrally in `resources/js/app.ts` by page name**: any page under `pages/admin/*` or `Dashboard` falls to the `default` case → `AppLayout` automatically. Do NOT edit `app.ts` and do NOT import AppLayout in these pages. Set breadcrumbs via `defineOptions({ layout: { breadcrumbs: [...] } })` like `resources/js/pages/Dashboard.vue` (this passes props to the resolved layout; it does not select it). Do NOT place admin pages under a `settings/` name — that would pull in SettingsLayout. Settings-style pages exist under `resources/js/pages/settings/*` and `resources/js/layouts/settings/*` — you may follow that pattern for the branding page but a plain AppLayout page is acceptable. UI components: `resources/js/components/ui/*` (button, card, input, label, select, dialog, badge, checkbox, separator). Toasts: `Inertia::flash('toast', ['type'=>'success','message'=>__('...')])`.

TASK-001 provides:
- `App\Models\Service` (fillable name, prefix, color, is_active, sort_order; scope `active()`; relations `lokets()`, `tickets()`).
- `App\Models\Loket` (fillable service_id, name, officer_name, is_active, sort_order; relation `service()`).
- `App\Models\Ticket` (statuses; scope `forToday()`).
- `App\Models\Setting` with `Setting::get/set/allValues`.
- `App\Support\Branding::resolve()` and Inertia-shared `branding`.
- Logo uses the **public** storage disk (`storage:link` already run in TASK-001; if not, run it).

## Files in scope
- `app/Http/Controllers/Admin/ServiceController.php` — resource-ish (index/store/update/destroy).
- `app/Http/Controllers/Admin/LoketController.php` — resource-ish (index/store/update/destroy).
- `app/Http/Controllers/Admin/BrandingController.php` — edit/update (handles logo upload + colors + app name + running text).
- `app/Http/Controllers/DashboardController.php` — new: replace the inertia-only dashboard route with a controller providing stats.
- `app/Http/Requests/Admin/*` — form requests for validation (ServiceRequest, LoketRequest, BrandingRequest) — or validate inline; prefer form requests.
- `routes/web.php` — edit: replace `Route::inertia('dashboard', 'Dashboard')` with `DashboardController@index`; add an `auth`,`verified` group `admin/*` with resource routes for services, lokets, and branding (`GET admin/branding`, `POST admin/branding`).
- `resources/js/pages/Dashboard.vue` — edit: show real stats.
- `resources/js/pages/admin/Services.vue` — new.
- `resources/js/pages/admin/Lokets.vue` — new.
- `resources/js/pages/admin/Branding.vue` — new.
- `resources/js/components/AppSidebar.vue` — edit: add nav items (Dashboard already there): "Lokets", "Layanan (Services)", "Tampilan/Branding". Also add convenience links to public "Kiosk" and "Display" (open in new tab) if trivial.
- `tests/Feature/Admin/ServiceCrudTest.php`, `LoketCrudTest.php`, `BrandingTest.php` — new (Pest).

## Implementation steps
1. **Services CRUD** (`Admin\ServiceController`, behind auth):
   - `index()` render `admin/Services` with all services (ordered by sort_order).
   - `store()` validate `name` (required), `prefix` (required, max 3), `color` (nullable hex), `is_active` (bool), `sort_order` (int); create; flash toast; back.
   - `update(Service $service)` same validation; update; toast; back.
   - `destroy(Service $service)` delete (cascade removes its lokets/tickets); toast; back. Guard: prevent deleting if it's the last active service? Not required — keep simple, allow delete.
2. **Lokets CRUD** (`Admin\LoketController`, behind auth):
   - `index()` render `admin/Lokets` with lokets (with service) + the list of services (for the create/edit select).
   - `store()` validate `service_id` (required, exists), `name` (required), `officer_name` (nullable), `is_active` (bool), `sort_order` (int); create; toast; back.
   - `update(Loket $loket)` / `destroy(Loket $loket)` similarly.
   - This is the "loket quantity created from dashboard" feature — admins add as many lokets as they want here.
3. **Branding** (`Admin\BrandingController`, behind auth):
   - `edit()` render `admin/Branding` with current values from `Branding::resolve()`.
   - `update()` validate: `app_name` (required, string), `primary_color` (required, hex like `/^#[0-9a-fA-F]{6}$/`), `accent_color` (same), `running_text` (nullable string), `logo` (nullable image, max 2048kb). Persist each via `Setting::set()`. If a `logo` file is uploaded, store on `public` disk (`$request->file('logo')->store('branding','public')`) and `Setting::set('logo_path', $path)`. Flash toast; `back()`.
   - Because branding is Inertia-shared, saved changes reflect app-wide on next navigation.
4. **Dashboard stats** (`DashboardController@index`): render `Dashboard` with today's numbers: total tickets issued today, waiting count, served/done count, per-service breakdown, active loket count. Use `Ticket::forToday()` aggregates.
5. **Routes**: 
   - Replace the `Route::inertia('dashboard', ...)` line with `Route::get('dashboard', [DashboardController::class,'index'])->name('dashboard')` inside the existing `auth`,`verified` group.
   - Add inside `auth`,`verified`:
     ```
     Route::resource('admin/services', Admin\ServiceController::class)->only(['index','store','update','destroy'])->names('admin.services');
     Route::resource('admin/lokets', Admin\LoketController::class)->only(['index','store','update','destroy'])->names('admin.lokets');
     Route::get('admin/branding', [Admin\BrandingController::class,'edit'])->name('admin.branding.edit');
     Route::post('admin/branding', [Admin\BrandingController::class,'update'])->name('admin.branding.update');
     ```
6. **Vue pages** (all wrapped in AppLayout, with breadcrumbs):
   - `admin/Services.vue`: table of services; a create form + edit dialog (use ui/dialog) with fields name, prefix, color (an `<input type="color">` plus hex), is_active checkbox, sort_order; delete with confirm dialog. Submit via `router.post/patch/delete` to the named routes (plain URLs like `/admin/services`, `/admin/services/{id}`).
   - `admin/Lokets.vue`: table of lokets (name, service badge, officer, active); create form + edit dialog with a service `Select`, name, officer_name, is_active, sort_order; delete confirm. This is the primary "add lokets" screen.
   - `admin/Branding.vue`: form with app_name, primary_color + accent_color (color inputs), running_text (textarea/input), logo file input with current-logo preview. Submit as multipart: `router.post('/admin/branding', { forceFormData: true, ... })`.
   - `Dashboard.vue`: replace placeholder tiles with real stat cards (Total hari ini, Menunggu, Selesai, Loket aktif) + per-service mini table, plus quick links to Kiosk / Display / Operator.
7. **Sidebar** (`AppSidebar.vue`): add nav items under mainNavItems: Dashboard (exists), "Loket" (`/admin/lokets`), "Layanan" (`/admin/services`), "Operator" (`/operator` — may already be added by TASK-003; if present, don't duplicate), "Tampilan" (`/admin/branding`). Use suitable `@lucide/vue` icons (e.g. `Building2`, `Layers`, `Palette`). Optionally footer links opening `/kiosk` and `/display` in new tabs.
8. **Tests** (Pest, authenticated user via factory):
   - ServiceCrudTest: create/update/delete a service persists.
   - LoketCrudTest: create a loket (assert row), update, delete.
   - BrandingTest: `POST /admin/branding` with app_name + colors persists via `Setting::get('app_name')`; uploading a fake image (`UploadedFile::fake()->image('logo.png')`, `Storage::fake('public')`) stores `logo_path`.

## Success criteria
- [ ] `php artisan test --filter="ServiceCrudTest|LoketCrudTest|BrandingTest"` passes.
- [ ] `php artisan test` passes, no previously-passing test fails.
- [ ] `npm run types:check` and `npm run lint:check` pass.
- [ ] `vendor/bin/pint --test` clean.
- [ ] After `POST /admin/branding` changing `app_name`, `Setting::get('app_name')` returns the new value.
- [ ] Creating a loket via `POST /admin/lokets` adds a row to `lokets`.
- [ ] Sidebar shows Lokets, Services (Layanan), and Branding (Tampilan) entries.

## Out of scope
- No role/permission separation (any authed user is admin). No changes to QueueService or the kiosk/display/operator page logic (may reference their routes for quick links only).
