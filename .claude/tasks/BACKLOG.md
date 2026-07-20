# BACKLOG — Antrian Disdukcapil (Queue app for Loket KTP & KK)

> Planner: Claude Fable 5. Stack: Laravel 13 + Vue 3 + Inertia 3 + Fortify + Tailwind v4 + Reka UI (shadcn-vue) + Wayfinder + SQLite.
> Domain language: Bahasa Indonesia for user-facing labels; code/identifiers in English. Currency (if any) IDR.

## Product goal
A queue (antrian) system for **Disdukcapil** (Dinas Kependudukan dan Pencatatan Sipil) with two service lines — **KTP** and **KK (Kartu Keluarga)** — plus:
- **Kiosk** touch screen that issues a queue number per service line.
- **Number-caller / Display** screen (TV) that shows currently-called numbers and announces them with voice (TTS).
- **Operator console** where an officer at a loket calls the next number, recalls, skips, or finishes.
- **Admin dashboard** to create any number of lokets (each with a loket name + officer name), manage the two service lines, and customize **branding** (app name, logo, colors).

## Domain model (decided)
- **Service** (`services`): a service line. Seeded with KTP and KK. Editable name, `prefix` (letter used in ticket code, e.g. `A`/`B`), `color`, `is_active`, `sort_order`. Full CRUD.
- **Loket** (`lokets`): a counter/window belonging to a Service. Fields: `service_id`, `name` (e.g. "Loket KTP 1"), `officer_name`, `is_active`, `sort_order`. Full CRUD — "loket quantity created from dashboard".
- **Ticket / Antrian** (`tickets`): one issued queue number. Belongs to a Service; assigned a `loket_id` when called. Fields: `service_id`, `number` (int, resets daily per service), `code` (formatted, e.g. `A-001`), `status` (waiting|called|serving|skipped|done), `loket_id?`, `called_at?`, `served_at?`, `finished_at?`, `issued_date` (Y-m-d). Tickets flow into a **per-service** queue; any active loket of that service pulls the next `waiting` ticket.
- **Setting** (`settings`): key/value store for branding — `app_name`, `logo_path`, `primary_color`, `accent_color`, `running_text`. Resolved + shared to every page via Inertia.

## Key decisions / assumptions
1. **Real-time = HTTP polling** (setInterval → lightweight JSON endpoint every ~3s). No websockets/Reverb — robust for a LAN kiosk deployment, zero extra infra.
2. **Per-service queue**: kiosk picks a service (KTP/KK); ticket enters that service's queue; any loket of that service calls the next waiting ticket. (Not tied to a specific loket at issue time.)
3. **Numbering**: per-service daily sequence, formatted `{prefix}-{number:3}` (e.g. `A-001`), resets each calendar day via `issued_date`.
4. **Voice announcement**: browser Web Speech API (`speechSynthesis`, `id-ID`) on the Display screen — announces on newly-called / recalled tickets. Pure client-side; no audio assets required.
5. **Auth/roles**: operator + admin screens sit behind the existing Fortify `auth` middleware. Kiosk + Display are **public** (no login) so they can run unattended on a TV / kiosk terminal. Fine-grained roles (admin vs operator) are OUT OF SCOPE — any authenticated user can operate and administer.
6. **Branding** applied by injecting CSS variables from settings + sharing `app_name`/`logo` via Inertia so Kiosk/Display/Admin all reflect it.

## Ranked tasks
| Rank | Task | Executor | Depends on | Parallel-safe |
|---|---|---|---|---|
| 1 | TASK-001 Foundation: schema, models, seeders, TicketService, settings + Inertia branding share | executor-opus | — | no |
| 2 | TASK-002 Kiosk screen (public) — issue number, printable ticket | executor-sonnet | 001 | no |
| 3 | TASK-003 Operator console — call/recall/skip/finish + polling | executor-sonnet | 001 | no |
| 4 | TASK-004 Display/caller screen — board + running text + TTS | executor-sonnet | 001 | no |
| 5 | TASK-005 Admin — Services + Lokets CRUD, branding page, dashboard stats, nav | executor-sonnet | 001 | no |

Sequential execution: 001 is the backbone (everything depends on it). 002–005 each edit shared files (`routes/web.php`, sidebar, Inertia) so they run one at a time to avoid conflicts.

TASK-001 routed to **executor-opus** — it defines the DB schema, the daily-numbering concurrency behavior, and the transactional call-next logic (race-condition-sensitive core). The rest are standard feature screens on top of it → executor-sonnet.

## Global conventions all tasks must follow
- Controllers return `Inertia::render('Page', [...])`; flash toasts via `Inertia::flash('toast', ['type' => 'success', 'message' => __('...')])` (pattern already in `ProfileController`).
- Vue pages under `resources/js/pages/`; use existing UI components in `resources/js/components/ui/*` (button, card, input, label, select, dialog, badge, separator).
- Use Wayfinder route helpers where a page already imports from `@/routes`; otherwise plain string URLs are acceptable for new public screens.
- Run `vendor/bin/pint` (or `composer lint`) on PHP and keep `npm run types:check` + `npm run lint:check` green. Every task ends with `php artisan test` passing.
- Money (if ever): IDR. Dates: server tz from config. No `Co-Authored-By` trailers, no direct commits to main.
