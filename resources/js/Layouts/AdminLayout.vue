<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import SidebarNav from "@/Components/Admin/SidebarNav.vue";
import BrandLogo from "@/Components/BrandLogo.vue";

const page = usePage();
const toast = useToast();
const mobileOpen = ref(false);
const collapsed = ref(localStorage.getItem("payroll-sidebar-collapsed") === "true");
const dark = ref(localStorage.getItem("payroll-theme") === "dark");
const userMenu = ref(null);

const currentTitle = computed(() => {
    const routeName = route().current();
    const titles = {
        "admin.dashboard": "Dashboard",
        "admin.guide.index": "Panduan",
        "admin.super-admin-dashboard.index": "Super Admin",
        "admin.employees.index": "Employees",
        "admin.employees.create": "Tambah Karyawan",
        "admin.employees.edit": "Edit Karyawan",
        "admin.attendance.index": "Attendance",
        "admin.payroll.index": "Payroll",
        "admin.payroll.show": "Payroll Detail",
        "admin.reports.index": "Reports",
        "admin.settings.index": "Settings",
        "admin.users.index": "User Management",
        "admin.developer-settings.index": "Developer Settings",
        "profile.edit": "Profile",
    };

    return titles[routeName] ?? "Payroll";
});

const user = computed(() => page.props.auth?.user ?? {});
const initial = computed(() => (user.value.name || "U").charAt(0).toUpperCase());
const roleName = computed(() => page.props.auth?.roleName || "Administrator");
const companyName = computed(() => page.props.app?.companyName || "Payroll Workspace");

const userMenuItems = computed(() => [
    {
        label: "Profile",
        icon: "pi pi-user",
        command: () => router.visit(route("profile.edit")),
    },
    {
        separator: true,
    },
    {
        label: "Log Out",
        icon: "pi pi-sign-out",
        command: () => router.post(route("logout")),
    },
]);

function applyTheme() {
    document.documentElement.classList.toggle("app-dark", dark.value);
    localStorage.setItem("payroll-theme", dark.value ? "dark" : "light");
}

function toggleTheme() {
    dark.value = !dark.value;
    applyTheme();
}

function toggleSidebar() {
    collapsed.value = !collapsed.value;
    localStorage.setItem("payroll-sidebar-collapsed", String(collapsed.value));
}

function toggleUserMenu(event) {
    userMenu.value.toggle(event);
}

function showFlashToast(toastData) {
    if (!toastData) {
        return;
    }

    toast.add({
        severity: toastData.type === "error" ? "error" : toastData.type === "warning" ? "warn" : "success",
        summary: toastData.title,
        detail: toastData.message,
        life: 4200,
    });
}

onMounted(applyTheme);

watch(
    () => page.props.flash?.toast,
    (toastData) => showFlashToast(toastData),
    { immediate: true },
);
</script>

<template>
    <div class="min-h-screen">
        <Toast position="top-right" />
        <ConfirmDialog />

        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-30 bg-slate-950/40 backdrop-blur-sm lg:hidden"
            @click="mobileOpen = false"
        />

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-[220px] flex-col border-r border-slate-200/80 bg-white shadow-xl shadow-slate-950/5 transition-[width,transform] duration-300 dark:border-slate-800 dark:bg-slate-900 lg:translate-x-0"
            :class="[
                collapsed ? 'lg:w-16' : 'lg:w-[220px]',
                mobileOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
            aria-label="Sidebar admin"
        >
            <div class="flex h-16 shrink-0 items-center border-b border-slate-200/80 px-4 dark:border-slate-800" :class="collapsed ? 'lg:justify-center lg:px-0' : 'justify-between'">
                <Link :href="route('admin.dashboard')" class="min-w-0" aria-label="Buka dashboard">
                    <BrandLogo :compact="collapsed" />
                </Link>
                <Button
                    icon="pi pi-times"
                    text
                    rounded
                    severity="secondary"
                    class="lg:hidden"
                    aria-label="Tutup sidebar"
                    @click="mobileOpen = false"
                />
            </div>

            <SidebarNav :collapsed="collapsed" @navigate="mobileOpen = false" />

            <div class="flex shrink-0 items-center gap-3 border-t border-slate-200/80 px-4 py-4 dark:border-slate-800" :class="collapsed ? 'lg:justify-center lg:px-0' : ''">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <i class="pi pi-verified text-sm" />
                </span>
                <span v-if="!collapsed" class="min-w-0">
                    <span class="block truncate text-xs font-semibold text-slate-600 dark:text-slate-300">{{ companyName }}</span>
                    <span class="block text-[11px] text-slate-400 dark:text-slate-500">Secure workspace</span>
                </span>
            </div>
        </aside>

        <div class="min-h-screen min-w-0 transition-[padding] duration-300" :class="collapsed ? 'lg:pl-16' : 'lg:pl-[220px]'">
            <header class="sticky top-0 z-20 h-16 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/90">
                <div class="flex h-full items-center gap-2 px-4 sm:px-6 lg:px-8">
                    <Button
                        icon="pi pi-bars"
                        text
                        rounded
                        severity="secondary"
                        class="lg:hidden"
                        aria-label="Buka sidebar"
                        @click="mobileOpen = true"
                    />
                    <Button
                        :icon="collapsed ? 'pi pi-angle-right' : 'pi pi-angle-left'"
                        text
                        rounded
                        severity="secondary"
                        class="hidden lg:inline-flex"
                        :aria-label="collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'"
                        @click="toggleSidebar"
                    />

                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="hidden text-[13px] font-medium text-slate-400 sm:inline dark:text-slate-500">{{ companyName }}</span>
                        <i class="pi pi-angle-right hidden text-[10px] text-slate-300 sm:inline dark:text-slate-700" />
                        <span class="truncate text-[13px] font-semibold text-slate-700 dark:text-slate-200">{{ currentTitle }}</span>
                    </div>

                    <div class="ml-auto flex items-center gap-1.5">
                        <Button
                            :icon="dark ? 'pi pi-sun' : 'pi pi-moon'"
                            text
                            rounded
                            severity="secondary"
                            aria-label="Ganti tema"
                            @click="toggleTheme"
                        />
                        <span class="mx-1 hidden h-6 w-px bg-slate-200 sm:block dark:bg-slate-800" />
                        <Button text rounded class="p-1" aria-haspopup="true" aria-controls="user-menu" @click="toggleUserMenu">
                            <span class="flex items-center gap-2">
                                <Avatar :label="initial" shape="square" class="bg-slate-950 text-white dark:bg-white dark:text-slate-950" />
                                <span class="hidden text-left sm:block">
                                    <span class="block max-w-36 truncate text-[13px] font-semibold text-slate-800 dark:text-slate-100">{{ user.name }}</span>
                                    <span class="block text-[11px] font-medium text-slate-400">{{ roleName }}</span>
                                </span>
                                <i class="pi pi-chevron-down hidden text-[10px] text-slate-400 sm:block" />
                            </span>
                        </Button>
                        <Menu
                            id="user-menu"
                            ref="userMenu"
                            :model="userMenuItems"
                            popup
                            :pt="{
                                itemLink: { class: 'py-1.5' },
                                itemIcon: { class: 'text-[10px]' },
                                itemLabel: { class: 'text-[11px] font-medium leading-none' },
                            }"
                        />
                    </div>
                </div>
            </header>

            <main class="mx-auto w-full max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <slot />
            </main>
        </div>
    </div>
</template>
