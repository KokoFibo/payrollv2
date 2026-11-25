<script setup>
import { computed } from "vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageHeader from "@/Components/Admin/PageHeader.vue";
import FormField from "@/Components/Admin/FormField.vue";

defineProps({
    mustVerifyEmail: { type: Boolean, default: false },
    status: { type: String, default: null },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const roleName = computed(() => page.props.auth?.roleName || "User");
const initial = computed(() => (user.value.name || "U").charAt(0).toUpperCase());

const profileForm = useForm({
    name: user.value.name || "",
    email: user.value.email || "",
});

const passwordForm = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
});

function updateProfile() {
    profileForm.patch(route("profile.update"), {
        preserveScroll: true,
    });
}

function updatePassword() {
    passwordForm.put(route("password.update"), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}

</script>

<template>
    <Head title="Profile" />

    <AdminLayout>
        <div class="space-y-6">
            <PageHeader
                title="Profile & Security"
                description="Kelola identitas akun, kredensial login, dan perlindungan akses workspace."
                eyebrow="Account settings"
            />

            <section class="relative overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-xl shadow-slate-950/10 dark:border dark:border-slate-800 sm:p-8">
                <div class="pointer-events-none absolute -right-28 -top-36 h-80 w-80 rounded-full bg-red-500/20 blur-3xl" />
                <div class="pointer-events-none absolute -bottom-44 left-1/3 h-80 w-80 rounded-full bg-sky-500/10 blur-3xl" />
                <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-5">
                        <Avatar :label="initial" size="xlarge" shape="circle" class="h-20 w-20 bg-white text-2xl font-extrabold text-slate-950" />
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <Tag severity="danger">{{ roleName }}</Tag>
                                <Tag :severity="user.email_verified_at ? 'success' : 'warn'">
                                    {{ user.email_verified_at ? "Email terverifikasi" : "Belum terverifikasi" }}
                                </Tag>
                            </div>
                            <h2 class="mt-3 truncate text-2xl font-extrabold tracking-[-0.035em]">{{ user.name }}</h2>
                            <p class="mt-1 truncate text-sm text-slate-300">{{ user.email }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-left">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Account ID</p>
                            <p class="mt-2 max-w-32 truncate font-mono text-xs text-slate-200">{{ user.id }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Access</p>
                            <p class="mt-2 text-xs font-bold text-emerald-300">Protected</p>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <template #title>
                        <div class="px-1">
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Informasi akun</h2>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Nama dan email digunakan untuk identitas login serta audit internal.</p>
                        </div>
                    </template>
                    <template #content>
                        <Message v-if="status" severity="success" :closable="false" class="mb-5">{{ status }}</Message>

                        <form class="space-y-5" @submit.prevent="updateProfile">
                            <FormField label="Nama Lengkap" :error="profileForm.errors.name" required>
                                <InputText v-model="profileForm.name" class="h-12 w-full" autocomplete="name" required />
                            </FormField>
                            <FormField label="Email" :error="profileForm.errors.email" required>
                                <InputText v-model="profileForm.email" type="email" class="h-12 w-full" autocomplete="username" required />
                            </FormField>

                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/50">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex items-start gap-3">
                                        <i class="pi pi-envelope mt-0.5 text-slate-400" />
                                        <div>
                                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Status email</p>
                                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                {{ user.email_verified_at ? "Email sudah dikonfirmasi." : "Konfirmasi email diperlukan untuk akses penuh." }}
                                            </p>
                                        </div>
                                    </div>
                                    <Link v-if="!user.email_verified_at" :href="route('verification.send')" method="post" as="button">
                                        <Button label="Kirim verifikasi" size="small" severity="secondary" outlined />
                                    </Link>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-3">
                                <Message v-if="profileForm.recentlySuccessful" severity="success" :closable="false">Tersimpan</Message>
                                <Button type="submit" label="Simpan profil" icon="pi pi-check" severity="danger" :loading="profileForm.processing" />
                            </div>
                        </form>
                    </template>
                </Card>

                <div class="space-y-6">
                    <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                        <template #title>
                            <div class="px-1">
                                <h2 class="text-base font-bold text-slate-950 dark:text-white">Keamanan password</h2>
                                <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Ganti password secara berkala dan hindari penggunaan ulang.</p>
                            </div>
                        </template>
                        <template #content>
                            <form class="space-y-4" @submit.prevent="updatePassword">
                                <FormField label="Password Saat Ini" :error="passwordForm.errors.current_password" required>
                                    <InputText v-model="passwordForm.current_password" type="password" class="h-12 w-full" autocomplete="current-password" required />
                                </FormField>
                                <FormField label="Password Baru" :error="passwordForm.errors.password" required>
                                    <InputText v-model="passwordForm.password" type="password" class="h-12 w-full" autocomplete="new-password" required />
                                </FormField>
                                <FormField label="Konfirmasi Password Baru" :error="passwordForm.errors.password_confirmation" required>
                                    <InputText v-model="passwordForm.password_confirmation" type="password" class="h-12 w-full" autocomplete="new-password" required />
                                </FormField>
                                <Button type="submit" label="Perbarui password" icon="pi pi-shield" severity="secondary" outlined class="w-full" :loading="passwordForm.processing" />
                            </form>
                        </template>
                    </Card>

                </div>
            </div>
        </div>
    </AdminLayout>
</template>
