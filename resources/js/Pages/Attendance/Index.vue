<script setup>
import { computed, reactive, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageHeader from "@/Components/Admin/PageHeader.vue";
import StatCard from "@/Components/Admin/StatCard.vue";
import EmptyState from "@/Components/Admin/EmptyState.vue";
import FormField from "@/Components/Admin/FormField.vue";
import EmployeeAutocomplete from "@/Components/Admin/EmployeeAutocomplete.vue";

const props = defineProps({
    records: { type: Object, required: true },
    filters: { type: Object, required: true },
    options: { type: Object, required: true },
    stats: { type: Object, required: true },
    recentImports: { type: Array, required: true },
    dateInfo: { type: Object, required: true },
});

const confirm = useConfirm();
const loading = ref(false);
const dialogVisible = ref(false);
const editingRecord = ref(null);
const inlineEditingEmployeeId = ref(null);
const fileInputKey = ref(0);
const filters = reactive({ ...props.filters });
const uploadProgress = computed(() => uploadForm.progress?.percentage ?? 0);
const latestImport = computed(() => props.recentImports[0] ?? null);
const perPageOptions = [5, 10, 15, 25, 50, 100];
const periodLabel = computed(() => formatDate(filters.date));
const longDateLabel = computed(() => {
    const date = new Date(`${filters.date}T00:00:00`);

    return new Intl.DateTimeFormat("id-ID", {
        weekday: "long",
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(date);
});
const editableStatuses = computed(() => props.options.statuses.filter(
    (status) => status.value !== "missing",
));

const form = useForm({
    employee: null,
    attendance_date: new Date().toISOString().slice(0, 10),
    status: "present",
    first_in: "08:00",
    first_out: "17:00",
    overtime_hours: 0,
    notes: "",
});

const uploadForm = useForm({
    attendance_file: null,
});

const inlineForm = useForm({
    status: "present",
    first_in: "",
    first_out: "",
    second_in: "",
    second_out: "",
    overtime_in: "",
    overtime_out: "",
});

function load() {
    router.get(route("admin.attendance.index"), queryParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => {
            loading.value = true;
        },
        onFinish: () => {
            loading.value = false;
        },
    });
}

function openCreate() {
    editingRecord.value = null;
    form.reset();
    form.clearErrors();
    form.attendance_date = filters.date;
    form.status = "present";
    form.first_in = "08:00";
    form.first_out = "17:00";
    form.overtime_hours = 0;
    dialogVisible.value = true;
}

function openEdit(record) {
    editingRecord.value = record;
    form.clearErrors();
    form.employee = {
        value: record.employee.id,
        label: `${record.employee.nip} - ${record.employee.name}`,
        nama: record.employee.name,
        nip: record.employee.nip,
    };
    form.attendance_date = record.attendance_date;
    form.status = record.status;
    form.first_in = record.first_in;
    form.first_out = record.first_out;
    form.overtime_hours = record.overtime_hours;
    form.notes = record.notes || "";
    dialogVisible.value = true;
}

function startInlineEdit(record) {
    inlineEditingEmployeeId.value = record.employee.id;
    inlineForm.clearErrors();
    inlineForm.status = record.status === "missing" ? "present" : record.status;
    inlineForm.first_in = record.first_in || "";
    inlineForm.first_out = record.first_out || "";
    inlineForm.second_in = record.second_in || "";
    inlineForm.second_out = record.second_out || "";
    inlineForm.overtime_in = record.overtime_in || "";
    inlineForm.overtime_out = record.overtime_out || "";
}

function cancelInlineEdit() {
    inlineEditingEmployeeId.value = null;
    inlineForm.clearErrors();
}

function saveInline(record) {
    inlineForm.transform((data) => ({
        karyawan_id: record.employee.id,
        attendance_date: filters.date,
        status: data.status,
        first_in: data.first_in || null,
        first_out: data.first_out || null,
        second_in: data.second_in || null,
        second_out: data.second_out || null,
        overtime_in: data.overtime_in || null,
        overtime_out: data.overtime_out || null,
    }));

    inlineForm.put(route("admin.attendance.update-inline"), {
        preserveScroll: true,
        onSuccess: () => {
            inlineEditingEmployeeId.value = null;
        },
    });
}

function save() {
    form.transform((data) => ({
        karyawan_id: data.employee?.value,
        attendance_date: data.attendance_date,
        status: data.status,
        first_in: data.first_in || null,
        first_out: data.first_out || null,
        overtime_hours: data.overtime_hours,
        notes: data.notes,
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogVisible.value = false;
        },
    };

    if (editingRecord.value) {
        form.put(route("admin.attendance.update", editingRecord.value.id), options);

        return;
    }

    form.post(route("admin.attendance.store"), options);
}

function removeRecord(record) {
    confirm.require({
        message: `Hapus absensi ${record.employee.name} tanggal ${formatDate(record.attendance_date)}?`,
        header: "Hapus catatan absensi?",
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Batal",
        acceptLabel: "Hapus",
        rejectClass: "p-button-secondary p-button-outlined",
        acceptClass: "p-button-danger",
        accept: () => router.delete(route("admin.attendance.destroy", record.id), { preserveScroll: true }),
    });
}

function chooseUploadFile(event) {
    uploadForm.attendance_file = event.target.files?.[0] ?? null;
    uploadForm.clearErrors();
}

function uploadAttendance() {
    uploadForm.post(route("admin.attendance.import"), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset();
            fileInputKey.value++;
        },
    });
}

function scrollToImport() {
    document.getElementById("attendance-import")?.scrollIntoView({
        behavior: "smooth",
        block: "start",
    });
}

function onPage(event) {
    router.get(route("admin.attendance.index"), {
        ...queryParams(),
        page: event.page + 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function changePerPage(value) {
    if (value === null || value === undefined) {
        return;
    }

    filters.perPage = Number(value);
    load();
}

function clearSearch() {
    filters.search = "";
    load();
}

function changeCompany() {
    filters.directorateId = null;
    filters.departmentId = null;
    filters.jabatanId = null;
    load();
}

function changeDirectorate() {
    filters.departmentId = null;
    filters.jabatanId = null;
    load();
}

function changeDepartment() {
    filters.jabatanId = null;
    load();
}

function onSort(event) {
    filters.sort = event.sortField || "has_missing_punch";
    filters.direction = event.sortOrder === 1 ? "asc" : "desc";
    load();
}

function changeDate(days) {
    const date = new Date(`${filters.date}T00:00:00`);
    date.setDate(date.getDate() + days);
    filters.date = [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, "0"),
        String(date.getDate()).padStart(2, "0"),
    ].join("-");
    load();
}

function goToToday() {
    filters.date = new Date().toISOString().slice(0, 10);
    load();
}

function deleteAttendanceDay() {
    confirm.require({
        message: `Seluruh attendance tanggal ${periodLabel.value} akan dihapus permanen.`,
        header: "Hapus attendance harian?",
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Batal",
        acceptLabel: "Hapus semua",
        rejectClass: "p-button-secondary p-button-outlined",
        acceptClass: "p-button-danger",
        accept: () => router.delete(route("admin.attendance.destroy-day"), {
            data: { attendance_date: filters.date },
            preserveScroll: true,
        }),
    });
}

function queryParams() {
    return {
        date: filters.date,
        status: filters.status,
        company_id: filters.companyId,
        directorate_id: filters.directorateId,
        department_id: filters.departmentId,
        jabatan_id: filters.jabatanId,
        search: filters.search,
        perPage: filters.perPage,
        late_only: filters.lateOnly ? 1 : 0,
        sort: filters.sort,
        direction: filters.direction,
    };
}

function formatDate(value) {
    if (!value) {
        return "-";
    }

    const [year, month, day] = value.split("-");

    return `${day}/${month}/${year}`;
}

function formatDateTime(value) {
    if (!value) {
        return "-";
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(value));
}

function scheduleLabel(record) {
    if (!record.schedule_label && !record.shift_label) {
        return "-";
    }

    return [record.schedule_label, record.shift_label].filter(Boolean).join(" · ");
}

function statusSeverity(status) {
    return {
        missing: "danger",
        present: "success",
        remote: "info",
        late: "warn",
        leave: "secondary",
        sick: "warn",
        absent: "danger",
    }[status] || "secondary";
}
</script>

<template>
    <Head title="Attendance" />

    <AdminLayout>
        <div class="space-y-6">
            <PageHeader
                title="Attendance"
                description="Catat dan pantau kehadiran harian karyawan, keterlambatan, cuti, serta lembur."
                eyebrow="Daily operations"
            >
                <template #actions>
                    <Button label="Hapus Harian" icon="pi pi-trash" severity="danger" outlined @click="deleteAttendanceDay" />
                    <Button label="Import Excel" icon="pi pi-file-excel" severity="secondary" outlined @click="scrollToImport" />
                    <Button label="Tambah Absensi" icon="pi pi-plus" severity="danger" @click="openCreate" />
                </template>
            </PageHeader>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <StatCard label="Hadir" :value="stats.present.toLocaleString('id-ID')" description="Hadir dan remote" icon="pi pi-check-circle" tone="emerald" />
                <StatCard label="Terlambat" :value="stats.late.toLocaleString('id-ID')" description="Perlu perhatian" icon="pi pi-clock" tone="amber" />
                <StatCard label="Punch belum lengkap" :value="stats.incomplete.toLocaleString('id-ID')" description="Perlu koreksi absensi" icon="pi pi-exclamation-circle" tone="rose" />
                <StatCard label="Cuti / Sakit" :value="stats.leave.toLocaleString('id-ID')" description="Ketidakhadiran terencana" icon="pi pi-calendar" tone="sky" />
                <StatCard label="Lembur" :value="`${stats.overtimeHours} jam`" description="Total periode terpilih" icon="pi pi-hourglass" tone="violet" />
            </div>

            <Card id="attendance-import" class="scroll-mt-24 border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                <template #title>
                    <div class="px-1">
                        <h2 class="text-base font-bold text-slate-950 dark:text-white">Import rekap absensi Excel</h2>
                        <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">
                            Upload format Attendance Logs satu hari. NIP dicocokkan ke data karyawan; ID tanpa punch dilewati.
                        </p>
                    </div>
                </template>
                <template #content>
                    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.8fr)]">
                        <form class="space-y-4" @submit.prevent="uploadAttendance">
                            <div>
                                <label for="attendance-file" class="mb-2 block text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    File rekap Attendance Logs
                                </label>
                                <input
                                    id="attendance-file"
                                    :key="fileInputKey"
                                    type="file"
                                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                    aria-describedby="attendance-file-guidance"
                                    class="block w-full cursor-pointer rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-600 file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-red-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:border-red-400 dark:border-slate-700 dark:bg-slate-950/40 dark:text-slate-300"
                                    @change="chooseUploadFile"
                                />
                                <p id="attendance-file-guidance" class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    Maksimal 5 MB. Double/triple punch dalam waktu berdekatan hanya disimpan sekali; slot masuk dan keluar menggunakan punch efektif pertama.
                                </p>
                                <p v-if="uploadForm.errors.attendance_file" class="mt-2 text-xs font-medium text-red-600 dark:text-red-400">
                                    {{ uploadForm.errors.attendance_file }}
                                </p>
                            </div>

                            <ProgressBar v-if="uploadForm.progress" :value="uploadProgress" :show-value="false" class="h-2" />

                            <div class="flex flex-wrap items-center gap-3">
                                <Button
                                    type="submit"
                                    label="Import dan bandingkan"
                                    icon="pi pi-upload"
                                    severity="danger"
                                    :loading="uploadForm.processing"
                                    :disabled="!uploadForm.attendance_file"
                                />
                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                    Detail nama dan department diambil dari tabel karyawan, bukan dari kolom Excel.
                                </span>
                            </div>
                        </form>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/40">
                            <div v-if="latestImport">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Import terakhir</p>
                                        <p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ latestImport.filename }}</p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            {{ formatDate(latestImport.attendance_date) }} · {{ formatDateTime(latestImport.imported_at) }}
                                        </p>
                                    </div>
                                    <Tag :severity="latestImport.unmatched_employee_count || latestImport.missing_punch_count ? 'warn' : 'success'">
                                        {{ latestImport.unmatched_employee_count || latestImport.missing_punch_count ? "Perlu dicek" : "Selesai" }}
                                    </Tag>
                                </div>
                                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <p class="text-xs text-slate-500">Diproses</p>
                                        <p class="font-semibold text-slate-900 dark:text-white">{{ latestImport.processed_employee_count.toLocaleString("id-ID") }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500">Tanpa punch</p>
                                        <p class="font-semibold text-slate-900 dark:text-white">{{ latestImport.skipped_without_punch_count.toLocaleString("id-ID") }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500">NIP tidak cocok</p>
                                        <p class="font-semibold text-slate-900 dark:text-white">{{ latestImport.unmatched_employee_count.toLocaleString("id-ID") }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500">Missed punch</p>
                                        <p class="font-semibold text-slate-900 dark:text-white">{{ latestImport.missing_punch_count.toLocaleString("id-ID") }}</p>
                                    </div>
                                </div>
                                <div v-if="latestImport.unmatched_user_ids.length" class="mt-4 rounded-lg bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                                    NIP tidak ditemukan:
                                    <span class="font-mono">{{ latestImport.unmatched_user_ids.join(", ") }}</span>
                                </div>
                            </div>
                            <EmptyState
                                v-else
                                title="Belum ada import"
                                description="Upload file Excel untuk mulai menyimpan rekap absensi."
                                icon="pi pi-file-excel"
                            />

                            <div v-if="recentImports.length > 1" class="mt-4 space-y-2 border-t border-slate-200 pt-3 dark:border-slate-800">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Riwayat singkat</p>
                                <div v-for="item in recentImports.slice(1)" :key="item.id" class="flex items-center justify-between gap-3 text-xs">
                                    <span class="truncate text-slate-600 dark:text-slate-300">{{ formatDate(item.attendance_date) }} · {{ item.filename }}</span>
                                    <span class="shrink-0 text-slate-400">{{ item.processed_employee_count }} karyawan</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </Card>

            <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                <template #title>
                    <div class="flex flex-col gap-4 px-1 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Attendance harian</h2>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">
                                Pilih tanggal langsung atau gunakan tombol sebelumnya dan berikutnya.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button icon="pi pi-chevron-left" aria-label="Tanggal sebelumnya" severity="secondary" outlined @click="changeDate(-1)" />
                            <InputText v-model="filters.date" type="date" class="h-11 w-44" @change="load" />
                            <Button icon="pi pi-chevron-right" aria-label="Tanggal berikutnya" severity="secondary" outlined @click="changeDate(1)" />
                            <Button label="Hari ini" severity="secondary" text @click="goToToday" />
                        </div>
                    </div>
                </template>
                <template #content>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <div class="relative">
                            <InputText v-model="filters.search" placeholder="Cari karyawan..." class="h-11 w-full pr-10" @change="load" />
                            <Button v-if="filters.search" icon="pi pi-times" text rounded severity="secondary" class="absolute right-1 top-1/2 -translate-y-1/2" aria-label="Hapus pencarian" @click="clearSearch" />
                        </div>
                        <Select v-model="filters.companyId" :options="options.companies" option-label="label" option-value="value" placeholder="Semua company" filter show-clear class="h-11" @update:model-value="changeCompany" />
                        <Select v-model="filters.directorateId" :options="options.directorates" option-label="label" option-value="value" placeholder="Semua directorate" filter show-clear class="h-11" @update:model-value="changeDirectorate" />
                        <Select v-model="filters.departmentId" :options="options.departments" option-label="label" option-value="value" placeholder="Semua department" filter show-clear class="h-11" @update:model-value="changeDepartment" />
                        <Select v-model="filters.jabatanId" :options="options.jabatans" option-label="label" option-value="value" placeholder="Semua jabatan" filter show-clear class="h-11" @change="load" />
                        <Select v-model="filters.status" :options="options.statuses" option-label="label" option-value="value" placeholder="Semua status" show-clear class="h-11" @change="load" />
                        <label class="flex h-11 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200">
                            <Checkbox v-model="filters.lateOnly" binary @change="load" />
                            Terlambat saja
                        </label>
                        <Button label="Terapkan" icon="pi pi-filter" severity="secondary" outlined @click="load" />
                    </div>
                </template>
            </Card>

            <Card class="overflow-hidden border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                <template #title>
                    <div class="flex flex-col gap-3 px-1 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Daftar absensi</h2>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    {{ longDateLabel }}
                                </p>
                                <Tag v-if="dateInfo.isSunday" severity="warn">Minggu</Tag>
                                <Tag v-if="dateInfo.isHoliday" severity="danger">
                                    Libur{{ dateInfo.holidayName ? `: ${dateInfo.holidayName}` : "" }}
                                </Tag>
                            </div>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">{{ records.total.toLocaleString("id-ID") }} catatan ditemukan</p>
                        </div>
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                            Baris per halaman
                            <Select
                                v-model="filters.perPage"
                                :options="perPageOptions"
                                class="w-24"
                                @update:model-value="changePerPage"
                            />
                        </label>
                    </div>
                </template>
                <template #content>
                    <Message v-if="inlineForm.hasErrors" severity="error" :closable="false" class="mb-3">
                        {{ Object.values(inlineForm.errors)[0] }}
                    </Message>
                    <DataTable
                        :value="records.data"
                        :loading="loading"
                        :paginator="true"
                        :rows="records.per_page"
                        :total-records="records.total"
                        :first="(records.current_page - 1) * records.per_page"
                        :lazy="true"
                        :sort-field="filters.sort"
                        :sort-order="filters.direction === 'asc' ? 1 : -1"
                        scrollable
                        @page="onPage"
                        @sort="onSort"
                    >
                        <template #empty>
                            <EmptyState title="Belum ada absensi" description="Tambahkan absensi pertama atau ubah periode filter." icon="pi pi-calendar-plus" />
                        </template>
                        <Column field="employee.nip" header="NIP" frozen sortable style="min-width: 110px">
                            <template #body="{ data }">
                                <span class="font-mono font-semibold text-slate-900 dark:text-white">{{ data.employee.nip }}</span>
                            </template>
                        </Column>
                        <Column field="employee.name" header="Karyawan" frozen sortable style="min-width: 220px">
                            <template #body="{ data }">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-white">{{ data.employee.name }}</p>
                                    <p class="text-xs text-slate-400">{{ data.employee.department || "-" }} · {{ data.employee.position || "-" }}</p>
                                </div>
                            </template>
                        </Column>
                        <Column field="status" header="Status" sortable style="min-width: 140px">
                            <template #body="{ data }">
                                <Select
                                    v-if="inlineEditingEmployeeId === data.employee.id"
                                    v-model="inlineForm.status"
                                    :options="editableStatuses"
                                    option-label="label"
                                    option-value="value"
                                    class="w-36"
                                    :invalid="Boolean(inlineForm.errors.status)"
                                />
                                <div v-else class="flex flex-wrap gap-1">
                                    <Tag :severity="statusSeverity(data.status)">{{ data.status_label }}</Tag>
                                    <Tag v-if="data.requires_review" severity="warn" icon="pi pi-exclamation-triangle">Review</Tag>
                                </div>
                            </template>
                        </Column>
                        <Column field="schedule_profile" header="Jadwal" sortable style="min-width: 180px">
                            <template #body="{ data }">
                                <p class="font-medium text-slate-800 dark:text-slate-100">{{ scheduleLabel(data) }}</p>
                                <p v-if="data.expected_check_in || data.expected_check_out" class="mt-1 text-xs text-slate-500">
                                    {{ data.expected_check_in || "-" }} → {{ data.expected_check_out || "-" }}
                                </p>
                                <p v-else class="mt-1 text-xs text-slate-400">Jadwal manual</p>
                                <Tag v-if="data.is_holiday_pay" severity="danger" class="mt-2">
                                    Libur x2{{ data.calendar_override_name ? `: ${data.calendar_override_name}` : "" }}
                                </Tag>
                                <Tag v-else-if="data.calendar_override_id" severity="info" class="mt-2">
                                    Hari kerja perusahaan
                                </Tag>
                            </template>
                        </Column>
                        <Column field="first_in" header="First In" sortable style="min-width: 95px">
                            <template #body="{ data }">
                                <InputText v-if="inlineEditingEmployeeId === data.employee.id" v-model="inlineForm.first_in" type="time" class="w-32" :invalid="Boolean(inlineForm.errors.first_in)" />
                                <span v-else>{{ data.first_in || "-" }}</span>
                            </template>
                        </Column>
                        <Column field="first_out" header="First Out" sortable style="min-width: 95px">
                            <template #body="{ data }">
                                <InputText v-if="inlineEditingEmployeeId === data.employee.id" v-model="inlineForm.first_out" type="time" class="w-32" :invalid="Boolean(inlineForm.errors.first_out)" />
                                <span v-else>{{ data.first_out || "-" }}</span>
                            </template>
                        </Column>
                        <Column field="second_in" header="Second In" sortable style="min-width: 100px">
                            <template #body="{ data }">
                                <InputText v-if="inlineEditingEmployeeId === data.employee.id" v-model="inlineForm.second_in" type="time" class="w-32" :invalid="Boolean(inlineForm.errors.second_in)" />
                                <span v-else>{{ data.second_in || "-" }}{{ data.second_in_day_offset ? " +1d" : "" }}</span>
                            </template>
                        </Column>
                        <Column field="second_out" header="Second Out" sortable style="min-width: 105px">
                            <template #body="{ data }">
                                <InputText v-if="inlineEditingEmployeeId === data.employee.id" v-model="inlineForm.second_out" type="time" class="w-32" :invalid="Boolean(inlineForm.errors.second_out)" />
                                <span v-else>{{ data.second_out || "-" }}{{ data.second_out_day_offset ? " +1d" : "" }}</span>
                            </template>
                        </Column>
                        <Column field="overtime_in" header="Overtime In" sortable style="min-width: 120px">
                            <template #body="{ data }">
                                <InputText v-if="inlineEditingEmployeeId === data.employee.id" v-model="inlineForm.overtime_in" type="time" class="w-32" :invalid="Boolean(inlineForm.errors.overtime_in)" />
                                <span v-else>{{ data.overtime_in || "-" }}{{ data.overtime_in_day_offset ? " +1d" : "" }}</span>
                            </template>
                        </Column>
                        <Column field="overtime_out" header="Overtime Out" sortable style="min-width: 125px">
                            <template #body="{ data }">
                                <InputText v-if="inlineEditingEmployeeId === data.employee.id" v-model="inlineForm.overtime_out" type="time" class="w-32" :invalid="Boolean(inlineForm.errors.overtime_out)" />
                                <span v-else>{{ data.overtime_out || "-" }}{{ data.overtime_out_day_offset ? " +1d" : "" }}</span>
                            </template>
                        </Column>
                        <Column field="has_missing_punch" header="Punch" sortable style="min-width: 220px">
                            <template #body="{ data }">
                                <div class="flex flex-wrap items-center gap-1">
                                    <Tag severity="secondary">{{ data.raw_punch_count }} raw</Tag>
                                    <Tag v-if="data.effective_punch_count" severity="info">{{ data.effective_punch_count }} efektif</Tag>
                                    <Tag v-if="data.has_duplicate_punch" severity="secondary" icon="pi pi-copy">{{ data.duplicate_punch_count }} ganda</Tag>
                                    <Tag v-if="data.has_missing_punch" severity="danger" icon="pi pi-exclamation-circle">Missed</Tag>
                                    <Tag v-if="data.ignored_punch_count" severity="secondary">{{ data.ignored_punch_count }} diabaikan</Tag>
                                </div>
                            </template>
                        </Column>
                        <Column field="late_minutes" header="Selisih" sortable style="min-width: 170px">
                            <template #body="{ data }">
                                <div class="space-y-1">
                                    <Tag v-if="data.late_minutes" severity="warn">
                                        Terlambat {{ data.late_minutes }}m
                                        <template v-if="data.late_penalty_hours"> · potong {{ data.late_penalty_hours }} jam</template>
                                    </Tag>
                                    <Tag v-if="data.early_leave_minutes" severity="danger">
                                        Pulang awal {{ data.early_leave_minutes }}m
                                    </Tag>
                                    <span v-if="!data.late_minutes && !data.early_leave_minutes" class="text-xs text-slate-400">Dalam jadwal</span>
                                </div>
                            </template>
                        </Column>
                        <Column field="worked_hours" header="Jam kerja" sortable style="min-width: 100px">
                            <template #body="{ data }">{{ data.worked_hours }} jam</template>
                        </Column>
                        <Column field="overtime_hours" header="Lembur" sortable style="min-width: 100px">
                            <template #body="{ data }">{{ data.overtime_hours }} jam</template>
                        </Column>
                        <Column field="holiday_worked_hours" header="Kerja Libur x2" sortable style="min-width: 125px">
                            <template #body="{ data }">{{ data.holiday_worked_hours }} jam</template>
                        </Column>
                        <Column field="holiday_overtime_hours" header="Lembur Libur x2" sortable style="min-width: 135px">
                            <template #body="{ data }">{{ data.holiday_overtime_hours }} jam</template>
                        </Column>
                        <Column field="notes" header="Catatan" sortable style="min-width: 260px">
                            <template #body="{ data }">
                                <p class="text-xs leading-5 text-slate-600 dark:text-slate-300">{{ data.comparison_notes || data.notes || "-" }}</p>
                            </template>
                        </Column>
                        <Column header="Aksi" style="width: 100px">
                            <template #body="{ data }">
                                <div class="flex justify-end gap-1">
                                    <template v-if="inlineEditingEmployeeId === data.employee.id">
                                        <Button icon="pi pi-check" text rounded severity="success" aria-label="Simpan" :loading="inlineForm.processing" @click="saveInline(data)" />
                                        <Button icon="pi pi-times" text rounded severity="secondary" aria-label="Batal" @click="cancelInlineEdit" />
                                    </template>
                                    <template v-else>
                                        <Button icon="pi pi-pencil" text rounded severity="secondary" aria-label="Edit baris" @click="startInlineEdit(data)" />
                                        <Button v-if="data.has_attendance" icon="pi pi-trash" text rounded severity="danger" aria-label="Hapus" @click="removeRecord(data)" />
                                    </template>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>

        <Dialog v-model:visible="dialogVisible" :header="editingRecord ? 'Edit absensi' : 'Tambah absensi'" modal class="w-full max-w-xl">
            <form class="space-y-4" @submit.prevent="save">
                <FormField label="Karyawan" :error="form.errors.karyawan_id" required>
                    <EmployeeAutocomplete v-model="form.employee" />
                </FormField>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Tanggal" :error="form.errors.attendance_date" required>
                        <InputText v-model="form.attendance_date" type="date" class="w-full" />
                    </FormField>
                    <FormField label="Status" :error="form.errors.status" required>
                        <Select v-model="form.status" :options="options.statuses" option-label="label" option-value="value" class="w-full" />
                    </FormField>
                    <FormField label="Jam Masuk" :error="form.errors.first_in">
                        <InputText v-model="form.first_in" type="time" class="w-full" />
                    </FormField>
                    <FormField label="Jam Keluar" :error="form.errors.first_out">
                        <InputText v-model="form.first_out" type="time" class="w-full" />
                    </FormField>
                    <FormField label="Lembur (jam)" :error="form.errors.overtime_hours" required>
                        <InputNumber v-model="form.overtime_hours" :min="0" :max="24" :min-fraction-digits="0" :max-fraction-digits="1" class="w-full" input-class="w-full" />
                    </FormField>
                    <FormField label="Catatan" :error="form.errors.notes" class="sm:col-span-2">
                        <Textarea v-model="form.notes" rows="3" class="w-full" />
                    </FormField>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" label="Batal" severity="secondary" text @click="dialogVisible = false" />
                    <Button type="submit" label="Simpan absensi" icon="pi pi-check" severity="danger" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </AdminLayout>
</template>
