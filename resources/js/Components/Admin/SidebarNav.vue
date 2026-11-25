<script setup>
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";

defineProps({
    collapsed: { type: Boolean, default: false },
});

const emit = defineEmits(["navigate"]);
const page = usePage();

const navigation = computed(() => {
    const items = [{ label: "Dashboard", routeName: "admin.dashboard", icon: "pi pi-home" }];

    if (page.props.auth?.can?.superAdmin) {
        items.splice(1, 0, {
            label: "Super Admin",
            routeName: "admin.super-admin-dashboard.index",
            icon: "pi pi-shield",
        });
    }

    if (page.props.auth?.can?.managePayroll) {
        items.push(
            { label: "Employees", routeName: "admin.employees.index", icon: "pi pi-users" },
            { label: "Attendance", routeName: "admin.attendance.index", icon: "pi pi-clock" },
        );
    }

    if (page.props.auth?.can?.payrollReports) {
        items.push(
            { label: "Payroll", routeName: "admin.payroll.index", icon: "pi pi-wallet" },
            { label: "Reports", routeName: "admin.reports.index", icon: "pi pi-chart-bar" },
        );
    }

    if (page.props.auth?.can?.managePayroll) {
        items.push({ label: "Settings", routeName: "admin.settings.index", icon: "pi pi-cog" });
    }

    if (page.props.auth?.can?.developer) {
        items.push({
            label: "Developer",
            routeName: "admin.developer-settings.index",
            icon: "pi pi-code",
        });
    }

    if (page.props.auth?.can?.manageUsers) {
        items.splice(items.length - (page.props.auth?.can?.developer ? 1 : 0), 0, {
            label: "Users",
            routeName: "admin.users.index",
            icon: "pi pi-user-edit",
        });
    }

    return items;
});

const isActive = (routeName) => route().current(routeName);
</script>

<template>
    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Navigasi admin">
        <p v-if="!collapsed" class="mb-3 px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-600">
            Workspace
        </p>
        <Link
            v-for="item in navigation"
            :key="item.routeName"
            :href="route(item.routeName)"
            prefetch
            :class="[
                'group relative flex h-10 items-center rounded-xl text-[13px] font-semibold transition',
                collapsed ? 'justify-center px-0' : 'gap-3 px-3',
                isActive(item.routeName)
                    ? 'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-100 dark:bg-brand-500/10 dark:text-brand-100 dark:ring-brand-500/20'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white',
            ]"
            @click="emit('navigate')"
        >
            <span v-if="isActive(item.routeName)" class="absolute left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full bg-brand-600 dark:bg-brand-300" />
            <i :class="item.icon" class="text-base" />
            <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
        </Link>
    </nav>
</template>
