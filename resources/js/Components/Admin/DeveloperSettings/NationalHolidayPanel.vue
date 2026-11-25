<script setup>
import { ref } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";
import FormField from "@/Components/Admin/FormField.vue";
import EmptyState from "@/Components/Admin/EmptyState.vue";

const props = defineProps({
    holidays: { type: Array, required: true },
});

const confirm = useConfirm();
const dialogVisible = ref(false);
const editingHoliday = ref(null);
const form = useForm({
    name: "",
    starts_on: "",
    ends_on: "",
    total_days: null,
});

function openCreate() {
    editingHoliday.value = null;
    form.reset();
    form.clearErrors();
    dialogVisible.value = true;
}

function openEdit(holiday) {
    editingHoliday.value = holiday;
    form.clearErrors();
    form.name = holiday.name ?? "";
    form.starts_on = holiday.starts_on ?? "";
    form.ends_on = holiday.ends_on ?? "";
    form.total_days = holiday.total_days;
    dialogVisible.value = true;
}

function save() {
    form.transform((data) => ({
        ...data,
        name: data.name || null,
        starts_on: data.starts_on || null,
        ends_on: data.ends_on || null,
        total_days: data.total_days || null,
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogVisible.value = false;
        },
    };

    if (editingHoliday.value) {
        form.put(route("admin.developer-settings.national-holidays.update", editingHoliday.value.id), options);

        return;
    }

    form.post(route("admin.developer-settings.national-holidays.store"), options);
}

function removeHoliday(holiday) {
    confirm.require({
        message: `Hapus hari libur ${holiday.name || "tanpa nama"}?`,
        header: "Hapus hari libur?",
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Batal",
        acceptLabel: "Hapus",
        rejectClass: "p-button-secondary p-button-outlined",
        acceptClass: "p-button-danger",
        accept: () => router.delete(
            route("admin.developer-settings.national-holidays.destroy", holiday.id),
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
                    <h2 class="text-base font-bold text-slate-950 dark:text-white">Hari libur nasional</h2>
                    <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">
                        Kelola rentang tanggal libur nasional yang dipakai sebagai kalender kerja.
                    </p>
                </div>
                <Button label="Tambah hari libur" icon="pi pi-plus" @click="openCreate" />
            </div>
        </template>
        <template #content>
            <DataTable :value="props.holidays" paginator :rows="10" :rows-per-page-options="[10, 25, 50]" scrollable>
                <template #empty>
                    <EmptyState title="Belum ada hari libur" description="Tambahkan tanggal libur nasional pertama." icon="pi pi-calendar" />
                </template>
                <Column field="name" header="Nama" style="min-width: 240px">
                    <template #body="{ data }">{{ data.name || "-" }}</template>
                </Column>
                <Column header="Mulai" style="min-width: 120px">
                    <template #body="{ data }">{{ formatDate(data.starts_on) }}</template>
                </Column>
                <Column header="Selesai" style="min-width: 120px">
                    <template #body="{ data }">{{ formatDate(data.ends_on) }}</template>
                </Column>
                <Column field="total_days" header="Jumlah hari" style="min-width: 110px">
                    <template #body="{ data }">{{ data.total_days ?? "-" }}</template>
                </Column>
                <Column header="Aksi" style="width: 110px" align-frozen="right">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-1">
                            <Button icon="pi pi-pencil" text rounded severity="secondary" aria-label="Edit hari libur" @click="openEdit(data)" />
                            <Button icon="pi pi-trash" text rounded severity="danger" aria-label="Hapus hari libur" @click="removeHoliday(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </template>
    </Card>

    <Dialog v-model:visible="dialogVisible" :header="editingHoliday ? 'Edit hari libur' : 'Tambah hari libur'" modal class="w-full max-w-lg">
        <form class="space-y-4" @submit.prevent="save">
            <FormField label="Nama hari libur" :error="form.errors.name">
                <InputText v-model="form.name" class="w-full" placeholder="Contoh: Hari Kemerdekaan" />
            </FormField>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Tanggal mulai" :error="form.errors.starts_on" required>
                    <InputText v-model="form.starts_on" type="date" class="w-full" />
                </FormField>
                <FormField
                    label="Tanggal selesai"
                    :error="form.errors.ends_on"
                    hint="Kosongkan jika libur hanya satu hari."
                >
                    <InputText v-model="form.ends_on" type="date" class="w-full" />
                </FormField>
            </div>
            <FormField label="Jumlah hari" :error="form.errors.total_days">
                <InputNumber v-model="form.total_days" :min="1" :max="366" class="w-full" input-class="w-full" />
            </FormField>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Jumlah hari akan dihitung otomatis dari rentang tanggal.
            </p>
            <div class="flex justify-end gap-2 pt-2">
                <Button type="button" label="Batal" severity="secondary" text @click="dialogVisible = false" />
                <Button type="submit" label="Simpan" icon="pi pi-check" :loading="form.processing" />
            </div>
        </form>
    </Dialog>
</template>
