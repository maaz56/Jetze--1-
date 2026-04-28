<script setup>
import { ref, watch } from "vue";
import { Input } from "@/components/ui/input";
import { BarcodeIcon, Check } from "lucide-vue-next";

const props = defineProps({
    placeholder: {
        required: false,
        default: "",
    },
    options: {
        type: Array,
        required: true,
        default: () => [],
    },
    resetAutocomplete: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["selectedItem", "barcode"]);

const isOpen = ref(false);
const selectedItem = ref(null);
const barcode = ref(null);
const highlightedIndex = ref(-1);
const query = ref("");

function showOptions() {
    isOpen.value = true;
}

function hideOptions() {
    setTimeout(() => {
        isOpen.value = false;
    }, 200);
}

function handleKeydown(event) {
    if (event.key === "Enter") {
        event.preventDefault();
        if (highlightedIndex.value >= 0) {
            selectedItem.value = props.options[highlightedIndex.value];
            hideOptions();
        } else {
            // Handle barcode
            barcode.value = event.target.value;
            emit("barcode", event.target.value);
            // Reset input after barcode is processed
            event.target.value = "";
        }
    } else if (event.key === "ArrowDown") {
        if (highlightedIndex.value < props.options.length - 1) {
            highlightedIndex.value++;
        }
    } else if (event.key === "ArrowUp") {
        if (highlightedIndex.value > 0) {
            highlightedIndex.value--;
        }
    }
}

watch(query, () => {
    highlightedIndex.value = -1;
});

watch(selectedItem, (newValue) => {
    emit("onSelected", newValue);
});

watch(barcode, (newValue) => {
    if (newValue) {
        emit("onSelectedBarcodeItem", newValue);
        barcode.value = null;
    }
});

watch(props.resetAutocomplete, (newValue) => {
    if (newValue) {
        resetAutocomplete();
    }
});

function resetAutocomplete() {
    selectedItem.value = null;
    query.value = "";
    highlightedIndex.value = -1;
    isOpen.value = false;
    emit("barcode", null);
}
</script>

<template>
    <div class="relative w-full">
        <!-- Autocomplete input -->
        <Input
            @input="
                (event) => {
                    showOptions();
                    query = event;
                    $emit('query', event);
                    selectedItem = null;
                }
            "
            :model-value="selectedItem?.label || ''"
            @blur="hideOptions"
            type="text"
            :placeholder="props.placeholder"
            @keydown="handleKeydown"
        />
        <!-- List of options -->
        <div
            v-if="isOpen && query !== ''"
            class="absolute z-50 bg-white w-full border mt-1 p-2 rounded-lg shadow-md max-h-60 overflow-y-scroll"
        >
            <div v-if="props.options.length > 0 && query.target.value !== ''">
                <div
                    v-for="(option, index) in props.options"
                    :key="index"
                    @mousedown="
                        () => {
                            selectedItem = option;
                            // //console.log(selectedItem);
                        }
                    "
                    :class="{
                        'bg-muted': index === highlightedIndex,
                        'bg-white': index !== highlightedIndex,
                    }"
                    class="flex items-center gap-2 text-sm p-2 rounded-lg cursor-pointer"
                >
                    <Check
                        v-if="selectedItem?.id === option.id"
                        class="w-4 h-4"
                    />
                    {{ option.label }}
                </div>
            </div>
            <div v-else>
                <div
                    class="flex items-center gap-2 text-sm p-2 rounded-lg cursor-pointer"
                >
                    Nothing found.
                </div>
            </div>
        </div>
    </div>
</template>
