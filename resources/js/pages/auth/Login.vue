<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { readableTextColor } from '@/lib/color';
import { store } from '@/routes/login';

defineOptions({
    layout: {
        title: 'Masuk',
        description: 'Khusus petugas dan admin.',
    },
});

defineProps<{
    status?: string;
    // Sent by Fortify; declared so it isn't forwarded as an attribute,
    // though the reset link has been removed from this page.
    canResetPassword: boolean;
}>();

const brandColor = computed(() => usePage().props.branding.primary_color);
const brandText = computed(() => readableTextColor(brandColor.value));
</script>

<template>
    <Head title="Masuk" />

    <div
        v-if="status"
        role="status"
        class="rounded-lg border border-green-600/20 bg-green-600/10 px-4 py-3 text-sm font-medium text-green-700 dark:text-green-400"
    >
        {{ status }}
    </div>

    <!-- --ring scoped to the form: field focus outlines take the brand colour. -->
    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        :style="{ '--ring': brandColor }"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="nama@contoh.com"
                    class="h-11"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Kata sandi</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Kata sandi"
                    class="h-11"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label
                    for="remember"
                    class="flex items-center gap-3 font-normal"
                >
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Ingat saya</span>
                </Label>
            </div>

            <Button
                type="submit"
                size="lg"
                class="mt-2 h-11 w-full text-base font-semibold transition hover:brightness-110 active:brightness-95"
                :style="{ backgroundColor: brandColor, color: brandText }"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Masuk
            </Button>
        </div>
    </Form>
</template>
