<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import type { Branding } from '@/types';

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

const page = usePage();
const errors = computed(() => page.props.errors);

const form = reactive({
    app_name: props.branding.app_name,
    primary_color: props.branding.primary_color,
    accent_color: props.branding.accent_color,
    running_texts: [...props.branding.running_texts],
    display_theme: props.branding.display_theme,
    logo: null as File | null,
});

const logoPreview = ref<string | null>(props.branding.logo_url);

function onLogoChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.logo = file;
    logoPreview.value = file ? URL.createObjectURL(file) : props.branding.logo_url;
}

function addRunningText() {
    form.running_texts.push('');
}

function removeRunningText(index: number) {
    form.running_texts.splice(index, 1);
}

function submit() {
    router.post(
        '/admin/branding',
        {
            app_name: form.app_name,
            primary_color: form.primary_color,
            accent_color: form.accent_color,
            running_texts: form.running_texts,
            display_theme: form.display_theme,
            logo: form.logo,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.running_texts = [...props.branding.running_texts];
                form.display_theme = props.branding.display_theme;
            },
        },
    );
}
</script>

<template>
    <Head title="Tampilan" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Card>
            <CardHeader>
                <CardTitle>Tampilan Aplikasi</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="grid max-w-xl gap-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="app-name">Nama Aplikasi</Label>
                        <Input id="app-name" v-model="form.app_name" />
                        <InputError :message="errors.app_name" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="primary-color">Warna Utama</Label>
                            <input id="primary-color" v-model="form.primary_color" type="color" class="h-9 w-full rounded-md border border-input" />
                            <InputError :message="errors.primary_color" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="accent-color">Warna Aksen</Label>
                            <input id="accent-color" v-model="form.accent_color" type="color" class="h-9 w-full rounded-md border border-input" />
                            <InputError :message="errors.accent_color" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>Teks Berjalan</Label>
                        <div
                            v-for="(_, index) in form.running_texts"
                            :key="index"
                        >
                            <div class="flex items-center gap-2">
                                <Input
                                    v-model="form.running_texts[index]"
                                    placeholder="Selamat datang..."
                                    class="flex-1"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`Hapus teks ${index + 1}`"
                                    @click="removeRunningText(index)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                            <InputError :message="errors[`running_texts.${index}`]" />
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

                    <div class="grid gap-2">
                        <Label for="logo">Logo</Label>
                        <img
                            v-if="logoPreview"
                            :src="logoPreview"
                            alt="Logo saat ini"
                            class="h-16 w-16 rounded object-contain"
                        >
                        <input id="logo" type="file" accept="image/*" @change="onLogoChange">
                        <InputError :message="errors.logo" />
                    </div>

                    <div>
                        <Button type="submit">Simpan</Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
