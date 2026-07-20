# Branding, Kiosk Numbers & Display Theme — Design

Date: 2026-07-21

## Goal

Six changes to the antrian app, grouped because five of them touch the same
branding settings pipeline (`Setting` → `Branding::resolve()` → shared Inertia
props):

1. Sidebar header shows the configured app name instead of "Laravel Starter Kit".
2. Kiosk shows the latest issued and latest called queue number per service.
3. Display screen supports a light theme.
4. Remove the "Repository" menu from the dashboard sidebar.
5. Remove the "Documentation" menu from the dashboard sidebar.
6. Branding supports more than one running text.

## Existing state

- `App\Support\Branding::resolve()` returns `app_name`, `logo_path`, `logo_url`,
  `primary_color`, `accent_color`, `running_text`. It is shared globally as the
  `branding` Inertia prop, so every page can read it via `usePage()`.
- `/admin/branding` ("Tampilan") already edits app name, logo, colors and a
  single running text.
- `resources/js/components/AppLogo.vue` hardcodes the string
  `"Laravel Starter Kit"` — this is why the sidebar ignores the configured name.
- `Display.vue` polls `GET /display/state` every 3 seconds. Kiosk does not poll.
- `resources/css/app.css` declares `@custom-variant dark (&:is(.dark *))`, so
  `dark:` variants activate from *any* ancestor carrying `.dark` — dark mode can
  be scoped to a single subtree rather than `<html>`.

## Changes

### 1. Sidebar app name

`AppLogo.vue` reads `branding.app_name` from the shared page props, falling back
to `"Laravel Starter Kit"` when the prop is absent (the component also renders on
pages without branding shared). No new menu is added — `/admin/branding` already
edits this value.

### 2. Remove Repository and Documentation menus

Delete both entries from `footerNavItems` in `AppSidebar.vue`, and remove the
`BookOpen` and `FolderGit2` imports that those entries were the only users of.
The "Kiosk" and "Display" footer entries stay.

### 3. Multiple running texts

The `running_text` setting changes from a plain string to a **JSON-encoded array
of strings**, stored under the same key.

- `Branding::resolve()` returns a new `running_texts: string[]` key. Resolution:
  `json_decode` the stored value; if it decodes to an array, use it; otherwise
  treat the raw stored value as a single-element array. This keeps existing
  installs (which hold a plain string) working without a data migration. When
  nothing is stored, the default is the current single welcome sentence.
- `Branding::resolve()` no longer returns `running_text`. Callers are updated in
  the same change.
- `BrandingRequest` validates `running_texts` as `array`, each entry
  `string|max:500`. Empty/whitespace-only entries are dropped before saving.
  An empty array is valid and means "no running text".
- `BrandingController::update()` stores `json_encode($validated['running_texts'])`.
- `DatabaseSeeder` seeds `running_text` as a JSON array with the one existing
  sentence.

The display footer joins all entries with `•` into the existing marquee and
duplicates the joined string once, as it already does, to keep the scroll loop
seamless. With an empty array the footer is not rendered.

`admin/Branding.vue` replaces the single "Teks Berjalan" input with a repeatable
list: one `Input` per entry, a trash button per row, and a "Tambah Teks" button
below. Rows submit as `running_texts[]`.

### 4. Display light mode

A new `display_theme` setting holds `'dark'` or `'light'`, defaulting to
`'dark'`. `Branding::resolve()` exposes it; `BrandingRequest` validates it with
`in:dark,light`; `admin/Branding.vue` edits it with a select labelled "Tema Layar
Display" (Gelap / Terang).

`Display.vue` binds `:class="{ dark: branding.display_theme === 'dark' }"` on its
root element. Every color utility inside is rewritten as a light-mode base plus a
`dark:` variant — e.g. `bg-white text-slate-900` / `dark:bg-slate-950
dark:text-white`. Because the variant is scoped to the display's own wrapper, it
never touches `<html>` and cannot interfere with the dashboard's per-user
appearance setting.

Admin-controlled rather than a button on the screen: display TVs are unattended,
so the setting must survive a reboot and needs no one standing there to click.

### 5. Kiosk latest numbers

New route `GET /kiosk/state` (public, alongside `/kiosk`) handled by
`KioskController::state()`, returning JSON:

```json
{
  "services": [
    { "id": 1, "name": "KTP", "color": "#2563eb",
      "last_issued": "A-014", "last_called": "A-009" }
  ]
}
```

- Active services only, ordered by `sort_order` — the same ordering the kiosk
  buttons use.
- `last_issued`: code of the newest ticket created today for that service.
- `last_called`: code of the newest ticket with a non-null `called_at` today.
- Either may be `null` when no ticket qualifies.
- "Today" uses the same day-scoping the existing queue numbering uses, so the
  numbers reset daily in step with the codes.

`Kiosk.vue` polls this endpoint every 3 seconds (matching `Display.vue`, using
the same in-flight guard and `onUnmounted` cleanup) and renders a card per
service **below** the service buttons:

```
KTP        Terakhir diambil  A-014
           Sedang dipanggil  A-009
```

`—` is shown where a value is null. The strip is hidden from print output so it
does not appear on a printed ticket.

## Testing

- `KioskController::state()` — returns one entry per active service; excludes
  inactive services; `last_issued` / `last_called` are null with no tickets;
  tickets from a previous day are excluded; `last_called` ignores tickets that
  were issued but never called.
- `Branding::resolve()` — decodes a JSON array; wraps a legacy plain string in a
  one-element array; returns the default when unset; `display_theme` defaults to
  `'dark'`.
- Branding update — accepts a `running_texts` array and persists it as JSON;
  drops blank entries; rejects a `display_theme` outside dark/light.
- The existing suite must stay green (`php artisan test`), and
  `npm run lint` / `npm run build` must pass.

## Out of scope

- Changing how branding colors are applied anywhere.
- Rotating or individually-timed running texts — all entries scroll in one
  continuous marquee joined by `•`, reusing the existing animation.
- A light theme for any screen other than `/display`.
- Websocket / real-time transport — polling stays the mechanism.
