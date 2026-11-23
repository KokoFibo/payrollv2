<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import AuthCard from "@/Components/AuthCard.vue";

const form = useForm({ password: "" });

function submit() {
    form.post(route("password.confirm"), {
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Konfirmasi Password" />
        <AuthCard
            title="Konfirmasi identitas"
            description="Area ini dilindungi. Masukkan kembali password sebelum melanjutkan."
            icon="pi pi-lock"
        >
            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label for="password" class="mb-2 block text-xs font-bold text-slate-700 dark:text-slate-300">Password</label>
                    <InputText id="password" v-model="form.password" type="password" class="h-12 w-full" autocomplete="current-password" autofocus required />
                    <Message v-if="form.errors.password" severity="error" :closable="false" class="mt-2">{{ form.errors.password }}</Message>
                </div>
                <Button type="submit" label="Konfirmasi" icon="pi pi-check" class="h-12 w-full" severity="danger" :loading="form.processing" />
            </form>
        </AuthCard>
    </GuestLayout>
</template>
