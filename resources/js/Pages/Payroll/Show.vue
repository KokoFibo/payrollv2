<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageHeader from "@/Components/Admin/PageHeader.vue";
import StatCard from "@/Components/Admin/StatCard.vue";
import EmptyState from "@/Components/Admin/EmptyState.vue";

const props = defineProps({
    period: { type: Object, required: true },
    items: { type: Object, required: true },
});

const currency = new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
});

function processPeriod() {
    router.post(route("admin.payroll.process", props.period.id), {}, { preserveScroll: true });
}

function markPaid() {
    router.post(route("admin.payroll.pay", props.period.id), {}, { preserveScroll: true });
}

function onPage(event) {
    router.get(route("admin.payroll.show", props.period.id), { page: event.page + 1 }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function printPage() {
    window.print();
}

function statusSeverity(status) {
    return { draft: "secondary", processed: "info", paid: "success" }[status] || "secondary";
}
</script>

<template>
    <Head :title="period.name" />

    <AdminLayout>
        <div class="space-y-6">
            <PageHeader
                :title="period.name"
                :description="`Periode ${period.start_date} sampai ${period.end_date}. Tanggal pembayaran ${period.pay_date}.`"
                eyebrow="Payroll detail"
            >
                <template #actions>
                    <Link :href="route('admin.payroll.index')">
                        <Button label="Kembali" icon="pi pi-arrow-left" severity="secondary" outlined />
                    </Link>
                    <Button label="Print" icon="pi pi-print" severity="secondary" outlined @click="printPage" />
                    <Button v-if="period.status === 'draft'" label="Proses payroll" icon="pi pi-check" severity="danger" @click="processPeriod" />
                    <Button v-if="period.status === 'processed'" label="Tandai dibayar" icon="pi pi-wallet" severity="success" @click="markPaid" />
                </template>
            </PageHeader>

            <Tag :severity="statusSeverity(period.status)" class="px-3 py-2">{{ period.status_label }}</Tag>

            <div class="grid gap-4 sm:grid-cols-3">
                <StatCard label="Gross Payroll" :value="currency.format(period.total_gross)" description="Total pendapatan" icon="pi pi-arrow-up-right" tone="sky" />
                <StatCard label="Deductions" :value="currency.format(period.total_deductions)" description="Potongan dan BPJS" icon="pi pi-arrow-down-right" tone="amber" />
                <StatCard label="Net Payroll" :value="currency.format(period.total_net)" description="Jumlah dibayarkan" icon="pi pi-wallet" tone="emerald" />
            </div>

            <Message v-if="period.notes" severity="info" :closable="false">{{ period.notes }}</Message>

            <Card class="overflow-hidden border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                <template #title>
                    <div class="flex items-center justify-between px-1">
                        <div>
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Rincian karyawan</h2>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">{{ items.total.toLocaleString("id-ID") }} item payroll</p>
                        </div>
                    </div>
                </template>
                <template #content>
                    <DataTable :value="items.data" :paginator="true" :rows="items.per_page" :total-records="items.total" :first="(items.current_page - 1) * items.per_page" :lazy="true" scrollable scroll-height="620px" @page="onPage">
                        <template #empty>
                            <EmptyState title="Tidak ada item payroll" description="Periode ini dibuat saat belum ada karyawan aktif." icon="pi pi-inbox" />
                        </template>
                        <Column header="Karyawan" style="min-width: 230px">
                            <template #body="{ data }">
                                <p class="font-semibold text-slate-900 dark:text-white">{{ data.employee.name }}</p>
                                <p class="text-xs text-slate-400">{{ data.employee.nip }} · {{ data.employee.position || "-" }}</p>
                            </template>
                        </Column>
                        <Column header="Department" style="min-width: 150px"><template #body="{ data }">{{ data.employee.department || "-" }}</template></Column>
                        <Column header="Gaji Pokok" style="min-width: 135px"><template #body="{ data }">{{ currency.format(data.base_salary) }}</template></Column>
                        <Column header="Overtime" style="min-width: 125px"><template #body="{ data }">{{ currency.format(data.overtime_salary) }}</template></Column>
                        <Column header="Tunjangan" style="min-width: 125px"><template #body="{ data }">{{ currency.format(data.allowances) }}</template></Column>
                        <Column header="Bonus" style="min-width: 120px"><template #body="{ data }">{{ currency.format(data.bonus) }}</template></Column>
                        <Column header="Gross" style="min-width: 130px"><template #body="{ data }">{{ currency.format(data.gross_salary) }}</template></Column>
                        <Column header="Potongan" style="min-width: 130px"><template #body="{ data }">{{ currency.format(data.total_deductions) }}</template></Column>
                        <Column header="Net" style="min-width: 140px">
                            <template #body="{ data }"><span class="font-extrabold text-slate-950 dark:text-white">{{ currency.format(data.net_salary) }}</span></template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>
    </AdminLayout>
</template>
