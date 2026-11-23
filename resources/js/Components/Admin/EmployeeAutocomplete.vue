<script setup>
import { computed, ref } from "vue";

const props = defineProps({
    modelValue: { type: Object, default: null },
    placeholder: { type: String, default: "Cari nama atau ID karyawan" },
});

const emit = defineEmits(["update:modelValue"]);
const suggestions = ref([]);
const loading = ref(false);

const value = computed({
    get: () => props.modelValue,
    set: (employee) => emit("update:modelValue", employee),
});

async function search(event) {
    const query = String(event.query ?? "").trim();

    if (query.length === 0) {
        suggestions.value = [];

        return;
    }

    loading.value = true;

    try {
        const response = await fetch(
            `${route("admin.employees.options")}?search=${encodeURIComponent(query)}`,
            {
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                credentials: "same-origin",
            },
        );

        suggestions.value = response.ok ? await response.json() : [];
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <AutoComplete
        v-model="value"
        :suggestions="suggestions"
        option-label="label"
        :placeholder="placeholder"
        :loading="loading"
        force-selection
        class="w-full"
        input-class="w-full"
        @complete="search"
    />
</template>
