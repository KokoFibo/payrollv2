<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: "" },
    eyebrow: { type: String, default: "" },
});
</script>

<template>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p v-if="eyebrow" class="mb-2 text-[11px] font-bold uppercase tracking-[0.18em] text-brand-600 dark:text-brand-300">
                {{ eyebrow }}
            </p>
            <h1 class="text-2xl font-extrabold tracking-[-0.035em] text-slate-950 dark:text-white sm:text-3xl">
                {{ title }}
            </h1>
            <p v-if="description" class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
