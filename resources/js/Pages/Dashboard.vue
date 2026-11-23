<script setup>
import { computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageHeader from "@/Components/Admin/PageHeader.vue";
import StatCard from "@/Components/Admin/StatCard.vue";

const props = defineProps({
    stats: { type: Object, required: true },
    recentPayrollPeriods: { type: Array, required: true },
    today: { type: String, required: true },
});

const page = usePage();
const permissions = computed(() => page.props.auth?.can ?? {});

const rupiah = new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
});

function payrollStatusSeverity(status) {
    return { draft: "secondary", processed: "info", paid: "success" }[status] || "secondary";
}

const quickLinks = computed(() => {
    const links = permissions.value.managePayroll ? [
        {
            title: "Employees",
            description: "Data dan profil karyawan",
            routeName: "admin.employees.index",
            icon: "pi pi-users",
            tone: "bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200",
        },
        {
            title: "Attendance",
            description: "Rekap kehadiran harian",
            routeName: "admin.attendance.index",
            icon: "pi pi-clock",
            tone: "bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300",
        },
        {
            title: "Payroll",
            description: "Proses penggajian",
            routeName: "admin.payroll.index",
            icon: "pi pi-wallet",
            tone: "bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300",
        },
        {
            title: "Reports",
            description: "Laporan operasional",
            routeName: "admin.reports.index",
            icon: "pi pi-chart-bar",
            tone: "bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300",
        },
    ] : [];

    if (permissions.value.superAdmin) {
        links.unshift({
            title: "Super Admin",
            description: "Export template salary adjustment",
            routeName: "admin.super-admin-dashboard.index",
            icon: "pi pi-shield",
            tone: "bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300",
        });
    }

    return links;
});
</script>

<template>
    <Head title="Dashboard" />

    <AdminLayout>
        <div class="space-y-6">
            <PageHeader
                title="Dashboard"
                description="Pantau kesiapan operasional payroll dan akses modul utama dari satu tempat."
                eyebrow="Operations overview"
            >
                <template #actions>
                    <Tag severity="secondary" class="h-10 border border-slate-200 bg-white px-3.5 dark:border-slate-800 dark:bg-slate-900">
                        <i class="pi pi-calendar mr-2 text-xs" />
                        {{ today }}
                    </Tag>
                </template>
            </PageHeader>

            <section aria-labelledby="dashboard-summary-heading">
                <div class="mb-3 flex items-center justify-between gap-4">
                    <h2 id="dashboard-summary-heading" class="text-lg font-bold tracking-tight text-slate-950 dark:text-white">Ringkasan utama</h2>
                    <span class="text-xs font-medium text-slate-400 dark:text-slate-500">Data langsung dari MySQL</span>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Employees"
                        :value="stats.employees.toLocaleString('id-ID')"
                        :description="`${stats.activeEmployees.toLocaleString('id-ID')} karyawan aktif`"
                        icon="pi pi-users"
                        tone="brand"
                    />
                    <StatCard
                        label="Attendance Today"
                        :value="stats.attendanceToday.toLocaleString('id-ID')"
                        :description="`${stats.attendanceLateToday} terlambat`"
                        icon="pi pi-clock"
                        tone="emerald"
                    />
                    <StatCard
                        label="Payroll Net"
                        :value="rupiah.format(stats.latestPayrollNet || stats.payrollTotal)"
                        :description="stats.latestPayrollNet > 0 ? 'Payroll terakhir diproses' : 'Estimasi gaji pokok aktif'"
                        icon="pi pi-wallet"
                        tone="amber"
                    />
                    <StatCard
                        label="Organization"
                        :value="`${stats.departments}/${stats.directorates}`"
                        description="Department / directorate"
                        icon="pi pi-building"
                        tone="sky"
                    />
                </div>
            </section>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
                    <template #title>
                        <div class="px-1">
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Aktivitas terbaru</h2>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Perubahan penting pada payroll dan data karyawan akan tampil di sini.</p>
                        </div>
                    </template>
                    <template #content>
                        <div v-if="recentPayrollPeriods.length" class="space-y-3">
                            <Link
                                v-for="period in recentPayrollPeriods"
                                :key="period.id"
                                :href="route('admin.payroll.show', period.id)"
                                class="flex items-center gap-4 rounded-2xl border border-slate-200/80 p-4 transition hover:border-red-200 hover:bg-red-50/30 dark:border-slate-800 dark:hover:border-red-500/20 dark:hover:bg-red-500/5"
                            >
                                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    <i class="pi pi-wallet" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-slate-900 dark:text-white">{{ period.name }}</p>
                                        <Tag :severity="payrollStatusSeverity(period.status)">{{ period.status }}</Tag>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-400">Bayar {{ period.pay_date }}</p>
                                </div>
                                <p class="hidden text-sm font-bold text-slate-700 dark:text-slate-200 sm:block">{{ rupiah.format(period.total_net) }}</p>
                                <i class="pi pi-angle-right text-xs text-slate-300" />
                            </Link>
                            <div class="flex justify-end pt-2">
                                <Link :href="route('admin.payroll.index')">
                                    <Button label="Semua payroll" icon="pi pi-arrow-right" icon-pos="right" severity="secondary" outlined />
                                </Link>
                            </div>
                        </div>
                        <div v-else class="flex min-h-80 flex-col items-center justify-center px-6 py-10 text-center">
                            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                                <i class="pi pi-wallet text-xl" />
                            </span>
                            <h3 class="mt-4 text-base font-bold text-slate-950 dark:text-white">Belum ada periode payroll</h3>
                            <p class="mt-1.5 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400">
                                Buat periode payroll untuk mulai menghitung komponen gaji karyawan aktif.
                            </p>
                            <Link :href="route('admin.payroll.index')">
                                <Button label="Buka payroll" icon="pi pi-arrow-up-right" severity="danger" class="mt-5" />
                            </Link>
                        </div>
                    </template>
                </Card>

                <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <template #title>
                        <div class="px-1">
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Akses cepat</h2>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Masuk ke modul yang paling sering digunakan.</p>
                        </div>
                    </template>
                    <template #content>
                        <div class="space-y-2">
                            <Link
                                v-for="item in quickLinks"
                                :key="item.routeName"
                                :href="route(item.routeName)"
                                class="group flex items-center gap-3 rounded-xl border border-slate-200/80 p-3 transition hover:border-brand-200 hover:bg-brand-50/50 dark:border-slate-800 dark:hover:border-brand-500/20 dark:hover:bg-brand-500/5"
                            >
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="item.tone">
                                    <i :class="item.icon" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[13px] font-semibold text-slate-800 dark:text-slate-100">{{ item.title }}</span>
                                    <span class="mt-0.5 block text-xs text-slate-400 dark:text-slate-500">{{ item.description }}</span>
                                </span>
                                <i class="pi pi-angle-right text-xs text-slate-300 transition group-hover:translate-x-0.5" />
                            </Link>
                        </div>
                    </template>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
