<script setup>
import { computed, ref } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";
import FormField from "@/Components/Admin/FormField.vue";
import EmptyState from "@/Components/Admin/EmptyState.vue";

const props = defineProps({
    schedules: { type: Array, required: true },
    options: { type: Object, required: true },
    scheduleGuide: { type: Object, required: true },
});

const confirm = useConfirm();
const dialogVisible = ref(false);
const periodDialogVisible = ref(false);
const guideFileInputKey = ref(0);
const editingSchedule = ref(null);
const selectedPeriodKey = ref(null);
const form = useForm({
    name: "",
    company_id: null,
    starts_on: "",
    ends_on: "",
    schedule_profile: null,
    day_type: null,
    shift: null,
    check_in: "08:00",
    check_out: "16:00",
    check_out_day_offset: 0,
    standard_work_hours: 8,
    expected_punch_count: 4,
    notes: "",
});
const periodForm = useForm({
    name: "",
    starts_on: "",
    ends_on: "",
    schedules: defaultPeriodSchedules(),
});
const guideForm = useForm({
    schedule_file: null,
});
const guideUploadProgress = computed(() => guideForm.progress?.percentage ?? 0);
const periodGroups = computed(() => {
    const groups = new Map();

    for (const schedule of props.schedules) {
        if (
            schedule.company_id
            || schedule.directorate_id
            || schedule.department_id
            || schedule.day_type
        ) {
            continue;
        }

        const value = `${schedule.name}|${schedule.starts_on}|${schedule.ends_on}`;
        groups.set(value, {
            value,
            label: `${schedule.name} (${formatDate(schedule.starts_on)} - ${formatDate(schedule.ends_on)})`,
            name: schedule.name,
            starts_on: schedule.starts_on,
            ends_on: schedule.ends_on,
        });
    }

    return [...groups.values()];
});

function openCreate() {
    editingSchedule.value = null;
    form.reset();
    form.clearErrors();
    form.check_in = "08:00";
    form.check_out = "16:00";
    form.company_id = null;
    form.check_out_day_offset = 0;
    form.standard_work_hours = 8;
    form.expected_punch_count = 4;
    dialogVisible.value = true;
}

function defaultPeriodSchedules() {
    return [
        {
            enabled: false,
            label: "Produksi / Umum - Shift pagi",
            schedule_profile: "general",
            shift: "day",
            check_in: "08:00",
            check_out: "17:00",
            check_out_day_offset: 0,
            standard_work_hours: 8,
            expected_punch_count: 4,
        },
        {
            enabled: false,
            label: "Produksi / Umum - Shift malam",
            schedule_profile: "general",
            shift: "night",
            check_in: "20:00",
            check_out: "05:00",
            check_out_day_offset: 1,
            standard_work_hours: 8,
            expected_punch_count: 4,
        },
        {
            enabled: false,
            label: "Security - Shift pagi",
            schedule_profile: "security",
            shift: "day",
            check_in: "08:00",
            check_out: "20:00",
            check_out_day_offset: 0,
            standard_work_hours: 8,
            expected_punch_count: 2,
        },
        {
            enabled: false,
            label: "Security - Shift malam",
            schedule_profile: "security",
            shift: "night",
            check_in: "20:00",
            check_out: "08:00",
            check_out_day_offset: 1,
            standard_work_hours: 8,
            expected_punch_count: 2,
        },
        {
            enabled: false,
            label: "QC Aging - Shift pagi",
            schedule_profile: "qc_aging",
            shift: "day",
            check_in: "08:00",
            check_out: "17:00",
            check_out_day_offset: 0,
            standard_work_hours: 8,
            expected_punch_count: 4,
        },
        {
            enabled: false,
            label: "QC Aging - Shift malam",
            schedule_profile: "qc_aging",
            shift: "night",
            check_in: "20:00",
            check_out: "05:00",
            check_out_day_offset: 1,
            standard_work_hours: 8,
            expected_punch_count: 4,
        },
    ];
}

function openPeriodCreate() {
    selectedPeriodKey.value = null;
    periodForm.reset();
    periodForm.clearErrors();
    periodForm.name = "";
    periodForm.starts_on = "";
    periodForm.ends_on = "";
    periodForm.schedules = defaultPeriodSchedules();
    periodDialogVisible.value = true;
}

function selectPeriod(value) {
    selectedPeriodKey.value = value;

    if (!value) {
        return;
    }

    const period = periodGroups.value.find((item) => item.value === value);

    if (!period) {
        return;
    }

    periodForm.name = period.name;
    periodForm.starts_on = period.starts_on;
    periodForm.ends_on = period.ends_on;
    periodForm.schedules = defaultPeriodSchedules().map((row) => {
        const existing = props.schedules.find((schedule) => (
            schedule.name === period.name
            && schedule.starts_on === period.starts_on
            && schedule.ends_on === period.ends_on
            && schedule.schedule_profile === row.schedule_profile
            && schedule.shift === row.shift
            && !schedule.company_id
            && !schedule.directorate_id
            && !schedule.department_id
            && !schedule.day_type
        ));

        if (!existing) {
            return row;
        }

        return {
            ...row,
            enabled: true,
            check_in: existing.check_in,
            check_out: existing.check_out,
            check_out_day_offset: existing.check_out_day_offset,
            standard_work_hours: existing.standard_work_hours,
            expected_punch_count: existing.expected_punch_count,
        };
    });
}

function savePeriod() {
    periodForm.post(route("admin.developer-settings.special-work-schedules.bulk"), {
        preserveScroll: true,
        onSuccess: () => {
            periodDialogVisible.value = false;
        },
    });
}

function openEdit(schedule) {
    editingSchedule.value = schedule;
    form.clearErrors();
    form.name = schedule.name;
    form.company_id = schedule.company_id;
    form.starts_on = schedule.starts_on;
    form.ends_on = schedule.ends_on;
    form.schedule_profile = schedule.schedule_profile;
    form.day_type = schedule.day_type;
    form.shift = schedule.shift;
    form.check_in = schedule.check_in;
    form.check_out = schedule.check_out;
    form.check_out_day_offset = schedule.check_out_day_offset;
    form.standard_work_hours = schedule.standard_work_hours;
    form.expected_punch_count = schedule.expected_punch_count;
    form.notes = schedule.notes ?? "";
    dialogVisible.value = true;
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogVisible.value = false;
        },
    };

    if (editingSchedule.value) {
        form.put(route("admin.developer-settings.special-work-schedules.update", editingSchedule.value.id), options);

        return;
    }

    form.post(route("admin.developer-settings.special-work-schedules.store"), options);
}

function removeSchedule(schedule) {
    confirm.require({
        message: `Hapus jadwal khusus ${schedule.name}?`,
        header: "Hapus jadwal kerja?",
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Batal",
        acceptLabel: "Hapus",
        rejectClass: "p-button-secondary p-button-outlined",
        acceptClass: "p-button-danger",
        accept: () => router.delete(
            route("admin.developer-settings.special-work-schedules.destroy", schedule.id),
            { preserveScroll: true },
        ),
    });
}

function downloadScheduleGuide() {
    window.location.assign(route("admin.attendance.schedule-guide.download"));
}

function chooseGuideFile(event) {
    guideForm.schedule_file = event.target.files?.[0] ?? null;
    guideForm.clearErrors();
}

function uploadGuide() {
    guideForm.post(route("admin.developer-settings.special-work-schedules.import"), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            guideForm.reset();
            guideFileInputKey.value++;
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
</script>

<template>
    <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
        <template #title>
            <div class="flex flex-col gap-3 px-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-950 dark:text-white">Jadwal kerja khusus</h2>
                    <p class="mt-1 max-w-3xl text-xs font-normal leading-5 text-slate-500 dark:text-slate-400">
                        Atur periode seperti Ramadan. Jadwal dapat berlaku global atau hanya untuk company tertentu.
                        Aturan paling spesifik akan diprioritaskan saat import maupun edit absensi langsung.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        label="Download Pedoman Jadwal Kerja Excel"
                        icon="pi pi-download"
                        severity="secondary"
                        text
                        :disabled="!scheduleGuide.downloadAvailable"
                        @click="downloadScheduleGuide"
                    />
                    <Button label="Atur jadwal serentak" icon="pi pi-calendar-plus" severity="secondary" outlined @click="openPeriodCreate" />
                    <Button label="Tambah jadwal" icon="pi pi-plus" @click="openCreate" />
                </div>
            </div>
        </template>
        <template #content>
            <Message v-if="!scheduleGuide.downloadAvailable" severity="warn" :closable="false" class="mb-4">
                Template pedoman jadwal belum tersedia di server.
            </Message>
            <form
                class="mb-4 rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-950/40"
                @submit.prevent="uploadGuide"
            >
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Upload perubahan jadwal dari Excel</h3>
                        <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500 dark:text-slate-400">
                            Isi bagian Jadwal Perubahan / Ramadan pada tab Jadwal Kerja. Jadwal baru akan ditambahkan atau diperbarui;
                            jadwal lain tidak dihapus.
                        </p>
                    </div>
                    <div class="w-full max-w-xl">
                        <input
                            :key="guideFileInputKey"
                            type="file"
                            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            class="block w-full cursor-pointer rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-600 file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-slate-800 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:file:bg-slate-700"
                            @change="chooseGuideFile"
                        />
                        <p v-if="guideForm.errors.schedule_file" class="mt-2 text-xs font-medium text-red-600 dark:text-red-400">
                            {{ guideForm.errors.schedule_file }}
                        </p>
                        <ProgressBar v-if="guideForm.progress" :value="guideUploadProgress" :show-value="false" class="mt-2 h-2" />
                    </div>
                </div>
                <div class="mt-3 flex justify-end">
                    <Button
                        type="submit"
                        label="Upload dan simpan jadwal"
                        icon="pi pi-upload"
                        :loading="guideForm.processing"
                        :disabled="!guideForm.schedule_file"
                    />
                </div>
            </form>

            <DataTable :value="props.schedules" paginator :rows="10" :rows-per-page-options="[10, 25, 50]" scrollable>
                <template #empty>
                    <EmptyState title="Belum ada jadwal khusus" description="Tambahkan periode jadwal seperti Ramadan pertama." icon="pi pi-clock" />
                </template>
                <Column field="name" header="Periode" style="min-width: 150px">
                    <template #body="{ data }">
                        <p class="font-semibold text-slate-900 dark:text-white">{{ data.name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ formatDate(data.starts_on) }} - {{ formatDate(data.ends_on) }}</p>
                    </template>
                </Column>
                <Column field="scope_label" header="Scope" style="min-width: 160px" />
                <Column field="schedule_profile_label" header="Profile" style="min-width: 135px" />
                <Column field="day_type_label" header="Hari" style="min-width: 120px" />
                <Column field="shift_label" header="Shift" style="min-width: 110px" />
                <Column header="Jam kerja" style="min-width: 150px">
                    <template #body="{ data }">
                        <span class="font-mono">{{ data.check_in }} - {{ data.check_out }}{{ data.check_out_day_offset ? " +1d" : "" }}</span>
                    </template>
                </Column>
                <Column field="expected_punch_count" header="Punch" style="min-width: 80px" />
                <Column field="standard_work_hours" header="Jam kerja" style="min-width: 95px">
                    <template #body="{ data }">{{ data.standard_work_hours }} jam</template>
                </Column>
                <Column field="notes" header="Catatan" style="min-width: 220px">
                    <template #body="{ data }">{{ data.notes || "-" }}</template>
                </Column>
                <Column header="Aksi" style="width: 110px" align-frozen="right">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-1">
                            <Button icon="pi pi-pencil" text rounded severity="secondary" aria-label="Edit jadwal" @click="openEdit(data)" />
                            <Button icon="pi pi-trash" text rounded severity="danger" aria-label="Hapus jadwal" @click="removeSchedule(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </template>
    </Card>

    <Dialog v-model:visible="dialogVisible" :header="editingSchedule ? 'Edit jadwal kerja' : 'Tambah jadwal kerja'" modal class="w-full max-w-2xl">
        <form class="space-y-4" @submit.prevent="save">
            <FormField label="Nama periode" :error="form.errors.name" required>
                <InputText v-model="form.name" class="w-full" placeholder="Contoh: Ramadan 2027" />
            </FormField>
            <FormField label="Berlaku untuk company" :error="form.errors.company_id">
                <Select
                    v-model="form.company_id"
                    :options="options.companies"
                    option-label="label"
                    option-value="value"
                    placeholder="Semua company (global)"
                    filter
                    show-clear
                    class="w-full"
                />
            </FormField>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Tanggal mulai" :error="form.errors.starts_on" required>
                    <InputText v-model="form.starts_on" type="date" class="w-full" />
                </FormField>
                <FormField label="Tanggal selesai" :error="form.errors.ends_on" required>
                    <InputText v-model="form.ends_on" type="date" class="w-full" />
                </FormField>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <FormField label="Profile" :error="form.errors.schedule_profile">
                    <Select v-model="form.schedule_profile" :options="options.profiles" option-label="label" option-value="value" placeholder="Semua profile" show-clear class="w-full" />
                </FormField>
                <FormField label="Tipe hari" :error="form.errors.day_type">
                    <Select v-model="form.day_type" :options="options.dayTypes" option-label="label" option-value="value" placeholder="Semua hari" show-clear class="w-full" />
                </FormField>
                <FormField label="Shift" :error="form.errors.shift">
                    <Select v-model="form.shift" :options="options.shifts" option-label="label" option-value="value" placeholder="Semua shift" show-clear class="w-full" />
                </FormField>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Jam masuk" :error="form.errors.check_in" required>
                    <InputText v-model="form.check_in" type="time" class="w-full" />
                </FormField>
                <FormField label="Jam keluar" :error="form.errors.check_out" required>
                    <InputText v-model="form.check_out" type="time" class="w-full" />
                </FormField>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <FormField label="Akhir shift" :error="form.errors.check_out_day_offset" required>
                    <Select
                        v-model="form.check_out_day_offset"
                        :options="[
                            { value: 0, label: 'Hari yang sama' },
                            { value: 1, label: 'Hari berikutnya' },
                            { value: 2, label: 'Dua hari berikutnya' },
                        ]"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </FormField>
                <FormField label="Jam kerja standar" :error="form.errors.standard_work_hours" required>
                    <InputNumber v-model="form.standard_work_hours" :min="0.5" :max="24" :step="0.5" :min-fraction-digits="0" :max-fraction-digits="1" class="w-full" input-class="w-full" />
                </FormField>
                <FormField label="Jumlah punch" :error="form.errors.expected_punch_count" required>
                    <InputNumber v-model="form.expected_punch_count" :min="0" :max="6" class="w-full" input-class="w-full" />
                </FormField>
            </div>
            <FormField label="Catatan" :error="form.errors.notes">
                <Textarea v-model="form.notes" rows="3" class="w-full" placeholder="Alasan atau detail perubahan jadwal" />
            </FormField>
            <div class="flex justify-end gap-2 pt-2">
                <Button type="button" label="Batal" severity="secondary" text @click="dialogVisible = false" />
                <Button type="submit" label="Simpan jadwal" icon="pi pi-check" :loading="form.processing" />
            </div>
        </form>
    </Dialog>

    <Dialog v-model:visible="periodDialogVisible" modal header="Atur jadwal serentak" class="w-full max-w-5xl">
        <form class="space-y-5" @submit.prevent="savePeriod">
            <Message severity="info" :closable="false">
                Jadwal ini berlaku untuk semua hari dan seluruh organisasi. Presensi yang sudah ada pada rentang tanggal tersebut akan dihitung ulang.
            </Message>
            <Message v-if="periodForm.errors.schedules" severity="error" :closable="false">
                {{ periodForm.errors.schedules }}
            </Message>

            <FormField label="Ubah periode yang sudah ada">
                <Select
                    v-model="selectedPeriodKey"
                    :options="periodGroups"
                    option-label="label"
                    option-value="value"
                    placeholder="Pilih periode atau buat periode baru"
                    show-clear
                    filter
                    class="w-full"
                    @update:model-value="selectPeriod"
                />
            </FormField>

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Nama periode" :error="periodForm.errors.name" required>
                    <InputText v-model="periodForm.name" class="w-full" placeholder="Contoh: Ramadan 2027" />
                </FormField>
                <div class="grid grid-cols-2 gap-4">
                    <FormField label="Tanggal mulai" :error="periodForm.errors.starts_on" required>
                        <InputText v-model="periodForm.starts_on" type="date" class="w-full" />
                    </FormField>
                    <FormField label="Tanggal selesai" :error="periodForm.errors.ends_on" required>
                        <InputText v-model="periodForm.ends_on" type="date" class="w-full" />
                    </FormField>
                </div>
            </div>

            <div class="space-y-3">
                <div
                    v-for="(schedule, index) in periodForm.schedules"
                    :key="`${schedule.schedule_profile}-${schedule.shift}`"
                    class="rounded-2xl border p-4 transition"
                    :class="schedule.enabled
                        ? 'border-brand-200 bg-brand-50/40 dark:border-brand-500/20 dark:bg-brand-500/5'
                        : 'border-slate-200 bg-slate-50/60 dark:border-slate-800 dark:bg-slate-950/30'"
                >
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <label class="flex min-w-48 cursor-pointer items-center gap-3">
                            <Checkbox v-model="schedule.enabled" binary />
                            <span>
                                <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">{{ schedule.label }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Aktifkan untuk mengubah shift ini</span>
                            </span>
                        </label>

                        <div class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                            <FormField label="Masuk" :error="periodForm.errors[`schedules.${index}.check_in`]">
                                <InputText v-model="schedule.check_in" type="time" class="w-full" :disabled="!schedule.enabled" />
                            </FormField>
                            <FormField label="Keluar" :error="periodForm.errors[`schedules.${index}.check_out`]">
                                <InputText v-model="schedule.check_out" type="time" class="w-full" :disabled="!schedule.enabled" />
                            </FormField>
                            <FormField label="Akhir shift">
                                <Select
                                    v-model="schedule.check_out_day_offset"
                                    :options="[
                                        { value: 0, label: 'Hari sama' },
                                        { value: 1, label: '+1 hari' },
                                        { value: 2, label: '+2 hari' },
                                    ]"
                                    option-label="label"
                                    option-value="value"
                                    class="w-full"
                                    :disabled="!schedule.enabled"
                                />
                            </FormField>
                            <FormField label="Jam reguler">
                                <InputNumber
                                    v-model="schedule.standard_work_hours"
                                    :min="0.5"
                                    :max="24"
                                    :step="0.5"
                                    :min-fraction-digits="0"
                                    :max-fraction-digits="1"
                                    class="w-full"
                                    input-class="w-full"
                                    :disabled="!schedule.enabled"
                                />
                            </FormField>
                            <FormField label="Jumlah punch">
                                <InputNumber
                                    v-model="schedule.expected_punch_count"
                                    :min="0"
                                    :max="6"
                                    class="w-full"
                                    input-class="w-full"
                                    :disabled="!schedule.enabled"
                                />
                            </FormField>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Button type="button" label="Batal" severity="secondary" text @click="periodDialogVisible = false" />
                <Button type="submit" label="Simpan dan hitung ulang" icon="pi pi-check" :loading="periodForm.processing" />
            </div>
        </form>
    </Dialog>
</template>
