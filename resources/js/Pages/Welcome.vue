<script setup>
import { computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import BrandLogo from "@/Components/BrandLogo.vue";

const page = usePage();
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));

const features = [
    {
        icon: "pi pi-users",
        title: "Employee records",
        description: "Profil, struktur organisasi, status kontrak, dan data payroll tersimpan dalam satu sumber.",
        tone: "bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300",
    },
    {
        icon: "pi pi-clock",
        title: "Attendance",
        description: "Catat kehadiran, keterlambatan, cuti, lembur, dan status remote secara harian.",
        tone: "bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300",
    },
    {
        icon: "pi pi-wallet",
        title: "Payroll periods",
        description: "Buat periode payroll, hitung gross dan deduction, lalu proses sampai pembayaran.",
        tone: "bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300",
    },
    {
        icon: "pi pi-chart-bar",
        title: "Reporting",
        description: "Pantau headcount, komposisi organisasi, absensi, dan nilai payroll dari dashboard analitik.",
        tone: "bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300",
    },
];
</script>

<template>
    <Head title="Payroll Workspace" />

    <div class="relative min-h-screen overflow-hidden bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-white">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -right-52 -top-56 h-[42rem] w-[42rem] rounded-full bg-red-100/80 blur-3xl dark:bg-red-950/25" />
            <div class="absolute -left-64 top-[32rem] h-[38rem] w-[38rem] rounded-full bg-orange-100/60 blur-3xl dark:bg-orange-950/15" />
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#e2e8f0_1px,transparent_1px),linear-gradient(to_bottom,#e2e8f0_1px,transparent_1px)] bg-[size:48px_48px] opacity-25 dark:bg-[linear-gradient(to_right,#1e293b_1px,transparent_1px),linear-gradient(to_bottom,#1e293b_1px,transparent_1px)] dark:opacity-15" />
        </div>

        <header class="relative mx-auto flex max-w-[1440px] items-center justify-between px-5 py-5 sm:px-8">
            <BrandLogo />
            <div class="flex items-center gap-2">
                <Link v-if="isAuthenticated" :href="route('admin.dashboard')">
                    <Button label="Buka dashboard" icon="pi pi-arrow-right" icon-pos="right" severity="danger" />
                </Link>
                <Link v-else :href="route('login')">
                    <Button label="Masuk" icon="pi pi-lock" severity="danger" />
                </Link>
            </div>
        </header>

        <main class="relative mx-auto max-w-[1440px] px-5 pb-20 pt-10 sm:px-8 lg:pt-20">
            <section class="grid items-center gap-12 lg:grid-cols-[1.02fr_0.98fr]">
                <div>
                    <Tag severity="danger" class="px-3 py-2">
                        <span class="mr-2 h-1.5 w-1.5 rounded-full bg-red-500" />
                        Internal payroll workspace
                    </Tag>
                    <h1 class="mt-7 max-w-3xl text-4xl font-extrabold leading-[1.03] tracking-[-0.06em] text-slate-950 dark:text-white sm:text-6xl">
                        Operasional payroll yang rapi, terukur, dan mudah diaudit.
                    </h1>
                    <p class="mt-6 max-w-2xl text-base leading-8 text-slate-500 dark:text-slate-400 sm:text-lg">
                        Satu workspace untuk data karyawan, absensi, proses payroll, laporan, dan export administrasi dengan kontrol akses berlapis.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <Link :href="isAuthenticated ? route('admin.dashboard') : route('login')">
                            <Button
                                :label="isAuthenticated ? 'Lanjutkan ke dashboard' : 'Login ke workspace'"
                                icon="pi pi-arrow-right"
                                icon-pos="right"
                                severity="danger"
                                class="h-12 px-6"
                            />
                        </Link>
                        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/70 px-4 py-3 text-xs font-semibold text-slate-500 backdrop-blur dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-400">
                            <i class="pi pi-shield text-emerald-500" />
                            Search & AI crawling disabled
                        </div>
                    </div>
                </div>

                <div class="relative mx-auto w-full max-w-xl">
                    <div class="absolute -inset-8 rounded-[3rem] bg-gradient-to-br from-red-200/40 via-transparent to-orange-200/40 blur-2xl dark:from-red-950/20 dark:to-orange-950/10" />
                    <div class="relative overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/90 p-4 shadow-[0_40px_100px_-35px_rgba(15,23,42,0.45)] backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/90 sm:p-6">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Payroll overview</p>
                                <p class="mt-1 text-lg font-extrabold text-slate-950 dark:text-white">November 2026</p>
                            </div>
                            <Tag severity="success">Ready</Tag>
                        </div>
                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div class="rounded-2xl bg-slate-950 p-4 text-white dark:bg-white dark:text-slate-950">
                                <i class="pi pi-wallet text-red-400 dark:text-red-600" />
                                <p class="mt-5 text-[10px] font-bold uppercase tracking-wider text-white/50 dark:text-slate-500">Net payroll</p>
                                <p class="mt-1 text-xl font-extrabold">Rp 428,5 jt</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950">
                                <i class="pi pi-users text-sky-600 dark:text-sky-300" />
                                <p class="mt-5 text-[10px] font-bold uppercase tracking-wider text-slate-400">Active employees</p>
                                <p class="mt-1 text-xl font-extrabold text-slate-950 dark:text-white">248</p>
                            </div>
                            <div class="col-span-2 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-bold text-slate-700 dark:text-slate-200">Attendance rate</p>
                                    <span class="text-xs font-extrabold text-emerald-600 dark:text-emerald-400">96,8%</span>
                                </div>
                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div class="h-full w-[96.8%] rounded-full bg-emerald-500" />
                                </div>
                                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                    <div class="rounded-xl bg-emerald-50 py-2 dark:bg-emerald-500/10">
                                        <p class="text-sm font-extrabold text-emerald-700 dark:text-emerald-300">218</p>
                                        <p class="text-[9px] font-bold uppercase text-emerald-600/70">Present</p>
                                    </div>
                                    <div class="rounded-xl bg-amber-50 py-2 dark:bg-amber-500/10">
                                        <p class="text-sm font-extrabold text-amber-700 dark:text-amber-300">12</p>
                                        <p class="text-[9px] font-bold uppercase text-amber-600/70">Late</p>
                                    </div>
                                    <div class="rounded-xl bg-rose-50 py-2 dark:bg-rose-500/10">
                                        <p class="text-sm font-extrabold text-rose-700 dark:text-rose-300">8</p>
                                        <p class="text-[9px] font-bold uppercase text-rose-600/70">Leave</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mt-24">
                <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-red-600 dark:text-red-300">Built for operations</p>
                        <h2 class="mt-2 text-3xl font-extrabold tracking-[-0.04em] text-slate-950 dark:text-white">Fondasi kerja payroll yang lengkap.</h2>
                    </div>
                    <p class="max-w-lg text-sm leading-6 text-slate-500 dark:text-slate-400">Setiap modul dirancang untuk mengurangi pekerjaan manual dan menjaga konsistensi data.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card v-for="feature in features" :key="feature.title" class="border border-slate-200/80 bg-white/80 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
                        <template #content>
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl" :class="feature.tone">
                                <i :class="feature.icon" />
                            </span>
                            <h3 class="mt-5 text-base font-extrabold text-slate-950 dark:text-white">{{ feature.title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ feature.description }}</p>
                        </template>
                    </Card>
                </div>
            </section>
        </main>

        <footer class="relative border-t border-slate-200/70 py-6 dark:border-slate-800">
            <div class="mx-auto flex max-w-[1440px] flex-col gap-2 px-5 text-[11px] text-slate-400 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <span>Payroll Workspace · Restricted internal application</span>
                <span>No search indexing · No AI crawler access</span>
            </div>
        </footer>
    </div>
</template>
