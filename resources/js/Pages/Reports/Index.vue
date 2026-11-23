<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageHeader from "@/Components/Admin/PageHeader.vue";
import StatCard from "@/Components/Admin/StatCard.vue";
import EmptyState from "@/Components/Admin/EmptyState.vue";

const props = defineProps({
    summary: { type: Object, required: true },
    statusBreakdown: { type: Array, required: true },
    departmentBreakdown: { type: Array, required: true },
    directorateBreakdown: { type: Array, required: true },
    attendanceBreakdown: { type: Array, required: true },
    period: { type: Object, required: true },
});

const currency = new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
});

const maxStatus = computed(() => Math.max(1, ...props.statusBreakdown.map((item) => item.value)));
const maxAttendance = computed(() => Math.max(1, ...props.attendanceBreakdown.map((item) => item.value)));
const maxDepartment = computed(() => Math.max(1, ...props.departmentBreakdown.map((item) => item.employees)));

function percent(value, max) {
    return `${Math.max(4, Math.round((value / Math.max(1, max)) * 100))}%`;
}
</script>

<template>
    <Head title="Reports" />

    <AdminLayout>
        <div class="space-y-6">
            <PageHeader
                title="Reports & Analytics"
                description="Ringkasan headcount, komposisi organisasi, absensi, dan payroll dalam satu dashboard."
                eyebrow="Business intelligence"
            >
                <template #actions>
                    <Tag severity="secondary" class="h-10 border border-slate-200 bg-white px-3.5 dark:border-slate-800 dark:bg-slate-900">
                        {{ period.from }} → {{ period.to }}
                    </Tag>
                </template>
            </PageHeader>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Total Headcount" :value="summary.headcount.toLocaleString('id-ID')" :description="`${summary.activeHeadcount} aktif`" icon="pi pi-users" tone="brand" />
                <StatCard label="Attendance Rate" :value="`${summary.attendanceRate}%`" :description="`${summary.attendanceRecords} catatan / 30 hari`" icon="pi pi-clock" tone="emerald" />
                <StatCard label="Payroll Net" :value="currency.format(summary.payrollNet)" :description="summary.payrollPeriod || 'Belum ada payroll diproses'" icon="pi pi-wallet" tone="amber" />
                <StatCard label="Average Base Salary" :value="currency.format(summary.averageSalary)" description="Rata-rata karyawan aktif" icon="pi pi-chart-line" tone="sky" />
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <template #title>
                        <div class="px-1">
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Komposisi status karyawan</h2>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Distribusi status kontrak dan nonaktif.</p>
                        </div>
                    </template>
                    <template #content>
                        <EmptyState v-if="statusBreakdown.length === 0" title="Belum ada data" description="Tambahkan karyawan untuk melihat komposisi status." icon="pi pi-users" />
                        <div v-else class="space-y-4">
                            <div v-for="item in statusBreakdown" :key="item.label">
                                <div class="mb-1.5 flex items-center justify-between text-xs">
                                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ item.label }}</span>
                                    <span class="font-bold text-slate-500 dark:text-slate-400">{{ item.value }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div class="h-full rounded-full bg-red-500" :style="{ width: percent(item.value, maxStatus) }" />
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>

                <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <template #title>
                        <div class="px-1">
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Absensi 30 hari</h2>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Komposisi status kehadiran terbaru.</p>
                        </div>
                    </template>
                    <template #content>
                        <EmptyState v-if="attendanceBreakdown.length === 0" title="Belum ada absensi" description="Catat absensi untuk menghasilkan analitik kehadiran." icon="pi pi-calendar" />
                        <div v-else class="space-y-4">
                            <div v-for="item in attendanceBreakdown" :key="item.label">
                                <div class="mb-1.5 flex items-center justify-between text-xs">
                                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ item.label }}</span>
                                    <span class="font-bold text-slate-500 dark:text-slate-400">{{ item.value }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div class="h-full rounded-full bg-emerald-500" :style="{ width: percent(item.value, maxAttendance) }" />
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <div class="grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
                <Card class="overflow-hidden border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <template #title>
                        <div class="px-1">
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Karyawan aktif per department</h2>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Sepuluh department dengan jumlah karyawan terbesar.</p>
                        </div>
                    </template>
                    <template #content>
                        <DataTable :value="departmentBreakdown">
                            <template #empty><EmptyState title="Belum ada department" description="Data department belum tersedia." icon="pi pi-building" /></template>
                            <Column field="label" header="Department" style="min-width: 220px" />
                            <Column header="Karyawan" style="min-width: 130px">
                                <template #body="{ data }">
                                    <div class="flex items-center gap-3">
                                        <div class="h-2 w-24 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                            <div class="h-full rounded-full bg-sky-500" :style="{ width: percent(data.employees, maxDepartment) }" />
                                        </div>
                                        <span class="font-bold">{{ data.employees }}</span>
                                    </div>
                                </template>
                            </Column>
                            <Column header="Payroll Pokok" style="min-width: 150px">
                                <template #body="{ data }"><span class="font-semibold">{{ currency.format(data.payroll) }}</span></template>
                            </Column>
                        </DataTable>
                    </template>
                </Card>

                <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <template #title>
                        <div class="px-1">
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Top directorate</h2>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Headcount berdasarkan penempatan.</p>
                        </div>
                    </template>
                    <template #content>
                        <EmptyState v-if="directorateBreakdown.length === 0" title="Belum ada directorate" description="Data penempatan belum tersedia." icon="pi pi-sitemap" />
                        <div v-else class="space-y-3">
                            <div v-for="(item, index) in directorateBreakdown" :key="item.id" class="flex items-center gap-3 rounded-xl border border-slate-200/70 p-3 dark:border-slate-800">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-extrabold text-slate-500 dark:bg-slate-800">{{ index + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ item.label }}</p>
                                    <p class="text-xs text-slate-400">{{ item.employees }} karyawan aktif</p>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
