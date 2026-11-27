<script setup>
import { reactive, ref } from "vue";
import { router } from "@inertiajs/vue3";
import EmptyState from "@/Components/Admin/EmptyState.vue";

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, required: true },
    eventOptions: { type: Array, required: true },
});

const filters = reactive({ ...props.filters });
const detailVisible = ref(false);
const selectedLog = ref(null);
const currencyFields = /gaji|tunjangan|bonus|potongan|iuran|denda|salary|gross|allowance|limit/i;
const currency = new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
});

function load(page = 1) {
    router.get(route("admin.super-admin-dashboard.index"), {
        audit_search: filters.search,
        audit_event: filters.event || "",
        audit_from: filters.from,
        audit_to: filters.to,
        page,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function openDetail(log) {
    selectedLog.value = log;
    detailVisible.value = true;
}

function formatDate(value) {
    if (!value) {
        return "-";
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "medium",
    }).format(new Date(value));
}

function formatValue(change, value) {
    if (value === null || value === undefined || value === "") {
        return "-";
    }

    if (typeof value === "boolean") {
        return value ? "Ya" : "Tidak";
    }

    if (typeof value === "number" && currencyFields.test(`${change.field} ${change.label}`)) {
        return currency.format(value);
    }

    return String(value);
}

function eventSeverity(eventType) {
    if (eventType.includes("deleted")) {
        return "danger";
    }

    if (eventType.includes("updated") || eventType.includes("imported") || eventType.includes("migrated")) {
        return "warn";
    }

    return "success";
}
</script>

<template>
    <div>
        <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
        <template #title>
            <div class="flex flex-col gap-3 px-1 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-950 dark:text-white">Audit perubahan gaji dan payroll</h2>
                    <p class="mt-1 max-w-3xl text-xs font-normal leading-5 text-slate-500 dark:text-slate-400">
                        Jejak permanen perubahan data gaji, attendance, periode payroll, dan konfigurasi yang memengaruhi perhitungan payroll.
                    </p>
                </div>
                <Tag severity="secondary">{{ logs.total.toLocaleString("id-ID") }} catatan</Tag>
            </div>
        </template>
        <template #content>
            <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-5" @submit.prevent="load(1)">
                <InputText v-model="filters.search" placeholder="Cari aktor, NIP, atau nama..." class="h-11 xl:col-span-2" />
                <Select
                    v-model="filters.event"
                    :options="eventOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Semua aksi"
                    show-clear
                    filter
                    class="h-11"
                />
                <InputText v-model="filters.from" type="date" class="h-11" />
                <InputText v-model="filters.to" type="date" class="h-11" />
                <div class="flex gap-2 xl:col-span-5 xl:justify-end">
                    <Button label="Terapkan filter" icon="pi pi-search" type="submit" size="small" />
                </div>
            </form>

            <DataTable
                :value="logs.data"
                :paginator="true"
                :rows="logs.per_page"
                :total-records="logs.total"
                :first="(logs.current_page - 1) * logs.per_page"
                :lazy="true"
                scrollable
                class="mt-5"
                @page="(event) => load(event.page + 1)"
            >
                <template #empty>
                    <EmptyState
                        title="Belum ada audit perubahan"
                        description="Perubahan gaji dan data payroll akan tercatat otomatis di sini."
                        icon="pi pi-history"
                    />
                </template>
                <Column header="Waktu" style="min-width: 165px">
                    <template #body="{ data }">
                        <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ formatDate(data.occurred_at) }}</p>
                        <p class="mt-1 font-mono text-[10px] text-slate-400">{{ data.ip_address || "-" }}</p>
                    </template>
                </Column>
                <Column header="Aktor" style="min-width: 160px">
                    <template #body="{ data }">
                        <p class="font-semibold text-slate-800 dark:text-slate-100">{{ data.actor_name }}</p>
                        <p class="mt-1 text-[11px] text-slate-400">Role level {{ data.actor_role || "-" }}</p>
                    </template>
                </Column>
                <Column header="Aksi" style="min-width: 180px">
                    <template #body="{ data }">
                        <Tag :severity="eventSeverity(data.event_type)">{{ data.event_label }}</Tag>
                    </template>
                </Column>
                <Column header="Data" style="min-width: 220px">
                    <template #body="{ data }">
                        <p class="font-semibold text-slate-800 dark:text-slate-100">{{ data.subject_label }}</p>
                        <p v-if="data.employee_nip" class="mt-1 text-xs text-slate-500">{{ data.employee_nip }} · {{ data.employee_name }}</p>
                    </template>
                </Column>
                <Column header="Perubahan" style="min-width: 320px">
                    <template #body="{ data }">
                        <div v-if="data.changes.length" class="flex flex-wrap gap-1">
                            <Tag
                                v-for="change in data.changes.slice(0, 2)"
                                :key="`${data.id}-${change.field}`"
                                severity="secondary"
                                class="text-[10px]"
                            >
                                {{ change.label }}
                            </Tag>
                            <Tag v-if="data.changes.length > 2" severity="secondary" class="text-[10px]">
                                +{{ data.changes.length - 2 }} field
                            </Tag>
                        </div>
                        <p v-else class="text-xs text-slate-400">Aksi tercatat tanpa detail field.</p>
                    </template>
                </Column>
                <Column header="Detail" style="width: 90px">
                    <template #body="{ data }">
                        <Button icon="pi pi-eye" text rounded size="small" severity="secondary" aria-label="Lihat detail audit" @click="openDetail(data)" />
                    </template>
                </Column>
            </DataTable>
        </template>
        </Card>

        <Dialog v-model:visible="detailVisible" header="Detail audit payroll" modal class="w-full max-w-4xl">
            <div v-if="selectedLog" class="space-y-5">
            <div class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 text-xs dark:border-slate-800 dark:bg-slate-950/40 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-slate-400">Waktu</p>
                    <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ formatDate(selectedLog.occurred_at) }}</p>
                </div>
                <div>
                    <p class="text-slate-400">Aktor</p>
                    <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ selectedLog.actor_name }}</p>
                </div>
                <div>
                    <p class="text-slate-400">Aksi</p>
                    <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ selectedLog.event_label }}</p>
                </div>
                <div>
                    <p class="text-slate-400">IP</p>
                    <p class="mt-1 font-mono font-semibold text-slate-800 dark:text-slate-100">{{ selectedLog.ip_address || "-" }}</p>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ selectedLog.subject_label }}</h3>
                <p v-if="selectedLog.employee_nip" class="mt-1 text-xs text-slate-500">
                    {{ selectedLog.employee_nip }} · {{ selectedLog.employee_name }}
                </p>
            </div>

            <DataTable v-if="selectedLog.changes.length" :value="selectedLog.changes" size="small" scrollable>
                <Column field="label" header="Field" style="min-width: 190px" />
                <Column header="Sebelum" style="min-width: 200px">
                    <template #body="{ data }">{{ formatValue(data, data.old) }}</template>
                </Column>
                <Column header="Sesudah" style="min-width: 200px">
                    <template #body="{ data }">{{ formatValue(data, data.new) }}</template>
                </Column>
            </DataTable>
            <Message v-else severity="secondary" :closable="false">
                Aksi ini dicatat melalui metadata tanpa perubahan field langsung.
            </Message>

            <pre v-if="Object.keys(selectedLog.metadata || {}).length" class="overflow-auto rounded-xl bg-slate-950 p-4 text-[11px] leading-5 text-slate-200">{{ JSON.stringify(selectedLog.metadata, null, 2) }}</pre>
            </div>
        </Dialog>
    </div>
</template>
