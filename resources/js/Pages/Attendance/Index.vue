<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageHeader from "@/Components/Admin/PageHeader.vue";
import StatCard from "@/Components/Admin/StatCard.vue";
import EmptyState from "@/Components/Admin/EmptyState.vue";
import FormField from "@/Components/Admin/FormField.vue";
import EmployeeAutocomplete from "@/Components/Admin/EmployeeAutocomplete.vue";
import AttendancePunchCell from "@/Components/Admin/AttendancePunchCell.vue";

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
const inlineEditingRecordId = ref(null);
const draggedPunch = ref(null);
const dragOverPunch = ref(null);
const deletedPunch = ref(null);
const fileInputKey = ref(0);
const anomalyScanVisible = ref(false);
const anomalyScanLoading = ref(false);
const anomalyScanError = ref("");
const anomalyScanResult = ref(null);
let deletedPunchTimeout = null;
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
const punchColumns = [
    { field: "first_in", label: "First In" },
    { field: "first_out", label: "First Out" },
    { field: "second_in", label: "Second In" },
    { field: "second_out", label: "Second Out" },
    { field: "overtime_in", label: "Overtime In" },
    { field: "overtime_out", label: "Overtime Out" },
];

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

const anomalyScanForm = reactive({
    scope: "active",
    start_date: props.filters.date,
    end_date: props.filters.date,
    short_work_minutes: 60,
    long_work_minutes: 120,
    overtime_threshold_hours: 4,
});

const anomalyScopeOptions = [
    { label: "Tanggal aktif", value: "active" },
    { label: "Rentang tanggal", value: "range" },
];

const inlineForm = useForm({
    status: "present",
    first_in: "",
    first_out: "",
    second_in: "",
    second_out: "",
    overtime_in: "",
    overtime_out: "",
});

watch(
    () => props.filters,
    (nextFilters) => Object.assign(filters, nextFilters),
    { deep: true },
);

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
    inlineEditingRecordId.value = record.id;
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
    inlineEditingRecordId.value = null;
    inlineForm.clearErrors();
    endPunchDrag();
}

function saveInline(record, options = {}) {
    inlineForm.transform((data) => ({
        karyawan_id: record.employee.id,
        attendance_date: record.attendance_date,
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
            if (options.closeOnSuccess !== false) {
                inlineEditingRecordId.value = null;
            }

            options.onSuccess?.();
        },
        onError: (errors) => options.onError?.(errors),
    });
}

function isInlineEditing(record) {
    return inlineEditingRecordId.value === record.id;
}

function isPunchDropTarget(record, field) {
    return dragOverPunch.value?.recordId === record.id
        && dragOverPunch.value?.field === field;
}

function startPunchDrag(record, field, event) {
    if (!isInlineEditing(record) || !inlineForm[field]) {
        event.preventDefault();

        return;
    }

    draggedPunch.value = { recordId: record.id, field };
    dragOverPunch.value = null;
}

function onPunchDragOver(record, field, event) {
    const source = draggedPunch.value;

    if (
        !source
        || source.recordId !== record.id
        || source.field === field
        || inlineForm.processing
    ) {
        return;
    }

    event.preventDefault();
    dragOverPunch.value = { recordId: record.id, field };
}

function endPunchDrag() {
    draggedPunch.value = null;
    dragOverPunch.value = null;
}

function dropPunch(record, field, event) {
    event.preventDefault();

    const source = draggedPunch.value;

    if (!source || source.recordId !== record.id || source.field === field) {
        endPunchDrag();

        return;
    }

    const sourceValue = inlineForm[source.field] || "";
    const targetValue = inlineForm[field] || "";
    inlineForm[source.field] = targetValue;
    inlineForm[field] = sourceValue;
    endPunchDrag();

    saveInline(record, {
        closeOnSuccess: false,
        onError: () => {
            inlineForm[source.field] = sourceValue;
            inlineForm[field] = targetValue;
        },
    });
}

function deletePunch(record, field) {
    if (!isInlineEditing(record) || !inlineForm[field] || inlineForm.processing) {
        return;
    }

    const previousValue = inlineForm[field];
    const punchLabel = punchColumns.find((punch) => punch.field === field)?.label || field;
    inlineForm[field] = "";

    saveInline(record, {
        closeOnSuccess: false,
        onSuccess: () => rememberDeletedPunch(record.id, field, punchLabel, previousValue),
        onError: () => {
            inlineForm[field] = previousValue;
        },
    });
}

function rememberDeletedPunch(recordId, field, label, value) {
    window.clearTimeout(deletedPunchTimeout);
    deletedPunch.value = { recordId, field, label, value };
    deletedPunchTimeout = window.setTimeout(() => {
        deletedPunch.value = null;
    }, 8000);
}

function undoPunchDelete() {
    const deletion = deletedPunch.value;

    if (!deletion) {
        return;
    }

    const record = props.records.data.find((item) => item.id === deletion.recordId);

    if (!record) {
        deletedPunch.value = null;

        return;
    }

    if (!isInlineEditing(record)) {
        startInlineEdit(record);
    }

    inlineForm[deletion.field] = deletion.value;
    saveInline(record, {
        closeOnSuccess: false,
        onSuccess: () => {
            window.clearTimeout(deletedPunchTimeout);
            deletedPunch.value = null;
        },
    });
}

onBeforeUnmount(() => window.clearTimeout(deletedPunchTimeout));

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

function clearReview(record) {
    confirm.require({
        message: `Hapus status review ${record.employee.name} tanggal ${formatDate(record.attendance_date)}? Punch dan jam kerja tidak akan berubah.`,
        header: "Hapus status review?",
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Batal",
        acceptLabel: "Hapus review",
        rejectClass: "p-button-secondary p-button-outlined",
        acceptClass: "p-button-warning",
        accept: () => router.delete(
            route("admin.attendance.destroy-review", record.id),
            { preserveScroll: true },
        ),
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

function openAnomalyScan() {
    anomalyScanError.value = "";
    anomalyScanResult.value = null;
    anomalyScanForm.scope = "active";
    anomalyScanForm.start_date = filters.date;
    anomalyScanForm.end_date = filters.date;
    anomalyScanVisible.value = true;
}

function changeAnomalyScanScope() {
    if (anomalyScanForm.scope === "active") {
        anomalyScanForm.start_date = filters.date;
        anomalyScanForm.end_date = filters.date;
    }
}

async function runAnomalyScan() {
    anomalyScanLoading.value = true;
    anomalyScanError.value = "";

    try {
        const startDate = anomalyScanForm.scope === "active"
            ? filters.date
            : anomalyScanForm.start_date;
        const endDate = anomalyScanForm.scope === "active"
            ? filters.date
            : anomalyScanForm.end_date;
        const response = await fetch(route("admin.attendance.anomalies", {
            start_date: startDate,
            end_date: endDate,
            short_work_minutes: anomalyScanForm.short_work_minutes,
            long_work_minutes: anomalyScanForm.long_work_minutes,
            overtime_threshold_hours: anomalyScanForm.overtime_threshold_hours,
        }), {
            headers: {
                Accept: "application/json",
            },
        });
        const payload = await response.json();

        if (!response.ok) {
            anomalyScanError.value = payload.errors
                ? Object.values(payload.errors).flat().join(" ")
                : payload.message || "Scan anomali gagal.";

            return;
        }

        anomalyScanResult.value = payload;
    } catch {
        anomalyScanError.value = "Scan anomali gagal dihubungi. Coba lagi.";
    } finally {
        anomalyScanLoading.value = false;
    }
}

function anomalySeverity(severity) {
    return {
        critical: "danger",
        warning: "warn",
        info: "info",
    }[severity] || "secondary";
}

function anomalySeverityLabel(severity) {
    return {
        critical: "Critical",
        warning: "Warning",
        info: "Info",
    }[severity] || severity;
}

function punchSummary(anomaly) {
    return [
        ["IN", anomaly.first_in, 0],
        ["OUT", anomaly.first_out, anomaly.first_out_day_offset],
        ["IN 2", anomaly.second_in, anomaly.second_in_day_offset],
        ["OUT 2", anomaly.second_out, anomaly.second_out_day_offset],
        ["OT IN", anomaly.overtime_in, anomaly.overtime_in_day_offset],
        ["OT OUT", anomaly.overtime_out, anomaly.overtime_out_day_offset],
    ]
        .filter(([, value]) => value)
        .map(([label, value, dayOffset]) => `${label} ${value}${dayOffset ? " +1d" : ""}`)
        .join(" · ") || "-";
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
                    <Button label="Scan Anomali" icon="pi pi-search" severity="warn" outlined @click="openAnomalyScan" />
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
                    <Message v-if="deletedPunch" severity="info" :closable="false" class="mb-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <span>{{ deletedPunch.label }} berhasil dihapus. Perhitungan absensi sudah diperbarui.</span>
                            <Button label="Undo" icon="pi pi-undo" size="small" text severity="info" :disabled="inlineForm.processing" @click="undoPunchDelete" />
                        </div>
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
                        <Column field="employee.nip" header="NIP" frozen sortable style="min-width: 150px">
                            <template #body="{ data }">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-semibold text-slate-900 dark:text-white">{{ data.employee.nip }}</span>
                                    <template v-if="isInlineEditing(data)">
                                        <Button icon="pi pi-check" text rounded severity="success" aria-label="Simpan" :loading="inlineForm.processing" @click="saveInline(data)" />
                                        <Button icon="pi pi-times" text rounded severity="secondary" aria-label="Batal" @click="cancelInlineEdit" />
                                    </template>
                                    <Button v-else icon="pi pi-pencil" text rounded severity="secondary" aria-label="Edit baris" @click="startInlineEdit(data)" />
                                </div>
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
                        <Column field="status" header="Status" sortable style="min-width: 190px">
                            <template #body="{ data }">
                                <Select
                                    v-if="isInlineEditing(data)"
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
                                    <Button
                                        v-if="data.requires_review"
                                        label="Hapus review"
                                        icon="pi pi-check"
                                        size="small"
                                        text
                                        severity="warning"
                                        @click="clearReview(data)"
                                    />
                                    <p
                                        v-if="data.review_resolved_by"
                                        class="basis-full text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        Review dihapus oleh {{ data.review_resolved_by }}
                                    </p>
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
                        <Column
                            v-for="punch in punchColumns"
                            :key="punch.field"
                            :field="punch.field"
                            :header="punch.label"
                            sortable
                            style="min-width: 170px"
                        >
                            <template #body="{ data }">
                                <AttendancePunchCell
                                    :editing="isInlineEditing(data)"
                                    :field="punch.field"
                                    :label="punch.label"
                                    :model-value="isInlineEditing(data) ? inlineForm[punch.field] : (data[punch.field] || '')"
                                    :display-value="data[punch.field]"
                                    :day-offset="data[`${punch.field}_day_offset`] || 0"
                                    :invalid="Boolean(inlineForm.errors[punch.field])"
                                    :disabled="inlineForm.processing"
                                    :drop-active="isPunchDropTarget(data, punch.field)"
                                    @update:model-value="inlineForm[punch.field] = $event"
                                    @drag-start="startPunchDrag(data, punch.field, $event)"
                                    @drag-over="onPunchDragOver(data, punch.field, $event)"
                                    @drag-end="endPunchDrag"
                                    @drop="dropPunch(data, punch.field, $event)"
                                    @delete="deletePunch(data, punch.field)"
                                />
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
                        <Column
                            field="monthly_review_count"
                            header="Review bulan ini"
                            sortable
                            style="min-width: 160px"
                        >
                            <template #body="{ data }">
                                <div class="space-y-1">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100">
                                        {{ data.monthly_review_count }} kali
                                    </p>
                                    <Tag v-if="data.review_penalty_hours" severity="danger">
                                        Denda {{ data.review_penalty_hours }} jam
                                    </Tag>
                                    <p v-else class="text-xs text-slate-400">Belum melewati batas 3 review</p>
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
                                    <Button v-if="data.has_attendance" icon="pi pi-trash" text rounded severity="danger" aria-label="Hapus" @click="removeRecord(data)" />
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

        <Dialog
            v-model:visible="anomalyScanVisible"
            header="Scan anomali attendance"
            modal
            :style="{ width: 'min(96vw, 1180px)' }"
            :breakpoints="{ '960px': '96vw' }"
        >
            <div class="space-y-5">
                <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                    Scan memakai jadwal aktif saat ini, termasuk jadwal reguler, pengecualian kalender, hari libur, dan jadwal khusus seperti Ramadan.
                </div>

                <form class="space-y-4" @submit.prevent="runAnomalyScan">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                        <FormField label="Cakupan scan">
                            <Select
                                v-model="anomalyScanForm.scope"
                                :options="anomalyScopeOptions"
                                option-label="label"
                                option-value="value"
                                class="w-full"
                                @update:model-value="changeAnomalyScanScope"
                            />
                        </FormField>
                        <FormField label="Tanggal awal">
                            <InputText
                                v-model="anomalyScanForm.start_date"
                                type="date"
                                class="w-full"
                                :disabled="anomalyScanForm.scope === 'active'"
                            />
                        </FormField>
                        <FormField label="Tanggal akhir">
                            <InputText
                                v-model="anomalyScanForm.end_date"
                                type="date"
                                class="w-full"
                                :disabled="anomalyScanForm.scope === 'active'"
                            />
                        </FormField>
                        <FormField label="Kurang dari standar (menit)">
                            <InputNumber
                                v-model="anomalyScanForm.short_work_minutes"
                                :min="0"
                                :max="1440"
                                class="w-full"
                                input-class="w-full"
                            />
                        </FormField>
                        <FormField label="Lembur maksimal (jam)">
                            <InputNumber
                                v-model="anomalyScanForm.overtime_threshold_hours"
                                :min="0"
                                :max="24"
                                :min-fraction-digits="0"
                                :max-fraction-digits="1"
                                class="w-full"
                                input-class="w-full"
                            />
                        </FormField>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                        <FormField label="Lebih dari standar (menit)">
                            <InputNumber
                                v-model="anomalyScanForm.long_work_minutes"
                                :min="0"
                                :max="1440"
                                class="w-full"
                                input-class="w-full"
                            />
                        </FormField>
                        <div class="flex items-end gap-2 xl:col-span-4">
                            <Button
                                type="submit"
                                label="Scan sekarang"
                                icon="pi pi-search"
                                severity="warn"
                                :loading="anomalyScanLoading"
                            />
                            <span class="pb-3 text-xs text-slate-500 dark:text-slate-400">
                                Maksimal 31 hari per scan.
                            </span>
                        </div>
                    </div>
                </form>

                <Message v-if="anomalyScanError" severity="error" :closable="false">
                    {{ anomalyScanError }}
                </Message>

                <template v-if="anomalyScanResult">
                    <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
                        <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                            <p class="text-xs text-slate-500">Record dipindai</p>
                            <p class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ anomalyScanResult.summary.records }}</p>
                            <p v-if="anomalyScanResult.summary.skipped_review" class="mt-1 text-xs text-slate-500">
                                {{ anomalyScanResult.summary.skipped_review }} review dilewati
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                            <p class="text-xs text-slate-500">Record terdampak</p>
                            <p class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ anomalyScanResult.summary.affected_records }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                            <p class="text-xs text-slate-500">Karyawan</p>
                            <p class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ anomalyScanResult.summary.affected_employees }}</p>
                        </div>
                        <div class="rounded-xl border border-red-200 bg-red-50/60 p-3 dark:border-red-500/30 dark:bg-red-500/10">
                            <p class="text-xs text-red-700 dark:text-red-200">Critical</p>
                            <p class="mt-1 text-xl font-bold text-red-700 dark:text-red-200">{{ anomalyScanResult.summary.critical }}</p>
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-3 dark:border-amber-500/30 dark:bg-amber-500/10">
                            <p class="text-xs text-amber-700 dark:text-amber-200">Warning</p>
                            <p class="mt-1 text-xl font-bold text-amber-700 dark:text-amber-200">{{ anomalyScanResult.summary.warning }}</p>
                        </div>
                        <div class="rounded-xl border border-sky-200 bg-sky-50/60 p-3 dark:border-sky-500/30 dark:bg-sky-500/10">
                            <p class="text-xs text-sky-700 dark:text-sky-200">Info</p>
                            <p class="mt-1 text-xl font-bold text-sky-700 dark:text-sky-200">{{ anomalyScanResult.summary.info }}</p>
                        </div>
                    </div>

                    <DataTable
                        :value="anomalyScanResult.results"
                        paginator
                        :rows="10"
                        :rows-per-page-options="[10, 25, 50]"
                        scrollable
                        scroll-height="480px"
                    >
                        <template #empty>
                            <EmptyState title="Tidak ada anomali" description="Tidak ditemukan anomali pada periode dan threshold ini." icon="pi pi-check-circle" />
                        </template>
                        <Column header="Severity" style="min-width: 105px">
                            <template #body="{ data }">
                                <Tag :severity="anomalySeverity(data.severity)">{{ anomalySeverityLabel(data.severity) }}</Tag>
                            </template>
                        </Column>
                        <Column header="Tanggal" style="min-width: 105px">
                            <template #body="{ data }">{{ formatDate(data.attendance_date) }}</template>
                        </Column>
                        <Column header="Karyawan" style="min-width: 210px">
                            <template #body="{ data }">
                                <p class="font-semibold text-slate-900 dark:text-white">{{ data.employee_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ data.employee_nip }} · {{ data.department || "-" }}</p>
                            </template>
                        </Column>
                        <Column header="Jadwal aktif" style="min-width: 190px">
                            <template #body="{ data }">
                                <p class="font-medium text-slate-800 dark:text-slate-100">{{ data.schedule_context }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ data.schedule_profile }} · {{ data.shift }} · {{ data.expected_check_in }}-{{ data.expected_check_out }}</p>
                            </template>
                        </Column>
                        <Column header="Anomali" style="min-width: 310px">
                            <template #body="{ data }">
                                <Tag severity="secondary">{{ data.category }}</Tag>
                                <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">{{ data.message }}</p>
                            </template>
                        </Column>
                        <Column header="Ekspektasi / Aktual" style="min-width: 180px">
                            <template #body="{ data }">
                                <p class="text-xs text-slate-500">Expected: <span class="font-medium text-slate-700 dark:text-slate-200">{{ data.expected ?? "-" }}</span></p>
                                <p class="mt-1 text-xs text-slate-500">Actual: <span class="font-medium text-slate-700 dark:text-slate-200">{{ data.actual ?? "-" }}</span></p>
                            </template>
                        </Column>
                        <Column header="Slot punch" style="min-width: 280px">
                            <template #body="{ data }">
                                <p class="text-xs leading-5 text-slate-600 dark:text-slate-300">{{ punchSummary(data) }}</p>
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </div>
        </Dialog>
    </AdminLayout>
</template>
