<script setup>
import { computed, ref } from "vue";
import { Head, Link, useForm, router } from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageHeader from "@/Components/Admin/PageHeader.vue";
import StatCard from "@/Components/Admin/StatCard.vue";
import FormField from "@/Components/Admin/FormField.vue";
import EmptyState from "@/Components/Admin/EmptyState.vue";

const props = defineProps({
    periods: { type: Object, required: true },
    stats: { type: Object, required: true },
});

const confirm = useConfirm();
const dialogVisible = ref(false);
const currency = new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
});

const now = new Date();
const periodName = new Intl.DateTimeFormat("id-ID", { month: "long", year: "numeric" }).format(now);
const firstDay = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10);
const payDay = new Date(now.getFullYear(), now.getMonth() + 1, 25).toISOString().slice(0, 10);

const form = useForm({
    name: `Payroll ${periodName}`,
    start_date: firstDay,
    end_date: lastDay,
    pay_date: payDay,
    notes: "",
});

const canCreate = computed(() => props.stats.activeEmployees > 0);

function openCreate() {
    form.clearErrors();
    dialogVisible.value = true;
}

function save() {
    form.post(route("admin.payroll.store"), {
        onSuccess: () => {
            dialogVisible.value = false;
        },
    });
}

function removePeriod(period) {
    confirm.require({
        message: `Hapus periode ${period.name}? Seluruh item payroll draft akan dihapus.`,
        header: "Hapus periode payroll?",
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Batal",
        acceptLabel: "Hapus",
        rejectClass: "p-button-secondary p-button-outlined",
        acceptClass: "p-button-danger",
        accept: () => router.delete(route("admin.payroll.destroy", period.id)),
    });
}

function onPage(event) {
    router.get(route("admin.payroll.index"), { page: event.page + 1 }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function statusSeverity(status) {
    return { draft: "secondary", processed: "info", paid: "success" }[status] || "secondary";
}
</script>

<template>
    <Head title="Payroll" />

    <AdminLayout>
        <div class="space-y-6">
            <PageHeader
                title="Payroll"
                description="Buat periode, hitung komponen gaji karyawan aktif, proses, lalu tandai pembayaran."
                eyebrow="Payroll operations"
            >
                <template #actions>
                    <Button label="Buat periode" icon="pi pi-plus" severity="danger" :disabled="!canCreate" @click="openCreate" />
                </template>
            </PageHeader>

            <Message v-if="!canCreate" severity="warn" :closable="false">
                Belum ada karyawan aktif. Import atau aktifkan karyawan sebelum membuat periode payroll.
            </Message>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Active Employees" :value="stats.activeEmployees.toLocaleString('id-ID')" description="Karyawan PKWT dan PKWTT" icon="pi pi-users" tone="brand" />
                <StatCard label="Draft Periods" :value="stats.draftPeriods.toLocaleString('id-ID')" description="Belum diproses" icon="pi pi-file-edit" tone="amber" />
                <StatCard label="Processed" :value="stats.processedPeriods.toLocaleString('id-ID')" description="Siap dibayarkan" icon="pi pi-check-circle" tone="sky" />
                <StatCard label="Paid This Year" :value="currency.format(stats.paidThisYear)" description="Total net payroll" icon="pi pi-wallet" tone="emerald" />
            </div>

            <Card class="overflow-hidden border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                <template #title>
                    <div class="px-1">
                        <h2 class="text-base font-bold text-slate-950 dark:text-white">Riwayat periode payroll</h2>
                        <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">{{ periods.total.toLocaleString("id-ID") }} periode tercatat</p>
                    </div>
                </template>
                <template #content>
                    <DataTable :value="periods.data" :paginator="true" :rows="periods.per_page" :total-records="periods.total" :first="(periods.current_page - 1) * periods.per_page" :lazy="true" @page="onPage">
                        <template #empty>
                            <EmptyState title="Belum ada periode payroll" description="Buat periode pertama setelah data karyawan tersedia." icon="pi pi-wallet" />
                        </template>
                        <Column field="name" header="Periode" style="min-width: 220px">
                            <template #body="{ data }">
                                <p class="font-semibold text-slate-900 dark:text-white">{{ data.name }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ data.items_count }} karyawan</p>
                            </template>
                        </Column>
                        <Column header="Rentang" style="min-width: 190px">
                            <template #body="{ data }">{{ data.start_date }} → {{ data.end_date }}</template>
                        </Column>
                        <Column field="pay_date" header="Bayar" style="min-width: 120px" />
                        <Column header="Status" style="min-width: 110px">
                            <template #body="{ data }"><Tag :severity="statusSeverity(data.status)">{{ data.status_label }}</Tag></template>
                        </Column>
                        <Column field="total_gross" header="Gross" style="min-width: 145px">
                            <template #body="{ data }">{{ currency.format(data.total_gross) }}</template>
                        </Column>
                        <Column field="total_deductions" header="Deduction" style="min-width: 145px">
                            <template #body="{ data }">{{ currency.format(data.total_deductions) }}</template>
                        </Column>
                        <Column field="total_net" header="Net" style="min-width: 145px">
                            <template #body="{ data }"><span class="font-bold text-slate-900 dark:text-white">{{ currency.format(data.total_net) }}</span></template>
                        </Column>
                        <Column header="Aksi" style="width: 110px">
                            <template #body="{ data }">
                                <div class="flex justify-end gap-1">
                                    <Link :href="route('admin.payroll.show', data.id)">
                                        <Button icon="pi pi-eye" text rounded severity="secondary" aria-label="Lihat payroll" />
                                    </Link>
                                    <Button v-if="data.status === 'draft'" icon="pi pi-trash" text rounded severity="danger" aria-label="Hapus payroll" @click="removePeriod(data)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>

        <Dialog v-model:visible="dialogVisible" modal header="Buat periode payroll" class="w-full max-w-xl">
            <form class="space-y-4" @submit.prevent="save">
                <FormField label="Nama Periode" :error="form.errors.name" required>
                    <InputText v-model="form.name" class="w-full" />
                </FormField>
                <div class="grid gap-4 sm:grid-cols-3">
                    <FormField label="Mulai" :error="form.errors.start_date" required>
                        <InputText v-model="form.start_date" type="date" class="w-full" />
                    </FormField>
                    <FormField label="Selesai" :error="form.errors.end_date" required>
                        <InputText v-model="form.end_date" type="date" class="w-full" />
                    </FormField>
                    <FormField label="Tanggal Bayar" :error="form.errors.pay_date" required>
                        <InputText v-model="form.pay_date" type="date" class="w-full" />
                    </FormField>
                </div>
                <FormField label="Catatan" :error="form.errors.notes">
                    <Textarea v-model="form.notes" rows="3" class="w-full" />
                </FormField>
                <Message severity="info" :closable="false">
                    Sistem akan menghitung gaji pokok, gaji tetap, overtime, tunjangan, bonus, BPJS, iuran, dan denda untuk seluruh karyawan aktif.
                </Message>
                <div class="flex justify-end gap-2">
                    <Button type="button" label="Batal" severity="secondary" text @click="dialogVisible = false" />
                    <Button type="submit" label="Buat dan hitung" icon="pi pi-calculator" severity="danger" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </AdminLayout>
</template>
