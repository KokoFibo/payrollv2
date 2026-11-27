<script setup>
import { ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import FormField from "@/Components/Admin/FormField.vue";

defineProps({
    userName: { type: String, required: true },
});

const form = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
});
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showPasswordConfirmation = ref(false);

function submit() {
    form.put(route("password.change-required.update"), {
        onFinish: () => form.reset("current_password", "password", "password_confirmation"),
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Ganti Password" />

        <Card class="overflow-hidden border border-white/70 bg-white/90 shadow-[0_30px_80px_-30px_rgba(15,23,42,0.35)] backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/90">
            <template #content>
                <div class="p-1 sm:p-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                        <i class="pi pi-shield text-lg" />
                    </span>
                    <h2 class="mt-5 text-2xl font-extrabold leading-tight tracking-[-0.02em] text-slate-950 dark:text-white">
                        Ganti password sebelum melanjutkan
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Halo {{ userName }}, akun ini menggunakan password awal. Buat password pribadi yang kuat untuk membuka akses aplikasi.
                    </p>

                    <form class="mt-6 space-y-5" @submit.prevent="submit">
                        <FormField label="Password Saat Ini" :error="form.errors.current_password" required>
                            <div class="relative">
                                <InputText
                                    v-model="form.current_password"
                                    :type="showCurrentPassword ? 'text' : 'password'"
                                    class="h-12 w-full pr-12"
                                    autocomplete="current-password"
                                    required
                                />
                                <Button
                                    type="button"
                                    :icon="showCurrentPassword ? 'pi pi-eye-slash' : 'pi pi-eye'"
                                    text
                                    rounded
                                    severity="secondary"
                                    class="!absolute right-1.5 top-1/2 -translate-y-1/2"
                                    :aria-label="showCurrentPassword ? 'Sembunyikan password saat ini' : 'Tampilkan password saat ini'"
                                    @click="showCurrentPassword = !showCurrentPassword"
                                />
                            </div>
                        </FormField>
                        <FormField label="Password Baru" :error="form.errors.password" required>
                            <div class="relative">
                                <InputText
                                    v-model="form.password"
                                    :type="showNewPassword ? 'text' : 'password'"
                                    class="h-12 w-full pr-12"
                                    autocomplete="new-password"
                                    required
                                />
                                <Button
                                    type="button"
                                    :icon="showNewPassword ? 'pi pi-eye-slash' : 'pi pi-eye'"
                                    text
                                    rounded
                                    severity="secondary"
                                    class="!absolute right-1.5 top-1/2 -translate-y-1/2"
                                    :aria-label="showNewPassword ? 'Sembunyikan password baru' : 'Tampilkan password baru'"
                                    @click="showNewPassword = !showNewPassword"
                                />
                            </div>
                        </FormField>
                        <FormField label="Konfirmasi Password Baru" :error="form.errors.password_confirmation" required>
                            <div class="relative">
                                <InputText
                                    v-model="form.password_confirmation"
                                    :type="showPasswordConfirmation ? 'text' : 'password'"
                                    class="h-12 w-full pr-12"
                                    autocomplete="new-password"
                                    required
                                />
                                <Button
                                    type="button"
                                    :icon="showPasswordConfirmation ? 'pi pi-eye-slash' : 'pi pi-eye'"
                                    text
                                    rounded
                                    severity="secondary"
                                    class="!absolute right-1.5 top-1/2 -translate-y-1/2"
                                    :aria-label="showPasswordConfirmation ? 'Sembunyikan konfirmasi password' : 'Tampilkan konfirmasi password'"
                                    @click="showPasswordConfirmation = !showPasswordConfirmation"
                                />
                            </div>
                        </FormField>

                        <Button
                            type="submit"
                            label="Simpan password baru"
                            icon="pi pi-check"
                            severity="danger"
                            class="h-12 w-full"
                            :loading="form.processing"
                        />
                    </form>
                </div>
            </template>
        </Card>
    </GuestLayout>
</template>
