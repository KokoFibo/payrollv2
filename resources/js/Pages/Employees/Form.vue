<script setup>
import { computed, reactive, ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageHeader from "@/Components/Admin/PageHeader.vue";
import FormField from "@/Components/Admin/FormField.vue";

const props = defineProps({
    employee: { type: Object, default: null },
    salaryLocked: { type: Boolean, default: false },
    options: { type: Object, required: true },
});

const editing = computed(() => Boolean(props.employee?.id));
const section = ref("pribadi");

const defaults = {
    nama: "",
    email: "",
    hp: "",
    telepon: "",
    tempat_lahir: "",
    tanggal_lahir: "",
    gender: "",
    status_pernikahan: "",
    golongan_darah: "",
    agama: "",
    etnis_id: null,
    jenis_identitas: "KTP",
    no_identitas: "",
    alamat_identitas: "",
    alamat_tinggal: "",
    status_karyawan: "PKWT",
    tanggal_bergabung: "",
    tanggal_resigned: "",
    tanggal_blacklist: "",
    company_id: null,
    directorate_id: null,
    department_id: null,
    jabatan_id: null,
    job_grade_id: null,
    nama_bank: "",
    nomor_rekening: "",
    metode_penggajian: "Bulanan",
    gaji_pokok: null,
    gaji_tetap: null,
    gaji_overtime: null,
    gaji_shift_malam_satpam: null,
    bonus: null,
    tunjangan_jabatan: null,
    tunjangan_bahasa: null,
    tunjangan_skill: null,
    tunjangan_lembur_sabtu: null,
    tunjangan_lama_kerja: null,
    tunjangan_housing: null,
    iuran_air: 0,
    denda: null,
    iuran_locker: null,
    gaji_bpjs: null,
    potongan_jht: false,
    potongan_jp: false,
    potongan_jkk: false,
    potongan_jkm: false,
    potongan_kesehatan: false,
    no_npwp: "",
    ptkp: "",
    kontak_darurat: "",
    kontak_darurat2: "",
    hp1: "",
    hp2: "",
    hubungan1: "",
    hubungan2: "",
    tanggungan: null,
    pendidikan: "",
    jurusan: "",
    nama_kampus: "",
};

const initialData = reactive({
    ...defaults,
    ...(props.employee || {}),
});

const form = useForm({ ...initialData });

const tabs = [
    { value: "pribadi", label: "Data Pribadi", icon: "pi pi-user" },
    { value: "identitas", label: "Identitas", icon: "pi pi-id-card" },
    { value: "kepegawaian", label: "Kepegawaian", icon: "pi pi-briefcase" },
    { value: "payroll", label: "Payroll", icon: "pi pi-wallet" },
];

const visibleTabs = computed(() => props.salaryLocked
    ? tabs.filter((tab) => tab.value !== "payroll")
    : tabs);

const allowanceFields = [
    ["bonus", "Bonus"],
    ["tunjangan_jabatan", "Tunjangan Jabatan"],
    ["tunjangan_bahasa", "Tunjangan Bahasa"],
    ["tunjangan_skill", "Tunjangan Skill"],
    ["tunjangan_lembur_sabtu", "Tunjangan Lembur Sabtu"],
    ["tunjangan_lama_kerja", "Tunjangan Lama Kerja"],
    ["tunjangan_housing", "Tunjangan Housing"],
];

const deductionFields = [
    ["potongan_jht", "Potongan JHT", "Jaminan Hari Tua"],
    ["potongan_jp", "Potongan JP", "Jaminan Pensiun"],
    ["potongan_jkk", "Potongan JKK", "Jaminan Kecelakaan Kerja"],
    ["potongan_jkm", "Potongan JKM", "Jaminan Kematian"],
    ["potongan_kesehatan", "Potongan Kesehatan", "Jaminan kesehatan"],
];

function submit() {
    if (editing.value) {
        form.put(route("admin.employees.update", props.employee.id));

        return;
    }

    form.post(route("admin.employees.store"));
}
</script>

<template>
    <Head :title="editing ? 'Edit Karyawan' : 'Tambah Karyawan'" />

    <AdminLayout>
        <form class="space-y-6" @submit.prevent="submit">
            <PageHeader
                :title="editing ? 'Edit Karyawan' : 'Tambah Karyawan'"
                description="Lengkapi data personal, penempatan, dan komponen penggajian karyawan."
                eyebrow="Employee profile"
            >
                <template #actions>
                    <Link :href="route('admin.employees.index')">
                        <Button label="Kembali" icon="pi pi-arrow-left" severity="secondary" outlined />
                    </Link>
                </template>
            </PageHeader>

            <Message v-if="Object.keys(form.errors).length" severity="error" :closable="false">
                Periksa kembali kolom bertanda merah sebelum menyimpan.
            </Message>

            <Message v-if="salaryLocked" severity="warn" :closable="false">
                Data payroll karyawan ini dikunci karena gaji pokoknya melebihi batas akses Admin. Data pribadi dan kepegawaian tetap dapat diedit.
            </Message>

            <nav class="grid grid-cols-2 gap-2 rounded-2xl border border-slate-200/80 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900 md:grid-cols-4" aria-label="Tahapan formulir">
                <Button
                    v-for="tab in visibleTabs"
                    :key="tab.value"
                    type="button"
                    :label="tab.label"
                    :icon="tab.icon"
                    class="justify-start"
                    :severity="section === tab.value ? 'primary' : 'secondary'"
                    :text="section !== tab.value"
                    @click="section = tab.value"
                />
            </nav>

            <section v-show="section === 'pribadi'" class="space-y-6">
                <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <template #title>
                        <div class="px-1">
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Data Pribadi</h2>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Informasi dasar, kelahiran, pendidikan, dan kontak darurat.</p>
                        </div>
                    </template>
                    <template #content>
                        <div class="space-y-7">
                            <div>
                                <h3 class="mb-4 text-sm font-bold text-slate-800 dark:text-slate-100">Informasi dasar</h3>
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                    <FormField label="Nama Lengkap" :error="form.errors.nama" required class="lg:col-span-2">
                                        <InputText v-model="form.nama" placeholder="Nama lengkap karyawan" class="w-full" />
                                    </FormField>
                                    <FormField label="Email" :error="form.errors.email">
                                        <InputText v-model="form.email" type="email" placeholder="nama@perusahaan.com" class="w-full" />
                                    </FormField>
                                    <FormField label="No. HP" :error="form.errors.hp">
                                        <InputText v-model="form.hp" placeholder="08xxxxxxxxxx" class="w-full" />
                                    </FormField>
                                    <FormField label="Telepon" :error="form.errors.telepon">
                                        <InputText v-model="form.telepon" placeholder="Nomor telepon" class="w-full" />
                                    </FormField>
                                </div>
                            </div>

                            <Divider />

                            <div>
                                <h3 class="mb-4 text-sm font-bold text-slate-800 dark:text-slate-100">Kelahiran & gender</h3>
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                    <FormField label="Tempat Lahir" :error="form.errors.tempat_lahir" required>
                                        <InputText v-model="form.tempat_lahir" placeholder="Kota kelahiran" class="w-full" />
                                    </FormField>
                                    <FormField label="Tanggal Lahir" :error="form.errors.tanggal_lahir" required>
                                        <InputText v-model="form.tanggal_lahir" type="date" class="w-full" />
                                    </FormField>
                                    <FormField label="Gender" :error="form.errors.gender" required>
                                        <Select v-model="form.gender" :options="options.genders" option-label="label" option-value="value" placeholder="Pilih gender" class="w-full" />
                                    </FormField>
                                </div>
                            </div>

                            <Divider />

                            <div>
                                <h3 class="mb-4 text-sm font-bold text-slate-800 dark:text-slate-100">Profil pribadi & pendidikan</h3>
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                    <FormField label="Status Pernikahan" :error="form.errors.status_pernikahan">
                                        <Select v-model="form.status_pernikahan" :options="options.maritalStatuses" option-label="label" option-value="value" placeholder="Pilih status" class="w-full" />
                                    </FormField>
                                    <FormField label="Agama" :error="form.errors.agama">
                                        <Select v-model="form.agama" :options="options.religions" option-label="label" option-value="value" placeholder="Pilih agama" class="w-full" />
                                    </FormField>
                                    <FormField label="Etnis" :error="form.errors.etnis_id" required>
                                        <Select v-model="form.etnis_id" :options="options.etnis" option-label="label" option-value="value" filter placeholder="Pilih etnis" class="w-full" />
                                    </FormField>
                                    <FormField label="Golongan Darah" :error="form.errors.golongan_darah">
                                        <Select v-model="form.golongan_darah" :options="options.bloodTypes" option-label="label" option-value="value" placeholder="Pilih golongan" class="w-full" />
                                    </FormField>
                                    <FormField label="Pendidikan Terakhir" :error="form.errors.pendidikan" required>
                                        <Select v-model="form.pendidikan" :options="options.educations" option-label="label" option-value="value" placeholder="Pilih pendidikan" class="w-full" />
                                    </FormField>
                                    <FormField label="Nama Sekolah/Kampus" :error="form.errors.nama_kampus">
                                        <InputText v-model="form.nama_kampus" placeholder="Nama sekolah atau kampus" class="w-full" />
                                    </FormField>
                                    <FormField
                                        v-if="!['Tidak Bersekolah', 'SD', 'SMP'].includes(form.pendidikan)"
                                        label="Jurusan"
                                        :error="form.errors.jurusan"
                                    >
                                        <InputText v-model="form.jurusan" placeholder="Jurusan pendidikan" class="w-full" />
                                    </FormField>
                                </div>
                            </div>

                            <Divider />

                            <div>
                                <h3 class="mb-4 text-sm font-bold text-slate-800 dark:text-slate-100">Kontak darurat</h3>
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                    <FormField label="Nama Kontak Darurat 1" :error="form.errors.kontak_darurat">
                                        <InputText v-model="form.kontak_darurat" placeholder="Nama kontak" class="w-full" />
                                    </FormField>
                                    <FormField label="HP Kontak Darurat 1" :error="form.errors.hp1">
                                        <InputText v-model="form.hp1" placeholder="08xxxxxxxxxx" class="w-full" />
                                    </FormField>
                                    <FormField label="Hubungan 1" :error="form.errors.hubungan1">
                                        <InputText v-model="form.hubungan1" placeholder="Contoh: Orang tua" class="w-full" />
                                    </FormField>
                                    <FormField label="Nama Kontak Darurat 2" :error="form.errors.kontak_darurat2">
                                        <InputText v-model="form.kontak_darurat2" placeholder="Nama kontak" class="w-full" />
                                    </FormField>
                                    <FormField label="HP Kontak Darurat 2" :error="form.errors.hp2">
                                        <InputText v-model="form.hp2" placeholder="08xxxxxxxxxx" class="w-full" />
                                    </FormField>
                                    <FormField label="Hubungan 2" :error="form.errors.hubungan2">
                                        <InputText v-model="form.hubungan2" placeholder="Contoh: Saudara" class="w-full" />
                                    </FormField>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </section>

            <section v-show="section === 'identitas'">
                <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <template #title>
                        <div class="px-1">
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Identitas</h2>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Dokumen identitas dan alamat karyawan.</p>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <FormField label="Jenis Identitas" :error="form.errors.jenis_identitas" required>
                                <Select v-model="form.jenis_identitas" :options="options.identityTypes" option-label="label" option-value="value" class="w-full" />
                            </FormField>
                            <FormField label="Nomor Identitas" :error="form.errors.no_identitas" required>
                                <InputText v-model="form.no_identitas" placeholder="Nomor KTP / SIM / Paspor" class="w-full" />
                            </FormField>
                            <FormField label="Alamat Sesuai Identitas" :error="form.errors.alamat_identitas" required>
                                <Textarea v-model="form.alamat_identitas" rows="4" placeholder="Alamat pada dokumen identitas" class="w-full" />
                            </FormField>
                            <FormField label="Alamat Tinggal" :error="form.errors.alamat_tinggal" required>
                                <Textarea v-model="form.alamat_tinggal" rows="4" placeholder="Alamat domisili saat ini" class="w-full" />
                            </FormField>
                        </div>
                    </template>
                </Card>
            </section>

            <section v-show="section === 'kepegawaian'">
                <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <template #title>
                        <div class="px-1">
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Data Kepegawaian</h2>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Status kontrak, struktur organisasi, dan rekening payroll.</p>
                        </div>
                    </template>
                    <template #content>
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            <FormField label="Status Karyawan" :error="form.errors.status_karyawan" required>
                                <Select v-model="form.status_karyawan" :options="options.statuses" option-label="label" option-value="value" class="w-full" />
                            </FormField>
                            <FormField label="Tanggal Bergabung" :error="form.errors.tanggal_bergabung" required>
                                <InputText v-model="form.tanggal_bergabung" type="date" class="w-full" />
                            </FormField>
                            <FormField label="Company" :error="form.errors.company_id" required>
                                <Select v-model="form.company_id" :options="options.companies" option-label="label" option-value="value" filter class="w-full" />
                            </FormField>
                            <FormField label="Directorate" :error="form.errors.directorate_id" required>
                                <Select v-model="form.directorate_id" :options="options.directorates" option-label="label" option-value="value" filter class="w-full" />
                            </FormField>
                            <FormField label="Department" :error="form.errors.department_id" required>
                                <Select v-model="form.department_id" :options="options.departments" option-label="label" option-value="value" filter class="w-full" />
                            </FormField>
                            <FormField label="Jabatan" :error="form.errors.jabatan_id" required>
                                <Select v-model="form.jabatan_id" :options="options.jabatans" option-label="label" option-value="value" filter class="w-full" />
                            </FormField>
                            <FormField label="Job Grade" :error="form.errors.job_grade_id">
                                <Select v-model="form.job_grade_id" :options="options.jobGrades" option-label="label" option-value="value" filter show-clear class="w-full" />
                            </FormField>
                            <FormField label="Nama Bank" :error="form.errors.nama_bank">
                                <Select v-model="form.nama_bank" :options="options.banks" option-label="label" option-value="value" show-clear class="w-full" />
                            </FormField>
                            <FormField label="Nomor Rekening" :error="form.errors.nomor_rekening">
                                <InputText v-model="form.nomor_rekening" placeholder="Nomor rekening" class="w-full" />
                            </FormField>
                            <FormField v-if="form.status_karyawan === 'Resigned'" label="Tanggal Resigned" :error="form.errors.tanggal_resigned" required>
                                <InputText v-model="form.tanggal_resigned" type="date" class="w-full" />
                            </FormField>
                            <FormField v-if="form.status_karyawan === 'Blacklist'" label="Tanggal Blacklist" :error="form.errors.tanggal_blacklist" required>
                                <InputText v-model="form.tanggal_blacklist" type="date" class="w-full" />
                            </FormField>
                        </div>
                    </template>
                </Card>
            </section>

            <section v-if="!salaryLocked" v-show="section === 'payroll'" class="space-y-6">
                <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <template #title>
                        <div class="px-1">
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Data Payroll</h2>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">Gaji, tunjangan, iuran, dan potongan karyawan.</p>
                        </div>
                    </template>
                    <template #content>
                        <div class="space-y-7">
                            <div>
                                <h3 class="mb-4 text-sm font-bold text-slate-800 dark:text-slate-100">Komponen utama</h3>
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                    <FormField label="Metode Penggajian" :error="form.errors.metode_penggajian" required>
                                        <InputText v-model="form.metode_penggajian" placeholder="Contoh: Bulanan" class="w-full" />
                                    </FormField>
                                    <FormField label="Gaji Pokok" :error="form.errors.gaji_pokok" required hint="Nominal Rupiah.">
                                        <InputNumber v-model="form.gaji_pokok" mode="currency" currency="IDR" locale="id-ID" :min="0" class="w-full" input-class="w-full" />
                                    </FormField>
                                    <FormField label="Gaji Tetap" :error="form.errors.gaji_tetap" hint="Tambahan di luar gaji pokok.">
                                        <InputNumber v-model="form.gaji_tetap" mode="currency" currency="IDR" locale="id-ID" :min="0" class="w-full" input-class="w-full" />
                                    </FormField>
                                    <FormField label="Gaji Overtime" :error="form.errors.gaji_overtime" required>
                                        <InputNumber v-model="form.gaji_overtime" mode="currency" currency="IDR" locale="id-ID" :min="0" class="w-full" input-class="w-full" />
                                    </FormField>
                                    <FormField label="Gaji Shift Malam Satpam" :error="form.errors.gaji_shift_malam_satpam">
                                        <InputNumber v-model="form.gaji_shift_malam_satpam" mode="currency" currency="IDR" locale="id-ID" :min="0" class="w-full" input-class="w-full" />
                                    </FormField>
                                    <FormField label="Nomor NPWP" :error="form.errors.no_npwp">
                                        <InputText v-model="form.no_npwp" placeholder="Nomor NPWP" class="w-full" />
                                    </FormField>
                                    <FormField label="PTKP" :error="form.errors.ptkp">
                                        <Select v-model="form.ptkp" :options="options.ptkp" option-label="label" option-value="value" show-clear class="w-full" />
                                    </FormField>
                                </div>
                            </div>

                            <Divider />

                            <div>
                                <h3 class="mb-4 text-sm font-bold text-slate-800 dark:text-slate-100">Tunjangan</h3>
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                    <FormField
                                        v-for="[field, label] in allowanceFields"
                                        :key="field"
                                        :label="label"
                                        :error="form.errors[field]"
                                    >
                                        <InputNumber v-model="form[field]" mode="currency" currency="IDR" locale="id-ID" :min="0" class="w-full" input-class="w-full" />
                                    </FormField>
                                </div>
                            </div>

                            <Divider />

                            <div>
                                <h3 class="mb-4 text-sm font-bold text-slate-800 dark:text-slate-100">Potongan & BPJS</h3>
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                    <FormField label="Iuran Air" :error="form.errors.iuran_air" required>
                                        <InputNumber v-model="form.iuran_air" mode="currency" currency="IDR" locale="id-ID" :min="0" class="w-full" input-class="w-full" />
                                    </FormField>
                                    <FormField label="Denda" :error="form.errors.denda">
                                        <InputNumber v-model="form.denda" mode="currency" currency="IDR" locale="id-ID" :min="0" class="w-full" input-class="w-full" />
                                    </FormField>
                                    <FormField label="Iuran Locker" :error="form.errors.iuran_locker">
                                        <InputNumber v-model="form.iuran_locker" mode="currency" currency="IDR" locale="id-ID" :min="0" class="w-full" input-class="w-full" />
                                    </FormField>
                                    <FormField label="Gaji BPJS" :error="form.errors.gaji_bpjs">
                                        <InputNumber v-model="form.gaji_bpjs" mode="currency" currency="IDR" locale="id-ID" :min="0" class="w-full" input-class="w-full" />
                                    </FormField>
                                    <FormField label="Jumlah Tanggungan BPJS" :error="form.errors.tanggungan">
                                        <InputNumber v-model="form.tanggungan" :min="0" class="w-full" input-class="w-full" />
                                    </FormField>
                                </div>
                            </div>

                            <Divider />

                            <div>
                                <h3 class="mb-4 text-sm font-bold text-slate-800 dark:text-slate-100">Jenis potongan</h3>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    <label
                                        v-for="[field, label, description] in deductionFields"
                                        :key="field"
                                        class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200/80 p-4 transition hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-950/40"
                                    >
                                        <Checkbox v-model="form[field]" binary />
                                        <span>
                                            <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">{{ label }}</span>
                                            <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Aktifkan {{ description.toLowerCase() }}.</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </section>

            <div class="sticky bottom-4 z-10 flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white/95 p-3 shadow-xl shadow-slate-950/5 backdrop-blur sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-900/95">
                <p class="px-2 text-xs text-slate-500 dark:text-slate-400">
                    Data wajib ditandai bintang merah. Nominal disimpan dalam Rupiah.
                </p>
                <div class="flex items-center justify-end gap-2">
                    <Link :href="route('admin.employees.index')">
                        <Button type="button" label="Batal" severity="secondary" text />
                    </Link>
                    <Button
                        type="submit"
                        :label="editing ? 'Simpan Perubahan' : 'Simpan Karyawan'"
                        icon="pi pi-check"
                        :loading="form.processing"
                    />
                </div>
            </div>
        </form>
    </AdminLayout>
</template>
