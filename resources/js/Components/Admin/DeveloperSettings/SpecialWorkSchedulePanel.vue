<script setup>
import { ref } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";
import FormField from "@/Components/Admin/FormField.vue";
import EmptyState from "@/Components/Admin/EmptyState.vue";

const props = defineProps({
    schedules: { type: Array, required: true },
    options: { type: Object, required: true },
});

const confirm = useConfirm();
const dialogVisible = ref(false);
const editingSchedule = ref(null);
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
                <Button label="Tambah jadwal" icon="pi pi-plus" @click="openCreate" />
            </div>
        </template>
        <template #content>
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
</template>
