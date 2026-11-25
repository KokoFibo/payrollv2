<script setup>
import { ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import GuestLayout from "@/Layouts/GuestLayout.vue";

defineProps({
    canResetPassword: { type: Boolean, default: false },
    status: { type: String, default: null },
});

const showPassword = ref(false);
const form = useForm({
    email: "",
    password: "",
    remember: false,
});

function submit() {
    form.post(route("login"), {
        onFinish: () => form.reset("password"),
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Sign in" />

        <Card class="overflow-hidden border border-white/70 bg-white/90 shadow-[0_30px_80px_-30px_rgba(15,23,42,0.35)] backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/90">
            <template #content>
                <div class="p-1 sm:p-3">
                    <div class="mb-8">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300">
                            <i class="pi pi-lock text-lg" />
                        </span>
                        <h2 class="mt-5 text-2xl font-extrabold leading-tight tracking-[-0.02em] text-slate-950 dark:text-white">
                            Welcome back
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                            Sign in with your registered internal account.
                        </p>
                    </div>

                    <Message v-if="status" severity="success" :closable="false" class="mb-5">
                        {{ status }}
                    </Message>

                    <Message v-if="form.errors.email && !form.errors.password" severity="error" :closable="false" class="mb-5">
                        {{ form.errors.email }}
                    </Message>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div>
                            <label for="email" class="mb-2 block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Email
                            </label>
                            <div class="relative">
                                <i class="pi pi-envelope absolute left-4 top-1/2 -translate-y-1/2 text-sm text-slate-400" />
                                <InputText
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    class="h-12 w-full pl-11"
                                    placeholder="name@company.com"
                                    autocomplete="username"
                                    autofocus
                                    required
                                />
                            </div>
                            <Message v-if="form.errors.email && form.errors.password" severity="error" :closable="false" class="mt-2">
                                {{ form.errors.email }}
                            </Message>
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label for="password" class="text-xs font-bold text-slate-700 dark:text-slate-300">Password</label>
                                <Link
                                    v-if="canResetPassword"
                                    :href="route('password.request')"
                                    class="text-xs font-bold text-red-600 hover:text-red-700 dark:text-red-300"
                                >
                                    Forgot password?
                                </Link>
                            </div>
                            <div class="relative">
                                <i class="pi pi-key absolute left-4 top-1/2 -translate-y-1/2 text-sm text-slate-400" />
                                <InputText
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="h-12 w-full px-11"
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    required
                                />
                                <Button
                                    type="button"
                                    :icon="showPassword ? 'pi pi-eye-slash' : 'pi pi-eye'"
                                    text
                                    rounded
                                    severity="secondary"
                                    class="!absolute right-1.5 top-1/2 -translate-y-1/2"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                    @click="showPassword = !showPassword"
                                />
                            </div>
                            <Message v-if="form.errors.password" severity="error" :closable="false" class="mt-2">
                                {{ form.errors.password }}
                            </Message>
                        </div>

                        <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-600 dark:text-slate-300">
                            <Checkbox v-model="form.remember" binary />
                            Remember this device
                        </label>

                        <Button
                            type="submit"
                            label="Sign in to workspace"
                            icon="pi pi-arrow-right"
                            icon-pos="right"
                            class="h-12 w-full"
                            severity="danger"
                            :loading="form.processing"
                        />
                    </form>

                    <div class="mt-7 flex items-start gap-3 rounded-2xl border border-slate-200/80 bg-slate-50 p-3.5 dark:border-slate-800 dark:bg-slate-950/50">
                        <i class="pi pi-shield mt-0.5 text-sm text-slate-400" />
                        <p class="text-[11px] leading-5 text-slate-500 dark:text-slate-400">
                            Access is logged for security purposes. Do not share your credentials or open this page on public devices.
                        </p>
                    </div>
                </div>
            </template>
        </Card>
    </GuestLayout>
</template>
