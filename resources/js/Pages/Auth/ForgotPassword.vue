<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import AuthCard from "@/Components/AuthCard.vue";

defineProps({
    status: { type: String, default: null },
});

const form = useForm({ email: "" });

function submit() {
    form.post(route("password.email"));
}
</script>

<template>
    <GuestLayout>
        <Head title="Lupa Password" />
        <AuthCard
            title="Pulihkan akses akun"
            description="Masukkan email akun internal. Sistem akan mengirim tautan untuk membuat password baru."
            icon="pi pi-key"
        >
            <Message v-if="status" severity="success" :closable="false" class="mb-5">{{ status }}</Message>
            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label for="email" class="mb-2 block text-xs font-bold text-slate-700 dark:text-slate-300">Email</label>
                    <InputText id="email" v-model="form.email" type="email" class="h-12 w-full" placeholder="nama@perusahaan.com" autocomplete="username" autofocus required />
                    <Message v-if="form.errors.email" severity="error" :closable="false" class="mt-2">{{ form.errors.email }}</Message>
                </div>
                <Button type="submit" label="Kirim tautan reset" icon="pi pi-send" class="h-12 w-full" severity="danger" :loading="form.processing" />
                <Link :href="route('login')" class="block text-center text-xs font-bold text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-300">
                    Kembali ke halaman login
                </Link>
            </form>
        </AuthCard>
    </GuestLayout>
</template>
