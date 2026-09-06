<template>
    <div ref="root" class="relative">
        <input
            v-model="search"
            type="text"
            class="form-input pr-9"
            :placeholder="placeholder"
            :disabled="disabled"
            autocomplete="off"
            @focus="openDropdown"
            @input="onInput"
            @keydown.down.prevent="moveHighlight(1)"
            @keydown.up.prevent="moveHighlight(-1)"
            @keydown.enter.prevent="selectHighlighted"
            @keydown.esc.prevent="closeDropdown"
            @blur="handleBlur"
        />
        <button
            type="button"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400"
            :disabled="disabled"
            @mousedown.prevent
            @click="toggleDropdown"
        >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
            </svg>
        </button>

        <div
            v-if="open"
            class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900"
        >
            <button
                v-if="clearable"
                type="button"
                class="block w-full px-3 py-2 text-left text-sm text-slate-500 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
                @mousedown.prevent="selectOption(null)"
            >
                {{ emptyLabel }}
            </button>

            <button
                v-for="(option, index) in filteredOptions"
                :key="String(option.value)"
                type="button"
                class="block w-full px-3 py-2 text-left text-sm transition"
                :class="index === highlightedIndex
                    ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300'
                    : 'text-slate-700 hover:bg-slate-50 dark:text-slate-100 dark:hover:bg-slate-800'"
                @mousedown.prevent="selectOption(option.value)"
                @mouseenter="highlightedIndex = index"
            >
                {{ option.label }}
            </button>

            <div v-if="!filteredOptions.length" class="px-3 py-2 text-sm text-slate-400">
                No matches found
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: { type: String, default: 'Search...' },
    emptyLabel: { type: String, default: 'Select option' },
    disabled: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const root = ref(null);
const open = ref(false);
const search = ref('');
const highlightedIndex = ref(-1);

const normalizedOptions = computed(() => props.options.map((option) => ({
    value: option?.value ?? option?.id ?? null,
    label: option?.label ?? option?.name ?? '',
})));

const selectedOption = computed(() =>
    normalizedOptions.value.find((option) => String(option.value) === String(props.modelValue ?? '')),
);

const filteredOptions = computed(() => {
    const query = search.value.trim().toLowerCase();
    if (!query) return normalizedOptions.value;

    return normalizedOptions.value.filter((option) => option.label.toLowerCase().includes(query));
});

watch(
    () => props.modelValue,
    () => {
        if (!open.value) {
            search.value = selectedOption.value?.label || '';
        }
    },
    { immediate: true },
);

watch(open, (isOpen) => {
    if (isOpen) {
        highlightedIndex.value = filteredOptions.value.length ? 0 : -1;
        search.value = selectedOption.value?.label || '';
    } else {
        highlightedIndex.value = -1;
        search.value = selectedOption.value?.label || '';
    }
});

function openDropdown() {
    if (props.disabled) return;
    open.value = true;
    highlightedIndex.value = filteredOptions.value.length ? 0 : -1;
}

function closeDropdown() {
    open.value = false;
}

function toggleDropdown() {
    if (props.disabled) return;
    open.value = !open.value;
}

function onInput() {
    open.value = true;
    highlightedIndex.value = filteredOptions.value.length ? 0 : -1;

    if (!search.value.trim()) {
        if (props.clearable) emit('update:modelValue', null);
    }
}

function selectOption(value) {
    emit('update:modelValue', value);
    const option = normalizedOptions.value.find((item) => String(item.value) === String(value ?? ''));
    search.value = option?.label || '';
    closeDropdown();
}

function moveHighlight(direction) {
    if (!open.value) {
        openDropdown();
        return;
    }

    if (!filteredOptions.value.length) return;

    const next = highlightedIndex.value + direction;
    if (next < 0) {
        highlightedIndex.value = filteredOptions.value.length - 1;
    } else if (next >= filteredOptions.value.length) {
        highlightedIndex.value = 0;
    } else {
        highlightedIndex.value = next;
    }
}

function selectHighlighted() {
    if (!open.value) {
        openDropdown();
        return;
    }

    if (highlightedIndex.value >= 0 && filteredOptions.value[highlightedIndex.value]) {
        selectOption(filteredOptions.value[highlightedIndex.value].value);
        return;
    }

    const exactMatch = filteredOptions.value.find((option) => option.label.toLowerCase() === search.value.trim().toLowerCase());
    if (exactMatch) {
        selectOption(exactMatch.value);
    }
}

function handleBlur() {
    window.setTimeout(() => {
        search.value = selectedOption.value?.label || '';
        closeDropdown();
    }, 120);
}

function onClickOutside(event) {
    if (root.value && !root.value.contains(event.target)) {
        search.value = selectedOption.value?.label || '';
        closeDropdown();
    }
}

onMounted(() => document.addEventListener('click', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside));
</script>
