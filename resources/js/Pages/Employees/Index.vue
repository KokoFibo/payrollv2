<script setup>
import { computed, reactive, ref } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageHeader from "@/Components/Admin/PageHeader.vue";
import FormField from "@/Components/Admin/FormField.vue";

const props = defineProps({
    karyawans: { type: Object, required: true },
    filters: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
});

const confirm = useConfirm();
const page = usePage();
const canExport = computed(() => page.props.auth?.can?.exportEmployees ?? false);
const canExportKhusus = computed(() => page.props.auth?.can?.exportEmployeesKhusus ?? false);
const loading = ref(false);
const searchTimer = ref(null);
const filters = reactive({ ...props.filters });
const exportMenu = ref(null);
const specialExportDialog = ref(false);

const previousMonth = new Date(new Date().getFullYear(), new Date().getMonth() - 1, 1);
const specialExport = reactive({
    semuaData: false,
    bulan: previousMonth.getMonth() + 1,
    tahun: previousMonth.getFullYear(),
});

const months = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
].map((label, index) => ({ value: index + 1, label }));

const years = Array.from(
    { length: 12 },
    (_, index) => new Date().getFullYear() - 10 + index,
);

const exportMenuItems = computed(() => {
    const items = [];

    if (canExport.value) {
        items.push({
            label: "Export Excel Karyawan",
            icon: "pi pi-file-excel",
            command: exportEmployees,
        });
    }

    if (canExportKhusus.value) {
        items.push({
            label: "Export Data Khusus",
            icon: "pi pi-file-export",
            command: () => {
                specialExportDialog.value = true;
            },
        });
    }

    return items;
});

const currency = new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
});

function queryState() {
    return {
        search: filters.search || "",
        status: filters.status ?? "",
        companyId: filters.companyId ?? "",
        directorateId: filters.directorateId ?? "",
        departmentId: filters.departmentId ?? "",
        jabatanId: filters.jabatanId ?? "",
        etnisId: filters.etnisId ?? "",
        perPage: filters.perPage,
        sortField: filters.sortField,
        sortDirection: filters.sortDirection,
        page: 1,
    };
}

function load(resetPage = true) {
    const query = queryState();

    if (!resetPage) {
        query.page = props.karyawans.current_page;
    }

    router.get(route("admin.employees.index"), query, {
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

function onSearch() {
    window.clearTimeout(searchTimer.value);
    searchTimer.value = window.setTimeout(() => load(true), 300);
}

function resetFilters() {
    Object.assign(filters, {
        search: "",
        status: "active",
        companyId: "",
        directorateId: "",
        departmentId: "",
        jabatanId: "",
        etnisId: "",
        perPage: 10,
        sortField: "nip",
        sortDirection: "desc",
    });
    load(true);
}

function onPage(event) {
    router.get(route("admin.employees.index"), {
        ...queryState(),
        page: event.page + 1,
        perPage: event.rows,
    }, {
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

function onSort(event) {
    filters.sortField = event.sortField || "nip";
    filters.sortDirection = event.sortOrder === 1 ? "asc" : "desc";
    load(true);
}

function deleteEmployee(employee) {
    confirm.require({
        message: `Data ${employee.nama} akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.`,
        header: "Hapus data karyawan?",
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Batal",
        acceptLabel: "Hapus Karyawan",
        rejectClass: "p-button-secondary p-button-outlined",
        acceptClass: "p-button-danger",
        accept: () => {
            router.delete(route("admin.employees.destroy", employee.uuid), {
                preserveScroll: true,
            });
        },
    });
}

function exportEmployees() {
    const query = new URLSearchParams(queryState()).toString();
    window.location.assign(`${route("admin.employees.export")}?${query}`);
}

function toggleExportMenu(event) {
    exportMenu.value.toggle(event);
}

function submitSpecialExport() {
    const query = new URLSearchParams({
        semua_data: specialExport.semuaData ? "1" : "0",
        bulan: String(specialExport.bulan),
        tahun: String(specialExport.tahun),
    }).toString();

    specialExportDialog.value = false;
    window.location.assign(`${route("admin.employees.export-khusus")}?${query}`);
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
    <Head title="Employees" />

    <AdminLayout>
        <div class="space-y-6">
            <PageHeader
                title="Employees"
                description="Kelola data karyawan, struktur organisasi, dan informasi penggajian dalam satu tempat."
                eyebrow="People operations"
            >
                <template #actions>
                    <Button
                        v-if="canExport || canExportKhusus"
                        label="Export"
                        icon="pi pi-download"
                        icon-pos="right"
                        severity="secondary"
                        outlined
                        aria-haspopup="true"
                        aria-controls="employee-export-menu"
                        @click="toggleExportMenu"
                    />
                    <Menu id="employee-export-menu" ref="exportMenu" :model="exportMenuItems" popup />
                    <Link :href="route('admin.employees.create')">
                        <Button label="Tambah Karyawan" icon="pi pi-plus" />
                    </Link>
                </template>
            </PageHeader>

            <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                <template #title>
                    <div class="px-1">
                        <h2 class="text-base font-bold text-slate-950 dark:text-white">Pencarian & Filter</h2>
                        <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Filter aktif juga berlaku saat mengekspor data.</p>
                    </div>
                </template>
                <template #content>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <div class="relative md:col-span-2 xl:col-span-1">
                            <i class="pi pi-search absolute left-3 top-1/2 z-10 -translate-y-1/2 text-sm text-slate-400" />
                            <InputText
                                v-model="filters.search"
                                placeholder="Cari nama, email, atau ID karyawan"
                                class="h-11 w-full pl-9"
                                @update:model-value="onSearch"
                            />
                        </div>

                        <Select
                            v-model="filters.status"
                            :options="filterOptions.statuses"
                            option-label="label"
                            option-value="value"
                            placeholder="Semua Status"
                            show-clear
                            class="h-11 w-full"
                            @change="load(true)"
                        />
                        <Select
                            v-model="filters.companyId"
                            :options="filterOptions.companies"
                            option-label="label"
                            option-value="value"
                            placeholder="Semua Company"
                            filter
                            show-clear
                            class="h-11 w-full"
                            @change="load(true)"
                        />
                        <Select
                            v-model="filters.directorateId"
                            :options="filterOptions.directorates"
                            option-label="label"
                            option-value="value"
                            placeholder="Semua Directorate"
                            filter
                            show-clear
                            class="h-11 w-full"
                            @change="load(true)"
                        />
                        <Select
                            v-model="filters.departmentId"
                            :options="filterOptions.departments"
                            option-label="label"
                            option-value="value"
                            placeholder="Semua Department"
                            filter
                            show-clear
                            class="h-11 w-full"
                            @change="load(true)"
                        />
                        <Select
                            v-model="filters.jabatanId"
                            :options="filterOptions.jabatans"
                            option-label="label"
                            option-value="value"
                            placeholder="Semua Jabatan"
                            filter
                            show-clear
                            class="h-11 w-full"
                            @change="load(true)"
                        />
                        <Select
                            v-model="filters.etnisId"
                            :options="filterOptions.etnis"
                            option-label="label"
                            option-value="value"
                            placeholder="Semua Etnis"
                            filter
                            show-clear
                            class="h-11 w-full"
                            @change="load(true)"
                        />
                        <Button label="Reset filter" icon="pi pi-filter-slash" severity="secondary" text @click="resetFilters" />
                    </div>
                </template>
            </Card>

            <Card class="overflow-hidden border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                <template #title>
                    <div class="flex flex-col gap-3 px-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Daftar Karyawan</h2>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">
                                {{ karyawans.total.toLocaleString("id-ID") }} data ditemukan
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-400">Per halaman</span>
                            <Select
                                v-model="filters.perPage"
                                :options="filterOptions.perPage"
                                class="w-24 min-w-24"
                                @change="load(true)"
                            />
                        </div>
                    </div>
                </template>
                <template #content>
                    <DataTable
                        :value="karyawans.data"
                        :loading="loading"
                        :paginator="true"
                        :rows="karyawans.per_page"
                        :total-records="karyawans.total"
                        :first="(karyawans.current_page - 1) * karyawans.per_page"
                        :lazy="true"
                        :sort-field="filters.sortField"
                        :sort-order="filters.sortDirection === 'asc' ? 1 : -1"
                        removable-sort
                        scrollable
                        scroll-height="620px"
                        class="text-sm"
                        @page="onPage"
                        @sort="onSort"
                    >
                        <template #empty>
                            <div class="py-12 text-center">
                                <i class="pi pi-inbox text-3xl text-slate-300" />
                                <h3 class="mt-3 text-sm font-bold text-slate-900 dark:text-white">Belum ada data karyawan</h3>
                                <p class="mt-1 text-xs text-slate-400">Coba ubah filter atau tambahkan karyawan baru.</p>
                            </div>
                        </template>

                        <Column field="nip" header="NIP" sortable frozen align-frozen="left" style="width: 110px; min-width: 110px">
                            <template #body="{ data }">
                                <Link :href="route('admin.employees.edit', data.uuid)" class="font-semibold text-red-600 hover:text-red-700 dark:text-red-300">
                                    {{ data.nip }}
                                </Link>
                            </template>
                        </Column>
                        <Column field="nama" header="Nama" sortable frozen align-frozen="left" style="width: 230px; min-width: 230px">
                            <template #body="{ data }">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-slate-900 dark:text-white">{{ data.nama }}</p>
                                    <p class="truncate text-xs text-slate-400">{{ data.email || "-" }}</p>
                                </div>
                            </template>
                        </Column>
                        <Column field="job_grade_id" header="Job Grade" sortable style="min-width: 140px">
                            <template #body="{ data }">
                                <div v-if="data.job_grade">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100">{{ data.job_grade.grade }}</p>
                                    <p class="text-xs text-slate-400">{{ data.job_grade.name }}</p>
                                </div>
                                <span v-else>-</span>
                            </template>
                        </Column>
                        <Column field="company_id" header="Company" sortable style="min-width: 130px">
                            <template #body="{ data }">{{ data.company || "-" }}</template>
                        </Column>
                        <Column field="directorate_id" header="Directorate" sortable style="min-width: 170px">
                            <template #body="{ data }">{{ data.directorate || "-" }}</template>
                        </Column>
                        <Column field="department_id" header="Department" sortable style="min-width: 150px">
                            <template #body="{ data }">{{ data.department || "-" }}</template>
                        </Column>
                        <Column field="jabatan_id" header="Jabatan" sortable style="min-width: 150px">
                            <template #body="{ data }">{{ data.jabatan || "-" }}</template>
                        </Column>
                        <Column header="Etnis" style="min-width: 100px">
                            <template #body="{ data }">{{ data.etnis || "-" }}</template>
                        </Column>
                        <Column field="status_karyawan" header="Status" sortable style="min-width: 115px">
                            <template #body="{ data }">
                                <Tag :severity="data.status_active ? 'success' : 'danger'" class="whitespace-nowrap">
                                    {{ data.status_label || "-" }}
                                </Tag>
                            </template>
                        </Column>
                        <Column header="Lama Bekerja" style="min-width: 120px">
                            <template #body="{ data }">{{ data.lama_bekerja }}</template>
                        </Column>
                        <Column field="metode_penggajian" header="Metode" sortable style="min-width: 120px">
                            <template #body="{ data }">{{ data.metode_penggajian || "-" }}</template>
                        </Column>
                        <Column field="gaji_pokok" header="Gaji Pokok" sortable style="min-width: 145px">
                            <template #body="{ data }">{{ data.salary_masked ? "" : (data.gaji_pokok == null ? "-" : currency.format(data.gaji_pokok)) }}</template>
                        </Column>
                        <Column field="gaji_bpjs" header="Gaji BPJS" sortable style="min-width: 130px">
                            <template #body="{ data }">{{ data.salary_masked ? "" : (data.gaji_bpjs == null ? "-" : currency.format(data.gaji_bpjs)) }}</template>
                        </Column>
                        <Column field="gaji_overtime" header="Overtime" sortable style="min-width: 130px">
                            <template #body="{ data }">{{ data.salary_masked ? "" : (data.gaji_overtime == null ? "-" : currency.format(data.gaji_overtime)) }}</template>
                        </Column>
                        <Column field="tanggal_update" header="Last Adjust" sortable style="min-width: 125px">
                            <template #body="{ data }">{{ formatDate(data.tanggal_update) }}</template>
                        </Column>
                        <Column field="iuran_air" header="Iuran Air" sortable style="min-width: 125px">
                            <template #body="{ data }">{{ data.salary_masked ? "" : (data.iuran_air == null ? "-" : currency.format(data.iuran_air)) }}</template>
                        </Column>
                        <Column field="iuran_locker" header="Iuran Locker" sortable style="min-width: 135px">
                            <template #body="{ data }">{{ data.salary_masked ? "" : (data.iuran_locker == null ? "-" : currency.format(data.iuran_locker)) }}</template>
                        </Column>
                        <Column header="Aksi" frozen align-frozen="right" style="min-width: 110px">
                            <template #body="{ data }">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="route('admin.employees.edit', data.uuid)">
                                        <Button icon="pi pi-pencil" text rounded severity="secondary" aria-label="Edit karyawan" />
                                    </Link>
                                    <Button
                                        icon="pi pi-trash"
                                        text
                                        rounded
                                        severity="danger"
                                        aria-label="Hapus karyawan"
                                        @click="deleteEmployee(data)"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>

        <Dialog
            v-model:visible="specialExportDialog"
            modal
            header="Export Data Karyawan Khusus"
            class="w-full max-w-lg"
        >
            <div class="space-y-5">
                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">
                    Karyawan PKWT, PKWTT, dan Dirumahkan selalu disertakan. Data Resigned dan Blacklist mengikuti periode mulai yang dipilih.
                </p>

                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                    <Checkbox v-model="specialExport.semuaData" binary />
                    <span>
                        <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">Semua data</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">
                            Sertakan seluruh karyawan Resigned dan Blacklist tanpa batas tanggal.
                        </span>
                    </span>
                </label>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Mulai Bulan">
                        <Select
                            v-model="specialExport.bulan"
                            :options="months"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                            :disabled="specialExport.semuaData"
                        />
                    </FormField>
                    <FormField label="Tahun">
                        <Select
                            v-model="specialExport.tahun"
                            :options="years"
                            class="w-full"
                            :disabled="specialExport.semuaData"
                        />
                    </FormField>
                </div>

                <div class="flex justify-end gap-2">
                    <Button label="Batal" severity="secondary" text @click="specialExportDialog = false" />
                    <Button label="Download Excel" icon="pi pi-file-excel" severity="success" @click="submitSpecialExport" />
                </div>
            </div>
        </Dialog>
    </AdminLayout>
</template>
