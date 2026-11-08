<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { BellRing, Volume2 } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { readableTextColor } from '@/lib/color';

interface DisplayLoket {
    id: number;
    name: string;
    service_id: number;
    service: {
        id: number;
        name: string;
        color: string | null;
    } | null;
}

interface Call {
    loket_id: number;
    loket_name: string;
    service_name: string | null;
    service_color: string | null;
    code: string | null;
    called_at: string | null;
}

interface Latest {
    code: string;
    loket_name: string;
    called_at: string;
}

defineProps<{
    lokets: DisplayLoket[];
}>();

const branding = computed(() => usePage().props.branding);

const calls = ref<Call[]>([]);
const latest = ref<Latest | null>(null);
const now = ref(new Date());
const soundEnabled = ref(false);

let isFetching = false;
let pollTimer: ReturnType<typeof setInterval> | undefined;
let clockTimer: ReturnType<typeof setInterval> | undefined;
let lastAnnouncedKey: string | null = null;
let audioContext: AudioContext | null = null;

const clockLabel = computed(() =>
    now.value.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }),
);

const dateLabel = computed(() =>
    now.value.toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
);

const brandText = computed(() =>
    readableTextColor(branding.value.primary_color),
);

// Codes are "<prefix>-<number>" (see QueueService::issue); the prefix is shown
// in the service colour, matching the prefix tile visitors tapped on the kiosk.
function splitCode(code: string): { prefix: string; number: string } {
    const dash = code.indexOf('-');

    return dash === -1
        ? { prefix: '', number: code }
        : { prefix: code.slice(0, dash), number: code.slice(dash + 1) };
}

// The latest call carries the loket name, not its id.
function isLatestCall(call: Call): boolean {
    return (
        latest.value !== null &&
        call.code === latest.value.code &&
        call.loket_name === latest.value.loket_name
    );
}

// The big card wears the colour of the service just called, falling back to
// the brand colour before any call (or for a service without a colour).
const heroColor = computed(
    () => calls.value.find(isLatestCall)?.service_color ?? branding.value.primary_color,
);

const heroText = computed(() => readableTextColor(heroColor.value));

const isDark = computed(() => branding.value.display_theme !== 'light');

const marqueeText = computed(() => branding.value.running_texts.join('  •  '));

// Calibrated so the default single sentence (56 characters) scrolls at the
// pre-existing ~20s pace; longer joined text takes proportionally longer
// instead of scrolling faster, and a floor keeps very short text readable.
const MARQUEE_CHARS_PER_SECOND = 56 / 20;
const MARQUEE_MIN_DURATION_SECONDS = 10;

const marqueeDurationSeconds = computed(() =>
    Math.max(MARQUEE_MIN_DURATION_SECONDS, marqueeText.value.length / MARQUEE_CHARS_PER_SECOND),
);

// The display's html.dark class is driven by branding.display_theme for as
// long as this page is mounted (see app.css's `dark` variant, which matches
// any descendant of a `.dark` ancestor — the local wrapper class alone can't
// override an already-dark <html>). The original state is restored on
// unmount so navigating away (e.g. back to the dashboard) doesn't leave the
// dashboard's own theme flipped.
let originalHtmlDark = false;

function applyHtmlDarkClass() {
    document.documentElement.classList.toggle('dark', isDark.value);
}

watch(isDark, applyHtmlDarkClass);

// useAppearance's global listener re-derives html.dark from the OS preference
// whenever that changes, which would silently undo the theme chosen here — a
// real scenario for a display left running through a scheduled sunset switch.
// Ours is registered on mount, so it runs after theirs and re-asserts.
const systemThemeQuery = typeof window === 'undefined' ? null : window.matchMedia('(prefers-color-scheme: dark)');

function spellCode(code: string): string {
    return code.replace(/-/g, ' ');
}

function playChime() {
    if (!audioContext) {
        return;
    }

    const oscillator = audioContext.createOscillator();
    const gain = audioContext.createGain();

    oscillator.type = 'sine';
    oscillator.frequency.setValueAtTime(880, audioContext.currentTime);
    gain.gain.setValueAtTime(0.001, audioContext.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.3, audioContext.currentTime + 0.05);
    gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.6);

    oscillator.connect(gain);
    gain.connect(audioContext.destination);

    oscillator.start();
    oscillator.stop(audioContext.currentTime + 0.6);
}

function speak(text: string) {
    if (typeof window === 'undefined' || !('speechSynthesis' in window)) {
        return;
    }

    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = 'id-ID';

    const voices = window.speechSynthesis.getVoices();
    const idVoice = voices.find((voice) => voice.lang === 'id-ID');

    if (idVoice) {
        utterance.voice = idVoice;
    }

    window.speechSynthesis.speak(utterance);
}

function announce(call: Latest) {
    playChime();

    const text = `Nomor antrian ${spellCode(call.code)}, silakan menuju ${call.loket_name}`;

    speak(text);
}

function enableSound() {
    if (typeof window === 'undefined') {
        return;
    }

    const AudioContextClass = window.AudioContext ?? (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
    audioContext = new AudioContextClass();

    if (audioContext.state === 'suspended') {
        audioContext.resume();
    }

    if ('speechSynthesis' in window) {
        const unlock = new SpeechSynthesisUtterance('');
        window.speechSynthesis.speak(unlock);
    }

    soundEnabled.value = true;
}

async function fetchState() {
    if (isFetching) {
        return;
    }

    isFetching = true;

    try {
        const response = await fetch('/display/state');

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        calls.value = data.calls;
        latest.value = data.latest;

        if (latest.value) {
            const key = `${latest.value.code}|${latest.value.called_at}`;

            if (key !== lastAnnouncedKey) {
                lastAnnouncedKey = key;

                if (soundEnabled.value) {
                    announce(latest.value);
                }
            }
        }
    } catch {
        // Network or parsing failure — keep the last-known state on screen.
    } finally {
        isFetching = false;
    }
}

onMounted(() => {
    originalHtmlDark = document.documentElement.classList.contains('dark');
    applyHtmlDarkClass();
    systemThemeQuery?.addEventListener('change', applyHtmlDarkClass);

    fetchState();
    pollTimer = setInterval(fetchState, 3000);
    clockTimer = setInterval(() => {
        now.value = new Date();
    }, 1000);
});

onUnmounted(() => {
    systemThemeQuery?.removeEventListener('change', applyHtmlDarkClass);
    document.documentElement.classList.toggle('dark', originalHtmlDark);

    if (pollTimer) {
        clearInterval(pollTimer);
    }

    if (clockTimer) {
        clearInterval(clockTimer);
    }
});
</script>

<template>
    <Head title="Display" />

    <div :class="{ dark: isDark }">
        <div class="flex min-h-dvh flex-col bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-white">
            <div
                v-if="!soundEnabled"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-6 backdrop-blur-sm"
            >
                <div class="flex max-w-lg flex-col items-center gap-6 rounded-3xl bg-white p-10 text-center text-slate-900 shadow-2xl dark:bg-slate-900 dark:text-white">
                    <span
                        class="flex size-20 items-center justify-center rounded-full"
                        :style="{ backgroundColor: branding.primary_color, color: brandText }"
                    >
                        <Volume2 class="size-10" aria-hidden="true" />
                    </span>
                    <div class="space-y-3">
                        <h2 class="text-3xl font-bold">Aktifkan Suara</h2>
                        <p class="text-lg text-slate-600 dark:text-slate-400">
                            Browser memerlukan satu sentuhan sebelum layar ini dapat membunyikan bel dan suara panggilan.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="w-full rounded-2xl px-8 py-5 text-2xl font-bold shadow-lg transition hover:brightness-110 focus-visible:ring-4 focus-visible:ring-slate-900 focus-visible:ring-offset-2 focus-visible:outline-none motion-reduce:transition-none dark:focus-visible:ring-white dark:focus-visible:ring-offset-slate-900"
                        :style="{ backgroundColor: branding.primary_color, color: brandText }"
                        @click="enableSound"
                    >
                        Aktifkan Suara
                    </button>
                </div>
            </div>

            <header class="flex items-center justify-between gap-8 border-b border-slate-200 bg-white px-10 py-5 dark:border-white/10 dark:bg-slate-900/60">
                <div class="flex min-w-0 items-center gap-5">
                    <img
                        v-if="branding.logo_url"
                        :src="branding.logo_url"
                        :alt="branding.app_name"
                        class="h-16 w-auto"
                    />
                    <div class="min-w-0">
                        <h1 class="truncate text-3xl font-bold">{{ branding.app_name }}</h1>
                        <p class="text-lg text-slate-500 dark:text-white/60">Sistem Antrian</p>
                    </div>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-5xl font-bold tabular-nums">{{ clockLabel }}</p>
                    <p class="text-lg text-slate-500 dark:text-white/60">{{ dateLabel }}</p>
                </div>
            </header>

            <main class="grid flex-1 gap-8 p-8 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
                <section
                    aria-live="polite"
                    class="relative flex min-h-96 flex-col items-center justify-center overflow-hidden rounded-4xl p-10 text-center shadow-xl transition-colors duration-700 motion-reduce:transition-none"
                    :style="{ backgroundColor: heroColor, color: heroText }"
                >
                    <!-- Decorative rings; purely visual. -->
                    <div
                        aria-hidden="true"
                        class="pointer-events-none absolute -top-24 -right-24 size-96 rounded-full border-[2.5rem] border-current opacity-10"
                    />
                    <div
                        aria-hidden="true"
                        class="pointer-events-none absolute -bottom-32 -left-20 size-80 rounded-full bg-current opacity-5"
                    />

                    <template v-if="latest">
                        <p class="relative rounded-full bg-current/15 px-6 py-2 text-2xl font-semibold tracking-widest uppercase">
                            Nomor Antrian
                        </p>
                        <p
                            :key="`${latest.code}|${latest.called_at}`"
                            class="call-enter relative mt-8 text-[clamp(6rem,12vw,13rem)] leading-none font-extrabold tracking-tight tabular-nums"
                        >
                            {{ latest.code }}
                        </p>
                        <p class="relative mt-8 text-2xl opacity-80">Silakan menuju</p>
                        <p class="relative mt-1 text-5xl font-bold">{{ latest.loket_name }}</p>
                    </template>
                    <template v-else>
                        <BellRing class="relative size-20 opacity-60" aria-hidden="true" />
                        <p class="relative mt-6 text-4xl font-semibold">Belum ada antrian dipanggil</p>
                        <p class="relative mt-2 text-xl opacity-80">Nomor antrian berikutnya akan tampil di sini</p>
                    </template>
                </section>

                <section aria-labelledby="serving-title" class="flex min-h-0 flex-col gap-4">
                    <h2
                        id="serving-title"
                        class="text-2xl font-bold tracking-widest text-slate-500 uppercase dark:text-white/60"
                    >
                        Sedang Dilayani
                    </h2>
                    <div
                        v-if="calls.length"
                        class="grid flex-1 auto-rows-fr gap-5 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        <div
                            v-for="call in calls"
                            :key="call.loket_id"
                            class="relative flex min-h-44 flex-col justify-between gap-4 rounded-2xl bg-white p-6 shadow-sm transition-shadow duration-300 [--frame:#0f172a] dark:bg-white/5 dark:shadow-none dark:[--frame:#ffffff]"
                            :class="{ 'shine-frame': isLatestCall(call) }"
                            :style="call.service_color ? { '--frame': call.service_color } : undefined"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-2xl font-bold">{{ call.loket_name }}</p>
                                    <p
                                        v-if="call.service_name"
                                        class="mt-1 truncate text-base font-semibold tracking-wide text-slate-500 uppercase dark:text-white/60"
                                    >
                                        {{ call.service_name }}
                                    </p>
                                </div>
                                <p
                                    v-if="isLatestCall(call)"
                                    class="shrink-0 rounded-full bg-slate-900 px-3 py-1 text-sm font-semibold text-white dark:bg-white dark:text-slate-900"
                                >
                                    Baru dipanggil
                                </p>
                            </div>
                            <p
                                v-if="call.code"
                                class="flex items-center gap-3 text-6xl font-extrabold tracking-tight tabular-nums"
                            >
                                <span
                                    v-if="splitCode(call.code).prefix"
                                    class="flex h-[1.15em] min-w-[1.15em] items-center justify-center rounded-xl px-2 text-[0.7em]"
                                    :style="{
                                        backgroundColor: call.service_color ?? 'rgb(100 116 139)',
                                        color: readableTextColor(call.service_color ?? '#64748b'),
                                    }"
                                >
                                    {{ splitCode(call.code).prefix }}
                                </span>
                                <span>{{ splitCode(call.code).number }}</span>
                            </p>
                            <p v-else class="text-6xl font-extrabold text-slate-300 dark:text-white/25">—</p>
                        </div>
                    </div>
                    <div
                        v-else
                        class="flex flex-1 items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-2xl text-slate-500 dark:border-white/15 dark:text-white/60"
                    >
                        Belum ada loket aktif
                    </div>
                </section>
            </main>

            <footer
                v-if="marqueeText"
                class="flex items-stretch overflow-hidden border-t border-slate-200 bg-white dark:border-white/10 dark:bg-black/40"
            >
                <p
                    class="relative z-10 flex shrink-0 items-center px-8 text-xl font-bold tracking-widest uppercase"
                    :style="{ backgroundColor: branding.primary_color, color: brandText }"
                >
                    Info
                </p>
                <div class="min-w-0 flex-1 overflow-hidden py-4">
                    <div
                        class="marquee whitespace-nowrap text-2xl font-medium"
                        :style="{ animationDuration: `${marqueeDurationSeconds}s` }"
                    >
                        <!-- Two identical copies so the -50% loop is seamless. Every
                             message, including the last, is followed by the same
                             separator, so the gap is even across the loop seam. -->
                        <span
                            v-for="copy in 2"
                            :key="copy"
                            class="inline-flex items-center"
                            :aria-hidden="copy === 2 ? 'true' : undefined"
                        >
                            <template v-for="(text, index) in branding.running_texts" :key="index">
                                <span>{{ text }}</span>
                                <span aria-hidden="true" class="mx-10 size-2.5 shrink-0 rounded-full bg-current opacity-40" />
                            </template>
                        </span>
                    </div>
                </div>
            </footer>
        </div>
    </div>
</template>

<style scoped>
.marquee {
    display: inline-flex;
    animation-name: marquee-scroll;
    animation-timing-function: linear;
    animation-iteration-count: infinite;
}

.call-enter {
    animation: call-enter 700ms cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes call-enter {
    from {
        opacity: 0;
        transform: scale(0.85);
    }

    to {
        opacity: 1;
        transform: scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .call-enter {
        animation: none;
    }
}

/*
 * "Baru dipanggil" card: a dim constant frame with a bright streak orbiting
 * it, plus a soft halo. The angle is a registered custom property so the
 * conic gradient itself can animate (no JS, compositor-friendly).
 */
@property --shine-angle {
    syntax: '<angle>';
    initial-value: 0deg;
    inherits: false;
}

.shine-frame::before {
    content: '';
    position: absolute;
    pointer-events: none;
    background: conic-gradient(
        from var(--shine-angle),
        color-mix(in srgb, var(--frame) 30%, transparent) 0deg,
        color-mix(in srgb, var(--frame) 30%, transparent) 230deg,
        var(--frame) 340deg,
        color-mix(in srgb, var(--frame) 30%, transparent) 360deg
    );
    /* Keep only the ring between the padding box and the border edge. */
    -webkit-mask:
        linear-gradient(#000 0 0) content-box,
        linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    inset: -5px;
    padding: 4px;
    border-radius: calc(1rem + 5px);
    animation: shine-orbit 3s linear infinite;
}

/* Halo: an outer shadow only paints outside the card, so it doesn't bleed
   through the translucent dark-theme background. */
.shine-frame {
    animation: shine-breathe 3s ease-in-out infinite alternate;
}

@keyframes shine-orbit {
    to {
        --shine-angle: 360deg;
    }
}

@keyframes shine-breathe {
    from {
        box-shadow: 0 0 16px 0 color-mix(in srgb, var(--frame) 15%, transparent);
    }

    to {
        box-shadow: 0 0 36px 6px color-mix(in srgb, var(--frame) 35%, transparent);
    }
}

@media (prefers-reduced-motion: reduce) {
    .shine-frame {
        animation: none;
        box-shadow: 0 0 24px 2px color-mix(in srgb, var(--frame) 25%, transparent);
    }

    .shine-frame::before {
        animation: none;
        background: var(--frame);
    }
}

@keyframes marquee-scroll {
    from {
        transform: translateX(0%);
    }

    to {
        transform: translateX(-50%);
    }
}
</style>
