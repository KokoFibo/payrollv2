<script setup>
const props = defineProps({
    editing: { type: Boolean, default: false },
    field: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: String, default: "" },
    displayValue: { type: String, default: null },
    dayOffset: { type: Number, default: 0 },
    invalid: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    dropActive: { type: Boolean, default: false },
});

const emit = defineEmits([
    "update:modelValue",
    "delete",
    "drag-start",
    "drag-over",
    "drag-end",
    "drop",
]);

function startDrag(event) {
    if (!props.modelValue || props.disabled) {
        event.preventDefault();

        return;
    }

    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", props.field);
    emit("drag-start", event);
}

function dragOver(event) {
    if (props.disabled) {
        return;
    }

    event.preventDefault();
    event.dataTransfer.dropEffect = "move";
    emit("drag-over", event);
}

function drop(event) {
    event.preventDefault();
    emit("drop", event);
}
</script>

<template>
    <div v-if="editing">
        <div
            class="flex min-h-10 items-center gap-1 rounded-xl border px-1.5 py-1 transition"
            :class="dropActive
                ? 'border-brand-400 bg-brand-50 ring-2 ring-brand-200 dark:border-brand-400 dark:bg-brand-500/10 dark:ring-brand-500/30'
                : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900'"
            @dragover="dragOver"
            @drop="drop"
        >
            <span
                v-if="modelValue"
                :draggable="!disabled"
                class="flex h-7 w-6 shrink-0 cursor-grab items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 active:cursor-grabbing dark:hover:bg-slate-800 dark:hover:text-slate-200"
                :title="`Pindahkan ${label}`"
                @dragstart="startDrag"
                @dragend="emit('drag-end')"
            >
                <i class="pi pi-bars text-[10px]" />
            </span>
            <span
                v-else
                class="flex h-7 w-6 shrink-0 items-center justify-center text-slate-300 dark:text-slate-600"
                aria-hidden="true"
            >
                <i class="pi pi-minus text-[10px]" />
            </span>

            <InputText
                :model-value="modelValue"
                type="time"
                class="min-w-0 flex-1"
                :invalid="invalid"
                :disabled="disabled"
                :aria-label="label"
                @update:model-value="emit('update:modelValue', $event)"
            />

            <Button
                v-if="modelValue"
                icon="pi pi-trash"
                text
                rounded
                size="small"
                severity="danger"
                :disabled="disabled"
                :aria-label="`Hapus ${label}`"
                @click.stop="emit('delete')"
            />
        </div>
    </div>
    <span v-else class="inline-flex items-center gap-1.5 whitespace-nowrap">
        <span>{{ displayValue || "-" }}</span>
        <Tag v-if="displayValue && dayOffset" severity="secondary" class="text-[9px]">+{{ dayOffset }}d</Tag>
    </span>
</template>
