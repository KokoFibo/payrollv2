<script setup>
import { Link } from "@inertiajs/vue3";
import BrandLogo from "@/Components/BrandLogo.vue";
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-slate-50 dark:bg-slate-950">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -left-40 top-[-15rem] h-[34rem] w-[34rem] rounded-full bg-red-100/70 blur-3xl dark:bg-red-950/30" />
            <div class="absolute -right-48 bottom-[-18rem] h-[38rem] w-[38rem] rounded-full bg-orange-100/60 blur-3xl dark:bg-orange-950/20" />
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#e2e8f0_1px,transparent_1px),linear-gradient(to_bottom,#e2e8f0_1px,transparent_1px)] bg-[size:44px_44px] opacity-30 dark:bg-[linear-gradient(to_right,#1e293b_1px,transparent_1px),linear-gradient(to_bottom,#1e293b_1px,transparent_1px)] dark:opacity-20" />
        </div>

        <div class="relative mx-auto flex min-h-screen max-w-[1500px] flex-col px-4 py-5 sm:px-6 lg:px-8">
            <header class="flex items-center justify-between">
                <Link href="/"><BrandLogo /></Link>
                <Tag severity="secondary" class="hidden border border-slate-200 bg-white/80 px-3 py-2 backdrop-blur sm:inline-flex dark:border-slate-800 dark:bg-slate-900/80">
                    <span class="mr-2 h-1.5 w-1.5 rounded-full bg-emerald-500" />
                    Internal system
                </Tag>
            </header>

            <main class="grid flex-1 items-center gap-10 py-10 lg:grid-cols-[minmax(0,1fr)_minmax(420px,520px)] lg:gap-16">
                <section class="hidden lg:block">
                    <Tag severity="danger" class="mb-7 px-3 py-2">
                        <i class="pi pi-lock mr-1.5 text-xs" />
                        Secure payroll operations
                    </Tag>
                    <h1 class="max-w-2xl text-5xl font-extrabold leading-[1.12] tracking-[-0.025em] text-slate-950 dark:text-white">
                        Manage people, attendance, and payroll in one workspace.
                    </h1>
                    <p class="mt-6 max-w-xl text-base leading-8 text-slate-500 dark:text-slate-400">
                        Employee data, attendance, payroll periods, reports, and administrative exports stay aligned in a consistent workflow.
                    </p>

                    <div class="mt-10 grid max-w-xl grid-cols-3 gap-3">
                        <div v-for="item in [
                            ['Employees', 'pi pi-users'],
                            ['Attendance', 'pi pi-clock'],
                            ['Payroll', 'pi pi-wallet'],
                        ]" :key="item[0]" class="rounded-2xl border border-slate-200/80 bg-white/70 p-4 backdrop-blur dark:border-slate-800 dark:bg-slate-900/60">
                            <i :class="item[1]" class="text-lg text-red-600 dark:text-red-300" />
                            <p class="mt-3 text-xs font-bold text-slate-700 dark:text-slate-200">{{ item[0] }}</p>
                        </div>
                    </div>
                </section>

                <section class="w-full">
                    <slot />
                </section>
            </main>

            <footer class="flex flex-col gap-2 border-t border-slate-200/70 py-5 text-[11px] text-slate-400 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <span>Payroll Workspace · Restricted access</span>
                <span>No indexing · No AI crawler access</span>
            </footer>
        </div>
    </div>
</template>
