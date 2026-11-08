<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ImageUp,
    Moon,
    Palette,
    Plus,
    Sun,
    Trash2,
    TriangleAlert,
    X,
} from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { contrastRatio, readableTextColor } from '@/lib/color';
import { dashboard } from '@/routes';
import type { Branding, RunningText } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Tampilan', href: '/admin/branding' },
        ],
    },
});

const props = defineProps<{
    branding: Branding;
}>();

const MAX_LOGO_BYTES = 2 * 1024 * 1024; // mirrors BrandingRequest's max:2048
const MAX_TEXT_LENGTH = 500;

const form = useForm({
    app_name: props.branding.app_name,
    primary_color: props.branding.primary_color,
    accent_color: props.branding.accent_color,
    running_texts: props.branding.running_texts.map((line): RunningText => ({
        ...line,
    })),
    display_theme: props.branding.display_theme,
    logo: null as File | null,
});

// The display's running-text bar: a white bar on the light theme, and
// black/40 over slate-950 (≈ #01040e) on the dark theme. A line set to
// automatic (null) uses the theme's text colour.
const MARQUEE_BG = { light: '#ffffff', dark: '#01040e' } as const;
const MARQUEE_CUSTOM_START = { light: '#1d4ed8', dark: '#facc15' } as const;

const themeName = computed(() =>
    form.display_theme === 'dark' ? 'gelap' : 'terang',
);

/** Contrast of a custom line colour on the bar, or null when automatic. */
function lineContrast(line: RunningText): number | null {
    return line.color === null
        ? null
        : contrastRatio(line.color, MARQUEE_BG[form.display_theme]);
}

function useCustomColor(line: RunningText) {
    line.color = MARQUEE_CUSTOM_START[form.display_theme];
}

// Blank lines are dropped on save, so the preview skips them too.
const previewLines = computed(() =>
    form.running_texts.filter((line) => line.text.trim() !== ''),
);

const logoPreview = ref<string | null>(props.branding.logo_url);
const logoName = ref<string | null>(null);
const logoError = ref<string | null>(null);

const brandText = computed(() => readableTextColor(form.primary_color));
const isHex = (value: string) => /^#[0-9a-fA-F]{6}$/.test(value);

function onLogoChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    logoError.value = null;

    if (file && file.size > MAX_LOGO_BYTES) {
        logoError.value = `Ukuran file ${(file.size / 1024 / 1024).toFixed(1)} MB, maksimal 2 MB.`;
        input.value = '';

        return;
    }

    form.logo = file;
    logoName.value = file?.name ?? null;
    logoPreview.value = file
        ? URL.createObjectURL(file)
        : props.branding.logo_url;
}

async function addRunningText() {
    form.running_texts.push({ text: '', color: null });
    await nextTick();
    document
        .getElementById(`running-text-${form.running_texts.length - 1}`)
        ?.focus();
}

// Laravel reports per-row errors under dotted keys like "running_texts.2.text".
function runningTextError(
    index: number,
    field: 'text' | 'color',
): string | undefined {
    return (form.errors as Record<string, string | undefined>)[
        `running_texts.${index}.${field}`
    ];
}

function removeRunningText(index: number) {
    form.running_texts.splice(index, 1);
}

function moveRunningText(index: number, offset: -1 | 1) {
    const target = index + offset;
    const texts = form.running_texts;

    [texts[index], texts[target]] = [texts[target], texts[index]];
}

function discard() {
    form.reset();
    form.clearErrors();
    logoPreview.value = props.branding.logo_url;
    logoName.value = null;
    logoError.value = null;
}

function submit() {
    form.post('/admin/branding', {
        forceFormData: true,
        // Each line is {text, color}. The default "brackets" format sends
        // running_texts[][text] + running_texts[][color], which PHP splits
        // into separate entries; indices keep a line's fields together.
        queryStringArrayFormat: 'indices',
        preserveScroll: true,
        onSuccess: () => {
            // The server drops blank running texts; adopt its cleaned values
            // as the new baseline so the form reads as saved.
            form.running_texts = props.branding.running_texts.map((line) => ({
                ...line,
            }));
            form.display_theme = props.branding.display_theme;
            form.logo = null;
            form.defaults();
            logoPreview.value = props.branding.logo_url;
            logoName.value = null;
        },
    });
}

// --- Guard against losing unsaved edits -----------------------------------

function onBeforeUnload(event: BeforeUnloadEvent) {
    if (form.isDirty) {
        event.preventDefault();
    }
}

let removeRouterGuard: (() => void) | undefined;

onMounted(() => {
    window.addEventListener('beforeunload', onBeforeUnload);

    removeRouterGuard = router.on('before', (event) => {
        // Only guard navigation away, not this form's own submit.
        if (event.detail.visit.method !== 'get' || !form.isDirty) {
            return;
        }

        if (
            !window.confirm(
                'Perubahan tampilan belum disimpan. Tinggalkan halaman ini?',
            )
        ) {
            event.preventDefault();
        }
    });
});

onUnmounted(() => {
    window.removeEventListener('beforeunload', onBeforeUnload);
    removeRouterGuard?.();
});
</script>

<template>
    <Head title="Tampilan" />

    <form class="flex flex-1 flex-col" @submit.prevent="submit">
        <div class="flex-1 space-y-6 p-4 md:p-6">
            <header>
                <h1 class="text-2xl font-bold">Tampilan</h1>
                <p class="mt-1 text-muted-foreground">
                    Identitas yang tampil di kiosk, layar display, dan halaman
                    masuk.
                </p>
            </header>

            <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_26rem]">
                <div class="space-y-8">
                    <!-- Identity -->
                    <section
                        class="space-y-5 rounded-xl border bg-card p-6 shadow-xs"
                    >
                        <h2 class="text-lg font-semibold">Identitas</h2>

                        <div class="grid gap-2">
                            <Label for="app-name">Nama aplikasi</Label>
                            <Input
                                id="app-name"
                                v-model="form.app_name"
                                required
                            />
                            <InputError :message="form.errors.app_name" />
                        </div>

                        <div class="grid gap-2">
                            <span class="text-sm leading-none font-medium"
                                >Logo</span
                            >
                            <div class="flex items-center gap-4">
                                <div
                                    class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border bg-muted/50"
                                >
                                    <img
                                        v-if="logoPreview"
                                        :src="logoPreview"
                                        alt="Pratinjau logo"
                                        class="size-full object-contain p-1"
                                    />
                                    <AppLogoIcon
                                        v-else
                                        class="size-10 fill-current text-muted-foreground"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <label
                                        for="logo"
                                        class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-md border px-4 text-sm font-medium shadow-xs transition-colors focus-within:ring-[3px] focus-within:ring-ring/50 hover:bg-accent"
                                    >
                                        <ImageUp
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        {{
                                            logoPreview
                                                ? 'Ganti logo'
                                                : 'Unggah logo'
                                        }}
                                        <input
                                            id="logo"
                                            type="file"
                                            accept="image/*"
                                            class="sr-only"
                                            @change="onLogoChange"
                                        />
                                    </label>
                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            logoName ??
                                            'PNG, JPG, atau SVG · maksimal 2 MB'
                                        }}
                                    </p>
                                </div>
                            </div>
                            <InputError
                                :message="logoError ?? form.errors.logo"
                            />
                        </div>
                    </section>

                    <!-- Colours & theme -->
                    <section
                        class="space-y-5 rounded-xl border bg-card p-6 shadow-xs"
                    >
                        <h2 class="text-lg font-semibold">Warna &amp; tema</h2>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="primary-color">Warna utama</Label>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="form.primary_color"
                                        type="color"
                                        class="h-9 w-12 shrink-0 cursor-pointer rounded-md border border-input bg-transparent p-1"
                                        aria-label="Pilih warna utama"
                                    />
                                    <Input
                                        id="primary-color"
                                        v-model="form.primary_color"
                                        class="font-mono uppercase"
                                        maxlength="7"
                                        :aria-invalid="
                                            !isHex(form.primary_color)
                                        "
                                    />
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    Panel halaman masuk, strip tiket kiosk,
                                    label INFO, dan kartu besar display sebelum
                                    ada panggilan.
                                </p>
                                <InputError
                                    :message="form.errors.primary_color"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="accent-color">Warna aksen</Label>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="form.accent_color"
                                        type="color"
                                        class="h-9 w-12 shrink-0 cursor-pointer rounded-md border border-input bg-transparent p-1"
                                        aria-label="Pilih warna aksen"
                                    />
                                    <Input
                                        id="accent-color"
                                        v-model="form.accent_color"
                                        class="font-mono uppercase"
                                        maxlength="7"
                                        :aria-invalid="
                                            !isHex(form.accent_color)
                                        "
                                    />
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    Disimpan, tetapi saat ini belum dipakai di
                                    layar mana pun.
                                </p>
                                <InputError
                                    :message="form.errors.accent_color"
                                />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <span
                                id="theme-label"
                                class="text-sm leading-none font-medium"
                                >Tema layar display</span
                            >
                            <div
                                role="radiogroup"
                                aria-labelledby="theme-label"
                                class="inline-flex w-fit rounded-lg border bg-muted/50 p-1"
                            >
                                <button
                                    v-for="option in [
                                        {
                                            value: 'dark',
                                            label: 'Gelap',
                                            icon: Moon,
                                        },
                                        {
                                            value: 'light',
                                            label: 'Terang',
                                            icon: Sun,
                                        },
                                    ] as const"
                                    :key="option.value"
                                    type="button"
                                    role="radio"
                                    :aria-checked="
                                        form.display_theme === option.value
                                    "
                                    class="flex items-center gap-2 rounded-md px-4 py-1.5 text-sm font-medium transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                    :class="
                                        form.display_theme === option.value
                                            ? 'bg-background shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    @click="form.display_theme = option.value"
                                >
                                    <component
                                        :is="option.icon"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    {{ option.label }}
                                </button>
                            </div>
                            <InputError :message="form.errors.display_theme" />
                        </div>
                    </section>

                    <!-- Running text -->
                    <section
                        class="space-y-5 rounded-xl border bg-card p-6 shadow-xs"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">Teks berjalan</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Ditampilkan berurutan di bagian bawah layar
                                display. Setiap baris bisa diberi warna sendiri;
                                "Otomatis" mengikuti tema display ({{
                                    form.display_theme === 'dark'
                                        ? 'putih di tema gelap'
                                        : 'hitam di tema terang'
                                }}). Baris kosong diabaikan.
                            </p>
                        </div>

                        <ol class="space-y-3">
                            <li
                                v-for="(line, index) in form.running_texts"
                                :key="index"
                                class="space-y-1"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="w-5 text-right text-sm text-muted-foreground tabular-nums"
                                    >
                                        {{ index + 1 }}
                                    </span>
                                    <Input
                                        :id="`running-text-${index}`"
                                        v-model="line.text"
                                        :maxlength="MAX_TEXT_LENGTH"
                                        placeholder="Contoh: Pelayanan KK hanya sampai pukul 14:00"
                                        class="flex-1"
                                        :aria-label="`Teks berjalan ${index + 1}`"
                                    />

                                    <!-- Per-line colour: automatic (theme) or a custom pick. -->
                                    <Button
                                        v-if="line.color === null"
                                        type="button"
                                        variant="outline"
                                        class="shrink-0 gap-2 px-3"
                                        :aria-label="`Pilih warna untuk teks ${index + 1}`"
                                        @click="useCustomColor(line)"
                                    >
                                        <Palette
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Otomatis
                                    </Button>
                                    <div
                                        v-else
                                        class="flex h-9 shrink-0 items-center gap-1.5 rounded-md border pr-1 pl-1.5"
                                    >
                                        <input
                                            v-model="line.color"
                                            type="color"
                                            class="h-6 w-8 cursor-pointer rounded border-0 bg-transparent p-0"
                                            :aria-label="`Warna teks ${index + 1}`"
                                        />
                                        <span
                                            class="w-16 font-mono text-xs uppercase"
                                            >{{ line.color }}</span
                                        >
                                        <button
                                            type="button"
                                            class="rounded p-1 text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                            :aria-label="`Kembalikan warna otomatis untuk teks ${index + 1}`"
                                            @click="line.color = null"
                                        >
                                            <X class="size-4" />
                                        </button>
                                    </div>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        :disabled="index === 0"
                                        :aria-label="`Naikkan teks ${index + 1}`"
                                        @click="moveRunningText(index, -1)"
                                    >
                                        <ArrowUp class="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        :disabled="
                                            index ===
                                            form.running_texts.length - 1
                                        "
                                        :aria-label="`Turunkan teks ${index + 1}`"
                                        @click="moveRunningText(index, 1)"
                                    >
                                        <ArrowDown class="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:text-destructive"
                                        :aria-label="`Hapus teks ${index + 1}`"
                                        @click="removeRunningText(index)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                                <div class="flex justify-between gap-3 pl-7">
                                    <div class="space-y-1">
                                        <InputError
                                            :message="
                                                runningTextError(index, 'text')
                                            "
                                        />
                                        <InputError
                                            :message="
                                                runningTextError(index, 'color')
                                            "
                                        />
                                        <p
                                            v-if="
                                                (lineContrast(line) ?? 21) < 4.5
                                            "
                                            class="flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400"
                                        >
                                            <TriangleAlert
                                                class="size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                            Kontras hanya
                                            {{
                                                lineContrast(line)?.toFixed(1)
                                            }}:1 di tema {{ themeName }}, sulit
                                            dibaca dari jauh. Disarankan minimal
                                            4.5:1.
                                        </p>
                                    </div>
                                    <span
                                        v-if="
                                            line.text.length >
                                            MAX_TEXT_LENGTH - 100
                                        "
                                        class="ml-auto text-xs text-muted-foreground tabular-nums"
                                    >
                                        {{ line.text.length }}/{{
                                            MAX_TEXT_LENGTH
                                        }}
                                    </span>
                                </div>
                            </li>
                        </ol>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addRunningText"
                        >
                            <Plus class="size-4" aria-hidden="true" />
                            Tambah teks
                        </Button>
                        <InputError :message="form.errors.running_texts" />
                    </section>
                </div>

                <!-- Live preview: see the result before saving. -->
                <aside
                    class="space-y-4 xl:sticky xl:top-4 xl:self-start"
                    aria-label="Pratinjau"
                >
                    <h2
                        class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Pratinjau
                    </h2>

                    <figure class="space-y-2">
                        <!-- Mirrors the kiosk's floating header panel and the
                             printed ticket, where the primary colour now lives. -->
                        <div
                            class="space-y-3 overflow-hidden rounded-xl border bg-slate-50 p-3 shadow-xs"
                        >
                            <div
                                class="flex items-center gap-3 rounded-xl border border-slate-200/80 bg-white px-3 py-2 shadow-sm"
                            >
                                <img
                                    v-if="logoPreview"
                                    :src="logoPreview"
                                    alt=""
                                    class="h-7 w-auto shrink-0"
                                />
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-bold text-slate-900"
                                    >
                                        {{ form.app_name || 'Nama aplikasi' }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        Layanan antrian mandiri
                                    </p>
                                </div>
                                <span
                                    class="ml-auto text-base font-bold text-slate-900 tabular-nums"
                                    >08:30</span
                                >
                            </div>
                            <p
                                class="text-center text-sm font-semibold text-slate-900"
                            >
                                Silakan pilih layanan
                            </p>
                            <div
                                class="mx-auto w-36 overflow-hidden rounded-lg border border-slate-200 bg-white text-center shadow-sm"
                            >
                                <p
                                    class="truncate px-2 py-1 text-xs font-semibold tracking-widest uppercase"
                                    :style="{
                                        backgroundColor: form.primary_color,
                                        color: brandText,
                                    }"
                                >
                                    {{ form.app_name || 'Nama aplikasi' }}
                                </p>
                                <p
                                    class="py-2 text-2xl font-extrabold text-slate-900 tabular-nums"
                                >
                                    A-001
                                </p>
                            </div>
                        </div>
                        <figcaption class="text-sm text-muted-foreground">
                            Kiosk · header dan tiket
                        </figcaption>
                    </figure>

                    <figure class="space-y-2">
                        <div
                            class="overflow-hidden rounded-xl border shadow-xs"
                            :class="
                                form.display_theme === 'dark'
                                    ? 'bg-slate-950 text-white'
                                    : 'bg-slate-100 text-slate-900'
                            "
                        >
                            <div
                                class="flex items-center gap-2 border-b px-4 py-2 text-sm font-bold"
                                :class="
                                    form.display_theme === 'dark'
                                        ? 'border-white/10 bg-slate-900/60'
                                        : 'border-slate-200 bg-white'
                                "
                            >
                                <img
                                    v-if="logoPreview"
                                    :src="logoPreview"
                                    alt=""
                                    class="h-6 w-auto"
                                />
                                <span class="truncate">{{
                                    form.app_name || 'Nama aplikasi'
                                }}</span>
                            </div>
                            <div class="p-3">
                                <div
                                    class="rounded-lg px-4 py-6 text-center"
                                    :style="{
                                        backgroundColor: form.primary_color,
                                        color: brandText,
                                    }"
                                >
                                    <p
                                        class="text-xs font-semibold tracking-widest uppercase opacity-80"
                                    >
                                        Belum ada antrian dipanggil
                                    </p>
                                </div>
                            </div>
                            <div
                                class="flex items-stretch border-t text-xs"
                                :class="
                                    form.display_theme === 'dark'
                                        ? 'border-white/10 bg-black/40'
                                        : 'border-slate-200 bg-white'
                                "
                            >
                                <span
                                    class="flex items-center px-2 font-bold uppercase"
                                    :style="{
                                        backgroundColor: form.primary_color,
                                        color: brandText,
                                    }"
                                >
                                    Info
                                </span>
                                <span class="truncate px-3 py-2">
                                    <template
                                        v-for="(line, index) in previewLines"
                                        :key="index"
                                    >
                                        <span
                                            v-if="index > 0"
                                            aria-hidden="true"
                                            class="mx-2 opacity-40"
                                            >•</span
                                        >
                                        <span
                                            :style="{
                                                color: line.color ?? undefined,
                                            }"
                                            >{{ line.text }}</span
                                        >
                                    </template>
                                    <span
                                        v-if="previewLines.length === 0"
                                        class="opacity-60"
                                        >Teks berjalan akan tampil di sini</span
                                    >
                                </span>
                            </div>
                        </div>
                        <figcaption class="text-sm text-muted-foreground">
                            Display · tema
                            {{
                                form.display_theme === 'dark'
                                    ? 'gelap'
                                    : 'terang'
                            }}
                        </figcaption>
                    </figure>
                </aside>
            </div>
        </div>

        <!-- Appears only when there is something to save. -->
        <div
            v-if="form.isDirty || form.processing"
            class="sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-3 border-t bg-background/95 px-4 py-3 backdrop-blur md:px-6"
        >
            <p class="text-sm font-medium">
                Ada perubahan yang belum disimpan.
            </p>
            <div class="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    @click="discard"
                >
                    Batalkan
                </Button>
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Simpan perubahan
                </Button>
            </div>
        </div>
    </form>
</template>
