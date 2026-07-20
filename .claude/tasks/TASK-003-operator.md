# TASK-003: Operator console — call next / recall / skip / finish + polling

status: implemented
rank: 3
assigned-executor: executor-sonnet
parallel-safe: false

## Goal
Build the authenticated **Operator console** where an officer selects the loket they are staffing, then calls the next waiting number, recalls it (re-announce), skips it, or finishes it. The queue for the loket's service refreshes automatically via polling so the officer sees new arrivals without reloading.

## Context (assume you have read NOTHING else)
Stack: Laravel 13, Inertia 3, Vue 3, Fortify auth. **Layouts are resolved centrally in `resources/js/app.ts` by page name**: any page not matching Welcome/Kiosk/auth/settings falls to the `default` case → `AppLayout` (the sidebar layout). So `Operator.vue` gets AppLayout automatically — do NOT edit `app.ts` and do NOT import AppLayout in the page. To set the breadcrumb, use `defineOptions({ layout: { breadcrumbs: [{ title: 'Operator', href: '/operator' }] } })` exactly like `resources/js/pages/Dashboard.vue` does (that `layout` object is passed as props to the resolved AppLayout — it does NOT choose the layout). UI components in `resources/js/components/ui/*` (button, card, select, badge, separator). Toasts via vue-sonner + `Inertia::flash('toast', ...)`.

TASK-001 provides:
- `App\Models\Loket` (fields `service_id`, `name`, `officer_name`, `is_active`; relations `service()`; scope `active()`).
- `App\Models\Service`, `App\Models\Ticket` (statuses waiting|called|serving|skipped|done; scopes `waiting()`, `forToday()`).
- `App\Services\QueueService`: `callNext(Loket $loket): ?Ticket`, `recall(Ticket $ticket): Ticket`, `skip(Ticket $ticket): Ticket`, `finish(Ticket $ticket): Ticket`, `startServing(Ticket $ticket): Ticket`.

## Files in scope
- `app/Http/Controllers/OperatorController.php` — new.
- `routes/web.php` — edit: add inside the existing `auth`(+`verified`) group:
  - `GET /operator` → `OperatorController@index`, name `operator.index`
  - `GET /operator/state` → `OperatorController@state` (JSON), name `operator.state`
  - `POST /operator/{loket}/call` → `callNext`, name `operator.call`
  - `POST /operator/ticket/{ticket}/recall` → `recall`, name `operator.recall`
  - `POST /operator/ticket/{ticket}/skip` → `skip`, name `operator.skip`
  - `POST /operator/ticket/{ticket}/finish` → `finish`, name `operator.finish`
- `resources/js/pages/Operator.vue` — new.
- `resources/js/components/AppSidebar.vue` — edit: add a nav item "Loket / Operator" → `/operator` (icon from `@lucide/vue`, e.g. `MonitorSpeaker` or `Bell`).
- `tests/Feature/OperatorTest.php` — new (Pest).

## Implementation steps
1. **Controller** `OperatorController` (all methods behind auth):
   - `index()`: render `Operator` with `lokets` = `Loket::active()->with('service')->orderBy('sort_order')->get()` (id, name, officer_name, service name+color). The chosen loket is client-side state; no per-user assignment needed.
   - `state(Request $request)`: given `?loket_id=`, return JSON: the loket's `current` ticket (status called/serving for this loket today, if any) and `waiting` list (waiting tickets for that loket's service today, ordered by number — code + created_at), and `waiting_count`. Return `response()->json([...])`. Guard: if no loket_id or loket not found, return empty structure.
   - `callNext(Loket $loket)`: `app(QueueService::class)->callNext($loket)`; flash toast success ("Memanggil nomor {code}") or info ("Tidak ada antrian menunggu") if null; `return back()`.
   - `recall(Ticket $ticket)`, `skip(Ticket $ticket)`, `finish(Ticket $ticket)`: call the matching QueueService method, flash a toast, `return back()`.
2. **Routes** — add to the `auth`,`verified` middleware group in `routes/web.php`.
3. **Operator.vue** (wrapped in AppLayout like Dashboard.vue, breadcrumb "Operator"):
   - **Loket selector**: a `Select` (from ui/select) listing active lokets ("Loket KTP 1 — Budi"). Persist the selected loket id in `localStorage` so a terminal remembers its loket across reloads.
   - **Current call panel**: large display of the current called `code` for the selected loket (big text). If none, show "Belum ada panggilan".
   - **Action buttons** (disabled when no loket selected): 
     - "Panggil Berikutnya" (Call Next) → `router.post('/operator/'+loketId+'/call')`.
     - "Panggil Ulang" (Recall, disabled if no current) → `router.post('/operator/ticket/'+currentId+'/recall')`.
     - "Lewati" (Skip, disabled if no current) → `.../skip`.
     - "Selesai" (Finish, disabled if no current) → `.../finish`.
     All with `{ preserveScroll: true, onSuccess: () => fetchState() }`.
   - **Waiting queue list**: shows waiting count + the next several waiting codes for the loket's service. 
   - **Polling**: `fetchState()` uses `fetch('/operator/state?loket_id='+id)` → updates reactive `current`, `waiting`, `waiting_count`. Call on mount, on loket change, and every 3s via `setInterval` (clear on unmount). Guard against overlapping requests.
4. **Sidebar** — add the "Operator" nav item to `mainNavItems` in `AppSidebar.vue`.
5. **Test** `OperatorTest` (Pest, authenticated with a factory user):
   - `GET /operator` 200 with component `Operator`.
   - Seeding a waiting ticket, `POST /operator/{loket}/call` sets it to `called` with that loket.
   - `/operator/state?loket_id=` returns JSON with the current called ticket and waiting list.
   - `finish` sets status `done`.

## Success criteria
- [x] `php artisan test --filter=OperatorTest` passes.
- [x] `php artisan test` passes, no previously-passing test fails.
- [x] `npm run types:check` and `npm run lint:check` pass.
- [x] `vendor/bin/pint --test` clean.
- [x] Nav shows an Operator entry linking to `/operator`.

## Out of scope
- No changes to QueueService/models (use them as given). No kiosk/display changes. No role checks beyond `auth`.

## Execution notes (executor-sonnet)

### Files touched
- `app/Http/Controllers/OperatorController.php` — new. `index`, `state`, `callNext`, `recall`, `skip`, `finish`.
- `routes/web.php` — edited: added `OperatorController` import and the 6 operator routes inside the existing `auth`,`verified` group. No existing routes changed.
- `resources/js/pages/Operator.vue` — new. Loket `Select` persisted to `localStorage` (`operator.loket_id`), current-call panel, 4 action buttons wired to `router.post` with `preserveScroll` + `onSuccess: fetchState`, polling via `fetch('/operator/state?loket_id=...')` every 3s with an `isFetching` guard, cleared on unmount. Breadcrumb set via `defineOptions({ layout: { breadcrumbs: [...] } })` exactly like `Dashboard.vue`; no `AppLayout` import, `app.ts` untouched.
- `resources/js/components/AppSidebar.vue` — edited: imported `MonitorSpeaker` from `@lucide/vue`, added a "Loket / Operator" nav item → `/operator` to `mainNavItems`.
- `tests/Feature/OperatorTest.php` — new (Pest, 4 tests): index renders `Operator` component; `operator.call` sets a waiting ticket to `called` for the loket; `operator.state` returns the current called ticket + waiting list/count; `operator.finish` sets status to `done`.

Also ran `npm run build` (not a scope file, build artifacts only) so Inertia's Vite manifest includes the new `Operator.vue` chunk — required for the Inertia test requests to resolve the page instead of 500ing on a missing manifest entry.

### Success criteria output

`php artisan test --filter=OperatorTest`:
```
{"tool":"pest","result":"passed","tests":4,"passed":4,"assertions":16,"duration_ms":618}
```

`php artisan test` (full suite, no regressions):
```
{"tool":"pest","result":"passed","tests":52,"passed":52,"assertions":197,"duration_ms":2915}
```

`npm run types:check`: exits 0, no output (clean).

`npm run lint:check`: exits 0, no output (clean).

`vendor/bin/pint --test` on files created/edited (`app/Http/Controllers/OperatorController.php`, `routes/web.php`, `tests/Feature/OperatorTest.php`):
```
{"tool":"pint","result":"passed"}
```

Nav: "Loket / Operator" entry added to `mainNavItems` in `AppSidebar.vue`, linking to `/operator`, using the `MonitorSpeaker` icon (verified to exist in `@lucide/vue`'s type defs).

No deviations from spec.
