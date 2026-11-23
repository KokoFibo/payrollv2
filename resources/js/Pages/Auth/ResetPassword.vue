<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import AuthCard from "@/Components/AuthCard.vue";

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: "",
    password_confirmation: "",
});

function submit() {
    form.post(route("password.store"), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password" />
        <AuthCard
            title="Buat password baru"
            description="Gunakan password yang panjang dan tidak dipakai pada layanan lain."
            icon="pi pi-shield"
        >
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label for="email" class="mb-2 block text-xs font-bold text-slate-700 dark:text-slate-300">Email</label>
                    <InputText id="email" v-model="form.email" type="email" class="h-12 w-full" autocomplete="username" required />
                    <Message v-if="form.errors.email" severity="error" :closable="false" class="mt-2">{{ form.errors.email }}</Message>
                </div>
                <div>
                    <label for="password" class="mb-2 block text-xs font-bold text-slate-700 dark:text-slate-300">Password Baru</label>
                    <InputText id="password" v-model="form.password" type="password" class="h-12 w-full" autocomplete="new-password" required />
                    <Message v-if="form.errors.password" severity="error" :closable="false" class="mt-2">{{ form.errors.password }}</Message>
                </div>
                <div>
                    <label for="password_confirmation" class="mb-2 block text-xs font-bold text-slate-700 dark:text-slate-300">Konfirmasi Password</label>
                    <InputText id="password_confirmation" v-model="form.password_confirmation" type="password" class="h-12 w-full" autocomplete="new-password" required />
                </div>
                <Button type="submit" label="Simpan password baru" icon="pi pi-check" class="h-12 w-full" severity="danger" :loading="form.processing" />
            </form>
        </AuthCard>
    </GuestLayout>
</template>
