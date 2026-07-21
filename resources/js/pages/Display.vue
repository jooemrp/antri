<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

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
        <div class="flex min-h-screen flex-col bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-white">
            <div
                v-if="!soundEnabled"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 dark:bg-black/80"
            >
                <button
                    type="button"
                    class="rounded-2xl bg-blue-600 text-white px-10 py-6 text-3xl font-bold shadow-xl transition hover:bg-blue-500"
                    @click="enableSound"
                >
                    Aktifkan Suara
                </button>
            </div>

            <header class="flex items-center justify-between border-b border-slate-300 dark:border-white/10 px-10 py-6">
                <div class="flex items-center gap-4">
                    <img
                        v-if="branding.logo_url"
                        :src="branding.logo_url"
                        :alt="branding.app_name"
                        class="h-14 w-auto"
                    />
                    <h1 class="text-3xl font-bold">{{ branding.app_name }}</h1>
                </div>
                <div class="text-4xl font-mono font-semibold tabular-nums">{{ clockLabel }}</div>
            </header>

            <main class="flex flex-1 flex-col gap-8 px-10 py-8">
                <section
                    class="flex flex-col items-center justify-center rounded-3xl bg-blue-100 py-12 text-center dark:bg-blue-950/40"
                >
                    <template v-if="latest">
                        <p class="text-2xl font-medium tracking-widest text-blue-700 uppercase dark:text-blue-300">
                            Sedang Dipanggil
                        </p>
                        <p class="mt-4 text-8xl font-black tracking-wider">{{ latest.code }}</p>
                        <p class="mt-4 text-4xl font-semibold text-blue-700 dark:text-blue-200">
                            Menuju {{ latest.loket_name }}
                        </p>
                    </template>
                    <template v-else>
                        <p class="text-3xl font-medium text-blue-700 dark:text-blue-200">Belum ada panggilan</p>
                    </template>
                </section>

                <section class="grid flex-1 grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4">
                    <div
                        v-for="call in calls"
                        :key="call.loket_id"
                        class="flex flex-col items-center justify-center gap-2 rounded-2xl bg-white p-6 text-center shadow-sm dark:bg-white/5 dark:shadow-none"
                    >
                        <p class="text-lg font-medium text-slate-600 dark:text-white/70">{{ call.loket_name }}</p>
                        <p
                            class="text-sm font-semibold tracking-wide uppercase"
                            :style="{ color: call.service_color ?? undefined }"
                        >
                            {{ call.service_name }}
                        </p>
                        <p class="mt-2 text-5xl font-black tracking-wider">
                            {{ call.code ?? '—' }}
                        </p>
                    </div>
                </section>
            </main>

            <footer v-if="marqueeText" class="overflow-hidden border-t border-slate-300 bg-white py-4 dark:border-white/10 dark:bg-black/40">
                <div
                    class="marquee whitespace-nowrap text-2xl font-medium"
                    :style="{ animationDuration: `${marqueeDurationSeconds}s` }"
                >
                    <span class="mx-8">{{ marqueeText }}</span>
                    <span class="mx-8">{{ marqueeText }}</span>
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

@keyframes marquee-scroll {
    from {
        transform: translateX(0%);
    }

    to {
        transform: translateX(-50%);
    }
}
</style>
