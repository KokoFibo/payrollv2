<script setup>
import { reactive, ref } from "vue";
import { Head, router } from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";
import { useForm } from "@inertiajs/vue3";
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
});

const confirm = useConfirm();
const loading = ref(false);
const dialogVisible = ref(false);
const editingRecord = ref(null);
const filters = reactive({ ...props.filters });

const form = useForm({
    employee: null,
    attendance_date: new Date().toISOString().slice(0, 10),
    status: "present",
    check_in: "08:00",
    check_out: "17:00",
    overtime_minutes: 0,
    notes: "",
});

function load() {
    router.get(route("admin.attendance.index"), { ...filters }, {
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
    form.attendance_date = new Date().toISOString().slice(0, 10);
    form.status = "present";
    form.check_in = "08:00";
    form.check_out = "17:00";
    form.overtime_minutes = 0;
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
    form.check_in = record.check_in;
    form.check_out = record.check_out;
    form.overtime_minutes = record.overtime_minutes;
    form.notes = record.notes || "";
    dialogVisible.value = true;
}

function save() {
    form.transform((data) => ({
        karyawan_id: data.employee?.value,
        attendance_date: data.attendance_date,
        status: data.status,
        check_in: data.check_in || null,
        check_out: data.check_out || null,
        overtime_minutes: data.overtime_minutes,
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

function onPage(event) {
    router.get(route("admin.attendance.index"), {
        ...filters,
        page: event.page + 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function formatDate(value) {
    const [year, month, day] = value.split("-");

    return `${day}/${month}/${year}`;
}

function statusSeverity(status) {
    return {
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
                    <Button label="Tambah Absensi" icon="pi pi-plus" severity="danger" @click="openCreate" />
                </template>
            </PageHeader>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Hadir" :value="stats.present.toLocaleString('id-ID')" description="Hadir dan remote" icon="pi pi-check-circle" tone="emerald" />
                <StatCard label="Terlambat" :value="stats.late.toLocaleString('id-ID')" description="Perlu perhatian" icon="pi pi-clock" tone="amber" />
                <StatCard label="Cuti / Sakit" :value="stats.leave.toLocaleString('id-ID')" description="Ketidakhadiran terencana" icon="pi pi-calendar" tone="sky" />
                <StatCard label="Lembur" :value="`${stats.overtimeHours} jam`" description="Total periode terpilih" icon="pi pi-hourglass" tone="violet" />
            </div>

            <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                <template #title>
                    <h2 class="px-1 text-base font-bold text-slate-950 dark:text-white">Filter periode</h2>
                </template>
                <template #content>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                        <InputText v-model="filters.search" placeholder="Cari karyawan..." class="h-11" @change="load" />
                        <InputText v-model="filters.dateFrom" type="date" class="h-11" @change="load" />
                        <InputText v-model="filters.dateTo" type="date" class="h-11" @change="load" />
                        <Select v-model="filters.status" :options="options.statuses" option-label="label" option-value="value" placeholder="Semua status" show-clear class="h-11" @change="load" />
                        <Select v-model="filters.departmentId" :options="options.departments" option-label="label" option-value="value" placeholder="Semua department" filter show-clear class="h-11" @change="load" />
                        <Button label="Terapkan" icon="pi pi-filter" severity="secondary" outlined @click="load" />
                    </div>
                </template>
            </Card>

            <Card class="overflow-hidden border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                <template #title>
                    <div class="px-1">
                        <h2 class="text-base font-bold text-slate-950 dark:text-white">Daftar absensi</h2>
                        <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">{{ records.total.toLocaleString("id-ID") }} catatan ditemukan</p>
                    </div>
                </template>
                <template #content>
                    <DataTable :value="records.data" :loading="loading" :paginator="true" :rows="records.per_page" :total-records="records.total" :first="(records.current_page - 1) * records.per_page" :lazy="true" @page="onPage">
                        <template #empty>
                            <EmptyState title="Belum ada absensi" description="Tambahkan absensi pertama atau ubah periode filter." icon="pi pi-calendar-plus" />
                        </template>
                        <Column header="Tanggal" style="min-width: 110px">
                            <template #body="{ data }">{{ formatDate(data.attendance_date) }}</template>
                        </Column>
                        <Column header="Karyawan" style="min-width: 240px">
                            <template #body="{ data }">
                                <div class="flex items-center gap-3">
                                    <Avatar :label="data.employee.name.charAt(0)" shape="circle" class="bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300" />
                                    <div>
                                        <p class="font-semibold text-slate-900 dark:text-white">{{ data.employee.name }}</p>
                                        <p class="text-xs text-slate-400">{{ data.employee.nip }} · {{ data.employee.department || "-" }}</p>
                                    </div>
                                </div>
                            </template>
                        </Column>
                        <Column header="Status" style="min-width: 120px">
                            <template #body="{ data }"><Tag :severity="statusSeverity(data.status)">{{ data.status_label }}</Tag></template>
                        </Column>
                        <Column header="Masuk" style="min-width: 90px"><template #body="{ data }">{{ data.check_in || "-" }}</template></Column>
                        <Column header="Keluar" style="min-width: 90px"><template #body="{ data }">{{ data.check_out || "-" }}</template></Column>
                        <Column header="Lembur" style="min-width: 100px"><template #body="{ data }">{{ data.overtime_minutes }} menit</template></Column>
                        <Column field="notes" header="Catatan" style="min-width: 220px"><template #body="{ data }">{{ data.notes || "-" }}</template></Column>
                        <Column header="Aksi" style="width: 100px">
                            <template #body="{ data }">
                                <div class="flex justify-end gap-1">
                                    <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openEdit(data)" />
                                    <Button icon="pi pi-trash" text rounded severity="danger" @click="removeRecord(data)" />
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
                    <FormField label="Jam Masuk" :error="form.errors.check_in">
                        <InputText v-model="form.check_in" type="time" class="w-full" />
                    </FormField>
                    <FormField label="Jam Keluar" :error="form.errors.check_out">
                        <InputText v-model="form.check_out" type="time" class="w-full" />
                    </FormField>
                    <FormField label="Lembur (menit)" :error="form.errors.overtime_minutes" required>
                        <InputNumber v-model="form.overtime_minutes" :min="0" :max="1440" class="w-full" input-class="w-full" />
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
