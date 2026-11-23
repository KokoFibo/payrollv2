<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    icon: { type: String, default: "pi pi-lock" },
});
</script>

<template>
    <Card class="overflow-hidden border border-white/70 bg-white/90 shadow-[0_30px_80px_-30px_rgba(15,23,42,0.35)] backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/90">
        <template #content>
            <div class="p-1 sm:p-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300">
                    <i :class="icon" class="text-lg" />
                </span>
                <h1 class="mt-5 text-2xl font-extrabold tracking-[-0.035em] text-slate-950 dark:text-white">{{ title }}</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ description }}</p>
                <div class="mt-7">
                    <slot />
                </div>
            </div>
        </template>
    </Card>
</template>
