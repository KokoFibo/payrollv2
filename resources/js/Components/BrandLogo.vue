<script setup>
import { usePage } from "@inertiajs/vue3";

defineProps({
    compact: { type: Boolean, default: false },
    inverted: { type: Boolean, default: false },
});

const page = usePage();
const companyName = page.props.app?.companyName || "Payroll Workspace";
</script>

<template>
    <div class="flex min-w-0 items-center gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm dark:border-slate-700 dark:bg-slate-950">
            <img src="/images/payroll-logo.png" alt="Payroll logo" class="h-full w-full object-contain">
        </span>
        <span v-if="!compact" class="min-w-0">
            <span
                class="block truncate text-sm font-extrabold tracking-[-0.02em]"
                :class="inverted ? 'text-white' : 'text-slate-950 dark:text-white'"
            >
                {{ companyName }}
            </span>
            <span
                class="mt-0.5 block truncate text-[10px] font-bold uppercase tracking-[0.16em]"
                :class="inverted ? 'text-white/55' : 'text-slate-400 dark:text-slate-500'"
            >
                People & payroll
            </span>
        </span>
    </div>
</template>
