<script setup>
import { computed, nextTick, reactive, ref } from "vue";
import { useForm } from "@inertiajs/vue3";
import EmptyState from "@/Components/Admin/EmptyState.vue";
import FormField from "@/Components/Admin/FormField.vue";

const props = defineProps({
    options: { type: Object, required: true },
});

const slotDefinitions = [
    { field: "first_in", label: "Masuk 1" },
    { field: "first_out", label: "Keluar 1" },
    { field: "second_in", label: "Masuk 2" },
    { field: "second_out", label: "Keluar 2" },
    { field: "overtime_in", label: "Masuk OT" },
    { field: "overtime_out", label: "Keluar OT" },
];
const currentDate = localDateValue(new Date());
const filters = reactive({
    start_date: currentDate,
    end_date: currentDate,
    company_id: null,
    directorate_id: null,
    department_id: null,
    jabatan_id: null,
    shift: "",
    status: "",
    schedule_profile: "",
    condition: "all",
    search: "",
    page: 1,
    per_page: 15,
});
const punchTimes = reactive(emptyTimes());
const previewData = ref(null);
const previewFilters = ref(null);
const selectedRows = ref([]);
const selectAllMatching = ref(false);
const syncingSelection = ref(false);
const loading = ref(false);
const error = ref("");
const fillEmptyOnly = ref(false);
const bulkForm = useForm({
    targets: [],
    times: emptyTimes(),
    fill_empty_only: false,
    select_all: false,
    start_date: "",
    end_date: "",
    company_id: null,
    directorate_id: null,
    department_id: null,
    jabatan_id: null,
    shift: "",
    status: "",
    schedule_profile: "",
    condition: "all",
    search: "",
});
const hasSubmittedTime = computed(() => Object.values(punchTimes).some(Boolean));
const selectedCount = computed(() => (
    selectAllMatching.value
        ? previewData.value?.summary.total ?? 0
        : selectedRows.value.length
));

function emptyTimes() {
    return Object.fromEntries(
        slotDefinitions.map((slot) => [slot.field, ""]),
    );
}

function localDateValue(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
}

function filterParams() {
    const params = new URLSearchParams();

    for (const [key, value] of Object.entries(filters)) {
        if (value === null || value === undefined || value === "") {
            continue;
        }

        params.set(key, value === true ? "1" : String(value));
    }

    return params;
}

function showPreview() {
    selectAllMatching.value = false;
    replaceSelection([]);
    previewFilters.value = null;
    filters.page = 1;
    loadPreview();
}

async function loadPreview() {
    const keepAllMatchingSelected = selectAllMatching.value;
    loading.value = true;
    error.value = "";

    if (!keepAllMatchingSelected) {
        replaceSelection([]);
    }

    try {
        const response = await fetch(
            `${route("admin.developer-settings.attendance-bulk.preview")}?${filterParams()}`,
            {
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            },
        );
        const payload = await response.json();

        if (!response.ok) {
            error.value = firstError(payload);

            return;
        }

        previewData.value = payload;
        previewFilters.value = { ...payload.filters };
        filters.page = payload.pagination.current_page;
        filters.per_page = payload.pagination.per_page;

        if (selectAllMatching.value) {
            replaceSelection(payload.data);
        }
    } catch {
        error.value = "Data attendance tidak dapat dimuat. Periksa koneksi lalu coba lagi.";
    } finally {
        loading.value = false;
    }
}

function onPreviewPage(event) {
    filters.page = event.page + 1;
    filters.per_page = event.rows;
    loadPreview();
}

function firstError(payload) {
    if (payload?.errors) {
        return Object.values(payload.errors).flat()[0] ?? payload.message;
    }

    return payload?.message ?? "Filter attendance tidak valid.";
}

function replaceSelection(rows) {
    syncingSelection.value = true;
    selectedRows.value = [...rows];
    nextTick(() => {
        syncingSelection.value = false;
    });
}

function onSelectionChange(rows) {
    selectedRows.value = rows;

    if (syncingSelection.value || loading.value) {
        return;
    }

    if (
        selectAllMatching.value
        && rows.length !== (previewData.value?.data.length ?? 0)
    ) {
        selectAllMatching.value = false;
    }
}

function selectCurrentPage() {
    selectAllMatching.value = false;
    replaceSelection(previewData.value?.data ?? []);
}

function selectAllFilteredRows() {
    if ((previewData.value?.summary.total ?? 0) === 0) {
        return;
    }

    selectAllMatching.value = true;
    replaceSelection(previewData.value?.data ?? []);
}

function clearSelection() {
    selectAllMatching.value = false;
    replaceSelection([]);
}

function applyBulkChanges() {
    bulkForm.clearErrors();

    if (!selectAllMatching.value && selectedRows.value.length === 0) {
        bulkForm.setError("targets", "Pilih minimal satu attendance yang akan dikoreksi.");

        return;
    }

    if (!hasSubmittedTime.value) {
        bulkForm.setError("times", "Isi minimal satu jam yang akan diterapkan.");

        return;
    }

    bulkForm.targets = selectAllMatching.value
        ? []
        : selectedRows.value.map((row) => ({
            karyawan_id: row.karyawan_id,
            attendance_date: row.attendance_date,
        }));
    bulkForm.times = { ...punchTimes };
    bulkForm.fill_empty_only = fillEmptyOnly.value;
    bulkForm.select_all = selectAllMatching.value;
    const selectedFilters = previewFilters.value ?? filters;
    bulkForm.start_date = selectedFilters.start_date;
    bulkForm.end_date = selectedFilters.end_date;
    bulkForm.company_id = selectedFilters.company_id;
    bulkForm.directorate_id = selectedFilters.directorate_id;
    bulkForm.department_id = selectedFilters.department_id;
    bulkForm.jabatan_id = selectedFilters.jabatan_id;
    bulkForm.shift = selectedFilters.shift;
    bulkForm.status = selectedFilters.status;
    bulkForm.schedule_profile = selectedFilters.schedule_profile;
    bulkForm.condition = selectedFilters.condition;
    bulkForm.search = selectedFilters.search;
    bulkForm.put(route("admin.developer-settings.attendance-bulk.update"), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            selectAllMatching.value = false;
            replaceSelection([]);
            Object.assign(punchTimes, emptyTimes());
            fillEmptyOnly.value = false;
            loadPreview();
        },
    });
}

function formatDate(value) {
    if (!value) {
        return "-";
    }

    const [year, month, day] = value.split("-");

    return `${day}/${month}/${year}`;
}

function timeLabel(row, slot) {
    const value = row[slot.field];

    if (!value) {
        return "—";
    }

    const offset = row[`${slot.field}_day_offset`] || 0;

    return offset > 0 ? `${value} +${offset}d` : value;
}
</script>

<template>
    <div class="space-y-4">
        <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
            <template #title>
                <div class="px-1">
                    <h2 class="text-base font-bold text-slate-950 dark:text-white">Koreksi attendance secara bulk</h2>
                    <p class="mt-1 max-w-4xl text-xs font-normal leading-5 text-slate-500 dark:text-slate-400">
                        Tampilkan hanya attendance yang sudah tersimpan pada periode filter, pilih data yang akan
                        diperbaiki, lalu sistem menghitung ulang status, jam kerja, lembur, dan review.
                    </p>
                </div>
            </template>
            <template #content>
                <form class="space-y-4" @submit.prevent="showPreview">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <FormField label="Tanggal mulai" required>
                            <InputText v-model="filters.start_date" type="date" class="w-full" />
                        </FormField>
                        <FormField label="Tanggal selesai" required>
                            <InputText v-model="filters.end_date" type="date" class="w-full" />
                        </FormField>
                        <FormField label="Company">
                            <Select
                                v-model="filters.company_id"
                                :options="options.companies"
                                option-label="label"
                                option-value="value"
                                placeholder="Semua company"
                                filter
                                show-clear
                                class="w-full"
                            />
                        </FormField>
                        <FormField label="Directorate">
                            <Select
                                v-model="filters.directorate_id"
                                :options="options.directorates"
                                option-label="label"
                                option-value="value"
                                placeholder="Semua directorate"
                                filter
                                show-clear
                                class="w-full"
                            />
                        </FormField>
                        <FormField label="Department">
                            <Select
                                v-model="filters.department_id"
                                :options="options.departments"
                                option-label="label"
                                option-value="value"
                                placeholder="Semua department"
                                filter
                                show-clear
                                class="w-full"
                            />
                        </FormField>
                        <FormField label="Jabatan">
                            <Select
                                v-model="filters.jabatan_id"
                                :options="options.jabatans"
                                option-label="label"
                                option-value="value"
                                placeholder="Semua jabatan"
                                filter
                                show-clear
                                class="w-full"
                            />
                        </FormField>
                        <FormField label="Shift">
                            <Select
                                v-model="filters.shift"
                                :options="options.shifts"
                                option-label="label"
                                option-value="value"
                                placeholder="Semua shift"
                                show-clear
                                class="w-full"
                            />
                        </FormField>
                        <FormField label="Status">
                            <Select
                                v-model="filters.status"
                                :options="options.statuses"
                                option-label="label"
                                option-value="value"
                                placeholder="Semua status"
                                show-clear
                                class="w-full"
                            />
                        </FormField>
                        <FormField label="Profile jadwal">
                            <Select
                                v-model="filters.schedule_profile"
                                :options="options.profiles"
                                option-label="label"
                                option-value="value"
                                placeholder="Semua profile"
                                show-clear
                                class="w-full"
                            />
                        </FormField>
                        <FormField label="Kondisi punch">
                            <Select
                                v-model="filters.condition"
                                :options="options.conditions"
                                option-label="label"
                                option-value="value"
                                class="w-full"
                            />
                        </FormField>
                        <FormField label="Cari NIP atau nama" class="md:col-span-2">
                            <InputText v-model="filters.search" placeholder="NIP atau nama karyawan" class="w-full" />
                        </FormField>
                    </div>

                    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-950/40 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs leading-5 text-slate-600 dark:text-slate-300">
                            Hanya record attendance yang sudah di-upload atau tersimpan pada periode ini yang ditampilkan.
                        </p>
                        <Button
                            type="submit"
                            label="Tampilkan data"
                            icon="pi pi-search"
                            :loading="loading"
                            class="w-full sm:w-auto"
                        />
                    </div>
                </form>

                <Message v-if="error" severity="error" :closable="false" class="mt-4">
                    {{ error }}
                </Message>
            </template>
        </Card>

        <Card v-if="previewData" class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
            <template #title>
                <div class="flex flex-col gap-3 px-1 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-950 dark:text-white">Preview dan pilihan record</h2>
                        <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">
                            {{ previewData.summary.total.toLocaleString("id-ID") }} attendance sesuai filter.
                            Halaman {{ previewData.pagination.current_page.toLocaleString("id-ID") }}
                            dari {{ previewData.pagination.last_page.toLocaleString("id-ID") }}.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            :label="`Pilih semua ${previewData.summary.total.toLocaleString('id-ID')}`"
                            icon="pi pi-check-square"
                            size="small"
                            severity="primary"
                            outlined
                            :disabled="previewData.summary.total === 0"
                            @click="selectAllFilteredRows"
                        />
                        <Button label="Pilih halaman ini" icon="pi pi-check" size="small" severity="secondary" outlined @click="selectCurrentPage" />
                        <Button label="Kosongkan pilihan" icon="pi pi-times" size="small" severity="secondary" text @click="clearSelection" />
                        <Tag severity="info">
                            {{ selectedCount.toLocaleString("id-ID") }} dipilih
                            <span v-if="selectAllMatching"> · semua hasil filter</span>
                        </Tag>
                    </div>
                </div>
            </template>
            <template #content>
                <DataTable
                    v-model:selection="selectedRows"
                    @update:selection="onSelectionChange"
                    :value="previewData.data"
                    :loading="loading"
                    data-key="key"
                    selection-mode="multiple"
                    paginator
                    lazy
                    :first="(previewData.pagination.current_page - 1) * previewData.pagination.per_page"
                    :rows="previewData.pagination.per_page"
                    :rows-per-page-options="[15, 30, 50, 100]"
                    :total-records="previewData.pagination.total"
                    scrollable
                    scroll-height="480px"
                    @page="onPreviewPage"
                >
                    <Column selection-mode="multiple" style="width: 3rem" />
                    <Column header="Karyawan" style="min-width: 210px">
                        <template #body="{ data }">
                            <p class="font-semibold text-slate-900 dark:text-white">{{ data.employee.name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ data.employee.nip }} · {{ data.employee.department || "-" }}</p>
                        </template>
                    </Column>
                    <Column header="Tanggal" style="min-width: 105px">
                        <template #body="{ data }">{{ formatDate(data.attendance_date) }}</template>
                    </Column>
                    <Column header="Attendance" style="min-width: 145px">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <Tag severity="info">{{ data.shift_label || "Tanpa shift" }}</Tag>
                                <Tag v-if="data.status_label" severity="secondary">{{ data.status_label }}</Tag>
                                <Tag v-if="data.has_missing_punch" severity="danger">Missed punch</Tag>
                                <Tag v-if="data.requires_review" severity="danger">Perlu review</Tag>
                                <Tag v-else-if="data.reviewed" severity="success">Reviewed</Tag>
                            </div>
                        </template>
                    </Column>
                    <Column header="Punch saat ini" style="min-width: 390px">
                        <template #body="{ data }">
                            <div class="grid grid-cols-3 gap-2 text-xs">
                                <div v-for="slot in slotDefinitions" :key="`${data.key}-${slot.field}`">
                                    <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">{{ slot.label }}</p>
                                    <p class="mt-0.5 font-mono font-semibold text-slate-700 dark:text-slate-200">{{ timeLabel(data, slot) }}</p>
                                </div>
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

        <Card v-else class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
            <template #content>
                <EmptyState
                    title="Belum ada data untuk dikoreksi"
                    description="Atur tanggal dan filter attendance, lalu klik Tampilkan data."
                    icon="pi pi-table"
                />
            </template>
        </Card>

        <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
            <template #title>
                <div class="px-1">
                    <h2 class="text-base font-bold text-slate-950 dark:text-white">Terapkan jam secara bulk</h2>
                    <p class="mt-1 text-xs font-normal leading-5 text-slate-500 dark:text-slate-400">
                        Kosongkan jam yang tidak ingin diubah. Attendance yang dipilih atau seluruh hasil filter
                        akan dihitung ulang setelah disimpan.
                    </p>
                </div>
            </template>
            <template #content>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <FormField
                        v-for="slot in slotDefinitions"
                        :key="`bulk-${slot.field}`"
                        :label="slot.label"
                        :error="bulkForm.errors[`times.${slot.field}`]"
                    >
                        <InputText v-model="punchTimes[slot.field]" type="time" class="w-full" />
                    </FormField>
                </div>

                <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3 text-xs leading-5 text-slate-600 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-300">
                    <Checkbox v-model="fillEmptyOnly" binary />
                    <span>
                        <span class="block font-semibold text-slate-800 dark:text-slate-100">Hanya isi punch yang masih kosong</span>
                        Jam yang sudah memiliki nilai tidak akan ditimpa. Matikan opsi ini untuk mengganti jam lama secara bulk.
                    </span>
                </label>

                <p v-if="bulkForm.errors.targets" class="mt-3 text-xs font-medium text-red-600 dark:text-red-400">
                    {{ bulkForm.errors.targets }}
                </p>
                <p v-if="bulkForm.errors.times" class="mt-3 text-xs font-medium text-red-600 dark:text-red-400">
                    {{ bulkForm.errors.times }}
                </p>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ selectedCount }} record dipilih. Semua data terpilih akan dihitung ulang.
                    </p>
                    <Button
                        label="Simpan dan hitung ulang"
                        icon="pi pi-refresh"
                        severity="danger"
                        :loading="bulkForm.processing"
                        :disabled="!selectedCount || !hasSubmittedTime"
                        @click="applyBulkChanges"
                    />
                </div>
            </template>
        </Card>
    </div>
</template>
