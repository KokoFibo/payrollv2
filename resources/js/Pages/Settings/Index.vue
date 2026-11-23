<script setup>
import { computed } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageHeader from "@/Components/Admin/PageHeader.vue";
import FormField from "@/Components/Admin/FormField.vue";

const props = defineProps({
    settings: { type: Object, required: true },
});

const initial = Object.fromEntries(
    Object.entries(props.settings).map(([group, fields]) => [
        group,
        Object.fromEntries(fields.map((field) => [field.key, field.value ?? ""])),
    ]),
);

const form = useForm(initial);

const groupMeta = {
    general: { title: "Identitas & Regional", icon: "pi pi-building", description: "Nama organisasi, bahasa, dan zona waktu aplikasi." },
    payroll: { title: "Aturan Payroll", icon: "pi pi-wallet", description: "Periode cutoff dan tanggal pembayaran default." },
    attendance: { title: "Aturan Absensi", icon: "pi pi-clock", description: "Jam masuk dan toleransi keterlambatan." },
};

const generalFields = computed(() => props.settings.general ?? []);
const payrollFields = computed(() => props.settings.payroll ?? []);
const attendanceFields = computed(() => props.settings.attendance ?? []);

function submit() {
    form.put(route("admin.settings.update"), {
        preserveScroll: true,
    });
}

function errorFor(group, key) {
    return form.errors[`${group}.${key}`];
}
</script>

<template>
    <Head title="Settings" />

    <AdminLayout>
        <form class="space-y-6" @submit.prevent="submit">
            <PageHeader
                title="Settings"
                description="Konfigurasikan identitas organisasi serta aturan dasar payroll dan attendance."
                eyebrow="Application configuration"
            >
                <template #actions>
                    <Button type="submit" label="Simpan pengaturan" icon="pi pi-check" severity="danger" :loading="form.processing" />
                </template>
            </PageHeader>

            <Message v-if="Object.keys(form.errors).length" severity="error" :closable="false">
                Periksa kembali konfigurasi yang ditandai.
            </Message>

            <div class="grid gap-6 xl:grid-cols-2">
                <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <template #title>
                        <div class="flex items-start gap-3 px-1">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300"><i class="pi pi-building" /></span>
                            <div>
                                <h2 class="text-base font-bold text-slate-950 dark:text-white">Identitas & Regional</h2>
                                <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Nama organisasi, bahasa, dan zona waktu aplikasi.</p>
                            </div>
                        </div>
                    </template>
                    <template #content>
                        <div class="space-y-4">
                            <FormField v-for="field in generalFields" :key="field.key" :label="field.label" :error="errorFor('general', field.key)" required>
                                <Select v-if="field.key === 'locale'" v-model="form.general[field.key]" :options="[{ value: 'id', label: 'Bahasa Indonesia' }, { value: 'en', label: 'English' }]" option-label="label" option-value="value" class="w-full" />
                                <InputText v-else v-model="form.general[field.key]" class="h-11 w-full" />
                            </FormField>
                        </div>
                    </template>
                </Card>

                <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <template #title>
                        <div class="flex items-start gap-3 px-1">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"><i class="pi pi-wallet" /></span>
                            <div>
                                <h2 class="text-base font-bold text-slate-950 dark:text-white">Aturan Payroll</h2>
                                <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Periode cutoff dan tanggal pembayaran default.</p>
                            </div>
                        </div>
                    </template>
                    <template #content>
                        <div class="space-y-4">
                            <FormField v-for="field in payrollFields" :key="field.key" :label="field.label" :error="errorFor('payroll', field.key)" required>
                                <InputNumber v-if="field.type === 'integer'" v-model="form.payroll[field.key]" :min="1" :max="31" class="w-full" input-class="w-full" />
                                <InputText v-else v-model="form.payroll[field.key]" class="h-11 w-full" />
                            </FormField>
                        </div>
                    </template>
                </Card>

                <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
                    <template #title>
                        <div class="flex items-start gap-3 px-1">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><i class="pi pi-clock" /></span>
                            <div>
                                <h2 class="text-base font-bold text-slate-950 dark:text-white">Aturan Absensi</h2>
                                <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Jam masuk dan toleransi keterlambatan.</p>
                            </div>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormField v-for="field in attendanceFields" :key="field.key" :label="field.label" :error="errorFor('attendance', field.key)" required>
                                <InputText v-if="field.key === 'work_start_time'" v-model="form.attendance[field.key]" type="time" class="h-11 w-full" />
                                <InputNumber v-else v-model="form.attendance[field.key]" :min="0" :max="120" class="w-full" input-class="w-full" />
                            </FormField>
                        </div>
                    </template>
                </Card>
            </div>

            <div class="flex justify-end">
                <Button type="submit" label="Simpan semua pengaturan" icon="pi pi-check" severity="danger" :loading="form.processing" />
            </div>
        </form>
    </AdminLayout>
</template>
