<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: "-" },
    description: { type: String, default: "" },
    icon: { type: String, default: "pi pi-chart-line" },
    tone: { type: String, default: "brand" },
    loading: { type: Boolean, default: false },
});

const toneClasses = {
    brand: "bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200",
    emerald: "bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300",
    amber: "bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300",
    sky: "bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300",
    violet: "bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300",
};
</script>

<template>
    <Card class="h-full border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
        <template #content>
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-400 dark:text-slate-500">{{ label }}</p>
                    <Skeleton v-if="loading" width="7rem" height="2.25rem" class="mt-2" />
                    <p v-else class="mt-2 text-3xl font-extrabold tracking-[-0.04em] text-slate-950 dark:text-white">{{ value }}</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ description }}</p>
                </div>
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl" :class="toneClasses[tone]">
                    <i :class="icon" class="text-lg" />
                </span>
            </div>
        </template>
    </Card>
</template>
