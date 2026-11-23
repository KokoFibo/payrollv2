<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    icon: { type: String, default: "pi pi-inbox" },
});
</script>

<template>
    <div class="flex min-h-64 flex-col items-center justify-center px-6 py-12 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
            <i :class="icon" class="text-xl" />
        </span>
        <h3 class="mt-4 text-base font-bold text-slate-950 dark:text-white">{{ title }}</h3>
        <p class="mt-1 max-w-md text-sm leading-6 text-slate-500 dark:text-slate-400">{{ description }}</p>
        <div v-if="$slots.default" class="mt-5">
            <slot />
        </div>
    </div>
</template>
