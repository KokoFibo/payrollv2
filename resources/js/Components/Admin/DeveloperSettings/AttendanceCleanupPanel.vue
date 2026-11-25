<script setup>
import { computed } from "vue";
import { useForm } from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";
import FormField from "@/Components/Admin/FormField.vue";

const props = defineProps({
    months: { type: Array, required: true },
    result: { type: Object, default: null },
});

const confirm = useConfirm();
const form = useForm({
    months: [],
});

const monthOptions = computed(() => props.months.map((month) => ({
    ...month,
    label: monthLabel(month.value),
})));
const selectedOptions = computed(() => (
    monthOptions.value.filter((month) => form.months.includes(month.value))
));
const selectedRecordCount = computed(() => selectedOptions.value.reduce(
    (total, month) => total + month.record_count,
    0,
));
const selectedImportCount = computed(() => selectedOptions.value.reduce(
    (total, month) => total + month.import_count,
    0,
));
const selectedFileBytes = computed(() => selectedOptions.value.reduce(
    (total, month) => total + month.file_bytes,
    0,
));
const formError = computed(() => (
    form.errors.months
    || Object.entries(form.errors).find(([key]) => key.startsWith("months."))?.[1]
    || ""
));
const selectionDescription = computed(() => {
    const labels = selectedOptions.value.map((month) => month.label);

    if (labels.length <= 5) {
        return labels.join(", ");
    }

    return `${labels.slice(0, 5).join(", ")} dan ${labels.length - 5} bulan lainnya`;
});

function cleanupAttendance() {
    if (form.months.length === 0) {
        return;
    }

    confirm.require({
        message: `Hapus permanen ${formatNumber(selectedRecordCount.value)} rekap presensi dan ${formatNumber(selectedImportCount.value)} riwayat import untuk ${selectionDescription.value}? Tindakan ini tidak dapat dibatalkan.`,
        header: "Bersihkan data presensi?",
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Batal",
        acceptLabel: "Hapus permanen",
        rejectClass: "p-button-secondary p-button-outlined",
        acceptClass: "p-button-danger",
        accept: () => {
            form.delete(route("admin.developer-settings.attendance-cleanup.destroy"), {
                preserveScroll: true,
                onSuccess: () => form.reset(),
            });
        },
    });
}

function monthLabel(value) {
    const [year, month] = value.split("-");
    const date = new Date(Date.UTC(Number(year), Number(month) - 1, 1));

    return new Intl.DateTimeFormat("id-ID", {
        month: "long",
        year: "numeric",
        timeZone: "UTC",
    }).format(date);
}

function formatNumber(value) {
    return Number(value ?? 0).toLocaleString("id-ID");
}

function formatBytes(bytes) {
    const size = Number(bytes ?? 0);

    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toLocaleString("id-ID", { maximumFractionDigits: 1 })} KB`;
    }

    return `${(size / (1024 * 1024)).toLocaleString("id-ID", { maximumFractionDigits: 1 })} MB`;
}
</script>

<template>
    <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
        <template #title>
            <div class="flex flex-col gap-3 px-1 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-950 dark:text-white">Bersihkan data presensi</h2>
                    <p class="mt-1 max-w-3xl text-xs font-normal leading-5 text-slate-500 dark:text-slate-400">
                        Hapus rekap presensi dan riwayat import berdasarkan bulan. Pilih Agustus, September, atau periode lama lain yang sudah tidak diperlukan.
                    </p>
                </div>
                <Tag severity="danger">Permanent</Tag>
            </div>
        </template>
        <template #content>
            <Message severity="warn" :closable="false" class="mb-5">
                Rekap presensi dan metadata import akan dihapus permanen. Payroll periode tidak ikut dihapus.
            </Message>

            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <FormField label="Bulan presensi" :error="formError" required>
                    <MultiSelect
                        v-model="form.months"
                        :options="monthOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Pilih satu atau beberapa bulan"
                        filter
                        display="chip"
                        :max-selected-labels="3"
                        selected-items-label="{0} bulan dipilih"
                        :show-toggle-all="true"
                        class="w-full"
                    />
                </FormField>
                <Button
                    label="Hapus data terpilih"
                    icon="pi pi-trash"
                    severity="danger"
                    :loading="form.processing"
                    :disabled="form.months.length === 0"
                    @click="cleanupAttendance"
                />
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/50">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Bulan dipilih</p>
                    <p class="mt-1 text-xl font-extrabold text-slate-950 dark:text-white">{{ formatNumber(form.months.length) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/50">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Rekap presensi</p>
                    <p class="mt-1 text-xl font-extrabold text-slate-950 dark:text-white">{{ formatNumber(selectedRecordCount) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/50">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Riwayat import</p>
                    <p class="mt-1 text-xl font-extrabold text-slate-950 dark:text-white">{{ formatNumber(selectedImportCount) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/50">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Ukuran file</p>
                    <p class="mt-1 text-xl font-extrabold text-slate-950 dark:text-white">{{ formatBytes(selectedFileBytes) }}</p>
                </div>
            </div>

            <div v-if="props.result" class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="font-bold text-emerald-900 dark:text-emerald-100">Pembersihan terakhir berhasil</p>
                        <p class="mt-1 text-xs leading-5 text-emerald-800 dark:text-emerald-200">
                            {{ props.result.months.map(monthLabel).join(", ") }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs font-semibold text-emerald-800 dark:text-emerald-200">
                        <Tag severity="success">{{ formatNumber(props.result.deleted_records) }} rekap</Tag>
                        <Tag severity="success">{{ formatNumber(props.result.deleted_imports) }} import</Tag>
                        <Tag severity="success">{{ formatBytes(props.result.file_bytes) }}</Tag>
                    </div>
                </div>
            </div>

            <DataTable
                :value="monthOptions"
                paginator
                :rows="10"
                :rows-per-page-options="[10, 25, 50]"
                scrollable
                class="mt-5"
            >
                <Column field="label" header="Bulan" style="min-width: 160px" />
                <Column field="record_count" header="Rekap" style="min-width: 100px">
                    <template #body="{ data }">{{ formatNumber(data.record_count) }}</template>
                </Column>
                <Column field="import_count" header="Riwayat import" style="min-width: 130px">
                    <template #body="{ data }">{{ formatNumber(data.import_count) }}</template>
                </Column>
                <Column field="file_bytes" header="Ukuran file" style="min-width: 120px">
                    <template #body="{ data }">{{ formatBytes(data.file_bytes) }}</template>
                </Column>
                <Column header="Status" style="min-width: 120px">
                    <template #body="{ data }">
                        <Tag :severity="data.record_count + data.import_count > 0 ? 'warn' : 'secondary'">
                            {{ data.record_count + data.import_count > 0 ? "Ada data" : "Kosong" }}
                        </Tag>
                    </template>
                </Column>
            </DataTable>
        </template>
    </Card>
</template>
