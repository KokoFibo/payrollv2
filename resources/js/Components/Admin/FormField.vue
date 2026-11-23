<script setup>
defineProps({
    label: { type: String, required: true },
    error: { type: String, default: "" },
    hint: { type: String, default: "" },
    required: { type: Boolean, default: false },
});
</script>

<template>
    <div class="min-w-0">
        <label class="mb-1.5 flex items-center gap-1 text-xs font-semibold text-slate-700 dark:text-slate-300">
            <span>{{ label }}</span>
            <span v-if="required" class="text-rose-500">*</span>
        </label>
        <slot />
        <p v-if="hint && !error" class="mt-1 text-[11px] leading-5 text-slate-400 dark:text-slate-500">{{ hint }}</p>
        <Message v-if="error" severity="error" size="small" :closable="false" class="mt-1.5">
            {{ error }}
        </Message>
    </div>
</template>
