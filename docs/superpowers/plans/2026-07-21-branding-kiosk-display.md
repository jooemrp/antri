# Branding, Kiosk Numbers & Display Theme Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire the sidebar to the configured app name, show latest issued/called numbers on the kiosk, add a light theme for the display screen, drop two starter-kit sidebar menus, and allow more than one running text.

**Architecture:** Everything user-configurable flows through the existing pipeline: rows in the `settings` table → `App\Support\Branding::resolve()` → the `branding` prop shared globally by `HandleInertiaRequests` → `usePage().props.branding` in Vue. Two new values join it (`running_texts`, `display_theme`). The kiosk gains a JSON polling endpoint mirroring the existing `/display/state`.

**Tech Stack:** Laravel 13 (PHP 8.3), Pest, Vue 3 + Inertia 3, TypeScript, Tailwind CSS v4, Vite.

## Global Constraints

- All user-facing copy is **Bahasa Indonesia**, matching the existing pages.
- Polling interval is **3000 ms**, matching `Display.vue` and `Operator.vue`.
- `resources/css/app.css` declares `@custom-variant dark (&:is(.dark *))`. This matches **descendants of** `.dark`, never the `.dark` element itself. Any element that toggles the class must therefore be a **wrapper around** the elements using `dark:` utilities.
- The `running_text` setting **key name does not change**; only its stored format becomes JSON.
- Commit messages: no `Co-Authored-By` trailer, no "Generated with" footer.
- Full verification suite, all four must pass before a task is done:
  `php artisan test`, `npm run lint:check`, `npm run types:check`, `npm run build`.

---

### Task 1: Branding backend — multiple running texts and display theme

**Files:**
- Modify: `app/Support/Branding.php`
- Modify: `app/Http/Requests/Admin/BrandingRequest.php`
- Modify: `app/Http/Controllers/Admin/BrandingController.php`
- Modify: `database/seeders/DatabaseSeeder.php:70`
- Test: `tests/Feature/Admin/BrandingTest.php`

**Interfaces:**
- Consumes: `App\Models\Setting::get()` / `::set()` (existing).
- Produces: `Branding::resolve()` returns an array whose keys are `app_name`, `logo_path`, `logo_url`, `primary_color`, `accent_color`, `running_texts` (`list<string>`), `display_theme` (`'dark'|'light'`). The key `running_text` is **removed** from the returned array. Tasks 4, 5 and 6 read these names.

- [ ] **Step 1: Write the failing tests**

Replace the `branding settings can be updated` test in `tests/Feature/Admin/BrandingTest.php` with the block below, and append the rest. Note the existing `uploading a logo` test also needs `display_theme` added to its payload — do that in this step too.

```php
test('branding settings can be updated', function () {
    $response = $this->post(route('admin.branding.update'), [
        'app_name' => 'Disdukcapil Kota Baru',
        'primary_color' => '#123456',
        'accent_color' => '#654321',
        'display_theme' => 'light',
        'running_texts' => ['Teks satu', 'Teks dua'],
    ]);

    $response->assertRedirect();
    expect(Setting::get('app_name'))->toBe('Disdukcapil Kota Baru');
    expect(Setting::get('primary_color'))->toBe('#123456');
    expect(Setting::get('accent_color'))->toBe('#654321');
    expect(Setting::get('display_theme'))->toBe('light');
    expect(Branding::resolve()['running_texts'])->toBe(['Teks satu', 'Teks dua']);
});

test('blank running texts are dropped before saving', function () {
    $this->post(route('admin.branding.update'), [
        'app_name' => 'Disdukcapil',
        'primary_color' => '#123456',
        'accent_color' => '#654321',
        'display_theme' => 'dark',
        'running_texts' => ['Teks satu', '   ', ''],
    ]);

    expect(Branding::resolve()['running_texts'])->toBe(['Teks satu']);
});

test('an invalid display theme is rejected', function () {
    $response = $this->post(route('admin.branding.update'), [
        'app_name' => 'Disdukcapil',
        'primary_color' => '#123456',
        'accent_color' => '#654321',
        'display_theme' => 'neon',
    ]);

    $response->assertSessionHasErrors('display_theme');
});

test('running texts decode from stored json', function () {
    Setting::set('running_text', json_encode(['Satu', 'Dua']));

    expect(Branding::resolve()['running_texts'])->toBe(['Satu', 'Dua']);
});

test('a legacy plain-string running text resolves as a single entry', function () {
    Setting::set('running_text', 'Teks lama');

    expect(Branding::resolve()['running_texts'])->toBe(['Teks lama']);
});

test('running texts fall back to the default when unset', function () {
    expect(Branding::resolve()['running_texts'])
        ->toBe(['Selamat datang di Dinas Kependudukan dan Pencatatan Sipil']);
});

test('an empty stored array means no running text', function () {
    Setting::set('running_text', json_encode([]));

    expect(Branding::resolve()['running_texts'])->toBe([]);
});

test('display theme defaults to dark', function () {
    expect(Branding::resolve()['display_theme'])->toBe('dark');
});
```

Add this import at the top of the file, below the existing `use` lines:

```php
use App\Support\Branding;
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=BrandingTest`
Expected: FAIL — `running_texts` is undefined on the resolved array, and `display_theme` is neither validated nor stored.

- [ ] **Step 3: Rewrite `Branding.php`**

Replace the whole class body:

```php
<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class Branding
{
    /**
     * The running text shown when nothing has been configured yet.
     */
    public const DEFAULT_RUNNING_TEXT = 'Selamat datang di Dinas Kependudukan dan Pencatatan Sipil';

    /**
     * Resolve the branding array from settings, applying defaults.
     *
     * @return array<string, mixed>
     */
    public static function resolve(): array
    {
        $logoPath = Setting::get('logo_path');

        return [
            'app_name' => Setting::get('app_name', config('app.name')),
            'logo_path' => $logoPath,
            'logo_url' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
            'primary_color' => Setting::get('primary_color', '#2563eb'),
            'accent_color' => Setting::get('accent_color', '#16a34a'),
            'running_texts' => self::runningTexts(),
            'display_theme' => Setting::get('display_theme', 'dark'),
        ];
    }

    /**
     * The running texts, decoded from the JSON array stored under `running_text`.
     *
     * Installs created before multi-text support hold a plain string under this
     * key; that value is returned as a single-entry list.
     *
     * @return list<string>
     */
    protected static function runningTexts(): array
    {
        $stored = Setting::get('running_text');

        if ($stored === null || $stored === '') {
            return [self::DEFAULT_RUNNING_TEXT];
        }

        $decoded = json_decode((string) $stored, true);

        if (! is_array($decoded)) {
            return [(string) $stored];
        }

        return self::clean($decoded);
    }

    /**
     * Trim entries and drop the blank ones, reindexing the result.
     *
     * @param  array<int|string, mixed>  $texts
     * @return list<string>
     */
    public static function clean(array $texts): array
    {
        $trimmed = array_map(fn (mixed $text): string => trim((string) $text), $texts);

        return array_values(array_filter($trimmed, fn (string $text): bool => $text !== ''));
    }
}
```

- [ ] **Step 4: Add the validation rules**

In `app/Http/Requests/Admin/BrandingRequest.php`, replace the `running_text` line with:

```php
            'running_texts' => ['nullable', 'array'],
            'running_texts.*' => ['string', 'max:500'],
            'display_theme' => ['required', 'in:dark,light'],
```

- [ ] **Step 5: Store the new values**

In `app/Http/Controllers/Admin/BrandingController.php`, replace the `Setting::set('running_text', ...)` line with:

```php
        Setting::set('running_text', json_encode(Branding::clean($validated['running_texts'] ?? [])));
        Setting::set('display_theme', $validated['display_theme']);
```

The `App\Support\Branding` import is already present in this file.

- [ ] **Step 6: Update the seeder**

In `database/seeders/DatabaseSeeder.php:70`, replace the `running_text` line with:

```php
            'running_text' => json_encode([\App\Support\Branding::DEFAULT_RUNNING_TEXT]),
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --filter=BrandingTest`
Expected: PASS, all tests green.

- [ ] **Step 8: Run the full backend suite**

Run: `php artisan test`
Expected: PASS. Nothing else reads `running_text` server-side, so no other test should move.

- [ ] **Step 9: Commit**

```bash
git add app/Support/Branding.php app/Http/Requests/Admin/BrandingRequest.php app/Http/Controllers/Admin/BrandingController.php database/seeders/DatabaseSeeder.php tests/Feature/Admin/BrandingTest.php
git commit -m "Support multiple running texts and a display theme setting"
```

---

### Task 2: Kiosk state endpoint

**Files:**
- Modify: `app/Http/Controllers/KioskController.php`
- Modify: `routes/web.php:11`
- Test: `tests/Feature/KioskTest.php`

**Interfaces:**
- Consumes: `App\Models\Service::active()`, `App\Models\Ticket::forToday()` (both existing scopes).
- Produces: route named `kiosk.state` at `GET /kiosk/state`, returning
  `{"services": [{"id": int, "name": string, "color": string|null, "last_issued": string|null, "last_called": string|null}]}`.
  Task 6 consumes this shape.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/KioskTest.php`:

```php
test('kiosk state returns the latest issued and called number per active service', function () {
    $service = Service::factory()->create(['is_active' => true, 'prefix' => 'A']);

    Ticket::factory()->create([
        'service_id' => $service->id,
        'number' => 9,
        'code' => 'A-009',
        'called_at' => now()->subMinutes(5),
    ]);
    Ticket::factory()->create([
        'service_id' => $service->id,
        'number' => 14,
        'code' => 'A-014',
        'called_at' => null,
    ]);

    $response = $this->get(route('kiosk.state'));

    $response->assertOk();
    $response->assertJsonPath('services.0.id', $service->id);
    $response->assertJsonPath('services.0.last_issued', 'A-014');
    $response->assertJsonPath('services.0.last_called', 'A-009');
});

test('kiosk state excludes inactive services', function () {
    Service::factory()->create(['is_active' => true]);
    Service::factory()->create(['is_active' => false]);

    $response = $this->get(route('kiosk.state'));

    $response->assertOk();
    $response->assertJsonCount(1, 'services');
});

test('kiosk state returns nulls when a service has no tickets', function () {
    Service::factory()->create(['is_active' => true]);

    $response = $this->get(route('kiosk.state'));

    $response->assertJsonPath('services.0.last_issued', null);
    $response->assertJsonPath('services.0.last_called', null);
});

test('kiosk state ignores tickets issued on a previous day', function () {
    $service = Service::factory()->create(['is_active' => true, 'prefix' => 'A']);

    Ticket::factory()->create([
        'service_id' => $service->id,
        'number' => 30,
        'code' => 'A-030',
        'issued_date' => today()->subDay(),
        'called_at' => now()->subDay(),
    ]);

    $response = $this->get(route('kiosk.state'));

    $response->assertJsonPath('services.0.last_issued', null);
    $response->assertJsonPath('services.0.last_called', null);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=KioskTest`
Expected: FAIL with `Route [kiosk.state] not defined.`

- [ ] **Step 3: Add the controller action**

In `app/Http/Controllers/KioskController.php`, add these imports below the existing ones:

```php
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
```

Then append this method to the class:

```php
    /**
     * Return the latest issued and latest called number for each active
     * service today, as JSON, for polling from the kiosk screen.
     */
    public function state(): JsonResponse
    {
        $services = Service::active()->orderBy('sort_order')->get(['id', 'name', 'color']);

        return response()->json([
            'services' => $services->map(function (Service $service): array {
                $lastIssued = Ticket::query()
                    ->where('service_id', $service->id)
                    ->forToday()
                    ->latest('number')
                    ->first(['code']);

                $lastCalled = Ticket::query()
                    ->where('service_id', $service->id)
                    ->forToday()
                    ->whereNotNull('called_at')
                    ->latest('called_at')
                    ->first(['code']);

                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'color' => $service->color,
                    'last_issued' => $lastIssued?->code,
                    'last_called' => $lastCalled?->code,
                ];
            })->values(),
        ]);
    }
```

- [ ] **Step 4: Register the route**

In `routes/web.php`, add directly below the `/kiosk` GET line:

```php
Route::get('/kiosk/state', [KioskController::class, 'state'])->name('kiosk.state');
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=KioskTest`
Expected: PASS, all tests green.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/KioskController.php routes/web.php tests/Feature/KioskTest.php
git commit -m "Add kiosk state endpoint for latest issued and called numbers"
```

---

### Task 3: Sidebar app name and menu removal

**Files:**
- Modify: `resources/js/components/AppLogo.vue`
- Modify: `resources/js/components/AppSidebar.vue:3`, `resources/js/components/AppSidebar.vue:59-68`

**Interfaces:**
- Consumes: `branding.app_name` from Task 1's resolved array (unchanged key, already shared).
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Read the app name from branding**

Replace the whole of `resources/js/components/AppLogo.vue`:

```vue
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const appName = computed(() => {
    const branding = usePage().props.branding as { app_name?: string } | undefined;

    return branding?.app_name || 'Laravel Starter Kit';
});
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{ appName }}</span>
    </div>
</template>
```

The `|| 'Laravel Starter Kit'` fallback covers the case where the component renders without the branding prop shared.

- [ ] **Step 2: Remove the two footer menu entries**

In `resources/js/components/AppSidebar.vue`, delete the `Repository` and `Documentation` objects from `footerNavItems`, leaving:

```ts
const footerNavItems: NavItem[] = [
    {
        title: 'Kiosk',
        href: '/kiosk',
        icon: LayoutGrid,
    },
    {
        title: 'Display',
        href: '/display',
        icon: MonitorSpeaker,
    },
];
```

- [ ] **Step 3: Drop the now-unused icon imports**

In the same file, change line 3 to:

```ts
import { Building2, Layers, LayoutGrid, MonitorSpeaker, Palette } from '@lucide/vue';
```

`BookOpen` and `FolderGit2` were used only by the deleted entries.

- [ ] **Step 4: Verify the frontend builds clean**

Run: `npm run lint:check && npm run types:check`
Expected: PASS with no unused-import errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/AppLogo.vue resources/js/components/AppSidebar.vue
git commit -m "Show configured app name in sidebar and drop starter-kit menus"
```

---

### Task 4: Branding admin UI

**Files:**
- Modify: `resources/js/pages/admin/Branding.vue`

**Interfaces:**
- Consumes: the `branding` prop from `BrandingController::edit()` — now carrying `running_texts: string[]` and `display_theme: string` (Task 1).
- Produces: posts `running_texts[]` and `display_theme` to `POST /admin/branding`.

- [ ] **Step 1: Update the props interface and form state**

In the `<script setup>` block, replace the `running_text` line in `BrandingProps` with:

```ts
    running_texts: string[];
    display_theme: 'dark' | 'light';
```

Replace the `running_text` line in the `form` reactive object with:

```ts
    running_texts: [...props.branding.running_texts],
    display_theme: props.branding.display_theme,
```

- [ ] **Step 2: Add the row add/remove helpers**

Add below the `onLogoChange` function:

```ts
function addRunningText() {
    form.running_texts.push('');
}

function removeRunningText(index: number) {
    form.running_texts.splice(index, 1);
}
```

- [ ] **Step 3: Submit the new fields**

In `submit()`, replace `running_text: form.running_text,` with:

```ts
            running_texts: form.running_texts,
            display_theme: form.display_theme,
```

- [ ] **Step 4: Import the trash icon and Button (Button is already imported)**

Add to the imports:

```ts
import { Trash2 } from '@lucide/vue';
```

- [ ] **Step 5: Replace the running-text field in the template**

Replace the whole `<div class="grid gap-2">` block containing `running-text` with:

```html
                    <div class="grid gap-2">
                        <Label>Teks Berjalan</Label>
                        <div
                            v-for="(_, index) in form.running_texts"
                            :key="index"
                            class="flex items-center gap-2"
                        >
                            <Input
                                v-model="form.running_texts[index]"
                                placeholder="Selamat datang..."
                                class="flex-1"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Hapus teks"
                                @click="removeRunningText(index)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                        <div>
                            <Button type="button" variant="outline" size="sm" @click="addRunningText">
                                Tambah Teks
                            </Button>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Semua teks ditampilkan berurutan pada layar display.
                        </p>
                        <InputError :message="errors.running_texts" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="display-theme">Tema Layar Display</Label>
                        <select
                            id="display-theme"
                            v-model="form.display_theme"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option value="dark">Gelap</option>
                            <option value="light">Terang</option>
                        </select>
                        <InputError :message="errors.display_theme" />
                    </div>
```

A native `<select>` is used deliberately — this form already uses native `<input type="color">` for the color pickers, so it matches.

- [ ] **Step 6: Verify the page compiles**

Run: `npm run lint:check && npm run types:check && npm run build`
Expected: PASS.

- [ ] **Step 7: Manually verify the round trip**

Run: `php artisan serve` in one terminal and `npm run dev` in another. Log in, open `/admin/branding`, add a second running text, set the theme to Terang, save. Reload the page.
Expected: both rows persist and the select still reads Terang.

- [ ] **Step 8: Commit**

```bash
git add resources/js/pages/admin/Branding.vue
git commit -m "Edit multiple running texts and display theme from Tampilan"
```

---

### Task 5: Display screen light mode and joined marquee

**Files:**
- Modify: `resources/js/pages/Display.vue`

**Interfaces:**
- Consumes: `branding.running_texts` and `branding.display_theme` (Task 1).
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Update the Branding interface**

In `Display.vue`, replace the `running_text: string;` line of the local `Branding` interface with:

```ts
    running_texts: string[];
    display_theme: 'dark' | 'light';
```

- [ ] **Step 2: Add the derived values**

Add below the `clockLabel` computed:

```ts
const isDark = computed(() => branding.value.display_theme !== 'light');

const marqueeText = computed(() => branding.value.running_texts.join('  •  '));
```

- [ ] **Step 3: Wrap the root element**

The `dark` variant is `&:is(.dark *)` — descendants only. The element carrying the class therefore cannot style itself, so a wrapper is required. Replace the opening of the template's root div:

```html
    <div :class="{ dark: isDark }">
        <div class="flex min-h-screen flex-col bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-white">
```

and add one extra `</div>` at the end of the template to close the wrapper.

- [ ] **Step 4: Give every colored element a light base**

Apply these class replacements inside the template. Left is current, right is the replacement.

| Element | Replace with |
|---|---|
| sound overlay `bg-black/80` | `bg-black/60 dark:bg-black/80` |
| header `border-white/10` | `border-slate-300 dark:border-white/10` |
| hero section `bg-blue-950/40` | `bg-blue-100 dark:bg-blue-950/40` |
| "Sedang Dipanggil" `text-blue-300` | `text-blue-700 dark:text-blue-300` |
| "Menuju ..." `text-blue-200` | `text-blue-700 dark:text-blue-200` |
| "Belum ada panggilan" `text-blue-200` | `text-blue-700 dark:text-blue-200` |
| loket card `bg-white/5` | `bg-white shadow-sm dark:bg-white/5 dark:shadow-none` |
| loket name `text-white/70` | `text-slate-600 dark:text-white/70` |
| footer `border-white/10 bg-black/40` | `border-slate-300 bg-white dark:border-white/10 dark:bg-black/40` |

The sound-overlay button (`bg-blue-600`, white text) reads correctly on both themes — leave it.

- [ ] **Step 5: Render the joined running texts**

Replace the footer's inner marquee div with:

```html
            <div v-if="marqueeText" class="marquee whitespace-nowrap text-2xl font-medium">
                <span class="mx-8">{{ marqueeText }}</span>
                <span class="mx-8">{{ marqueeText }}</span>
            </div>
```

The string is duplicated as before so the `translateX(-50%)` loop stays seamless.

- [ ] **Step 6: Verify it compiles**

Run: `npm run lint:check && npm run types:check && npm run build`
Expected: PASS.

- [ ] **Step 7: Manually verify both themes**

With the dev servers running, set Tema Layar Display to Terang in `/admin/branding`, then open `/display`.
Expected: light background with dark, readable text; all loket cards legible. Switch back to Gelap and confirm the screen is unchanged from before this task. Confirm both running texts scroll, separated by `•`.

- [ ] **Step 8: Commit**

```bash
git add resources/js/pages/Display.vue
git commit -m "Add light theme and multi-text marquee to the display screen"
```

---

### Task 6: Kiosk latest numbers

**Files:**
- Modify: `resources/js/pages/Kiosk.vue`

**Interfaces:**
- Consumes: `GET /kiosk/state` from Task 2.
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Update imports and the local Branding interface**

Change the `vue` import line to:

```ts
import { computed, onMounted, onUnmounted, ref } from 'vue';
```

In the local `Branding` interface, replace `running_text: string;` with:

```ts
    running_texts: string[];
    display_theme: 'dark' | 'light';
```

(The kiosk does not use either, but the interface must match the shared prop.)

- [ ] **Step 2: Add the state type and polling**

Add below the `branding` computed:

```ts
interface ServiceState {
    id: number;
    name: string;
    color: string | null;
    last_issued: string | null;
    last_called: string | null;
}

const serviceStates = ref<ServiceState[]>([]);

let isFetching = false;
let pollTimer: ReturnType<typeof setInterval> | undefined;

async function fetchState() {
    if (isFetching) {
        return;
    }

    isFetching = true;

    try {
        const response = await fetch('/kiosk/state');
        const data = await response.json();

        serviceStates.value = data.services;
    } finally {
        isFetching = false;
    }
}

onMounted(() => {
    fetchState();
    pollTimer = setInterval(fetchState, 3000);
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
});
```

The `isFetching` guard and the `onUnmounted` cleanup mirror `Display.vue` — a slow response must not stack up requests, and the timer must not outlive the page.

- [ ] **Step 3: Refresh the numbers after a ticket is issued**

A ticket taken on this kiosk should appear immediately rather than up to 3 seconds later. Change `issue()` to:

```ts
function issue(service: KioskService) {
    router.post(`/kiosk/${service.id}`, {}, {
        preserveScroll: true,
        onSuccess: () => fetchState(),
    });
}
```

- [ ] **Step 4: Render the strip below the buttons**

Inside `<main>`, directly after the closing `</div>` of the service-buttons grid, add:

```html
            <div v-if="serviceStates.length" class="grid w-full grid-cols-1 gap-4 sm:grid-cols-2 print:hidden">
                <div
                    v-for="state in serviceStates"
                    :key="state.id"
                    class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                >
                    <p
                        class="text-sm font-semibold tracking-wide uppercase"
                        :style="{ color: state.color ?? undefined }"
                    >
                        {{ state.name }}
                    </p>
                    <dl class="mt-3 grid gap-2">
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Terakhir diambil</dt>
                            <dd class="text-2xl font-bold text-slate-900 tabular-nums dark:text-slate-100">
                                {{ state.last_issued ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Sedang dipanggil</dt>
                            <dd class="text-2xl font-bold text-slate-900 tabular-nums dark:text-slate-100">
                                {{ state.last_called ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
```

`print:hidden` keeps the strip off the printed ticket, which is already isolated by the `.ticket-print` print rules.

- [ ] **Step 5: Verify it compiles**

Run: `npm run lint:check && npm run types:check && npm run build`
Expected: PASS.

- [ ] **Step 6: Manually verify**

With the dev servers running, open `/kiosk`. Take a KTP ticket.
Expected: "Terakhir diambil" jumps to the new code immediately. Call it from `/operator` in another tab and confirm "Sedang dipanggil" updates within ~3 seconds without a reload. Print the ticket and confirm the strip does not appear on the printout.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/Kiosk.vue
git commit -m "Show latest issued and called numbers on the kiosk"
```

---

### Task 7: Full verification and README update

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Run the whole suite**

Run: `php artisan test && npm run lint:check && npm run types:check && npm run build`
Expected: all four PASS.

- [ ] **Step 2: Update the README feature list**

In the "Fitur" section, update the two affected bullets:

- The **Kiosk** bullet gains: `Menampilkan nomor terakhir diambil dan nomor yang sedang dipanggil untuk tiap layanan.`
- The **Layar Display** bullet gains: `Tema gelap atau terang dapat dipilih dari dashboard.`
- The **Tampilan / Branding** bullet: change `running text` to `running text (bisa lebih dari satu)` and add `serta tema layar display`.

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "Document kiosk numbers, display theme, and multi-text running text"
```

---

## Self-Review

**Spec coverage:**

| Spec section | Task |
|---|---|
| 1. Sidebar app name | Task 3 |
| 2. Remove Repository / Documentation | Task 3 |
| 3. Multiple running texts — backend | Task 1 |
| 3. Multiple running texts — admin UI | Task 4 |
| 3. Multiple running texts — display footer | Task 5 |
| 4. Display light mode — setting | Tasks 1, 4 |
| 4. Display light mode — rendering | Task 5 |
| 5. Kiosk numbers — endpoint | Task 2 |
| 5. Kiosk numbers — UI | Task 6 |
| Testing — kiosk state | Task 2 |
| Testing — `Branding::resolve()` | Task 1 |
| Testing — branding update | Task 1 |
| Testing — suite stays green | Tasks 1, 7 |

No gaps.

**Type consistency:** `running_texts` (`string[]`) and `display_theme` (`'dark' | 'light'`) are named identically in `Branding::resolve()` (Task 1), the admin props (Task 4), `Display.vue` (Task 5) and `Kiosk.vue` (Task 6). The `/kiosk/state` keys `id`/`name`/`color`/`last_issued`/`last_called` match between Task 2's controller and Task 6's `ServiceState`. `Branding::clean()` is defined public in Task 1 Step 3 and called in Task 1 Step 5.

**Deviation from spec, deliberate:** Task 6 Step 3 refreshes the kiosk numbers on `onSuccess` after issuing a ticket, in addition to the 3-second poll. The spec specified polling only; this removes a visible lag on the one action the user just took, at the cost of one extra request per ticket.
