# TASK-004: Display / caller screen — board + running text + TTS announcement

status: implemented
rank: 4
assigned-executor: executor-sonnet
parallel-safe: false

## Goal
Build the public **Display** (number-caller) screen for a TV/monitor in the waiting room. It shows the currently-called number for each loket, a big "now calling" highlight, a running text ticker, branding, and it **announces new calls by voice** (Web Speech API, Indonesian). No login — runs unattended.

## Context (assume you have read NOTHING else)
Stack: Laravel 13, Inertia 3, Vue 3, Tailwind v4. Inertia shares `branding` (`app_name`, `logo_url`, `primary_color`, `accent_color`, `running_text`) — read via `usePage().props.branding`.

**IMPORTANT — how layouts are resolved:** The layout is chosen centrally in `resources/js/app.ts` by page NAME (a `layout: (name) => {...}` switch), NOT via `defineOptions` in the page. To render the Display page bare (no AppLayout, like `Welcome`/`Kiosk`), you MUST add a case for it in that switch: change the existing
`case name === 'Welcome': case name === 'Kiosk': return null;`
to also include `case name === 'Display':` before the `return null;`. This is a required, in-bounds edit for this task — add `resources/js/app.ts` to your working set. `usePage().props.branding` is typed loosely; declare a local `Branding` interface in `Display.vue` and cast, rather than editing shared type files.

TASK-001 provides `App\Models\Loket` (relations `service`), `App\Models\Ticket` (statuses; a ticket that is `called`/`serving` has `loket_id`, `code`, `called_at`), `App\Models\Service`.

## Files in scope
- `app/Http/Controllers/DisplayController.php` — new: `index()` renders the page; `state()` returns JSON of current calls.
- `routes/web.php` — edit: add public routes:
  - `GET /display` → `DisplayController@index`, name `display`
  - `GET /display/state` → `DisplayController@state`, name `display.state`
- `resources/js/pages/Display.vue` — new.
- `resources/js/app.ts` — edit: add `case name === 'Display':` to the bare-layout (`return null`) branch of the layout resolver (see Context).
- `tests/Feature/DisplayTest.php` — new (Pest).

## Implementation steps
1. **Controller** `DisplayController`:
   - `index()`: `Inertia::render('Display', ['lokets' => Loket::active()->with('service')->orderBy('sort_order')->get(['id','name','service_id'])])`.
   - `state()`: for each active loket, find its current ticket today (status `called` or `serving`, latest `called_at`). Return JSON array of `{ loket_id, loket_name, service_name, service_color, code, called_at }` (called_at ISO). Also return a `latest` object = the single most-recently-called ticket across all lokets (max `called_at`) so the client can decide what to announce. Shape: `response()->json(['calls' => [...], 'latest' => {code, loket_name} | null])`.
2. **Routes** — public, outside auth group.
3. **Display.vue** (bare page, dark high-contrast TV style):
   - Header: branding logo + `app_name`, big clock (current time, updates each second).
   - **Now-calling hero**: large panel showing the most-recent call — very large `code` (text-8xl+) and "Menuju {loket_name}". 
   - **Grid of lokets**: one tile per loket showing loket name, service name (accent color), and its current `code` (or "—"). 
   - **Running text ticker** at the bottom: horizontally scrolling `branding.running_text` (CSS marquee-style animation).
   - **Polling**: `fetch('/display/state')` every 3s. Track the last-announced key = `latest.code + '|' + latest.called_at`. When it changes to a new non-null value, **announce**:
     - Play a short chime (generate a beep via `AudioContext` oscillator — no asset needed), then
     - `speechSynthesis.speak(new SpeechSynthesisUtterance('Nomor antrian ' + speltCode + ', silakan menuju ' + loket_name))` with `utter.lang = 'id-ID'`. Spell the code so `A-001` is read clearly, e.g. announce as "A 001" (replace '-' with space; optionally read digits). Pick an `id-ID` voice if available from `speechSynthesis.getVoices()`.
     - Browsers block audio/speech until a user gesture. Add a one-time "Aktifkan Suara" (Enable sound) button overlay shown on first load; clicking it unlocks audio (resume AudioContext + speak an empty/again utterance) and hides the overlay. After that, announcements work.
   - Recall handling: because `recall()` bumps `called_at`, the announce key changes and it re-announces automatically — good.
   - Guard against overlapping fetches; clear intervals on unmount.
4. **Test** `DisplayTest` (Pest):
   - `GET /display` 200, component `Display`.
   - With a `called` ticket assigned to a loket, `GET /display/state` returns that loket's code and a non-null `latest`.

## Success criteria
- [ ] `php artisan test --filter=DisplayTest` passes.
- [ ] `php artisan test` passes, no previously-passing test fails.
- [ ] `npm run types:check` and `npm run lint:check` pass.
- [ ] `vendor/bin/pint --test` clean.
- [ ] `/display/state` returns JSON with `calls` and `latest` keys (documented sample in task notes).

## Out of scope
- No calling logic (read-only view of state produced by the operator console). No auth. No model/service changes.

## Execution notes

Files touched:
- `app/Http/Controllers/DisplayController.php` (new)
- `routes/web.php` (edit — added public `GET /display` and `GET /display/state` routes, outside the `auth` group)
- `resources/js/pages/Display.vue` (new)
- `resources/js/app.ts` (edit — added `case name === 'Display':` to the bare-layout branch)
- `tests/Feature/DisplayTest.php` (new)
- `public/build/**` regenerated via `npm run build` (required so Inertia can resolve `Display` in tests)

Sample `/display/state` response shape (from a manual tinker check against temporary data, cleaned up afterward):
```json
{
  "calls": [
    {
      "loket_id": 5,
      "loket_name": "Loket 1",
      "service_name": "Layanan KTP",
      "service_color": "#2563eb",
      "code": "A-001",
      "called_at": "2026-07-20T08:36:20+00:00"
    },
    {
      "loket_id": 6,
      "loket_name": "Loket 2",
      "service_name": "Layanan KTP",
      "service_color": "#2563eb",
      "code": null,
      "called_at": null
    }
  ],
  "latest": {
    "code": "A-001",
    "loket_name": "Loket 1",
    "called_at": "2026-07-20T08:36:20+00:00"
  }
}
```

### Success criteria — actual output

- `php artisan test --filter=DisplayTest` → `{"tool":"pest","result":"passed","tests":2,"passed":2,"assertions":13}`
- `php artisan test` → `{"tool":"pest","result":"passed","tests":54,"passed":54,"assertions":210}` (no regressions)
- `npm run types:check` → passes, no output/errors (vue-tsc --noEmit clean)
- `npm run lint:check` → passes, exit 0, no output (eslint clean)
- `vendor/bin/pint --test app/Http/Controllers/DisplayController.php routes/web.php tests/Feature/DisplayTest.php` → `{"tool":"pint","result":"passed"}`
- `npm run build` → succeeded, `Display-BIt5j6di.js` chunk emitted

### Deviations / notes
- None from spec. One process note: verifying the `/display/state` JSON shape via `php artisan tinker` writes to the real dev database (not the testing DB), since tinker doesn't use `RefreshDatabase`. The inserted Service/Loket/Ticket rows were identified precisely by ID and deleted immediately after capturing the sample output; final counts were confirmed to match pre-existing dev-DB state (0 tickets, 4 lokets, 2 services).
