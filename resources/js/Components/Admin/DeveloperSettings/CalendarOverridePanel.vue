<script setup>
import { computed, ref } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";
import FormField from "@/Components/Admin/FormField.vue";
import EmptyState from "@/Components/Admin/EmptyState.vue";

const props = defineProps({
    overrides: { type: Array, required: true },
    options: { type: Object, required: true },
});

const confirm = useConfirm();
const dialogVisible = ref(false);
const editingOverride = ref(null);
const form = useForm({
    company_id: null,
    company_ids: [],
    calendar_date: "",
    name: "",
    is_holiday_pay: true,
    schedule_day_type: null,
    notes: "",
});
const selectedCompanyCount = computed(() => form.company_ids?.length ?? 0);
const allCompaniesSelected = computed(
    () => props.options.companies.length > 0
        && selectedCompanyCount.value === props.options.companies.length,
);
const companySelectionError = computed(() => (
    form.errors.company_ids
    || Object.entries(form.errors).find(([key]) => key.startsWith("company_ids."))?.[1]
    || ""
));

function openCreate() {
    editingOverride.value = null;
    form.reset();
    form.clearErrors();
    form.is_holiday_pay = true;
    form.company_ids = [];
    dialogVisible.value = true;
}

function openEdit(override) {
    editingOverride.value = override;
    form.clearErrors();
    form.company_id = override.company_id;
    form.company_ids = [override.company_id];
    form.calendar_date = override.calendar_date;
    form.name = override.name ?? "";
    form.is_holiday_pay = override.is_holiday_pay;
    form.schedule_day_type = override.schedule_day_type;
    form.notes = override.notes ?? "";
    dialogVisible.value = true;
}

function selectAllCompanies() {
    form.company_ids = props.options.companies.map((company) => company.value);
}

function clearCompanies() {
    form.company_ids = [];
}

function save() {
    form.transform((data) => {
        const common = {
            calendar_date: data.calendar_date,
            name: data.name || null,
            is_holiday_pay: data.is_holiday_pay,
            schedule_day_type: data.schedule_day_type || null,
            notes: data.notes || null,
        };

        return editingOverride.value
            ? { ...common, company_id: data.company_id }
            : { ...common, company_ids: data.company_ids };
    });

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogVisible.value = false;
        },
    };

    if (editingOverride.value) {
        form.put(
            route("admin.developer-settings.calendar-overrides.update", editingOverride.value.id),
            options,
        );

        return;
    }

    form.post(route("admin.developer-settings.calendar-overrides.store"), options);
}

function removeOverride(override) {
    confirm.require({
        message: `Hapus pengecualian ${override.company_name || "company"} tanggal ${formatDate(override.calendar_date)}?`,
        header: "Hapus pengecualian kalender?",
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Batal",
        acceptLabel: "Hapus",
        rejectClass: "p-button-secondary p-button-outlined",
        acceptClass: "p-button-danger",
        accept: () => router.delete(
            route("admin.developer-settings.calendar-overrides.destroy", override.id),
            { preserveScroll: true },
        ),
    });
}

function formatDate(value) {
    if (!value) {
        return "-";
    }

    const [year, month, day] = value.split("-");

    return `${day}/${month}/${year}`;
}
</script>

<template>
    <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
        <template #title>
            <div class="flex flex-col gap-3 px-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-950 dark:text-white">Pengecualian kalender perusahaan</h2>
                    <p class="mt-1 max-w-3xl text-xs font-normal leading-5 text-slate-500 dark:text-slate-400">
                        Libur nasional tetap global. Di sini company dapat menjadikan tanggal tertentu sebagai hari kerja,
                        atau menetapkan libur perusahaan sendiri. Scope directorate dan department sudah didukung oleh perhitungan database.
                    </p>
                </div>
                <Button label="Tambah pengecualian" icon="pi pi-plus" @click="openCreate" />
            </div>
        </template>
        <template #content>
            <DataTable :value="props.overrides" paginator :rows="10" :rows-per-page-options="[10, 25, 50]" scrollable>
                <template #empty>
                    <EmptyState title="Belum ada pengecualian kalender" description="Tambahkan aturan untuk company yang tanggal liburnya berbeda." icon="pi pi-calendar" />
                </template>
                <Column header="Tanggal" style="min-width: 120px">
                    <template #body="{ data }">{{ formatDate(data.calendar_date) }}</template>
                </Column>
                <Column field="scope_label" header="Scope" style="min-width: 190px" />
                <Column field="name" header="Nama" style="min-width: 190px">
                    <template #body="{ data }">{{ data.name || "-" }}</template>
                </Column>
                <Column header="Perlakuan" style="min-width: 150px">
                    <template #body="{ data }">
                        <Tag :severity="data.is_holiday_pay ? 'danger' : 'success'">
                            {{ data.is_holiday_pay ? "Libur x2" : "Hari kerja" }}
                        </Tag>
                    </template>
                </Column>
                <Column header="Dasar jadwal" style="min-width: 145px">
                    <template #body="{ data }">{{ data.schedule_day_type_label || "Otomatis" }}</template>
                </Column>
                <Column field="notes" header="Catatan" style="min-width: 240px">
                    <template #body="{ data }">{{ data.notes || "-" }}</template>
                </Column>
                <Column header="Aksi" style="width: 110px" align-frozen="right">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-1">
                            <Button icon="pi pi-pencil" text rounded severity="secondary" aria-label="Edit pengecualian" @click="openEdit(data)" />
                            <Button icon="pi pi-trash" text rounded severity="danger" aria-label="Hapus pengecualian" @click="removeOverride(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </template>
    </Card>

    <Dialog v-model:visible="dialogVisible" :header="editingOverride ? 'Edit pengecualian kalender' : 'Tambah pengecualian kalender'" modal class="w-full max-w-lg">
        <form class="space-y-4" @submit.prevent="save">
            <FormField
                v-if="editingOverride"
                label="Company"
                :error="form.errors.company_id"
                required
            >
                <Select
                    v-model="form.company_id"
                    :options="options.companies"
                    option-label="label"
                    option-value="value"
                    placeholder="Pilih company"
                    filter
                    class="w-full"
                />
            </FormField>
            <FormField
                v-else
                label="Company"
                :error="companySelectionError"
                hint="Centang kotak di header untuk pilih semua, atau gunakan tombol di bawah."
                required
            >
                <MultiSelect
                    v-model="form.company_ids"
                    :options="options.companies"
                    option-label="label"
                    option-value="value"
                    placeholder="Pilih satu atau beberapa company"
                    filter
                    display="chip"
                    :max-selected-labels="2"
                    selected-items-label="{0} company dipilih"
                    :show-toggle-all="true"
                    :invalid="Boolean(companySelectionError)"
                    class="w-full"
                />
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        {{ selectedCompanyCount }} dari {{ options.companies.length }} company dipilih
                    </span>
                    <div class="flex items-center gap-1">
                        <Button
                            type="button"
                            label="Pilih semua"
                            icon="pi pi-check-square"
                            severity="secondary"
                            text
                            size="small"
                            :disabled="allCompaniesSelected || options.companies.length === 0"
                            @click="selectAllCompanies"
                        />
                        <Button
                            type="button"
                            label="Kosongkan"
                            icon="pi pi-times"
                            severity="secondary"
                            text
                            size="small"
                            :disabled="selectedCompanyCount === 0"
                            @click="clearCompanies"
                        />
                    </div>
                </div>
            </FormField>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Tanggal" :error="form.errors.calendar_date" required>
                    <InputText v-model="form.calendar_date" type="date" class="w-full" />
                </FormField>
                <FormField label="Perlakuan" :error="form.errors.is_holiday_pay" required>
                    <Select
                        v-model="form.is_holiday_pay"
                        :options="[
                            { value: false, label: 'Hari kerja biasa' },
                            { value: true, label: 'Libur, jam kerja x2' },
                        ]"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </FormField>
            </div>
            <FormField label="Nama pengecualian" :error="form.errors.name">
                <InputText v-model="form.name" class="w-full" placeholder="Contoh: Libur nasional diganti hari kerja" />
            </FormField>
            <FormField label="Dasar tipe hari" :error="form.errors.schedule_day_type">
                <Select
                    v-model="form.schedule_day_type"
                    :options="options.dayTypes"
                    option-label="label"
                    option-value="value"
                    placeholder="Otomatis"
                    show-clear
                    class="w-full"
                />
            </FormField>
            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">
                Jika libur x2 dan dasar tipe hari kosong, sistem memakai ketentuan Minggu-Kamis.
                Jika hari kerja dan kosong, sistem memakai hari aktual.
            </p>
            <FormField label="Catatan" :error="form.errors.notes">
                <Textarea v-model="form.notes" rows="3" class="w-full" placeholder="Alasan perubahan kalender company" />
            </FormField>
            <div class="flex justify-end gap-2 pt-2">
                <Button type="button" label="Batal" severity="secondary" text @click="dialogVisible = false" />
                <Button type="submit" label="Simpan" icon="pi pi-check" :loading="form.processing" />
            </div>
        </form>
    </Dialog>
</template>
