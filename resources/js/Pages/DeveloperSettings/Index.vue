<script setup>
import { computed, reactive, ref, watch } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageHeader from "@/Components/Admin/PageHeader.vue";
import StatCard from "@/Components/Admin/StatCard.vue";
import FormField from "@/Components/Admin/FormField.vue";
import EmptyState from "@/Components/Admin/EmptyState.vue";
import AttendanceBulkCorrectionPanel from "@/Components/Admin/DeveloperSettings/AttendanceBulkCorrectionPanel.vue";
import AttendanceCleanupPanel from "@/Components/Admin/DeveloperSettings/AttendanceCleanupPanel.vue";
import CalendarOverridePanel from "@/Components/Admin/DeveloperSettings/CalendarOverridePanel.vue";
import NationalHolidayPanel from "@/Components/Admin/DeveloperSettings/NationalHolidayPanel.vue";
import ServerHealthMonitor from "@/Components/Admin/DeveloperSettings/ServerHealthMonitor.vue";
import SpecialWorkSchedulePanel from "@/Components/Admin/DeveloperSettings/SpecialWorkSchedulePanel.vue";

const props = defineProps({
    resource: { type: String, required: true },
    config: { type: Object, required: true },
    resources: { type: Array, required: true },
    records: { type: Object, required: true },
    filters: { type: Object, required: true },
    preview: { type: Object, required: true },
    sourceDatabase: { type: String, default: "" },
    migrationResult: { type: Object, default: null },
    masterCopyResult: { type: Object, default: null },
    attendanceCleanupMonths: { type: Array, required: true },
    attendanceCleanupResult: { type: Object, default: null },
    nationalHolidays: { type: Array, required: true },
    calendarOverrides: { type: Array, required: true },
    specialWorkSchedules: { type: Array, required: true },
    scheduleGuide: { type: Object, required: true },
    attendanceBulkOptions: { type: Object, required: true },
    initialSection: { type: String, default: "master" },
    workCalendarOptions: { type: Object, required: true },
    counts: { type: Object, required: true },
    adminSalaryLimit: { type: Number, required: true },
    attendanceToleranceMinutes: { type: Number, required: true },
});

const confirm = useConfirm();
const section = ref(props.initialSection);
const search = ref(props.filters.search);
const perPage = ref(props.filters.perPage);
const searchTimer = ref(null);
const dialogVisible = ref(false);
const editingRecord = ref(null);
const copying = ref(false);
const copyingMasterData = ref(false);
const mappingReviewed = ref(false);
const salaryLimitForm = useForm({
    admin_salary_limit: props.adminSalaryLimit,
});
const attendanceToleranceForm = useForm({
    attendance_tolerance_minutes: props.attendanceToleranceMinutes,
});

const resourceVisuals = {
    companies: { label: "Company", caption: "Entitas perusahaan", icon: "pi pi-building" },
    directorates: { label: "Directorate", caption: "Penempatan", icon: "pi pi-sitemap" },
    departments: { label: "Department", caption: "Struktur divisi", icon: "pi pi-building" },
    jabatans: { label: "Jabatan", caption: "Posisi kerja", icon: "pi pi-briefcase" },
    etnis: { label: "Etnis", caption: "Data etnis", icon: "pi pi-globe" },
    "job-grades": { label: "Job Grade", caption: "Level jabatan", icon: "pi pi-layers" },
};

const navigationItems = computed(() => [
    { value: "server-health", section: "server-health", label: "Server Health", caption: "SSE on-demand", icon: "pi pi-server" },
    { value: "import", section: "import", label: "Copy Karyawan", caption: "Mapping & migrasi", icon: "pi pi-cloud-download" },
    { value: "holidays", section: "holidays", label: "Hari Libur", caption: "Kalender nasional", icon: "pi pi-calendar" },
    { value: "calendar-overrides", section: "calendar-overrides", label: "Pengecualian Kalender", caption: "Libur per company", icon: "pi pi-calendar-clock" },
    { value: "work-schedules", section: "work-schedules", label: "Jadwal Kerja", caption: "Ramadan & periode khusus", icon: "pi pi-clock" },
    { value: "attendance-bulk", section: "attendance-bulk", label: "Koreksi Presensi", caption: "Bulk punch & kalkulasi", icon: "pi pi-pencil" },
    { value: "attendance-cleanup", section: "attendance-cleanup", label: "Bersihkan Presensi", caption: "Hapus rekap per bulan", icon: "pi pi-trash" },
    ...props.resources.map((item) => ({
        value: item.value,
        section: "master",
        label: resourceVisuals[item.value]?.label ?? item.label,
        caption: resourceVisuals[item.value]?.caption ?? "Master data",
        icon: resourceVisuals[item.value]?.icon ?? "pi pi-database",
    })),
]);

const emptyValues = () => Object.fromEntries(
    props.config.fields.map((field) => [field.name, ""]),
);

const form = useForm({
    values: emptyValues(),
});

const previewData = computed(() => props.preview?.data);
const previewSummary = computed(() => previewData.value?.summary ?? {});
const sourceDatabaseLabel = computed(() => props.sourceDatabase || "configured source database");

watch(
    () => props.resource,
    () => {
        search.value = props.filters.search;
        perPage.value = props.filters.perPage;
        form.defaults({ values: emptyValues() });
        form.reset();
        form.clearErrors();
    },
);

function navigate(query = {}) {
    router.get(route("admin.developer-settings.index"), {
        resource: props.resource,
        search: search.value,
        perPage: perPage.value,
        ...query,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function onSearch() {
    window.clearTimeout(searchTimer.value);
    searchTimer.value = window.setTimeout(() => navigate({ page: 1 }), 300);
}

function selectSection(item) {
    if (item.section !== "master") {
        section.value = item.section;

        return;
    }

    section.value = "master";

    if (item.value !== props.resource) {
        search.value = "";
        router.get(route("admin.developer-settings.index"), {
            resource: item.value,
            perPage: perPage.value,
        });
    }
}

function isActiveNavigationItem(item) {
    return item.section === "master"
        ? section.value === "master" && item.value === props.resource
        : section.value === item.section;
}

function saveSalaryLimit() {
    salaryLimitForm.put(route("admin.developer-settings.admin-salary-limit.update"), {
        preserveScroll: true,
    });
}

function saveAttendanceTolerance() {
    attendanceToleranceForm.put(
        route("admin.developer-settings.attendance-tolerance.update"),
        { preserveScroll: true },
    );
}

function openCreate() {
    editingRecord.value = null;
    form.defaults({ values: emptyValues() });
    form.reset();
    form.clearErrors();
    dialogVisible.value = true;
}

function openEdit(record) {
    editingRecord.value = record;
    form.clearErrors();
    form.values = Object.fromEntries(
        props.config.fields.map((field) => [field.name, record.values[field.name] ?? ""]),
    );
    dialogVisible.value = true;
}

function save() {
    form.transform((data) => data.values);

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogVisible.value = false;
            editingRecord.value = null;
        },
    };

    if (editingRecord.value) {
        form.put(
            route("admin.developer-settings.master.update", {
                resource: props.resource,
                record: editingRecord.value.id,
            }),
            options,
        );

        return;
    }

    form.post(
        route("admin.developer-settings.master.store", { resource: props.resource }),
        options,
    );
}

function deleteRecord(record) {
    confirm.require({
        message: record.karyawans_count > 0
            ? `${record.name} masih digunakan oleh ${record.karyawans_count} karyawan. Relasi karyawan akan menjadi kosong.`
            : `${record.name} akan dihapus secara permanen.`,
        header: "Hapus master data?",
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Batal",
        acceptLabel: "Hapus",
        rejectClass: "p-button-secondary p-button-outlined",
        acceptClass: "p-button-danger",
        accept: () => {
            router.delete(
                route("admin.developer-settings.master.destroy", {
                    resource: props.resource,
                    record: record.id,
                }),
                { preserveScroll: true },
            );
        },
    });
}

function copyEmployees() {
    if (!mappingReviewed.value) {
        return;
    }

    confirm.require({
        message: `Semua karyawan di database target akan dihapus permanen terlebih dahulu. Attendance dan payroll item yang terhubung juga akan terhapus karena cascade. Setelah itu seluruh karyawan dari ${sourceDatabaseLabel.value} disalin ulang.`,
        header: "Ganti seluruh data karyawan?",
        icon: "pi pi-database",
        rejectLabel: "Batal",
        acceptLabel: "Hapus & Copy",
        accept: () => {
            copying.value = true;
            router.post(route("admin.developer-settings.copy"), {}, {
                onFinish: () => {
                    copying.value = false;
                },
            });
        },
    });
}

function copyMasterData() {
    confirm.require({
        message: `Semua Company, Directorate, Department, Jabatan, dan Job Grade di database target akan dihapus permanen sebelum disalin ulang dari ${sourceDatabaseLabel.value}. Data kalender dengan company terkait dapat ikut terhapus, dan referensi master pada karyawan akan menjadi kosong sampai Copy Karyawan dijalankan.`,
        header: "Ganti seluruh 5 tabel master?",
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Batal",
        acceptLabel: "Hapus & Copy",
        rejectClass: "p-button-secondary p-button-outlined",
        acceptClass: "p-button-danger",
        accept: () => {
            copyingMasterData.value = true;
            router.post(route("admin.developer-settings.copy-master-data"), {}, {
                preserveScroll: true,
                onFinish: () => {
                    copyingMasterData.value = false;
                },
            });
        },
    });
}
</script>

<template>
    <Head title="Developer Settings" />

    <AdminLayout>
        <div class="space-y-6">
            <PageHeader
                title="Developer Settings"
                description="Pusat utilitas internal untuk migrasi data dan pengelolaan master data aplikasi."
                eyebrow="Engineering workspace"
            >
                <template #actions>
                    <Tag severity="warn" class="h-10 px-3.5">
                        <span class="mr-2 h-1.5 w-1.5 rounded-full bg-amber-500" />
                        Developer only
                    </Tag>
                </template>
            </PageHeader>

            <section class="relative overflow-hidden rounded-[1.75rem] bg-slate-950 px-6 py-7 text-white shadow-xl shadow-slate-950/10 dark:border dark:border-slate-800">
                <div class="pointer-events-none absolute -right-24 -top-32 h-80 w-80 rounded-full bg-brand-600/30 blur-3xl" />
                <div class="pointer-events-none absolute -bottom-40 left-1/3 h-72 w-72 rounded-full bg-sky-500/10 blur-3xl" />
                <div class="relative grid gap-6 lg:grid-cols-[1.3fr_0.7fr] lg:items-end">
                    <div>
                        <Tag severity="secondary" class="border border-white/10 bg-white/10 text-slate-200">
                            <i class="pi pi-code mr-2" />
                            Internal tools
                        </Tag>
                        <h2 class="mt-5 max-w-3xl text-2xl font-bold tracking-[-0.03em] sm:text-3xl">
                            Migrasi data dan referensi aplikasi dalam satu workspace.
                        </h2>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                            Preview struktur database sebelum copy, sinkronkan referensi master, lalu kelola data inti payroll.
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Source</p>
                            <p class="mt-2 font-mono text-sm font-semibold text-white">{{ props.sourceDatabase || "Not configured" }}</p>
                            <p class="mt-1 text-xs text-slate-400">Read-only preview</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Target</p>
                            <p class="mt-2 font-mono text-sm font-semibold text-white">MySQL</p>
                            <p class="mt-1 text-xs text-slate-400">Transactional copy</p>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <StatCard label="Employees" :value="counts.employees.toLocaleString('id-ID')" description="Total data target" icon="pi pi-users" tone="brand" />
                <StatCard label="Active Employees" :value="counts.activeEmployees.toLocaleString('id-ID')" description="PKWT dan PKWTT" icon="pi pi-check-circle" tone="emerald" />
            </div>

            <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                <template #title>
                    <div class="flex flex-col gap-4 px-1 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Ganti master data referensi</h2>
                            <p class="mt-1 max-w-3xl text-xs font-normal leading-5 text-slate-500 dark:text-slate-400">
                                Ganti seluruh Company, Directorate, Department, Jabatan, dan Job Grade di database target dengan data terbaru dari <span class="font-mono">{{ props.sourceDatabase || "configured source" }}</span>. Data lama dihapus lebih dahulu dalam transaksi yang sama.
                            </p>
                        </div>
                        <Button
                            label="Ganti 5 tabel"
                            icon="pi pi-copy"
                            severity="danger"
                            :loading="copyingMasterData"
                            @click="copyMasterData"
                        />
                    </div>
                </template>
                <template #content>
                    <div class="flex flex-wrap gap-2">
                        <Tag severity="secondary"><i class="pi pi-building mr-1.5" />Company</Tag>
                        <Tag severity="secondary"><i class="pi pi-sitemap mr-1.5" />Directorate</Tag>
                        <Tag severity="secondary"><i class="pi pi-building mr-1.5" />Department</Tag>
                        <Tag severity="secondary"><i class="pi pi-briefcase mr-1.5" />Jabatan</Tag>
                        <Tag severity="secondary"><i class="pi pi-layers mr-1.5" />Job Grade</Tag>
                    </div>

                    <ProgressBar v-if="copyingMasterData" mode="indeterminate" class="mt-4" />

                    <div v-if="masterCopyResult" class="mt-5 overflow-hidden rounded-xl border border-emerald-200 dark:border-emerald-500/20">
                        <DataTable :value="masterCopyResult.statistics" size="small" scrollable>
                            <Column field="label" header="Tabel" style="min-width: 140px" />
                            <Column field="deleted_rows" header="Dihapus" style="min-width: 90px">
                                <template #body="{ data }">{{ Number(data.deleted_rows ?? 0).toLocaleString("id-ID") }}</template>
                            </Column>
                            <Column field="source_rows" header="Source" style="min-width: 90px">
                                <template #body="{ data }">{{ data.source_rows.toLocaleString("id-ID") }}</template>
                            </Column>
                            <Column field="created_rows" header="Baru" style="min-width: 80px">
                                <template #body="{ data }"><span class="font-semibold text-emerald-700 dark:text-emerald-300">{{ data.created_rows.toLocaleString("id-ID") }}</span></template>
                            </Column>
                            <Column field="updated_rows" header="Diperbarui" style="min-width: 105px">
                                <template #body="{ data }">{{ data.updated_rows.toLocaleString("id-ID") }}</template>
                            </Column>
                            <Column field="skipped_rows" header="Dilewati" style="min-width: 90px">
                                <template #body="{ data }">{{ data.skipped_rows.toLocaleString("id-ID") }}</template>
                            </Column>
                            <Column field="target_rows" header="Target" style="min-width: 90px">
                                <template #body="{ data }">{{ data.target_rows.toLocaleString("id-ID") }}</template>
                            </Column>
                        </DataTable>
                    </div>
                </template>
            </Card>

            <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                <template #title>
                    <div class="px-1">
                        <h2 class="text-base font-bold text-slate-950 dark:text-white">Batas sensor gaji Admin</h2>
                        <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">
                            Di halaman Employees, role Admin tidak dapat melihat atau mengubah gaji pokok dan gaji tetap karyawan yang gaji pokoknya melebihi batas ini. Komponen payroll lain tetap dapat diakses.
                        </p>
                    </div>
                </template>
                <template #content>
                    <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="saveSalaryLimit">
                        <FormField label="Batas Sensor Gaji" :error="salaryLimitForm.errors.admin_salary_limit" class="flex-1" required>
                            <InputNumber
                                v-model="salaryLimitForm.admin_salary_limit"
                                mode="currency"
                                currency="IDR"
                                locale="id-ID"
                                :min="0"
                                class="w-full"
                                input-class="w-full"
                            />
                        </FormField>
                        <Button type="submit" label="Simpan batas" icon="pi pi-check" severity="danger" :loading="salaryLimitForm.processing" />
                    </form>
                </template>
            </Card>

            <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                <template #title>
                    <div class="px-1">
                        <h2 class="text-base font-bold text-slate-950 dark:text-white">Toleransi keterlambatan attendance</h2>
                        <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">
                            Toleransi hanya berlaku untuk first in, second in, dan overtime in. Seluruh punch out tidak memiliki toleransi.
                        </p>
                    </div>
                </template>
                <template #content>
                    <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="saveAttendanceTolerance">
                        <FormField label="Toleransi punch masuk (menit)" :error="attendanceToleranceForm.errors.attendance_tolerance_minutes" class="flex-1" required>
                            <InputNumber
                                v-model="attendanceToleranceForm.attendance_tolerance_minutes"
                                :min="0"
                                :max="120"
                                class="w-full"
                                input-class="w-full"
                            />
                        </FormField>
                        <Button type="submit" label="Simpan toleransi" icon="pi pi-check" severity="danger" :loading="attendanceToleranceForm.processing" />
                    </form>
                </template>
            </Card>

            <nav class="grid grid-cols-2 gap-2 rounded-2xl border border-slate-200/80 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-3 xl:grid-cols-8" aria-label="Navigasi Developer Settings">
                <Button
                    v-for="item in navigationItems"
                    :key="item.value"
                    type="button"
                    :severity="isActiveNavigationItem(item) ? 'primary' : 'secondary'"
                    :text="!isActiveNavigationItem(item)"
                    class="h-auto justify-start rounded-xl px-3 py-3 text-left"
                    @click="selectSection(item)"
                >
                    <span class="flex min-w-0 items-start gap-2.5">
                        <i :class="item.icon" class="mt-0.5" />
                        <span class="min-w-0">
                            <span class="block truncate text-[13px] font-semibold">{{ item.label }}</span>
                            <span class="mt-0.5 block truncate text-[11px] font-normal opacity-70">{{ item.caption }}</span>
                        </span>
                    </span>
                </Button>
            </nav>

            <section v-if="section === 'server-health'" class="space-y-4">
                <ServerHealthMonitor />
            </section>

            <section v-show="section === 'holidays'" class="space-y-4">
                <NationalHolidayPanel :holidays="nationalHolidays" />
            </section>

            <section v-show="section === 'calendar-overrides'" class="space-y-4">
                <CalendarOverridePanel
                    :overrides="calendarOverrides"
                    :options="workCalendarOptions"
                />
            </section>

            <section v-show="section === 'work-schedules'" class="space-y-4">
                <SpecialWorkSchedulePanel
                    :schedules="specialWorkSchedules"
                    :options="workCalendarOptions"
                    :schedule-guide="scheduleGuide"
                />
            </section>

            <section v-show="section === 'attendance-bulk'" class="space-y-4">
                <AttendanceBulkCorrectionPanel :options="attendanceBulkOptions" />
            </section>

            <section v-show="section === 'attendance-cleanup'" class="space-y-4">
                <AttendanceCleanupPanel
                    :months="attendanceCleanupMonths"
                    :result="attendanceCleanupResult"
                />
            </section>

            <section v-show="section === 'master'" class="space-y-4">
                <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <template #title>
                        <div class="flex flex-col gap-3 px-1 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 class="text-base font-bold text-slate-950 dark:text-white">{{ config.title }}</h2>
                                <p class="mt-1 text-xs font-normal leading-5 text-slate-500 dark:text-slate-400">{{ config.description }}</p>
                            </div>
                            <Button :label="`Tambah ${config.singular}`" icon="pi pi-plus" @click="openCreate" />
                        </div>
                    </template>
                    <template #content>
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row">
                            <div class="relative flex-1">
                                <i class="pi pi-search absolute left-3 top-1/2 z-10 -translate-y-1/2 text-sm text-slate-400" />
                                <InputText v-model="search" placeholder="Cari master data..." class="h-11 w-full pl-9" @update:model-value="onSearch" />
                            </div>
                            <Select
                                v-model="perPage"
                                :options="[5, 10, 25, 50, 100]"
                                class="h-11 w-full sm:w-32"
                                @change="navigate({ page: 1 })"
                            />
                        </div>

                        <DataTable
                            :value="records.data"
                            :paginator="true"
                            :rows="records.per_page"
                            :total-records="records.total"
                            :first="(records.current_page - 1) * records.per_page"
                            :lazy="true"
                            removable-sort
                            @page="(event) => navigate({ page: event.page + 1, perPage: event.rows })"
                        >
                            <template #empty>
                                <EmptyState title="Belum ada master data" description="Tambahkan data baru untuk mulai mengisi referensi aplikasi." />
                            </template>
                            <Column header="Nama" style="min-width: 240px">
                                <template #body="{ data }">
                                    <span class="font-semibold text-slate-900 dark:text-white">{{ data.name }}</span>
                                </template>
                            </Column>
                            <Column header="Dipakai" style="min-width: 110px">
                                <template #body="{ data }">
                                    <Tag :severity="data.karyawans_count > 0 ? 'info' : 'secondary'">
                                        {{ data.karyawans_count.toLocaleString("id-ID") }} karyawan
                                    </Tag>
                                </template>
                            </Column>
                            <Column header="Aksi" style="width: 110px" align-frozen="right">
                                <template #body="{ data }">
                                    <div class="flex justify-end gap-1">
                                        <Button icon="pi pi-pencil" text rounded severity="secondary" aria-label="Edit" @click="openEdit(data)" />
                                        <Button icon="pi pi-trash" text rounded severity="danger" aria-label="Hapus" @click="deleteRecord(data)" />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </template>
                </Card>
            </section>

            <section v-show="section === 'import'" class="space-y-4">
                <Message v-if="preview.error" severity="error" :closable="false">{{ preview.error }}</Message>

                <template v-if="previewData">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard label="Source Rows" :value="previewData.source.row_count.toLocaleString('id-ID')" :description="`${previewData.source.database}.${previewData.source.table}`" icon="pi pi-database" />
                        <StatCard label="Target Rows" :value="previewData.target.row_count.toLocaleString('id-ID')" description="Target karyawans" icon="pi pi-table" tone="sky" />
                        <StatCard label="Mapped Fields" :value="previewSummary.mapped_fields.toLocaleString('id-ID')" description="Kolom memiliki padanan" icon="pi pi-directions" tone="emerald" />
                        <StatCard label="Transformed" :value="previewSummary.transformed_fields.toLocaleString('id-ID')" description="Nilai perlu transformasi" icon="pi pi-sync" tone="amber" />
                    </div>

                    <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                        <template #title>
                            <div class="flex flex-col gap-3 px-1 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                <h2 class="text-base font-bold text-slate-950 dark:text-white">Field Mapping</h2>
                                <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Tinjau perubahan nama dan transformasi sebelum menyalin.</p>
                                </div>
                                <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                                    <Checkbox v-model="mappingReviewed" binary />
                                    Mapping sudah ditinjau
                                </label>
                                <Button
                                    label="Copy Karyawan"
                                    icon="pi pi-cloud-download"
                                    :loading="copying"
                                    :disabled="!mappingReviewed"
                                    @click="copyEmployees"
                                />
                            </div>
                        </template>
                        <template #content>
                            <ProgressBar v-if="copying" mode="indeterminate" class="mb-4" />
                            <DataTable :value="previewData.mappings" paginator :rows="10" :rows-per-page-options="[10, 25, 50]" scrollable scroll-height="520px">
                                <Column field="source_field" header="Source Field" style="min-width: 180px">
                                    <template #body="{ data }">{{ data.source_field || "-" }}</template>
                                </Column>
                                <Column field="target_field" header="Target Field" style="min-width: 180px">
                                    <template #body="{ data }">{{ data.target_field || "-" }}</template>
                                </Column>
                                <Column field="source_type" header="Source Type" style="min-width: 160px">
                                    <template #body="{ data }"><span class="font-mono text-xs">{{ data.source_type || "-" }}</span></template>
                                </Column>
                                <Column field="target_type" header="Target Type" style="min-width: 160px">
                                    <template #body="{ data }"><span class="font-mono text-xs">{{ data.target_type || "-" }}</span></template>
                                </Column>
                                <Column field="status" header="Status" style="min-width: 120px">
                                    <template #body="{ data }">
                                        <Tag
                                            :severity="{
                                                direct: 'success',
                                                renamed: 'info',
                                                transformed: 'warn',
                                                skipped: 'danger',
                                                target_only: 'secondary',
                                            }[data.status] || 'secondary'"
                                        >
                                            {{ data.status }}
                                        </Tag>
                                    </template>
                                </Column>
                                <Column field="note" header="Catatan" style="min-width: 300px" />
                            </DataTable>
                        </template>
                    </Card>

                    <div class="grid gap-4 xl:grid-cols-2">
                        <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                            <template #title><h2 class="px-1 text-base font-bold text-slate-950 dark:text-white">Data Quality</h2></template>
                            <template #content>
                                <div class="space-y-3">
                                    <div v-for="item in previewData.data_quality" :key="item.key" class="flex items-start justify-between gap-4 rounded-xl border border-slate-200/80 p-3 dark:border-slate-800">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ item.label }}</p>
                                            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ item.description }}</p>
                                        </div>
                                        <Tag :severity="item.severity === 'warning' ? 'warn' : 'info'">{{ item.count }}</Tag>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                            <template #title><h2 class="px-1 text-base font-bold text-slate-950 dark:text-white">Reference Data</h2></template>
                            <template #content>
                                <DataTable :value="previewData.reference_data" size="small">
                                    <Column field="label" header="Referensi" />
                                    <Column field="source_count" header="Source" />
                                    <Column field="target_count" header="Target" />
                                </DataTable>
                            </template>
                        </Card>
                    </div>
                </template>

                <Card v-if="migrationResult" class="border border-emerald-200 bg-emerald-50/70 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                    <template #title><h2 class="px-1 text-base font-bold text-emerald-900 dark:text-emerald-100">Hasil copy terakhir</h2></template>
                    <template #content>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                            <div v-for="(value, key) in migrationResult.statistics" :key="key">
                                <p class="text-xs text-emerald-700 dark:text-emerald-300">{{ key.replaceAll('_', ' ') }}</p>
                                <p class="mt-1 text-xl font-bold text-emerald-950 dark:text-white">{{ Number(value).toLocaleString("id-ID") }}</p>
                            </div>
                        </div>
                    </template>
                </Card>
            </section>
        </div>

        <Dialog v-model:visible="dialogVisible" :header="editingRecord ? `Edit ${config.singular}` : `Tambah ${config.singular}`" modal class="w-full max-w-lg">
            <form class="space-y-4" @submit.prevent="save">
                <FormField
                    v-for="field in config.fields"
                    :key="field.name"
                    :label="field.label"
                    :error="form.errors[field.name]"
                    required
                >
                    <InputText v-model="form.values[field.name]" :placeholder="field.placeholder" class="w-full" />
                </FormField>
                <div class="flex justify-end gap-2 pt-3">
                    <Button type="button" label="Batal" severity="secondary" text @click="dialogVisible = false" />
                    <Button type="submit" label="Simpan" icon="pi pi-check" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </AdminLayout>
</template>
