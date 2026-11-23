<script setup>
import { computed, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageHeader from "@/Components/Admin/PageHeader.vue";

const props = defineProps({
    readiness: { type: Object, required: true },
    roleName: { type: String, required: true },
    today: { type: String, required: true },
});

const exporting = ref(null);

const issueCount = computed(
    () => props.readiness.missingDirectorate + props.readiness.missingDepartment + props.readiness.missingSalary,
);

const readinessRows = computed(() => [
    {
        label: "Directorate",
        description: "Menentukan folder dan grouping workbook.",
        count: props.readiness.missingDirectorate,
        icon: "pi pi-sitemap",
    },
    {
        label: "Department",
        description: "Diperlukan untuk template level department.",
        count: props.readiness.missingDepartment,
        icon: "pi pi-building",
    },
    {
        label: "Gaji pokok",
        description: "Nilai awal untuk kolom gaji sebelum penyesuaian.",
        count: props.readiness.missingSalary,
        icon: "pi pi-wallet",
    },
]);

function download(type) {
    exporting.value = type;
    const routeName = type === "department"
        ? "admin.super-admin-dashboard.export-department"
        : "admin.super-admin-dashboard.export-directorate";

    window.location.assign(route(routeName));
    window.setTimeout(() => {
        exporting.value = null;
    }, 1200);
}
</script>

<template>
    <Head title="Super Admin Dashboard" />

    <AdminLayout>
        <div class="space-y-6">
            <PageHeader
                title="Super Admin Dashboard"
                description="Pusat persiapan dan unduhan template Excel payroll untuk kebutuhan administrasi tingkat tinggi."
                eyebrow="Export operations"
            >
                <template #actions>
                    <Tag severity="info" class="h-10 px-3.5">
                        <span class="mr-2 h-2 w-2 rounded-full bg-sky-500" />
                        {{ roleName }}
                    </Tag>
                    <Tag severity="secondary" class="h-10 border border-slate-200 bg-white px-3.5 dark:border-slate-800 dark:bg-slate-900">
                        {{ today }}
                    </Tag>
                </template>
            </PageHeader>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(340px,0.85fr)]">
                <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <template #title>
                        <div class="px-1">
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Kesiapan data export</h2>
                            <p class="mt-1 text-xs font-normal leading-5 text-slate-500 dark:text-slate-400">
                                Periksa data penting sebelum membuat template agar struktur folder dan isi workbook tetap lengkap.
                            </p>
                        </div>
                    </template>
                    <template #content>
                        <div class="space-y-4">
                            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-950/40">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">Karyawan eligible</p>
                                    <p class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950 dark:text-white">
                                        {{ readiness.eligibleEmployees.toLocaleString("id-ID") }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Status PKWT dan PKWTT akan masuk ke template.</p>
                                </div>
                                <Tag :severity="issueCount === 0 ? 'success' : 'warn'" class="self-start px-3 py-2 sm:self-auto">
                                    {{ issueCount === 0 ? "Siap diexport" : `${issueCount.toLocaleString("id-ID")} data perlu dicek` }}
                                </Tag>
                            </div>

                            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                                <div v-for="row in readinessRows" :key="row.label" class="flex items-center justify-between gap-4 py-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                                            :class="row.count === 0
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                                : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'"
                                        >
                                            <i :class="row.icon" />
                                        </span>
                                        <div>
                                            <p class="text-[13px] font-semibold text-slate-800 dark:text-slate-100">{{ row.label }}</p>
                                            <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">{{ row.description }}</p>
                                        </div>
                                    </div>
                                    <span
                                        class="shrink-0 text-xs font-bold tabular-nums"
                                        :class="row.count === 0
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-amber-600 dark:text-amber-400'"
                                    >
                                        {{ row.count === 0 ? "Lengkap" : `${row.count.toLocaleString("id-ID")} kosong` }}
                                    </span>
                                </div>
                            </div>

                            <Message v-if="issueCount > 0" severity="warn" :closable="false">
                                Lengkapi data di atas agar grouping dan nilai awal Excel lebih akurat.
                            </Message>
                        </div>
                    </template>
                </Card>

                <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <template #title>
                        <div class="px-1">
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Aturan export</h2>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Kontrak data yang diterapkan pada kedua jenis template.</p>
                        </div>
                    </template>
                    <template #content>
                        <div class="space-y-3">
                            <div
                                v-for="rule in [
                                    ['Status karyawan', 'Hanya PKWT dan PKWTT yang disertakan.', 'pi pi-check-circle'],
                                    ['Urutan data', 'Karyawan diurutkan berdasarkan ID ascending.', 'pi pi-sort-amount-down'],
                                    ['Format output', 'Satu ZIP berisi workbook XLSX sesuai grouping.', 'pi pi-file-export'],
                                ]"
                                :key="rule[0]"
                                class="flex gap-3 rounded-xl border border-slate-200/80 p-3 dark:border-slate-800"
                            >
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    <i :class="rule[2]" />
                                </span>
                                <div>
                                    <p class="text-[13px] font-semibold text-slate-800 dark:text-slate-100">{{ rule[0] }}</p>
                                    <p class="mt-0.5 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ rule[1] }}</p>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                <template #title>
                    <div class="px-1">
                        <h2 class="text-base font-bold text-slate-950 dark:text-white">Template Export Center</h2>
                        <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Siapkan template salary adjustment terbaru berdasarkan data karyawan aktif.</p>
                    </div>
                </template>
                <template #content>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <article class="rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50 via-white to-orange-50 p-5 dark:border-amber-500/20 dark:from-amber-500/10 dark:via-slate-900 dark:to-slate-900">
                            <div class="flex items-start justify-between gap-4">
                                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                    <i class="pi pi-file-excel text-xl" />
                                </span>
                                <Tag severity="warn">ZIP</Tag>
                            </div>
                            <h3 class="mt-5 text-lg font-bold tracking-tight text-slate-950 dark:text-white">Template Department Excel</h3>
                            <p class="mt-1.5 text-sm leading-6 text-slate-600 dark:text-slate-400">Satu workbook untuk setiap kombinasi directorate dan department.</p>
                            <Button
                                label="Download template"
                                icon="pi pi-download"
                                severity="warn"
                                class="mt-5"
                                :loading="exporting === 'department'"
                                @click="download('department')"
                            />
                        </article>

                        <article class="rounded-2xl border border-sky-200/80 bg-gradient-to-br from-sky-50 via-white to-cyan-50 p-5 dark:border-sky-500/20 dark:from-sky-500/10 dark:via-slate-900 dark:to-slate-900">
                            <div class="flex items-start justify-between gap-4">
                                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                                    <i class="pi pi-folder-open text-xl" />
                                </span>
                                <Tag severity="info">ZIP</Tag>
                            </div>
                            <h3 class="mt-5 text-lg font-bold tracking-tight text-slate-950 dark:text-white">Template Directorate Excel</h3>
                            <p class="mt-1.5 text-sm leading-6 text-slate-600 dark:text-slate-400">Satu workbook per directorate dengan format penamaan standar.</p>
                            <Button
                                label="Download template"
                                icon="pi pi-download"
                                severity="info"
                                class="mt-5"
                                :loading="exporting === 'directorate'"
                                @click="download('directorate')"
                            />
                        </article>
                    </div>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
