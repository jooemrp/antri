# TASK-002: Kiosk screen (public) — issue queue number, printable ticket

status: implemented
rank: 2
assigned-executor: executor-sonnet
parallel-safe: false

## Goal
Build the public **Kiosk** touch screen: a full-screen page showing one large button per active service line (KTP, KK). Tapping a service issues a new queue number and shows a big printable ticket with the number, service name, date/time, and a print button. This is what a citizen uses at the office entrance. No login.

## Context (assume you have read NOTHING else)
Stack: Laravel 13, Inertia 3, Vue 3, Tailwind v4, Reka UI components in `resources/js/components/ui/*` (button, card). Wayfinder is available but plain URLs are fine for public pages. Toasts: vue-sonner already wired globally.

TASK-001 (already merged) provides:
- `App\Models\Service` (fields: `name`, `prefix`, `color`, `is_active`, `sort_order`; scope `active()`).
- `App\Models\Ticket` with statuses; `App\Services\QueueService::issue(Service $service): Ticket` — creates and returns a waiting ticket with a formatted `code` (e.g. `A-001`).
- Inertia shares `branding` (`app_name`, `logo_url`, `primary_color`, `accent_color`, `running_text`) on every page — access via `usePage().props.branding`.
- Controllers render with `Inertia::render(...)`.

## Files in scope
- `app/Http/Controllers/KioskController.php` — new: `index()` renders kiosk with active services; `store(Service $service)` (or by id) issues a ticket via QueueService and returns the ticket for display.
- `routes/web.php` — edit: add public routes:
  - `GET /kiosk` → `KioskController@index`, name `kiosk`
  - `POST /kiosk/{service}` → `KioskController@store`, name `kiosk.issue`
- `resources/js/pages/Kiosk.vue` — new: full-screen kiosk UI.
- `tests/Feature/KioskTest.php` — new (Pest).

## Implementation steps
1. **Controller** `KioskController`:
   - `index()`: `Inertia::render('Kiosk', ['services' => Service::active()->orderBy('sort_order')->get(['id','name','prefix','color'])])`.
   - `store(Service $service)`: call `app(QueueService::class)->issue($service)`; return `Inertia::render('Kiosk', ['services' => ...same..., 'issuedTicket' => ['code'=>$ticket->code, 'service_name'=>$service->name, 'issued_at'=>$ticket->created_at->toIso8601String()]])`. Use route-model binding on `{service}`. (Returning the same page with an `issuedTicket` prop lets the SPA show the ticket without a separate route.)
2. **Routes** — register the two public routes at the top of `routes/web.php` (outside the `auth` group). Keep existing routes intact.
3. **Kiosk.vue**:
   - Full-viewport layout (`min-h-screen`), centered. Show branding logo (`branding.logo_url` if present) + `branding.app_name` as a header. Large friendly Indonesian copy: heading "Silakan pilih layanan" (Please choose a service).
   - One big touch card/button per service, min height ~200px, large font, using the service `color` as accent (inline style `borderColor`/background tint). Label e.g. "AMBIL NOMOR ANTRIAN" + service name (KTP / Kartu Keluarga).
   - Tapping a service does `router.post('/kiosk/' + service.id, {}, { preserveScroll: true })` (Inertia `router` from `@inertiajs/vue3`).
   - When `issuedTicket` prop is present, show a **modal/overlay ticket**: big service name, very large `code` (e.g. `A-001`, text-7xl+), date/time, message "Silakan menunggu nomor Anda dipanggil". Buttons: "Cetak" (`window.print()`) and "Selesai / Ambil Lagi" (clears by `router.get('/kiosk')`).
   - Add a print stylesheet so only the ticket prints (a `@media print` block hiding everything except a `.ticket-print` container, or use a scoped `<style>`). Keep it simple.
   - Use existing `Button`/`Card` components where reasonable; inline Tailwind is fine for the kiosk-specific big layout. No app sidebar/layout — this is a standalone public page (do NOT wrap in AppLayout; render bare like `Welcome.vue`).
4. **Test** `KioskTest` (Pest):
   - `GET /kiosk` returns 200 and Inertia component `Kiosk` with services.
   - `POST /kiosk/{service}` issues a ticket (asserts a `tickets` row created for that service with status `waiting`) and returns the `issuedTicket` prop.

## Success criteria
- [ ] `php artisan test --filter=KioskTest` passes.
- [ ] `php artisan test` passes with no previously-passing test failing.
- [ ] `npm run types:check` passes (no TS errors in Kiosk.vue).
- [ ] `npm run lint:check` passes (or `npm run lint` to fix).
- [ ] `vendor/bin/pint --test` clean on new/edited PHP.
- [ ] Manual sanity (documented in task notes): visiting `/kiosk` shows service buttons; issuing shows a big number.

## Out of scope
- No calling/serving logic (that's the operator console). No display screen. No auth. Do not modify models or QueueService.

## Executor report

### Files touched
- `app/Http/Controllers/KioskController.php` — new
- `routes/web.php` — edited: added `GET /kiosk` (`kiosk`) and `POST /kiosk/{service}` (`kiosk.issue`) outside the `auth` group, existing routes untouched
- `resources/js/pages/Kiosk.vue` — new
- `tests/Feature/KioskTest.php` — new
- `resources/js/app.ts` — edited, OUTSIDE the listed "Files in scope", flagged here: added `case name === 'Kiosk':` alongside the existing `Welcome` case in the per-page `layout` resolver, so Kiosk renders bare (no `AppLayout`) exactly like `Welcome.vue`. This mirrors the codebase's only existing mechanism for a layout-less public page — the layout is resolved by component name in `app.ts`, not overridable per-component — so it was the minimal change to satisfy the spec's explicit "do NOT wrap in AppLayout; render bare like Welcome.vue" instruction. No other lines in `app.ts` were touched.
- Ran `npm run build` to refresh `public/build/manifest.json` / assets so the new `Kiosk.vue` page is resolvable by Inertia in tests (build artifacts, not source).

### Notes / deviations
- The working directory `e:/GitHub/antrian-app` is **not a git repository** (confirmed via `git status` → "fatal: not a git repository"). Per the executor instructions I was to work on a branch `task/002-kiosk`, but there is no `.git` to branch in. No commits were made (none were possible). This should be flagged to the user/planner — either the repo needs `git init`, or branching is not applicable in this environment.
- `usePage().props.branding` is typed `unknown` by the current shared-props typing (`sharedPageProps` in `resources/js/types/global.d.ts` only declares `name`, `auth`, `sidebarOpen`, plus an index signature). Rather than edit that out-of-scope type file, `Kiosk.vue` declares a local `Branding` interface and casts `usePage().props.branding as Branding` — kept entirely inside the in-scope file.

### Success criteria — actual output

`php artisan test --filter=KioskTest`:
```
{"tool":"pest","result":"passed","tests":2,"passed":2,"assertions":25,"duration_ms":632}
```

`php artisan test` (full suite, no regressions):
```
{"tool":"pest","result":"passed","tests":48,"passed":48,"assertions":181,"duration_ms":3168}
```

`npm run types:check`:
```
> types:check
> vue-tsc --noEmit
```
(no output = no errors)

`npm run lint:check`:
```
> lint:check
> eslint .
```
(no output = no errors)

`vendor/bin/pint --test`:
```
{"tool":"pint","result":"fail","files":[{"path":"tests\\Feature\\Auth\\AuthenticationTest.php",...}, ... 12 pre-existing files with "single_blank_line_at_eof" ...]}
```
This failure is pre-existing and unrelated to this task: all 12 flagged files are ones I did not touch (Auth tests, DashboardTest, ExampleTest, etc., all failing on a trailing-newline style rule). None of the files I created or edited (`app/Http/Controllers/KioskController.php`, `routes/web.php`, `tests/Feature/KioskTest.php`) appear in the failure list — they are pint-clean. The success criterion "clean on new/edited PHP" is met; the repo-wide `pint --test` was already red before this task.

Manual sanity: verified functionally via the automated feature tests above — `GET /kiosk` returns the `Kiosk` Inertia component with the active services list (inactive services excluded), and `POST /kiosk/{service}` creates a `waiting` ticket and returns it as `issuedTicket` with the correct sequential code. Did not additionally drive a browser.
