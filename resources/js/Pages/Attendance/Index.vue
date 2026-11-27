<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import Paginator from "primevue/paginator";
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
    specialWorkSchedules: { type: Array, required: true },
    scheduleGuide: { type: Object, required: true },
    dateInfo: { type: Object, required: true },
});

const confirm = useConfirm();
const toast = useToast();
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
const guideDialogVisible = ref(false);
const guideSection = ref("reguler");
const importDialogVisible = ref(false);
const importSection = ref("rekap");
const filtersExpanded = ref(false);
const notesVisible = ref(false);
let deletedPunchTimeout = null;
const filters = reactive(normalizedFilterState(props.filters));
const uploadProgress = computed(() => uploadForm.progress?.percentage ?? 0);
const latestImport = computed(() => props.recentImports[0] ?? null);
const activeFilterCount = computed(() => [
    filters.search?.trim(),
    filters.companyId,
    filters.directorateId,
    filters.departmentId,
    filters.jabatanId,
    filters.status,
    filters.shift,
    filters.lateOnly,
    filters.reviewOnly,
].filter(Boolean).length);
const importSections = [
    { label: "Rekap Absensi", value: "rekap", icon: "pi pi-upload" },
    { label: "Database Source", value: "database", icon: "pi pi-database" },
    { label: "Riwayat Import", value: "riwayat", icon: "pi pi-history" },
];
const databaseImportModes = [
    { label: "Harian", value: "date" },
    { label: "Satu Bulan", value: "month" },
];
const guideSections = [
    { label: "Jadwal Reguler", value: "reguler", icon: "pi pi-table" },
    { label: "Ketentuan", value: "ketentuan", icon: "pi pi-list-check" },
    { label: "Jadwal Khusus", value: "khusus", icon: "pi pi-calendar-clock" },
];
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
const databaseImportForm = useForm({
    attendance_mode: "date",
    attendance_date: props.filters.date,
    attendance_month: props.filters.date.slice(0, 7),
});
const databasePreviewFilters = reactive({
    attendance_mode: "date",
    attendance_date: props.filters.date,
    attendance_month: props.filters.date.slice(0, 7),
    page: 1,
    per_page: 15,
});
const databasePreview = ref(null);
const databasePreviewedMode = ref("date");
const databasePreviewedPeriod = ref("");
const databasePreviewLoading = ref(false);
const databasePreviewError = ref("");
const databaseImportRunning = ref(false);
const databaseImportResult = ref(null);
const databaseImportProgress = reactive({
    current: 0,
    total: 0,
    date: "",
    processed: 0,
    review: 0,
});
const databasePeriodValue = computed(() => (
    databasePreviewFilters.attendance_mode === "month"
        ? databasePreviewFilters.attendance_month
        : databasePreviewFilters.attendance_date
));

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
    (nextFilters) => {
        if (!loading.value) {
            Object.assign(filters, normalizedFilterState(nextFilters));
        }
    },
    { deep: true },
);

function normalizedFilterState(source) {
    return {
        ...source,
        companyId: filterId(source.companyId),
        directorateId: filterId(source.directorateId),
        departmentId: filterId(source.departmentId),
        jabatanId: filterId(source.jabatanId),
        perPage: Number(source.perPage) || 5,
    };
}

function filterId(value) {
    if (value === null || value === undefined || value === "") {
        return null;
    }

    const id = Number(value);

    return Number.isFinite(id) ? id : null;
}

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
    clearDeletedPunchNotice();
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
    inlineForm.reset();
    inlineForm.clearErrors();
    clearDeletedPunchNotice();
    endPunchDrag();
}

function saveInline(record) {
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
            inlineEditingRecordId.value = null;
            inlineForm.reset();
            clearDeletedPunchNotice();
        },
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
}

function deletePunch(record, field) {
    if (!isInlineEditing(record) || !inlineForm[field] || inlineForm.processing) {
        return;
    }

    const previousValue = inlineForm[field];
    const punchLabel = punchColumns.find((punch) => punch.field === field)?.label || field;
    inlineForm[field] = "";
    rememberDeletedPunch(record.id, field, punchLabel, previousValue);
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

    if (!record || !isInlineEditing(record)) {
        clearDeletedPunchNotice();

        return;
    }

    inlineForm[deletion.field] = deletion.value;
    clearDeletedPunchNotice();
}

function clearDeletedPunchNotice() {
    window.clearTimeout(deletedPunchTimeout);
    deletedPunchTimeout = null;
    deletedPunch.value = null;
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

function resolveReview(record) {
    confirm.require({
        message: `Tandai data absensi ${record.employee.name} tanggal ${formatDate(record.attendance_date)} sudah benar? Punch dan jam kerja tidak akan berubah.`,
        header: "Tandai review selesai?",
        icon: "pi pi-check-circle",
        rejectLabel: "Batal",
        acceptLabel: "Ya, data benar",
        rejectClass: "p-button-secondary p-button-outlined",
        acceptClass: "p-button-success",
        accept: () => router.put(
            route("admin.attendance.resolve-review", record.id),
            { preserveScroll: true },
        ),
    });
}

function deleteReview(record) {
    confirm.require({
        message: `Hapus review ${record.employee.name} tanggal ${formatDate(record.attendance_date)}? Data tidak akan ditandai sebagai sudah benar.`,
        header: "Hapus review?",
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Batal",
        acceptLabel: "Hapus review",
        rejectClass: "p-button-secondary p-button-outlined",
        acceptClass: "p-button-danger",
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
            importDialogVisible.value = false;
        },
    });
}

async function loadDatabasePreview(resetPage = false) {
    if (resetPage) {
        databasePreviewFilters.page = 1;
        databaseImportResult.value = null;
    }

    databasePreviewLoading.value = true;
    databasePreviewError.value = "";

    try {
        const response = await fetch(route("admin.attendance.database-import.preview", {
            attendance_mode: databasePreviewFilters.attendance_mode,
            attendance_date: databasePreviewFilters.attendance_mode === "date"
                ? databasePreviewFilters.attendance_date
                : undefined,
            attendance_month: databasePreviewFilters.attendance_mode === "month"
                ? databasePreviewFilters.attendance_month
                : undefined,
            page: databasePreviewFilters.page,
            per_page: databasePreviewFilters.per_page,
        }), {
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        });
        const payload = await response.json();

        if (!response.ok) {
            databasePreview.value = null;
            databasePreviewedPeriod.value = "";
            databasePreviewError.value = payload.errors
                ? Object.values(payload.errors).flat()[0]
                : payload.message || "Data source tidak dapat dimuat.";

            return;
        }

        databasePreview.value = payload;
        databasePreviewedMode.value = databasePreviewFilters.attendance_mode;
        databasePreviewedPeriod.value = databasePeriodValue.value;
        databasePreviewFilters.page = payload.pagination.current_page;
        databasePreviewFilters.per_page = payload.pagination.per_page;
    } catch {
        databasePreview.value = null;
        databasePreviewedPeriod.value = "";
        databasePreviewError.value = "Database source tidak dapat dihubungi. Periksa konfigurasi koneksi.";
    } finally {
        databasePreviewLoading.value = false;
    }
}

function onDatabasePreviewPage(event) {
    databasePreviewFilters.page = event.page + 1;
    databasePreviewFilters.per_page = event.rows;
    loadDatabasePreview();
}

async function runDatabaseMonthImport(dates) {
    databaseImportRunning.value = true;
    databaseImportResult.value = null;
    databasePreviewError.value = "";
    Object.assign(databaseImportProgress, {
        current: 0,
        total: dates.length,
        date: "",
        processed: 0,
        review: 0,
    });

    try {
        for (const [index, item] of dates.entries()) {
            databaseImportProgress.date = item.attendance_date;
            const response = await fetch(route("admin.attendance.database-import.store-day"), {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-XSRF-TOKEN": decodeURIComponent(
                        document.cookie
                            .split("; ")
                            .find((cookie) => cookie.startsWith("XSRF-TOKEN="))
                            ?.split("=")
                            .slice(1)
                            .join("=") ?? "",
                    ),
                },
                body: JSON.stringify({
                    attendance_mode: "date",
                    attendance_date: item.attendance_date,
                }),
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(firstError(payload));
            }

            databaseImportProgress.current = index + 1;
            databaseImportProgress.processed += payload.processed_employee_count;
            databaseImportProgress.review += payload.review_required_count;
        }

        databaseImportResult.value = {
            dates: dates.length,
            processed: databaseImportProgress.processed,
            review: databaseImportProgress.review,
        };
        toast.add({
            severity: databaseImportProgress.review > 0 ? "warn" : "success",
            summary: "Import satu bulan selesai",
            detail: `${databaseImportProgress.processed.toLocaleString("id-ID")} attendance diproses, `
                + `${databaseImportProgress.review.toLocaleString("id-ID")} perlu review.`,
            life: 6000,
        });
        router.reload({ only: ["recentImports", "stats"] });
    } catch (error) {
        databasePreviewError.value = error instanceof Error
            ? error.message
            : "Import bulanan gagal diproses.";
    } finally {
        databaseImportRunning.value = false;
    }
}

function importDatabaseAttendance() {
    const summary = databasePreview.value?.summary;
    const periodLabel = databasePreviewedMode.value === "month"
        ? formatMonth(databasePreviewedPeriod.value)
        : formatDate(databasePreviewedPeriod.value);

    confirm.require({
        message: `Import ${summary?.matched ?? 0} attendance periode ${periodLabel}? `
            + `${summary?.existing ?? 0} record yang sudah ada akan diperbarui.`,
        header: "Import attendance dari database?",
        icon: "pi pi-database",
        rejectLabel: "Batal",
        acceptLabel: "Ya, import",
        rejectClass: "p-button-secondary p-button-outlined",
        acceptClass: "p-button-success",
        accept: () => {
            if (databasePreviewedMode.value === "month") {
                runDatabaseMonthImport(databasePreview.value?.dates ?? []);

                return;
            }

            databaseImportForm.attendance_mode = databasePreviewedMode.value;
            databaseImportForm.attendance_date = databasePreviewedMode.value === "date"
                ? databasePreviewedPeriod.value
                : "";
            databaseImportForm.attendance_month = databasePreviewedMode.value === "month"
                ? databasePreviewedPeriod.value
                : "";
            databaseImportForm.post(route("admin.attendance.database-import.store"), {
                preserveScroll: true,
                onSuccess: () => {
                    importDialogVisible.value = false;
                    databasePreview.value = null;
                    databasePreviewedPeriod.value = "";
                },
            });
        },
    });
}

function databasePunchSummary(row) {
    return [
        ["IN", row.first_in],
        ["OUT", row.first_out],
        ["IN 2", row.second_in],
        ["OUT 2", row.second_out],
        ["OT IN", row.overtime_in],
        ["OT OUT", row.overtime_out],
    ]
        .filter(([, value]) => value)
        .map(([label, value]) => `${label} ${value}`)
        .join(" · ") || "-";
}

function databaseTargetSeverity(state) {
    return state === "update" ? "warn" : "success";
}

function databaseTargetLabel(state) {
    return state === "update" ? "Perbarui" : "Baru";
}

function downloadScheduleGuide() {
    window.location.assign(route("admin.attendance.schedule-guide.download"));
}

function openImportDialog(section = "rekap") {
    importSection.value = section;
    importDialogVisible.value = true;
}

function openGuideDialog() {
    guideSection.value = "reguler";
    guideDialogVisible.value = true;
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

function clearFilters() {
    filters.search = "";
    filters.companyId = null;
    filters.directorateId = null;
    filters.departmentId = null;
    filters.jabatanId = null;
    filters.status = "";
    filters.shift = "";
    filters.lateOnly = false;
    filters.reviewOnly = false;
    load();
}

function toggleFilters() {
    filtersExpanded.value = !filtersExpanded.value;
}

function toggleNotes() {
    notesVisible.value = !notesVisible.value;
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
        shift: filters.shift,
        company_id: filters.companyId,
        directorate_id: filters.directorateId,
        department_id: filters.departmentId,
        jabatan_id: filters.jabatanId,
        search: filters.search,
        perPage: filters.perPage,
        late_only: filters.lateOnly ? 1 : 0,
        review_only: filters.reviewOnly ? 1 : 0,
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

function formatMonth(value) {
    if (!value) {
        return "-";
    }

    const [year, month] = value.split("-");

    return `${month}/${year}`;
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
    return record.schedule_label || "-";
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
                    <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-wrap">
                        <Button label="Pedoman Jadwal Kerja" icon="pi pi-book" severity="secondary" outlined class="w-full sm:w-auto" @click="openGuideDialog" />
                        <Button label="Scan Anomali" icon="pi pi-search" severity="warn" outlined class="w-full sm:w-auto" @click="openAnomalyScan" />
                        <Button label="Hapus Harian" icon="pi pi-trash" severity="danger" outlined class="w-full sm:w-auto" @click="deleteAttendanceDay" />
                        <Button label="Tambah Absensi" icon="pi pi-plus" severity="secondary" outlined class="w-full sm:w-auto" @click="openCreate" />
                        <Button label="Import Attendance" icon="pi pi-download" severity="danger" class="col-span-2 w-full sm:col-span-1 sm:w-auto" @click="openImportDialog()" />
                    </div>
                </template>
            </PageHeader>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-5">
                <StatCard label="Hadir" :value="stats.present.toLocaleString('id-ID')" description="Hadir dan remote" icon="pi pi-check-circle" tone="emerald" />
                <StatCard label="Terlambat" :value="stats.late.toLocaleString('id-ID')" description="Perlu perhatian" icon="pi pi-clock" tone="amber" />
                <StatCard label="Punch belum lengkap" :value="stats.incomplete.toLocaleString('id-ID')" description="Perlu koreksi absensi" icon="pi pi-exclamation-circle" tone="rose" />
                <StatCard label="Cuti / Sakit" :value="stats.leave.toLocaleString('id-ID')" description="Ketidakhadiran terencana" icon="pi pi-calendar" tone="sky" />
                <StatCard class="col-span-2 sm:col-span-1" label="Lembur" :value="`${stats.overtimeHours} jam`" description="Total periode terpilih" icon="pi pi-hourglass" tone="violet" />
            </div>

            <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                <template #title>
                    <div class="flex flex-col gap-4 px-1 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Attendance harian</h2>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">
                                Pilih tanggal langsung atau gunakan tombol sebelumnya dan berikutnya.
                            </p>
                            <p v-if="!filtersExpanded" class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                                {{ activeFilterCount ? `${activeFilterCount} filter aktif` : "Filter disembunyikan" }}
                            </p>
                        </div>
                        <div class="grid w-full grid-cols-[2.75rem_minmax(0,1fr)_2.75rem] items-center gap-2 sm:flex sm:w-auto sm:flex-wrap">
                            <Button
                                :label="filtersExpanded
                                    ? 'Tutup filter'
                                    : activeFilterCount
                                        ? `Filter (${activeFilterCount})`
                                        : 'Tampilkan filter'"
                                :icon="filtersExpanded ? 'pi pi-chevron-up' : 'pi pi-filter'"
                                severity="secondary"
                                outlined
                                class="col-span-3 w-full justify-center sm:col-span-1 sm:w-auto"
                                @click="toggleFilters"
                            />
                            <Button icon="pi pi-chevron-left" aria-label="Tanggal sebelumnya" severity="secondary" outlined class="w-full sm:w-auto" @click="changeDate(-1)" />
                            <InputText v-model="filters.date" type="date" class="h-11 w-full sm:w-44" @change="load" />
                            <Button icon="pi pi-chevron-right" aria-label="Tanggal berikutnya" severity="secondary" outlined class="w-full sm:w-auto" @click="changeDate(1)" />
                            <Button label="Hari ini" severity="secondary" text class="col-span-3 w-full justify-center sm:col-span-1 sm:w-auto" @click="goToToday" />
                        </div>
                    </div>
                </template>
                <template #content>
                    <Transition
                        enter-active-class="transition duration-150 ease-out"
                        enter-from-class="-translate-y-1 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-100 ease-in"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="-translate-y-1 opacity-0"
                    >
                        <div v-if="filtersExpanded" class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <div class="relative">
                                <InputText v-model="filters.search" placeholder="Cari karyawan..." class="h-11 w-full pr-10" @change="load" />
                                <Button v-if="filters.search" icon="pi pi-times" text rounded severity="secondary" class="absolute right-1 top-1/2 -translate-y-1/2" aria-label="Hapus pencarian" @click="clearSearch" />
                            </div>
                            <Select v-model="filters.companyId" :options="options.companies" option-label="label" option-value="value" placeholder="Semua company" filter show-clear class="h-11" @update:model-value="changeCompany" />
                            <Select v-model="filters.directorateId" :options="options.directorates" option-label="label" option-value="value" placeholder="Semua directorate" filter show-clear class="h-11" @update:model-value="changeDirectorate" />
                            <Select v-model="filters.departmentId" :options="options.departments" option-label="label" option-value="value" placeholder="Semua department" filter show-clear class="h-11" @update:model-value="changeDepartment" />
                            <Select v-model="filters.jabatanId" :options="options.jabatans" option-label="label" option-value="value" placeholder="Semua jabatan" filter show-clear class="h-11" @update:model-value="load" />
                            <Select v-model="filters.status" :options="options.statuses" option-label="label" option-value="value" placeholder="Semua status" show-clear class="h-11" @update:model-value="load" />
                            <Select v-model="filters.shift" :options="options.shifts" option-label="label" option-value="value" placeholder="Semua shift" show-clear class="h-11" @update:model-value="load" />
                            <div class="grid grid-cols-1 gap-2 md:col-span-2 md:flex md:h-11 md:items-center xl:col-span-2">
                                <label class="flex min-h-11 min-w-0 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200 md:h-11 md:flex-1">
                                    <Checkbox v-model="filters.lateOnly" binary @update:model-value="load" />
                                    <span class="truncate">Terlambat saja</span>
                                </label>
                                <label class="flex min-h-11 min-w-0 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200 md:h-11 md:flex-1">
                                    <Checkbox v-model="filters.reviewOnly" binary @update:model-value="load" />
                                    <span class="truncate">Review hari ini</span>
                                </label>
                                <Button label="Clear Filter" icon="pi pi-times" severity="secondary" outlined class="h-11 w-full justify-center md:w-auto md:shrink-0" @click="clearFilters" />
                            </div>
                        </div>
                    </Transition>
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
                        <label class="flex w-full items-center justify-between gap-2 text-xs font-medium text-slate-500 dark:text-slate-400 sm:w-auto sm:justify-start">
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
                            <span>{{ deletedPunch.label }} dihapus dari form. Klik Simpan untuk menerapkan perubahan.</span>
                            <Button label="Undo" icon="pi pi-undo" size="small" text severity="info" :disabled="inlineForm.processing" @click="undoPunchDelete" />
                        </div>
                    </Message>

                    <div class="space-y-3 sm:hidden">
                        <div v-if="loading" class="space-y-3" aria-label="Memuat daftar absensi">
                            <div v-for="index in 3" :key="index" class="animate-pulse rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 space-y-2">
                                        <div class="h-3 w-24 rounded bg-slate-200 dark:bg-slate-700" />
                                        <div class="h-4 w-40 rounded bg-slate-200 dark:bg-slate-700" />
                                        <div class="h-3 w-32 rounded bg-slate-100 dark:bg-slate-800" />
                                    </div>
                                    <div class="h-9 w-9 rounded-lg bg-slate-100 dark:bg-slate-800" />
                                </div>
                                <div class="mt-4 grid grid-cols-2 gap-2">
                                    <div v-for="field in 4" :key="field" class="h-12 rounded-lg bg-slate-100 dark:bg-slate-800" />
                                </div>
                            </div>
                        </div>

                        <template v-else-if="records.data.length">
                            <article
                                v-for="record in records.data"
                                :key="`mobile-${record.id}`"
                                class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
                            >
                                <div class="border-b border-slate-100 p-4 dark:border-slate-800">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-mono text-[11px] font-semibold tracking-wide text-slate-500 dark:text-slate-400">
                                                {{ record.employee.nip }}
                                            </p>
                                            <p class="mt-1 truncate font-semibold text-slate-950 dark:text-white">
                                                {{ record.employee.name }}
                                            </p>
                                            <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                                {{ record.employee.department || "-" }} ·
                                                {{ record.employee.position || "-" }}
                                            </p>
                                        </div>
                                        <Button
                                            icon="pi pi-pencil"
                                            text
                                            rounded
                                            severity="secondary"
                                            aria-label="Edit absensi"
                                            title="Edit absensi"
                                            @click="openEdit(record)"
                                        />
                                    </div>

                                    <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                        <Tag
                                            v-if="record.status === 'present' && !record.requires_review"
                                            severity="success"
                                            icon="pi pi-check-circle"
                                        >
                                            Hadir
                                        </Tag>
                                        <span
                                            v-else-if="record.status === 'late'"
                                            class="inline-flex items-center gap-1 rounded-md bg-yellow-100 px-2 py-1 text-[11px] font-semibold text-yellow-950 ring-1 ring-yellow-300 dark:bg-yellow-400/15 dark:text-yellow-100 dark:ring-yellow-400/30"
                                        >
                                            <i class="pi pi-clock text-[11px]" />
                                            Terlambat
                                        </span>
                                        <Tag
                                            v-else-if="record.status !== 'present'"
                                            :severity="statusSeverity(record.status)"
                                            :icon="record.status === 'absent' ? 'pi pi-times-circle' : undefined"
                                        >
                                            {{ record.status_label }}
                                        </Tag>
                                        <Tag v-if="record.requires_review" severity="danger" icon="pi pi-exclamation-triangle">
                                            Perlu review
                                        </Tag>
                                        <Tag
                                            v-if="record.review_resolved_at && !record.requires_review"
                                            severity="success"
                                            icon="pi pi-check"
                                        >
                                            Done review
                                        </Tag>
                                    </div>
                                </div>

                                <div class="space-y-4 p-4">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span
                                                class="inline-flex rounded-md px-2 py-1 text-[11px] font-semibold"
                                                :class="record.shift === 'night'
                                                    ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300'
                                                    : record.shift === 'day'
                                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                                            >
                                                {{ scheduleLabel(record) }}
                                            </span>
                                            <span v-if="record.expected_check_in || record.expected_check_out" class="text-[11px] text-slate-500 dark:text-slate-400">
                                                {{ record.expected_check_in || "-" }}–{{ record.expected_check_out || "-" }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <div
                                            v-for="punch in punchColumns"
                                            :key="`mobile-${record.id}-${punch.field}`"
                                            class="rounded-lg border border-slate-100 bg-slate-50/80 px-2.5 py-2 dark:border-slate-800 dark:bg-slate-950/40"
                                        >
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                                                {{ punch.label }}
                                            </p>
                                            <p
                                                class="mt-0.5 font-mono text-xs font-semibold"
                                                :class="(record.late_fields || []).includes(punch.field)
                                                    ? 'rounded bg-orange-100 px-1 text-orange-800 dark:bg-orange-500/15 dark:text-orange-200'
                                                    : 'text-slate-800 dark:text-slate-100'"
                                            >
                                                {{ record[punch.field] || "—" }}
                                                <span
                                                    v-if="record[punch.field] && record[`${punch.field}_day_offset`]"
                                                    class="ml-1 text-[9px] font-medium text-slate-400"
                                                >
                                                    +{{ record[`${punch.field}_day_offset`] }}d
                                                </span>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-3 gap-2 text-center">
                                        <div class="rounded-xl bg-slate-50 px-2 py-2 dark:bg-slate-950/40">
                                            <p class="text-[10px] font-medium text-slate-400">Jam kerja</p>
                                            <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ record.worked_hours }}</p>
                                        </div>
                                        <div class="rounded-xl bg-slate-50 px-2 py-2 dark:bg-slate-950/40">
                                            <p class="text-[10px] font-medium text-slate-400">Lembur</p>
                                            <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ record.overtime_hours }}</p>
                                            <p v-if="record.night_shift_allowance" class="mt-0.5 text-[10px] font-semibold text-indigo-700 dark:text-indigo-300">
                                                +{{ record.night_shift_allowance }} jam malam
                                            </p>
                                        </div>
                                        <div class="rounded-xl bg-slate-50 px-2 py-2 dark:bg-slate-950/40">
                                            <p class="text-[10px] font-medium text-slate-400">Selisih</p>
                                            <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">
                                                {{ record.late_minutes ? `${record.late_minutes}m` : record.early_leave_minutes ? `${record.early_leave_minutes}m` : "—" }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-1">
                                        <Tag severity="secondary" class="attendance-compact-tag">{{ record.raw_punch_count }} raw</Tag>
                                        <Tag v-if="record.has_missing_punch" severity="danger" icon="pi pi-exclamation-circle" class="attendance-compact-tag">Missed</Tag>
                                        <Tag v-if="record.has_duplicate_punch" severity="secondary" icon="pi pi-copy" class="attendance-compact-tag">{{ record.duplicate_punch_count }} ganda</Tag>
                                    </div>

                                    <p v-if="record.review_resolved_at && !record.requires_review" class="text-[11px] leading-5 text-emerald-700 dark:text-emerald-300">
                                        Reviewed by {{ record.review_resolved_by || "-" }} · {{ formatDateTime(record.review_resolved_at) }}
                                    </p>

                                    <div>
                                        <Button
                                            :label="notesVisible ? 'Sembunyikan catatan' : 'Tampilkan catatan'"
                                            :icon="notesVisible ? 'pi pi-eye-slash' : 'pi pi-eye'"
                                            size="small"
                                            text
                                            severity="secondary"
                                            class="-ml-2"
                                            @click="toggleNotes"
                                        />
                                        <p v-if="notesVisible" class="mt-1 rounded-lg bg-slate-50 p-3 text-xs leading-5 text-slate-600 dark:bg-slate-950/40 dark:text-slate-300">
                                            {{ record.comparison_notes || record.notes || "Belum ada catatan." }}
                                        </p>
                                    </div>

                                    <div
                                        v-if="record.requires_review || record.review_resolved_at || record.has_attendance"
                                        class="flex items-center justify-end gap-1 border-t border-slate-100 pt-3 dark:border-slate-800"
                                    >
                                        <Button
                                            v-if="record.requires_review"
                                            icon="pi pi-check"
                                            size="small"
                                            text
                                            rounded
                                            severity="success"
                                            aria-label="Done review"
                                            title="Done review"
                                            @click="resolveReview(record)"
                                        />
                                        <Button
                                            v-if="record.requires_review || record.review_resolved_at"
                                            icon="pi pi-times"
                                            size="small"
                                            text
                                            rounded
                                            severity="danger"
                                            aria-label="Hapus review"
                                            title="Hapus review"
                                            @click="deleteReview(record)"
                                        />
                                        <Button
                                            v-if="record.has_attendance"
                                            icon="pi pi-trash"
                                            text
                                            rounded
                                            severity="danger"
                                            aria-label="Hapus absensi"
                                            title="Hapus absensi"
                                            @click="removeRecord(record)"
                                        />
                                    </div>
                                </div>
                            </article>

                            <Paginator
                                :first="(records.current_page - 1) * records.per_page"
                                :rows="records.per_page"
                                :total-records="records.total"
                                template="PrevPage, Page, NextPage"
                                class="attendance-mobile-paginator"
                                @page="onPage"
                            />
                        </template>

                        <EmptyState
                            v-else
                            title="Belum ada absensi"
                            description="Tambahkan absensi pertama atau ubah periode filter."
                            icon="pi pi-calendar-plus"
                        />
                    </div>

                    <DataTable
                        :value="records.data"
                        :loading="loading"
                        size="small"
                        class="attendance-table hidden sm:block"
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
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-if="data.status === 'present' && !data.requires_review"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                                        title="Hadir"
                                        aria-label="Hadir"
                                    >
                                        <i class="pi pi-check-circle text-[12px]" />
                                    </span>
                                    <span
                                        v-else-if="data.status === 'late'"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-yellow-100 text-yellow-950 ring-1 ring-yellow-300 dark:bg-yellow-400/15 dark:text-yellow-100 dark:ring-yellow-400/30"
                                        title="Terlambat"
                                        aria-label="Terlambat"
                                    >
                                        <i class="pi pi-clock text-[12px]" />
                                    </span>
                                    <Tag v-else-if="data.status !== 'present'" :severity="statusSeverity(data.status)">{{ data.status_label }}</Tag>
                                    <span
                                        v-if="data.requires_review"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-red-100 text-red-700 ring-1 ring-red-200 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-500/30"
                                        title="Perlu review"
                                        aria-label="Perlu review"
                                    >
                                        <i class="pi pi-exclamation-triangle text-[12px]" />
                                    </span>
                                    <span
                                        v-if="data.review_resolved_at && !data.requires_review"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                                        title="Done review"
                                        aria-label="Done review"
                                    >
                                        <i class="pi pi-check text-[12px]" />
                                    </span>
                                    <p
                                        v-if="data.review_resolved_at && !data.requires_review"
                                        class="basis-full text-xs font-medium text-emerald-700 dark:text-emerald-300"
                                    >
                                        Reviewed by
                                        <template v-if="data.review_resolved_by">{{ data.review_resolved_by }}</template>
                                        · {{ formatDateTime(data.review_resolved_at) }}
                                    </p>
                                </div>
                            </template>
                        </Column>
                        <Column field="schedule_profile" header="Jadwal" sortable style="min-width: 180px">
                            <template #body="{ data }">
                                <span
                                    class="inline-flex rounded-md px-2 py-0.5 text-xs font-semibold"
                                    :class="data.shift === 'night'
                                        ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300'
                                        : data.shift === 'day'
                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                                    :title="data.shift === 'night' ? 'Shift malam' : data.shift === 'day' ? 'Shift pagi' : null"
                                >
                                    {{ scheduleLabel(data) }}
                                </span>
                                <p v-if="data.expected_check_in || data.expected_check_out" class="mt-1 text-xs text-slate-500">
                                    {{ data.expected_check_in || "-" }} → {{ data.expected_check_out || "-" }}
                                </p>
                                <p v-else class="mt-1 text-xs text-slate-400">Jadwal manual</p>
                                <Tag v-if="data.is_holiday_pay" severity="danger" class="mt-2 !px-2 !py-0.5 !text-[10px]">
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
                                    :late="(data.late_fields || []).includes(punch.field)"
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
                                    <Tag severity="secondary" class="attendance-compact-tag">{{ data.raw_punch_count }} raw</Tag>
                                    <Tag v-if="data.effective_punch_count" severity="info" class="attendance-compact-tag">{{ data.effective_punch_count }} efektif</Tag>
                                    <Tag v-if="data.has_duplicate_punch" severity="secondary" icon="pi pi-copy" class="attendance-compact-tag">{{ data.duplicate_punch_count }} ganda</Tag>
                                    <Tag v-if="data.has_missing_punch" severity="danger" icon="pi pi-exclamation-circle" class="attendance-compact-tag">Missed</Tag>
                                    <Tag v-if="data.ignored_punch_count" severity="secondary" class="attendance-compact-tag">{{ data.ignored_punch_count }} diabaikan</Tag>
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
                                    <Tag v-if="data.late_minutes" severity="warn" class="attendance-compact-tag">
                                        Terlambat {{ data.late_minutes }}m
                                        <template v-if="data.late_penalty_hours"> · potong {{ data.late_penalty_hours }} jam</template>
                                    </Tag>
                                    <Tag v-if="data.early_leave_minutes" severity="danger" class="attendance-compact-tag">
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
                        <Column field="night_shift_allowance" header="Shift Malam" sortable style="min-width: 115px">
                            <template #body="{ data }">
                                <span v-if="data.night_shift_allowance" class="font-semibold text-indigo-700 dark:text-indigo-300">
                                    +{{ data.night_shift_allowance }} jam
                                </span>
                                <span v-else class="text-xs text-slate-400">â€”</span>
                            </template>
                        </Column>
                        <Column field="holiday_worked_hours" header="Kerja Libur x2" sortable style="min-width: 125px">
                            <template #body="{ data }">{{ data.holiday_worked_hours }} jam</template>
                        </Column>
                        <Column field="holiday_overtime_hours" header="Lembur Libur x2" sortable style="min-width: 135px">
                            <template #body="{ data }">{{ data.holiday_overtime_hours }} jam</template>
                        </Column>
                        <Column
                            field="notes"
                            sortable
                            :style="{ minWidth: notesVisible ? '260px' : '110px' }"
                        >
                            <template #header>
                                <div class="flex items-center justify-center gap-1">
                                    <span>Catatan</span>
                                    <Button
                                        :icon="notesVisible ? 'pi pi-eye-slash' : 'pi pi-eye'"
                                        text
                                        rounded
                                        size="small"
                                        severity="secondary"
                                        class="!h-6 !w-6 !min-h-6 !p-0"
                                        :aria-label="notesVisible ? 'Sembunyikan catatan' : 'Tampilkan catatan'"
                                        :title="notesVisible ? 'Sembunyikan catatan' : 'Tampilkan catatan'"
                                        @click.stop="toggleNotes"
                                    />
                                </div>
                            </template>
                            <template #body="{ data }">
                                <p v-if="notesVisible" class="line-clamp-2 whitespace-normal break-words text-xs leading-4 text-slate-600 dark:text-slate-300" :title="data.comparison_notes || data.notes || '-'">
                                    {{ data.comparison_notes || data.notes || "-" }}
                                </p>
                                <span v-else class="block text-center text-xs text-slate-400">—</span>
                            </template>
                        </Column>
                        <Column
                            header="Aksi"
                            frozen
                            align-frozen="right"
                            style="min-width: 140px"
                        >
                            <template #body="{ data }">
                                <div
                                    v-if="!isInlineEditing(data)"
                                    class="flex flex-wrap items-center justify-end gap-1 rounded-lg bg-white px-1 py-1 dark:bg-slate-900"
                                >
                                    <Button
                                        v-if="data.requires_review"
                                        icon="pi pi-check"
                                        size="small"
                                        text
                                        rounded
                                        severity="success"
                                        aria-label="Done review"
                                        title="Done review"
                                        @click="resolveReview(data)"
                                    />
                                    <Button
                                        v-if="data.requires_review || data.review_resolved_at"
                                        icon="pi pi-times"
                                        size="small"
                                        text
                                        rounded
                                        severity="danger"
                                        aria-label="Hapus review"
                                        title="Hapus review"
                                        @click="deleteReview(data)"
                                    />
                                    <Button
                                        v-if="data.has_attendance"
                                        icon="pi pi-trash"
                                        text
                                        rounded
                                        severity="danger"
                                        aria-label="Hapus absensi"
                                        @click="removeRecord(data)"
                                    />
                                </div>
                                <p v-else class="text-right text-xs font-medium text-slate-400">Selesaikan edit untuk melihat aksi</p>
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>

        <Dialog v-model:visible="dialogVisible" :header="editingRecord ? 'Edit absensi' : 'Tambah absensi'" modal class="attendance-responsive-dialog w-full max-w-xl">
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
                <div class="grid grid-cols-2 gap-2 pt-2 sm:flex sm:justify-end">
                    <Button type="button" label="Batal" severity="secondary" text class="w-full sm:w-auto" @click="dialogVisible = false" />
                    <Button type="submit" label="Simpan absensi" icon="pi pi-check" severity="danger" class="w-full sm:w-auto" :loading="form.processing" />
                </div>
            </form>
        </Dialog>

        <Dialog
            v-model:visible="guideDialogVisible"
            header="Pedoman jam kerja & jadwal khusus"
            modal
            :style="{ width: 'min(96vw, 1280px)' }"
            :breakpoints="{ '960px': '96vw' }"
            class="attendance-responsive-dialog"
        >
            <div class="max-h-[calc(100dvh-8rem)] space-y-5 overflow-y-auto pr-1 sm:max-h-[74vh]">
                <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-950/40 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">
                            Pedoman lengkap jadwal reguler dan periode khusus
                        </p>
                        <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500 dark:text-slate-400">
                            File Excel memuat ketentuan lengkap pada tab Ketentuan. Perubahan jadwal Ramadan dilakukan
                            developer pada bagian Jadwal Perubahan / Ramadan di tab Jadwal Kerja.
                        </p>
                    </div>
                    <Button
                        label="Download Pedoman Jadwal Kerja Excel"
                        icon="pi pi-download"
                        severity="secondary"
                        outlined
                        class="w-full sm:w-auto"
                        :disabled="!scheduleGuide.downloadAvailable"
                        @click="downloadScheduleGuide"
                    />
                </div>

                <Message v-if="!scheduleGuide.downloadAvailable" severity="warn" :closable="false">
                    File pedoman belum tersedia di server.
                </Message>

                <SelectButton
                    v-model="guideSection"
                    :options="guideSections"
                    option-label="label"
                    option-value="value"
                    :allow-empty="false"
                    class="w-full"
                />

                <template v-if="guideSection === 'reguler'">
                    <div class="space-y-4">
                        <section
                            v-for="profile in scheduleGuide.profiles"
                            :key="profile.key"
                            class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800"
                        >
                            <div class="flex flex-col gap-2 bg-slate-900 px-4 py-3 text-white sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-sm font-bold">{{ profile.label }}</h3>
                                    <p v-if="profile.notice" class="mt-1 text-xs leading-5 text-slate-300">{{ profile.notice }}</p>
                                </div>
                                <Tag v-if="profile.review_required" severity="warn">Perlu review</Tag>
                                <Tag v-else severity="success">Jadwal tetap</Tag>
                            </div>

                            <div class="space-y-3 bg-slate-50/70 p-3 dark:bg-slate-950/30">
                                <div
                                    v-for="day in profile.days"
                                    :key="`${profile.key}-${day.key}`"
                                    class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
                                >
                                    <div class="border-b border-slate-200 bg-slate-100/80 px-4 py-2 dark:border-slate-800 dark:bg-slate-800/60">
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ day.label }}</p>
                                    </div>
                                    <div class="divide-y divide-slate-200 dark:divide-slate-800">
                                        <div
                                            v-for="shift in day.shifts"
                                            :key="`${profile.key}-${day.key}-${shift.key}`"
                                            class="grid grid-cols-2 gap-3 px-4 py-3 text-sm md:grid-cols-[120px_170px_minmax(0,1fr)_100px_80px] md:items-center"
                                        >
                                            <div>
                                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Shift</p>
                                                <p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ shift.label }}</p>
                                            </div>
                                            <div>
                                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Masuk - pulang</p>
                                                <p class="mt-1 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ shift.time }}</p>
                                            </div>
                                            <div>
                                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Istirahat</p>
                                                <p class="mt-1 leading-5 text-slate-700 dark:text-slate-200">{{ shift.break }}</p>
                                            </div>
                                            <div>
                                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Jam reguler</p>
                                                <p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ shift.standard_hours }} jam</p>
                                            </div>
                                            <div>
                                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Punch</p>
                                                <p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ shift.punch_count }}x</p>
                                            </div>
                                            <p class="col-span-2 rounded-lg bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-500 dark:bg-slate-950/50 dark:text-slate-400 md:col-span-5">
                                                {{ shift.note }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </template>

                <template v-else-if="guideSection === 'ketentuan'">
                    <div class="space-y-5">
                        <section
                            v-for="group in scheduleGuide.requirements"
                            :key="group.title"
                            class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
                        >
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ group.title }}</h3>
                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <div
                                    v-for="item in group.items"
                                    :key="item.title"
                                    class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-950/40"
                                >
                                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ item.title }}</p>
                                    <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ item.value }}</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-slate-300">{{ item.description }}</p>
                                </div>
                            </div>
                        </section>
                    </div>
                </template>

                <template v-else>
                    <div>
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Jadwal khusus tersimpan</h3>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ specialWorkSchedules.length.toLocaleString("id-ID") }} jadwal tercatat.
                                </p>
                            </div>
                        </div>

                        <DataTable
                            v-if="specialWorkSchedules.length"
                            :value="specialWorkSchedules"
                            paginator
                            :rows="6"
                            :rows-per-page-options="[6, 10, 25]"
                            scrollable
                            class="mt-3"
                        >
                            <Column field="name" header="Periode" style="min-width: 170px">
                                <template #body="{ data }">
                                    <p class="font-semibold text-slate-900 dark:text-white">{{ data.name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ formatDate(data.starts_on) }} - {{ formatDate(data.ends_on) }}</p>
                                </template>
                            </Column>
                            <Column field="scope" header="Scope" style="min-width: 145px" />
                            <Column field="profile" header="Profile" style="min-width: 130px" />
                            <Column field="day_type" header="Hari" style="min-width: 115px" />
                            <Column field="shift" header="Shift" style="min-width: 105px" />
                            <Column header="Jam kerja" style="min-width: 155px">
                                <template #body="{ data }">
                                    <span class="font-mono">{{ data.check_in }} - {{ data.check_out }}{{ data.check_out_day_offset ? " +1d" : "" }}</span>
                                </template>
                            </Column>
                            <Column header="Reguler" style="min-width: 90px">
                                <template #body="{ data }">{{ data.standard_work_hours }} jam</template>
                            </Column>
                            <Column field="expected_punch_count" header="Punch" style="min-width: 80px" />
                            <Column field="notes" header="Catatan" style="min-width: 220px">
                                <template #body="{ data }">{{ data.notes || "-" }}</template>
                            </Column>
                        </DataTable>
                        <EmptyState
                            v-else
                            title="Belum ada jadwal khusus"
                            description="Jadwal Ramadan dan periode khusus lain akan tampil di sini setelah ditambahkan dari Developer Settings."
                            icon="pi pi-clock"
                            class="mt-3"
                        />
                    </div>
                </template>
            </div>
        </Dialog>

        <Dialog
            v-model:visible="importDialogVisible"
            header="Import Attendance"
            modal
            :style="{ width: 'min(94vw, 980px)' }"
            :breakpoints="{ '960px': '94vw' }"
            class="attendance-responsive-dialog"
        >
            <div class="space-y-5">
                <SelectButton
                    v-model="importSection"
                    :options="importSections"
                    option-label="label"
                    option-value="value"
                    :allow-empty="false"
                    class="w-full"
                />

                <template v-if="importSection === 'rekap'">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-950/40">
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Import rekap absensi</h3>
                            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                Upload Attendance Logs satu hari. NIP dicocokkan ke data karyawan dan ID tanpa punch dilewati.
                            </p>
                        </div>
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
                                    class="block w-full cursor-pointer rounded-xl border border-dashed border-slate-300 bg-white px-4 py-5 text-sm text-slate-600 file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-red-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:border-red-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                    @change="chooseUploadFile"
                                />
                                <p id="attendance-file-guidance" class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                    Maksimal 5 MB. Punch berulang dalam waktu berdekatan hanya disimpan sekali.
                                </p>
                                <p v-if="uploadForm.errors.attendance_file" class="mt-2 text-xs font-medium text-red-600 dark:text-red-400">
                                    {{ uploadForm.errors.attendance_file }}
                                </p>
                            </div>
                            <ProgressBar v-if="uploadForm.progress" :value="uploadProgress" :show-value="false" class="h-2" />
                            <div class="flex justify-end">
                                <Button
                                    type="submit"
                                    label="Import dan bandingkan"
                                    icon="pi pi-upload"
                                    severity="danger"
                                    class="w-full sm:w-auto"
                                    :loading="uploadForm.processing"
                                    :disabled="!uploadForm.attendance_file"
                                />
                            </div>
                        </form>
                    </div>
                </template>

                <template v-else-if="importSection === 'database'">
                    <div class="space-y-4">
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-950/40">
                            <div class="mb-4">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Import dari database source</h3>
                                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                    Data diambil dari tabel <span class="font-mono">yfrekappresensis</span> pada database
                                    yang dikonfigurasi. Jam kerja, hari kerja, lembur, kerja libur, overtime libur,
                                    late, shift, dan punch disalin langsung tanpa dihitung ulang.
                                    Pilih satu tanggal atau seluruh bulan. Review dihitung dari No Scan,
                                    punch masuk/keluar yang kosong, dan total jam kerja negatif.
                                </p>
                            </div>
                            <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="loadDatabasePreview(true)">
                                <FormField label="Mode import" class="w-full sm:max-w-[230px]">
                                    <SelectButton
                                        v-model="databasePreviewFilters.attendance_mode"
                                        :options="databaseImportModes"
                                        option-label="label"
                                        option-value="value"
                                        :allow-empty="false"
                                        class="w-full"
                                    />
                                </FormField>
                                <FormField
                                    v-if="databasePreviewFilters.attendance_mode === 'date'"
                                    label="Tanggal attendance"
                                    class="w-full sm:max-w-xs"
                                    :error="databaseImportForm.errors.attendance_date"
                                >
                                    <InputText v-model="databasePreviewFilters.attendance_date" type="date" class="w-full" />
                                </FormField>
                                <FormField
                                    v-else
                                    label="Bulan attendance"
                                    class="w-full sm:max-w-xs"
                                    :error="databaseImportForm.errors.attendance_month"
                                >
                                    <InputText v-model="databasePreviewFilters.attendance_month" type="month" class="w-full" />
                                </FormField>
                                <Button
                                    type="submit"
                                    label="Preview data"
                                    icon="pi pi-search"
                                    class="w-full sm:w-auto"
                                    :loading="databasePreviewLoading"
                                    :disabled="databaseImportRunning"
                                />
                            </form>
                        </div>

                        <Message v-if="databasePreviewError" severity="error" :closable="false">
                            {{ databasePreviewError }}
                        </Message>

                        <div v-if="databaseImportRunning" class="space-y-2 rounded-xl border border-sky-200 bg-sky-50/60 p-3 dark:border-sky-500/30 dark:bg-sky-500/10">
                            <div class="flex items-center justify-between gap-3 text-xs text-sky-800 dark:text-sky-200">
                                <span>Mengimpor {{ formatDate(databaseImportProgress.date) }}</span>
                                <span>{{ databaseImportProgress.current }} / {{ databaseImportProgress.total }}</span>
                            </div>
                            <ProgressBar
                                :value="databaseImportProgress.total
                                    ? (databaseImportProgress.current / databaseImportProgress.total) * 100
                                    : 0"
                                :show-value="false"
                                class="h-2"
                            />
                            <p class="text-xs text-sky-700 dark:text-sky-200">
                                {{ databaseImportProgress.processed.toLocaleString("id-ID") }} attendance,
                                {{ databaseImportProgress.review.toLocaleString("id-ID") }} perlu review.
                            </p>
                        </div>

                        <Message v-if="databaseImportResult" severity="success" :closable="false">
                            Import {{ databaseImportResult.dates }} hari selesai:
                            {{ databaseImportResult.processed.toLocaleString("id-ID") }} attendance,
                            {{ databaseImportResult.review.toLocaleString("id-ID") }} perlu review.
                        </Message>

                        <template v-if="databasePreview">
                            <div class="rounded-xl border border-sky-200 bg-sky-50/60 p-3 text-xs text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200">
                                Source: <span class="font-mono">{{ databasePreview.source.database }}.{{ databasePreview.source.table }}</span>
                                · Periode {{ databasePreview.period.label }}
                            </div>

                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-7">
                                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                    <p class="text-xs text-slate-500">Record source</p>
                                    <p class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ databasePreview.summary.total.toLocaleString("id-ID") }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                    <p class="text-xs text-slate-500">NIP cocok</p>
                                    <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-300">{{ databasePreview.summary.matched.toLocaleString("id-ID") }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                    <p class="text-xs text-slate-500">NIP tidak cocok</p>
                                    <p class="mt-1 text-xl font-bold text-red-700 dark:text-red-300">{{ databasePreview.summary.unmatched.toLocaleString("id-ID") }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                    <p class="text-xs text-slate-500">Record baru</p>
                                    <p class="mt-1 text-xl font-bold text-sky-700 dark:text-sky-300">{{ databasePreview.summary.create.toLocaleString("id-ID") }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                    <p class="text-xs text-slate-500">Diperbarui</p>
                                    <p class="mt-1 text-xl font-bold text-amber-700 dark:text-amber-300">{{ databasePreview.summary.existing.toLocaleString("id-ID") }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                    <p class="text-xs text-slate-500">No Scan</p>
                                    <p class="mt-1 text-xl font-bold text-red-700 dark:text-red-300">{{ databasePreview.summary.no_scan.toLocaleString("id-ID") }}</p>
                                </div>
                                <div class="rounded-xl border border-red-200 bg-red-50/60 p-3 dark:border-red-500/30 dark:bg-red-500/10">
                                    <p class="text-xs text-red-700 dark:text-red-200">Perlu review</p>
                                    <p class="mt-1 text-xl font-bold text-red-700 dark:text-red-200">{{ databasePreview.summary.review_required.toLocaleString("id-ID") }}</p>
                                </div>
                            </div>

                            <Message v-if="databasePreview.summary.unmatched" severity="warn" :closable="false">
                                {{ databasePreview.summary.unmatched }} record source tidak memiliki NIP yang cocok dan akan dilewati.
                            </Message>

                            <DataTable
                                :value="databasePreview.data"
                                :loading="databasePreviewLoading"
                                data-key="source_id"
                                paginator
                                lazy
                                :first="(databasePreview.pagination.current_page - 1) * databasePreview.pagination.per_page"
                                :rows="databasePreview.pagination.per_page"
                                :rows-per-page-options="[15, 30, 50, 100]"
                                :total-records="databasePreview.pagination.total"
                                scrollable
                                scroll-height="480px"
                                @page="onDatabasePreviewPage"
                            >
                                <template #empty>
                                    <EmptyState title="Tidak ada data" description="Tidak ada attendance pada periode tersebut di database source." icon="pi pi-database" />
                                </template>
                                <Column header="Karyawan" style="min-width: 210px">
                                    <template #body="{ data }">
                                        <p class="font-semibold text-slate-900 dark:text-white">{{ data.name }}</p>
                                        <p class="mt-1 font-mono text-xs text-slate-500">{{ data.nip }}</p>
                                    </template>
                                </Column>
                                <Column header="Tanggal" style="min-width: 105px">
                                    <template #body="{ data }">{{ formatDate(data.attendance_date) }}</template>
                                </Column>
                                <Column header="Shift source" style="min-width: 105px">
                                    <template #body="{ data }">{{ data.source_shift || "-" }}</template>
                                </Column>
                                <Column header="Punch source" style="min-width: 330px">
                                    <template #body="{ data }">
                                        <p class="font-mono text-xs text-slate-700 dark:text-slate-200">{{ databasePunchSummary(data) }}</p>
                                    </template>
                                </Column>
                                <Column header="Total source" style="min-width: 150px">
                                    <template #body="{ data }">
                                        <p class="text-xs text-slate-600 dark:text-slate-300">
                                            Kerja {{ data.source_worked_hours ?? "-" }} jam
                                        </p>
                                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                                            Lembur {{ data.source_overtime_hours ?? "-" }} jam
                                        </p>
                                    </template>
                                </Column>
                                <Column header="Flag source" style="min-width: 135px">
                                    <template #body="{ data }">
                                        <div class="flex flex-wrap gap-1">
                                            <Tag v-if="data.source_no_scan" severity="danger">No Scan</Tag>
                                            <Tag v-else-if="data.source_late" severity="warn">Terlambat</Tag>
                                            <Tag v-else severity="success">Normal</Tag>
                                        </div>
                                    </template>
                                </Column>
                                <Column header="Target" style="min-width: 90px">
                                    <template #body="{ data }">
                                        <Tag :severity="databaseTargetSeverity(data.target_state)">
                                            {{ databaseTargetLabel(data.target_state) }}
                                        </Tag>
                                    </template>
                                </Column>
                            </DataTable>

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    Halaman {{ databasePreview.pagination.current_page.toLocaleString("id-ID") }}
                                    dari {{ databasePreview.pagination.last_page.toLocaleString("id-ID") }}.
                                </p>
                                <Button
                                    :label="databasePreview.period.mode === 'month' ? 'Import satu bulan' : 'Import tanggal ini'"
                                    icon="pi pi-database"
                                    severity="success"
                                    class="w-full sm:w-auto"
                                    :loading="databaseImportForm.processing || databaseImportRunning"
                                    :disabled="databasePreview.summary.matched === 0 || databaseImportRunning"
                                    @click="importDatabaseAttendance"
                                />
                            </div>
                        </template>

                        <EmptyState
                            v-else-if="!databasePreviewLoading"
                            title="Pilih periode source"
                            description="Tentukan tanggal atau bulan attendance lalu klik Preview data."
                            icon="pi pi-database"
                        />
                    </div>
                </template>

                <template v-else>
                    <div v-if="latestImport" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.75fr)]">
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-950/40">
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
                                <div>
                                    <p class="text-xs text-slate-500">Perlu review</p>
                                    <p class="font-semibold text-red-700 dark:text-red-300">{{ latestImport.review_required_count.toLocaleString("id-ID") }}</p>
                                </div>
                            </div>
                            <div v-if="latestImport.unmatched_user_ids.length" class="mt-4 rounded-lg bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                                NIP tidak ditemukan:
                                <span class="font-mono">{{ latestImport.unmatched_user_ids.join(", ") }}</span>
                            </div>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Riwayat singkat</p>
                            <div v-if="recentImports.length > 1" class="mt-3 space-y-3">
                                <div v-for="item in recentImports.slice(1)" :key="item.id" class="flex items-center justify-between gap-3 text-xs">
                                    <span class="truncate text-slate-600 dark:text-slate-300">{{ formatDate(item.attendance_date) }} · {{ item.filename }}</span>
                                    <span class="shrink-0 text-slate-400">{{ item.processed_employee_count }} karyawan</span>
                                </div>
                            </div>
                            <p v-else class="mt-3 text-xs text-slate-400">Belum ada riwayat import lain.</p>
                        </div>
                    </div>
                    <EmptyState
                        v-else
                        title="Belum ada import"
                        description="Pilih Rekap Absensi untuk mengunggah file Attendance Logs."
                        icon="pi pi-file-excel"
                    />
                </template>
            </div>
        </Dialog>

        <Dialog
            v-model:visible="anomalyScanVisible"
            header="Scan anomali attendance"
            modal
            :style="{ width: 'min(96vw, 1180px)' }"
            :breakpoints="{ '960px': '96vw' }"
            class="attendance-responsive-dialog"
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
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end xl:col-span-4">
                            <Button
                                type="submit"
                                label="Scan sekarang"
                                icon="pi pi-search"
                                severity="warn"
                                class="w-full sm:w-auto"
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
