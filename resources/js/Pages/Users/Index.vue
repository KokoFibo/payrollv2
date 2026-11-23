<script setup>
import { reactive, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageHeader from "@/Components/Admin/PageHeader.vue";
import StatCard from "@/Components/Admin/StatCard.vue";
import FormField from "@/Components/Admin/FormField.vue";
import EmptyState from "@/Components/Admin/EmptyState.vue";

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, required: true },
    roles: { type: Array, required: true },
    stats: { type: Object, required: true },
});

const confirm = useConfirm();
const dialogVisible = ref(false);
const editing = ref(null);
const filters = reactive({ ...props.filters });
const form = useForm({
    name: "",
    email: "",
    username: "",
    role: 5,
    language: "Id",
    password: "",
    password_confirmation: "",
});

function load() {
    router.get(route("admin.users.index"), {
        search: filters.search,
        role: filters.role || "",
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.role = 5;
    form.language = "Id";
    dialogVisible.value = true;
}

function openEdit(user) {
    editing.value = user;
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.username = user.username ?? "";
    form.role = user.role;
    form.language = user.language;
    form.password = "";
    form.password_confirmation = "";
    dialogVisible.value = true;
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogVisible.value = false;
        },
    };

    if (editing.value) {
        form.put(route("admin.users.update", editing.value.id), options);

        return;
    }

    form.post(route("admin.users.store"), options);
}

function removeUser(user) {
    confirm.require({
        message: `Hapus akun ${user.name} (${user.email})? Pengguna tersebut tidak akan dapat login lagi.`,
        header: "Hapus user?",
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Batal",
        acceptLabel: "Hapus",
        rejectClass: "p-button-secondary p-button-outlined",
        acceptClass: "p-button-danger",
        accept: () => router.delete(route("admin.users.destroy", user.id), { preserveScroll: true }),
    });
}

function onPage(event) {
    router.get(route("admin.users.index"), {
        ...filters,
        page: event.page + 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function roleSeverity(role) {
    return { 8: "danger", 7: "warn", 6: "info", 5: "success", 4: "secondary", 3: "secondary" }[role] || "secondary";
}
</script>

<template>
    <Head title="User Management" />

    <AdminLayout>
        <div class="space-y-6">
            <PageHeader
                title="User Management"
                description="Tambahkan dan kelola akun yang dapat mengakses sistem. Registrasi publik sudah dihapus."
                eyebrow="Access control"
            >
                <template #actions>
                    <Button label="Tambah User" icon="pi pi-user-plus" severity="danger" @click="openCreate" />
                </template>
            </PageHeader>

            <div class="grid gap-4 sm:grid-cols-3">
                <StatCard label="Total Users" :value="stats.total.toLocaleString('id-ID')" description="Akun terdaftar" icon="pi pi-users" tone="brand" />
                <StatCard label="Admin Access" :value="stats.admins.toLocaleString('id-ID')" description="Role Admin ke atas" icon="pi pi-shield" tone="sky" />
                <StatCard label="Privileged" :value="stats.privileged.toLocaleString('id-ID')" description="Super Admin dan Developer" icon="pi pi-key" tone="amber" />
            </div>

            <Card class="overflow-hidden border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                <template #title>
                    <div class="flex flex-col gap-3 px-1 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Akun pengguna</h2>
                            <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">{{ users.total.toLocaleString("id-ID") }} akun ditemukan</p>
                        </div>
                        <div class="grid w-full gap-2 sm:w-auto sm:grid-cols-2">
                            <InputText v-model="filters.search" placeholder="Cari nama atau email..." class="h-11" @change="load" />
                            <Select v-model="filters.role" :options="roles" option-label="label" option-value="value" placeholder="Semua role" show-clear class="h-11" @change="load" />
                        </div>
                    </div>
                </template>
                <template #content>
                    <DataTable :value="users.data" :paginator="true" :rows="users.per_page" :total-records="users.total" :first="(users.current_page - 1) * users.per_page" :lazy="true" @page="onPage">
                        <template #empty>
                            <EmptyState title="Belum ada user" description="Tambahkan akun internal pertama dari halaman ini." icon="pi pi-user-plus" />
                        </template>
                        <Column header="User" style="min-width: 240px">
                            <template #body="{ data }">
                                <div class="flex items-center gap-3">
                                    <Avatar :label="data.name.charAt(0).toUpperCase()" shape="circle" class="bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300" />
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="font-semibold text-slate-900 dark:text-white">{{ data.name }}</p>
                                            <Tag v-if="data.is_current_user" severity="info" class="text-[10px]">Anda</Tag>
                                        </div>
                                        <p class="text-xs text-slate-400">{{ data.email }}</p>
                                    </div>
                                </div>
                            </template>
                        </Column>
                        <Column header="Username" style="min-width: 110px">
                            <template #body="{ data }">{{ data.username || "-" }}</template>
                        </Column>
                        <Column header="Role" style="min-width: 140px">
                            <template #body="{ data }">
                                <Tag :severity="roleSeverity(data.role)">{{ data.role_name || "-" }}</Tag>
                            </template>
                        </Column>
                        <Column header="Status Email" style="min-width: 130px">
                            <template #body="{ data }">
                                <Tag :severity="data.email_verified ? 'success' : 'warn'">{{ data.email_verified ? "Verified" : "Unverified" }}</Tag>
                            </template>
                        </Column>
                        <Column field="language" header="Bahasa" style="min-width: 90px" />
                        <Column header="Aksi" style="width: 110px">
                            <template #body="{ data }">
                                <div class="flex justify-end gap-1">
                                    <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openEdit(data)" />
                                    <Button
                                        icon="pi pi-trash"
                                        text
                                        rounded
                                        severity="danger"
                                        :disabled="data.is_current_user"
                                        @click="removeUser(data)"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </template>
            </Card>
        </div>

        <Dialog v-model:visible="dialogVisible" :header="editing ? 'Edit user' : 'Tambah user'" modal class="w-full max-w-2xl">
            <form class="space-y-4" @submit.prevent="save">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Nama Lengkap" :error="form.errors.name" required>
                        <InputText v-model="form.name" class="w-full" autocomplete="name" />
                    </FormField>
                    <FormField label="Email" :error="form.errors.email" required>
                        <InputText v-model="form.email" type="email" class="w-full" autocomplete="email" />
                    </FormField>
                    <FormField label="Username" :error="form.errors.username">
                        <InputText v-model="form.username" class="w-full" placeholder="Opsional, berupa angka" />
                    </FormField>
                    <FormField label="Role" :error="form.errors.role" required>
                        <Select v-model="form.role" :options="roles" option-label="label" option-value="value" class="w-full" />
                    </FormField>
                    <FormField label="Bahasa" :error="form.errors.language" required>
                        <Select v-model="form.language" :options="[{ value: 'Id', label: 'Bahasa Indonesia' }, { value: 'En', label: 'English' }]" option-label="label" option-value="value" class="w-full" />
                    </FormField>
                    <FormField :label="editing ? 'Password Baru (Opsional)' : 'Password'" :error="form.errors.password" :required="!editing">
                        <InputText v-model="form.password" type="password" class="w-full" autocomplete="new-password" />
                    </FormField>
                    <FormField label="Konfirmasi Password" :error="form.errors.password_confirmation" :required="!editing">
                        <InputText v-model="form.password_confirmation" type="password" class="w-full" autocomplete="new-password" />
                    </FormField>
                </div>
                <Message severity="info" :closable="false">
                    Saat membuat user, email otomatis ditandai terverifikasi agar akun internal dapat langsung digunakan.
                </Message>
                <div class="flex justify-end gap-2">
                    <Button type="button" label="Batal" severity="secondary" text @click="dialogVisible = false" />
                    <Button type="submit" :label="editing ? 'Simpan perubahan' : 'Tambah user'" icon="pi pi-check" severity="danger" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </AdminLayout>
</template>
